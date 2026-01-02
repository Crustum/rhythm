<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Queue;

use Cake\Core\ContainerInterface;
use Cake\Queue\Job\Message;
use Cake\Queue\Queue\Processor;
use Enqueue\Consumption\Result;
use Error;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Timed Processor
 *
 * Extends the original Processor to add timing metrics to all events.
 */
class TimedProcessor extends Processor
{
    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface|null $logger Logger instance
     * @param \Cake\Core\ContainerInterface|null $container DI container instance
     */
    public function __construct(?LoggerInterface $logger = null, ?ContainerInterface $container = null)
    {
        parent::__construct($logger, $container);
    }

    /**
     * Process message with timing
     *
     * @param \Interop\Queue\Message $message Message
     * @param \Interop\Queue\Context $context Context
     * @return object|string
     */
    public function process(QueueMessage $message, Context $context): string|object
    {
        $this->dispatchEvent('Processor.message.seen', ['queueMessage' => $message]);

        $jobMessage = new Message($message, $context, $this->container);
        try {
            $jobMessage->getCallable();
        } catch (RuntimeException | Error $e) {
            $this->logger->debug('Invalid callable for message. Rejecting message from queue.');
            $this->dispatchEvent('Processor.message.invalid', ['message' => $jobMessage]);

            return InteropProcessor::REJECT;
        }

        $startTime = microtime(true) * 1000;
        $this->dispatchEvent('Processor.message.start', ['message' => $jobMessage]);

        try {
            $response = $this->processMessage($jobMessage);
        } catch (Throwable $e) {
            $message->setProperty('jobException', $e);

            $this->logger->debug(sprintf('Message encountered exception: %s', $e->getMessage()));
            $this->dispatchEvent('Processor.message.exception', [
                'message' => $jobMessage,
                'exception' => $e,
                'duration' => (int)((microtime(true) * 1000) - $startTime),
            ]);

            return Result::requeue('Exception occurred while processing message');
        }

        $duration = (int)((microtime(true) * 1000) - $startTime);

        if ($response === InteropProcessor::ACK) {
            $this->logger->debug('Message processed successfully');
            $this->dispatchEvent('Processor.message.success', [
                'message' => $jobMessage,
                'duration' => $duration,
            ]);

            return InteropProcessor::ACK;
        }

        if ($response === InteropProcessor::REJECT) {
            $this->logger->debug('Message processed with rejection');
            $this->dispatchEvent('Processor.message.reject', [
                'message' => $jobMessage,
                'duration' => $duration,
            ]);

            return InteropProcessor::REJECT;
        }

        $this->logger->debug('Message processed with failure, requeuing');
        $this->dispatchEvent('Processor.message.failure', [
            'message' => $jobMessage,
            'duration' => $duration,
        ]);

        return InteropProcessor::REQUEUE;
    }
}

<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Queue\Job\Message;
use Crustum\Rhythm\Recorder\Trait\IgnoresTrait;
use Crustum\Rhythm\Recorder\Trait\QueueNameTrait;
use Crustum\Rhythm\Recorder\Trait\SamplingTrait;
use Crustum\Rhythm\Rhythm;

class QueuesRecorder extends BaseRecorder implements EventListenerInterface
{
    use QueueNameTrait;
    use SamplingTrait;
    use IgnoresTrait;

    /**
     * Constructor.
     *
     * @param \Crustum\Rhythm\Rhythm $rhythm Rhythm instance
     * @param array $config Configuration array
     */
    public function __construct(Rhythm $rhythm, array $config = [])
    {
        $config = $config ?: Configure::read('Rhythm.recorders.queues', []);
        parent::__construct($rhythm, $config);

        $this->extractQueuePrefixes();
    }

    /**
     * @return array
     */
    public function implementedEvents(): array
    {
        return [
            'Processor.message.seen' => 'record',
            'Processor.message.invalid' => 'record',
            'Processor.message.start' => 'record',
            'Processor.message.exception' => 'record',
            'Processor.message.success' => 'record',
            'Processor.message.reject' => 'record',
            'Processor.message.failure' => 'record',
        ];
    }

    /**
     * Record metric data.
     *
     * @param mixed $data Data to record
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
        }

        if ($data instanceof EventInterface) {
            $eventName = $data->getName();
            $eventData = $data->getData();

            $queueName = 'default';
            if (isset($eventData['message']) && $eventData['message'] instanceof Message) {
                $jobMessage = $eventData['message'];
                $originalMessage = $jobMessage->getOriginalMessage();
                if (method_exists($originalMessage, 'getKey')) {
                    $queueName = $originalMessage->getKey();
                } else {
                    $parsedBody = $jobMessage->getParsedBody();
                    $queueName = $parsedBody['queue'] ?? 'default';
                }
            } elseif (isset($eventData['queueMessage'])) {
                $queueName = 'default';
            }

            $cleanQueueName = $this->stripQueuePrefix($queueName);

            $jobClassName = 'unknown';
            if (isset($eventData['message']) && $eventData['message'] instanceof Message) {
                $jobMessage = $eventData['message'];
                $parsedBody = $jobMessage->getParsedBody();
                $jobClassName = $parsedBody['class'] ?? 'unknown';

                if (is_array($jobClassName)) {
                    $jobClassName = implode('::', $jobClassName);
                }
            }

            if ($this->shouldIgnore((string)$jobClassName)) {
                return;
            }

            switch ($eventName) {
                case 'Processor.message.start':
                    $this->rhythm->record('queues.processing', $cleanQueueName)->count()->onlyBuckets();
                    break;
                case 'Processor.message.invalid':
                case 'Processor.message.exception':
                case 'Processor.message.reject':
                case 'Consumption.LimitAttemptsExtension.failed':
                    $this->rhythm->record('queues.failed', $cleanQueueName)->count()->onlyBuckets();
                    break;
                case 'Processor.message.success':
                    $this->rhythm->record('queues.processed', $cleanQueueName)->count()->onlyBuckets();
                    break;
                case 'Processor.message.failure':
                    $this->rhythm->record('queues.released', $cleanQueueName)->count()->onlyBuckets();
                    break;
            }
        }
    }
}

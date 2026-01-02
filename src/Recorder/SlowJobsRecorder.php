<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Queue\Job\Message;
use Crustum\Rhythm\Recorder\Trait\IgnoresTrait;
use Crustum\Rhythm\Recorder\Trait\SamplingTrait;
use Crustum\Rhythm\Recorder\Trait\ThresholdsTrait;

/**
 * Slow Jobs Recorder
 *
 * Records metrics for slow queue jobs.
 * Listens to queue job events and records jobs that exceed thresholds.
 */
class SlowJobsRecorder extends BaseRecorder implements EventListenerInterface
{
    use SamplingTrait;
    use IgnoresTrait;
    use ThresholdsTrait;

    /**
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Processor.message.success' => 'handleJobSuccess',
            'Processor.message.exception' => 'handleJobException',
            'Processor.message.failure' => 'handleJobFailure',
        ];
    }

    /**
     * Handle job success event.
     *
     * @param mixed $data Job success data
     * @return void
     */
    public function handleJobSuccess(mixed $data): void
    {
        $this->handleJobCompletion($data, 'success');
    }

    /**
     * Handle job exception event.
     *
     * @param mixed $data Job exception data
     * @return void
     */
    public function handleJobException(mixed $data): void
    {
        $this->handleJobCompletion($data, 'exception');
    }

    /**
     * Handle job failure event.
     *
     * @param mixed $data Job failure data
     * @return void
     */
    public function handleJobFailure(mixed $data): void
    {
        $this->handleJobCompletion($data, 'failure');
    }

    /**
     * Handle job completion (success, exception, or failure).
     *
     * @param mixed $data Job completion data
     * @param string $status Job status
     * @return void
     */
    protected function handleJobCompletion(mixed $data, string $status): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
            return;
        }

        if ($data instanceof EventInterface) {
            $eventData = $data->getData();

            if (!isset($eventData['duration'])) {
                return;
            }

            $jobClassName = $this->extractJobClassName($eventData);

            if ($this->shouldIgnore($jobClassName)) {
                return;
            }

            $duration = (int)$eventData['duration'];

            if ($this->underThreshold($duration, $jobClassName)) {
                return;
            }

            $this->rhythm->record(
                type: 'slow_job',
                key: json_encode([$jobClassName, $status], JSON_THROW_ON_ERROR),
                value: $duration,
            )->max()->count();
        }
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
            return;
        }

        if (is_array($data) && isset($data['type'], $data['job_name'])) {
            if ($data['type'] === 'start') {
                return;
            }

            if (isset($data['duration'])) {
                $jobName = $data['job_name'];
                $duration = (int)$data['duration'];

                if ($this->shouldIgnore($jobName)) {
                    return;
                }

                if ($this->underThreshold($duration, $jobName)) {
                    return;
                }

                $status = $data['status'] ?? 'unknown';
                $this->rhythm->record(
                    type: 'slow_job',
                    key: json_encode([$jobName, $status], JSON_THROW_ON_ERROR),
                    value: $duration,
                )->max()->count();
            }
        }
    }

    /**
     * Extract job class name from event data.
     *
     * @param mixed $data Event data
     * @return string Job class name
     */
    protected function extractJobClassName(mixed $data): string
    {
        if (is_array($data) && isset($data['message']) && $data['message'] instanceof Message) {
            $jobMessage = $data['message'];
            $parsedBody = $jobMessage->getParsedBody();
            $jobClassName = $parsedBody['class'] ?? 'unknown';

            if (is_array($jobClassName)) {
                $jobClassName = implode('::', $jobClassName);
            }

            return (string)$jobClassName;
        }

        return 'unknown';
    }
}

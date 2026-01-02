<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Http\Client\Request;
use Crustum\Rhythm\Recorder\Trait\GroupsTrait;
use Crustum\Rhythm\Recorder\Trait\IgnoresTrait;
use Crustum\Rhythm\Recorder\Trait\SamplingTrait;
use Crustum\Rhythm\Recorder\Trait\ThresholdsTrait;
use Crustum\Rhythm\Rhythm;
use SplObjectStorage;

/**
 * Listens for HttpClient events to record slow outgoing requests.
 */
class OutgoingRequestRecorder extends BaseRecorder implements EventListenerInterface
{
    use SamplingTrait;
    use IgnoresTrait;
    use ThresholdsTrait;
    use GroupsTrait;

    /**
     * A map to store request start times.
     *
     * @var \SplObjectStorage<\Cake\Http\Client\Request, float>
     */
    private SplObjectStorage $startTimes;

    /**
     * Constructor.
     *
     * @param \Crustum\Rhythm\Rhythm $rhythm Rhythm service instance.
     * @param array<string, mixed> $config Configuration for the recorder.
     */
    public function __construct(Rhythm $rhythm, array $config = [])
    {
        parent::__construct($rhythm, $config);
        $this->startTimes = new SplObjectStorage();
    }

    /**
     * Returns the events this listener is interested in.
     *
     * @return array<string, string>
     */
    public function implementedEvents(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        return [
            'HttpClient.beforeSend' => 'beforeSend',
            'HttpClient.afterSend' => 'afterSend',
        ];
    }

    /**
     * Records the start time of an outgoing HTTP request.
     *
     * @param \Cake\Event\Event $event The event object.
     * @return void
     */
    public function beforeSend(Event $event): void
    {
        $request = $event->getData('request');
        if ($request instanceof Request) {
            $this->startTimes[$request] = microtime(true);
        }
    }

    /**
     * Calculates the duration of an outgoing HTTP request and records it if slow.
     *
     * @param \Cake\Event\Event $event The event object.
     * @return void
     */
    public function afterSend(Event $event): void
    {
        $request = $event->getData('request');
        if (!($request instanceof Request) || !$this->startTimes->contains($request)) {
            return;
        }

        $startTime = $this->startTimes[$request];
        $endTime = microtime(true);
        $duration = (int)(($endTime - $startTime) * 1000);

        $this->startTimes->detach($request);

        $uri = (string)$request->getUri();

        if ($this->shouldIgnore($uri)) {
            return;
        }

        if ($this->underThreshold($duration, $uri)) {
            return;
        }

        $this->record([
            'request' => $request,
            'duration' => $duration,
        ]);
    }

    /**
     * Records a slow request.
     *
     * @param mixed $data The data to record. Expects an array with 'request' and 'duration'.
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!is_array($data) || !isset($data['request'], $data['duration'])) {
            return;
        }

        /** @var \Cake\Http\Client\Request $request */
        $request = $data['request'];
        $duration = $data['duration'];

        $uri = (string)$request->getUri();
        $groupedUri = $this->group($uri);

        $this->rhythm->record(
            type: 'slow_outgoing_request',
            key: json_encode([
                $request->getMethod(),
                $groupedUri,
            ], JSON_THROW_ON_ERROR),
            value: $duration,
        )->max()->count();
    }
}

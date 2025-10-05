<?php
declare(strict_types=1);

namespace Rhythm\Recorder;

use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Rhythm\Event\SlowRequestEvent;
use Rhythm\Recorder\Trait\IgnoresTrait;
use Rhythm\Recorder\Trait\SamplingTrait;
use Rhythm\Recorder\Trait\ThresholdsTrait;
use Rhythm\Rhythm;

/**
 * Slow Requests Recorder
 *
 * Records metrics for slow HTTP requests.
 */
class SlowRequestsRecorder extends BaseRecorder implements EventListenerInterface
{
    use SamplingTrait;
    use IgnoresTrait;
    use ThresholdsTrait;

    /**
     * @param \Rhythm\Rhythm $rhythm Rhythm instance
     * @param array $config Configuration array
     */
    public function __construct(Rhythm $rhythm, array $config = [])
    {
        $config = $config ?: Configure::read('Rhythm.recorders.slow_requests', []);
        parent::__construct($rhythm, $config);
    }

    /**
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Rhythm.slowRequest' => 'handleSlowRequest',
        ];
    }

    /**
     * @param \Rhythm\Event\SlowRequestEvent $event Slow request event
     * @return void
     */
    public function handleSlowRequest(SlowRequestEvent $event): void
    {
        $this->record($event);
    }

    /**
     * @param mixed $data Data to record
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$this->isEnabled() || !$this->shouldSample() || !$data instanceof SlowRequestEvent) {
            return;
        }

        $path = $data->getRequest()->getUri()->getPath();

        if ($this->shouldIgnore($path)) {
            return;
        }

        if ($this->underThreshold($data->getDuration(), $path)) {
            return;
        }

        $request = $data->getRequest();
        $response = $data->getResponse();
        $duration = $data->getDuration();

        $method = $request->getMethod();
        $statusCode = $response->getStatusCode();

        $key = json_encode([$method, $path, $statusCode], JSON_THROW_ON_ERROR);
        $this->rhythm->record('slow_request', $key, $duration)->max()->count();

        if ($request->getAttribute('identity')) {
            $userId = $request->getAttribute('identity')->getIdentifier();
            $this->rhythm->record('slow_user_request', (string)$userId)->count();
        }
    }
}

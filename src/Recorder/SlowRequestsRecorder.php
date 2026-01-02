<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Crustum\Rhythm\Event\SlowRequestEvent;
use Crustum\Rhythm\Recorder\Trait\IgnoresTrait;
use Crustum\Rhythm\Recorder\Trait\SamplingTrait;
use Crustum\Rhythm\Recorder\Trait\ThresholdsTrait;
use Crustum\Rhythm\Rhythm;

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
     * @param \Crustum\Rhythm\Rhythm $rhythm Rhythm instance
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
     * @param \Crustum\Rhythm\Event\SlowRequestEvent $event Slow request event
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

<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Crustum\Rhythm\Recorder\Trait\IgnoresTrait;
use Crustum\Rhythm\Recorder\Trait\SamplingTrait;
use Crustum\Rhythm\Rhythm;

/**
 * User Requests Recorder
 *
 * Records metrics for authenticated user requests.
 */
class UserRequestsRecorder extends BaseRecorder implements EventListenerInterface
{
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
        $config = $config ?: Configure::read('Rhythm.recorders.user_requests', []);
        parent::__construct($rhythm, $config);
    }

    /**
     * Implemented events.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Controller.initialize' => 'recordUserRequest',
        ];
    }

    /**
     * Record user request.
     *
     * @param \Cake\Event\EventInterface $event Event instance
     * @return void
     */
    public function recordUserRequest(EventInterface $event): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
            return;
        }

        $request = $event->getData('request');
        if ($request && $request->getAttribute('identity')) {
            $path = $request->getUri()->getPath();

            if ($this->shouldIgnore($path)) {
                return;
            }

            $userId = $request->getAttribute('identity')->getIdentifier();
            $this->rhythm->record('user_request', (string)$userId);
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

        if (is_array($data) && isset($data['user_id'])) {
            $this->rhythm->record('user_request', (string)$data['user_id']);
        }
    }
}

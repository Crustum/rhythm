<?php
declare(strict_types=1);

namespace Rhythm\Recorder;

use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Rhythm\Event\SlowQueryEvent;
use Rhythm\Recorder\Trait\IgnoresTrait;
use Rhythm\Recorder\Trait\SamplingTrait;
use Rhythm\Recorder\Trait\ThresholdsTrait;
use Rhythm\Rhythm;

/**
 * Slow Queries Recorder
 *
 * Records metrics for slow database queries using CakePHP's event system.
 * Listens to SlowQueryEvent events dispatched by RhythmQueryLogger.
 */
class SlowQueriesRecorder extends BaseRecorder implements EventListenerInterface
{
    use SamplingTrait;
    use IgnoresTrait;
    use ThresholdsTrait;

    /**
     * Constructor.
     *
     * @param \Rhythm\Rhythm $rhythm Rhythm instance
     * @param array $config Configuration array
     */
    public function __construct(Rhythm $rhythm, array $config = [])
    {
        $config = $config ?: Configure::read('Rhythm.recorders.slow_queries', []);
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
            'Rhythm.slowQuery' => 'handleSlowQuery',
        ];
    }

    /**
     * Handle slow query event.
     *
     * @param \Rhythm\Event\SlowQueryEvent $event Slow query event
     * @return void
     */
    public function handleSlowQuery(SlowQueryEvent $event): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
            return;
        }

        $sql = $event->getSql();

        if ($this->shouldIgnore($sql)) {
            return;
        }

        if ($this->underThreshold($event->getDuration(), $sql)) {
            return;
        }

        $duration = $event->getDuration();
        $location = $event->getLocation();

        $maxQueryLength = $this->config['max_query_length'] ?? 1000;
        if (strlen($sql) > $maxQueryLength) {
            $sql = substr($sql, 0, $maxQueryLength - 3) . '...';
        }

        $key = json_encode([$sql, $location], JSON_THROW_ON_ERROR);
        $this->rhythm->record('slow_query', $key, $duration)->count()->avg()->min()->max();
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

        if (is_array($data) && isset($data['sql']) && isset($data['duration'])) {
            if ($this->shouldIgnore($data['sql'])) {
                return;
            }

            if ($this->underThreshold($data['duration'], $data['sql'])) {
                return;
            }

            $location = $data['location'] ?? null;
            $key = json_encode([$data['sql'], $location], JSON_THROW_ON_ERROR);
            $this->rhythm->record('slow_query', $key, $data['duration']);
        }
    }
}

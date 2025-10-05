<?php
declare(strict_types=1);

namespace Rhythm\Recorder\Trait;

use Cake\Cache\Cache;
use Cake\I18n\DateTime;
use DateInterval;
use Rhythm\Event\IsolatedBeat;
use Rhythm\Event\SharedBeat;

/**
 * Provides throttling functionality for recorders.
 */
trait ThrottlingTrait
{
    /**
     * Determine if the recorder is ready to record another snapshot.
     *
     * @param \DateInterval|int $interval The interval to throttle.
     * @param \Rhythm\Event\SharedBeat|\Rhythm\Event\IsolatedBeat $event The beat event.
     * @param callable $callback The callback to execute.
     * @param string|null $key An optional key for the throttle.
     * @return void
     */
    public function throttle(
        DateInterval|int $interval,
        SharedBeat|IsolatedBeat $event,
        callable $callback,
        ?string $key = null,
    ): void {
        $key ??= static::class;

        if ($event instanceof SharedBeat) {
            $key = $event->getInstance() . ":{$key}";
        }

        $cacheKey = 'rhythm:throttle:' . $key;

        $lastRunAt = Cache::read($cacheKey, 'rhythm');

        if ($lastRunAt !== null) {
            $future = new DateTime($lastRunAt);
            if (is_int($interval)) {
                $future = $future->modify("+{$interval} seconds");
            } else {
                $future = $future->add($interval);
            }

            if ($future->isFuture()) {
                return;
            }
        }

        $callback($event);

        Cache::write($cacheKey, $event->getTimestamp()->format('Y-m-d H:i:s'), 'rhythm');
    }
}

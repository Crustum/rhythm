<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder\Trait;

use Cake\Collection\Collection;

/**
 * Provides threshold checking for recorders.
 */
trait ThresholdsTrait
{
    /**
     * Determine if the duration is under the configured threshold.
     *
     * @param float|int $duration The duration to check.
     * @param string $key The key to check against custom thresholds.
     * @return bool
     */
    public function underThreshold(int|float $duration, string $key): bool
    {
        return $duration < $this->threshold($key);
    }

    /**
     * Get the threshold for the given key.
     *
     * @param string $string The key to find the threshold for.
     * @param string|null $recorder The recorder class name.
     * @return int
     */
    public function threshold(string $string, ?string $recorder = null): int
    {
        $config = $this->config['threshold'] ?? 1000;

        if (!is_array($config)) {
            return (int)$config;
        }

        $patterns = (new Collection($config))
            ->filter(function ($value, $key) use ($string) {
                return $key !== 'default' && preg_match($key, $string) === 1;
            })
            ->map(function ($value, $key) {
                return (int)$value;
            })
            ->first();

        if ($patterns) {
            return (int)$patterns;
        }

        return (int)($config['default'] ?? 1000);
    }
}

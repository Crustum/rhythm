<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder\Trait;

use Cake\Collection\Collection;

/**
 * Provides ignoring functionality for recorders.
 *
 * This trait allows recorders to ignore specific values based on regex patterns
 * defined in their configuration.
 *
 * Usage:
 * ```php
 * class MyRecorder extends BaseRecorder
 * {
 *     use IgnoresTrait;
 *
 *     public function record($data): void
 *     {
 *         if ($this->shouldIgnore($data['path'])) {
 *             return;
 *         }
 *         // Record the data...
 *     }
 * }
 * ```
 *
 * Configuration:
 * ```php
 * 'recorders' => [
 *     MyRecorder::class => [
 *         'ignore' => [
 *             '#^/admin#', // Ignore admin routes
 *             '/^system:/', // Ignore system messages
 *         ],
 *     ],
 * ],
 * ```
 */
trait IgnoresTrait
{
    /**
     * Determine if the given value should be ignored.
     *
     * Checks the value against all ignore patterns defined in the recorder's
     * configuration. Returns true if any pattern matches.
     *
     * @param string $value The value to check against ignore patterns.
     * @return bool True if the value should be ignored, false otherwise.
     */
    public function shouldIgnore(string $value): bool
    {
        $ignorePatterns = (array)($this->getConfig()['ignore'] ?? []);
        if ($ignorePatterns === []) {
            return false;
        }

        return (new Collection($ignorePatterns))
            ->some(fn(string $pattern): bool => preg_match($pattern, $value) > 0);
    }

    /**
     * Get all ignore patterns for this recorder.
     *
     * @return array<string> Array of ignore patterns.
     */
    protected function getIgnorePatterns(): array
    {
        return (array)($this->getConfig()['ignore'] ?? []);
    }

    /**
     * Check if ignore patterns are configured for this recorder.
     *
     * @return bool True if ignore patterns are configured, false otherwise.
     */
    protected function hasIgnorePatterns(): bool
    {
        return !empty($this->getIgnorePatterns());
    }
}

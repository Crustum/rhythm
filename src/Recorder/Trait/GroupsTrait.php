<?php
declare(strict_types=1);

namespace Rhythm\Recorder\Trait;

use Throwable;

/**
 * Provides grouping functionality for recorders.
 *
 * This trait allows recorders to group values based on regex patterns
 * defined in their configuration.
 *
 * Usage:
 * ```php
 * class MyRecorder extends BaseRecorder
 * {
 *     use GroupsTrait;
 *
 *     public function record($data): void
 *     {
 *         $groupedValue = $this->group($data['path']);
 *         // Record the grouped value...
 *     }
 * }
 * ```
 *
 * Configuration:
 * ```php
 * 'recorders' => [
 *     MyRecorder::class => [
 *         'groups' => [
 *             '#^/api/v1/users/(\d+)#' => '/api/v1/users/*', // Group user IDs
 *             '#^/admin/(.+)#' => '/admin/*', // Group admin routes
 *         ],
 *     ],
 * ],
 * ```
 */
trait GroupsTrait
{
    /**
     * Group the value based on the configured grouping rules.
     *
     * Checks the value against all group patterns defined in the recorder's
     * configuration. Returns the first matching group or the original value
     * if no patterns match.
     *
     * @param string $value The value to group.
     * @return string The grouped value.
     */
    public function group(string $value): string
    {
        $groupPatterns = (array)($this->getConfig()['groups'] ?? []);

        foreach ($groupPatterns as $pattern => $replacement) {
            try {
                $group = preg_replace($pattern, (string)$replacement, $value, -1, $count);

                if ($count > 0 && $group !== null) {
                    return $group;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $value;
    }

    /**
     * Get all group patterns for this recorder.
     *
     * @return array<string, string> Array of group patterns with replacements.
     */
    public function getGroupPatterns(): array
    {
        return (array)($this->getConfig()['groups'] ?? []);
    }

    /**
     * Check if group patterns are configured for this recorder.
     *
     * @return bool True if group patterns are configured, false otherwise.
     */
    public function hasGroupPatterns(): bool
    {
        return !empty($this->getGroupPatterns());
    }
}

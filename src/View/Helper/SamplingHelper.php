<?php
declare(strict_types=1);

namespace Crustum\Rhythm\View\Helper;

use Cake\View\Helper;

/**
 * Sampling Helper
 *
 * Provides sampling-aware formatting for displaying magnified values
 * with sampling indicators and tooltips.
 */
class SamplingHelper extends Helper
{
    /**
     * Default configuration
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [];

    /**
     * Format a magnified value for display with sampling indicator
     *
     * @param float|int $value Value to format
     * @param float|int|null $rawValue Original raw value (for tooltip)
     * @param float $sampleRate Sample rate (1.0 = no sampling, 0.1 = 10% sampling)
     * @return string Formatted value with sampling indicator if applicable
     */
    public function formatMagnifiedValue(
        float|int $value,
        float|int|null $rawValue = null,
        float $sampleRate = 1.0,
    ): string {
        if ($sampleRate >= 1.0) {
            return number_format($value);
        }

        $rawValue ??= $value;
        $magnifiedValue = $this->magnifyValue($value, $sampleRate);

        return sprintf(
            '<span title="Sample rate: %s, Raw value: %s">~%s</span>',
            $sampleRate,
            number_format($rawValue),
            number_format($magnifiedValue),
        );
    }

    /**
     * Magnify a value based on sample rate
     *
     * @param float|int $value Raw value to magnify
     * @param float $sampleRate Sample rate
     * @return float Magnified value
     */
    public function magnifyValue(float|int $value, float $sampleRate): float
    {
        return $sampleRate < 1.0 ? $value * 1 / $sampleRate : (float)$value;
    }

    /**
     * Check if sampling is enabled
     *
     * @param float $sampleRate Sample rate
     * @return bool True if sampling is enabled (sample_rate < 1.0)
     */
    public function isSamplingEnabled(float $sampleRate): bool
    {
        return $sampleRate < 1.0;
    }

    /**
     * Format a simple magnified value without HTML (for use in attributes, etc.)
     *
     * @param float|int $value Value to format
     * @param float $sampleRate Sample rate
     * @return string Formatted value with ~ prefix if sampled
     */
    public function formatSimpleMagnifiedValue(float|int $value, float $sampleRate = 1.0): string
    {
        if ($sampleRate >= 1.0) {
            return number_format($value);
        }

        $magnifiedValue = $this->magnifyValue($value, $sampleRate);

        return '~' . number_format($magnifiedValue);
    }

    /**
     * Get sampling tooltip text
     *
     * @param float|int $rawValue Raw value
     * @param float $sampleRate Sample rate
     * @return string Tooltip text
     */
    public function getSamplingTooltip(float|int $rawValue, float $sampleRate): string
    {
        return sprintf('Sample rate: %s, Raw value: %s', $sampleRate, number_format($rawValue));
    }
}

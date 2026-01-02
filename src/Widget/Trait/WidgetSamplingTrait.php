<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget\Trait;

use InvalidArgumentException;

/**
 * WidgetSamplingTrait
 *
 * Provides sampling magnification functionality for widgets that use sampled data.
 * This trait should be used by widgets that need to handle data collected with reduced sample rates.
 */
trait WidgetSamplingTrait
{
    /**
     * Get sample rate from recorder configuration
     *
     * @return float Sample rate (1.0 = no sampling, 0.1 = 10% sampling)
     */
    protected function getSampleRate(): float
    {
        try {
            return (float)($this->getRecorderSetting('sample_rate') ?? 1.0);
        } catch (InvalidArgumentException) {
            return 1.0;
        }
    }

    /**
     * Check if sampling is enabled for this widget
     *
     * @return bool True if sampling is enabled (sample_rate < 1.0)
     */
    protected function isSamplingEnabled(): bool
    {
        return $this->getSampleRate() < 1.0;
    }

    /**
     * Magnify a value based on sample rate
     *
     * @param float|int $value Raw value to magnify
     * @param float|null $sampleRate Sample rate (uses widget's rate if null)
     * @return float Magnified value
     */
    protected function magnifyValue(float|int $value, ?float $sampleRate = null): float
    {
        $sampleRate ??= $this->getSampleRate();

        return $sampleRate < 1.0 ? round($value * 1 / $sampleRate) : $value;
    }

    /**
     * Magnify chart data array (recursively processes nested arrays)
     *
     * @param array $chartData Chart data to magnify
     * @param float|null $sampleRate Sample rate (uses widget's rate if null)
     * @return array Magnified chart data
     */
    protected function magnifyChartData(array $chartData, ?float $sampleRate = null): array
    {
        if (!$this->isSamplingEnabled() && $sampleRate === null) {
            return $chartData;
        }

        $sampleRate ??= $this->getSampleRate();
        $magnifiedData = [];

        foreach ($chartData as $key => $value) {
            if (is_array($value)) {
                $magnifiedData[$key] = $this->magnifyChartData($value, $sampleRate);
            } elseif (is_numeric($value)) {
                $magnifiedData[$key] = (int)$this->magnifyValue((float)$value, $sampleRate);
            } else {
                $magnifiedData[$key] = is_numeric($value) ? (int)round((float)$value) : $value;
            }
        }

        return $magnifiedData;
    }

    /**
     * Calculate scaled Y-axis maximum for chart data
     *
     * @param array $chartData Chart data to analyze
     * @param float|null $sampleRate Sample rate (uses widget's rate if null)
     * @return float Scaled Y-axis maximum
     */
    protected function calculateScaledYMax(array $chartData, ?float $sampleRate = null): float
    {
        $sampleRate ??= $this->getSampleRate();
        $maxValue = $this->findMaxValue($chartData);

        return $this->magnifyValue($maxValue, $sampleRate);
    }

    /**
     * Find maximum numeric value in nested array
     *
     * @param array $data Data to search
     * @return float Maximum value found
     */
    private function findMaxValue(array $data): float
    {
        $maxValue = 0.0;

        foreach ($data as $value) {
            if (is_array($value)) {
                $maxValue = max($maxValue, $this->findMaxValue($value));
            } elseif (is_numeric($value)) {
                $maxValue = max($maxValue, (float)$value);
            }
        }

        return $maxValue;
    }

    /**
     * Get sampling information for JavaScript charts
     *
     * @return array Sampling configuration for chart JavaScript
     */
    protected function getSamplingConfig(): array
    {
        if (!$this->isSamplingEnabled()) {
            return [
                'sample_rate' => 1.0,
                'is_sampled' => false,
            ];
        }

        return [
            'sample_rate' => $this->getSampleRate(),
            'is_sampled' => true,
        ];
    }
}

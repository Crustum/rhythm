<?php
declare(strict_types=1);

namespace Rhythm\Widget\Trait;

use DateTime;

/**
 * WidgetChartFormattingTrait
 *
 * Provides common chart data formatting logic for widgets (Queues, Redis, etc).
 * Generalizes chart config and legend preparation for time-series metrics.
 */
trait WidgetChartFormattingTrait
{
    /**
     * Prepare chart data and configurations for multiple metric groups
     *
     * @param array $seriesData Array of metric group => [connection => data]
     * @param array $labels Array of metric group => [metric => label]
     * @param string $prefix Chart ID prefix
     * @return array
     */
    protected function prepareWidgetChartData(array $seriesData, array $labels, string $prefix = 'widget'): array
    {
        $chartData = [];
        $allConnections = [];
        foreach ($seriesData as $group => $groupData) {
            $allConnections = array_merge($allConnections, array_keys($groupData));
        }
        $allConnections = array_unique($allConnections);

        foreach ($allConnections as $connection) {
            $connection = (string)$connection;
            $chartData[$connection] = [];
            foreach ($seriesData as $group => $groupData) {
                $metrics = $groupData[$connection] ?? null;
                $chartData[$connection][$group] = $this->prepareWidgetChartConfig(
                    $connection,
                    $group,
                    $metrics,
                    $labels[$group] ?? [],
                    $prefix,
                );
            }
        }

        return $chartData;
    }

    /**
     * Prepare chart config for a single metric group
     *
     * @param string $connection Connection or queue name
     * @param string $group Metric group/type (e.g. memory, activity)
     * @param mixed $metrics Raw metric data
     * @param array $metricLabels Metric => label
     * @param string $prefix Chart ID prefix
     * @return array|null
     */
    protected function prepareWidgetChartConfig(
        string $connection,
        string $group,
        mixed $metrics,
        array $metricLabels,
        string $prefix,
        array $colors = [],
    ): ?array {
        if (!$metrics) {
            return null;
        }
        if (is_object($metrics) && method_exists($metrics, 'toArray')) {
            $metrics = $metrics->toArray();
        }
        if (empty($metrics)) {
            return null;
        }
        $chartData = [];
        $legendData = [];
        $chartColors = [];
        foreach ($metrics as $metric => $series) {
            $lastValue = null;
            $label = $metricLabels[$metric] ?? $metric;
            foreach ($series as $timestamp => $value) {
                if ($value !== null) {
                    $date = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
                    $formattedTime = $date ? $date->format('d.m.y, H:i') : $timestamp;
                    $chartData[$label][$formattedTime] = (int)$value;
                    $lastValue = (int)$value;
                }
            }
            $legendData[$metric] = [
                'label' => $label,
                'total' => $lastValue,
            ];

            $chartColors[$label] = $this->getMetricColor($metric, $colors);
        }
        $chartId = $prefix . '-' . $group . '-' . str_replace([':', '-', '.'], ['-', '', ''], $connection);

        return [
            'chartId' => $chartId,
            'chartData' => $chartData,
            'legendData' => $legendData,
            'colors' => $chartColors,
            'type' => $group,
        ];
    }

    /**
     * Unpack graph data
     *
     * @param mixed $data Data to convert
     * @return void
     */
    protected function unpackGraphData(mixed &$data): void
    {
        if (empty($data)) {
            return;
        }

        $data = $data->toArray();
        foreach ($data as &$graph) {
            $graph = $graph->toArray();
        }
    }

    /**
     * Format bytes as human-readable string (MB, GB, etc)
     *
     * @param float|int $bytes
     * @return string
     */
    protected function formatBytes(float|int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Map colors to both original and config-driven labels
     *
     * @param array<string, array<string, string>> $colors Original colors (grouped)
     * @param array<string, array<string, string>> $labels Optional labels (grouped)
     * @return array<string, array<string, string>> Colors mapped to both formats
     */
    protected function mapColorsWithLabels(array $colors, array $labels = []): array
    {
        foreach ($colors as $group => &$groupColors) {
            if (!empty($labels[$group])) {
                foreach ($labels[$group] as $metric => $label) {
                    if (isset($groupColors[$metric])) {
                        $groupColors[$label] = $groupColors[$metric];
                    }
                }
            } else {
                foreach ($groupColors as $metric => $color) {
                    $formatted = str_replace('_', ' ', ucfirst(strtolower($metric)));
                    $groupColors[$formatted] = $color;
                }
            }
        }

        return $colors;
    }

    /**
     * Get color for metric (tries both formats)
     *
     * @param string $metric Metric name
     * @param array<string, string> $colors Available colors
     * @return string Color value
     */
    protected function getMetricColor(string $metric, array $colors): string
    {
        return $colors[$metric]
            ?? $colors[str_replace('_', ' ', ucfirst(strtolower($metric)))]
            ?? $colors['primary']
            ?? '#3b82f6';
    }

    /**
     * Prepare sampling-aware chart data with magnification
     *
     * @param array $seriesData Array of metric group => [connection => data]
     * @param array $labels Array of metric group => [metric => label]
     * @param string $prefix Chart ID prefix
     * @return array Chart data with sampling information
     */
    protected function prepareSamplingAwareChartData(array $seriesData, array $labels, string $prefix = 'widget'): array
    {
        $chartData = $this->prepareWidgetChartData($seriesData, $labels, $prefix);

        if ($this->isSamplingEnabled()) {
            $chartData = $this->magnifyChartData($chartData);
            $chartData['sample_rate'] = $this->getSampleRate();
            $chartData['is_sampled'] = true;
        }

        return $chartData;
    }

    /**
     * Prepare sampling-aware chart config with scaled Y-axis
     *
     * @param string $connection Connection or queue name
     * @param string $group Metric group/type (e.g. memory, activity)
     * @param mixed $metrics Raw metric data
     * @param array $metricLabels Metric => label
     * @param string $prefix Chart ID prefix
     * @return array|null Chart config with sampling information
     */
    protected function prepareSamplingAwareChartConfig(
        string $connection,
        string $group,
        mixed $metrics,
        array $metricLabels,
        string $prefix,
    ): ?array {
        $chartConfig = $this->prepareWidgetChartConfig($connection, $group, $metrics, $metricLabels, $prefix);

        if (!$chartConfig) {
            return null;
        }

        if ($this->isSamplingEnabled()) {
            $chartConfig['chartData'] = $this->magnifyChartData($chartConfig['chartData']);

            foreach ($chartConfig['legendData'] as &$legendItem) {
                if (isset($legendItem['total']) && is_numeric($legendItem['total'])) {
                    $legendItem['raw_total'] = $legendItem['total'];
                    $legendItem['total'] = (int)$this->magnifyValue($legendItem['total']);
                }
            }

            $chartConfig['sample_rate'] = $this->getSampleRate();
            $chartConfig['is_sampled'] = true;

            $chartConfig['y_max'] = $this->calculateScaledYMax($chartConfig['chartData']);
        }

        return $chartConfig;
    }

    /**
     * Prepare sampling-aware chart data with magnification for multiple charts (1-N relationship) with per-group sample rates
     *
     * @param array $seriesData Array of metric group => [connection => data]
     * @param array $labels Array of metric group => [metric => label]
     * @param string $prefix Chart ID prefix
     * @param array $colors Optional colors for the charts
     * @param array $sampleRates Per-group sample rates, e.g. ['activity' => 0.2, 'statistics' => 0.3]
     * @return array Chart data with sampling information and proper color mapping
     */
    protected function prepareSamplingAwareMultiChartDataWithRates(
        array $seriesData,
        array $labels,
        string $prefix = 'widget',
        array $colors = [],
        array $sampleRates = [],
    ): array {
        $chartData = [];
        $allConnections = [];

        foreach ($seriesData as $group => $groupData) {
            $allConnections = array_merge($allConnections, array_keys($groupData));
        }
        $allConnections = array_unique($allConnections);

        foreach ($allConnections as $connection) {
            $chartData[$connection] = [];

            foreach ($seriesData as $group => $groupData) {
                $connection = (string)$connection;
                $metrics = $groupData[$connection] ?? null;
                $groupColors = $colors[$group] ?? [];
                $groupSampleRate = $sampleRates[$group] ?? 1.0;
                $chartConfig = $this->prepareWidgetChartConfig(
                    $connection,
                    $group,
                    $metrics,
                    $labels[$group] ?? [],
                    $prefix,
                    $groupColors,
                );

                if ($chartConfig) {
                    if ($groupSampleRate < 1.0) {
                        $chartConfig['chartData'] = $this->magnifyChartData(
                            $chartConfig['chartData'],
                            $groupSampleRate,
                        );
                        foreach ($chartConfig['legendData'] as &$legendItem) {
                            if (isset($legendItem['total']) && is_numeric($legendItem['total'])) {
                                $legendItem['raw_total'] = $legendItem['total'];
                                $legendItem['total'] = (int)$this->magnifyValue($legendItem['total'], $groupSampleRate);
                            }
                        }
                        $chartConfig['sample_rate'] = $groupSampleRate;
                        $chartConfig['is_sampled'] = true;
                    } else {
                        $chartConfig['sample_rate'] = 1.0;
                        $chartConfig['is_sampled'] = false;
                    }
                    $chartData[$connection][$group] = $chartConfig;
                }
            }
        }

        return $chartData;
    }

    /**
     * Transform chart data to match template expectations
     *
     * @param array $chartData Nested chart data from trait methods
     * @param array $expectedGroups Expected metric groups (e.g. ['activity', 'statistics'])
     * @return array Transformed chart data with flat structure
     */
    protected function transformChartDataForTemplates(
        array $chartData,
        array $expectedGroups = ['activity', 'statistics'],
    ): array {
        $transformedChartData = [];

        foreach ($chartData as $queueName => $queueChartData) {
            if (is_array($queueChartData)) {
                $transformedChartData[$queueName] = [];

                foreach ($expectedGroups as $group) {
                    if (isset($queueChartData[$group])) {
                        $groupData = $queueChartData[$group];
                        $transformedChartData[$queueName][$group] = [
                            'chartId' => $groupData['chartId'] ?? '',
                            'chartData' => $groupData['chartData'] ?? [],
                            'legendData' => $groupData['legendData'] ?? [],
                            'colors' => $groupData['colors'] ?? [],
                            'type' => $groupData['type'] ?? $group,
                            'is_sampled' => $groupData['is_sampled'] ?? false,
                            'sample_rate' => $groupData['sample_rate'] ?? 1.0,
                        ];
                    }
                }
            }
        }

        return $transformedChartData;
    }

    /**
     * Format legend value with sampling indicator
     *
     * @param float|int $value Value to format
     * @param float|int|null $rawValue Original raw value
     * @return string Formatted value
     */
    protected function formatLegendValue(float|int $value, float|int|null $rawValue = null): string
    {
        if (!$this->isSamplingEnabled()) {
            return number_format($value);
        }

        $rawValue ??= $value;
        $magnifiedValue = (int)$this->magnifyValue($value);
        $sampleRate = $this->getSampleRate();

        return sprintf(
            '<span title="Sample rate: %s, Raw value: %s">~%s</span>',
            $sampleRate,
            number_format($rawValue),
            number_format($magnifiedValue),
        );
    }
}

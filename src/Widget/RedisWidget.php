<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget;

use Crustum\Rhythm\Widget\Trait\WidgetChartFormattingTrait;
use Crustum\Rhythm\Widget\Trait\WidgetSamplingTrait;
use DateTime;
use Exception;

/**
 * Redis Widget
 *
 * Displays Redis server status with interactive charts.
 */
class RedisWidget extends BaseWidget
{
    use WidgetChartFormattingTrait;
    use WidgetSamplingTrait;

    /**
     * Get widget data
     *
     * @param array<string, mixed> $options Widget options (period, sort, etc.)
     * @return array<string, mixed>
     */
    public function getData(array $options = []): array
    {
        $period = $options['period'] ?? $this->getConfigValue('period', 60);
        $connections = $options['connections'] ?? $this->getConfigValue('connections', ['default']);

        return $this->remember(function () use ($period) {
            try {
                $memory = $this->getEnabledMetrics()['memory_usage'] ?
                    $this->rhythm->getStorage()->graph(['redis_used_memory', 'redis_max_memory'], 'avg', $period)
                    : [];
                $activeKeys = $this->getEnabledMetrics()['key_statistics'] ?
                    $this->rhythm->getStorage()->graph(
                        ['redis_keys_total', 'redis_keys_with_expiration'],
                        'avg',
                        $period,
                    )
                    : [];
                $removedKeys = $this->getEnabledMetrics()['removed_keys'] ?
                    $this->rhythm->getStorage()->graph(['redis_expired_keys', 'redis_evicted_keys'], 'avg', $period)
                    : [];
                $ttl = $this->getEnabledMetrics()['key_statistics'] ?
                    $this->rhythm->getStorage()->graph(['redis_avg_ttl'], 'avg', $period)
                    : [];
                $network = $this->getEnabledMetrics()['network_usage'] ?
                    $this->rhythm->getStorage()->graph(['redis_network_usage'], 'avg', $period)
                    : [];

                $this->unpackGraphData($memory);
                $this->unpackGraphData($activeKeys);
                $this->unpackGraphData($removedKeys);
                $this->unpackGraphData($ttl);
                $this->unpackGraphData($network);

                $labels = $this->getConfigValue('labels', []);
                $seriesData = [
                    'memory' => $memory,
                    'active_keys' => $activeKeys,
                    'removed_keys' => $removedKeys,
                    'ttl' => $ttl,
                    'network' => $network,
                ];
                $chartData = $this->prepareWidgetChartData($seriesData, $labels, 'redis');
                $mappedColors = $this->mapColorsWithLabels($this->getChartColors(), $labels);

                $empty = empty($memory) && empty($activeKeys) && empty($removedKeys) && empty($ttl) && empty($network);

                $data = [
                    'empty' => $empty,
                    'chartData' => $chartData,
                    'colors' => $mappedColors,
                    'metrics' => $this->getEnabledMetrics(),
                    'period' => $period,
                ];

                return $data;
            } catch (Exception $e) {
                return [
                    'empty' => true,
                    'chartData' => [],
                    'colors' => $this->getChartColors(),
                    'metrics' => $this->getEnabledMetrics(),
                    'period' => $period,
                    'error' => $e->getMessage(),
                ];
            }
        }, 'redis_widget_' . implode('_', $connections) . '_' . $period, $this->getRefreshInterval());
    }

    /**
     * Prepare chart data and configurations (override trait for custom legend formatting)
     *
     * @param array $seriesData
     * @param array $labels
     * @param string $prefix
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
            $chartData[$connection] = [];
            foreach ($seriesData as $group => $groupData) {
                $metrics = $groupData[$connection] ?? null;
                $chartData[$connection][$group] = $this->prepareWidgetChartConfigCustom(
                    (string)$connection,
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
     * Prepare chart config for a single metric group (custom legend: latest value, memory formatted)
     *
     * @param string $connection
     * @param string $group
     * @param mixed $metrics
     * @param array $metricLabels
     * @param string $prefix
     * @return array|null
     */
    protected function prepareWidgetChartConfigCustom(
        string $connection,
        string $group,
        mixed $metrics,
        array $metricLabels,
        string $prefix,
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
        foreach ($metrics as $metric => $series) {
            $latestValue = null;
            foreach ($series as $timestamp => $value) {
                if ($value !== null) {
                    $date = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
                    $formattedTime = $date ? $date->format('d.m.y, H:i') : $timestamp;
                    $val = is_numeric($value) ? (float)$value : $value;
                    if ($group === 'memory') {
                        $mbValue = $val / 1048576;
                        $chartData[$metricLabels[$metric] ?? $metric][$formattedTime] = round($mbValue, 2);
                        $latestValue = $mbValue;
                    } else {
                        $chartData[$metricLabels[$metric] ?? $metric][$formattedTime] = $val;
                        $latestValue = $val;
                    }
                }
            }
            $legendData[$metric] = [
                'label' => $metricLabels[$metric] ?? $metric,
                'total' => $group === 'memory' && $latestValue !== null
                    ? round($latestValue, 2) . ' MB'
                    : $latestValue,
            ];
        }
        $chartId = $prefix . '-' . $group . '-' . str_replace([':', '-', '.'], ['-', '', ''], $connection);

        return [
            'chartId' => $chartId,
            'chartData' => $chartData,
            'legendData' => $legendData,
            'type' => $group,
        ];
    }

    /**
     * Prepare chart data and configurations
     *
     * @param array $memory Memory data
     * @param array $activeKeys Active keys data
     * @param array $removedKeys Removed keys data
     * @param array $ttl TTL data
     * @param array $network Network data
     * @return array
     */
    protected function prepareChartData(
        array $memory,
        array $activeKeys,
        array $removedKeys,
        array $ttl,
        array $network,
    ): array {
        $chartData = [];
        $allConnections = array_unique(array_merge(
            array_keys($memory),
            array_keys($activeKeys),
            array_keys($removedKeys),
            array_keys($ttl),
            array_keys($network),
        ));

        foreach ($allConnections as $connection) {
            $chartData[$connection] = [
                'memory' => $this->prepareChartConfig($connection, 'memory', $memory[$connection] ?? null),
                'active_keys' => $this->prepareChartConfig(
                    $connection,
                    'active_keys',
                    $activeKeys[$connection] ?? null,
                ),
                'removed_keys' => $this->prepareChartConfig(
                    $connection,
                    'removed_keys',
                    $removedKeys[$connection] ?? null,
                ),
                'ttl' => $this->prepareChartConfig($connection, 'ttl', $ttl[$connection] ?? null),
                'network' => $this->prepareChartConfig($connection, 'network', $network[$connection] ?? null),
            ];
        }

        return $chartData;
    }

    /**
     * Prepare chart configuration
     *
     * @param string $connection Connection name
     * @param string $type Chart type
     * @param mixed $data Chart data
     * @return array|null
     */
    protected function prepareChartConfig(string $connection, string $type, mixed $data): ?array
    {
        if (!$data) {
            return null;
        }

        $chartId = 'redis-' . $type . '-' . str_replace([':', '-', '.'], ['-', '', ''], $connection);

        return [
            'chartId' => $chartId,
            'data' => $data,
            'type' => $type,
        ];
    }

    /**
     * Get chart colors configuration
     *
     * @return array<string, array<string, string>>
     */
    protected function getChartColors(): array
    {
        $configColors = $this->getConfigValue('colors', []);
        $defaultColors = [
            'memory' => [
                'redis_used_memory' => '#10b981',
                'redis_max_memory' => '#9333ea',
            ],
            'active_keys' => [
                'redis_keys_total' => '#3b82f6',
                'redis_keys_with_expiration' => '#f59e0b',
            ],
            'removed_keys' => [
                'redis_expired_keys' => '#ef4444',
                'redis_evicted_keys' => '#8b5cf6',
            ],
            'ttl' => [
                'redis_avg_ttl' => '#06b6d4',
            ],
            'network' => [
                'redis_network_usage' => '#84cc16',
            ],
        ];

        return array_replace_recursive($defaultColors, $configColors);
    }

    /**
     * Get enabled metrics configuration
     *
     * @return array<string, bool>
     */
    protected function getEnabledMetrics(): array
    {
        return [
            'memory_usage' => $this->getConfigValue('metrics.memory_usage', true),
            'key_statistics' => $this->getConfigValue('metrics.key_statistics', true),
            'removed_keys' => $this->getConfigValue('metrics.removed_keys', true),
            'network_usage' => $this->getConfigValue('metrics.network_usage', true),
        ];
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Crustum/Rhythm.widgets/redis';
    }

    /**
     * Get refresh interval in seconds
     *
     * @return int
     */
    public function getRefreshInterval(): int
    {
        return $this->getConfigValue('refreshInterval', 60);
    }

    /**
     * Get default icon for this widget
     *
     * @return string|null
     */
    protected function getDefaultIcon(): ?string
    {
        return 'fas fa-database';
    }
}

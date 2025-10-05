<?php
declare(strict_types=1);

namespace Rhythm\Widget;

use Cake\I18n\DateTime;
use Exception;
use Rhythm\Widget\Trait\WidgetChartFormattingTrait;
use Rhythm\Widget\Trait\WidgetSamplingTrait;

/**
 * Database Widget
 *
 * Displays database server status with interactive charts.
 */
class DatabaseWidget extends BaseWidget
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
        $title = $options['title'] ?? $this->getConfigValue('title', 'Database Status');
        $values = $options['values'] ?? $this->getConfigValue('values', []);
        $graphs = $options['graphs'] ?? $this->getConfigValue('graphs', []);

        $key = 'database_widget_' . implode('_', $connections) . '_' . $period . '_' . md5(serialize($values));

        return $this->remember(function () use ($period, $connections, $title, $values, $graphs) {
            try {
                $connectionsData = $this->buildConnectionsData($period, $connections, $values, $graphs);
                $empty = $connectionsData === [];

                $data = [
                    'empty' => $empty,
                    'connections' => $connectionsData,
                    'colors' => $this->getChartColors(),
                    'metrics' => $this->getEnabledMetrics(),
                    'period' => $period,
                    'title' => $title,
                    'values' => $values,
                    'graphs' => $graphs,
                ];

                return $data;
            } catch (Exception $e) {
                return [
                    'empty' => true,
                    'connections' => [],
                    'colors' => $this->getChartColors(),
                    'metrics' => $this->getEnabledMetrics(),
                    'period' => $period,
                    'title' => $title,
                    'values' => $values,
                    'graphs' => $graphs,
                    'error' => $e->getMessage(),
                ];
            }
        }, $key, $this->getRefreshInterval());
    }

    /**
     * Build connections data
     *
     * @param int $period Period in minutes
     * @param array<string> $connections Connection names
     * @param array<string> $values Specific values to show
     * @param array<string, array<string, string>> $graphs Graph configuration
     * @return array<string, mixed>
     */
    protected function buildConnectionsData(int $period, array $connections, array $values, array $graphs): array
    {
        $connectionsData = [];
        $graphsData = $this->buildGraphs($period, $values, $graphs);

        foreach ($connections as $connectionName) {
            $connectionValues = $this->rhythm->getStorage()->values('database_connection', [$connectionName]);

            if (count($connectionValues) === 0) {
                continue;
            }

            $connectionValuesArray = $connectionValues->toArray();
            $latestValue = end($connectionValuesArray);
            if (!$latestValue) {
                continue;
            }

            $valueString = is_string($latestValue->value) ? $latestValue->value : '';
            $allValues = json_decode($valueString, true, 512, JSON_THROW_ON_ERROR) ?? [];

            $filteredValues = array_intersect_key($allValues, array_flip($values));

            $unpackedGraphs = $graphsData;
            foreach ($unpackedGraphs as $aggregateType => $graphData) {
                $this->unpackGraphData($unpackedGraphs[$aggregateType]);
            }

            $labels = $this->buildLabels($values);
            $chartData = $this->prepareWidgetChartData($unpackedGraphs, $labels, 'database');

            $mappedColors = $this->mapColorsWithLabels($graphs);

            $connectionsData[$connectionName] = [
                'name' => $connectionName,
                'values' => $filteredValues,
                'chartData' => $chartData[$connectionName] ?? [],
                'mappedGraphs' => $mappedColors,
                'updated_at' => $latestValue->timestamp,
                'recently_reported' => (new DateTime())->getTimestamp() - $latestValue->timestamp < 30,
            ];
        }

        return $connectionsData;
    }

    /**
     * Build graphs for specific values and aggregate types
     *
     * @param int $period Period in minutes
     * @param array<string> $values Specific values to show
     * @param array<string, array<string, string>> $graphs Graph configuration
     * @return array<string, mixed>
     */
    protected function buildGraphs(int $period, array $values, array $graphs): array
    {
        $graphsData = [];

        foreach ($graphs as $aggregateType => $metricColors) {
            if (empty($metricColors)) {
                continue;
            }

            $databaseMetrics = array_map(function ($metric) {
                return $metric;
            }, array_keys($metricColors));

            $graphsData[$aggregateType] = $this->rhythm->getStorage()->graph($databaseMetrics, $aggregateType, $period);
        }

        return $graphsData;
    }

    /**
     * Build labels for metrics
     *
     * @param array<string> $values Specific values to show
     * @return array<array-key, array<string, string>>
     */
    protected function buildLabels(array $values): array
    {
        $labels = [];
        $graphs = $this->getConfigValue('graphs', []);

        foreach ($graphs as $aggregateType => $metricColors) {
            if (empty($metricColors)) {
                continue;
            }

            $labels[$aggregateType] = [];
            foreach (array_keys($metricColors) as $metric) {
                $labels[(string)$aggregateType][(string)$metric] = $this->formatMetricLabel((string)$metric);
            }
        }

        return $labels;
    }

    /**
     * Format metric label for display
     *
     * @param string $metric Metric name
     * @return string Formatted label
     */
    protected function formatMetricLabel(string $metric): string
    {
        return str_replace('_', ' ', ucfirst(strtolower($metric)));
    }

    /**
     * Get chart colors configuration
     *
     * @return array<string, string>
     */
    protected function getChartColors(): array
    {
        return [
            'primary' => '#3b82f6',
            'secondary' => '#ef4444',
        ];
    }

    /**
     * Get enabled metrics configuration
     *
     * @return array<string, bool>
     */
    protected function getEnabledMetrics(): array
    {
        $graphs = $this->getConfigValue('graphs', []);

        return [
            'max' => isset($graphs['max']) && !empty($graphs['max']),
            'avg' => isset($graphs['avg']) && !empty($graphs['avg']),
            'count' => isset($graphs['count']) && !empty($graphs['count']),
        ];
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Rhythm.widgets/database';
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

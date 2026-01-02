<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget;

use Cake\Collection\Collection;
use Cake\Collection\CollectionInterface;
use Crustum\Rhythm\Widget\Trait\WidgetChartFormattingTrait;
use Crustum\Rhythm\Widget\Trait\WidgetSamplingTrait;
use Exception;

/**
 * Queues Widget
 *
 * Displays queue metrics from Rhythm data.
 * Shows time-series graphs for each queue state and queue statistics.
 */
class QueuesWidget extends BaseWidget
{
    use WidgetChartFormattingTrait;
    use WidgetSamplingTrait;

    /**
     * Get widget data
     *
     * @param array $options Widget options (period, sort, etc.)
     * @return array
     */
    public function getData(array $options = []): array
    {
        $period = $options['period'] ?? 60;

        return $this->remember(function () use ($period) {
            try {
                $queues = $this->rhythm->getStorage()->graph(
                    [
                        'queues.queued',
                        'queues.processing',
                        'queues.processed',
                        'queues.released',
                        'queues.failed',
                    ],
                    'count',
                    $period,
                )->toArray();

                $queueDepth = $this->rhythm->getStorage()
                    ->graph(['queue_depth'], 'avg', $period)
                    ->toArray();
                $queueHealth = $this->rhythm->getStorage()
                    ->graph(['queue_health'], 'avg', $period)
                    ->toArray();
                $queueMaxWaitTime = $this->rhythm->getStorage()
                    ->graph(['queue_maximum_wait_time'], 'max', $period)
                    ->toArray();

                $queueStats = $this->combineQueueStats($queueDepth, $queueHealth, $queueMaxWaitTime);

                $colors = $this->getConfigValue('colors', []);
                $labels = $this->getConfigValue('labels', []);
                $colors = $this->mapColorsWithLabels($colors);

                $activitySampleRate = (float)($this->getRecorderSettingForRecorder('queues', 'sample_rate') ?? 1.0);
                $statisticsSampleRate = 1.0;

                $summary = $this->calculateSummaryStatistics($queues, $activitySampleRate);

                $chartData = $this->prepareSamplingAwareMultiChartDataWithRates(
                    [
                        'activity' => $queues,
                        'statistics' => $queueStats,
                    ],
                    $labels,
                    'queue',
                    $colors,
                    [
                        'activity' => $activitySampleRate,
                        'statistics' => $statisticsSampleRate,
                    ],
                );

                $transformedChartData = $this->transformChartDataForTemplates($chartData, ['activity', 'statistics']);

                $result = [
                    'queues' => $queues,
                    'queueStats' => $queueStats,
                    'summary' => $summary,
                    'period' => $period,
                    'chartData' => $transformedChartData,
                ];

                return $result;
            } catch (Exception $e) {
                return [
                    'queues' => [],
                    'queueStats' => [],
                    'summary' => [
                        'total_processed' => 0,
                        'total_failed' => 0,
                        'total_queued' => 0,
                        'success_rate' => 0,
                        'status' => 'unknown',
                    ],
                    'period' => $period,
                    'chartData' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }, 'queues_' . $period, $this->getRefreshInterval());
    }

    /**
     * Combine queue statistics data
     *
     * @param array $queueDepth Queue depth data
     * @param array $queueHealth Queue health data
     * @param array $queueMaxWaitTime Maximum wait time data
     * @return array
     */
    protected function combineQueueStats(array $queueDepth, array $queueHealth, array $queueMaxWaitTime): array
    {
        $combinedStats = [];

        $allKeys = array_unique(
            array_merge(
                array_keys($queueDepth),
                array_keys($queueHealth),
                array_keys($queueMaxWaitTime),
            ),
        );
        $allKeys = array_filter($allKeys);

        foreach ($allKeys as $queueName) {
            $combinedData = [];

            if (isset($queueDepth[$queueName])) {
                $depthData = $queueDepth[$queueName]->toArray();
                if (isset($depthData['queue_depth'])) {
                    $combinedData['queue_depth'] = $depthData['queue_depth'];
                }
            }

            if (isset($queueHealth[$queueName])) {
                $healthData = $queueHealth[$queueName]->toArray();
                if (isset($healthData['queue_health'])) {
                    $combinedData['queue_health'] = $healthData['queue_health'];
                }
            }

            if (isset($queueMaxWaitTime[$queueName])) {
                $maxData = $queueMaxWaitTime[$queueName]->toArray();
                if (isset($maxData['queue_maximum_wait_time'])) {
                    $combinedData['queue_maximum_wait_time'] = $maxData['queue_maximum_wait_time'];
                }
            }

            $combinedStats[$queueName] = new Collection($combinedData);
        }

        return $combinedStats;
    }

    /**
     * Calculate summary statistics
     *
     * @param array $queues Queue data
     * @param float $sampleRate Sample rate for magnification
     * @return array
     */
    protected function calculateSummaryStatistics(array $queues, float $sampleRate): array
    {
        $totalProcessed = 0;
        $totalFailed = 0;
        $totalQueued = 0;
        $rawProcessed = 0;
        $rawFailed = 0;
        $rawQueued = 0;

        foreach ($queues as $queueCollection) {
            if (!$queueCollection instanceof CollectionInterface) {
                continue;
            }

            $queueData = $queueCollection->toArray();

            foreach ($queueData as $metricType => $timeSeriesData) {
                if (!is_array($timeSeriesData)) {
                    continue;
                }

                $metricTotal = 0;
                foreach ($timeSeriesData as $value) {
                    if ($value !== null) {
                        $metricTotal += (float)$value;
                    }
                }

                $rawValue = $metricTotal;
                if ($sampleRate < 1.0) {
                    $metricTotal = $this->magnifyValue($metricTotal, $sampleRate);
                }

                switch ($metricType) {
                    case 'queues.processed':
                        $totalProcessed += $metricTotal;
                        $rawProcessed += $rawValue;
                        break;
                    case 'queues.failed':
                        $totalFailed += $metricTotal;
                        $rawFailed += $rawValue;
                        break;
                    case 'queues.queued':
                        $totalQueued += $metricTotal;
                        $rawQueued += $rawValue;
                        break;
                }
            }
        }

        $successRate = $totalProcessed + $totalFailed > 0
            ? round($totalProcessed / ($totalProcessed + $totalFailed) * 100, 2)
            : 0;

        return [
            'total_processed' => (int)$totalProcessed,
            'total_failed' => (int)$totalFailed,
            'total_queued' => (int)$totalQueued,
            'raw_processed' => (int)$rawProcessed,
            'raw_failed' => (int)$rawFailed,
            'raw_queued' => (int)$rawQueued,
            'success_rate' => $successRate,
            'is_sampled' => $sampleRate < 1.0,
            'sample_rate' => $sampleRate,
            'status' => $this->getQueueStatus($successRate, (int)$totalQueued),
        ];
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Rhythm.widgets/queues';
    }

    /**
     * Get refresh interval in seconds
     *
     * @return int
     */
    public function getRefreshInterval(): int
    {
        return $this->getConfigValue('refreshInterval', 30);
    }

    /**
     * Get default icon for this widget
     *
     * @return string|null
     */
    protected function getDefaultIcon(): ?string
    {
        return 'fas fa-list';
    }

    /**
     * Get queue status based on success rate and queued count
     *
     * @param float $successRate Success rate percentage
     * @param int $queued Queued jobs count
     * @return string
     */
    protected function getQueueStatus(float $successRate, int $queued): string
    {
        if ($queued >= 100) {
            return 'critical';
        }

        if ($successRate < 80) {
            return 'warning';
        }

        if ($queued >= 50) {
            return 'warning';
        }

        return 'normal';
    }
}

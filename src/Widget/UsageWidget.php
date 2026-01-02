<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget;

use Crustum\Rhythm\Widget\Trait\WidgetSamplingTrait;
use Exception;

/**
 * Usage Widget
 *
 * Displays system usage metrics from Rhythm data.
 */
class UsageWidget extends BaseWidget
{
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
                $requestsCountResult = $this->rhythm->getStorage()->aggregateTotal('slow_request', 'count', $period);
                $avgResponseTimeResult = $this->rhythm->getStorage()->aggregateTotal('slow_request', 'avg', $period);
                $avgMemoryUsageResult = $this->rhythm->getStorage()->aggregateTotal('memory', 'avg', $period);

                $requestsCount = is_numeric($requestsCountResult) ? (float)$requestsCountResult : 0.0;
                $avgResponseTime = is_numeric($avgResponseTimeResult) ? (float)$avgResponseTimeResult : 0.0;
                $avgMemoryUsage = is_numeric($avgMemoryUsageResult) ? (float)$avgMemoryUsageResult : 0.0;

                $rawRequestCount = (int)$requestsCount;
                $magnifiedRequestCount = (int)$this->magnifyValue($rawRequestCount);

                return [
                    'requests' => [
                        'count' => $magnifiedRequestCount,
                        'raw_count' => $rawRequestCount,
                        'status' => $this->getRequestStatus($magnifiedRequestCount),
                        'is_sampled' => $this->isSamplingEnabled(),
                        'sample_rate' => $this->getSampleRate(),
                    ],
                    'response_time' => [
                        'average' => round((float)$avgResponseTime, 2),
                        'unit' => 'ms',
                        'status' => $this->getResponseTimeStatus((float)$avgResponseTime),
                    ],
                    'memory_usage' => [
                        'average' => round((float)$avgMemoryUsage, 2),
                        'unit' => 'MB',
                        'status' => $this->getMemoryUsageStatus((float)$avgMemoryUsage),
                    ],
                    'is_sampled' => $this->isSamplingEnabled(),
                    'sample_rate' => $this->getSampleRate(),
                ];
            } catch (Exception $e) {
                return [
                    'requests' => ['count' => 0, 'status' => 'unknown'],
                    'response_time' => ['average' => 0, 'unit' => 'ms', 'status' => 'unknown'],
                    'memory_usage' => ['average' => 0, 'unit' => 'MB', 'status' => 'unknown'],
                    'error' => $e->getMessage(),
                ];
            }
        }, 'usage_' . $period, $this->getRefreshInterval());
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Crustum/Rhythm.widgets/usage';
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
        return 'fas fa-chart-line';
    }

    /**
     * Get recorder name
     *
     * @return string
     */
    protected function getRecorderName(): string
    {
        return 'user_requests';
    }

    /**
     * Get request status based on count
     *
     * @param int $count Request count
     * @return string
     */
    protected function getRequestStatus(int $count): string
    {
        if ($count >= 1000) {
            return 'high';
        }

        if ($count >= 500) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get response time status
     *
     * @param float $responseTime Average response time in milliseconds
     * @return string
     */
    protected function getResponseTimeStatus(float $responseTime): string
    {
        if ($responseTime >= 1000) {
            return 'critical';
        }

        if ($responseTime >= 500) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Get memory usage status
     *
     * @param float $memoryUsage Average memory usage in MB
     * @return string
     */
    protected function getMemoryUsageStatus(float $memoryUsage): string
    {
        if ($memoryUsage >= 500) {
            return 'critical';
        }

        if ($memoryUsage >= 250) {
            return 'warning';
        }

        return 'normal';
    }
}

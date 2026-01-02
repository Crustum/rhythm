<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget;

use Crustum\Rhythm\Widget\Trait\WidgetSamplingTrait;
use Exception;

/**
 * Slow Outgoing Requests Widget
 *
 * Displays slow outgoing HTTP requests from Rhythm data.
 */
class SlowOutgoingRequestsWidget extends BaseWidget
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
                $requests = $this->rhythm->getStorage()->aggregate(
                    type: 'slow_outgoing_request',
                    aggregates: ['max', 'count'],
                    intervalMinutes: $period,
                    orderBy: 'max',
                );

                $slowRequests = [];
                $totalCount = 0;

                foreach ($requests as $request) {
                    $requestData = json_decode($request['metric_key'], true);
                    $method = is_array($requestData) && isset($requestData[0]) ? $requestData[0] : 'GET';
                    $url = is_array($requestData) && isset($requestData[1]) ? $requestData[1] : $request['metric_key'];

                    $rawCount = $request['count'] ?? 0;
                    $magnifiedCount = $this->magnifyValue($rawCount);

                    $slowRequests[] = [
                        'method' => $method,
                        'url' => $url,
                        'count' => $magnifiedCount,
                        'raw_count' => $rawCount,
                        'max_duration' => round((float)($request['max'] ?? 0), 2),
                        'status' => $this->getRequestStatus((float)($request['max'] ?? 0)),
                        'is_sampled' => $this->isSamplingEnabled(),
                        'sample_rate' => $this->getSampleRate(),
                    ];

                    $totalCount += $rawCount;
                }

                $magnifiedTotalCount = $this->magnifyValue($totalCount);

                return [
                    'requests' => array_slice($slowRequests, 0, 10),
                    'total_count' => $magnifiedTotalCount,
                    'raw_total_count' => $totalCount,
                    'max_duration' => $this->getMaxRequestTime($slowRequests),
                    'is_sampled' => $this->isSamplingEnabled(),
                    'sample_rate' => $this->getSampleRate(),
                ];
            } catch (Exception $e) {
                return [
                    'requests' => [],
                    'total_count' => 0,
                    'max_duration' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }, 'slow_outgoing_requests_' . $period, $this->getRefreshInterval());
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Crustum/Rhythm.widgets/slow_outgoing_requests';
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
        return 'fas fa-external-link-alt';
    }

    /**
     * Get recorder name
     *
     * @return string
     */
    protected function getRecorderName(): string
    {
        return 'slow_outgoing_requests';
    }

    /**
     * Get request status based on duration.
     *
     * @param float $duration The request duration in milliseconds.
     * @return string
     */
    protected function getRequestStatus(float $duration): string
    {
        if ($duration >= 2000) {
            return 'critical';
        }
        if ($duration >= 1000) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Get maximum request time across all requests.
     *
     * @param array $requests Request data.
     * @return float
     */
    protected function getMaxRequestTime(array $requests): float
    {
        if ($requests === []) {
            return 0;
        }

        return max(array_column($requests, 'max_duration')) ?: 0;
    }
}

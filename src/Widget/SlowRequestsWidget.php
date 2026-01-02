<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget;

use Crustum\Rhythm\Widget\Trait\SortableTrait;
use Crustum\Rhythm\Widget\Trait\WidgetSamplingTrait;
use Exception;

/**
 * Slow Requests Widget
 *
 * Displays slow HTTP requests from Rhythm data.
 */
class SlowRequestsWidget extends BaseWidget
{
    use SortableTrait;
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

        return $this->remember(function () use ($period, $options) {
            try {
                $sortOrder = $this->getSortOrder($options);
                $orderBy = match ($sortOrder) {
                    'count' => 'count',
                    default => 'max',
                };

                $requests = $this->rhythm->getStorage()->aggregate(
                    type: 'slow_request',
                    aggregates: ['max', 'count'],
                    intervalMinutes: $period,
                    orderBy: $orderBy,
                );
                $slowRequests = [];
                $totalCount = 0;

                foreach ($requests as $request) {
                    $requestData = json_decode($request['key'], true);
                    $method = is_array($requestData) && isset($requestData[0]) ? $requestData[0] : 'Unknown';
                    $path = is_array($requestData) && isset($requestData[1]) ? $requestData[1] : $request['key'];
                    $statusCode = is_array($requestData) && isset($requestData[2]) ? $requestData[2] : 0;

                    $rawCount = $request['count'] ?? 0;
                    $magnifiedCount = $this->magnifyValue($rawCount);

                    $slowRequests[] = [
                        'method' => $method,
                        'path' => $path,
                        'status_code' => $statusCode,
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
        }, $this->getSortCacheKey('slow_requests_' . $period, $options), $this->getRefreshInterval());
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Rhythm.widgets/slow_requests';
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
        return 'fas fa-clock';
    }

    /**
     * Get recorder name
     *
     * @return string
     */
    protected function getRecorderName(): string
    {
        return 'slow_requests';
    }

    /**
     * Get sort options for this widget
     *
     * @return array Array of sort options [value => label]
     */
    protected function getSortOptions(): array
    {
        return [
            'slowest' => 'Slowest First',
            'count' => 'Most Frequent',
        ];
    }

    /**
     * Get default sort order for this widget
     *
     * @return string Default sort value
     */
    protected function getDefaultSort(): string
    {
        return 'slowest';
    }

    /**
     * Get request status based on duration
     *
     * @param float $duration Request duration in milliseconds
     * @return string
     */
    protected function getRequestStatus(float $duration): string
    {
        if ($duration >= 5000) {
            return 'critical';
        }

        if ($duration >= 2000) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Get maximum request time across all requests
     *
     * @param array $requests Request data
     * @return float
     */
    protected function getMaxRequestTime(array $requests): float
    {
        if ($requests === []) {
            return 0;
        }

        $maxDuration = 0;

        foreach ($requests as $request) {
            if ($request['max_duration'] > $maxDuration) {
                $maxDuration = $request['max_duration'];
            }
        }

        return $maxDuration;
    }
}

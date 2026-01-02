<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget;

use Crustum\Rhythm\Widget\Trait\SortableTrait;
use Crustum\Rhythm\Widget\Trait\WidgetSamplingTrait;
use Exception;

/**
 * Slow Queries Widget
 *
 * Displays slow database queries from Rhythm data.
 */
class SlowQueriesWidget extends BaseWidget
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

                $queries = $this->rhythm->getStorage()->aggregate(
                    type: 'slow_query',
                    aggregates: ['max', 'count'],
                    intervalMinutes: $period,
                    orderBy: $orderBy,
                );

                $slowQueries = [];
                $totalCount = 0;

                foreach ($queries as $query) {
                    $queryData = json_decode($query['key'], true);
                    $sql = is_array($queryData) && isset($queryData[0]) ? $queryData[0] : $query['key'];
                    $location = is_array($queryData) && isset($queryData[1]) ? $queryData[1] : 'Unknown';

                    $rawCount = $query['count'] ?? 0;
                    $magnifiedCount = $this->magnifyValue($rawCount);

                    $slowQueries[] = [
                        'sql' => $sql,
                        'location' => $location,
                        'count' => $magnifiedCount,
                        'raw_count' => $rawCount,
                        'max_duration' => round((float)($query['max'] ?? 0), 2),
                        'status' => $this->getQueryStatus((float)($query['max'] ?? 0)),
                        'is_sampled' => $this->isSamplingEnabled(),
                        'sample_rate' => $this->getSampleRate(),
                    ];

                    $totalCount += $rawCount;
                }

                $magnifiedTotalCount = $this->magnifyValue($totalCount);

                return [
                    'queries' => array_slice($slowQueries, 0, 10),
                    'total_count' => $magnifiedTotalCount,
                    'raw_total_count' => $totalCount,
                    'max_duration' => $this->getMaxQueryTime($slowQueries),
                    'is_sampled' => $this->isSamplingEnabled(),
                    'sample_rate' => $this->getSampleRate(),
                ];
            } catch (Exception $e) {
                return [
                    'queries' => [],
                    'total_count' => 0,
                    'max_duration' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }, $this->getSortCacheKey('slow_queries_' . $period, $options), $this->getRefreshInterval());
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Rhythm.widgets/slow_queries';
    }

    /**
     * Get refresh interval in seconds
     *
     * @return int
     */
    public function getRefreshInterval(): int
    {
        return $this->getConfigValue('refreshInterval', 5);
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

    /**
     * Get recorder name
     *
     * @return string
     */
    protected function getRecorderName(): string
    {
        return 'slow_queries';
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
     * Get query status based on average duration
     *
     * @param float $avgDuration Average query duration in milliseconds
     * @return string
     */
    protected function getQueryStatus(float $avgDuration): string
    {
        if ($avgDuration >= 1000) {
            return 'critical';
        }

        if ($avgDuration >= 500) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Get maximum query time across all queries
     *
     * @param array $queries Query data
     * @return float
     */
    protected function getMaxQueryTime(array $queries): float
    {
        if ($queries === []) {
            return 0;
        }

        $maxDuration = 0;

        foreach ($queries as $query) {
            if ($query['max_duration'] > $maxDuration) {
                $maxDuration = $query['max_duration'];
            }
        }

        return $maxDuration;
    }
}

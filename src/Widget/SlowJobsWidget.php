<?php
declare(strict_types=1);

namespace Rhythm\Widget;

use Exception;
use Rhythm\Widget\Trait\SortableTrait;
use Rhythm\Widget\Trait\WidgetSamplingTrait;

/**
 * Slow Jobs Widget
 *
 * Displays slow queue jobs from Rhythm data.
 */
class SlowJobsWidget extends BaseWidget
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

                $jobs = $this->rhythm->getStorage()->aggregate(
                    type: 'slow_job',
                    aggregates: ['max', 'count'],
                    intervalMinutes: $period,
                    orderBy: $orderBy,
                );

                $slowJobs = [];
                $totalCount = 0;

                foreach ($jobs as $job) {
                    $jobData = json_decode($job['key'], true);
                    $jobName = is_array($jobData) && isset($jobData[0]) ? $jobData[0] : $job['key'];
                    $status = is_array($jobData) && isset($jobData[1]) ? $jobData[1] : 'unknown';

                    $rawCount = $job['count'] ?? 0;
                    $magnifiedCount = $this->magnifyValue($rawCount);

                    $slowJobs[] = [
                        'job' => $jobName,
                        'status' => $status,
                        'count' => $magnifiedCount,
                        'raw_count' => $rawCount,
                        'max_duration' => round((float)($job['max'] ?? 0), 2),
                        'threshold' => $this->getJobThreshold($jobName),
                        'status_class' => $this->getJobStatusClass($status),
                        'is_sampled' => $this->isSamplingEnabled(),
                        'sample_rate' => $this->getSampleRate(),
                    ];

                    $totalCount += $rawCount;
                }

                $magnifiedTotalCount = $this->magnifyValue($totalCount);

                return [
                    'jobs' => array_slice($slowJobs, 0, 10),
                    'total_count' => $magnifiedTotalCount,
                    'raw_total_count' => $totalCount,
                    'max_duration' => $this->getMaxJobTime($slowJobs),
                    'is_sampled' => $this->isSamplingEnabled(),
                    'sample_rate' => $this->getSampleRate(),
                ];
            } catch (Exception $e) {
                return [
                    'jobs' => [],
                    'total_count' => 0,
                    'max_duration' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }, $this->getSortCacheKey('slow_jobs_' . $period, $options), $this->getRefreshInterval());
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Rhythm.widgets/slow_jobs';
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
        return 'fas fa-clock';
    }

    /**
     * Get recorder name
     *
     * @return string
     */
    protected function getRecorderName(): string
    {
        return 'slow_jobs';
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
     * Get job threshold for the given job name
     *
     * @param string $jobName Job name
     * @return int Threshold in milliseconds
     */
    protected function getJobThreshold(string $jobName): int
    {
        return 1000;
    }

    /**
     * Get job status CSS class
     *
     * @param string $status Job status
     * @return string CSS class
     */
    protected function getJobStatusClass(string $status): string
    {
        return match ($status) {
            'success' => 'text-success',
            'failure' => 'text-danger',
            'exception' => 'text-warning',
            default => 'text-muted',
        };
    }

    /**
     * Get maximum job time across all jobs
     *
     * @param array $jobs Job data
     * @return float
     */
    protected function getMaxJobTime(array $jobs): float
    {
        if ($jobs === []) {
            return 0;
        }

        $maxDuration = 0;

        foreach ($jobs as $job) {
            if ($job['max_duration'] > $maxDuration) {
                $maxDuration = $job['max_duration'];
            }
        }

        return $maxDuration;
    }
}

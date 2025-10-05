<?php
declare(strict_types=1);

namespace Rhythm\Widget;

use Exception;
use Rhythm\Widget\Trait\SortableTrait;
use Rhythm\Widget\Trait\WidgetSamplingTrait;

/**
 * Exceptions Widget
 *
 * Displays application exceptions from Rhythm data.
 */
class ExceptionsWidget extends BaseWidget
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
                    'latest' => 'max',
                    default => 'count',
                };

                $exceptions = $this->rhythm->getStorage()->aggregate(
                    type: 'exception',
                    aggregates: ['max', 'count'],
                    intervalMinutes: $period,
                    orderBy: $orderBy,
                );
                $exceptionData = [];
                $totalCount = 0;

                foreach ($exceptions as $exception) {
                    $keyData = json_decode($exception['key'] ?? '', true);

                    $rawCount = $exception['count'] ?? 0;
                    $magnifiedCount = $this->magnifyValue($rawCount);

                    $exceptionData[] = [
                        'class' => $keyData['class'] ?? 'Unknown Exception',
                        'location' => $keyData['location'] ?? 'Unknown Location',
                        'count' => $magnifiedCount,
                        'raw_count' => $rawCount,
                        'latest' => $exception['max'] ?? 0,
                        'status' => $this->getExceptionStatus((int)$magnifiedCount),
                        'is_sampled' => $this->isSamplingEnabled(),
                        'sample_rate' => $this->getSampleRate(),
                    ];

                    $totalCount += $rawCount;
                }

                $magnifiedTotalCount = $this->magnifyValue($totalCount);

                return [
                    'exceptions' => array_slice($exceptionData, 0, 10),
                    'total_count' => $magnifiedTotalCount,
                    'raw_total_count' => $totalCount,
                    'unique_count' => count($exceptionData),
                    'is_sampled' => $this->isSamplingEnabled(),
                    'sample_rate' => $this->getSampleRate(),
                ];
            } catch (Exception $e) {
                return [
                    'exceptions' => [],
                    'total_count' => 0,
                    'unique_count' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }, $this->getSortCacheKey('exceptions_' . $period, $options), $this->getRefreshInterval());
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Rhythm.widgets/exceptions';
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
        return 'fas fa-exclamation-triangle';
    }

    /**
     * Get recorder name
     *
     * @return string
     */
    protected function getRecorderName(): string
    {
        return 'exceptions';
    }

    /**
     * Get sort options for this widget
     *
     * @return array Array of sort options [value => label]
     */
    protected function getSortOptions(): array
    {
        return [
            'count' => 'Most Frequent',
            'latest' => 'Latest First',
        ];
    }

    /**
     * Get default sort order for this widget
     *
     * @return string Default sort value
     */
    protected function getDefaultSort(): string
    {
        return 'count';
    }

    /**
     * Get exception status based on count
     *
     * @param int $count Exception count
     * @return string
     */
    protected function getExceptionStatus(int $count): string
    {
        if ($count >= 50) {
            return 'critical';
        }

        if ($count >= 10) {
            return 'warning';
        }

        return 'normal';
    }
}

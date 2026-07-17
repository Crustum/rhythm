<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget;

use Cake\Collection\Collection;
use Cake\I18n\DateTime;
use Crustum\Rhythm\Widget\Trait\SortableTrait;
use Exception;
use RuntimeException;

/**
 * Server State Widget
 *
 * Displays server metrics like memory, CPU, and disk usage.
 */
class ServerStateWidget extends BaseWidget
{
    use SortableTrait;

    /**
     * Get widget data
     *
     * @param array $options Widget options (period, sort, sortDirection, etc.)
     * @return array
     */
    public function getData(array $options = []): array
    {
        $period = $options['period'] ?? 60;
        $sortBy = $this->resolveSortBy($options);
        $sortDirection = $this->resolveSortDirection($options);

        return $this->remember(function () use ($period, $sortBy, $sortDirection) {
            $systemValues = $this->rhythm->getStorage()->values('system');
            $graphs = $this->rhythm->getStorage()->graph(['cpu', 'memory'], 'avg', $period);
            $graphs = $graphs->toArray();
            foreach ($graphs as &$graph) {
                $graph = $graph->toArray();
            }

            $servers = [];
            foreach ($systemValues as $serverKey => $systemData) {
                $values = json_decode($systemData->value, true);
                if (!$values) {
                    continue;
                }

                $serverName = $values['name'] ?? $serverKey;
                $currentTime = (new DateTime())->getTimestamp();
                $recentlyReported = $currentTime - $systemData->timestamp < 300;

                $serverGraphData = $graphs[$serverKey] ?? null;
                $cpuGraph = $serverGraphData && isset($serverGraphData['cpu']) ? $serverGraphData['cpu'] : [];
                $memoryGraph = $serverGraphData && isset($serverGraphData['memory']) ? $serverGraphData['memory'] : [];

                $cpuCurrent = (int)($values['cpu'] ?? 0);
                $memoryCurrent = (int)($values['memory_used'] ?? 0);
                $memoryTotal = (int)($values['memory_total'] ?? 0);

                $storage = $values['storage'] ?? [];
                $totalDisk = 0;
                $usedDisk = 0;
                foreach ($storage as $disk) {
                    $totalDisk += $disk['total'] ?? 0;
                    $usedDisk += $disk['used'] ?? 0;
                }
                $diskPercentage = $totalDisk > 0 ? $usedDisk / $totalDisk * 100 : 0;

                $servers[$serverKey] = [
                    'name' => $serverName,
                    'key' => $serverKey,
                    'status' => $recentlyReported ? 'online' : 'offline',
                    'cpu' => [
                        'current' => $cpuCurrent,
                        'unit' => '%',
                        'status' => $this->getStatusLevel($cpuCurrent, 70, 85),
                        'graph' => $cpuGraph,
                    ],
                    'memory' => [
                        'current' => $memoryCurrent,
                        'total' => $memoryTotal,
                        'unit' => 'MB',
                        'status' => $this->getStatusLevel($memoryCurrent / 1024, 80, 90),
                        'graph' => $memoryGraph,
                    ],
                    'disk' => [
                        'used' => round($usedDisk / 1024, 0),
                        'total' => round($totalDisk / 1024, 0),
                        'percent' => round($diskPercentage, 1),
                        'status' => $this->getStatusLevel($diskPercentage, 80, 90),
                    ],
                    'storage' => $storage,
                    'timestamp' => $systemData->timestamp,
                    'updated_at' => $systemData->timestamp,
                    'cpu_current' => $cpuCurrent,
                    'memory_current' => $memoryCurrent,
                    'recently_reported' => $recentlyReported,
                ];
            }

            $servers = $this->sortServers($servers, $sortBy, $sortDirection);

            return [
                'servers' => $servers,
                'server_count' => count($servers),
                'period' => $period,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ];
        }, $this->getSortCacheKey('server_state_' . $period . '_' . $sortBy . '_' . $sortDirection, $options), $this->getRefreshInterval());
    }

    /**
     * @inheritDoc
     */
    protected function getSortOptions(): array
    {
        return [
            'name' => 'Name',
            'cpu_current' => 'CPU',
            'memory_current' => 'Memory',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultSort(): string
    {
        return (string)$this->getConfigValue('sortBy', 'name');
    }

    /**
     * Resolve sort field from options or widget config.
     *
     * @param array<string, mixed> $options Widget options
     * @return string
     */
    protected function resolveSortBy(array $options): string
    {
        $sortBy = $options['sortBy'] ?? $options['sort'] ?? $this->getConfigValue('sortBy', 'name');
        $validOptions = array_keys($this->getSortOptions());

        if (is_string($sortBy) && in_array($sortBy, $validOptions, true)) {
            return $sortBy;
        }

        return 'name';
    }

    /**
     * Resolve sort direction from options or widget config.
     *
     * @param array<string, mixed> $options Widget options
     * @return string
     */
    protected function resolveSortDirection(array $options): string
    {
        $direction = strtolower((string)($options['sortDirection'] ?? $this->getConfigValue('sortDirection', 'asc')));

        return $direction === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Sort servers by the selected field and direction.
     *
     * @param array<string, array<string, mixed>> $servers Servers keyed by slug
     * @param string $sortBy Sort field
     * @param string $sortDirection Sort direction
     * @return array<string, array<string, mixed>>
     */
    protected function sortServers(array $servers, string $sortBy, string $sortDirection): array
    {
        $sortType = $sortBy === 'name' ? SORT_NATURAL : SORT_NUMERIC;

        $sorted = (new Collection($servers))
            ->sortBy($sortBy, $sortDirection === 'desc' ? SORT_DESC : SORT_ASC, $sortType)
            ->toArray();

        /** @var array<string, array<string, mixed>> $sorted */
        return $sorted;
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Crustum/Rhythm.widgets/server_state';
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
        return 'fas fa-server';
    }

    /**
     * Get recorder name
     *
     * @return string
     */
    protected function getRecorderName(): string
    {
        return 'servers';
    }

    /**
     * Get memory metrics
     *
     * @param int $period The time period in minutes
     * @return array
     */
    protected function getMemoryMetrics(int $period): array
    {
        try {
            $serverName = gethostname();
            if (!$serverName) {
                throw new RuntimeException('Could not determine host name.');
            }

            $memoryAggregates = $this->rhythm->getStorage()->aggregate('memory', ['avg', 'max'], $period);

            $serverMemory = $memoryAggregates->filter(fn($agg) => $agg['metric_key'] === $serverName)->first();

            $memoryAvg = $serverMemory['avg'] ?? 0;
            $memoryMax = $serverMemory['max'] ?? 0;

            return [
                'average' => round((float)$memoryAvg, 2),
                'maximum' => round((float)$memoryMax, 2),
                'unit' => 'MB',
                'status' => $this->getStatusLevel((float)$memoryAvg, 80, 90),
            ];
        } catch (Exception $e) {
            return [
                'average' => 0,
                'maximum' => 0,
                'unit' => 'MB',
                'status' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get CPU metrics
     *
     * @param int $period The time period in minutes
     * @return array
     */
    protected function getCpuMetrics(int $period): array
    {
        try {
            $serverName = gethostname();
            if (!$serverName) {
                throw new RuntimeException('Could not determine host name.');
            }

            $cpuAggregates = $this->rhythm->getStorage()->aggregate('cpu', ['avg', 'max'], $period);

            $serverCpu = $cpuAggregates->filter(fn($agg) => $agg['metric_key'] === $serverName)->first();

            $cpuAvg = $serverCpu['avg'] ?? 0;
            $cpuMax = $serverCpu['max'] ?? 0;

            return [
                'average' => round((float)$cpuAvg, 2),
                'maximum' => round((float)$cpuMax, 2),
                'unit' => '%',
                'status' => $this->getStatusLevel((float)$cpuAvg, 70, 85),
            ];
        } catch (Exception $e) {
            return [
                'average' => 0,
                'maximum' => 0,
                'unit' => '%',
                'status' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get disk metrics
     *
     * @param int $period The time period in minutes
     * @return array
     */
    protected function getDiskMetrics(int $period): array
    {
        try {
            $serverName = gethostname();
            if (!$serverName) {
                throw new RuntimeException('Could not determine host name.');
            }
            $systemValue = $this->rhythm->getStorage()->values('system', [$serverName])->first();

            if ($systemValue && $systemValue->value) {
                $systemInfo = json_decode($systemValue->value, true);
                $storage = $systemInfo['storage'] ?? [];

                if (!empty($storage)) {
                    $totalDisk = 0;
                    $usedDisk = 0;

                    foreach ($storage as $disk) {
                        $totalDisk += $disk['total'];
                        $usedDisk += $disk['used'];
                    }

                    $diskPercentage = $totalDisk > 0 ? $usedDisk / $totalDisk * 100 : 0;

                    return [
                        'average' => round($diskPercentage, 2),
                        'maximum' => round($diskPercentage, 2),
                        'unit' => '%',
                        'status' => $this->getStatusLevel($diskPercentage, 80, 90),
                        'used' => $usedDisk,
                        'total' => $totalDisk,
                    ];
                }
            }

            return [
                'average' => 0,
                'maximum' => 0,
                'unit' => '%',
                'status' => 'unknown',
                'used' => 0,
                'total' => 0,
            ];
        } catch (Exception $e) {
            return [
                'average' => 0,
                'maximum' => 0,
                'unit' => '%',
                'status' => 'unknown',
                'used' => 0,
                'total' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get status level based on value and thresholds
     *
     * @param float $value Current value
     * @param float $warningThreshold Warning threshold
     * @param float $criticalThreshold Critical threshold
     * @return string
     */
    protected function getStatusLevel(float $value, float $warningThreshold, float $criticalThreshold): string
    {
        if ($value >= $criticalThreshold) {
            return 'critical';
        }

        if ($value >= $warningThreshold) {
            return 'warning';
        }

        return 'normal';
    }
}

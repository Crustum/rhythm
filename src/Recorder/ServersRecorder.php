<?php
declare(strict_types=1);

namespace Rhythm\Recorder;

use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Cake\Utility\Text;
use Exception;
use Rhythm\Event\SharedBeat;
use Rhythm\Recorder\Trait\IgnoresTrait;
use Rhythm\Recorder\Trait\SamplingTrait;
use Rhythm\Recorder\Trait\ThrottlingTrait;
use RuntimeException;

/**
 * Records server metrics.
 */
class ServersRecorder extends BaseRecorder implements EventListenerInterface
{
    use ThrottlingTrait;
    use SamplingTrait;
    use IgnoresTrait;

    /**
     * @inheritDoc
     */
    public function implementedEvents(): array
    {
        return [
            SharedBeat::class => 'record',
        ];
    }

    /**
     * Record the system stats.
     *
     * @param mixed $data The shared beat event.
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$data instanceof SharedBeat) {
            return;
        }

        $this->throttle(15, $data, function (SharedBeat $event): void {
            $serverName = $this->getServerName();
            $slug = Text::slug($serverName);

            ['total' => $memoryTotal, 'used' => $memoryUsed] = $this->getMemoryUsage();
            $cpu = $this->getCpuUsage();
            $timestamp = $event->getTimestamp()->getTimestamp();
            $this->rhythm->record('cpu', $slug, $cpu, $timestamp)
                ->avg()
                ->onlyBuckets();
            $this->rhythm->record('memory', $slug, $memoryUsed, $timestamp)
                ->avg()
                ->onlyBuckets();

            $storage = (new Collection($this->config['directories'] ?? ['/']))
                ->map(function (string $directory) {
                    if ($this->shouldIgnore($directory)) {
                        return null;
                    }

                    try {
                        $total = disk_total_space($directory);
                        $free = disk_free_space($directory);

                        if ($total === false || $free === false) {
                            return null;
                        }

                        return [
                            'directory' => $directory,
                            'total' => (int)round($total / 1024 / 1024),
                            'used' => (int)round(($total - $free) / 1024 / 1024),
                        ];
                    } catch (Exception) {
                        return null;
                    }
                })
                ->filter()
                ->toList();

            $this->rhythm->set(
                'system',
                $slug,
                json_encode([
                    'name' => $serverName,
                    'cpu' => $cpu,
                    'memory_used' => $memoryUsed,
                    'memory_total' => $memoryTotal,
                    'storage' => $storage,
                ], JSON_THROW_ON_ERROR),
                $timestamp,
            );
        });
    }

    /**
     * Get CPU usage.
     *
     * @return int
     */
    protected function getCpuUsage(): int
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => (int)`top -l 1 | grep -E "^CPU" | tail -1 | awk '{ print $3 + $5 }'`,
            'Linux' => (int)`top -bn1 | grep -E '^(%Cpu|CPU)' | awk '{ print $2 + $4 }'`,
            'Windows' => (int)(trim(`wmic cpu get loadpercentage | more +1` ?? '')),
            'BSD' => (int)`top -b -d 2| grep 'CPU: ' | tail -1 | awk '{print$10}' | grep -Eo '[0-9]+\.[0-9]+' | awk '{ print 100 - $1 }'`,
            default => throw new RuntimeException('The ServersRecorder does not currently support ' . PHP_OS_FAMILY),
        };
    }

    /**
     * Get memory usage.
     *
     * @return array{total: int, used: int}
     */
    protected function getMemoryUsage(): array
    {
        $memoryTotal = match (PHP_OS_FAMILY) {
            'Darwin' => (int)(`sysctl hw.memsize | grep -Eo '[0-9]+'` / 1024 / 1024),
            'Linux' => (int)(`cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'` / 1024),
            'Windows' => (int)((int)trim(`wmic ComputerSystem get TotalPhysicalMemory | more +1`) / 1024 / 1024),
            'BSD' => (int)(`sysctl hw.physmem | grep -Eo '[0-9]+'` / 1024 / 1024),
            default => throw new RuntimeException('The ServersRecorder does not currently support ' . PHP_OS_FAMILY),
        };

        $memoryUsed = match (PHP_OS_FAMILY) {
            'Darwin' => $memoryTotal - (int)((int)(`vm_stat | grep 'Pages free' | grep -Eo '[0-9]+'`) * (int)(`pagesize`) / 1024 / 1024),
            'Linux' => $memoryTotal - (int)(`cat /proc/meminfo | grep MemAvailable | grep -E -o '[0-9]+'` / 1024),
            'Windows' => $memoryTotal - (int)((int)trim(`wmic OS get FreePhysicalMemory | more +1`) / 1024),
            'BSD' => (int)((int)(`( sysctl vm.stats.vm.v_cache_count | grep -Eo '[0-9]+' ; sysctl vm.stats.vm.v_inactive_count | grep -Eo '[0-9]+' ; sysctl vm.stats.vm.v_active_count | grep -Eo '[0-9]+' ) | awk '{s+=$1} END {print s}'`) * (int)(`pagesize`) / 1024 / 1024),
            default => throw new RuntimeException('The ServersRecorder does not currently support ' . PHP_OS_FAMILY),
        };

        return [
            'total' => $memoryTotal,
            'used' => $memoryUsed,
        ];
    }

    /**
     * Get server name.
     *
     * @return string
     */
    protected function getServerName(): string
    {
        $hostname = gethostname();

        return $hostname ?: 'default';
    }
}

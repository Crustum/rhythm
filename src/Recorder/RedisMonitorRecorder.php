<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Crustum\Rhythm\Datasource\RedisConnection;
use Crustum\Rhythm\Event\SharedBeat;
use Crustum\Rhythm\Recorder\Trait\ThrottlingTrait;
use Crustum\Rhythm\Rhythm;
use Exception;
use Redis;
use RuntimeException;

/**
 * Redis Monitor Recorder
 *
 * Monitors Redis server status including memory usage, key statistics,
 * network usage, and performance metrics. Uses its own connection config
 * separate from other Redis connections in the application.
 */
class RedisMonitorRecorder extends BaseRecorder implements EventListenerInterface
{
    use ThrottlingTrait;

    /**
     * The Redis connection instance.
     *
     * @var \Redis
     */
    protected Redis $redis;

    /**
     * Array of redis connection names to record.
     *
     * @var array<string>
     */
    protected array $connections = [];

    /**
     * Array containing boolean values storing whether a metric is enabled.
     *
     * @var array<string, bool>
     */
    protected array $metrics = [];

    /**
     * Interval of recorder in minutes.
     *
     * @var int
     */
    protected int $interval = 1;

    /**
     * Create a new recorder instance.
     *
     * @param \Crustum\Rhythm\Rhythm $rhythm The Rhythm instance.
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(Rhythm $rhythm, array $config = [])
    {
        $config = $config ?: Configure::read('Rhythm.recorders.redis_monitor', []);
        parent::__construct($rhythm, $config);

        $this->setInterval();
        $this->setRedisConnections();
        $this->setMetrics();
        $this->initializeRedis();
    }

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
     * Record Redis monitoring data.
     *
     * @param mixed $data The shared beat event.
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$data instanceof SharedBeat) {
            return;
        }

        $this->throttle(10, $data, function (SharedBeat $event): void {
            if ($this->metrics['memory_usage']) {
                $this->monitorMemoryUsage($event);
            }

            if ($this->metrics['key_statistics']) {
                $this->monitorKeyUsage($event);
            }

            if ($this->metrics['removed_keys']) {
                $this->monitorKeyStats($event);
            }

            if ($this->metrics['network_usage']) {
                $this->monitorNetworkUsage($event);
            }
        });
    }

    /**
     * Monitors the memory usage of all configured Redis connections.
     *
     * @param \Crustum\Rhythm\Event\SharedBeat $event The shared beat event.
     * @return void
     */
    protected function monitorMemoryUsage(SharedBeat $event): void
    {
        foreach ($this->connections as $connection) {
            try {
                $output = $this->redis->info('memory');
                $this->recordMemoryUsage($connection, $output, $event);
            } catch (Exception) {
                $this->rhythm->record('redis_error', $connection, 1)->count()->onlyBuckets();
            }
        }
    }

    /**
     * Monitors the key usage of all configured Redis connections.
     *
     * @param \Crustum\Rhythm\Event\SharedBeat $event The shared beat event.
     * @return void
     */
    protected function monitorKeyUsage(SharedBeat $event): void
    {
        foreach ($this->connections as $connection) {
            try {
                $output = $this->redis->info('keyspace');
                $this->recordKeyUsage($connection, $output, $event);
            } catch (Exception) {
                $this->rhythm->record('redis_error', $connection, 1)->count()->onlyBuckets();
            }
        }
    }

    /**
     * Monitors the key stats of all configured Redis connections.
     *
     * @param \Crustum\Rhythm\Event\SharedBeat $event The shared beat event.
     * @return void
     */
    protected function monitorKeyStats(SharedBeat $event): void
    {
        foreach ($this->connections as $connection) {
            try {
                $output = $this->redis->info('stats');
                $this->recordKeyStats($connection, $output, $event);
            } catch (Exception) {
                $this->rhythm->record('redis_error', $connection, 1)->count()->onlyBuckets();
            }
        }
    }

    /**
     * Monitors network usage of all configured Redis connections.
     *
     * @param \Crustum\Rhythm\Event\SharedBeat $event The shared beat event.
     * @return void
     */
    protected function monitorNetworkUsage(SharedBeat $event): void
    {
        foreach ($this->connections as $connection) {
            try {
                $output = $this->redis->info('stats');
                $this->recordNetworkUsage($connection, $output, $event);
            } catch (Exception) {
                $this->rhythm->record('redis_error', $connection, 1)->count()->onlyBuckets();
            }
        }
    }

    /**
     * Records the memory usage data for a specific Redis connection.
     *
     * @param string $connection Connection name
     * @param array<string, mixed> $output Redis INFO output
     * @param \Crustum\Rhythm\Event\SharedBeat $event The shared beat event
     * @return void
     */
    protected function recordMemoryUsage(string $connection, array $output, SharedBeat $event): void
    {
        if (!isset($output['used_memory'], $output['maxmemory'])) {
            return;
        }

        $timestamp = $event->getTimestamp()->getTimestamp();

        $this->rhythm->set('redis_used_memory', $connection, (string)$output['used_memory'], $timestamp);
        $this->rhythm->set('redis_max_memory', $connection, (string)$output['maxmemory'], $timestamp);

        $this->rhythm->record('redis_used_memory', $connection, (int)$output['used_memory'])
            ->avg()
            ->onlyBuckets();
        $this->rhythm->record('redis_max_memory', $connection, (int)$output['maxmemory'])
            ->avg()
            ->onlyBuckets();

        if ($output['maxmemory'] > 0) {
            $usagePercentage = $output['used_memory'] / $output['maxmemory'] * 100;
            $this->rhythm->set('redis_memory_usage_percent', $connection, (string)$usagePercentage, $timestamp);
            $this->rhythm->record('redis_memory_usage_percent', $connection, (int)$usagePercentage)
                ->avg()
                ->onlyBuckets();
        }
    }

    /**
     * Records the key usage data for all dbs for a specific connection.
     *
     * @param string $connection Connection name
     * @param array<string, mixed> $output Redis INFO output
     * @param \Crustum\Rhythm\Event\SharedBeat $event The shared beat event
     * @return void
     */
    protected function recordKeyUsage(string $connection, array $output, SharedBeat $event): void
    {
        $timestamp = $event->getTimestamp()->getTimestamp();
        foreach ($output as $dbKey => $statsString) {
            if (!str_starts_with($dbKey, 'db') || empty($statsString)) {
                continue;
            }

            $dbStats = explode(',', $statsString);
            $parsedStats = [];

            foreach ($dbStats as $stat) {
                $parts = explode('=', $stat);
                if (count($parts) === 2) {
                    $parsedStats[$parts[0]] = $parts[1];
                }
            }

            $key = $connection . '_' . $dbKey;

            if (isset($parsedStats['keys'], $parsedStats['expires'])) {
                $this->rhythm->set('redis_keys_total', $key, $parsedStats['keys'], $timestamp);
                $this->rhythm->set('redis_keys_with_expiration', $key, $parsedStats['expires'], $timestamp);

                $this->rhythm->record('redis_keys_total', $key, (int)$parsedStats['keys'])
                    ->avg()
                    ->onlyBuckets();
                $this->rhythm->record('redis_keys_with_expiration', $key, (int)$parsedStats['expires'])
                    ->avg()
                    ->onlyBuckets();
            }

            if (isset($parsedStats['avg_ttl'])) {
                $this->rhythm->set('redis_avg_ttl', $key, $parsedStats['avg_ttl'], $timestamp);
                $this->rhythm->record('redis_avg_ttl', $key, (int)$parsedStats['avg_ttl'])
                    ->avg()
                    ->onlyBuckets();
            }
        }
    }

    /**
     * Records the expired and evicted key counts for a specific Redis connection.
     *
     * @param string $connection Connection name
     * @param array<string, mixed> $output Redis INFO output
     * @param \Crustum\Rhythm\Event\SharedBeat $event The shared beat event
     * @return void
     */
    protected function recordKeyStats(string $connection, array $output, SharedBeat $event): void
    {
        if (!isset($output['expired_keys'], $output['evicted_keys'])) {
            return;
        }

        $timestamp = $event->getTimestamp()->getTimestamp();

        $prevExpiredKeys = (int)Cache::read('redis_total_expired_keys_' . $connection, 'rhythm') ?: 0;
        $prevEvictedKeys = (int)Cache::read('redis_total_evicted_keys_' . $connection, 'rhythm') ?: 0;

        if ($prevExpiredKeys > 0 || $prevEvictedKeys > 0) {
            $diffExpired = $output['expired_keys'] - $prevExpiredKeys;
            $diffEvicted = $output['evicted_keys'] - $prevEvictedKeys;

            $this->rhythm->record('redis_expired_keys', $connection, $diffExpired, $timestamp)
                ->count()
                ->onlyBuckets();
            $this->rhythm->record('redis_evicted_keys', $connection, $diffEvicted, $timestamp)
                ->count()
                ->onlyBuckets();
        }

        Cache::write('redis_total_expired_keys_' . $connection, $output['expired_keys'], 'rhythm');
        Cache::write('redis_total_evicted_keys_' . $connection, $output['evicted_keys'], 'rhythm');
    }

    /**
     * Records the network usage since last interval for a specific Redis connection.
     *
     * @param string $connection Connection name
     * @param array<string, mixed> $output Redis INFO output
     * @param \Crustum\Rhythm\Event\SharedBeat $event The shared beat event
     * @return void
     */
    protected function recordNetworkUsage(string $connection, array $output, SharedBeat $event): void
    {
        if (!isset($output['total_net_input_bytes'], $output['total_net_output_bytes'])) {
            return;
        }

        $timestamp = $event->getTimestamp()->getTimestamp();

        $prevInputBytes = (int)Cache::read('redis_total_net_input_bytes_' . $connection, 'rhythm') ?: 0;
        $prevOutputBytes = (int)Cache::read('redis_total_net_output_bytes_' . $connection, 'rhythm') ?: 0;

        if ($prevInputBytes > 0 && $prevOutputBytes > 0) {
            $diffInput = $output['total_net_input_bytes'] - $prevInputBytes;
            $diffOutput = $output['total_net_output_bytes'] - $prevOutputBytes;
            $totalDiff = $diffInput + $diffOutput;

            $this->rhythm->record('redis_network_usage', $connection, $totalDiff, $timestamp)
                ->avg()
                ->onlyBuckets();
        }

        Cache::write('redis_total_net_input_bytes_' . $connection, $output['total_net_input_bytes'], 'rhythm');
        Cache::write('redis_total_net_output_bytes_' . $connection, $output['total_net_output_bytes'], 'rhythm');
    }

    /**
     * Initialize Redis connection.
     *
     * @return void
     */
    protected function initializeRedis(): void
    {
        $config = $this->getConfig();
        $redisConfig = $config['redis'] ?? null;
        if (!$redisConfig) {
            throw new RuntimeException('Redis configuration is required for RedisMonitorRecorder');
        }
        $connection = RedisConnection::create($redisConfig);
        if (!$connection) {
            throw new RuntimeException('Failed to create Redis connection for RedisMonitorRecorder');
        }

        $this->redis = $connection;
    }

    /**
     * Set the enabled metrics based on the configuration.
     *
     * @return void
     */
    protected function setMetrics(): void
    {
        $this->metrics = [
            'memory_usage' => $this->config['metrics']['memory_usage'] ?? true,
            'key_statistics' => $this->config['metrics']['key_statistics'] ?? true,
            'removed_keys' => $this->config['metrics']['removed_keys'] ?? true,
            'network_usage' => $this->config['metrics']['network_usage'] ?? true,
        ];
    }

    /**
     * Sets the interval, in minutes, for recording.
     *
     * @return void
     */
    protected function setInterval(): void
    {
        $this->interval = $this->config['interval'] ?? 5;
    }

    /**
     * Sets the redis connection names to monitor.
     *
     * @return void
     */
    protected function setRedisConnections(): void
    {
        $this->connections = $this->config['connections'] ?? ['default'];
    }
}

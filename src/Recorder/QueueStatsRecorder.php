<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Cake\I18n\DateTime;
use Crustum\Rhythm\Datasource\RedisConnection;
use Crustum\Rhythm\Event\SharedBeat;
use Crustum\Rhythm\Recorder\Trait\IgnoresTrait;
use Crustum\Rhythm\Recorder\Trait\QueueNameTrait;
use Crustum\Rhythm\Recorder\Trait\SamplingTrait;
use Crustum\Rhythm\Recorder\Trait\ThrottlingTrait;
use Crustum\Rhythm\Rhythm;
use Exception;
use Redis;

/**
 * Collects current queue statistics from Redis.
 *
 * This recorder listens to SharedBeat events and queries Redis directly
 * for current queue state (queue depth, job wait times, processing rates).
 * This complements the event-based QueuesRecorder which tracks job lifecycle events.
 */
class QueueStatsRecorder extends BaseRecorder implements EventListenerInterface
{
    use ThrottlingTrait;
    use QueueNameTrait;
    use SamplingTrait;
    use IgnoresTrait;

    /**
     * The Redis connection instance.
     *
     * @var \Redis|null
     */
    protected ?Redis $redis = null;

    /**
     * The Redis key prefix.
     *
     * @var string
     */
    protected string $redisPrefix = '';

    /**
     * Create a new recorder instance.
     *
     * @param \Crustum\Rhythm\Rhythm $rhythm The Rhythm instance.
     * @param array $config Configuration array
     */
    public function __construct(Rhythm $rhythm, array $config = [])
    {
        $config = $config ?: Configure::read('Rhythm.recorders.queue_stats', []);
        parent::__construct($rhythm, $config);

        $this->extractQueuePrefixes();
        $this->initializeRedis();
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
            return;
        }

        $this->redis = RedisConnection::create($redisConfig);
        if ($this->redis) {
            $this->redisPrefix = $redisConfig['prefix'] ?? '';
        }
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
     * Record current queue statistics from Redis.
     *
     * @param mixed $data The shared beat event.
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$data instanceof SharedBeat || $this->redis === null) {
            return;
        }
        $this->throttle(1, $data, function (SharedBeat $event): void {
            if ($this->redis === null) {
                return;
            }

            $timestamp = $event->getTimestamp()->getTimestamp();
            $queues = $this->getConfiguredQueues();

            foreach ($queues as $queueData) {
                if ($this->shouldIgnore($queueData['clean_name'])) {
                    continue;
                }
                $this->collectQueueStats($queueData['redis_key'], $queueData['clean_name'], $timestamp);
            }

            $this->collectSystemStats($timestamp);
        });
    }

    /**
     * Get configured queue names by scanning Redis keys.
     *
     * @return array Array of queue data with both full Redis key and clean name
     */
    protected function getConfiguredQueues(): array
    {
        if ($this->redis === null) {
            return [];
        }

        $queues = [];
        foreach ($this->queuePrefixes as $prefix) {
            $keys = $this->redis->keys($this->redisPrefix . $prefix . '*');
            foreach ($keys as $key) {
                $queueName = str_replace($this->redisPrefix, '', $key);
                $type = $this->redis->type($key);
                if (in_array($type, [Redis::REDIS_LIST, Redis::REDIS_SET, Redis::REDIS_ZSET])) {
                    $cleanQueueName = $this->stripQueuePrefix($queueName);
                    $queues[] = [
                        'redis_key' => $key,
                        'clean_name' => $cleanQueueName,
                    ];
                }
            }
        }

        return $queues;
    }

    /**
     * Collect statistics for a specific queue using Redis.
     *
     * @param string $redisKey Full Redis key with prefix
     * @param string $cleanQueueName Queue name without prefix (for metric recording)
     * @param int $timestamp Current timestamp
     * @return void
     */
    protected function collectQueueStats(string $redisKey, string $cleanQueueName, int $timestamp): void
    {
        try {
            $queueDepth = $this->getQueueDepth($redisKey);
            $this->rhythm->set('queue_depth', $cleanQueueName, (string)$queueDepth, $timestamp);

            $waitTimes = $this->getQueueWaitTimes($redisKey);
            $averageWaitTime = 0;
            $maximumWaitTime = 0;

            if ($waitTimes !== []) {
                $averageWaitTime = array_sum($waitTimes) / count($waitTimes);
                $maximumWaitTime = max($waitTimes);
            }

            $this->rhythm->set('queue_average_wait_time', $cleanQueueName, (string)$averageWaitTime, $timestamp);
            $this->rhythm->set('queue_maximum_wait_time', $cleanQueueName, (string)$maximumWaitTime, $timestamp);

            $this->rhythm->record('queue_average_wait_time', $cleanQueueName, (int)$averageWaitTime)
                ->avg()
                ->onlyBuckets();
            $this->rhythm->record('queue_maximum_wait_time', $cleanQueueName, (int)$maximumWaitTime)
                ->max()
                ->onlyBuckets();

            $healthScore = $this->calculateHealthScore($cleanQueueName, $queueDepth, $averageWaitTime);
            $this->rhythm->set('queue_health', $cleanQueueName, (string)$healthScore, $timestamp);

            $this->rhythm->set(
                'queue_state',
                $cleanQueueName,
                json_encode([
                    'name' => $cleanQueueName,
                    'depth' => $queueDepth,
                    'average_wait_time' => $averageWaitTime,
                    'maximum_wait_time' => $maximumWaitTime,
                    'job_count' => count($waitTimes),
                    'health_score' => $healthScore,
                    'last_updated' => $timestamp,
                ], JSON_THROW_ON_ERROR),
                $timestamp,
            );

            $this->rhythm->record('queue_depth', $cleanQueueName, $queueDepth)->avg()->onlyBuckets();
            $this->rhythm->record('queue_health', $cleanQueueName, $healthScore)->avg()->onlyBuckets();
        } catch (Exception) {
            $this->rhythm->set('queue_depth', $cleanQueueName, '0', $timestamp);
            $this->rhythm->set('queue_average_wait_time', $cleanQueueName, '0', $timestamp);
            $this->rhythm->set('queue_maximum_wait_time', $cleanQueueName, '0', $timestamp);
            $this->rhythm->set('queue_health', $cleanQueueName, '0', $timestamp);

            $this->rhythm->record('queue_depth', $cleanQueueName, 0)->count()->onlyBuckets();
            $this->rhythm->record('queue_average_wait_time', $cleanQueueName, 0)->avg()->onlyBuckets();
            $this->rhythm->record('queue_maximum_wait_time', $cleanQueueName, 0)->max()->onlyBuckets();
            $this->rhythm->record('queue_health', $cleanQueueName, 0)->avg()->count()->onlyBuckets();
        }
    }

    /**
     * Get queue depth (number of jobs in queue).
     *
     * @param string $redisKey Full Redis key with prefix
     * @return int
     */
    protected function getQueueDepth(string $redisKey): int
    {
        if ($this->redis === null) {
            return 0;
        }

        $type = $this->redis->type($redisKey);

        switch ($type) {
            case Redis::REDIS_LIST:
                $result = $this->redis->llen($redisKey);

                return is_int($result) ? $result : 0;
            case Redis::REDIS_SET:
                $result = $this->redis->scard($redisKey);

                return is_int($result) ? $result : 0;
            case Redis::REDIS_ZSET:
                $result = $this->redis->zcard($redisKey);

                return is_int($result) ? $result : 0;
            default:
                return 0;
        }
    }

    /**
     * Get wait times for a sample of jobs in a queue.
     *
     * @param string $redisKey Full Redis key with prefix
     * @param int $sampleSize Maximum number of jobs to sample
     * @return array
     */
    protected function getQueueWaitTimes(string $redisKey, int $sampleSize = 10): array
    {
        $waitTimes = [];
        if ($this->redis === null) {
            return $waitTimes;
        }

        $type = $this->redis->type($redisKey);

        switch ($type) {
            case Redis::REDIS_LIST:
                for ($i = 0; $i < $sampleSize; $i++) {
                    $jobJson = $this->redis->lindex($redisKey, $i);
                    if ($jobJson === false || $jobJson === null) {
                        break;
                    }
                    $waitTime = $this->getJobWaitTimeFromJson($jobJson);
                    if ($waitTime > 0) {
                        $waitTimes[] = $waitTime;
                    }
                }
                break;
            case Redis::REDIS_SET:
                $jobData = $this->redis->sRandMember($redisKey, $sampleSize);
                if (is_array($jobData)) {
                    foreach ($jobData as $jobJson) {
                        $waitTime = $this->getJobWaitTimeFromJson($jobJson);
                        if ($waitTime > 0) {
                            $waitTimes[] = $waitTime;
                        }
                    }
                }
                break;
            case Redis::REDIS_ZSET:
                $jobData = $this->redis->zRange($redisKey, 0, $sampleSize - 1);
                if (is_array($jobData)) {
                    foreach ($jobData as $jobJson) {
                        $waitTime = $this->getJobWaitTimeFromJson($jobJson);
                        if ($waitTime > 0) {
                            $waitTimes[] = $waitTime;
                        }
                    }
                }
                break;
            default:
                return [];
        }

        return $waitTimes;
    }

    /**
     * Get job wait time from JSON job data.
     *
     * @param string $jobJson JSON string containing job data
     * @return float
     */
    protected function getJobWaitTimeFromJson(string $jobJson): float
    {
        $jobData = json_decode($jobJson, true);
        if (!$jobData || !isset($jobData['headers']['timestamp'])) {
            return 0.0;
        }

        $createdAt = (int)$jobData['headers']['timestamp'];
        $currentTime = (new DateTime())->getTimestamp();

        return (float)($currentTime - $createdAt);
    }

    /**
     * Get average wait time for a queue.
     *
     * @param string $queueName Queue name
     * @return float
     */
    protected function getAverageQueueWaitTime(string $queueName): float
    {
        $waitTimes = $this->getQueueWaitTimes($queueName);

        if ($waitTimes === []) {
            return 0.0;
        }

        return array_sum($waitTimes) / count($waitTimes);
    }

    /**
     * Get maximum wait time for a queue.
     *
     * @param string $queueName Queue name
     * @return float
     */
    protected function getMaximumQueueWaitTime(string $queueName): float
    {
        $waitTimes = $this->getQueueWaitTimes($queueName);

        if ($waitTimes === []) {
            return 0.0;
        }

        return max($waitTimes);
    }

    /**
     * Calculate queue health score (0-100).
     *
     * @param string $queueName Queue name
     * @param int $queueDepth Current queue depth
     * @param float $averageWaitTime Current average wait time
     * @return int Health score (0-100)
     */
    protected function calculateHealthScore(string $queueName, int $queueDepth, float $averageWaitTime): int
    {
        $depthScore = max(0, 100 - ($queueDepth * 2));
        $waitScore = max(0, 100 - ($averageWaitTime * 0.1));

        return (int)min(100, max(0, ($depthScore + $waitScore) / 2));
    }

    /**
     * Collect overall queue system statistics.
     *
     * @param int $timestamp Current timestamp
     * @return void
     */
    protected function collectSystemStats(int $timestamp): void
    {
        $queues = $this->getConfiguredQueues();
        $totalDepth = 0;
        $totalWaitTime = 0;
        $totalJobs = 0;
        $maxWaitTime = 0;

        foreach ($queues as $queueData) {
            try {
                $redisKey = $queueData['redis_key'];
                $queueDepth = $this->getQueueDepth($redisKey);
                $waitTimes = $this->getQueueWaitTimes($redisKey);

                $totalDepth += $queueDepth;
                $totalWaitTime += array_sum($waitTimes);
                $totalJobs += count($waitTimes);
                $maxWaitTime = max($maxWaitTime, max($waitTimes ?: [0]));
            } catch (Exception) {
                continue;
            }
        }

        $averageWaitTime = $totalJobs > 0 ? $totalWaitTime / $totalJobs : 0;

        $this->rhythm->set('queue_system_total_depth', 'system', (string)$totalDepth, $timestamp);
        $this->rhythm->set('queue_system_total_jobs', 'system', (string)$totalJobs, $timestamp);
        $this->rhythm->set('queue_system_average_wait_time', 'system', (string)$averageWaitTime, $timestamp);
        $this->rhythm->set('queue_system_maximum_wait_time', 'system', (string)$maxWaitTime, $timestamp);

        $systemHealth = $this->calculateSystemHealth($totalDepth, $totalJobs, $averageWaitTime);
        $this->rhythm->set('queue_system_health', 'system', (string)$systemHealth, $timestamp);

        $this->rhythm->record('queue_system_total_depth', 'system', $totalDepth)
            ->count()
            ->onlyBuckets();

        $this->rhythm->record('queue_system_total_jobs', 'system', $totalJobs)
            ->count()
            ->onlyBuckets();

        $this->rhythm->record('queue_system_average_wait_time', 'system', (int)$averageWaitTime)
            ->count()
            ->onlyBuckets();

        $this->rhythm->record('queue_system_maximum_wait_time', 'system', (int)$maxWaitTime)
            ->max()
            ->onlyBuckets();

        $this->rhythm->record('queue_system_health', 'system', $systemHealth)
            ->count()
            ->onlyBuckets();
    }

    /**
     * Calculate system-wide health score.
     *
     * @param int $totalDepth Total queue depth
     * @param int $totalJobs Total jobs across all queues
     * @param float $averageWaitTime Average wait time across all queues
     * @return int System health score (0-100)
     */
    protected function calculateSystemHealth(int $totalDepth, int $totalJobs, float $averageWaitTime): int
    {
        $depthScore = max(0, 100 - $totalDepth);
        $jobScore = min(100, max(0, 100 - ($totalJobs * 0.5)));
        $waitScore = max(0, 100 - ($averageWaitTime * 0.05));

        return (int)min(100, max(0, ($depthScore + $jobScore + $waitScore) / 3));
    }
}

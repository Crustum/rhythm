<?php
declare(strict_types=1);

namespace Rhythm\Ingest;

use Cake\Collection\Collection;
use Cake\Collection\CollectionInterface;
use Redis;
use Rhythm\Datasource\RedisConnection;
use RuntimeException;

/**
 * Redis Ingest Implementation
 *
 * Uses Redis lists for communication between Rhythm class and digest command.
 *
 * @property \Redis $redis
 */
class RedisIngest extends AbstractIngest
{
    /**
     * Redis connection.
     *
     * @var \Redis|null
     */
    protected ?Redis $redis = null;

    /**
     * Queue key for metric entries.
     *
     * @var string
     */
    protected string $queueKey;

    /**
     * Processing queue key.
     *
     * @var string
     */
    protected string $processingKey;

    /**
     * Initialize the Redis ingest implementation.
     *
     * @return void
     * @throws \RuntimeException When Redis extension is not available
     */
    protected function initialize(): void
    {
        if (!extension_loaded('redis')) {
            throw new RuntimeException('Redis extension is required for RedisIngest');
        }

        $this->queueKey = $this->getConfig('queue_key', 'rhythm:metrics:queue');
        $this->processingKey = $this->getConfig('processing_key', 'rhythm:metrics:processing');

        $config = [
            'host' => $this->getConfig('host', '127.0.0.1'),
            'port' => $this->getConfig('port', 6379),
            'password' => $this->getConfig('password'),
            'database' => $this->getConfig('database'),
        ];

        $redis = RedisConnection::create($config);
        if (!$redis) {
            throw new RuntimeException('Failed to create Redis connection for RedisIngest');
        }
        $this->redis = $redis;
    }

    /**
     * Store items in Redis queue.
     *
     * @param \Cake\Collection\CollectionInterface $items Collection of metric entries
     * @return void
     */
    protected function storeItems(CollectionInterface $items): void
    {
        $pipe = $this->redis->pipeline();
        foreach ($items as $item) {
            $pipe->lPush($this->queueKey, serialize($item));
        }
        $pipe->exec();
    }

    /**
     * Process digest items from Redis queue to final storage using batch processing.
     * Processes all items in queue until empty.
     *
     * @return int Number of items processed
     */
    protected function processDigestItems(): int
    {
        $total = 0;
        $batchSize = $this->getConfig('batch_size', 500);

        while (true) {
            $batch = [];
            $queueLength = $this->redis->lLen($this->queueKey);

            if ($queueLength === 0) {
                return $total;
            }

            $itemsToProcess = min($batchSize, $queueLength);

            for ($i = 0; $i < $itemsToProcess; $i++) {
                $result = $this->redis->rPop($this->queueKey);

                if (!$result) {
                    break;
                }

                $entry = unserialize($result);
                if ($entry) {
                    $batch[] = $entry;
                }
            }
            if ($batch === []) {
                return $total;
            }

            $this->processBatch($batch);
            $total += count($batch);

            if (count($batch) < $itemsToProcess) {
                return $total;
            }
        }
    }

    /**
     * Process a batch of items through storage.
     *
     * @param array<int, \Rhythm\RhythmEntry|\Rhythm\RhythmValue> $batch Batch of metric entries
     * @return void
     */
    protected function processBatch(array $batch): void
    {
        if ($batch === []) {
            return;
        }

        $this->storage->store(new Collection($batch));
    }

    /**
     * Remove old data from Redis queues.
     *
     * @param int $cutoff Cutoff timestamp
     * @return void
     */
    protected function removeOldData(int $cutoff): void
    {
        $processingItems = $this->redis->lRange($this->processingKey, 0, -1);
        foreach ($processingItems as $item) {
            $data = json_decode($item, true);
            if ($data && $data['created'] < $cutoff) {
                $this->redis->lRem($this->processingKey, $item, 1);
            }
        }
    }

    /**
     * Get queue statistics.
     *
     * @return array<string, mixed> Queue statistics
     */
    public function getStats(): array
    {
        return [
            'queue_key' => $this->queueKey,
            'processing_key' => $this->processingKey,
            'queue_length' => $this->redis->lLen($this->queueKey),
            'processing_length' => $this->redis->lLen($this->processingKey),
            'redis_info' => $this->redis->info(),
        ];
    }

    /**
     * Clear all queues (for testing/debugging).
     *
     * @return void
     */
    public function clear(): void
    {
        $this->redis->del($this->queueKey);
        $this->redis->del($this->processingKey);
    }
}

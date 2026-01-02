<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase;

use Cake\Collection\Collection;
use Crustum\Rhythm\Ingest\RedisIngest;
use ReflectionClass;

/**
 * Ingest Flow Test
 *
 * Tests the ingest system flows:
 * 1. Database ingest flow
 * 2. Redis ingest flow
 * 3. Abstract ingest functionality
 * 4. Ingest event dispatching
 */
class IngestFlowTest extends RhythmTestCase
{
    /**
     * Test Redis ingest flow (if Redis is available).
     *
     * @return void
     */
    public function testRedisIngestFlow(): void
    {
        $this->redisIngest->clear();

        $entries = [
            $this->createTestEntry('redis_test', 'key_1', 100),
            $this->createTestEntry('redis_test', 'key_2', 200),
            $this->createTestValue('redis_test', 'key_3', 'test_value'),
        ];

        $this->redisIngest->ingest(new Collection($entries));

        $stats = $this->redisIngest->getStats();
        $this->assertGreaterThan(0, $stats['queue_length'], 'Metrics not added to Redis queue');

        $digestCount = $this->redisIngest->digest();
        $this->assertGreaterThan(0, $digestCount, 'No metrics digested from Redis queue');

        $this->assertMetricEntryExists('redis_test', 'key_1', 100);
        $this->assertMetricEntryExists('redis_test', 'key_2', 200);
        $this->assertMetricValueExists('redis_test', 'key_3', 'test_value');
    }

    /**
     * Test abstract ingest functionality.
     *
     * @return void
     */
    public function testAbstractIngestFunctionality(): void
    {
        $config = [
            'trim' => [
                'keep' => '2 hours',
            ],
            'max_items_per_digest' => 50,
        ];

        $redisIngest = new RedisIngest($this->storage, $config);

        $reflection = new ReflectionClass($redisIngest);
        $property = $reflection->getProperty('config');
        $retrievedConfig = $property->getValue($redisIngest);
        $this->assertEquals('2 hours', $retrievedConfig['trim']['keep']);
        $this->assertEquals(50, $retrievedConfig['max_items_per_digest']);
        $this->assertEquals('default', $retrievedConfig['nonexistent'] ?? 'default');
    }

    /**
     * Test Redis ingest statistics.
     *
     * @return void
     */
    public function testRedisIngestStatistics(): void
    {
        $this->redisIngest->clear();

        $initialStats = $this->redisIngest->getStats();

        $this->assertEquals(0, $initialStats['queue_length']);
        $this->assertEquals(0, $initialStats['processing_length']);

        $entries = [
            $this->createTestEntry('stats_test', 'key_1', 100),
            $this->createTestEntry('stats_test', 'key_2', 200),
        ];

        $this->redisIngest->ingest(new Collection($entries));

        $updatedStats = $this->redisIngest->getStats();

        $this->assertGreaterThan(0, $updatedStats['queue_length']);
        $this->assertArrayHasKey('redis_info', $updatedStats);
    }

    /**
     * Test ingest performance.
     *
     * @group performance
     * @return void
     */
    public function testIngestPerformance(): void
    {
        $this->redisIngest = new RedisIngest($this->storage, ['batch_size' => 1000]);

        $this->redisIngest->clear();

        $startTime = microtime(true);

        $entries = [];
        for ($i = 0; $i < 1000; $i++) {
            $key = 'key_' . ($i % 20);
            $entries[] = $this->createTestEntry('perf_test', $key, $i);
        }

        $this->redisIngest->ingest(new Collection($entries));

        $ingestTime = microtime(true) - $startTime;

        $this->assertLessThan(2.0, $ingestTime, "Ingesting 100 metrics took too long: {$ingestTime}s");

        $startTime = microtime(true);
        $this->redisIngest->digest();
        $digestTime = microtime(true) - $startTime;

        $this->assertLessThan(2.0, $digestTime, "Digesting 100 metrics took too long: {$digestTime}s");
    }
}

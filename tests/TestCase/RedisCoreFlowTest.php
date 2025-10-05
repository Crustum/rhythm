<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase;

use Rhythm\Rhythm;

/**
 * Redis Core Flow Test
 *
 * Extends the CoreFlowTest to run all the same flows, but re-wired
 * to use the RedisIngest service instead of the default DatabaseIngest.
 */
class RedisCoreFlowTest extends CoreFlowTest
{
    /**
     * Test setup
     *
     * Overrides the parent setup to re-wire the Rhythm service to use
     * the RedisIngest driver.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->redisIngest->clear();

        $this->rhythm = new Rhythm($this->storage, $this->redisIngest, $this->container);
    }

    /**
     * Override the parent method to test the full Redis ingest and digest flow.
     *
     * @return void
     */
    public function testIngestFlow(): void
    {
        $this->rhythm->record('redis_test', 'key_1', 100);
        $this->rhythm->set('redis_test', 'key_2', 'value_2');

        $initialStats = $this->redisIngest->getStats();
        $this->assertEquals(0, $initialStats['queue_length']);

        $ingestCount = $this->rhythm->ingest();
        $this->assertEquals(2, $ingestCount);

        $queuedStats = $this->redisIngest->getStats();
        $this->assertEquals(2, $queuedStats['queue_length']);

        $digestCount = $this->redisIngest->digest();
        $this->assertEquals(2, $digestCount);

        $finalStats = $this->redisIngest->getStats();
        $this->assertEquals(0, $finalStats['queue_length']);

        $this->assertMetricEntryExists('redis_test', 'key_1', 100);
        $this->assertMetricValueExists('redis_test', 'key_2', 'value_2');
    }
}

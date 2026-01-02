<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase;

use Cake\Collection\Collection;
use Cake\I18n\DateTime;
use Crustum\Rhythm\RhythmEntry;
use Crustum\Rhythm\RhythmValue;

/**
 * RedisIngest Test Case
 */
class RedisIngestTest extends RhythmTestCase
{
    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->redisIngest->clear();
    }

    /**
     * Test basic ingest functionality
     *
     * @return void
     */
    public function testBasicIngest(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $items = new Collection([
            new RhythmEntry($timestamp, 'request', 'user:123', 100),
            new RhythmValue($timestamp, 'user', 'active:123', 'John Doe'),
        ]);

        $this->redisIngest->ingest($items);

        $stats = $this->redisIngest->getStats();
        $this->assertEquals(2, $stats['queue_length']);
    }

    /**
     * Test ingest with empty collection
     *
     * @return void
     */
    public function testIngestEmpty(): void
    {
        $items = new Collection([]);

        $this->redisIngest->ingest($items);

        $stats = $this->redisIngest->getStats();
        $this->assertEquals(0, $stats['queue_length']);
    }
}

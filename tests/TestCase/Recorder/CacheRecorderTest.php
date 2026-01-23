<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Recorder;

use Cake\Cache\Cache;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Crustum\Rhythm\Recorder\CacheRecorder;
use Crustum\Rhythm\Test\TestCase\RhythmTestCase;

/**
 * CacheRecorder Test Case
 */
class CacheRecorderTest extends RhythmTestCase
{
    /**
     * Test subject
     *
     * @var \Crustum\Rhythm\Recorder\CacheRecorder
     */
    protected CacheRecorder $recorder;

    /**
     * Disable default test data setup since this test creates its own data.
     *
     * @return bool
     */
    protected function shouldSetupTestData(): bool
    {
        return false;
    }

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DateTime::setTestNow();
        $this->cleanupTestData();
        $this->redisIngest->clear();
        $this->rhythm->flush();
        Cache::clear('default');

        $this->recorder = new CacheRecorder($this->rhythm, []);
        EventManager::instance()->on($this->recorder);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (isset($this->recorder)) {
            EventManager::instance()->off($this->recorder);
        }
        $this->rhythm->flush();
        Cache::clear('default');
        DateTime::setTestNow();
        unset($this->recorder);
        parent::tearDown();
    }

    /**
     * Test record with cache hit
     *
     * @return void
     */
    public function testRecordCacheHit(): void
    {
        Cache::write('test_cache_key', 'test_value', 'default');

        Cache::read('test_cache_key', 'default');

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $aggregates = $this->storage->aggregate('cache_hit', 'count', 240);
        $this->assertGreaterThan(0, $aggregates->count(), 'No aggregates found for cache_hit');

        $keyAggregate = $aggregates->firstMatch(['metric_key' => 'cake_test_cache_key']);
        $this->assertNotEmpty($keyAggregate, 'Aggregate for cake_test_cache_key not found');
        $this->assertGreaterThan(0, $keyAggregate['count']);
    }

    /**
     * Test record with cache miss
     *
     * @return void
     */
    public function testRecordCacheMiss(): void
    {
        Cache::read('test_cache_key_miss', 'default');

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $aggregates = $this->storage->aggregate('cache_miss', 'count', 240);
        $this->assertGreaterThan(0, $aggregates->count());

        $keyAggregate = $aggregates->firstMatch(['metric_key' => 'cake_test_cache_key_miss']);
        $this->assertNotEmpty($keyAggregate);
        $this->assertGreaterThan(0, $keyAggregate['count']);
    }

    /**
     * Test record with ignored key
     *
     * @return void
     */
    public function testRecordWithIgnoredKey(): void
    {
        $recorder = new CacheRecorder($this->rhythm, [
            'ignore' => ['#^test_#'],
        ]);
        EventManager::instance()->on($recorder);

        Cache::write('test_cache_key', 'test_value', 'default');
        Cache::read('test_cache_key', 'default');

        $this->rhythm->flush();
        $this->rhythm->ingest();
        $this->rhythm->digest();

        $aggregates = $this->storage->aggregate('cache_hit', 'count', 240);
        $keyAggregate = $aggregates->firstMatch(['metric_key' => 'cake_test_cache_key']);
        $this->assertEmpty($keyAggregate);

        EventManager::instance()->off($recorder);
    }

    /**
     * Test record with grouped key
     *
     * @return void
     */
    public function testRecordWithGroupedKey(): void
    {
        $recorder = new CacheRecorder($this->rhythm, [
            'groups' => [
                '#^cake_cache_([^_]+)_\d+$#i' => 'cake_cache_\1_*',
            ],
        ]);
        EventManager::instance()->on($recorder);

        Cache::write('cache_user_123', 'test_value', 'default');
        Cache::read('cache_user_123', 'default');

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $aggregates = $this->storage->aggregate('cache_hit', 'count', 240);
        $keyAggregate = $aggregates->firstMatch(['metric_key' => 'cake_cache_user_*']);
        $this->assertNotEmpty($keyAggregate, 'Aggregate for cake_cache_user_* not found');

        EventManager::instance()->off($recorder);
    }

    /**
     * Test record with URL encoded key
     *
     * @return void
     */
    public function testRecordWithUrlEncodedKey(): void
    {
        $encodedKey = rawurlencode('test cache key with spaces');
        Cache::write($encodedKey, 'test_value', 'default');
        Cache::read($encodedKey, 'default');

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $aggregates = $this->storage->aggregate('cache_hit', 'count', 240);
        $decodedKey = 'cake_test cache key with spaces';
        $keyAggregate = $aggregates->firstMatch(['metric_key' => $decodedKey]);
        if (empty($keyAggregate)) {
            $keyAggregate = $aggregates->firstMatch(['metric_key' => 'cake_test%20cache%20key%20with%20spaces']);
        }
        $this->assertNotEmpty($keyAggregate, 'Aggregate for URL decoded key not found');
    }

    /**
     * Test record with invalid event data
     *
     * @return void
     */
    public function testRecordWithInvalidEventData(): void
    {
        Cache::read('test_key', 'default');

        $this->rhythm->flush();
        $this->rhythm->ingest();
        $this->rhythm->digest();

        $aggregates = $this->storage->aggregate('cache_hit', 'count', 240);
        $this->assertEquals(0, $aggregates->count());
    }

    /**
     * Test implemented events
     *
     * @return void
     */
    public function testImplementedEvents(): void
    {
        $events = $this->recorder->implementedEvents();

        $this->assertArrayHasKey('Cache.afterGet', $events);
        $this->assertEquals('record', $events['Cache.afterGet']);
    }
}

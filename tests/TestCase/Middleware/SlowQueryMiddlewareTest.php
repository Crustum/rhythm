<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Middleware;

use Cake\Core\Container;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Rhythm\Database\Log\RhythmQueryLogger;
use Rhythm\Event\SlowQueryEvent;
use Rhythm\Ingest\TransparentIngest;
use Rhythm\Recorder\SlowQueriesRecorder;
use Rhythm\Rhythm;
use Rhythm\Storage\DigestStorage;

/**
 * Slow Query Recorder Test Case
 *
 * Tests the slow query recording functionality using the recorder approach.
 * Initializes RhythmQueryLogger directly and tests SlowQueriesRecorder.
 */
class SlowQueryMiddlewareTest extends TestCase
{
    /**
     * Rhythm instance
     *
     * @var \Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Query logger instance
     *
     * @var \Rhythm\Database\Log\RhythmQueryLogger
     */
    protected RhythmQueryLogger $queryLogger;

    /**
     * Event manager instance
     *
     * @var \Cake\Event\EventManager
     */
    protected EventManager $eventManager;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $storage = new DigestStorage([
            'connection' => 'test',
            'tables' => [
                'entries' => 'rhythm_entries',
                'aggregates' => 'rhythm_aggregates',
            ],
        ]);

        $ingest = new TransparentIngest($storage);

        $container = new Container();
        $this->rhythm = new Rhythm($storage, $ingest, $container);

        $this->eventManager = new EventManager();

        $this->rhythm->register([
            'slow_queries' => [
                'className' => SlowQueriesRecorder::class,
                'enabled' => true,
                'threshold' => 0,
                'sample_rate' => 1.0,
                'max_query_length' => 1000,
            ],
        ]);

        $recorder = $this->rhythm->getRecorder(SlowQueriesRecorder::class);
        if ($recorder instanceof EventListenerInterface) {
            $this->eventManager->on($recorder);
        }

        $this->queryLogger = new RhythmQueryLogger(
            null,
            'test',
            0,
        );

        $this->rhythm->flush();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (isset($this->rhythm)) {
            $this->rhythm->flush();
        }

        unset($this->rhythm);
        unset($this->queryLogger);

        parent::tearDown();
    }

    /**
     * Test query logger initialization
     *
     * @return void
     */
    public function testQueryLoggerInitialization(): void
    {
        $this->assertInstanceOf(RhythmQueryLogger::class, $this->queryLogger);
        $this->assertEquals('test', $this->queryLogger->name());
        $this->assertEquals(0, $this->queryLogger->getThreshold());
    }

    /**
     * Test slow query event handling
     *
     * @return void
     */
    public function testSlowQueryEventHandling(): void
    {
        $sql = 'SELECT * FROM users WHERE id = 1';
        $duration = 150;
        $location = 'src/Controller/UsersController.php:25';

        $recorder = $this->rhythm->getRecorder(SlowQueriesRecorder::class);
        $this->assertNotNull($recorder, 'SlowQueriesRecorder should be registered');

        $event = new SlowQueryEvent($sql, $duration, $location);
        $this->eventManager->dispatch($event);

        $entries = $this->rhythm->entries()->toArray();
        $this->assertGreaterThan(0, count($entries), 'Rhythm should have entries after event dispatch');

        $entry = $entries[0];
        $this->assertEquals('slow_query', $entry->type);
        $this->assertEquals($duration, $entry->value);

        $keyData = json_decode($entry->key, true);
        $this->assertEquals($sql, $keyData[0]);
        $this->assertEquals($location, $keyData[1]);
    }

    /**
     * Test query length truncation
     *
     * @return void
     */
    public function testQueryLengthTruncation(): void
    {
        $longSql = str_repeat('SELECT * FROM users WHERE id = 1 AND ', 50) . 'id > 0';
        $duration = 150;

        $event = new SlowQueryEvent($longSql, $duration);

        $this->eventManager->dispatch($event);

        $entries = $this->rhythm->entries()->toArray();
        $this->assertGreaterThan(0, count($entries), 'Rhythm should have entries after event dispatch');

        $entry = $entries[0];

        $this->assertGreaterThan(0, count($entries), 'Slow query entries should be recorded for long queries');
        $keyData = json_decode($entry->key, true);
        $this->assertLessThanOrEqual(1000, strlen($keyData[0]));
        $this->assertStringEndsWith('...', $keyData[0]);
    }

    /**
     * Test threshold filtering
     *
     * @return void
     */
    public function testThresholdFiltering(): void
    {
        $fastSql = 'SELECT * FROM users WHERE id = 1';
        $fastDuration = 50;

        $event = new SlowQueryEvent($fastSql, $fastDuration);
        $this->eventManager->dispatch($event);

        $slowSql = 'SELECT * FROM users WHERE id = 1';
        $slowDuration = 150;

        $event = new SlowQueryEvent($slowSql, $slowDuration);
        $this->eventManager->dispatch($event);

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $entriesTable = $this->getTableLocator()->get('Rhythm.RhythmEntries');
        $entries = $entriesTable->find()
            ->where(['type' => 'slow_query'])
            ->orderBy(['timestamp' => 'DESC'])
            ->limit(2)
            ->toArray();

        $this->assertCount(2, $entries, 'All queries should be recorded with threshold 0');
    }

    /**
     * Test recorder configuration
     *
     * @return void
     */
    public function testRecorderConfiguration(): void
    {
        $recorder = $this->rhythm->getRecorder(SlowQueriesRecorder::class);
        $this->assertNotNull($recorder);

        $this->assertTrue($recorder->isEnabled(), 'Recorder should be enabled');

        $this->assertEquals(1.0, $recorder->getSampleRate(), 'Sample rate should be 100%');

        $this->assertInstanceOf(SlowQueriesRecorder::class, $recorder);
    }

    /**
     * Test recorder event listener registration
     *
     * @return void
     */
    public function testRecorderEventRegistration(): void
    {
        $recorder = $this->rhythm->getRecorder(SlowQueriesRecorder::class);
        $this->assertNotNull($recorder);

        $this->assertInstanceOf(EventListenerInterface::class, $recorder);

        $eventManager = EventManager::instance();
        $listeners = $eventManager->listeners('Rhythm.slowQuery');
        $this->assertNotEmpty($listeners, 'Recorder should be registered with event manager');
    }

    /**
     * Test direct recorder method call
     *
     * @return void
     */
    public function testDirectRecorderMethod(): void
    {
        $recorder = $this->rhythm->getRecorder(SlowQueriesRecorder::class);
        $this->assertNotNull($recorder);

        $this->assertTrue($recorder->isEnabled(), 'Recorder should be enabled');
        $this->assertEquals(1.0, $recorder->getSampleRate(), 'Sample rate should be 100%');

        $sql = 'SELECT * FROM direct_test WHERE id = 999';
        $duration = 250;
        $location = 'src/Controller/DirectTestController.php:99';

        $event = new SlowQueryEvent($sql, $duration, $location);

        $this->eventManager->dispatch($event);

        $entries = $this->rhythm->entries()->toArray();
        $this->assertGreaterThan(0, count($entries), 'Rhythm should have entries after direct recorder call');

        $entry = $entries[0];
        $this->assertEquals('slow_query', $entry->type);
        $this->assertEquals($duration, $entry->value);

        $keyData = json_decode($entry->key, true);
        $this->assertEquals($sql, $keyData[0]);
        $this->assertEquals($location, $keyData[1]);
    }

    /**
     * Test multiple slow queries
     *
     * @return void
     */
    public function testMultipleSlowQueries(): void
    {
        $queries = [
        ['sql' => 'SELECT * FROM multiple_test_1 WHERE id = 1', 'duration' => 110],
        ['sql' => 'SELECT * FROM multiple_test_2 WHERE user_id = 1', 'duration' => 190],
        ['sql' => 'SELECT * FROM multiple_test_3 WHERE post_id = 1', 'duration' => 210],
        ];

        foreach ($queries as $query) {
            $event = new SlowQueryEvent($query['sql'], $query['duration']);
            $this->eventManager->dispatch($event);
        }

        $entries = $this->rhythm->entries()->toArray();

        $testEntries = array_filter($entries, function ($entry) use ($queries) {
            $expectedDurations = array_column($queries, 'duration');

            return in_array($entry->value, $expectedDurations);
        });

        $this->assertGreaterThanOrEqual(3, count($testEntries), 'All slow queries should be recorded');

        $recordedDurations = array_column($testEntries, 'value');
        $expectedDurations = array_column($queries, 'duration');

        foreach ($expectedDurations as $expectedDuration) {
            $this->assertContains(
                $expectedDuration,
                $recordedDurations,
                "Expected duration {$expectedDuration} should be recorded",
            );
        }
    }
}

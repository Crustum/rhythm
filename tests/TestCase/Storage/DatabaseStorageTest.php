<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Storage;

use Cake\Collection\Collection;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use ReflectionClass;
use Rhythm\Model\Table\RhythmAggregatesTable;
use Rhythm\Model\Table\RhythmEntriesTable;
use Rhythm\Storage\DigestStorage;

/**
 * Test case for aggregate methods in Storage
 */
class DatabaseStorageTest extends TestCase
{
    /**
     * No fixtures needed - using direct table operations
     */

    /**
     * Database storage instance
     *
     * @var \Rhythm\Storage\DigestStorage
     */
    protected DigestStorage $storage;

    /**
     * Entries table instance
     *
     * @var \Rhythm\Model\Table\RhythmEntriesTable
     */
    protected RhythmEntriesTable $entriesTable;

    /**
     * Aggregates table instance
     *
     * @var \Rhythm\Model\Table\RhythmAggregatesTable
     */
    protected RhythmAggregatesTable $aggregatesTable;

    /**
     * Test data helper
     *
     * @var array
     */
    protected array $testData = [];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        DateTime::setTestNow('2025-01-15 12:30:00');

        $this->storage = new DigestStorage();
        /** @var \Rhythm\Model\Table\RhythmEntriesTable $entriesTable */
        $entriesTable = TableRegistry::getTableLocator()->get('Rhythm.RhythmEntries');
        $this->entriesTable = $entriesTable;
        /** @var \Rhythm\Model\Table\RhythmAggregatesTable $aggregatesTable */
        $aggregatesTable = TableRegistry::getTableLocator()->get('Rhythm.RhythmAggregates');
        $this->aggregatesTable = $aggregatesTable;

        $this->storage->purge();

        $this->generateTestData();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        DateTime::setTestNow(null);

        unset($this->storage);
        unset($this->entriesTable);
        unset($this->aggregatesTable);

        parent::tearDown();
    }

    /**
     * Generate test data for aggregate testing
     *
     * @return void
     */
    protected function generateTestData(): void
    {
        $now = (new DateTime())->getTimestamp();
        $oneHourAgo = $now - 3600;
        $twoHoursAgo = $now - 7200;

        $entriesData = [
            [
                'timestamp' => $oneHourAgo,
                'type' => 'request',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 120,
            ],
            [
                'timestamp' => $oneHourAgo + 300,
                'type' => 'request',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 95,
            ],
            [
                'timestamp' => $oneHourAgo + 600,
                'type' => 'request',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 180,
            ],

            [
                'timestamp' => $oneHourAgo,
                'type' => 'request',
                'key' => '/api/posts',
                'key_hash' => md5('/api/posts'),
                'value' => 75,
            ],
            [
                'timestamp' => $oneHourAgo + 150,
                'type' => 'request',
                'key' => '/api/posts',
                'key_hash' => md5('/api/posts'),
                'value' => 110,
            ],

            [
                'timestamp' => $oneHourAgo,
                'type' => 'database',
                'key' => 'users_query',
                'key_hash' => md5('users_query'),
                'value' => 25,
            ],
            [
                'timestamp' => $oneHourAgo + 200,
                'type' => 'database',
                'key' => 'users_query',
                'key_hash' => md5('users_query'),
                'value' => 30,
            ],
            [
                'timestamp' => $oneHourAgo + 400,
                'type' => 'database',
                'key' => 'users_query',
                'key_hash' => md5('users_query'),
                'value' => 22,
            ],

            [
                'timestamp' => $twoHoursAgo,
                'type' => 'cache',
                'key' => 'user_profile',
                'key_hash' => md5('user_profile'),
                'value' => 5,
            ],
        ];

        foreach ($entriesData as $data) {
            $entity = $this->entriesTable->newEntity($data);
            $this->entriesTable->save($entity);
        }

        $bucketTime60 = (int)(floor($now / 3600) * 3600);
        $bucketTime360 = (int)(floor($now / 21600) * 21600);
        $aggregatesData = [
            [
                'bucket' => $bucketTime60,
                'period' => 60,
                'type' => 'request',
                'aggregate' => 'count',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 15,
                'count' => 15,
            ],
            [
                'bucket' => $bucketTime60,
                'period' => 60,
                'type' => 'request',
                'aggregate' => 'sum',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 1800,
                'count' => 15,
            ],
            [
                'bucket' => $bucketTime60,
                'period' => 60,
                'type' => 'request',
                'aggregate' => 'min',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 50,
                'count' => 15,
            ],
            [
                'bucket' => $bucketTime60,
                'period' => 60,
                'type' => 'request',
                'aggregate' => 'max',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 200,
                'count' => 15,
            ],
            [
                'bucket' => $bucketTime60,
                'period' => 60,
                'type' => 'request',
                'aggregate' => 'avg',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 120,
                'count' => 15,
            ],

            [
                'bucket' => $bucketTime360,
                'period' => 360,
                'type' => 'request',
                'aggregate' => 'count',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 15,
                'count' => 15,
            ],
            [
                'bucket' => $bucketTime360,
                'period' => 360,
                'type' => 'request',
                'aggregate' => 'sum',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 1800,
                'count' => 15,
            ],
            [
                'bucket' => $bucketTime360,
                'period' => 360,
                'type' => 'request',
                'aggregate' => 'min',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 50,
                'count' => 15,
            ],
            [
                'bucket' => $bucketTime360,
                'period' => 360,
                'type' => 'request',
                'aggregate' => 'max',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 200,
                'count' => 15,
            ],
            [
                'bucket' => $bucketTime360,
                'period' => 360,
                'type' => 'request',
                'aggregate' => 'avg',
                'key' => '/api/users',
                'key_hash' => md5('/api/users'),
                'value' => 120,
                'count' => 15,
            ],

            [
                'bucket' => $bucketTime60,
                'period' => 60,
                'type' => 'database',
                'aggregate' => 'count',
                'key' => 'users_query',
                'key_hash' => md5('users_query'),
                'value' => 8,
                'count' => 8,
            ],
            [
                'bucket' => $bucketTime60,
                'period' => 60,
                'type' => 'database',
                'aggregate' => 'avg',
                'key' => 'users_query',
                'key_hash' => md5('users_query'),
                'value' => 27.5,
                'count' => 8,
            ],

            [
                'bucket' => $bucketTime360,
                'period' => 360,
                'type' => 'database',
                'aggregate' => 'count',
                'key' => 'users_query',
                'key_hash' => md5('users_query'),
                'value' => 8,
                'count' => 8,
            ],
            [
                'bucket' => $bucketTime360,
                'period' => 360,
                'type' => 'database',
                'aggregate' => 'avg',
                'key' => 'users_query',
                'key_hash' => md5('users_query'),
                'value' => 27.5,
                'count' => 8,
            ],
        ];

        foreach ($aggregatesData as $data) {
            $entity = $this->aggregatesTable->newEntity($data);
            $this->aggregatesTable->save($entity);
        }

        $this->testData = [
            'entries' => $entriesData,
            'aggregates' => $aggregatesData,
            'bucketTime60' => $bucketTime60,
            'bucketTime360' => $bucketTime360,
            'now' => $now,
            'oneHourAgo' => $oneHourAgo,
            'twoHoursAgo' => $twoHoursAgo,
        ];
    }

    /**
     * Test aggregate method with single aggregate
     *
     * @return void
     */
    public function testAggregateSingleAggregate(): void
    {
        $result = $this->storage->aggregate('request', 'count', 60);

        $this->assertInstanceOf(Collection::class, $result);
        $data = $result->toArray();

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('key', $data[0]);
        $this->assertArrayHasKey('count', $data[0]);

        $usersData = array_filter($data, fn(array $item) => $item['key'] === '/api/users');
        $this->assertNotEmpty($usersData);

        $usersEntry = array_values($usersData)[0];
        $this->assertEquals('/api/users', $usersEntry['key']);
        $this->assertGreaterThan(0, $usersEntry['count']);
    }

    /**
     * Test aggregate method with multiple aggregates
     *
     * @return void
     */
    public function testAggregateMultipleAggregates(): void
    {
        $result = $this->storage->aggregate('request', ['count', 'sum', 'avg'], 60);

        $this->assertInstanceOf(Collection::class, $result);
        $data = $result->toArray();

        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('count', $item);
            $this->assertArrayHasKey('sum', $item);
            $this->assertArrayHasKey('avg', $item);
        }

        $usersData = array_filter($data, fn(array $item) => $item['key'] === '/api/users');
        if ($usersData !== []) {
            $usersEntry = array_values($usersData)[0];
            $this->assertEquals(17, $usersEntry['count']);
            $this->assertEquals(2075, $usersEntry['sum']);
            $this->assertEqualsWithDelta(122.06, $usersEntry['avg'], 0.1);
        }
    }

    /**
     * Test aggregate method ordering
     *
     * @return void
     */
    public function testAggregateOrdering(): void
    {
        $resultDesc = $this->storage->aggregate('request', 'sum', 60, 'sum', 'desc');
        $dataDesc = $resultDesc->toArray();

        if (count($dataDesc) > 1) {
            $this->assertGreaterThanOrEqual($dataDesc[1]['sum'], $dataDesc[0]['sum']);
        }

        $resultAsc = $this->storage->aggregate('request', 'sum', 60, 'sum', 'asc');
        $dataAsc = $resultAsc->toArray();

        if (count($dataAsc) > 1) {
            $this->assertLessThanOrEqual($dataAsc[1]['sum'], $dataAsc[0]['sum']);
        }
    }

    /**
     * Test aggregateTypes method
     *
     * @return void
     */
    public function testAggregateTypes(): void
    {
        $result = $this->storage->aggregateTypes(['request', 'database'], 'count', 120);

        $this->assertInstanceOf(Collection::class, $result);
        $data = $result->toArray();

        $this->assertNotEmpty($data);

        foreach ($data as $item) {
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('request', $item);
            $this->assertArrayHasKey('database', $item);
        }

        $usersData = array_filter($data, fn(array $item) => $item['key'] === '/api/users');
        if ($usersData !== []) {
            $usersEntry = array_values($usersData)[0];
            $this->assertEquals(3, $usersEntry['request']);
            $this->assertEquals(0, $usersEntry['database']);
        }

        $queryData = array_filter($data, fn(array $item) => $item['key'] === 'users_query');
        if ($queryData !== []) {
            $queryEntry = array_values($queryData)[0];
            $this->assertEquals(0, $queryEntry['request']);
            $this->assertEquals(3, $queryEntry['database']);
        }
    }

    /**
     * Test aggregateTypes with single type
     *
     * @return void
     */
    public function testAggregateTypesSingleType(): void
    {
        $result = $this->storage->aggregateTypes('request', 'sum', 120);

        $this->assertInstanceOf(Collection::class, $result);
        $data = $result->toArray();

        $this->assertNotEmpty($data);

        foreach ($data as $item) {
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('request', $item);
        }
    }

    /**
     * Test aggregateTotal method with single type
     *
     * @return void
     */
    public function testAggregateTotalSingleType(): void
    {
        $result = $this->storage->aggregateTotal('request', 'count', 120);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);

        $this->assertEquals(5.0, $result);
    }

    /**
     * Test aggregateTotal method with multiple types
     *
     * @return void
     */
    public function testAggregateTotalMultipleTypes(): void
    {
        $result = $this->storage->aggregateTotal(['request', 'database'], 'count', 120);

        $this->assertInstanceOf(Collection::class, $result);
        $data = $result->toArray();

        $this->assertArrayHasKey('request', $data);
        $this->assertArrayHasKey('database', $data);

        $this->assertEquals(5, $data['request']);
        $this->assertEquals(3, $data['database']);
    }

    /**
     * Test aggregateTotal with different aggregates
     *
     * @return void
     */
    public function testAggregateTotalDifferentAggregates(): void
    {
        $sumResult = $this->storage->aggregateTotal('request', 'sum', 120);
        $this->assertEquals(580.0, $sumResult);

        $minResult = $this->storage->aggregateTotal('request', 'min', 120);
        $this->assertEquals(75.0, $minResult);

        $maxResult = $this->storage->aggregateTotal('request', 'max', 120);
        $this->assertEquals(180.0, $maxResult);

        $avgResult = $this->storage->aggregateTotal('request', 'avg', 120);
        $this->assertEquals(116.0, $avgResult);
    }

    /**
     * Test findBestPeriod method
     *
     * @return void
     */
    public function testFindBestPeriod(): void
    {
        $reflection = new ReflectionClass($this->storage);
        $method = $reflection->getMethod('findBestPeriod');

        $periods = [60, 360, 1440, 10080];

        $result = $method->invoke($this->storage, 30, $periods);
        $this->assertEquals(60, $result);

        $result = $method->invoke($this->storage, 120, $periods);
        $this->assertEquals(360, $result);

        $result = $method->invoke($this->storage, 20000, $periods);
        $this->assertEquals(10080, $result);
    }

    /**
     * Test with invalid aggregate types
     *
     * @return void
     */
    public function testInvalidAggregateTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid aggregate type(s) [invalid], allowed types: [count, min, max, sum, avg].');

        $this->storage->aggregate('request', ['count', 'invalid'], 60);
    }

    /**
     * Test with empty data
     *
     * @return void
     */
    public function testWithEmptyData(): void
    {
        $this->entriesTable->deleteAll([]);
        $this->aggregatesTable->deleteAll([]);

        $result = $this->storage->aggregate('nonexistent', 'count', 60);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result->toArray());

        $totalResult = $this->storage->aggregateTotal('nonexistent', 'count', 60);
        $this->assertEquals(0.0, $totalResult);
    }

    /**
     * Test limit functionality
     *
     * @return void
     */
    public function testLimitFunctionality(): void
    {
        $result = $this->storage->aggregate('request', 'count', 120, null, 'desc', 1);
        $data = $result->toArray();

        $this->assertLessThanOrEqual(1, count($data));
    }

    /**
     * Test aggregate method with avg, max, min
     *
     * @return void
     */
    public function testAggregateWithAllAggregates(): void
    {
        $this->aggregatesTable->updateAll(
            ['bucket' => (new DateTime())->getTimestamp() - 100],
            ['type' => 'request', 'key' => '/api/users'],
        );

        $result = $this->storage->aggregate('request', ['avg', 'max', 'min', 'count', 'sum'], 60);

        $apiUsers = $result->filter(fn($r) => $r['key'] === '/api/users')->first();
        $this->assertEquals(200, $apiUsers['max']);
        $this->assertEquals(50, $apiUsers['min']);
        $this->assertEqualsWithDelta(122.06, $apiUsers['avg'], 0.01);
        $this->assertEquals(17, $apiUsers['count']);
        $this->assertEquals(2075, $apiUsers['sum']);
    }

    /**
     * Test aggregate method with only avg and max (like the widget uses)
     *
     * @return void
     */
    public function testAggregateAvgMaxOnly(): void
    {
        $this->aggregatesTable->updateAll(
            ['bucket' => (new DateTime())->getTimestamp() - 100],
            ['type' => 'request', 'key' => '/api/users'],
        );

        $result = $this->storage->aggregate('request', ['avg', 'max'], 60);

        $apiUsers = $result->filter(fn($r) => $r['key'] === '/api/users')->first();
        $this->assertEquals(200, $apiUsers['max']);
        $this->assertGreaterThan(0, $apiUsers['avg']);
    }

    /**
     * Test graph method
     *
     * @return void
     */
    public function testGraph(): void
    {
        $this->aggregatesTable->updateAll(
            ['bucket' => (new DateTime())->getTimestamp() - 100],
            ['type IN' => ['request', 'database']],
        );

        $result = $this->storage->graph(['request', 'database'], 'count', 60);

        $jsonString = json_encode($result);
        $resultArray = $jsonString !== false ? json_decode($jsonString, true) : [];

        $this->assertIsArray($resultArray);
        $this->assertCount(2, $resultArray);

        $apiUsersGraph = $resultArray['/api/users'];
        $this->assertCount(2, $apiUsersGraph);
        $this->assertCount(60, $apiUsersGraph['request']);

        $requestPoints = (new Collection($apiUsersGraph['request']))->filter(fn($val) => $val !== null);
        $this->assertGreaterThan(0, $requestPoints->count());
    }
}

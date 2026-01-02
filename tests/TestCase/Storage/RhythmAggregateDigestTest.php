<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Storage;

use Cake\TestSuite\TestCase;
use Crustum\Rhythm\Storage\RhythmAggregateDigest;
use ReflectionClass;

/**
 * RhythmAggregateDigest Test Case
 *
 * Comprehensive tests for the modern array-based aggregation implementation.
 */
class RhythmAggregateDigestTest extends TestCase
{
    protected RhythmAggregateDigest $digest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->digest = new RhythmAggregateDigest([
            'table' => 'rhythm_aggregates',
            'chunkSize' => 100,
        ]);
    }

    protected function tearDown(): void
    {
        unset($this->digest);
        parent::tearDown();
    }

    /**
     * Test constructor with configuration
     */
    public function testConstructor(): void
    {
        $digest = new RhythmAggregateDigest([
            'table' => 'custom_table',
            'chunkSize' => 500,
        ]);

        $this->assertEquals('custom_table', $digest->getTable());
        $this->assertEquals(500, $digest->getChunkSize());
    }

    /**
     * Test constructor with defaults
     */
    public function testConstructorDefaults(): void
    {
        $digest = new RhythmAggregateDigest();

        $this->assertEquals('rhythm_aggregates', $digest->getTable());
        $this->assertEquals(1000, $digest->getChunkSize());
    }

    /**
     * Test processAggregations with empty data
     */
    public function testProcessAggregationsEmpty(): void
    {
        $result = $this->digest->processAggregations([]);
        $this->assertEquals(0, $result);
    }

    /**
     * Test processAggregations with count aggregation - single entry
     */
    public function testProcessAggregationsCountSingle(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
        ];

        $result = $this->digest->processAggregations(['count' => $entries]);

        $this->assertEquals(1, $result, 'Single count aggregation should affect 1 row');
    }

    /**
     * Test processAggregations with count aggregation - multiple entries same key
     */
    public function testProcessAggregationsCountMultipleSameKey(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 300,
            ],
        ];

        $result = $this->digest->processAggregations(['count' => $entries]);

        $this->assertEquals(1, $result, 'Multiple same-key count aggregation should affect 1 row with count=3');
    }

    /**
     * Test processAggregations with count aggregation - multiple different keys
     */
    public function testProcessAggregationsCountMultipleDifferentKeys(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:456',
                'key_hash' => md5('user:456'),
                'value' => 200,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:789',
                'key_hash' => md5('user:789'),
                'value' => 300,
            ],
        ];

        $result = $this->digest->processAggregations(['count' => $entries]);

        $this->assertEquals(3, $result, 'Multiple different-key count aggregation should affect 3 rows');
    }

    /**
     * Test processAggregations with sum aggregation
     */
    public function testProcessAggregationsSum(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 300,
            ],
        ];

        $result = $this->digest->processAggregations(['sum' => $entries]);

        $this->assertEquals(1, $result, 'Sum aggregation should affect 1 row with sum=600');
    }

    /**
     * Test processAggregations with min aggregation
     */
    public function testProcessAggregationsMin(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 300,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
        ];

        $result = $this->digest->processAggregations(['min' => $entries]);

        $this->assertEquals(1, $result, 'Min aggregation should affect 1 row with min=100');
    }

    /**
     * Test processAggregations with max aggregation
     */
    public function testProcessAggregationsMax(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 300,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
        ];

        $result = $this->digest->processAggregations(['max' => $entries]);

        $this->assertEquals(1, $result, 'Max aggregation should affect 1 row with max=300');
    }

    /**
     * Test processAggregations with avg aggregation
     */
    public function testProcessAggregationsAvg(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 300,
            ],
        ];

        $result = $this->digest->processAggregations(['avg' => $entries]);

        $this->assertEquals(1, $result, 'Avg aggregation should affect 1 row with avg=200');
    }

    /**
     * Test processAggregations with mixed aggregation types
     */
    public function testProcessAggregationsMixed(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
        ];

        $result = $this->digest->processAggregations([
            'count' => $entries,
            'sum' => $entries,
            'min' => $entries,
            'max' => $entries,
            'avg' => $entries,
        ]);

        $this->assertEquals(5, $result, 'Mixed aggregation should affect 5 rows (one per aggregation type)');
    }

    /**
     * Test processAggregations with different buckets
     */
    public function testProcessAggregationsDifferentBuckets(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609462800,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
        ];

        $result = $this->digest->processAggregations(['count' => $entries]);

        $this->assertEquals(2, $result, 'Different buckets should affect 2 rows (one per bucket)');
    }

    /**
     * Test processAggregations with different periods
     */
    public function testProcessAggregationsDifferentPeriods(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 86400,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
        ];

        $result = $this->digest->processAggregations(['count' => $entries]);

        $this->assertEquals(2, $result, 'Different periods should affect 2 rows (one per period)');
    }

    /**
     * Test processAggregations with different types
     */
    public function testProcessAggregationsDifferentTypes(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'memory',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
        ];

        $result = $this->digest->processAggregations(['count' => $entries]);

        $this->assertEquals(2, $result, 'Different types should affect 2 rows (one per type)');
    }

    /**
     * Test aggregateEntries with count aggregation
     */
    public function testAggregateEntriesCount(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
        ];

        $reflection = new ReflectionClass($this->digest);
        $method = $reflection->getMethod('aggregateEntries');

        $result = $method->invoke($this->digest, $entries, 'count');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(2, $result[0]['value']);
        $this->assertEquals(2, $result[0]['entry_count']);
        $this->assertEquals('count', $result[0]['aggregate']);
    }

    /**
     * Test aggregateEntries with sum aggregation
     */
    public function testAggregateEntriesSum(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
        ];

        $reflection = new ReflectionClass($this->digest);
        $method = $reflection->getMethod('aggregateEntries');

        $result = $method->invoke($this->digest, $entries, 'sum');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(300, $result[0]['value']);
        $this->assertEquals(2, $result[0]['entry_count']);
        $this->assertEquals('sum', $result[0]['aggregate']);
    }

    /**
     * Test aggregateEntries with min aggregation
     */
    public function testAggregateEntriesMin(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 300,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
        ];

        $reflection = new ReflectionClass($this->digest);
        $method = $reflection->getMethod('aggregateEntries');

        $result = $method->invoke($this->digest, $entries, 'min');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(100, $result[0]['value']);
        $this->assertEquals(3, $result[0]['entry_count']);
        $this->assertEquals('min', $result[0]['aggregate']);
    }

    /**
     * Test aggregateEntries with max aggregation
     */
    public function testAggregateEntriesMax(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 300,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
        ];

        $reflection = new ReflectionClass($this->digest);
        $method = $reflection->getMethod('aggregateEntries');

        $result = $method->invoke($this->digest, $entries, 'max');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(300, $result[0]['value']);
        $this->assertEquals(3, $result[0]['entry_count']);
        $this->assertEquals('max', $result[0]['aggregate']);
    }

    /**
     * Test aggregateEntries with avg aggregation
     */
    public function testAggregateEntriesAvg(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 200,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 300,
            ],
        ];

        $reflection = new ReflectionClass($this->digest);
        $method = $reflection->getMethod('aggregateEntries');

        $result = $method->invoke($this->digest, $entries, 'avg');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(200, $result[0]['value']);
        $this->assertEquals(3, $result[0]['entry_count']);
        $this->assertEquals('avg', $result[0]['aggregate']);
    }

    /**
     * Test buildAggregateKey method
     */
    public function testBuildAggregateKey(): void
    {
        $entry = [
            'bucket' => 1609459200,
            'period' => 3600,
            'type' => 'request',
            'key_hash' => md5('user:123'),
        ];

        $reflection = new ReflectionClass($this->digest);
        $method = $reflection->getMethod('buildAggregateKey');

        $result = $method->invoke($this->digest, $entry, 'count');

        $expected = '1609459200:3600:request:count:' . md5('user:123');
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getInitialValue method
     */
    public function testGetInitialValue(): void
    {
        $entry = ['value' => 100];

        $reflection = new ReflectionClass($this->digest);
        $method = $reflection->getMethod('getInitialValue');

        $this->assertEquals(1, $method->invoke($this->digest, $entry, 'count'));
        $this->assertEquals(100, $method->invoke($this->digest, $entry, 'min'));
        $this->assertEquals(100, $method->invoke($this->digest, $entry, 'max'));
        $this->assertEquals(100, $method->invoke($this->digest, $entry, 'sum'));
        $this->assertEquals(100, $method->invoke($this->digest, $entry, 'avg'));
        $this->assertEquals(0, $method->invoke($this->digest, $entry, 'unknown'));
    }

    /**
     * Test calculateAggregateValue method
     */
    public function testCalculateAggregateValue(): void
    {
        $aggregate = ['value' => 100, 'entry_count' => 2];
        $entry = ['value' => 50];

        $reflection = new ReflectionClass($this->digest);
        $method = $reflection->getMethod('calculateAggregateValue');

        $this->assertEquals(101, $method->invoke($this->digest, $aggregate, $entry, 'count'));
        $this->assertEquals(50, $method->invoke($this->digest, $aggregate, $entry, 'min'));
        $this->assertEquals(100, $method->invoke($this->digest, $aggregate, $entry, 'max'));
        $this->assertEquals(150, $method->invoke($this->digest, $aggregate, $entry, 'sum'));
        $this->assertEquals(83.33333333333333, $method->invoke($this->digest, $aggregate, $entry, 'avg'));
        $this->assertEquals(100, $method->invoke($this->digest, $aggregate, $entry, 'unknown'));
    }

    /**
     * Test large dataset chunking
     */
    public function testLargeDatasetChunking(): void
    {
        $entries = [];
        for ($i = 0; $i < 250; $i++) {
            $entries[] = [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:' . ($i % 10),
                'key_hash' => md5('user:' . ($i % 10)),
                'value' => $i,
            ];
        }

        $result = $this->digest->processAggregations(['count' => $entries]);

        $this->assertEquals(10, $result, 'Large dataset should affect 10 rows (one per unique key)');
    }

    /**
     * Test edge case: zero values
     */
    public function testZeroValues(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 0,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 0,
            ],
        ];

        $result = $this->digest->processAggregations([
            'count' => $entries,
            'sum' => $entries,
            'min' => $entries,
            'max' => $entries,
            'avg' => $entries,
        ]);

        $this->assertEquals(5, $result, 'Zero values should affect 5 rows (one per aggregation type)');
    }

    /**
     * Test edge case: negative values
     */
    public function testNegativeValues(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => -100,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => -50,
            ],
        ];

        $result = $this->digest->processAggregations([
            'sum' => $entries,
            'min' => $entries,
            'max' => $entries,
            'avg' => $entries,
        ]);

        $this->assertEquals(4, $result, 'Negative values should affect 4 rows (one per aggregation type)');
    }

    /**
     * Test edge case: very large values
     */
    public function testLargeValues(): void
    {
        $entries = [
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 999999999,
            ],
            [
                'bucket' => 1609459200,
                'period' => 3600,
                'type' => 'request',
                'metric_key' => 'user:123',
                'key_hash' => md5('user:123'),
                'value' => 888888888,
            ],
        ];

        $result = $this->digest->processAggregations([
            'sum' => $entries,
            'min' => $entries,
            'max' => $entries,
            'avg' => $entries,
        ]);

        $this->assertEquals(4, $result, 'Large values should affect 4 rows (one per aggregation type)');
    }

    /**
     * Test getter methods
     */
    public function testGetters(): void
    {
        $this->assertEquals('rhythm_aggregates', $this->digest->getTable());
        $this->assertEquals(100, $this->digest->getChunkSize());
    }
}

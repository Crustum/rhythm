<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase;

use Cake\Collection\Collection;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Crustum\Rhythm\RhythmEntry;
use Crustum\Rhythm\RhythmValue;

/**
 * Rhythm Integration Test Case
 *
 * Simple tests to verify basic object creation and data flow
 */
class RhythmIntegrationTest extends TestCase
{
    /**
     * Test creating and working with mixed rhythm data
     *
     * @return void
     */
    public function testMixedRhythmData(): void
    {
        $timestamp = (new DateTime())->getTimestamp();

        $entry = new RhythmEntry($timestamp, 'request', 'user:123', 100);
        $value = new RhythmValue($timestamp, 'user', 'active:123', 'John Doe');

        $collection = new Collection([$entry, $value]);

        $this->assertEquals(2, $collection->count());

        $entries = $collection->filter(function ($item) {
            return $item instanceof RhythmEntry;
        });

        $values = $collection->filter(function ($item) {
            return $item instanceof RhythmValue;
        });

        $this->assertEquals(1, $entries->count());
        $this->assertEquals(1, $values->count());
    }

    /**
     * Test converting objects to arrays for storage
     *
     * @return void
     */
    public function testConvertToArrays(): void
    {
        $timestamp = (new DateTime())->getTimestamp();

        $entry = new RhythmEntry($timestamp, 'request', 'test', 200);
        $value = new RhythmValue($timestamp, 'cache', 'status', 'connected');

        $entryArray = $entry->attributes();
        $valueArray = $value->attributes();

        $this->assertArrayHasKey('timestamp', $valueArray);

        $this->assertArrayHasKey('timestamp', $entryArray);
        $this->assertArrayHasKey('type', $entryArray);
        $this->assertArrayHasKey('key', $entryArray);
        $this->assertArrayHasKey('value', $entryArray);

        $this->assertEquals($timestamp, $entryArray['timestamp']);
        $this->assertEquals('request', $entryArray['type']);
        $this->assertEquals('test', $entryArray['key']);
        $this->assertEquals(200, $entryArray['value']);
    }

    /**
     * Test bulk creation for ingest layer
     *
     * @return void
     */
    public function testBulkCreation(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $entries = [];

        for ($i = 1; $i <= 100; $i++) {
            $entries[] = new RhythmEntry($timestamp, 'bulk_test', "item:{$i}", $i * 10);
        }

        $collection = new Collection($entries);

        $this->assertEquals(100, $collection->count());

        $total = $collection->sumOf(fn($item) => $item->value);
        $this->assertEquals(50500, $total);
    }
}

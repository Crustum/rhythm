<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase;

use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Rhythm\RhythmEntry;

/**
 * RhythmEntry Test Case
 */
class RhythmEntryTest extends TestCase
{
    /**
     * Test basic construction and properties
     *
     * @return void
     */
    public function testBasicConstruction(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $entry = new RhythmEntry($timestamp, 'request', 'user:123', 50);

        $this->assertEquals($timestamp, $entry->timestamp);
        $this->assertEquals('request', $entry->type);
        $this->assertEquals('user:123', $entry->key);
        $this->assertEquals(50, $entry->value);
    }

    /**
     * Test construction with null value
     *
     * @return void
     */
    public function testConstructionWithNullValue(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $entry = new RhythmEntry($timestamp, 'exception', 'RuntimeException');

        $this->assertEquals($timestamp, $entry->timestamp);
        $this->assertEquals('exception', $entry->type);
        $this->assertEquals('RuntimeException', $entry->key);
        $this->assertNull($entry->value);
    }

    /**
     * Test attributes method
     *
     * @return void
     */
    public function testAttributes(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $entry = new RhythmEntry($timestamp, 'query', 'slow_query', 1200);

        $attributes = $entry->attributes();

        $expected = [
            'timestamp' => $timestamp,
            'type' => 'query',
            'key' => 'slow_query',
            'value' => 1200,
        ];

        $this->assertEquals($expected, $attributes);
    }
}

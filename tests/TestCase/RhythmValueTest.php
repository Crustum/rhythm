<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase;

use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Crustum\Rhythm\RhythmValue;

/**
 * RhythmValue Test Case
 */
class RhythmValueTest extends TestCase
{
    /**
     * Test basic construction and properties
     *
     * @return void
     */
    public function testBasicConstruction(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $value = new RhythmValue($timestamp, 'user', 'active_user:123', 'John Doe');

        $this->assertEquals($timestamp, $value->timestamp);
        $this->assertEquals('user', $value->type);
        $this->assertEquals('active_user:123', $value->key);
        $this->assertEquals('John Doe', $value->value);
    }

    /**
     * Test attributes method
     *
     * @return void
     */
    public function testAttributes(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $value = new RhythmValue($timestamp, 'cache', 'redis_status', 'connected');

        $attributes = $value->attributes();

        $expected = [
            'timestamp' => $timestamp,
            'type' => 'cache',
            'key' => 'redis_status',
            'value' => 'connected',
        ];

        $this->assertEquals($expected, $attributes);
    }
}

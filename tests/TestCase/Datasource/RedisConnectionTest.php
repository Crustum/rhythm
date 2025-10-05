<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Datasource;

use Cake\TestSuite\TestCase;
use Redis;
use Rhythm\Datasource\RedisConnection;

/**
 * RedisConnection Test Case
 */
class RedisConnectionTest extends TestCase
{
    /**
     * Test create method with default configuration.
     *
     * @return void
     */
    public function testCreateWithDefaultConfig(): void
    {
        $redis = RedisConnection::create();

        if (extension_loaded('redis')) {
            $this->assertInstanceOf(Redis::class, $redis);
        } else {
            $this->assertNull($redis);
        }
    }

    /**
     * Test create method with custom configuration.
     *
     * @return void
     */
    public function testCreateWithCustomConfig(): void
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
        ];

        $redis = RedisConnection::create($config);

        if (extension_loaded('redis')) {
            $this->assertInstanceOf(Redis::class, $redis);
        } else {
            $this->assertNull($redis);
        }
    }
}

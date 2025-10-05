<?php
declare(strict_types=1);

namespace Rhythm\Datasource;

use Redis;

/**
 * Redis Connection Utility Class
 *
 * Provides centralized Redis connection creation logic to eliminate code duplication
 * across Rhythm components. Simple utility class with static methods.
 */
class RedisConnection
{
    /**
     * Create a new Redis connection.
     *
     * @param array<string, mixed> $config Configuration array
     * @return \Redis|null Redis instance or null if connection fails
     */
    public static function create(array $config = []): ?Redis
    {
        if (!extension_loaded('redis')) {
            return null;
        }

        $redis = new Redis();
        $redis->connect(
            $config['host'] ?? getenv('REDIS_HOST') ?: '127.0.0.1',
            (int)($config['port'] ?? getenv('REDIS_PORT') ?: 6379),
        );

        if (!empty($config['password'])) {
            $redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $redis->select($config['database']);
        }

        return $redis;
    }
}

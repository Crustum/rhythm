<?php
/**
 * Test Application Configuration
 */

return [
    'debug' => true,
    'App' => [
        'namespace' => 'TestApp',
        'encoding' => 'UTF-8',
        'defaultLocale' => 'en_US',
        'defaultTimezone' => 'UTC',
        'base' => false,
        'dir' => 'src',
        'webroot' => 'webroot',
        'wwwRoot' => WWW_ROOT,
        'fullBaseUrl' => false,
        'imageBaseUrl' => 'img/',
        'jsBaseUrl' => 'js/',
        'cssBaseUrl' => 'css/',
        'paths' => [
            'plugins' => [ROOT . DS . 'plugins' . DS],
            'templates' => [ROOT . DS . 'templates' . DS],
            'locales' => [ROOT . DS . 'resources' . DS . 'locales' . DS],
        ],
    ],
    'Security' => [
        'salt' => 'test-salt-for-testing-only-change-in-production',
    ],
    'Asset' => [
        'cacheTime' => '+1 year',
    ],
    'Rhythm' => [
        'enabled' => true,
        'storage' => [
            'driver' => 'database',
            'database' => [
                'connection' => 'test',
                'tables' => [
                    'entries' => 'rhythm_entries',
                    'aggregates' => 'rhythm_aggregates',
                ],
            ],
        ],
        'recorders' => [
            'user_requests' => [
                'className' => 'Rhythm\Recorder\UserRequestsRecorder',
                'enabled' => true,
                'sample_rate' => 1.0,
            ],
            'slow_queries' => [
                'className' => 'Rhythm\Recorder\SlowQueriesRecorder',
                'enabled' => true,
                'threshold' => 100,
                'sample_rate' => 0.1,
            ],
            'exceptions' => [
                'className' => 'Rhythm\Recorder\ExceptionsRecorder',
                'enabled' => true,
                'sample_rate' => 1.0,
            ],
        ],
        'ingest' => [
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'queue_key' => 'rhythm:test:queue',
                'processing_key' => 'rhythm:test:processing',
            ],
        ],
    ],
];

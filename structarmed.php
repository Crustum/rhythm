<?php
declare(strict_types=1);

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    ->layerPattern('Foundation', [
        '/^Crustum\\\\Rhythm\\\\(Utility|Event)(\\\\.*)?$/',
        '/^Crustum\\\\Rhythm\\\\(RhythmEntry|RhythmValue)$/',
    ])
    ->layerPattern('Datasource', '/^Crustum\\\\Rhythm\\\\Datasource\\\\.*$/')
    ->layerPattern('Database', '/^Crustum\\\\Rhythm\\\\Database\\\\.*$/')
    ->layerPattern('Model', '/^Crustum\\\\Rhythm\\\\Model\\\\.*$/')
    ->layerPattern('Storage', '/^Crustum\\\\Rhythm\\\\Storage(\\\\.*)?$/')
    ->layerPattern('Ingest', '/^Crustum\\\\Rhythm\\\\Ingest\\\\.*$/')
    ->layerPattern('Recorder', '/^Crustum\\\\Rhythm\\\\Recorder\\\\.*$/')
    ->layerPattern('Queue', '/^Crustum\\\\Rhythm\\\\Queue\\\\.*$/')
    ->layerPattern('Middleware', '/^Crustum\\\\Rhythm\\\\Middleware\\\\.*$/')
    ->layerPattern('Widget', '/^Crustum\\\\Rhythm\\\\Widget\\\\.*$/')
    ->layerPattern('View', '/^Crustum\\\\Rhythm\\\\View\\\\.*$/')
    ->layerPattern('Controller', '/^Crustum\\\\Rhythm\\\\Controller\\\\.*$/')
    ->layerPattern('Command', '/^Crustum\\\\Rhythm\\\\Command\\\\.*$/')
    ->layerPattern('Plugin', '/^Crustum\\\\Rhythm\\\\RhythmPlugin$/')
    ->ruleset([
        'Foundation' => [],
        'Datasource' => ['Foundation'],
        'Database' => ['Foundation'],
        'Model' => [],
        'Storage' => ['Model', 'Foundation'],
        'Ingest' => ['Storage', 'Datasource', 'Foundation'],
        'Recorder' => ['Datasource', 'Foundation'],
        'Queue' => ['Foundation'],
        'Middleware' => ['Foundation'],
        'Widget' => ['Foundation'],
        'View' => ['Widget'],
        'Controller' => ['Widget', 'Foundation'],
        'Command' => ['Foundation'],
        'Plugin' => ['+Ingest', '+Recorder', '+Command', 'Middleware', 'Controller', 'View', 'Queue', 'Database'],
    ]);

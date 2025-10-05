<?php
declare(strict_types=1);

/**
 * Rhythm Plugin Routes
 *
 * Routes for Rhythm performance monitoring plugin.
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    // $routes->scope('/rhythm', function (RouteBuilder $builder) {
    //     // Dashboard routes
    //     $builder->connect('/dashboard', ['controller' => 'Dashboard', 'action' => 'index']);
    //     $builder->connect('/dashboard/refresh', ['controller' => 'Dashboard', 'action' => 'refresh']);
    //     $builder->connect('/dashboard/widget/*', ['controller' => 'Dashboard', 'action' => 'widget']);

    //     // Fallback
    //     $builder->fallbacks();
    // });

    $routes->plugin('Rhythm', ['path' => '/rhythm'], function (RouteBuilder $routes): void {
        // Dashboard routes
        $routes->connect('/dashboard', ['controller' => 'Dashboard', 'action' => 'index']);
        $routes->connect('/dashboard/refresh', ['controller' => 'Dashboard', 'action' => 'refresh']);
        $routes->connect('/dashboard/widget/*', ['controller' => 'Dashboard', 'action' => 'widget']);

        // New System routes
        // $routes->connect('/new-system', ['controller' => 'NewSystem', 'action' => 'index']);
        // $routes->connect('/new-system/refresh', ['controller' => 'NewSystem', 'action' => 'refresh']);
        // $routes->connect('/new-system/widget/*', ['controller' => 'NewSystem', 'action' => 'widget']);


        // $routes->connect('/widgets/server-state', ['controller' => 'Widgets', 'action' => 'serverState']);
        // $routes->connect('/widgets/demo', ['controller' => 'Widgets', 'action' => 'demo']);

        // API routes for metrics
        // $routes->connect('/api/metrics/:type', ['controller' => 'Api', 'action' => 'metrics'], ['pass' => ['type']]);
        // $routes->connect('/api/aggregates/:type/:aggregate', ['controller' => 'Api', 'action' => 'aggregates'], ['pass' => ['type', 'aggregate']]);

        // // Dashboard routes
        // $routes->connect('/dashboard', ['controller' => 'Dashboard', 'action' => 'index']);
        // $routes->connect('/dashboard/:action', ['controller' => 'Dashboard']);

        // // Health check routes
        // $routes->connect('/health', ['controller' => 'Health', 'action' => 'index']);
        // $routes->connect('/health/:action', ['controller' => 'Health']);

        $routes->fallbacks();
    });
};

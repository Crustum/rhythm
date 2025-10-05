<?php
declare(strict_types=1);

/**
 * Rhythm Plugin bootstrap file.
 *
 * This file loads the Rhythm plugin configuration.
 */

use Cake\Cache\Cache;
use Cake\Cache\Engine\FileEngine;
use Cake\Core\Configure;
$config = Configure::read('Rhythm.config') ?? [];
Configure::load('Rhythm.rhythm', 'default', false);
collection($config)->each(function ($merge, $file): void {
	if (is_int($file)) {
		$file = $merge;
		$merge = true;
	}
    Configure::load($file, 'default', $merge);
});

if (!Cache::getConfig('Cache.rhythm') && !Configure::check('Cache.rhythm')) {
    Configure::write('Cache.rhythm', [
        'className' => FileEngine::class,
        'prefix' => '',
        'path' => CACHE . 'rhythm' . DS,
        'serialize' => true,
        'duration' => '5 seconds',
    ]);
    Cache::setConfig('rhythm', Configure::consume('Cache.rhythm'));
}

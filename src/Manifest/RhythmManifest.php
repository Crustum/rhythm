<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Manifest;

use Crustum\PluginManifest\Manifest\ManifestOps;
use Crustum\Rhythm\RhythmPlugin;

/**
 * Standalone manifest provider for the Rhythm plugin.
 *
 * Plain class without interface or trait dependencies, so the plugin loads
 * and runs without `crustum/plugin-manifest` installed. Discovered by the
 * installer through the provider convention when the plugin class itself
 * provides no manifest. All package references stay inside method bodies,
 * which only execute when the installer (and therefore the package) exists.
 */
final class RhythmManifest
{
    /**
     * Plugin install assets via crustum/plugin-manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__, 2);
        $plugin = ManifestOps::pluginName(RhythmPlugin::class);

        return array_merge(
            ManifestOps::manifestMigrations(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Migrations',
            ),
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rhythm.php.default',
                CONFIG . 'rhythm.php',
                false,
            ),
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rhythm_layouts.php.default',
                CONFIG . 'rhythm_layouts.php',
                false,
            ),
            ManifestOps::manifestBootstrapAppend(
                $plugin,
                "Configure::write('Rhythm.config', ['rhythm', 'rhythm_layouts' => false]);",
                '// Rhythm Plugin Configuration',
            ),
            ManifestOps::manifestStarRepo($plugin, 'Crustum/rhythm'),
        );
    }
}

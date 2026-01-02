<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Exception;

/**
 * App Info Widget
 *
 * Displays application information including CakePHP version, PHP info,
 * debug mode, and other application details.
 */
class AppInfoWidget extends BaseWidget
{
    /**
     * Get widget data
     *
     * @param array<string, mixed> $options Widget options
     * @return array<string, mixed>
     */
    public function getData(array $options = []): array
    {
        return $this->remember(function () {
            try {
                return [
                    'environment' => $this->getEnvironmentInfo(),
                    'application' => $this->getApplicationInfo(),
                    'system' => $this->getSystemInfo(),
                ];
            } catch (Exception $e) {
                return [
                    'environment' => [],
                    'application' => [],
                    'system' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }, 'app_info_widget', $this->getRefreshInterval());
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Crustum/Rhythm.widgets/app_info';
    }

    /**
     * Get refresh interval in seconds
     *
     * @return int
     */
    public function getRefreshInterval(): int
    {
        return $this->getConfigValue('refreshInterval', 300);
    }

    /**
     * Get default icon for this widget
     *
     * @return string|null
     */
    protected function getDefaultIcon(): ?string
    {
        return 'fas fa-info-circle';
    }

    /**
     * Get environment information
     *
     * @return array<string, mixed>
     */
    protected function getEnvironmentInfo(): array
    {
        return [
            'Debug Mode' => Configure::read('debug') ? 'Enabled' : 'Disabled',
            'Timezone' => Configure::read('App.defaultTimezone') ?: date_default_timezone_get(),
            'Locale' => Configure::read('App.defaultLocale') ?: 'en_US',
            'URL' => $this->getApplicationUrl(),
        ];
    }

    /**
     * Get application information
     *
     * @return array<string, mixed>
     */
    protected function getApplicationInfo(): array
    {
        return [
            'Application Name' => Configure::read('App.name') ?: 'CakePHP Application',
            'CakePHP Version' => $this->getCakePhpVersion(),
            'PHP Version' => PHP_VERSION,
            'Composer Version' => $this->getComposerVersion(),
            'Application Path' => $this->getApplicationPath(),
        ];
    }

    /**
     * Get system information
     *
     * @return array<string, mixed>
     */
    protected function getSystemInfo(): array
    {
        return [
            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . 's',
            'Upload Max Filesize' => ini_get('upload_max_filesize'),
            'Post Max Size' => ini_get('post_max_size'),
            'Database Type' => $this->getDatabaseType(),
        ];
    }

    /**
     * Get CakePHP version
     *
     * @return string
     */
    protected function getCakePhpVersion(): string
    {
        $version = Configure::version();
        if ($version) {
            return $version;
        }

        $lockFile = ROOT . DS . 'composer.lock';
        if (file_exists($lockFile)) {
            $lockContent = file_get_contents($lockFile);
            if ($lockContent !== false) {
                $lockData = json_decode($lockContent, true);
                if (isset($lockData['packages'])) {
                    foreach ($lockData['packages'] as $package) {
                        if ($package['name'] === 'cakephp/cakephp') {
                            return $package['version'];
                        }
                    }
                }
            }
        }

        $composerFile = ROOT . DS . 'composer.json';
        if (file_exists($composerFile)) {
            $composerContent = file_get_contents($composerFile);
            if ($composerContent !== false) {
                $composerData = json_decode($composerContent, true);
                if (isset($composerData['require']['cakephp/cakephp'])) {
                    return $composerData['require']['cakephp/cakephp'];
                }
            }
        }

        return 'Unknown';
    }

    /**
     * Get Composer version
     *
     * @return string
     */
    protected function getComposerVersion(): string
    {
        $output = shell_exec('composer --version 2>&1');
        if ($output && preg_match('/Composer version (\S+)/', $output, $matches)) {
            return $matches[1];
        }

        return 'Unknown';
    }

    /**
     * Get application URL
     *
     * @return string
     */
    protected function getApplicationUrl(): string
    {
        $url = Configure::read('App.fullBaseUrl');
        if ($url) {
            return $url;
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . '://' . $host;
    }

    /**
     * Get application path
     *
     * @return string
     */
    protected function getApplicationPath(): string
    {
        return ROOT;
    }

    /**
     * Get database type
     *
     * @return string
     */
    protected function getDatabaseType(): string
    {
        try {
            $defaultConnection = ConnectionManager::get('default');
            $config = $defaultConnection->config();

            if (isset($config['driver'])) {
                $driver = $config['driver'];

                return str_replace('Cake\Database\Driver\\', '', $driver);
            }

            $defaultConnection = Configure::read('Datasources.default');
            if ($defaultConnection && isset($defaultConnection['driver'])) {
                $driver = $defaultConnection['driver'];

                return str_replace('Cake\Database\Driver\\', '', $driver);
            }
        } catch (Exception) {
            $defaultConnection = Configure::read('Datasources.default');
            if ($defaultConnection && isset($defaultConnection['driver'])) {
                $driver = $defaultConnection['driver'];

                return str_replace('Cake\Database\Driver\\', '', $driver);
            }
        }

        return 'Unknown';
    }
}

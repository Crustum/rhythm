<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\Utility\Hash;
use Crustum\Rhythm\Rhythm;
use InvalidArgumentException;

/**
 * Base Widget Class
 *
 * Abstract base class for all dashboard widgets.
 */
abstract class BaseWidget
{
    /**
     * Rhythm instance
     *
     * @var \Crustum\Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Widget configuration
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Constructor
     *
     * @param \Crustum\Rhythm\Rhythm $rhythm Rhythm instance
     * @param array<string, mixed> $config Widget configuration
     */
    public function __construct(Rhythm $rhythm, array $config = [])
    {
        $this->rhythm = $rhythm;
        $this->config = $config;
    }

    /**
     * Get widget data
     *
     * @param array<string, mixed> $options Widget options (period, sort, etc.)
     * @return array<string, mixed>
     */
    abstract public function getData(array $options = []): array;

    /**
     * Get template name
     *
     * @return string
     */
    abstract public function getTemplate(): string;

    /**
     * Get refresh interval in seconds
     *
     * @return int
     */
    abstract public function getRefreshInterval(): int;

    /**
     * Get CSS files for this widget
     *
     * @return array<string>
     */
    public static function getCss(): array
    {
        return [];
    }

    /**
     * Get JavaScript files for this widget
     *
     * @return array<string>
     */
    public static function getJs(): array
    {
        return [];
    }

    /**
     * Get widget icon
     *
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->getConfigValue('icon', $this->getDefaultIcon());
    }

    /**
     * Get default icon for this widget
     *
     * @return string|null
     */
    protected function getDefaultIcon(): ?string
    {
        return null;
    }

    /**
     * Check if widget supports sorting
     *
     * @return bool True if widget has sort options
     */
    public function isSortable(): bool
    {
        return false;
    }

    /**
     * Get sort configuration for templates
     *
     * @return array<string, mixed> Sort configuration array
     */
    public function getSortConfig(): array
    {
        return [];
    }

    /**
     * Get widget configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Update widget configuration
     *
     * @param array<string, mixed> $config Widget configuration
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get recorder setting value
     *
     * @param string $setting Setting key to retrieve
     * @return mixed Setting value or null if not found
     */
    protected function getRecorderSetting(string $setting): mixed
    {
        $recorderName = $this->getRecorderName();

        if (!$recorderName) {
            throw new InvalidArgumentException('Recorder name not configured for this widget');
        }

        return $this->getRecorderSettingForRecorder($recorderName, $setting);
    }

    /**
     * Get recorder setting value for a specific recorder
     *
     * @param string $recorderName Recorder name
     * @param string $setting Setting key to retrieve
     * @return mixed Setting value or null if not found
     */
    protected function getRecorderSettingForRecorder(string $recorderName, string $setting): mixed
    {
        $rhythmConfig = Configure::read('Rhythm.recorders', []);

        $recorderConfig = $rhythmConfig[$recorderName] ?? null;

        if (!$recorderConfig) {
            throw new InvalidArgumentException("Recorder configuration not found for '{$recorderName}'");
        }

        return Hash::get($recorderConfig, $setting);
    }

    /**
     * Get recorder name
     *
     * @return string
     */
    protected function getRecorderName(): ?string
    {
        return null;
    }

    /**
     * Remember the query for the current period.
     *
     * @param callable $query The query to cache.
     * @param string $key The cache key.
     * @param int|null $ttl The time-to-live in seconds.
     * @return mixed
     */
    protected function remember(callable $query, string $key = '', ?int $ttl = 300): mixed
    {
        $cacheKey = 'rhythm-widget-' . str_replace('\\', '_', static::class) . '-' . $key;
        $timeKey = $cacheKey . '-time';

        $cached = Cache::read($cacheKey, 'rhythm');
        $cachedTime = Cache::read($timeKey, 'rhythm');

        if ($cached !== null && $cachedTime !== null) {
            $cachedTimestamp = new DateTime($cachedTime);
            $currentTime = new DateTime();
            $expiryTime = $cachedTimestamp->modify("+{$ttl} seconds");

            if ($currentTime < $expiryTime) {
                return $cached;
            }
        }

        $result = $query();
        $currentTimestamp = (new DateTime())->format('Y-m-d H:i:s');

        Cache::write($cacheKey, $result, 'rhythm');
        Cache::write($timeKey, $currentTimestamp, 'rhythm');

        return $result;
    }
}

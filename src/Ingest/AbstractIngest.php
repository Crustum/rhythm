<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Ingest;

use Cake\Collection\CollectionInterface;
use Cake\I18n\DateTime;
use Crustum\Rhythm\RhythmEntry;
use Crustum\Rhythm\RhythmValue;
use Crustum\Rhythm\Storage\StorageInterface;

/**
 * Abstract Ingest Base Class
 *
 * Provides common functionality for ingest implementations.
 * Eliminates code duplication between Redis and Database ingest classes.
 */
abstract class AbstractIngest implements IngestInterface
{
    /**
     * Configuration.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Storage interface.
     *
     * @var \Crustum\Rhythm\Storage\StorageInterface
     */
    protected StorageInterface $storage;

    /**
     * Constructor.
     *
     * @param \Crustum\Rhythm\Storage\StorageInterface $storage Storage interface
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(StorageInterface $storage, array $config = [])
    {
        $this->storage = $storage;
        $this->config = $this->getDefaultConfig($config);
        $this->initialize();
    }

    /**
     * Get default configuration with fallbacks.
     *
     * @param array<string, mixed> $config User configuration
     * @return array<string, mixed> Merged configuration
     */
    protected function getDefaultConfig(array $config): array
    {
        $defaults = [
            'trim' => [
                'keep' => '1 hour',
            ],
        ];

        return array_merge($defaults, $config);
    }

    /**
     * Initialize the ingest implementation.
     *
     * @return void
     */
    abstract protected function initialize(): void;

    /**
     * Ingest the items into persistent storage.
     *
     * @param \Cake\Collection\CollectionInterface $items Collection of metric entries
     * @return void
     */
    public function ingest(CollectionInterface $items): void
    {
        if ($items->isEmpty()) {
            return;
        }
        $this->storeItems($items);
    }

    /**
     * Digest ingested items to final storage.
     *
     * @return int Number of items digested
     */
    public function digest(): int
    {
        return $this->processDigestItems();
    }

    /**
     * Trim old data from ingest storage.
     *
     * @return void
     */
    public function trim(): void
    {
        $keep = $this->config['trim']['keep'] ?? '1 hour';
        $cutoff = $this->calculateCutoffTime($keep);

        $this->removeOldData($cutoff);
    }

    /**
     * Store items in the ingest storage.
     *
     * @param \Cake\Collection\CollectionInterface $items Collection of metric entries
     * @return void
     */
    abstract protected function storeItems(CollectionInterface $items): void;

    /**
     * Process digest items from ingest storage to final storage.
     *
     * @return int Number of items processed
     */
    abstract protected function processDigestItems(): int;

    /**
     * Remove old data from ingest storage.
     *
     * @param int $cutoff Cutoff timestamp
     * @return void
     */
    abstract protected function removeOldData(int $cutoff): void;

    /**
     * Convert metric items to collection format for storage.
     *
     * @param \Cake\Collection\CollectionInterface $items Collection of metric entries
     * @return \Cake\Collection\CollectionInterface Collection of metric data
     */
    protected function convertItemsToArray(CollectionInterface $items): CollectionInterface
    {
        return $items->map(function ($item) {
            return [
                'data' => serialize($item),
                'created' => date('Y-m-d H:i:s'),
            ];
        })->filter();
    }

    /**
     * Create metric entity from array data.
     *
     * @param array<string, mixed> $data Metric data
     * @return \Crustum\Rhythm\RhythmEntry|\Crustum\Rhythm\RhythmValue
     */
    protected function createMetricEntity(array $data): RhythmEntry|RhythmValue
    {
        return unserialize($data['data']);
    }

    /**
     * Calculate cutoff time based on keep duration.
     *
     * @param string $keep Duration string (e.g., '1 hour', '30 minutes')
     * @return int Cutoff timestamp
     */
    protected function calculateCutoffTime(string $keep): int
    {
        return (int)DateTime::now()->modify("-{$keep}")->getTimestamp();
    }

    /**
     * Get configuration value with fallback.
     *
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}

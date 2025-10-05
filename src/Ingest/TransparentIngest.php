<?php
declare(strict_types=1);

namespace Rhythm\Ingest;

use Cake\Collection\CollectionInterface;

/**
 * Transparent Ingest Implementation
 *
 * A transparent ingest implementation that stores the items in the storage.
 * Useful for testing or when you want to use the storage directly.
 *
 * @internal
 * @todo
 */
class TransparentIngest extends AbstractIngest
{
    /**
     * Initialize the ingest.
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * Ingest the items.
     *
     * @param \Cake\Collection\CollectionInterface $items
     */
    public function ingest(CollectionInterface $items): void
    {
        $this->storage->store($items);
    }

    /**
     * Trim the ingest.
     */
    public function trim(): void
    {
    }

    /**
     * Digest the ingested items.
     */
    protected function processDigestItems(): int
    {
        return 0;
    }

    /**
     * Store items in the ingest storage.
     *
     * @param \Cake\Collection\CollectionInterface $items Collection of metric entries
     * @return void
     */
    protected function storeItems(CollectionInterface $items): void
    {
        $this->storage->store($items);
    }

    /**
     * Remove old data from ingest storage.
     *
     * @param int $cutoff Cutoff timestamp
     * @return void
     */
    protected function removeOldData(int $cutoff): void
    {
    }

    /**
     * Clear the ingest storage.
     *
     * @return void
     */
    public function clear(): void
    {
    }

    /**
     * Get ingest statistics.
     *
     * @return array<string, mixed> Statistics array
     */
    public function getStats(): array
    {
        return [];
    }
}

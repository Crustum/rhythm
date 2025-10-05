<?php
declare(strict_types=1);

namespace Rhythm\Ingest;

use Cake\Collection\CollectionInterface;

/**
 * Null Ingest Implementation
 *
 * A null object implementation that discards all metrics.
 * Useful for testing or when you want to disable metric collection
 * without changing configuration structure.
 */
class NullIngest implements IngestInterface
{
    /**
     * Discard the items (do nothing).
     *
     * @param \Cake\Collection\CollectionInterface $items Collection of metric entries
     * @return void
     */
    public function ingest(CollectionInterface $items): void
    {
    }

    /**
     * No digest processing needed for null implementation.
     *
     * @return int Always returns 0 as no items are processed
     */
    public function digest(): int
    {
        return 0;
    }

    /**
     * No trimming needed for null implementation.
     *
     * @return void
     */
    public function trim(): void
    {
    }
}

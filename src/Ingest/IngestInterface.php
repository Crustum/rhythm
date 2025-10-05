<?php
declare(strict_types=1);

namespace Rhythm\Ingest;

use Cake\Collection\CollectionInterface;

/**
 * Ingest Interface
 *
 * Contract for ingesting metrics into persistent storage.
 */
interface IngestInterface
{
    /**
     * Ingest the items into persistent storage.
     *
     * @param \Cake\Collection\CollectionInterface $items Collection of metric entries
     * @return void
     */
    public function ingest(CollectionInterface $items): void;

    /**
     * Digest the ingested items into final storage.
     *
     * @return int Number of items processed
     */
    public function digest(): int;

    /**
     * Trim old data from ingest storage.
     *
     * @return void
     */
    public function trim(): void;
}

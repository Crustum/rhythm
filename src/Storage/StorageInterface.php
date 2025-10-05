<?php
declare(strict_types=1);

namespace Rhythm\Storage;

use Cake\Collection\CollectionInterface;

/**
 * Storage Interface
 *
 * Contract for metric storage implementations.
 */
interface StorageInterface
{
    /**
     * Store metric entries.
     *
     * @param \Cake\Collection\CollectionInterface $entries Collection of metric entries
     * @return void
     */
    public function store(CollectionInterface $entries): void;

    /**
     * Retrieve aggregate values for the given type.
     *
     * @param string $type The metric type
     * @param array<string>|string $aggregates List of aggregates ('count', 'min', 'max', 'sum', 'avg')
     * @param int $intervalMinutes Interval in minutes
     * @param string|null $orderBy Order by column
     * @param string $direction Order direction
     * @param int $limit Result limit
     * @return \Cake\Collection\CollectionInterface Collection of aggregate results
     */
    public function aggregate(
        string $type,
        array|string $aggregates,
        int $intervalMinutes,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): CollectionInterface;

    /**
     * Retrieve aggregate values for multiple types.
     *
     * @param array<string>|string $types The metric types
     * @param string $aggregate Single aggregate type
     * @param int $intervalMinutes Interval in minutes
     * @param string|null $orderBy Order by column
     * @param string $direction Order direction
     * @param int $limit Result limit
     * @return \Cake\Collection\CollectionInterface Collection of aggregate results
     */
    public function aggregateTypes(
        string|array $types,
        string $aggregate,
        int $intervalMinutes,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): CollectionInterface;

    /**
     * Retrieve aggregate total for given types.
     *
     * @param array<string>|string $types The metric types
     * @param string $aggregate Aggregate type
     * @param int $intervalMinutes Interval in minutes
     * @return \Cake\Collection\CollectionInterface|float Total value or collection by type
     */
    public function aggregateTotal(
        array|string $types,
        string $aggregate,
        int $intervalMinutes,
    ): float|CollectionInterface;

    /**
     * Retrieve aggregate values for plotting on a graph.
     *
     * @param array<string> $types List of metric types
     * @param string $aggregate Aggregate type ('count', 'min', 'max', 'sum', 'avg')
     * @param int $intervalMinutes Interval in minutes
     * @return \Cake\Collection\CollectionInterface
     */
    public function graph(array $types, string $aggregate, int $intervalMinutes): CollectionInterface;

    /**
     * Retrieve values for the given type.
     *
     * @param string $type The metric type
     * @param array<string>|null $keys Optional list of keys to filter by
     * @return \Cake\Collection\CollectionInterface
     */
    public function values(string $type, ?array $keys = null): CollectionInterface;

    /**
     * Trim old data from storage.
     *
     * @return void
     */
    public function trim(): void;

    /**
     * Purge data from storage.
     *
     * @param array<string>|null $types Specific types to purge, null for all
     * @return void
     */
    public function purge(?array $types = null): void;
}

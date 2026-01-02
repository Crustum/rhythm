<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Storage;

use Cake\Collection\Collection;
use Cake\Collection\CollectionInterface;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Crustum\Rhythm\Model\Table\RhythmAggregatesTable;
use Crustum\Rhythm\Model\Table\RhythmEntriesTable;
use Crustum\Rhythm\Model\Table\RhythmValuesTable;
use Crustum\Rhythm\RhythmEntry;
use Crustum\Rhythm\RhythmValue;
use Exception;

/**
 * Base Storage Implementation
 *
 * Provides common logic for all Rhythm storage backends.
 * Does not implement upsert or aggregation logic.
 */
abstract class BaseStorage implements StorageInterface
{
    /**
     * Database connection.
     *
     * @var \Cake\Database\Connection
     */
    protected Connection $connection;

    /**
     * Configuration.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Entries table instance.
     *
     * @var \Crustum\Rhythm\Model\Table\RhythmEntriesTable
     */
    protected RhythmEntriesTable $entriesTable;

    /**
     * Values table instance.
     *
     * @var \Crustum\Rhythm\Model\Table\RhythmValuesTable
     */
    protected RhythmValuesTable $valuesTable;

    /**
     * Aggregates table instance.
     *
     * @var \Crustum\Rhythm\Model\Table\RhythmAggregatesTable
     */
    protected RhythmAggregatesTable $aggregatesTable;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        /** @var \Crustum\Rhythm\Model\Table\RhythmEntriesTable $entriesTable */
        $entriesTable = TableRegistry::getTableLocator()->get('Crustum/Rhythm.RhythmEntries');
        $this->entriesTable = $entriesTable;

        /** @var \Crustum\Rhythm\Model\Table\RhythmValuesTable $valuesTable */
        $valuesTable = TableRegistry::getTableLocator()->get('Crustum/Rhythm.RhythmValues');
        $this->valuesTable = $valuesTable;

        /** @var \Crustum\Rhythm\Model\Table\RhythmAggregatesTable $aggregatesTable */
        $aggregatesTable = TableRegistry::getTableLocator()->get('Crustum/Rhythm.RhythmAggregates');
        $this->aggregatesTable = $aggregatesTable;
        $this->connection = $this->entriesTable->getConnection();
    }

    /**
     * Trim old data from storage.
     *
     * @return void
     */
    public function trim(): void
    {
        $now = DateTime::now();

        $keep = $this->config['trim']['keep'] ?? '7 days';

        $before = DateTime::now()->modify("-{$keep}");

        if ($now->subDays(7) > $before) {
            $before = $now->subDays(7);
        }

        $this->connection->transactional(function () use ($before, $now): void {
            $this->entriesTable->deleteAll(['timestamp <=' => $before->getTimestamp()]);
            $this->valuesTable->deleteAll(['timestamp <=' => $before->getTimestamp()]);
            $periods = $this->aggregatesTable->find()->select([$this->aggregatesTable->aliasField('period')])->distinct($this->aggregatesTable->aliasField('period'))->all()->extract('period')->toArray();
            foreach ($periods as $period) {
                $date = max($now->subMinutes($period)->getTimestamp(), $before->getTimestamp());
                $this->aggregatesTable->deleteAll([
                    'bucket <=' => $date,
                    'period' => $period]);
            }
        });
    }

    /**
     * Purge data from storage.
     *
     * @param array<string>|null $types Specific types to purge, null for all
     * @return void
     */
    public function purge(?array $types = null): void
    {
        $this->connection->transactional(function () use ($types): void {
            $this->entriesTable->deleteAll($types ? ['type IN' => $types] : []);
            $this->valuesTable->deleteAll($types ? ['type IN' => $types] : []);
            $this->aggregatesTable->deleteAll($types ? ['type IN' => $types] : []);
        });
    }

    /**
     * Retrieve aggregate values for the given type.
     *
     * @param string $type The metric type
     * @param array|string $aggregates List of aggregates ('count', 'min', 'max', 'sum', 'avg')
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
    ): CollectionInterface {
        return $this->aggregatesTable->aggregate($type, $aggregates, $intervalMinutes, $orderBy, $direction, $limit);
    }

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
    ): CollectionInterface {
        return $this->aggregatesTable->aggregateTypes(
            $types,
            $aggregate,
            $intervalMinutes,
            $orderBy,
            $direction,
            $limit,
        );
    }

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
    ): float|CollectionInterface {
        return $this->aggregatesTable->aggregateTotal($types, $aggregate, $intervalMinutes);
    }

    /**
     * Retrieve aggregate values for plotting on a graph.
     *
     * @param array<string> $types List of metric types
     * @param string $aggregate Aggregate type ('count', 'min', 'max', 'sum', 'avg')
     * @param int $intervalMinutes Interval in minutes
     * @return \Cake\Collection\Collection
     */
    public function graph(array $types, string $aggregate, int $intervalMinutes): CollectionInterface
    {
        return $this->aggregatesTable->graph($types, $aggregate, $intervalMinutes);
    }

    /**
     * Retrieve values for the given type.
     *
     * @param string $type The metric type
     * @param array<string>|null $keys Optional list of keys to filter by
     * @return \Cake\Collection\CollectionInterface
     */
    public function values(string $type, ?array $keys = null): CollectionInterface
    {
        return $this->valuesTable->values($type, $keys);
    }

    /**
     * Find the best period for the given interval.
     *
     * @param int $intervalMinutes Interval in minutes
     * @param array<int> $periods Available periods in minutes
     * @return int Best period in minutes
     */
    protected function findBestPeriod(int $intervalMinutes, array $periods): int
    {
        return $this->aggregatesTable->getBestPeriod($intervalMinutes, $periods);
    }

    /**
     * Store metric entries.
     *
     * @param \Cake\Collection\CollectionInterface $items Collection of metric entries
     * @return void
     */
    public function store(CollectionInterface $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $entries = (new Collection($items))->filter(fn($item) => $item instanceof RhythmEntry);
        $values = (new Collection($items))->filter(fn($item) => $item instanceof RhythmValue);

        $entriesForStorage = $entries->filter(
            fn(RhythmEntry $entry) => !$entry->isOnlyBuckets(),
        );
        $entriesForAggregation = $entries;

        $entryChunks = (new Collection($entriesForStorage))
            ->map(fn(RhythmEntry $entry) => [
                'timestamp' => $entry->timestamp,
                'type' => $entry->type,
                'metric_key' => $entry->key,
                'key_hash' => md5($entry->key),
                'value' => $entry->value,
            ])
            ->chunk($this->config['chunk'] ?? 1000);

        $aggregationData = $this->prepareAggregationData($entriesForAggregation);

        $valueChunks = (new Collection($this->collapseValues($values)))
            ->map(fn(RhythmValue $value) => [
                'timestamp' => $value->timestamp,
                'type' => $value->type,
                'metric_key' => $value->key,
                'key_hash' => md5($value->key),
                'value' => $value->value,
            ])
            ->chunk($this->config['chunk'] ?? 1000);

        try {
            foreach ($entryChunks as $chunk) {
                if (empty($chunk)) {
                    continue;
                }
                $entities = $this->entriesTable->newEntities($chunk);
                $this->entriesTable->saveMany($entities);
            }

            $this->processAggregations($aggregationData);

            foreach ($valueChunks as $chunk) {
                if (empty($chunk)) {
                    continue;
                }
                $this->upsertValues($chunk);
            }
        } catch (Exception $e) {
            debug($e->getMessage());
            debug($e->getTraceAsString());
        }
    }

    /**
     * Prepare aggregation data from entries.
     * This method is shared between storage implementations.
     *
     * @param \Cake\Collection\CollectionInterface $entries
     * @return array<array-key, list<array<string, mixed>>>
     */
    protected function prepareAggregationData(CollectionInterface $entries): array
    {
        /** @var array<string, list<array<string, mixed>>> $aggregationData */
        $aggregationData = [];

        foreach ($entries as $entry) {
            $entryAggregations = $entry->aggregations();

            if (empty($entryAggregations)) {
                $defaultAggregations = Configure::read('Rhythm.default_aggregations', ['count']);
                $entryAggregations = $defaultAggregations;
            }

            foreach ((Configure::read('Rhythm.aggregation.periods') ?? [60, 360, 1440, 10080]) as $period) {
                if ($entry->timestamp < (new DateTime())->getTimestamp() - $period * 60) {
                    continue;
                }
                $bucket = (int)(floor($entry->timestamp / $period) * $period);

                foreach ($entryAggregations as $aggregate) {
                    $aggregationData[$aggregate][] = [
                        'bucket' => $bucket,
                        'period' => $period,
                        'type' => $entry->type,
                        'metric_key' => $entry->key,
                        'key_hash' => md5($entry->key),
                        'value' => $entry->value,
                    ];
                }
            }
        }

        return $aggregationData;
    }

    /**
     * Process aggregations.
     * This method must be implemented by concrete storage classes.
     *
     * @param array $aggregationData
     * @return void
     */
    abstract protected function processAggregations(array $aggregationData): void;

    /**
     * Collapse the given values.
     *
     * @param \Cake\Collection\CollectionInterface<int, \Crustum\Rhythm\RhythmValue> $values
     * @return \Cake\Collection\CollectionInterface<int, \Crustum\Rhythm\RhythmValue>
     */
    protected function collapseValues(CollectionInterface $values): CollectionInterface
    {
        $reversed = new Collection(array_reverse($values->toList()));

        return $reversed->unique(fn(RhythmValue $value) => $value->key . ':' . $value->type);
    }

    /**
     * Get current time bucket for a period.
     *
     * @param int $period Time period in seconds
     * @param int|null $timestamp Optional timestamp to use for bucket calculation
     * @return int
     */
    protected function getCurrentBucket(int $period, ?int $timestamp = null): int
    {
        $timestamp = $timestamp ?: (new DateTime())->getTimestamp();

        return (int)($timestamp / $period) * $period;
    }

    /**
     * Upsert values (for set operations).
     *
     * @param list<array<string, mixed>> $values
     */
    protected function upsertValues(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $toInsert = [];
        $toUpdate = [];

        foreach ($values as $value) {
            $existing = $this->valuesTable->find()
                ->where([
                    'type' => $value['type'],
                    'key_hash' => $value['key_hash'],
                ])
                ->first();

            if ($existing) {
                $toUpdate[] = [
                    'id' => $existing->id,
                    'timestamp' => $value['timestamp'],
                    'value' => $value['value'],
                ];
            } else {
                $toInsert[] = $value;
            }
        }

        if ($toInsert !== []) {
            $entities = $this->valuesTable->newEntities($toInsert);
            foreach ($entities as $entity) {
                $this->valuesTable->save($entity);
            }
        }

        foreach ($toUpdate as $updateData) {
            $id = $updateData['id'];
            unset($updateData['id']);

            $entity = $this->valuesTable->get($id);
            $entity = $this->valuesTable->patchEntity($entity, $updateData);
            $this->valuesTable->save($entity);
        }

        return count($toInsert) + count($toUpdate);
    }
}

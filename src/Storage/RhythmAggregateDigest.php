<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Storage;

use Cake\Database\Connection;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Aggregation/digest logic for Rhythm metrics
 * Handles preaggregation, chunking, and upsert for all aggregation types.
 * Optimized for performance using arrays throughout.
 */
class RhythmAggregateDigest
{
    use LocatorAwareTrait;

    /**
     * Aggregates table name
     *
     * @var string
     */
    protected string $table;

    /**
     * Chunk size for batch operations
     *
     * @var int
     */
    protected int $chunkSize;

    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct(array $config = [])
    {
        $this->table = $config['table'] ?? 'rhythm_aggregates';
        $this->chunkSize = $config['chunkSize'] ?? 1000;
    }

    /**
     * Process all aggregation types for given entries in a single optimized pass.
     *
     * @param array $aggregationData Array of [aggregateType => entries[]]
     * @return int Total number of affected rows
     */
    public function processAggregations(array $aggregationData): int
    {
        if ($aggregationData === []) {
            return 0;
        }

        $totalAffected = 0;
        foreach ($aggregationData as $aggregateType => $entries) {
            if (empty($entries)) {
                continue;
            }

            $aggregated = $this->aggregateEntries($entries, $aggregateType);
            if ($aggregated !== []) {
                $totalAffected += $this->upsertAggregates($aggregated, $aggregateType);
            }
        }

        return $totalAffected;
    }

    /**
     * Aggregate entries by type using optimized array operations.
     *
     * @param array $entries
     * @param string $aggregateType
     * @return array
     */
    protected function aggregateEntries(array $entries, string $aggregateType): array
    {
        $result = [];

        foreach ($entries as $entry) {
            $key = $this->buildAggregateKey($entry, $aggregateType);

            if (isset($result[$key])) {
                $result[$key] = $this->mergeAggregateEntry($result[$key], $entry, $aggregateType);
            } else {
                $result[$key] = $this->createAggregateEntry($entry, $aggregateType);
            }
        }

        return array_values($result);
    }

    /**
     * Build unique key for aggregation grouping.
     *
     * @param array $entry
     * @param string $aggregateType
     * @return string
     */
    protected function buildAggregateKey(array $entry, string $aggregateType): string
    {
        return sprintf(
            '%s:%s:%s:%s:%s',
            $entry['bucket'],
            $entry['period'],
            $entry['type'],
            $aggregateType,
            $entry['key_hash'],
        );
    }

    /**
     * Create new aggregate entry.
     *
     * @param array $entry
     * @param string $aggregateType
     * @return array
     */
    protected function createAggregateEntry(array $entry, string $aggregateType): array
    {
        return [
            'bucket' => $entry['bucket'],
            'period' => $entry['period'],
            'type' => $entry['type'],
            'key' => $entry['key'],
            'key_hash' => $entry['key_hash'],
            'aggregate' => $aggregateType,
            'value' => $this->getInitialValue($entry, $aggregateType),
            'count' => 1,
        ];
    }

    /**
     * Merge entry into existing aggregate.
     *
     * @param array $aggregate
     * @param array $entry
     * @param string $aggregateType
     * @return array
     */
    protected function mergeAggregateEntry(array $aggregate, array $entry, string $aggregateType): array
    {
        $aggregate['value'] = $this->calculateAggregateValue($aggregate, $entry, $aggregateType);
        $aggregate['count']++;

        return $aggregate;
    }

    /**
     * Get initial value for aggregate type.
     *
     * @param array $entry
     * @param string $aggregateType
     * @return float|int
     */
    protected function getInitialValue(array $entry, string $aggregateType): float|int
    {
        return match ($aggregateType) {
            'count' => 1,
            'min', 'max', 'sum', 'avg' => $entry['value'] ?? 0,
            default => 0
        };
    }

    /**
     * Calculate aggregate value based on type.
     *
     * @param array $aggregate
     * @param array $entry
     * @param string $aggregateType
     * @return float|int
     */
    protected function calculateAggregateValue(array $aggregate, array $entry, string $aggregateType): float|int
    {
        return match ($aggregateType) {
            'count' => $aggregate['value'] + 1,
            'min' => min($aggregate['value'], $entry['value']),
            'max' => max($aggregate['value'], $entry['value']),
            'sum' => $aggregate['value'] + $entry['value'],
            'avg' => ($aggregate['value'] * $aggregate['count'] + $entry['value']) / ($aggregate['count'] + 1),
            default => $aggregate['value']
        };
    }

    /**
     * Upsert aggregated data using optimized batch operations.
     *
     * @param array $aggregates
     * @param string $aggregateType
     * @return int
     */
    protected function upsertAggregates(array $aggregates, string $aggregateType): int
    {
        if ($aggregates === []) {
            return 0;
        }

        $chunkSize = max(1, $this->chunkSize);
        $chunks = array_chunk($aggregates, $chunkSize);

        foreach ($chunks as $chunk) {
            $this->executeUpsert($chunk, $aggregateType);
        }

        return count($aggregates);
    }

    /**
     * Execute upsert query for a chunk of data using CakePHP's query builder.
     *
     * @param array $rows
     * @param string $aggregateType
     * @return int
     */
    protected function executeUpsert(array $rows, string $aggregateType): int
    {
        if ($rows === []) {
            return 0;
        }

        $table = $this->getTableLocator()->get($this->table);
        $connection = $table->getConnection();
        $driverName = $this->getDriverName($connection);

        $query = $connection->insertQuery($this->table);
        $columns = array_keys($rows[0]);
        $query->insert($columns);

        foreach ($rows as $row) {
            $query->values($row);
        }

        $updateClauses = $this->getUpdateClauses($aggregateType, $driverName);

        if (in_array($driverName, ['mysql', 'mariadb'])) {
            $query->epilog('ON DUPLICATE KEY UPDATE ' . implode(', ', $updateClauses));
        } else {
            $conflictColumns = ['bucket', 'period', 'type', 'key_hash', 'aggregate'];
            $query->epilog(
                'ON CONFLICT (' . implode(', ', $conflictColumns) . ') DO UPDATE SET ' .
                implode(', ', $updateClauses),
            );
        }

        $statement = $query->execute();

        return $statement->rowCount();
    }

    /**
     * Get update clauses for different database drivers.
     *
     * @param string $aggregateType
     * @param string $driverName
     * @return array
     */
    protected function getUpdateClauses(string $aggregateType, string $driverName): array
    {
        $clauses = [
            'mysql' => $this->getMySQLUpdateClauses($aggregateType),
            'mariadb' => $this->getMySQLUpdateClauses($aggregateType),
            'pgsql' => $this->getPostgreSQLUpdateClauses($aggregateType),
            'sqlite' => $this->getSQLiteUpdateClauses($aggregateType),
        ];

        return $clauses[$driverName] ?? $clauses['mysql'];
    }

    /**
     * Get MySQL/MariaDB update clauses.
     *
     * @param string $aggregateType
     * @return array
     */
    protected function getMySQLUpdateClauses(string $aggregateType): array
    {
        return match ($aggregateType) {
            'count', 'sum' => [
                '`value` = `value` + VALUES(`value`)',
                '`count` = `count` + VALUES(`count`)',
            ],
            'min' => [
                '`value` = LEAST(`value`, VALUES(`value`))',
                '`count` = `count` + VALUES(`count`)',
            ],
            'max' => [
                '`value` = GREATEST(`value`, VALUES(`value`))',
                '`count` = `count` + VALUES(`count`)',
            ],
            'avg' => [
                '`value` = (`value` * `count` + (VALUES(`value`) * VALUES(`count`))) / (`count` + VALUES(`count`))',
                '`count` = `count` + VALUES(`count`)',
            ],
            default => [
                '`value` = VALUES(`value`)',
                '`count` = VALUES(`count`)',
            ]
        };
    }

    /**
     * Get PostgreSQL update clauses.
     *
     * @param string $aggregateType
     * @return array
     */
    protected function getPostgreSQLUpdateClauses(string $aggregateType): array
    {
        return match ($aggregateType) {
            'count', 'sum' => [
                '"value" = "rhythm_aggregates"."value" + excluded."value"',
                '"count" = "rhythm_aggregates"."count" + excluded."count"',
            ],
            'min' => [
                '"value" = LEAST("rhythm_aggregates"."value", excluded."value")',
                '"count" = "rhythm_aggregates"."count" + excluded."count"',
            ],
            'max' => [
                '"value" = GREATEST("rhythm_aggregates"."value", excluded."value")',
                '"count" = "rhythm_aggregates"."count" + excluded."count"',
            ],
            'avg' => [
                '"value" = ("rhythm_aggregates"."value" * "rhythm_aggregates"."count"' .
                '  + (excluded."value" * excluded."count")) / ' .
                '  ("rhythm_aggregates"."count" + excluded."count")',
                '"count" = "rhythm_aggregates"."count" + excluded."count"',
            ],
            default => [
                '"value" = excluded."value"',
                '"count" = excluded."count"',
            ]
        };
    }

    /**
     * Get SQLite update clauses.
     *
     * @param string $aggregateType
     * @return array
     */
    protected function getSQLiteUpdateClauses(string $aggregateType): array
    {
        return match ($aggregateType) {
            'count', 'sum' => [
                'value = value + excluded.value',
                'count = count + excluded.count',
            ],
            'min' => [
                'value = MIN(value, excluded.value)',
                'count = count + excluded.count',
            ],
            'max' => [
                'value = MAX(value, excluded.value)',
                'count = count + excluded.count',
            ],
            'avg' => [
                'value = (value * count + (excluded.value * excluded.count)) / ' .
                '(count + excluded.count)',
                'count = count + excluded.count',
            ],
            default => [
                'value = excluded.value',
                'count = excluded.count',
            ]
        };
    }

    /**
     * Get database driver name.
     *
     * @param \Cake\Database\Connection $connection
     * @return string
     */
    protected function getDriverName(Connection $connection): string
    {
        $driverClass = get_class($connection->getDriver());
        $driverName = strtolower(basename(str_replace('\\', '/', $driverClass)));

        return match ($driverName) {
            'mysql' => 'mysql',
            'postgres' => 'pgsql',
            'sqlite' => 'sqlite',
            'sqlserver' => 'sqlserver',
            default => 'mysql'
        };
    }

    /**
     * Get table name.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get chunk size.
     *
     * @return int
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }
}

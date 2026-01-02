<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventListenerInterface;
use Crustum\Rhythm\Event\SharedBeat;
use Crustum\Rhythm\Recorder\Trait\ThrottlingTrait;
use Exception;

/**
 * PostgreSQL Monitor Recorder
 *
 * Monitors PostgreSQL server status using efficient queries to pg_stat_database and pg_stat_bgwriter.
 */
class PostgreSqlMonitorRecorder extends BaseRecorder implements EventListenerInterface
{
    use ThrottlingTrait;

    /**
     * @inheritDoc
     */
    public function implementedEvents(): array
    {
        return [
            SharedBeat::class => 'record',
        ];
    }

    /**
     * Record PostgreSQL monitoring data.
     *
     * @param mixed $data The shared beat event.
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$data instanceof SharedBeat) {
            return;
        }

        $this->throttle(($this->config['interval'] ?? 5) * 60, $data, function (SharedBeat $event): void {
            $this->monitorConnections($event);
        });
    }

    /**
     * Monitor all configured PostgreSQL connections.
     *
     * @param \Crustum\Rhythm\Event\SharedBeat $event The shared beat event.
     * @return void
     */
    protected function monitorConnections(SharedBeat $event): void
    {
        $connections = $this->config['connections'] ?? ['default'];

        foreach ($connections as $connectionName) {
            try {
                $this->monitorConnection($connectionName, $event);
            } catch (Exception) {
                $this->rhythm->record('database_error', $connectionName, 1)->count()->onlyBuckets();
            }
        }
    }

    /**
     * Monitor a specific PostgreSQL connection.
     *
     * @param string $connectionName Connection name
     * @param \Crustum\Rhythm\Event\SharedBeat $event The shared beat event
     * @return void
     */
    protected function monitorConnection(string $connectionName, SharedBeat $event): void
    {
        $status = $this->getPostgreSqlStatus($connectionName);
        $timestamp = $event->getTimestamp()->getTimestamp();

        $maxAggregates = $this->config['aggregates']['max'] ?? [];
        $avgAggregates = $this->config['aggregates']['avg'] ?? [];
        $countAggregates = $this->config['aggregates']['count'] ?? [];

        foreach ($maxAggregates as $metric) {
            if (isset($status[$metric])) {
                $this->rhythm->record($metric, $connectionName, (int)$status[$metric], $timestamp)
                    ->max()
                    ->onlyBuckets();
            }
        }

        foreach ($avgAggregates as $metric) {
            if (isset($status[$metric])) {
                $this->rhythm->record($metric, $connectionName, (int)$status[$metric], $timestamp)
                    ->avg()
                    ->onlyBuckets();
            }
        }

        foreach ($countAggregates as $metric) {
            if (isset($status[$metric])) {
                $this->rhythm->record($metric, $connectionName, (int)$status[$metric], $timestamp)
                    ->count()
                    ->onlyBuckets();
            }
        }

        $this->rhythm->set('database_connection', $connectionName, json_encode([
            ...$status,
            'name' => $connectionName,
        ], JSON_THROW_ON_ERROR), $timestamp);
    }

    /**
     * Get PostgreSQL status variables for a connection.
     *
     * @param string $connectionName Connection name
     * @return array<string, mixed>
     */
    protected function getPostgreSqlStatus(string $connectionName): array
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get($connectionName);

        $status = [];

        $dbStats = $this->getDatabaseStats($connection);
        $status = array_merge($status, $dbStats);

        $bgStats = $this->getBackgroundWriterStats($connection);
        $status = array_merge($status, $bgStats);

        $connStats = $this->getConnectionStats($connection);
        $status = array_merge($status, $connStats);

        return $status;
    }

    /**
     * Get database statistics from pg_stat_database.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @return array<string, mixed>
     */
    protected function getDatabaseStats(Connection $connection): array
    {
        $sql = "
            SELECT
                numbackends as active_connections,
                xact_commit as transactions_committed,
                xact_rollback as transactions_rollback,
                blks_read as blocks_read,
                blks_hit as blocks_hit,
                tup_returned as tuples_returned,
                tup_fetched as tuples_fetched,
                tup_inserted as tuples_inserted,
                tup_updated as tuples_updated,
                tup_deleted as tuples_deleted,
                temp_files as temp_files,
                temp_bytes as temp_bytes,
                deadlocks as deadlocks,
                blk_read_time as block_read_time,
                blk_write_time as block_write_time
            FROM pg_stat_database
            WHERE datname = current_database()
        ";

        $statement = $connection->execute($sql);
        $result = $statement->fetch('assoc');

        return $result ?: [];
    }

    /**
     * Get background writer statistics from pg_stat_bgwriter.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @return array<string, mixed>
     */
    protected function getBackgroundWriterStats(Connection $connection): array
    {
        $sql = "
            SELECT
                checkpoints_timed as checkpoints_timed,
                checkpoints_req as checkpoints_req,
                checkpoint_write_time as checkpoint_write_time,
                checkpoint_sync_time as checkpoint_sync_time,
                buffers_checkpoint as buffers_checkpoint,
                buffers_clean as buffers_clean,
                maxwritten_clean as maxwritten_clean,
                buffers_backend as buffers_backend,
                buffers_backend_fsync as buffers_backend_fsync,
                buffers_alloc as buffers_alloc
            FROM pg_stat_bgwriter
        ";

        $statement = $connection->execute($sql);
        $result = $statement->fetch('assoc');

        return $result ?: [];
    }

    /**
     * Get connection statistics.
     *
     * @param \Cake\Database\Connection $connection Database connection
     * @return array<string, mixed>
     */
    protected function getConnectionStats(Connection $connection): array
    {
        $sql = "
            SELECT
                count(*) as total_connections,
                count(*) FILTER (WHERE state = 'active') as active_connections,
                count(*) FILTER (WHERE state = 'idle') as idle_connections,
                count(*) FILTER (WHERE state = 'idle in transaction') as idle_in_transaction_connections
            FROM pg_stat_activity
        ";

        $statement = $connection->execute($sql);
        $result = $statement->fetch('assoc');

        return $result ?: [];
    }

    /**
     * Get status variables to monitor.
     *
     * @return array<string>
     */
    protected function getStatusVariables(): array
    {
        $maxAggregates = $this->config['aggregates']['max'] ?? [];
        $avgAggregates = $this->config['aggregates']['avg'] ?? [];
        $countAggregates = $this->config['aggregates']['count'] ?? [];

        return array_merge($maxAggregates, $avgAggregates, $countAggregates);
    }
}

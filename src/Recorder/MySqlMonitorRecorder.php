<?php
declare(strict_types=1);

namespace Rhythm\Recorder;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventListenerInterface;
use Exception;
use Rhythm\Event\SharedBeat;
use Rhythm\Recorder\Trait\ThrottlingTrait;

/**
 * MySQL Monitor Recorder
 *
 * Monitors MySQL server status using efficient single SHOW STATUS query.
 */
class MySqlMonitorRecorder extends BaseRecorder implements EventListenerInterface
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
     * Record MySQL monitoring data.
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
     * Monitor all configured MySQL connections.
     *
     * @param \Rhythm\Event\SharedBeat $event The shared beat event.
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
     * Monitor a specific MySQL connection.
     *
     * @param string $connectionName Connection name
     * @param \Rhythm\Event\SharedBeat $event The shared beat event
     * @return void
     */
    protected function monitorConnection(string $connectionName, SharedBeat $event): void
    {
        $status = $this->getMySqlStatus($connectionName);
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
     * Get MySQL status variables for a connection.
     *
     * @param string $connectionName Connection name
     * @return array<string, mixed>
     */
    protected function getMySqlStatus(string $connectionName): array
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get($connectionName);
        $statement = $connection->execute('SHOW STATUS');
        $result = $statement->fetchAll('assoc');

        $statusVariables = $this->getStatusVariables();

        $filtered = [];
        foreach ($result as $row) {
            if (in_array($row['Variable_name'], $statusVariables)) {
                $filtered[$row['Variable_name']] = $row['Value'];
            }
        }

        return $filtered;
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

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create Rhythm Aggregates Table Migration
 *
 * Creates the aggregates table for pre-aggregated metrics.
 * This is a separate migration as mentioned in the plan.
 */
class CreateRhythmAggregates extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $this->table('rhythm_aggregates')
            ->addColumn('bucket', 'integer', [
                'null' => false,
                'comment' => 'Time bucket for aggregation (aligned to period)',
            ])
            ->addColumn('period', 'integer', [
                'null' => false,
                'comment' => 'Aggregation period in seconds (60, 300, 3600, 86400)',
            ])
            ->addColumn('type', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Type of metric being aggregated',
            ])
            ->addColumn('key_hash', 'string', [
                'limit' => 32,
                'null' => false,
                'comment' => 'MD5 hash of the metric key',
            ])
            ->addColumn('metric_key', 'text', [
                'null' => false,
                'comment' => 'The actual metric key value',
            ])
            ->addColumn('aggregate', 'string', [
                'limit' => 50,
                'null' => false,
                'comment' => 'Type of aggregation (count, min, max, sum, avg)',
            ])
            ->addColumn('value', 'decimal', [
                'precision' => 20,
                'scale' => 2,
                'null' => false,
                'comment' => 'Aggregated value',
            ])
            ->addColumn('entry_count', 'integer', [
                'null' => true,
                'comment' => 'Number of entries in this aggregation (for avg calculations)',
            ])
            ->addIndex(['bucket', 'period', 'type', 'aggregate', 'key_hash'], [
                'name' => 'unique_rhythm_aggregate',
                'unique' => true,
            ])
            ->addIndex(['period', 'bucket'], [
                'name' => 'idx_rhythm_aggregates_period_bucket',
            ])
            ->addIndex(['type'], [
                'name' => 'idx_rhythm_aggregates_type',
            ])
            ->addIndex(['period', 'type', 'aggregate', 'bucket'], [
                'name' => 'idx_rhythm_aggregates_query',
            ])
            ->create();
    }
}

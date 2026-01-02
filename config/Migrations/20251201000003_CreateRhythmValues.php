<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create Rhythm Values Table Migration
 *
 * Creates the values table for storing string-based metrics.
 * This table stores RhythmValue objects with string values.
 */
class CreateRhythmValues extends AbstractMigration
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
        $this->table('rhythm_values')
            ->addColumn('timestamp', 'integer', [
                'null' => false,
                'comment' => 'Unix timestamp of the metric value',
            ])
            ->addColumn('type', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Type of metric (e.g., config_value, user_agent, status)',
            ])
            ->addColumn('key_hash', 'string', [
                'limit' => 32,
                'null' => false,
                'comment' => 'MD5 hash of the metric key for efficient lookups',
            ])
            ->addColumn('metric_key', 'text', [
                'null' => false,
                'comment' => 'The actual metric key value',
            ])
            ->addColumn('value', 'text', [
                'null' => false,
                'comment' => 'String value of the metric',
            ])
            ->addIndex(['timestamp'], [
                'name' => 'idx_rhythm_values_timestamp',
            ])
            ->addIndex(['type'], [
                'name' => 'idx_rhythm_values_type',
            ])
            ->addIndex(['type', 'key_hash'], [
                'name' => 'unique_rhythm_values',
                'unique' => true,
            ])
            ->create();
    }
}

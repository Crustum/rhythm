<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create Rhythm Tables Migration
 *
 * Creates the main tables for the Rhythm performance monitoring system.
 */
class CreateRhythmEntries extends AbstractMigration
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
        // Create rhythm_entries table for raw metric entries
        $this->table('rhythm_entries')
            ->addColumn('timestamp', 'integer', [
                'null' => false,
                'comment' => 'Unix timestamp of the metric entry',
            ])
            ->addColumn('type', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Type of metric (e.g., user_request, slow_query, exception)',
            ])
            ->addColumn('key_hash', 'string', [
                'limit' => 32,
                'null' => false,
                'comment' => 'MD5 hash of the metric key for efficient lookups',
            ])
            ->addColumn('key', 'text', [
                'null' => false,
                'comment' => 'The actual metric key value',
            ])
            ->addColumn('value', 'biginteger', [
                'null' => true,
                'comment' => 'Numeric value of the metric (optional)',
            ])
            ->addIndex(['timestamp'], [
                'name' => 'idx_rhythm_entries_timestamp',
            ])
            ->addIndex(['type'], [
                'name' => 'idx_rhythm_entries_type',
            ])
            ->addIndex(['key_hash'], [
                'name' => 'idx_rhythm_entries_key_hash',
            ])
            ->addIndex(['timestamp', 'type', 'key_hash', 'value'], [
                'name' => 'idx_rhythm_entries_composite',
            ])
            ->create();
    }
}

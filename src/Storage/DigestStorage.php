<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Storage;

use Cake\Core\Configure;

/**
 * Digest Storage Implementation
 *
 * Uses RhythmAggregateDigest for efficient aggregation and upsert operations.
 * Optimized for performance using arrays throughout.
 */
class DigestStorage extends BaseStorage
{
    /**
     * Constructor.
     *
     * @param array $config Configuration array
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config ?: Configure::read('Rhythm.storage.digest', []));
    }

    /**
     * Process aggregations for the given entries.
     *
     * @param array $aggregationData
     * @return void
     */
    protected function processAggregations(array $aggregationData): void
    {
        if ($aggregationData === []) {
            return;
        }

        $digest = new RhythmAggregateDigest([
            'table' => 'rhythm_aggregates',
            'chunkSize' => $this->config['chunk'] ?? 1000,
        ]);

        $digest->processAggregations($aggregationData);
    }
}

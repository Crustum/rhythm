<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Model\Entity;

use Cake\ORM\Entity;

/**
 * Metric Aggregate Entity
 *
 * Represents an aggregated metric in the rhythm system.
 */
class MetricAggregate extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'bucket' => true,
        'period' => true,
        'type' => true,
        'key_hash' => true,
        'key' => true,
        'aggregate' => true,
        'value' => true,
        'count' => true,
    ];

    /**
     * Hidden fields for JSON serialization.
     *
     * @var array<string>
     */
    protected array $_hidden = [
        'id',
    ];
}

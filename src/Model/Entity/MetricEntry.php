<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Model\Entity;

use Cake\ORM\Entity;

/**
 * Metric Entry Entity
 *
 * Represents a single metric entry in the rhythm system.
 */
class MetricEntry extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'timestamp' => true,
        'type' => true,
        'key' => true,
        'key_hash' => true,
        'value' => true,
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

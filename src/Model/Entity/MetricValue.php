<?php
declare(strict_types=1);

namespace Rhythm\Model\Entity;

use Cake\ORM\Entity;

/**
 * Metric Value Entity
 *
 * Represents a metric value in the rhythm system.
 */
class MetricValue extends Entity
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

<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Model\Entity;

use Cake\ORM\Entity;

/**
 * Metric Entry Entity
 *
 * Represents a single metric entry in the rhythm system.
 *
 * @property int $id
 * @property int $timestamp
 * @property string $type
 * @property string $metric_key
 * @property string $key_hash
 * @property float|string|int|null $value
 * @property string|null $key Virtual accessor for metric_key
 * @property float|string|int|null $total Virtual select alias used by aggregate queries
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
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
        'metric_key' => true,
        'key_hash' => true,
        'value' => true,
        'key' => true,
    ];

    /**
     * Get the metric key.
     *
     * @return string|null
     */
    protected function _getKey(): ?string
    {
        return $this->_fields['metric_key'] ?? null;
    }

    /**
     * Set the metric key.
     *
     * @param string|null $value
     */
    protected function _setKey(?string $value): void
    {
        $this->_fields['metric_key'] = $value;
    }

    /**
     * Virtual fields that use accessors.
     *
     * @var array<string>
     */
    protected array $_virtual = [
        'key',
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

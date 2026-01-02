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
        'metric_key' => true,
        'aggregate' => true,
        'value' => true,
        'entry_count' => true,
        'key' => true,
        'count' => true,
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
     * Get the entry count.
     *
     * @return int|null
     */
    protected function _getCount(): ?int
    {
        return $this->_fields['entry_count'] ?? null;
    }

    /**
     * Set the entry count.
     *
     * @param int|null $value
     */
    protected function _setCount(?int $value): void
    {
        $this->_fields['entry_count'] = $value;
    }

    /**
     * Virtual fields that use accessors.
     *
     * @var array<string>
     */
    protected array $_virtual = [
        'key',
        'count',
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

<?php
declare(strict_types=1);

namespace Rhythm;

/**
 * Lightweight Entry for Internal Collections
 *
 * Represents a numeric metric entry for aggregation.
 */
class RhythmEntry
{
    /**
     * The aggregations to perform on the entry.
     *
     * @var array<string>
     */
    protected array $aggregations = [];

    /**
     * Whether to only save aggregate bucket data for the entry.
     */
    protected bool $onlyBuckets = false;

    /**
     * Create a new RhythmEntry instance.
     *
     * @param int $timestamp Unix timestamp
     * @param string $type Metric type
     * @param string $key Metric key
     * @param int|null $value Numeric value for aggregation
     */
    public function __construct(
        public int $timestamp,
        public string $type,
        public string $key,
        public ?int $value = null,
    ) {
    }

    /**
     * Capture the count aggregate.
     *
     * @return static
     */
    public function count(): static
    {
        $this->aggregations[] = 'count';

        return $this;
    }

    /**
     * Capture the minimum aggregate.
     *
     * @return static
     */
    public function min(): static
    {
        $this->aggregations[] = 'min';

        return $this;
    }

    /**
     * Capture the maximum aggregate.
     *
     * @return static
     */
    public function max(): static
    {
        $this->aggregations[] = 'max';

        return $this;
    }

    /**
     * Capture the sum aggregate.
     *
     * @return static
     */
    public function sum(): static
    {
        $this->aggregations[] = 'sum';

        return $this;
    }

    /**
     * Capture the average aggregate.
     *
     * @return static
     */
    public function avg(): static
    {
        $this->aggregations[] = 'avg';

        return $this;
    }

    /**
     * Only save aggregate bucket data for the entry.
     *
     * @return static
     */
    public function onlyBuckets(): static
    {
        $this->onlyBuckets = true;

        return $this;
    }

    /**
     * Return the aggregations for the entry.
     *
     * @return array<string>
     */
    public function aggregations(): array
    {
        return $this->aggregations;
    }

    /**
     * Determine whether the entry is marked for count aggregation.
     *
     * @return bool
     */
    public function isCount(): bool
    {
        return in_array('count', $this->aggregations);
    }

    /**
     * Determine whether the entry is marked for minimum aggregation.
     *
     * @return bool
     */
    public function isMin(): bool
    {
        return in_array('min', $this->aggregations);
    }

    /**
     * Determine whether the entry is marked for maximum aggregation.
     *
     * @return bool
     */
    public function isMax(): bool
    {
        return in_array('max', $this->aggregations);
    }

    /**
     * Determine whether the entry is marked for sum aggregation.
     *
     * @return bool
     */
    public function isSum(): bool
    {
        return in_array('sum', $this->aggregations);
    }

    /**
     * Determine whether the entry is marked for average aggregation.
     *
     * @return bool
     */
    public function isAvg(): bool
    {
        return in_array('avg', $this->aggregations);
    }

    /**
     * Determine whether to only save aggregate bucket data for the entry.
     *
     * @return bool
     */
    public function isOnlyBuckets(): bool
    {
        return $this->onlyBuckets;
    }

    /**
     * Fetch the entry attributes for persisting.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'type' => $this->type,
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}

<?php
declare(strict_types=1);

namespace Rhythm;

/**
 * Lightweight Value for Internal Collections
 *
 * Represents a string metric value for storage and display.
 * Simple data object for the ingest layer.
 */
class RhythmValue
{
    /**
     * Create a new RhythmValue instance.
     *
     * @param int $timestamp Unix timestamp
     * @param string $type Metric type
     * @param string $key Metric key
     * @param string $value String value for storage/display
     */
    public function __construct(
        public int $timestamp,
        public string $type,
        public string $key,
        public string $value,
    ) {
    }

    /**
     * Fetch the value attributes for persisting.
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

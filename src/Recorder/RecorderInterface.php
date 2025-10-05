<?php
declare(strict_types=1);

namespace Rhythm\Recorder;

/**
 * Recorder Interface
 *
 * Contract for metric recorders.
 */
interface RecorderInterface
{
    /**
     * Record metric data.
     *
     * @param mixed $data Data to record
     * @return void
     */
    public function record(mixed $data): void;

    /**
     * Check if recorder is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Get sample rate for recording.
     *
     * @return float Sample rate between 0.0 and 1.0
     */
    public function getSampleRate(): float;
}

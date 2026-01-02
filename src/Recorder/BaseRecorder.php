<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Crustum\Rhythm\Rhythm;

/**
 * Base Recorder Abstract Class
 *
 * Provides common functionality for all recorders including:
 * - Standard constructor with Rhythm and config injection
 * - Common isEnabled() and getSampleRate() implementations
 * - Abstract record() method for subclasses to implement
 */
abstract class BaseRecorder implements RecorderInterface
{
    /**
     * Rhythm instance.
     *
     * @var \Crustum\Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Recorder configuration.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Constructor.
     *
     * @param \Crustum\Rhythm\Rhythm $rhythm Rhythm instance
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(Rhythm $rhythm, array $config = [])
    {
        $this->rhythm = $rhythm;
        $this->config = $config;
    }

    /**
     * Check if recorder is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Get recorder configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get sample rate for recording.
     *
     * @return float Sample rate between 0.0 and 1.0
     */
    public function getSampleRate(): float
    {
        return $this->config['sample_rate'] ?? 1.0;
    }

    /**
     * Record metric data.
     *
     * @param mixed $data Data to record
     * @return void
     */
    abstract public function record(mixed $data): void;
}

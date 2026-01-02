<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder\Trait;

use Crustum\Rhythm\Utility\Chance;

/**
 * Provides sampling functionality for recorders.
 */
trait SamplingTrait
{
    /**
     * Determine if the event should be sampled.
     *
     * @return bool
     */
    public function shouldSample(): bool
    {
        $sampleRate = $this->getSampleRate();

        return (new Chance($sampleRate))->isWin();
    }

    /**
     * Determine if the event should be sampled deterministically.
     *
     * @param string $seed The seed for deterministic sampling.
     * @return bool
     */
    public function shouldSampleDeterministically(string $seed): bool
    {
        $sampleRate = $this->getSampleRate();
        $value = hexdec(md5($seed)) / pow(16, 32);

        return $value <= $sampleRate;
    }
}

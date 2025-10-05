<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Recorder\Trait;

use Rhythm\Recorder\Trait\SamplingTrait;

/**
 * Test class for testing SamplingTrait
 */
class TestSamplingClass
{
    use SamplingTrait;

    /**
     * Configuration array
     *
     * @var array
     */
    protected array $config;

    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
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
}

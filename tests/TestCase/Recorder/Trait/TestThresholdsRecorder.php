<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Recorder\Trait;

use Rhythm\Recorder\Trait\ThresholdsTrait;

/**
 * Test Recorder for ThresholdsTrait testing
 */
class TestThresholdsRecorder
{
    use ThresholdsTrait;

    public array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
}

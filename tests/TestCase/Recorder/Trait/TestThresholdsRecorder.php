<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Recorder\Trait;

use Crustum\Rhythm\Recorder\Trait\ThresholdsTrait;

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

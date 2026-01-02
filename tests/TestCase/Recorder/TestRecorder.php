<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Recorder;

use Crustum\Rhythm\Recorder\RecorderInterface;

/**
 * Test recorder class for testing
 */
class TestRecorder implements RecorderInterface
{
    public function record(mixed $data): void
    {
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getSampleRate(): float
    {
        return 1.0;
    }
}

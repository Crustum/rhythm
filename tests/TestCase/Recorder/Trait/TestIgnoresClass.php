<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Recorder\Trait;

use Crustum\Rhythm\Recorder\Trait\IgnoresTrait;

/**
 * Test class for testing IgnoresTrait
 */
class TestIgnoresClass
{
    use IgnoresTrait;

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
     * Get recorder configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}

<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Recorder\Trait;

use Crustum\Rhythm\Recorder\Trait\GroupsTrait;

/**
 * Test class for testing GroupsTrait
 */
class TestGroupsClass
{
    use GroupsTrait;

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
     * Get configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}

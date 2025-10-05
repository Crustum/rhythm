<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Recorder\Trait;

use Rhythm\Recorder\Trait\QueueNameTrait;

/**
 * Test class for testing QueueNameTrait
 */
class TestQueueNameClass
{
    use QueueNameTrait;

    /**
     * Get queue prefixes for testing.
     *
     * @return array
     */
    public function getQueuePrefixes(): array
    {
        return $this->queuePrefixes;
    }

    /**
     * Set queue prefixes for testing.
     *
     * @param array $prefixes
     * @return void
     */
    public function setQueuePrefixes(array $prefixes): void
    {
        $this->queuePrefixes = $prefixes;
    }
}

<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Ingest;

use Cake\Collection\Collection;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Crustum\Rhythm\Ingest\IngestInterface;
use Crustum\Rhythm\Ingest\NullIngest;
use Crustum\Rhythm\RhythmEntry;
use Crustum\Rhythm\Storage\StorageInterface;

/**
 * NullIngest Test Case
 *
 * Tests the null object implementation that discards all metrics.
 */
class NullIngestTest extends TestCase
{
    /**
     * Null ingest instance.
     *
     * @var \Crustum\Rhythm\Ingest\NullIngest
     */
    protected NullIngest $nullIngest;

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->nullIngest = new NullIngest();
    }

    /**
     * Test that digest method returns zero and does nothing
     *
     * @return void
     */
    public function testDigestReturnsZero(): void
    {
        /** @var \Crustum\Rhythm\Storage\StorageInterface&\PHPUnit\Framework\MockObject\MockObject $storage */
        $storage = $this->createMock(StorageInterface::class);

        // Storage should never be called since null ingest doesn't process anything
        $storage->expects($this->never())->method('store');

        $result = $this->nullIngest->digest();

        $this->assertSame(0, $result);
    }

    /**
     * Test multiple operations in sequence
     *
     * @return void
     */
    public function testMultipleOperationsInSequence(): void
    {
        /** @var \Crustum\Rhythm\Storage\StorageInterface&\PHPUnit\Framework\MockObject\MockObject $storage */
        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->never())->method('store');

        $timestamp = (new DateTime())->getTimestamp();
        $items = new Collection([
            new RhythmEntry($timestamp, 'request', 'user:456', 200),
        ]);

        // Perform multiple operations
        $this->nullIngest->ingest($items);
        $result = $this->nullIngest->digest();
        $this->nullIngest->trim();

        $this->assertSame(0, $result);
    }

    /**
     * Test that null ingest implements the interface correctly
     *
     * @return void
     */
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(IngestInterface::class, $this->nullIngest);
    }
}

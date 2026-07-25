<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Recorder;

use Cake\Core\Container;
use Cake\TestSuite\TestCase;
use Crustum\Rhythm\Ingest\IngestInterface;
use Crustum\Rhythm\Recorder\RecorderInterface;
use Crustum\Rhythm\Recorder\RecorderResolver;
use Crustum\Rhythm\Rhythm;
use Crustum\Rhythm\Storage\StorageInterface;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\Stub;
use stdClass;

/**
 * RecorderResolver Test Case
 */
class RecorderResolverTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Crustum\Rhythm\Recorder\RecorderResolver
     */
    protected RecorderResolver $resolver;

    /**
     * Real container
     *
     * @var \Cake\Core\Container
     */
    protected Container $container;

    /**
     * Real rhythm instance
     *
     * @var \Crustum\Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();

        $this->container->addShared(StorageInterface::class, fn(): Stub => $this->createStub(StorageInterface::class));

        $mockIngest = $this->createStub(IngestInterface::class);
        $this->rhythm = new Rhythm(
            $this->container->get(StorageInterface::class),
            $mockIngest,
            $this->container,
        );

        $this->resolver = new RecorderResolver($this->container, $this->rhythm);
    }

    /**
     * Test resolve with container registration
     *
     * @return void
     */
    public function testResolveWithContainerRegistration(): void
    {
        $mockRecorder = $this->createStub(RecorderInterface::class);

        $this->container->addShared(TestRecorder::class, fn(): Stub => $mockRecorder);

        $result = $this->resolver->resolve(TestRecorder::class);

        $this->assertSame($mockRecorder, $result);
    }

    /**
     * Test resolve with auto-injection
     *
     * @return void
     */
    public function testResolveWithAutoInjection(): void
    {
        $result = $this->resolver->resolve(TestRecorder::class);

        $this->assertInstanceOf(TestRecorder::class, $result);
    }

    /**
     * Test resolve with non-existent class
     *
     * @return void
     */
    public function testResolveWithNonExistentClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Recorder class `NonExistentRecorder` does not exist.');

        $this->resolver->resolve('NonExistentRecorder');
    }

    /**
     * Test resolve with invalid interface
     *
     * @return void
     */
    public function testResolveWithInvalidInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Recorder class `Crustum\Rhythm\Test\TestCase\Recorder\InvalidRecorder` must implement RecorderInterface.');

        $this->resolver->resolve(InvalidRecorder::class);
    }

    /**
     * Test resolve with container returning invalid type
     *
     * @return void
     */
    public function testResolveWithContainerReturningInvalidType(): void
    {
        $invalidRecorder = new stdClass();

        $this->container->addShared('TestRecorder', fn(): stdClass => $invalidRecorder);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Recorder `TestRecorder` from container does not implement RecorderInterface.');

        $this->resolver->resolve('TestRecorder');
    }
}

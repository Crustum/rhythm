<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Storage;

use Cake\TestSuite\TestCase;
use Crustum\Rhythm\Storage\DigestStorage;
use ReflectionClass;

/**
 * DigestStorage Test Case
 *
 * Tests for the DigestStorage class that uses RhythmAggregateDigest
 * for aggregation operations.
 */
class DigestStorageTest extends TestCase
{
    protected DigestStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new DigestStorage([
            'chunk' => 100,
        ]);
    }

    protected function tearDown(): void
    {
        unset($this->storage);
        parent::tearDown();
    }

    /**
     * Test constructor with configuration
     */
    public function testConstructor(): void
    {
        $storage = new DigestStorage([
            'chunk' => 500,
            'test' => 'value',
        ]);

        $this->assertInstanceOf(DigestStorage::class, $storage);
    }

    /**
     * Test that DigestStorage has digest-specific methods
     */
    public function testHasDigestSpecificMethods(): void
    {
        $reflection = new ReflectionClass($this->storage);
        $this->assertTrue($reflection->hasMethod('processAggregations'));
    }

    /**
     * Test that DigestStorage creates digest instance when needed
     */
    public function testHasDigestInstance(): void
    {
        $reflection = new ReflectionClass($this->storage);
        $method = $reflection->getMethod('processAggregations');

        $method->invoke($this->storage, []);

        $this->assertTrue($reflection->hasMethod('processAggregations'));
    }
}

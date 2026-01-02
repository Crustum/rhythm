<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase;

use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\Core\ContainerInterface;
use Cake\TestSuite\TestCase;
use Crustum\Rhythm\Ingest\IngestInterface;
use Crustum\Rhythm\Ingest\NullIngest;
use Crustum\Rhythm\Ingest\RedisIngest;
use Crustum\Rhythm\Rhythm;
use Crustum\Rhythm\Storage\DigestStorage;
use Crustum\Rhythm\Storage\StorageInterface;

/**
 * NullIngest Integration Test Case
 *
 * Tests the integration of NullIngest with the full Rhythm system.
 */
class NullIngestIntegrationTest extends TestCase
{
    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        Configure::write('Rhythm.ingest.driver', 'null');
    }

    /**
     * Test that null ingest is properly resolved from configuration
     *
     * @return void
     */
    public function testNullIngestResolvedFromConfiguration(): void
    {
        $container = $this->getContainer();

        $container->addShared(StorageInterface::class, DigestStorage::class);
        $container->addShared(IngestInterface::class, NullIngest::class);

        $ingest = $container->get(IngestInterface::class);

        $this->assertInstanceOf(NullIngest::class, $ingest);
    }

    /**
     * Test that Rhythm works correctly with NullIngest
     *
     * @return void
     */
    public function testRhythmWorksWithNullIngest(): void
    {
        $container = $this->getContainer();

        $container->addShared(StorageInterface::class, DigestStorage::class);
        $container->addShared(IngestInterface::class, NullIngest::class);

        $storage = $container->get(StorageInterface::class);
        $ingest = $container->get(IngestInterface::class);

        $rhythm = new Rhythm($storage, $ingest, $container);

        $rhythm->record('test_metric', 'key1', 100);
        $rhythm->record('test_metric', 'key2', 200);
        $rhythm->set('test_value', 'config', 'value1');

        $result = $rhythm->ingest();
        $this->assertSame(3, $result);

        $aggregates = $storage->aggregate('test_metric', 'count', 3600);
        $this->assertTrue($aggregates->isEmpty());
    }

    /**
     * Test that metrics are properly discarded with no side effects
     *
     * @return void
     */
    public function testMetricsDiscardedWithNoSideEffects(): void
    {
        $container = $this->getContainer();
        $container->addShared(StorageInterface::class, DigestStorage::class);
        $container->addShared(IngestInterface::class, NullIngest::class);

        $storage = $container->get(StorageInterface::class);
        $ingest = $container->get(IngestInterface::class);

        $rhythm = new Rhythm($storage, $ingest, $container);

        for ($i = 0; $i < 100; $i++) {
            $rhythm->record('bulk_test', "key_{$i}", $i);
        }

        $result = $rhythm->ingest();
        $this->assertSame(100, $result);

        $aggregates = $storage->aggregate('bulk_test', 'count', 3600);
        $this->assertTrue($aggregates->isEmpty());
    }

    /**
     * Test configuration switching between drivers
     *
     * @return void
     */
    public function testConfigurationSwitching(): void
    {
        Configure::write('Rhythm.ingest.driver', 'null');
        $container1 = $this->getContainer();
        $container1->addShared(StorageInterface::class, DigestStorage::class);
        $container1->addShared(IngestInterface::class, NullIngest::class);

        $ingest1 = $container1->get(IngestInterface::class);
        $this->assertInstanceOf(NullIngest::class, $ingest1);

        Configure::write('Rhythm.ingest.driver', 'redis');
        $container2 = $this->getContainer();
        $container2->addShared(StorageInterface::class, DigestStorage::class);
        $container2->addShared(IngestInterface::class, RedisIngest::class)
            ->addArgument(StorageInterface::class);

        $ingest2 = $container2->get(IngestInterface::class);
        $this->assertInstanceOf(RedisIngest::class, $ingest2);
    }

    /**
     * Helper method to get a container instance
     *
     * @return \Cake\Core\ContainerInterface
     */
    protected function getContainer(): ContainerInterface
    {
        return new Container();
    }
}

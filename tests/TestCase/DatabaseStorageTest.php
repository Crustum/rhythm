<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase;

use Cake\Collection\Collection;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Rhythm\RhythmEntry;
use Rhythm\RhythmValue;
use Rhythm\Storage\DigestStorage;

/**
 * DigestStorage Test Case
 */
class DatabaseStorageTest extends TestCase
{
    /**
     * Storage instance.
     *
     * @var \Rhythm\Storage\DigestStorage
     */
    protected DigestStorage $storage;

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->storage = new DigestStorage();
        $this->storage->purge();
    }

    /**
     * Test storing rhythm entries
     *
     * @return void
     */
    public function testStoreRhythmEntries(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $entries = new Collection([
            new RhythmEntry($timestamp, 'request', 'user:123', 100),
            new RhythmEntry($timestamp, 'request', 'user:456', 200),
        ]);

        $this->storage->store($entries);

        $entriesTable = $this->getTableLocator()->get('Rhythm.RhythmEntries');
        $count = $entriesTable->find()->count();

        $this->assertEquals(2, $count);
    }

    /**
     * Test storing rhythm values
     *
     * @return void
     */
    public function testStoreRhythmValues(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $values = new Collection([
            new RhythmValue($timestamp, 'user', 'active:123', 'John Doe'),
            new RhythmValue($timestamp, 'user', 'active:456', 'Jane Smith'),
        ]);

        $this->storage->store($values);

        $valuesTable = $this->getTableLocator()->get('Rhythm.RhythmValues');
        $count = $valuesTable->find()->count();

        $this->assertEquals(2, $count);
    }

    /**
     * Test storing mixed entries and values
     *
     * @return void
     */
    public function testStoreMixedData(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $items = new Collection([
            new RhythmEntry($timestamp, 'request', 'user:123', 100),
            new RhythmValue($timestamp, 'user', 'active:123', 'John Doe'),
        ]);

        $this->storage->store($items);

        $entriesTable = $this->getTableLocator()->get('Rhythm.RhythmEntries');
        $valuesTable = $this->getTableLocator()->get('Rhythm.RhythmValues');

        $this->assertEquals(1, $entriesTable->find()->count());
        $this->assertEquals(1, $valuesTable->find()->count());
    }

    /**
     * Test aggregation functionality
     *
     * @return void
     */
    public function testAggregation(): void
    {
        $timestamp = (new DateTime())->getTimestamp();
        $entries = new Collection([
            new RhythmEntry($timestamp, 'request', 'test', 100),
            new RhythmEntry($timestamp, 'request', 'test', 200),
        ]);

        $this->storage->store($entries);

        $result = $this->storage->aggregate('request', 'count', 60);

        $this->assertInstanceOf(Collection::class, $result);
    }
}

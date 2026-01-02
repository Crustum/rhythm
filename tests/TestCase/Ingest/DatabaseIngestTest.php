<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Ingest;

use Cake\Core\Container;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Crustum\Rhythm\Ingest\TransparentIngest;
use Crustum\Rhythm\Model\Table\RhythmAggregatesTable;
use Crustum\Rhythm\Model\Table\RhythmEntriesTable;
use Crustum\Rhythm\Model\Table\RhythmValuesTable;
use Crustum\Rhythm\Rhythm;
use Crustum\Rhythm\Storage\DigestStorage;

/**
 * Ingest test
 */
class DatabaseIngestTest extends TestCase
{
    protected Rhythm $rhythm;
    protected TransparentIngest $ingest;
    protected DigestStorage $storage;
    protected RhythmEntriesTable $Entries;
    protected RhythmAggregatesTable $Aggregates;
    protected RhythmValuesTable $Values;

    public function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        $this->storage = new DigestStorage();
        $this->ingest = new TransparentIngest($this->storage);
        $this->rhythm = new Rhythm($this->storage, $this->ingest, $container);
        $this->Entries = $this->getTableLocator()->get('Crustum/Rhythm.RhythmEntries');
        $this->Aggregates = $this->getTableLocator()->get('Crustum/Rhythm.RhythmAggregates');
        $this->Values = $this->getTableLocator()->get('Crustum/Rhythm.RhythmValues');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->ingest->clear();
        $this->storage->purge();
    }

    public function testTrimsValuesAtOrPastExpiry(): void
    {
        DateTime::setTestNow('2000-01-01 00:00:04');
        $this->rhythm->set('type', 'foo', 'value');
        DateTime::setTestNow('2000-01-01 00:00:05');
        $this->rhythm->set('type', 'bar', 'value');
        DateTime::setTestNow('2000-01-01 00:00:06');
        $this->rhythm->set('type', 'baz', 'value');

        $this->rhythm->ingest();

        $this->rhythm->stopRecording();
        DateTime::setTestNow('2000-01-08 00:00:05');
        $this->rhythm->trim();

        $this->assertEquals(['baz'], $this->Values->find()->all()->extract('key')->toArray());
    }

    public function testTrimsEntriesAtOrAfterWeekAfterTimestamp(): void
    {
        DateTime::setTestNow('2000-01-01 00:00:04');
        $this->rhythm->record('foo', 'xxxx', 1);
        DateTime::setTestNow('2000-01-01 00:00:05');
        $this->rhythm->record('bar', 'xxxx', 1);
        DateTime::setTestNow('2000-01-01 00:00:06');
        $this->rhythm->record('baz', 'xxxx', 1);
        $this->rhythm->ingest();

        $this->rhythm->stopRecording();
        DateTime::setTestNow('2000-01-08 00:00:05');
        $this->rhythm->trim();

        $this->assertEquals(['baz'], $this->Entries->find()->all()->extract('type')->toArray());
    }

    public function testTrimsAggregatesOnceThe1HourBucketIsNoLongerRelevant(): void
    {
        DateTime::setTestNow('2000-01-01 00:00:59'); // Bucket: 2000-01-01 00:00:00
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->ingest();
        $this->assertEquals(1, $this->Aggregates->find()->where(['period' => 60])->count());

        DateTime::setTestNow('2000-01-01 00:01:00'); // Bucket: 2000-01-01 00:01:00
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->ingest();
        $this->assertEquals(2, $this->Aggregates->find()->where(['period' => 60])->count());

        $this->rhythm->stopRecording();
        DateTime::setTestNow('2000-01-01 00:59:59'); // 1 second before the oldest bucket become irrelevant.
        $this->rhythm->trim();
        $this->assertEquals(2, $this->Aggregates->find()->where(['period' => 60])->count());

        DateTime::setTestNow('2000-01-01 01:00:00'); // The second the oldest bucket become irrelevant.
        $this->rhythm->trim();
        $this->assertEquals(1, $this->Aggregates->find()->where(['period' => 60])->count());
    }

    public function testTrimsAggregatesOnceThe6HourBucketIsNoLongerRelevant(): void
    {
        DateTime::setTestNow('2000-01-01 00:05:59'); // Bucket: 2000-01-01 00:00:00
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->ingest();
        $this->assertEquals(1, $this->Aggregates->find()->where(['period' => 360])->count());

        DateTime::setTestNow('2000-01-01 00:06:00'); // Bucket: 2000-01-01 00:06:00
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->ingest();
        $this->assertEquals(2, $this->Aggregates->find()->where(['period' => 360])->count());

        $this->rhythm->stopRecording();
        DateTime::setTestNow('2000-01-01 05:59:59'); // 1 second before the oldest bucket become irrelevant.
        $this->rhythm->trim();
        $this->assertEquals(2, $this->Aggregates->find()->where(['period' => 360])->count());

        DateTime::setTestNow('2000-01-01 06:00:00'); // The second the oldest bucket become irrelevant.
        $this->rhythm->trim();
        $this->assertEquals(1, $this->Aggregates->find()->where(['period' => 360])->count());
    }

    public function testTrimsAggregatesOnceThe24HourBucketIsNoLongerRelevant(): void
    {
        DateTime::setTestNow('2000-01-01 00:23:59'); // Bucket: 2000-01-01 00:00:00
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->ingest();
        $this->assertEquals(1, $this->Aggregates->find()->where(['period' => 1440])->count());

        DateTime::setTestNow('2000-01-01 00:24:00'); // Bucket: 2000-01-01 00:24:00
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->ingest();
        $this->assertEquals(2, $this->Aggregates->find()->where(['period' => 1440])->count());

        $this->rhythm->stopRecording();
        DateTime::setTestNow('2000-01-01 23:35:59'); // 1 second before the oldest bucket become irrelevant.
        $this->rhythm->trim();
        $this->assertEquals(2, $this->Aggregates->find()->where(['period' => 1440])->count());

        DateTime::setTestNow('2000-01-02 00:00:00'); // The second the oldest bucket become irrelevant.
        $this->rhythm->trim();
        $this->assertEquals(1, $this->Aggregates->find()->where(['period' => 1440])->count());
    }

    public function testTrimsAggregatesToTheConfiguredStorageDurationWhenConfiguredTrimIsShorterThanTheBucketPeriodDuration(): void
    {
        $this->storage = new DigestStorage([
            'trim' => [
                'keep' => '23 minutes',
            ],
        ]);
        $this->ingest = new TransparentIngest($this->storage);
        $this->rhythm = new Rhythm($this->storage, $this->ingest, new Container());

        DateTime::setTestNow('2000-01-01 00:00:00'); // Bucket: 2000-01-01 00:00:00
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->ingest();
        $this->assertEquals(1, $this->Aggregates->find()->where(['period' => 1440])->count());

        DateTime::setTestNow('2000-01-01 00:22:59');
        $this->rhythm->trim();
        $this->assertEquals(1, $this->Aggregates->find()->where(['period' => 1440])->count());

        DateTime::setTestNow('2000-01-01 00:23:00');
        $this->rhythm->trim();
        $this->assertEquals(0, $this->Aggregates->find()->where(['period' => 1440])->count());
    }

    public function testTrimsAggregatesOnceThe7DayBucketIsNoLongerRelevant(): void
    {
        DateTime::setTestNow('2000-01-01 02:23:59'); // Bucket: 1999-12-31 23:36:00
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->ingest();
        $this->assertEquals(1, $this->Aggregates->find()->where(['period' => 10080])->count());

        DateTime::setTestNow('2000-01-01 02:24:00'); // Bucket: 2000-01-01 02:24:00
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->ingest();
        $this->assertEquals(2, $this->Aggregates->find()->where(['period' => 10080])->count());

        $this->rhythm->stopRecording();
        DateTime::setTestNow('2000-01-07 23:35:59'); // 1 second before the oldest bucket become irrelevant.
        $this->rhythm->trim();
        $this->assertEquals(2, $this->Aggregates->find()->where(['period' => 10080])->count());

        DateTime::setTestNow('2000-01-07 23:36:00'); // The second the oldest bucket become irrelevant.
        $this->rhythm->trim();
        $this->assertEquals(1, $this->Aggregates->find()->where(['period' => 10080])->count());
    }

    public function testCanConfigureDaysOfDataToKeepWhenTrimming(): void
    {
        $this->storage = new DigestStorage([
            'trim' => [
                'keep' => '2 days',
            ],
        ]);
        $this->ingest = new TransparentIngest($this->storage);
        $this->rhythm = new Rhythm($this->storage, $this->ingest, new Container());

        DateTime::setTestNow('2000-01-01 00:00:04');
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->set('type', 'foo', 'value');
        DateTime::setTestNow('2000-01-01 00:00:05');
        $this->rhythm->record('bar', 'xxxx', 1);
        $this->rhythm->set('type', 'bar', 'value');
        DateTime::setTestNow('2000-01-01 00:00:06');
        $this->rhythm->record('baz', 'xxxx', 1);
        $this->rhythm->set('type', 'baz', 'value');
        $this->rhythm->ingest();

        $this->rhythm->stopRecording();
        DateTime::setTestNow('2000-01-03 00:00:05');
        $this->rhythm->trim();

        $this->assertEquals(['baz'], $this->Entries->find()->all()->extract('type')->toArray());
        $this->assertEquals(['baz'], $this->Values->find()->all()->extract('key')->toArray());
    }

    public function testRestrictsTrimDurationTo7Days(): void
    {
        $this->storage = new DigestStorage([
            'trim' => [
                'keep' => '7 days',
            ],
        ]);
        $this->ingest = new TransparentIngest($this->storage);
        $this->rhythm = new Rhythm($this->storage, $this->ingest, new Container());

        DateTime::setTestNow('2000-01-01 00:00:04');
        $this->rhythm->record('foo', 'xxxx', 1);
        $this->rhythm->set('type', 'foo', 'value');
        DateTime::setTestNow('2000-01-01 00:00:05');
        $this->rhythm->record('bar', 'xxxx', 1);
        $this->rhythm->set('type', 'bar', 'value');
        DateTime::setTestNow('2000-01-01 00:00:06');
        $this->rhythm->record('baz', 'xxxx', 1);
        $this->rhythm->set('type', 'baz', 'value');
        $this->rhythm->ingest();

        $this->rhythm->stopRecording();
        DateTime::setTestNow('2000-01-08 00:00:05');
        $this->rhythm->trim();

        $this->assertEquals(['baz'], $this->Entries->find()->all()->extract('type')->toArray());
        $this->assertEquals(['baz'], $this->Values->find()->all()->extract('key')->toArray());
    }
}

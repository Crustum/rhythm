<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Storage;

use Cake\Collection\Collection;
use Cake\Core\Container;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Rhythm\Ingest\TransparentIngest;
use Rhythm\Model\Table\RhythmAggregatesTable;
use Rhythm\Model\Table\RhythmEntriesTable;
use Rhythm\Model\Table\RhythmValuesTable;
use Rhythm\Rhythm;
use Rhythm\Storage\DigestStorage;

/**
 * Aggregation logic test
 */
class DatabaseStorageFeatureTest extends TestCase
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
        $this->Entries = $this->getTableLocator()->get('Rhythm.RhythmEntries');
        $this->Aggregates = $this->getTableLocator()->get('Rhythm.RhythmAggregates');
        $this->Values = $this->getTableLocator()->get('Rhythm.RhythmValues');
        $this->ingest->clear();
        $this->storage->purge();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->ingest->clear();
        $this->storage->purge();
    }

    /**
     * Test aggregation logic for entries and aggregates (full scenario)
     *
     * @return void
     */
    public function testAggregation(): void
    {
        $this->rhythm->record('type', 'key1', 200)->count()->min()->max()->sum()->avg();
        $this->rhythm->record('type', 'key1', 100)->count()->min()->max()->sum()->avg();
        $this->rhythm->record('type', 'key2', 400)->count()->min()->max()->sum()->avg();
        $this->rhythm->ingest();

        $entries = $this->rhythm->ignore(fn() => $this->Entries->find()->orderBy(['id' => 'ASC'])->all());
        $this->assertCount(3, $entries);
        $this->assertEquals(['type' => 'type', 'key' => 'key1', 'value' => 200], $entries->first()->extract(['type', 'key', 'value']));
        $this->assertEquals(['type' => 'type', 'key' => 'key1', 'value' => 100], $entries->skip(1)->first()->extract(['type', 'key', 'value']));
        $this->assertEquals(['type' => 'type', 'key' => 'key2', 'value' => 400], $entries->skip(2)->first()->extract(['type', 'key', 'value']));

        $aggregates = $this->rhythm->ignore(fn() => $this->Aggregates->find()->orderBy(['period' => 'ASC', 'aggregate' => 'ASC', 'key' => 'ASC'])->all());
        $this->assertCount(40, $aggregates);
        $agg = fn($i) => $aggregates->skip($i)->first();
        // period 60
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 150], $agg(0)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400], $agg(1)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'count', 'key' => 'key1', 'value' => 2], $agg(2)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1], $agg(3)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'max', 'key' => 'key1', 'value' => 200], $agg(4)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400], $agg(5)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100], $agg(6)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400], $agg(7)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 300], $agg(8)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400], $agg(9)->extract(['type','period','aggregate','key','value']));
        // period 360
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 150], $agg(10)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400], $agg(11)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'count', 'key' => 'key1', 'value' => 2], $agg(12)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1], $agg(13)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'max', 'key' => 'key1', 'value' => 200], $agg(14)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400], $agg(15)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100], $agg(16)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400], $agg(17)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 300], $agg(18)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400], $agg(19)->extract(['type','period','aggregate','key','value']));
        // period 1440
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 150], $agg(20)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400], $agg(21)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'count', 'key' => 'key1', 'value' => 2], $agg(22)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1], $agg(23)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'max', 'key' => 'key1', 'value' => 200], $agg(24)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400], $agg(25)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100], $agg(26)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400], $agg(27)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 300], $agg(28)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400], $agg(29)->extract(['type','period','aggregate','key','value']));
        // period 10080
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 150], $agg(30)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400], $agg(31)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'count', 'key' => 'key1', 'value' => 2], $agg(32)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1], $agg(33)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'max', 'key' => 'key1', 'value' => 200], $agg(34)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400], $agg(35)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100], $agg(36)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400], $agg(37)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 300], $agg(38)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400], $agg(39)->extract(['type','period','aggregate','key','value']));

        $this->rhythm->record('type', 'key1', 600)->count()->min()->max()->sum()->avg();
        $this->rhythm->ingest();

        $entries = $this->rhythm->ignore(fn() => $this->Entries->find()->orderBy(['id' => 'ASC'])->all());
        $this->assertCount(4, $entries);
        $this->assertEquals(['type' => 'type', 'key' => 'key1', 'value' => 200], $entries->first()->extract(['type', 'key', 'value']));
        $this->assertEquals(['type' => 'type', 'key' => 'key1', 'value' => 100], $entries->skip(1)->first()->extract(['type', 'key', 'value']));
        $this->assertEquals(['type' => 'type', 'key' => 'key2', 'value' => 400], $entries->skip(2)->first()->extract(['type', 'key', 'value']));
        $this->assertEquals(['type' => 'type', 'key' => 'key1', 'value' => 600], $entries->skip(3)->first()->extract(['type', 'key', 'value']));

        $aggregates = $this->rhythm->ignore(fn() => $this->Aggregates->find()->orderBy(['period' => 'ASC', 'aggregate' => 'ASC', 'key' => 'ASC'])->all());
        $agg = fn($i) => $aggregates->skip($i)->first();

        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 300], $agg(0)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400], $agg(1)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'count', 'key' => 'key1', 'value' => 3], $agg(2)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1], $agg(3)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'max', 'key' => 'key1', 'value' => 600], $agg(4)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400], $agg(5)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100], $agg(6)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400], $agg(7)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 900], $agg(8)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 60, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400], $agg(9)->extract(['type','period','aggregate','key','value']));

        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 300], $agg(10)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400], $agg(11)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'count', 'key' => 'key1', 'value' => 3], $agg(12)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1], $agg(13)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'max', 'key' => 'key1', 'value' => 600], $agg(14)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400], $agg(15)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100], $agg(16)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400], $agg(17)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 900], $agg(18)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 360, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400], $agg(19)->extract(['type','period','aggregate','key','value']));

        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 300], $agg(20)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400], $agg(21)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'count', 'key' => 'key1', 'value' => 3], $agg(22)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1], $agg(23)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'max', 'key' => 'key1', 'value' => 600], $agg(24)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400], $agg(25)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100], $agg(26)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400], $agg(27)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 900], $agg(28)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 1440, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400], $agg(29)->extract(['type','period','aggregate','key','value']));

        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 300], $agg(30)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400], $agg(31)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'count', 'key' => 'key1', 'value' => 3], $agg(32)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1], $agg(33)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'max', 'key' => 'key1', 'value' => 600], $agg(34)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400], $agg(35)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100], $agg(36)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400], $agg(37)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 900], $agg(38)->extract(['type','period','aggregate','key','value']));
        $this->assertEquals(['type' => 'type', 'period' => 10080, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400], $agg(39)->extract(['type','period','aggregate','key','value']));
    }

    /**
     * Test combining duplicate count aggregates before upserting
     *
     * @return void
     */
    public function testCombinesDuplicateCountAggregates(): void
    {
        $this->rhythm->record('type', 'key1')->count();
        $this->rhythm->record('type', 'key1')->count();
        $this->rhythm->record('type', 'key1')->count();
        $this->rhythm->record('type', 'key2')->count();
        $this->rhythm->ingest();

        $entries = $this->rhythm->ignore(fn() => $this->Entries->find()->orderBy(['id' => 'ASC'])->all());
        $this->assertCount(4, $entries);
        $this->assertEquals(['type' => 'type', 'key' => 'key1'], $entries->first()->extract(['type', 'key']));
        $this->assertEquals(['type' => 'type', 'key' => 'key2'], $entries->last()->extract(['type', 'key']));

        $aggregates = $this->rhythm->ignore(fn() => $this->Aggregates->find()->where(['period' => 60])->orderBy(['key' => 'ASC'])->all());
        $this->assertEquals(3, $aggregates->first()->value);
        $this->assertEquals(1, $aggregates->last()->value);
    }

    /**
     * Test combining duplicate min aggregates before upserting
     *
     * @return void
     */
    public function testCombinesDuplicateMinAggregates(): void
    {
        $this->rhythm->record('type', 'key1', 200)->min();
        $this->rhythm->record('type', 'key1', 100)->min();
        $this->rhythm->record('type', 'key1', 300)->min();
        $this->rhythm->record('type', 'key2', 100)->min();
        $this->rhythm->ingest();

        $entries = $this->rhythm->ignore(fn() => $this->Entries->find()->orderBy(['id' => 'ASC'])->all());
        $this->assertCount(4, $entries);
        $this->assertEquals(['type' => 'type', 'key' => 'key1'], $entries->first()->extract(['type', 'key']));
        $this->assertEquals(['type' => 'type', 'key' => 'key2'], $entries->last()->extract(['type', 'key']));

        $aggregates = $this->rhythm->ignore(fn() => $this->Aggregates->find()->where(['period' => 60])->orderBy(['key' => 'ASC'])->all());
        $this->assertEquals(100, $aggregates->first()->value);
        $this->assertEquals(100, $aggregates->last()->value);
    }

    /**
     * Test combining duplicate max aggregates before upserting
     *
     * @return void
     */
    public function testCombinesDuplicateMaxAggregates(): void
    {
        $this->rhythm->record('type', 'key1', 100)->max();
        $this->rhythm->record('type', 'key1', 300)->max();
        $this->rhythm->record('type', 'key1', 200)->max();
        $this->rhythm->record('type', 'key2', 100)->max();
        $this->rhythm->ingest();

        $entries = $this->rhythm->ignore(fn() => $this->Entries->find()->orderBy(['id' => 'ASC'])->all());
        $this->assertCount(4, $entries);
        $this->assertEquals(['type' => 'type', 'key' => 'key1'], $entries->first()->extract(['type', 'key']));
        $this->assertEquals(['type' => 'type', 'key' => 'key2'], $entries->last()->extract(['type', 'key']));

        $aggregates = $this->rhythm->ignore(fn() => $this->Aggregates->find()->where(['period' => 60])->orderBy(['key' => 'ASC'])->all());
        $this->assertEquals(300, $aggregates->first()->value);
        $this->assertEquals(100, $aggregates->last()->value);
    }

    /**
     * Test combining duplicate sum aggregates before upserting
     *
     * @return void
     */
    public function testCombinesDuplicateSumAggregates(): void
    {
        $this->rhythm->record('type', 'key1', 100)->sum();
        $this->rhythm->record('type', 'key1', 300)->sum();
        $this->rhythm->record('type', 'key1', 200)->sum();
        $this->rhythm->record('type', 'key2', 100)->sum();
        $this->rhythm->ingest();

        $entries = $this->rhythm->ignore(fn() => $this->Entries->find()->orderBy(['id' => 'ASC'])->all());
        $this->assertCount(4, $entries);
        $this->assertEquals(['type' => 'type', 'key' => 'key1'], $entries->first()->extract(['type', 'key']));
        $this->assertEquals(['type' => 'type', 'key' => 'key2'], $entries->last()->extract(['type', 'key']));

        $aggregates = $this->rhythm->ignore(fn() => $this->Aggregates->find()->where(['period' => 60])->orderBy(['key' => 'ASC'])->all());
        $this->assertEquals(600, $aggregates->first()->value);
        $this->assertEquals(100, $aggregates->last()->value);
    }

    /**
     * Test combining duplicate average aggregates before upserting
     *
     * @return void
     */
    public function testCombinesDuplicateAvgAggregates(): void
    {
        $this->rhythm->record('type', 'key1', 100)->avg();
        $this->rhythm->record('type', 'key1', 300)->avg();
        $this->rhythm->record('type', 'key1', 200)->avg();
        $this->rhythm->record('type', 'key2', 100)->avg();
        $this->rhythm->ingest();

        $entries = $this->rhythm->ignore(fn() => $this->Entries->find()->orderBy(['id' => 'ASC'])->all());
        $this->assertCount(4, $entries);
        $this->assertEquals(['type' => 'type', 'key' => 'key1'], $entries->first()->extract(['type', 'key']));
        $this->assertEquals(['type' => 'type', 'key' => 'key2'], $entries->last()->extract(['type', 'key']));
        // Aggregate assertion placeholder


        $aggregates = $this->rhythm->ignore(fn() => $this->Aggregates->find()->where(['period' => 60])->orderBy(['key' => 'ASC'])->all());
        $this->assertEquals(200, $aggregates->first()->value);
        $this->assertEquals(100, $aggregates->last()->value);
        $this->assertEquals(3, $aggregates->first()->count);
        $this->assertEquals(1, $aggregates->last()->count);

        $this->rhythm->record('type', 'key1', 400)->avg();
        $this->rhythm->record('type', 'key1', 400)->avg();
        $this->rhythm->record('type', 'key1', 400)->avg();
        $this->rhythm->ingest();
        $aggregate = $this->rhythm->ignore(fn() => $this->Aggregates->find()->where(['period' => 60, 'key' => 'key1'])->first());
        $this->assertEquals(6, $aggregate->count);
        $this->assertEquals(300, $aggregate->value);
    }

    /**
     * Test one aggregate for multiple types, per key
     *
     * @return void
     */
    public function testAggregateTypesCountPerKey(): void
    {
        DateTime::setTestNow('2000-01-01 12:00:00');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'user:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'user:*')->count();

        DateTime::setTestNow('2000-01-01 12:00:01');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'user:*')->count();
        $this->rhythm->record('cache_hit', 'user:*')->count();
        $this->rhythm->record('cache_miss', 'user:*')->count();

        DateTime::setTestNow('2000-01-01 12:59:58');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'user:*')->count();
        $this->rhythm->record('cache_hit', 'user:*')->count();
        $this->rhythm->record('cache_miss', 'user:*')->count();

        $this->rhythm->ingest();

        DateTime::setTestNow('2000-01-01 13:00:00');

        $results = $this->storage->aggregateTypes(['cache_hit', 'cache_miss'], 'count', 60);
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'key' => $row['key'],
                'cache_hit' => $row['cache_hit'],
                'cache_miss' => $row['cache_miss'],
            ];
        }
        $this->assertEquals([
            ['key' => 'flight:*', 'cache_hit' => 8, 'cache_miss' => 6],
            ['key' => 'user:*', 'cache_hit' => 4, 'cache_miss' => 2],
        ], $data);
        DateTime::setTestNow();
    }

    /**
     * Test total aggregate for a single type
     *
     * @return void
     */
    public function testAggregateTotalSingleType(): void
    {
        DateTime::setTestNow('2000-01-01 12:00:00');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();

        DateTime::setTestNow('2000-01-01 12:00:01');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        DateTime::setTestNow('2000-01-01 12:00:02');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        DateTime::setTestNow('2000-01-01 12:00:03');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();

        DateTime::setTestNow('2000-01-01 12:59:00');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        DateTime::setTestNow('2000-01-01 12:59:10');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        DateTime::setTestNow('2000-01-01 12:59:20');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();

        $this->rhythm->ingest();
        DateTime::setTestNow('2000-01-01 13:00:00');

        $total = $this->storage->aggregateTotal('cache_hit', 'count', 60);
        $this->assertEquals(12, $total);
        DateTime::setTestNow();
    }

    /**
     * Test total aggregate for multiple types
     *
     * @return void
     */
    public function testAggregateTotalMultipleTypes(): void
    {
        DateTime::setTestNow('2000-01-01 12:00:00');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();

        DateTime::setTestNow('2000-01-01 12:00:01');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        DateTime::setTestNow('2000-01-01 12:00:02');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        DateTime::setTestNow('2000-01-01 12:00:03');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();

        DateTime::setTestNow('2000-01-01 12:59:00');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        DateTime::setTestNow('2000-01-01 12:59:10');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();
        DateTime::setTestNow('2000-01-01 12:59:20');
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_hit', 'flight:*')->count();
        $this->rhythm->record('cache_miss', 'flight:*')->count();

        $this->rhythm->ingest();
        DateTime::setTestNow('2000-01-01 13:00:00');

        $results = $this->storage->aggregateTotal(['cache_hit', 'cache_miss'], 'count', 60);
        $this->assertInstanceOf(Collection::class, $results);
        $resultsArray = $results->toArray();
        $this->assertEquals([
            'cache_hit' => 12,
            'cache_miss' => 6,
        ], $resultsArray);
        DateTime::setTestNow();
    }

    /**
     * Test collapsing values with the same key into a single upsert
     *
     * @return void
     */
    public function testCollapsesValuesWithSameKey(): void
    {
        $this->rhythm->set('read_counter', 'post:321', '123');
        $this->rhythm->set('read_counter', 'post:321', '234');
        $this->rhythm->set('read_counter', 'post:321', '345');
        $this->rhythm->ingest();

        $values = $this->rhythm->ignore(fn() => $this->Values->find()->all());
        $this->assertCount(1, $values);
        $this->assertEquals('345', $values->first()->value);
    }
}

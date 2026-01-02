<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Widget;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Crustum\Rhythm\Recorder\CacheRecorder;
use Crustum\Rhythm\Test\TestCase\RhythmTestCase;
use Crustum\Rhythm\Widget\CacheWidget;
use ReflectionClass;

/**
 * CacheWidget Test Case
 */
class CacheWidgetTest extends RhythmTestCase
{
    /**
     * Test subject
     *
     * @var \Crustum\Rhythm\Widget\CacheWidget
     */
    protected CacheWidget $widget;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->rhythm->flush();
        Cache::clear('rhythm');
        Cache::clear('default');

        Configure::write('Rhythm.recorders.cache', [
            'className' => CacheRecorder::class,
            'enabled' => env('RHYTHM_CACHE_ENABLED', true),
            'sample_rate' => env('RHYTHM_CACHE_SAMPLE_RATE', 1.0),
            'groups' => [],
            'ignore' => [],
        ]);
        Configure::write('Rhythm.widgets.cache', [
            'className' => CacheWidget::class,
            'name' => 'Cache',
            'cols' => ['default' => 12, 'lg' => 4],
            'refreshInterval' => 60,
        ]);

        $this->widget = new CacheWidget($this->rhythm, []);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->rhythm->flush();
        Cache::clear('rhythm');
        Cache::clear('default');
        unset($this->widget);
        parent::tearDown();
    }

    /**
     * Test getData with cache hits and misses
     *
     * @return void
     */
    public function testGetDataWithHitsAndMisses(): void
    {
        $this->rhythm->record('cache_hit', 'key1', 1)->count()->onlyBuckets();
        $this->rhythm->record('cache_hit', 'key1', 1)->count()->onlyBuckets();
        $this->rhythm->record('cache_hit', 'key2', 1)->count()->onlyBuckets();
        $this->rhythm->record('cache_miss', 'key1', 1)->count()->onlyBuckets();
        $this->rhythm->record('cache_miss', 'key3', 1)->count()->onlyBuckets();

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $data = $this->widget->getData(['period' => 240]);

        $this->assertArrayHasKey('hits', $data);
        $this->assertArrayHasKey('misses', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('hit_rate', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('cacheKeyInteractions', $data);

        $this->assertEquals(3, $data['hits']);
        $this->assertEquals(2, $data['misses']);
        $this->assertEquals(5, $data['total']);
        $this->assertEquals(60.0, $data['hit_rate']);
        $this->assertIsString($data['status']);
    }

    /**
     * Test getData with only hits
     *
     * @return void
     */
    public function testGetDataWithOnlyHits(): void
    {
        $this->rhythm->record('cache_hit', 'key1')->count()->onlyBuckets();
        $this->rhythm->record('cache_hit', 'key2')->count()->onlyBuckets();

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $data = $this->widget->getData(['period' => 240]);

        $this->assertEquals(2, $data['hits']);
        $this->assertEquals(0, $data['misses']);
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(100.0, $data['hit_rate']);
        $this->assertEquals('excellent', $data['status']);
    }

    /**
     * Test getData with only misses
     *
     * @return void
     */
    public function testGetDataWithOnlyMisses(): void
    {
        $this->rhythm->record('cache_miss', 'key1')->count()->onlyBuckets();
        $this->rhythm->record('cache_miss', 'key2')->count()->onlyBuckets();

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $data = $this->widget->getData(['period' => 240]);

        $this->assertEquals(0, $data['hits']);
        $this->assertEquals(2, $data['misses']);
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(0.0, $data['hit_rate']);
        $this->assertEquals('poor', $data['status']);
    }

    /**
     * Test getData with no data
     *
     * @return void
     */
    public function testGetDataWithNoData(): void
    {
        $data = $this->widget->getData(['period' => 240]);

        $this->assertEquals(0, $data['hits']);
        $this->assertEquals(0, $data['misses']);
        $this->assertEquals(0, $data['total']);
        $this->assertEquals(0.0, $data['hit_rate']);
        $this->assertEquals('poor', $data['status']);
        $this->assertIsArray($data['cacheKeyInteractions']);
        $this->assertEmpty($data['cacheKeyInteractions']);
    }

    /**
     * Test getData with cache key interactions
     *
     * @return void
     */
    public function testGetDataWithCacheKeyInteractions(): void
    {
        $this->rhythm->record('cache_hit', 'key1', 1)->count()->onlyBuckets();
        $this->rhythm->record('cache_hit', 'key1', 1)->count()->onlyBuckets();
        $this->rhythm->record('cache_miss', 'key1', 1)->count()->onlyBuckets();
        $this->rhythm->record('cache_hit', 'key2', 1)->count()->onlyBuckets();

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $data = $this->widget->getData(['period' => 240]);

        $this->assertNotEmpty($data['cacheKeyInteractions']);

        $key1Interaction = null;
        foreach ($data['cacheKeyInteractions'] as $interaction) {
            if ($interaction->key === 'key1') {
                $key1Interaction = $interaction;
                break;
            }
        }

        $this->assertNotNull($key1Interaction);
        $this->assertEquals(2, $key1Interaction->hits);
        $this->assertEquals(1, $key1Interaction->misses);
    }

    /**
     * Test getData with ignored patterns
     *
     * @return void
     */
    public function testGetDataWithIgnoredPatterns(): void
    {
        $this->rhythm->record('cache_hit', 'key1', 1)->count()->onlyBuckets();
        $this->rhythm->record('cache_hit', 'test_key', 1)->count()->onlyBuckets();
        $this->rhythm->record('cache_miss', 'test_key', 1)->count()->onlyBuckets();

        $this->rhythm->ingest();
        $this->rhythm->digest();

        Configure::write('Rhythm.recorders.cache.ignore', ['#^test_#']);

        $widget = new CacheWidget($this->rhythm, []);

        $data = $widget->getData(['period' => 240]);

        $this->assertEquals(2, $data['hits'], 'Should have 2 hits (key1 + test_key, ignore only filters display)');
        $this->assertEquals(1, $data['misses'], 'Should have 1 miss (test_key, ignore only filters display)');

        $hasTestKey = false;
        foreach ($data['cacheKeyInteractions'] as $interaction) {
            if (str_starts_with($interaction->key, 'test_')) {
                $hasTestKey = true;
                break;
            }
        }

        $this->assertFalse($hasTestKey, 'Ignored keys should not appear in interactions');
    }

    /**
     * Test getData with different periods
     *
     * @return void
     */
    public function testGetDataWithDifferentPeriods(): void
    {
        $oneHourAgo = (new DateTime())->getTimestamp() - 3600;

        $this->rhythm->record('cache_hit', 'key1', null, $oneHourAgo)->count()->onlyBuckets();
        $this->rhythm->record('cache_hit', 'key2')->count()->onlyBuckets();

        $this->rhythm->ingest();
        $this->rhythm->digest();

        $data60 = $this->widget->getData(['period' => 60]);
        $this->assertEquals(1, $data60['hits'], '60 minute period should only include recent key2');

        $data240 = $this->widget->getData(['period' => 240]);
        $this->assertEquals(2, $data240['hits'], '240 minute period should include both keys');
    }

    /**
     * Test getTemplate
     *
     * @return void
     */
    public function testGetTemplate(): void
    {
        $template = $this->widget->getTemplate();

        $this->assertEquals('Crustum/Rhythm.widgets/cache', $template);
    }

    /**
     * Test getRefreshInterval
     *
     * @return void
     */
    public function testGetRefreshInterval(): void
    {
        $interval = $this->widget->getRefreshInterval();

        $this->assertEquals(60, $interval);
    }

    /**
     * Test getRefreshInterval with custom config
     *
     * @return void
     */
    public function testGetRefreshIntervalWithCustomConfig(): void
    {
        $widget = new CacheWidget($this->rhythm, [
            'refreshInterval' => 30,
        ]);

        $interval = $widget->getRefreshInterval();

        $this->assertEquals(30, $interval);
    }

    /**
     * Test getCacheStatus with different hit rates
     *
     * @return void
     */
    public function testGetCacheStatus(): void
    {
        $reflection = new ReflectionClass($this->widget);
        $method = $reflection->getMethod('getCacheStatus');
        $method->setAccessible(true);

        $this->assertEquals('excellent', $method->invoke($this->widget, 95.0));
        $this->assertEquals('excellent', $method->invoke($this->widget, 90.0));
        $this->assertEquals('good', $method->invoke($this->widget, 85.0));
        $this->assertEquals('good', $method->invoke($this->widget, 70.0));
        $this->assertEquals('fair', $method->invoke($this->widget, 60.0));
        $this->assertEquals('fair', $method->invoke($this->widget, 50.0));
        $this->assertEquals('poor', $method->invoke($this->widget, 40.0));
        $this->assertEquals('poor', $method->invoke($this->widget, 0.0));
    }
}

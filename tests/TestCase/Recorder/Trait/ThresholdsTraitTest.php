<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Recorder\Trait;

use Cake\TestSuite\TestCase;

/**
 * ThresholdsTrait Test Case
 */
class ThresholdsTraitTest extends TestCase
{
    /**
     * Test basic threshold with simple value
     *
     * @return void
     */
    public function testBasicThreshold(): void
    {
        $recorder = new TestThresholdsRecorder(['threshold' => 1000]);

        $this->assertEquals(1000, $recorder->threshold('any_key'));
        $this->assertTrue($recorder->underThreshold(500, 'any_key'));
        $this->assertFalse($recorder->underThreshold(1500, 'any_key'));
    }

    /**
     * Test threshold with pattern matching
     *
     * @return void
     */
    public function testPatternBasedThreshold(): void
    {
        $config = [
            'threshold' => [
                'default' => 1000,
                '#^/api/#' => 500,
                '#^/admin/#' => 2000,
                '#^/rhythm/#' => 3000,
            ],
        ];

        $recorder = new TestThresholdsRecorder($config);

        $this->assertEquals(1000, $recorder->threshold('/some/other/path'));
        $this->assertTrue($recorder->underThreshold(800, '/some/other/path'));
        $this->assertFalse($recorder->underThreshold(1200, '/some/other/path'));

        $this->assertEquals(500, $recorder->threshold('/api/users'));
        $this->assertTrue($recorder->underThreshold(300, '/api/users'));
        $this->assertFalse($recorder->underThreshold(600, '/api/users'));

        $this->assertEquals(2000, $recorder->threshold('/admin/dashboard'));
        $this->assertTrue($recorder->underThreshold(1500, '/admin/dashboard'));
        $this->assertFalse($recorder->underThreshold(2500, '/admin/dashboard'));

        $this->assertEquals(3000, $recorder->threshold('/rhythm/metrics'));
        $this->assertTrue($recorder->underThreshold(2500, '/rhythm/metrics'));
        $this->assertFalse($recorder->underThreshold(3500, '/rhythm/metrics'));
    }

    /**
     * Test threshold with SQL pattern matching
     *
     * @return void
     */
    public function testSqlPatternBasedThreshold(): void
    {
        $config = [
            'threshold' => [
                'default' => 1000,
                '#SELECT.*FROM users#' => 500,
                '#UPDATE.*SET#' => 2000,
                '#DELETE FROM#' => 1500,
                '#INSERT INTO#' => 1200,
            ],
        ];

        $recorder = new TestThresholdsRecorder($config);

        $this->assertEquals(1000, $recorder->threshold('SELECT * FROM other_table'));
        $this->assertTrue($recorder->underThreshold(800, 'SELECT * FROM other_table'));
        $this->assertFalse($recorder->underThreshold(1200, 'SELECT * FROM other_table'));

        $this->assertEquals(500, $recorder->threshold('SELECT * FROM users WHERE id = 1'));
        $this->assertTrue($recorder->underThreshold(300, 'SELECT * FROM users WHERE id = 1'));
        $this->assertFalse($recorder->underThreshold(600, 'SELECT * FROM users WHERE id = 1'));

        $this->assertEquals(2000, $recorder->threshold('UPDATE users SET name = "test" WHERE id = 1'));
        $this->assertTrue($recorder->underThreshold(1500, 'UPDATE users SET name = "test" WHERE id = 1'));
        $this->assertFalse($recorder->underThreshold(2500, 'UPDATE users SET name = "test" WHERE id = 1'));

        $this->assertEquals(1500, $recorder->threshold('DELETE FROM users WHERE id = 1'));
        $this->assertTrue($recorder->underThreshold(1200, 'DELETE FROM users WHERE id = 1'));
        $this->assertFalse($recorder->underThreshold(1800, 'DELETE FROM users WHERE id = 1'));

        $this->assertEquals(1200, $recorder->threshold('INSERT INTO users (name) VALUES ("test")'));
        $this->assertTrue($recorder->underThreshold(1000, 'INSERT INTO users (name) VALUES ("test")'));
        $this->assertFalse($recorder->underThreshold(1500, 'INSERT INTO users (name) VALUES ("test")'));
    }

    /**
     * Test threshold with job pattern matching
     *
     * @return void
     */
    public function testJobPatternBasedThreshold(): void
    {
        $config = [
            'threshold' => [
                'default' => 5000,
                '#^TestJob#' => 1000,
                '#^EmailJob#' => 3000,
                '#^ReportJob#' => 10000,
            ],
        ];

        $recorder = new TestThresholdsRecorder($config);

        $this->assertEquals(5000, $recorder->threshold('SomeOtherJob'));
        $this->assertTrue($recorder->underThreshold(4000, 'SomeOtherJob'));
        $this->assertFalse($recorder->underThreshold(6000, 'SomeOtherJob'));

        $this->assertEquals(1000, $recorder->threshold('TestJob'));
        $this->assertTrue($recorder->underThreshold(800, 'TestJob'));
        $this->assertFalse($recorder->underThreshold(1200, 'TestJob'));

        $this->assertEquals(3000, $recorder->threshold('EmailJob'));
        $this->assertTrue($recorder->underThreshold(2500, 'EmailJob'));
        $this->assertFalse($recorder->underThreshold(3500, 'EmailJob'));

        $this->assertEquals(10000, $recorder->threshold('ReportJob'));
        $this->assertTrue($recorder->underThreshold(8000, 'ReportJob'));
        $this->assertFalse($recorder->underThreshold(12000, 'ReportJob'));
    }

    /**
     * Test threshold fallback to default when no pattern matches
     *
     * @return void
     */
    public function testThresholdFallbackToDefault(): void
    {
        $config = [
            'threshold' => [
                'default' => 1000,
                '#^/api/#' => 500,
            ],
        ];

        $recorder = new TestThresholdsRecorder($config);

        $this->assertEquals(1000, $recorder->threshold('/some/other/path'));
        $this->assertEquals(1000, $recorder->threshold('/admin/dashboard'));
        $this->assertEquals(1000, $recorder->threshold('any_string'));
    }

    /**
     * Test threshold with no default in config
     *
     * @return void
     */
    public function testThresholdWithoutDefault(): void
    {
        $config = [
            'threshold' => [
                '#^/api/#' => 500,
            ],
        ];

        $recorder = new TestThresholdsRecorder($config);

        $this->assertEquals(1000, $recorder->threshold('/some/other/path'));
        $this->assertEquals(500, $recorder->threshold('/api/users'));
    }

    /**
     * Test threshold with no config
     *
     * @return void
     */
    public function testThresholdWithNoConfig(): void
    {
        $recorder = new TestThresholdsRecorder([]);

        $this->assertEquals(1000, $recorder->threshold('any_key'));
        $this->assertTrue($recorder->underThreshold(800, 'any_key'));
        $this->assertFalse($recorder->underThreshold(1200, 'any_key'));
    }
}

<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Recorder\Trait;

use Cake\TestSuite\TestCase;

/**
 * SamplingTrait Test Case
 */
class SamplingTraitTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Rhythm\Test\TestCase\Recorder\Trait\TestSamplingClass
     */
    protected TestSamplingClass $samplingClass;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->samplingClass = new TestSamplingClass(['sample_rate' => 0.5]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->samplingClass);
        parent::tearDown();
    }

    /**
     * Test getSampleRate method
     *
     * @return void
     */
    public function testGetSampleRate(): void
    {
        $this->assertEquals(0.5, $this->samplingClass->getSampleRate());
    }

    /**
     * Test getSampleRate with default value
     *
     * @return void
     */
    public function testGetSampleRateDefault(): void
    {
        $samplingClass = new TestSamplingClass([]);
        $this->assertEquals(1.0, $samplingClass->getSampleRate());
    }

    /**
     * Test shouldSample method with 100% sample rate
     *
     * @return void
     */
    public function testShouldSampleWithFullRate(): void
    {
        $samplingClass = new TestSamplingClass(['sample_rate' => 1.0]);

        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $samplingClass->shouldSample();
        }

        $this->assertTrue(in_array(true, $results, true), 'Should always sample with 100% rate');
    }

    /**
     * Test shouldSample method with 0% sample rate
     *
     * @return void
     */
    public function testShouldSampleWithZeroRate(): void
    {
        $samplingClass = new TestSamplingClass(['sample_rate' => 0.0]);

        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $samplingClass->shouldSample();
        }

        $this->assertFalse(in_array(true, $results, true), 'Should never sample with 0% rate');
    }

    /**
     * Test shouldSampleDeterministically method
     *
     * @return void
     */
    public function testShouldSampleDeterministically(): void
    {
        $seed = 'test-seed-123';
        $result1 = $this->samplingClass->shouldSampleDeterministically($seed);
        $result2 = $this->samplingClass->shouldSampleDeterministically($seed);

        $this->assertEquals($result1, $result2, 'Deterministic sampling should return same result for same seed');
    }

    /**
     * Test shouldSampleDeterministically with statistical distribution
     *
     * @return void
     */
    public function testShouldSampleDeterministicallyStatisticalDistribution(): void
    {
        $samplingClass = new TestSamplingClass(['sample_rate' => 0.5]);

        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $samplingClass->shouldSampleDeterministically('seed-' . $i);
        }

        $trueCount = count(array_filter($results));
        $falseCount = count($results) - $trueCount;

        $this->assertGreaterThan(0, $trueCount, 'Should have some true results with 50% sample rate');
        $this->assertGreaterThan(0, $falseCount, 'Should have some false results with 50% sample rate');
    }

    /**
     * Test shouldSampleDeterministically with 100% sample rate
     *
     * @return void
     */
    public function testShouldSampleDeterministicallyFullRate(): void
    {
        $samplingClass = new TestSamplingClass(['sample_rate' => 1.0]);

        $result = $samplingClass->shouldSampleDeterministically('any-seed');
        $this->assertTrue($result, 'Should always sample with 100% rate');
    }

    /**
     * Test shouldSampleDeterministically with 0% sample rate
     *
     * @return void
     */
    public function testShouldSampleDeterministicallyZeroRate(): void
    {
        $samplingClass = new TestSamplingClass(['sample_rate' => 0.0]);

        $result = $samplingClass->shouldSampleDeterministically('any-seed');
        $this->assertFalse($result, 'Should never sample with 0% rate');
    }
}

<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Recorder\Trait;

use Cake\TestSuite\TestCase;

/**
 * IgnoresTrait Test Case
 */
class IgnoresTraitTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Crustum\Rhythm\Test\TestCase\Recorder\Trait\TestIgnoresClass
     */
    protected TestIgnoresClass $ignoresClass;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->ignoresClass = new TestIgnoresClass([
            'ignore' => [
                '#^/admin#',
                '#^/dashboard#',
                '#^/health#',
                '#^/api/v1#',
            ],
        ]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->ignoresClass);
        parent::tearDown();
    }

    /**
     * Test shouldIgnore with matching patterns
     *
     * @return void
     */
    public function testShouldIgnoreWithMatchingPatterns(): void
    {
        $this->assertTrue($this->ignoresClass->shouldIgnore('/admin/users'));
        $this->assertTrue($this->ignoresClass->shouldIgnore('/dashboard/overview'));
        $this->assertTrue($this->ignoresClass->shouldIgnore('/health/check'));
        $this->assertTrue($this->ignoresClass->shouldIgnore('/api/v1/users'));
    }

    /**
     * Test shouldIgnore with non-matching patterns
     *
     * @return void
     */
    public function testShouldIgnoreWithNonMatchingPatterns(): void
    {
        $this->assertFalse($this->ignoresClass->shouldIgnore('/users/profile'));
        $this->assertFalse($this->ignoresClass->shouldIgnore('/products/list'));
        $this->assertFalse($this->ignoresClass->shouldIgnore('/orders/create'));
        $this->assertFalse($this->ignoresClass->shouldIgnore('/api/v2/users'));
    }

    /**
     * Test shouldIgnore with empty ignore patterns
     *
     * @return void
     */
    public function testShouldIgnoreWithEmptyPatterns(): void
    {
        $ignoresClass = new TestIgnoresClass([]);

        $this->assertFalse($ignoresClass->shouldIgnore('/admin/users'));
        $this->assertFalse($ignoresClass->shouldIgnore('/dashboard/overview'));
        $this->assertFalse($ignoresClass->shouldIgnore('/any/path'));
    }

    /**
     * Test shouldIgnore with null ignore patterns
     *
     * @return void
     */
    public function testShouldIgnoreWithNullPatterns(): void
    {
        $ignoresClass = new TestIgnoresClass(['ignore' => null]);

        $this->assertFalse($ignoresClass->shouldIgnore('/admin/users'));
        $this->assertFalse($ignoresClass->shouldIgnore('/dashboard/overview'));
        $this->assertFalse($ignoresClass->shouldIgnore('/any/path'));
    }

    /**
     * Test shouldIgnore with complex regex patterns
     *
     * @return void
     */
    public function testShouldIgnoreWithComplexPatterns(): void
    {
        $ignoresClass = new TestIgnoresClass([
            'ignore' => [
                '#^/admin/.*#',
                '#^/api/v[0-9]+#',
                '#^/health.*#',
                '#^/dashboard.*#',
            ],
        ]);

        $this->assertTrue($ignoresClass->shouldIgnore('/admin/users/list'));
        $this->assertTrue($ignoresClass->shouldIgnore('/api/v1/users'));
        $this->assertTrue($ignoresClass->shouldIgnore('/api/v2/users'));
        $this->assertTrue($ignoresClass->shouldIgnore('/health/check/status'));
        $this->assertTrue($ignoresClass->shouldIgnore('/dashboard/analytics'));

        $this->assertFalse($ignoresClass->shouldIgnore('/users/profile'));
        $this->assertFalse($ignoresClass->shouldIgnore('/products/list'));
    }

    /**
     * Test shouldIgnore with case sensitivity
     *
     * @return void
     */
    public function testShouldIgnoreCaseSensitivity(): void
    {
        $ignoresClass = new TestIgnoresClass([
            'ignore' => [
                '#^/Admin#',
                '#^/DASHBOARD#',
            ],
        ]);

        $this->assertTrue($ignoresClass->shouldIgnore('/Admin/users'));
        $this->assertTrue($ignoresClass->shouldIgnore('/DASHBOARD/overview'));
        $this->assertFalse($ignoresClass->shouldIgnore('/admin/users'));
        $this->assertFalse($ignoresClass->shouldIgnore('/dashboard/overview'));
    }

    /**
     * Test shouldIgnore with special characters
     *
     * @return void
     */
    public function testShouldIgnoreWithSpecialCharacters(): void
    {
        $ignoresClass = new TestIgnoresClass([
            'ignore' => [
                '#^/api/v1/users/.*#',
                '#^/admin/.*/edit#',
                '#^/health-check#',
            ],
        ]);

        $this->assertTrue($ignoresClass->shouldIgnore('/api/v1/users/123'));
        $this->assertTrue($ignoresClass->shouldIgnore('/admin/users/edit'));
        $this->assertTrue($ignoresClass->shouldIgnore('/health-check'));

        $this->assertFalse($ignoresClass->shouldIgnore('/api/v1/users'));
        $this->assertFalse($ignoresClass->shouldIgnore('/admin/users'));
        $this->assertFalse($ignoresClass->shouldIgnore('/health'));
    }

    /**
     * Test shouldIgnore with empty string
     *
     * @return void
     */
    public function testShouldIgnoreWithEmptyString(): void
    {
        $this->assertFalse($this->ignoresClass->shouldIgnore(''));
    }
}

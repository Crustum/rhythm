<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Recorder\Trait;

use Cake\TestSuite\TestCase;

/**
 * GroupsTrait Test Case
 */
class GroupsTraitTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Crustum\Rhythm\Test\TestCase\Recorder\Trait\TestGroupsClass
     */
    protected TestGroupsClass $groupsClass;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->groupsClass = new TestGroupsClass([
            'groups' => [
                '#^(https?://)api\.([^/]+)\.com/([^/]+)/(\d+)#' => '\1api.\2.com/\3/\4',
                '#^(https?://)([^/]+)\.api\.([^/]+)\.com/([^/]+)#' => '\1\2.api.\3.com/\4',
                '#^(https?://)([^/]+)\.com/api/v(\d+)/([^/]+)#' => '\1\2.com/api/v\3/\4',
                '#^(https?://)([^/]+)\.com/([^/]+)/(\d+)#' => '\1\2.com/\3/\4',
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
        unset($this->groupsClass);
        parent::tearDown();
    }

    /**
     * Test group method with API endpoints with IDs pattern.
     *
     * @return void
     */
    public function testGroupWithApiEndpointsWithIds(): void
    {
        $value = 'https://api.example.com/users/123';
        $expected = 'https://api.example.com/users/123';

        $result = $this->groupsClass->group($value);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test group method with subdomain APIs pattern.
     *
     * @return void
     */
    public function testGroupWithSubdomainApis(): void
    {
        $value = 'https://v1.api.example.com/data';
        $expected = 'https://v1.api.example.com/data';

        $result = $this->groupsClass->group($value);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test group method with versioned APIs pattern.
     *
     * @return void
     */
    public function testGroupWithVersionedApis(): void
    {
        $value = 'https://example.com/api/v1/users';
        $expected = 'https://example.com/api/v1/users';

        $result = $this->groupsClass->group($value);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test group method with endpoints with IDs pattern.
     *
     * @return void
     */
    public function testGroupWithEndpointsWithIds(): void
    {
        $value = 'https://example.com/users/123';
        $expected = 'https://example.com/users/123';

        $result = $this->groupsClass->group($value);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test group method with multiple patterns - first match should be used.
     *
     * @return void
     */
    public function testGroupWithMultiplePatternsFirstMatch(): void
    {
        $value = 'https://api.example.com/users/123';
        $expected = 'https://api.example.com/users/123';

        $result = $this->groupsClass->group($value);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test group method with multiple patterns - second match should be used.
     *
     * @return void
     */
    public function testGroupWithMultiplePatternsSecondMatch(): void
    {
        $value = 'https://example.com/users/123';
        $expected = 'https://example.com/users/123';

        $result = $this->groupsClass->group($value);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test group method with no matching patterns.
     *
     * @return void
     */
    public function testGroupWithNoMatchingPatterns(): void
    {
        $value = 'https://example.com/some/other/path';
        $expected = 'https://example.com/some/other/path';

        $result = $this->groupsClass->group($value);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test group method with HTTP URLs.
     *
     * @return void
     */
    public function testGroupWithHttpUrls(): void
    {
        $value = 'http://example.com/users/123';
        $expected = 'http://example.com/users/123';

        $result = $this->groupsClass->group($value);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test group method with no patterns configured.
     *
     * @return void
     */
    public function testGroupWithNoPatterns(): void
    {
        $groupsClass = new TestGroupsClass([]);
        $value = 'https://example.com/api/users/123';

        $result = $groupsClass->group($value);

        $this->assertEquals($value, $result);
    }

    /**
     * Test group method with empty patterns array.
     *
     * @return void
     */
    public function testGroupWithEmptyPatterns(): void
    {
        $groupsClass = new TestGroupsClass(['groups' => []]);
        $value = 'https://example.com/api/users/123';

        $result = $groupsClass->group($value);

        $this->assertEquals($value, $result);
    }

    /**
     * Test getGroupPatterns method.
     *
     * @return void
     */
    public function testGetGroupPatterns(): void
    {
        $patterns = [
            '#^https?://api\.([^/]+)\.com/([^/]+)/(\d+)#' => 'https://api.\1.com/\2/\3',
            '#^https?://([^/]+)\.com/([^/]+)/(\d+)#' => 'https://\1.com/\2/\3',
        ];

        $groupsClass = new TestGroupsClass(['groups' => $patterns]);

        $result = $groupsClass->getGroupPatterns();

        $this->assertEquals($patterns, $result);
    }

    /**
     * Test getGroupPatterns method with no patterns.
     *
     * @return void
     */
    public function testGetGroupPatternsWithNoPatterns(): void
    {
        $groupsClass = new TestGroupsClass([]);

        $result = $groupsClass->getGroupPatterns();

        $this->assertEquals([], $result);
    }

    /**
     * Test hasGroupPatterns method with patterns.
     *
     * @return void
     */
    public function testHasGroupPatternsWithPatterns(): void
    {
        $result = $this->groupsClass->hasGroupPatterns();

        $this->assertTrue($result);
    }

    /**
     * Test hasGroupPatterns method with no patterns.
     *
     * @return void
     */
    public function testHasGroupPatternsWithNoPatterns(): void
    {
        $groupsClass = new TestGroupsClass([]);

        $result = $groupsClass->hasGroupPatterns();

        $this->assertFalse($result);
    }

    /**
     * Test hasGroupPatterns method with empty patterns array.
     *
     * @return void
     */
    public function testHasGroupPatternsWithEmptyPatterns(): void
    {
        $groupsClass = new TestGroupsClass(['groups' => []]);

        $result = $groupsClass->hasGroupPatterns();

        $this->assertFalse($result);
    }

    /**
     * Test group method with real-world examples.
     *
     * @return void
     */
    public function testGroupWithRealWorldExamples(): void
    {
        $groupsClass = new TestGroupsClass([
            'groups' => [
                '#^(https?://)api\.stripe\.com/v1/([^/]+)/([^/]+)#' => '\1api.stripe.com/v1/\2/\3',
                '#^(https?://)api\.github\.com/([^/]+)/([^/]+)#' => '\1api.github.com/\2/\3',
                '#^(https?://)([^/]+)\.amazonaws\.com/([^/]+)/([^/]+)#' => '\1\2.amazonaws.com/\3/\4',
            ],
        ]);

        $stripeUrl = 'https://api.stripe.com/v1/customers/cus_123';
        $expectedStripe = 'https://api.stripe.com/v1/customers/cus_123';
        $this->assertEquals($expectedStripe, $groupsClass->group($stripeUrl));

        $githubUrl = 'https://api.github.com/users/octocat';
        $expectedGithub = 'https://api.github.com/users/octocat';
        $this->assertEquals($expectedGithub, $groupsClass->group($githubUrl));

        $awsUrl = 'https://s3.amazonaws.com/bucket/file1';
        $expectedAws = 'https://s3.amazonaws.com/bucket/file1';
        $this->assertEquals($expectedAws, $groupsClass->group($awsUrl));
    }
}

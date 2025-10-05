<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Recorder\Trait;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * QueueNameTrait Test Case
 */
class QueueNameTraitTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Rhythm\Test\TestCase\Recorder\Trait\TestQueueNameClass
     */
    protected TestQueueNameClass $queueNameClass;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->queueNameClass = new TestQueueNameClass();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->queueNameClass);
        parent::tearDown();
    }

    /**
     * Test extractQueuePrefixes with default configuration.
     *
     * @return void
     */
    public function testExtractQueuePrefixesWithDefaultConfig(): void
    {
        Configure::write('Queue', [
            'default' => [
                'url' => [
                    'client' => [
                        'prefix' => 'enqueue',
                        'separator' => '.',
                        'app_name' => 'app',
                    ],
                ],
            ],
        ]);

        $this->queueNameClass->extractQueuePrefixes();

        $this->assertEquals(['enqueue.app.'], $this->queueNameClass->getQueuePrefixes());
    }

    /**
     * Test extractQueuePrefixes with custom configuration.
     *
     * @return void
     */
    public function testExtractQueuePrefixesWithCustomConfig(): void
    {
        Configure::write('Queue', [
            'default' => [
                'url' => [
                    'client' => [
                        'prefix' => 'myapp',
                        'separator' => '_',
                        'app_name' => 'test',
                    ],
                ],
            ],
        ]);

        $this->queueNameClass->extractQueuePrefixes();

        $this->assertEquals(['myapp_test_'], $this->queueNameClass->getQueuePrefixes());
    }

    /**
     * Test extractQueuePrefixes with multiple queue configurations.
     *
     * @return void
     */
    public function testExtractQueuePrefixesWithMultipleConfigs(): void
    {
        Configure::write('Queue', [
            'default' => [
                'url' => [
                    'client' => [
                        'prefix' => 'enqueue',
                        'separator' => '.',
                        'app_name' => 'app',
                    ],
                ],
            ],
            'high' => [
                'url' => [
                    'client' => [
                        'prefix' => 'enqueue',
                        'separator' => '.',
                        'app_name' => 'app',
                    ],
                ],
            ],
        ]);

        $this->queueNameClass->extractQueuePrefixes();

        $this->assertEquals(['enqueue.app.', 'enqueue.app.'], $this->queueNameClass->getQueuePrefixes());
    }

    /**
     * Test extractQueuePrefixes with legacy client configuration.
     *
     * @return void
     */
    public function testExtractQueuePrefixesWithLegacyClientConfig(): void
    {
        Configure::write('Queue', [
            'default' => [
                'client' => [
                    'prefix' => 'enqueue',
                    'separator' => '.',
                    'app' => 'app',
                ],
            ],
        ]);

        $this->queueNameClass->extractQueuePrefixes();

        $this->assertEquals(['enqueue.app.'], $this->queueNameClass->getQueuePrefixes());
    }

    /**
     * Test extractQueuePrefixes with no prefix.
     *
     * @return void
     */
    public function testExtractQueuePrefixesWithNoPrefix(): void
    {
        Configure::write('Queue', [
            'default' => [
                'url' => [
                    'client' => [
                        'separator' => '.',
                        'app_name' => 'app',
                    ],
                ],
            ],
        ]);

        $this->queueNameClass->extractQueuePrefixes();

        $this->assertEquals(['enqueue.app.'], $this->queueNameClass->getQueuePrefixes());
    }

    /**
     * Test extractQueuePrefixes with no Queue configuration.
     *
     * @return void
     */
    public function testExtractQueuePrefixesWithNoQueueConfig(): void
    {
        Configure::delete('Queue');

        $this->queueNameClass->extractQueuePrefixes();

        $this->assertEquals(['enqueue.app.'], $this->queueNameClass->getQueuePrefixes());
    }

    /**
     * Test stripQueuePrefix with matching prefix.
     *
     * @return void
     */
    public function testStripQueuePrefixWithMatchingPrefix(): void
    {
        $this->queueNameClass->setQueuePrefixes(['enqueue.app.']);

        $result = $this->queueNameClass->stripQueuePrefix('enqueue.app.default');
        $this->assertEquals('default', $result);
    }

    /**
     * Test stripQueuePrefix with non-matching prefix.
     *
     * @return void
     */
    public function testStripQueuePrefixWithNonMatchingPrefix(): void
    {
        $this->queueNameClass->setQueuePrefixes(['enqueue.app.']);

        $result = $this->queueNameClass->stripQueuePrefix('other.prefix.default');
        $this->assertEquals('other.prefix.default', $result);
    }

    /**
     * Test stripQueuePrefix with multiple prefixes.
     *
     * @return void
     */
    public function testStripQueuePrefixWithMultiplePrefixes(): void
    {
        $this->queueNameClass->setQueuePrefixes(['enqueue.app.', 'myapp.test.']);

        $result = $this->queueNameClass->stripQueuePrefix('enqueue.app.default');
        $this->assertEquals('default', $result);

        $result = $this->queueNameClass->stripQueuePrefix('myapp.test.high');
        $this->assertEquals('high', $result);
    }

    /**
     * Test stripQueuePrefix with exact prefix match.
     *
     * @return void
     */
    public function testStripQueuePrefixWithExactPrefixMatch(): void
    {
        $this->queueNameClass->setQueuePrefixes(['enqueue.app.']);

        $result = $this->queueNameClass->stripQueuePrefix('enqueue.app.');
        $this->assertEquals('', $result);
    }

    /**
     * Test integration with real configuration.
     *
     * @return void
     */
    public function testIntegrationWithRealConfiguration(): void
    {
        Configure::write('Queue', [
            'default' => [
                'url' => [
                    'client' => [
                        'prefix' => 'enqueue',
                        'separator' => '.',
                        'app_name' => 'app',
                    ],
                ],
            ],
            'high' => [
                'url' => [
                    'client' => [
                        'prefix' => 'enqueue',
                        'separator' => '.',
                        'app_name' => 'app',
                    ],
                ],
            ],
        ]);

        $this->queueNameClass->extractQueuePrefixes();

        $result = $this->queueNameClass->stripQueuePrefix('enqueue.app.default');
        $this->assertEquals('default', $result);

        $result = $this->queueNameClass->stripQueuePrefix('enqueue.app.high');
        $this->assertEquals('high', $result);

        $result = $this->queueNameClass->stripQueuePrefix('enqueue.app.email.notifications');
        $this->assertEquals('email.notifications', $result);
    }
}

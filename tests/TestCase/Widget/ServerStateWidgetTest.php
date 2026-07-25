<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase\Widget;

use Cake\Collection\Collection;
use Cake\Core\ContainerInterface;
use Cake\TestSuite\TestCase;
use Crustum\Rhythm\Ingest\NullIngest;
use Crustum\Rhythm\Rhythm;
use Crustum\Rhythm\Storage\StorageInterface;
use Crustum\Rhythm\Widget\ServerStateWidget;

/**
 * ServerStateWidget Test Case
 */
class ServerStateWidgetTest extends TestCase
{
    /**
     * Build a widget with mocked system values.
     *
     * @param array<string, object> $systemValues System values keyed by server slug
     * @param array<string, mixed> $config Widget config
     * @return \Crustum\Rhythm\Widget\ServerStateWidget
     */
    protected function createWidget(array $systemValues, array $config = []): ServerStateWidget
    {
        $storage = $this->createStub(StorageInterface::class);
        $storage->method('values')->willReturn(new Collection($systemValues));
        $storage->method('graph')->willReturn(new Collection([]));

        $container = $this->createStub(ContainerInterface::class);
        $rhythm = new Rhythm($storage, new NullIngest(), $container);

        return new class ($rhythm, $config) extends ServerStateWidget {
            /**
             * @inheritDoc
             */
            protected function remember(callable $query, string $key = '', ?int $ttl = 300): mixed
            {
                return $query();
            }
        };
    }

    /**
     * Create a system value object.
     *
     * @param string $name Server name
     * @param int $timestamp Timestamp
     * @param int $cpu CPU percentage
     * @param int $memoryUsed Memory used in MB
     * @return object
     */
    protected function systemValue(string $name, int $timestamp, int $cpu = 10, int $memoryUsed = 100): object
    {
        return (object)[
            'timestamp' => $timestamp,
            'value' => json_encode([
                'name' => $name,
                'cpu' => $cpu,
                'memory_used' => $memoryUsed,
                'memory_total' => 1000,
                'storage' => [
                    ['directory' => '/', 'used' => 100, 'total' => 500],
                ],
            ], JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * Test default ascending sort by name.
     *
     * @return void
     */
    public function testSortsByNameAscendingByDefault(): void
    {
        $now = time();
        $widget = $this->createWidget([
            'b-web' => $this->systemValue('B Web', $now - 2),
            'a-web' => $this->systemValue('A Web', $now - 3),
            'c-web' => $this->systemValue('C Web', $now - 1),
        ]);

        $data = $widget->getData();
        $names = array_column($data['servers'], 'name');

        $this->assertSame(['A Web', 'B Web', 'C Web'], $names);
        $this->assertSame('name', $data['sort_by']);
        $this->assertSame('asc', $data['sort_direction']);
    }

    /**
     * Test descending name sort via options.
     *
     * @return void
     */
    public function testSortsByNameDescending(): void
    {
        $now = time();
        $widget = $this->createWidget([
            'b-web' => $this->systemValue('B Web', $now - 2),
            'a-web' => $this->systemValue('A Web', $now - 3),
            'c-web' => $this->systemValue('C Web', $now - 1),
        ]);

        $data = $widget->getData(['sortDirection' => 'desc']);
        $names = array_column($data['servers'], 'name');

        $this->assertSame(['C Web', 'B Web', 'A Web'], $names);
    }

    /**
     * Test sorting by updated_at.
     *
     * @return void
     */
    public function testSortsByUpdatedAt(): void
    {
        $now = time();
        $widget = $this->createWidget([
            'b-web' => $this->systemValue('B Web', $now - 2),
            'a-web' => $this->systemValue('A Web', $now - 3),
            'c-web' => $this->systemValue('C Web', $now - 1),
        ]);

        $data = $widget->getData(['sortBy' => 'updated_at']);
        $names = array_column($data['servers'], 'name');

        $this->assertSame(['A Web', 'B Web', 'C Web'], $names);
    }

    /**
     * Test sortBy from widget config (Pulse Livewire prop equivalent).
     *
     * @return void
     */
    public function testUsesConfigSortByAndDirection(): void
    {
        $now = time();
        $widget = $this->createWidget([
            'b-web' => $this->systemValue('B Web', $now - 2, 50),
            'a-web' => $this->systemValue('A Web', $now - 3, 10),
            'c-web' => $this->systemValue('C Web', $now - 1, 90),
        ], [
            'sortBy' => 'cpu_current',
            'sortDirection' => 'desc',
        ]);

        $data = $widget->getData();
        $names = array_column($data['servers'], 'name');

        $this->assertSame(['C Web', 'B Web', 'A Web'], $names);
    }
}

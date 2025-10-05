<?php
declare(strict_types=1);

namespace Rhythm;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use InvalidArgumentException;
use Rhythm\Ingest\IngestInterface;
use Rhythm\Recorder\RecorderInterface;
use Rhythm\Recorder\RecorderResolver;
use Rhythm\Storage\StorageInterface;
use Throwable;

/**
 * Core Rhythm Manager
 *
 * Central manager for metric collection, storage.
 */
class Rhythm
{
    /**
     * The registered recorders.
     *
     * @var array<string, \Rhythm\Recorder\RecorderInterface>
     */
    protected array $recorders = [];

    /**
     * The queued metric entries.
     *
     * @var array<int, \Rhythm\RhythmEntry|\Rhythm\RhythmValue>
     */
    protected array $entries = [];

    /**
     * The list of queued lazy entry and value resolvers.
     *
     * @var array<callable>
     */
    protected array $lazy = [];

    /**
     * Indicates if Rhythm should be recording.
     *
     * @var bool
     */
    protected bool $shouldRecord = true;

    /**
     * The entry filters.
     *
     * @var array<callable>
     */
    protected array $filters = [];

    /**
     * The remembered user's ID.
     *
     * @var string|int|null
     */
    protected int|string|null $rememberedUserId = null;

    /**
     * Indicates if Rhythm is currently evaluating the buffer.
     *
     * @var bool
     */
    protected bool $evaluatingBuffer = false;

    /**
     * Handle exceptions using the given callback.
     *
     * @var callable|null
     */
    protected $handleExceptionsUsing;

    /**
     * Rhythm configuration.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Storage interface.
     *
     * @var \Rhythm\Storage\StorageInterface
     */
    protected StorageInterface $storage;

    /**
     * Ingest interface.
     *
     * @var \Rhythm\Ingest\IngestInterface
     */
    protected IngestInterface $ingest;

    /**
     * Event manager instance.
     *
     * @var \Cake\Event\EventManager
     */
    protected EventManager $eventManager;

    /**
     * Container interface for dependency injection.
     *
     * @var \Cake\Core\ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Recorder resolver for dependency injection.
     *
     * @var \Rhythm\Recorder\RecorderResolver
     */
    protected RecorderResolver $recorderResolver;

    /**
     * Constructor.
     *
     * @param \Rhythm\Storage\StorageInterface $storage Storage interface
     * @param \Rhythm\Ingest\IngestInterface $ingest Ingest interface
     * @param \Cake\Core\ContainerInterface $container Container interface
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(
        StorageInterface $storage,
        IngestInterface $ingest,
        ContainerInterface $container,
        array $config = [],
    ) {
        $this->storage = $storage;
        $this->ingest = $ingest;
        $this->container = $container;
        $this->config = $config ?: Configure::read('Rhythm', []);
        $this->eventManager = EventManager::instance();
        $this->recorderResolver = new RecorderResolver($container, $this);
        $this->loadRecordersFromConfig();
    }

    /**
     * Record a metric entry.
     *
     * @param string $type Metric type
     * @param string $key Metric key
     * @param int|null $value Metric value
     * @param int|null $timestamp Timestamp
     * @return \Rhythm\RhythmEntry
     */
    public function record(string $type, string $key, ?int $value = null, ?int $timestamp = null): RhythmEntry
    {
        $timestamp = $timestamp ?: (new DateTime())->getTimestamp();

        $entry = new RhythmEntry($timestamp, $type, $key, $value);

        if ($this->shouldRecord) {
            $this->entries[] = $entry;
            $this->ingestWhenOverBufferSize();
        }

        return $entry;
    }

    /**
     * Set a metric value.
     *
     * @param string $type Metric type
     * @param string $key Metric key
     * @param string $value Metric value
     * @param int|null $timestamp Timestamp
     * @return \Rhythm\RhythmValue
     */
    public function set(string $type, string $key, string $value, ?int $timestamp = null): RhythmValue
    {
        $timestamp = $timestamp ?: (new DateTime())->getTimestamp();

        $metricValue = new RhythmValue($timestamp, $type, $key, $value);

        if ($this->shouldRecord) {
            $this->entries[] = $metricValue;
            $this->ingestWhenOverBufferSize();
        }

        return $metricValue;
    }

    /**
     * Lazily capture items.
     *
     * @param callable $closure Closure to execute for lazy entry creation
     * @return $this
     */
    public function lazy(callable $closure)
    {
        if ($this->shouldRecord) {
            $this->lazy[] = $closure;
            $this->ingestWhenOverBufferSize();
        }

        return $this;
    }

    /**
     * Start recording.
     *
     * @return $this
     */
    public function startRecording()
    {
        $this->shouldRecord = true;

        return $this;
    }

    /**
     * Stop recording.
     *
     * @return $this
     */
    public function stopRecording()
    {
        $this->shouldRecord = false;

        return $this;
    }

    /**
     * Execute the given callback without recording.
     *
     * @param callable $callback Callback to execute
     * @return mixed
     */
    public function ignore(callable $callback): mixed
    {
        $cachedRecording = $this->shouldRecord;

        try {
            $this->shouldRecord = false;

            return $callback();
        } catch (Throwable $e) {
            debug($e->getMessage());
            debug($e->getTraceAsString());
        } finally {
            $this->shouldRecord = $cachedRecording;
        }

        return null;
    }

    /**
     * Filter items before storage using the provided filter.
     *
     * @param callable $filter Filter function that returns bool
     * @return $this
     */
    public function filter(callable $filter)
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Handle exceptions using the given callback.
     *
     * @param callable $callback Exception handler
     * @return $this
     */
    public function handleExceptionsUsing(callable $callback)
    {
        $this->handleExceptionsUsing = $callback;

        return $this;
    }

    /**
     * Execute the given callback handling any exceptions.
     *
     * @param callable $callback Callback to execute
     * @return mixed
     */
    public function rescue(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if ($this->handleExceptionsUsing) {
                ($this->handleExceptionsUsing)($e);
            }

            return null;
        }
    }

    /**
     * Determine if the given entry should be recorded.
     *
     * @param \Rhythm\RhythmEntry|\Rhythm\RhythmValue $entry Entry to check
     * @return bool
     */
    protected function shouldRecordEntry(RhythmEntry|RhythmValue $entry): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter($entry)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve lazy entries.
     *
     * @return void
     */
    protected function resolveLazyEntries(): void
    {
        $this->rescue(function (): void {
            foreach ($this->lazy as $lazy) {
                $lazy();
            }
        });

        $this->lazy = [];
    }

    /**
     * Register recorders with the event system.
     *
     * @param array<string, array<string, mixed>> $recorders Array of recorder configurations with className
     * @return $this
     */
    public function register(array $recorders)
    {
        foreach ($recorders as $recorderName => $config) {
            if (array_key_exists($recorderName, $this->recorders)) {
                continue;
            }

            if (!($config['enabled'] ?? false)) {
                continue;
            }

            if (!isset($config['className'])) {
                throw new InvalidArgumentException("Recorder '{$recorderName}' must have a 'className' configuration");
            }

            $recorderClass = $config['className'];
            $recorder = $this->recorderResolver->resolve($recorderClass, $config);

            if ($recorder instanceof EventListenerInterface) {
                $this->eventManager->on($recorder);
            }

            $this->recorders[(string)$recorderClass] = $recorder;
        }

        return $this;
    }

    /**
     * Unregister recorders from the event system.
     *
     * @return $this
     */
    public function unregister()
    {
        foreach ($this->recorders as $recorder) {
            if ($recorder instanceof EventListenerInterface) {
                $this->eventManager->off($recorder);
            }
        }

        return $this;
    }

    /**
     * Load recorders from configuration.
     *
     * @return $this
     */
    public function loadRecordersFromConfig()
    {
        $recorders = $this->config['recorders'] ?? [];

        return $this->register($recorders);
    }

    /**
     * Register a single recorder.
     *
     * @param string $name Recorder name
     * @param \Rhythm\Recorder\RecorderInterface $recorder Recorder instance
     * @return void
     */
    public function registerRecorder(string $name, RecorderInterface $recorder): void
    {
        $this->recorders[$name] = $recorder;
    }

    /**
     * Get a recorder by name.
     *
     * @param string $name Recorder name
     * @return \Rhythm\Recorder\RecorderInterface|null
     */
    public function getRecorder(string $name): ?RecorderInterface
    {
        return $this->recorders[$name] ?? null;
    }

    /**
     * Ingest queued entries to persistent storage.
     *
     * @return int Number of entries ingested
     */
    public function ingest(): int
    {
        $this->resolveLazyEntries();

        return $this->ignore(function () {
            if ($this->entries === []) {
                $this->flush();

                return 0;
            }

            $entries = $this->rescue(function () {
                return array_filter($this->entries, fn(RhythmEntry|RhythmValue $entry) => $this->shouldRecordEntry($entry));
            }) ?? [];

            if (empty($entries)) {
                $this->flush();

                return 0;
            }

            $count = $this->rescue(function () use ($entries) {
                $this->ingest->ingest(new Collection($entries));

                return count($entries);
            }) ?? 0;

            $this->flush();

            return $count;
        });
    }

    /**
     * Digest ingested entries to final storage.
     *
     * @return int Number of entries digested
     */
    public function digest(): int
    {
            $count = $this->ingest->digest();

            return $count;
    }

    /**
     * Flush the queue.
     *
     * @return int Number of entries flushed
     */
    public function flush(): int
    {
        $count = count($this->entries) + count($this->lazy);

        $this->entries = [];
        $this->lazy = [];
        $this->rememberedUserId = null;

        return $count;
    }

    /**
     * Aggregate metrics for a specific type and period.
     *
     * @param string $type Metric type
     * @param array|string $aggregates Aggregation type (count, min, max, sum, avg)
     * @param int $intervalMinutes Time period in minutes
     * @param string|null $orderBy Order by field
     * @param string $direction Order direction
     * @param int $limit Result limit
     * @return \Cake\Collection\Collection
     */
    public function aggregate(
        string $type,
        array|string $aggregates,
        int $intervalMinutes,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        return new Collection(
            $this->storage->aggregate($type, $aggregates, $intervalMinutes, $orderBy, $direction, $limit),
        );
    }

    /**
     * Get configuration value.
     *
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get all registered recorders.
     *
     * @return array<string, \Rhythm\Recorder\RecorderInterface>
     */
    public function getRecorders(): array
    {
        return $this->recorders;
    }

    /**
     * Get all registered recorders.
     *
     * @return void
     */
    public function clearRecorders(): void
    {
        $this->unregister();
        $this->recorders = [];
    }

    /**
     * Get storage interface.
     *
     * @return \Rhythm\Storage\StorageInterface
     */
    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    /**
     * Check if we should ingest based on buffer size.
     *
     * @return void
     */
    protected function ingestWhenOverBufferSize(): void
    {
        if ($this->evaluatingBuffer) {
            return;
        }

        $buffer = $this->config['buffer'] ?? 5000;

        if (count($this->entries) + count($this->lazy) > $buffer) {
            $this->evaluatingBuffer = true;
            $this->resolveLazyEntries();
        }

        if (count($this->entries) > $buffer) {
            $this->evaluatingBuffer = true;
            $this->ingest();
        }

        $this->evaluatingBuffer = false;
    }

    /**
     * Determine if Rhythm wants to ingest entries.
     *
     * @return bool
     */
    public function wantsIngesting(): bool
    {
        return $this->lazy !== [] || $this->entries !== [];
    }

    /**
     * Report an exception to Rhythm.
     *
     * @param \Throwable $exception Exception to report
     * @return $this
     */
    public function report(Throwable $exception)
    {
        $this->rescue(function () use ($exception): void {
            $exceptionClass = get_class($exception);
            $this->record('exception', $exceptionClass);
        });

        return $this;
    }

    /**
     * Get the queued entries.
     *
     * @return \Cake\Collection\Collection<int, \Rhythm\RhythmEntry|\Rhythm\RhythmValue>
     */
    public function entries(): Collection
    {
        return new Collection($this->entries);
    }

    /**
     * Trim old data from storage.
     *
     * @return void
     */
    public function trim(): void
    {
        $this->storage->trim();
    }

    /**
     * Purge data from storage.
     *
     * @param array<string>|null $types Specific types to purge, null for all
     * @return void
     */
    public function purge(?array $types = null): void
    {
        $this->storage->purge($types);
    }
}

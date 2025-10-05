<?php
declare(strict_types=1);

namespace Rhythm\Database\Log;

use Cake\Database\Log\LoggedQuery;
use Cake\Event\EventManager;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Rhythm\Event\SlowQueryEvent;
use Stringable;

/**
 * Rhythm Query Logger
 *
 * Captures slow database queries and dispatches events.
 * Follows the DebugKit pattern for query logging.
 */
class RhythmQueryLogger extends AbstractLogger
{
    /**
     * Decorated logger.
     *
     * @var \Psr\Log\LoggerInterface|null
     */
    protected ?LoggerInterface $_logger = null;

    /**
     * Name of the connection being logged.
     *
     * @var string
     */
    protected string $_connectionName;

    /**
     * Slow query threshold in milliseconds.
     *
     * @var int
     */
    protected int $_threshold;

    /**
     * Event manager instance.
     *
     * @var \Cake\Event\EventManager
     */
    protected EventManager $eventManager;

    /**
     * Constructor.
     *
     * @param \Psr\Log\LoggerInterface|null $logger The logger to decorate
     * @param string $name The name of the connection being logged
     * @param int $threshold Slow query threshold in milliseconds
     */
    public function __construct(
        ?LoggerInterface $logger,
        string $name,
        int $threshold = 1000,
    ) {
        $this->_logger = $logger;
        $this->_connectionName = $name;
        $this->_threshold = $threshold;
        $this->eventManager = EventManager::instance();
    }

    /**
     * Get the connection name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->_connectionName;
    }

    /**
     * Get the slow query threshold.
     *
     * @return int
     */
    public function getThreshold(): int
    {
        return $this->_threshold;
    }

    /**
     * Set the slow query threshold.
     *
     * @param int $threshold Threshold in milliseconds
     * @return $this
     */
    public function setThreshold(int $threshold)
    {
        $this->_threshold = $threshold;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        /** @var \Cake\Database\Log\LoggedQuery|object|null $query */
        $query = $context['query'] ?? null;
        $this->_threshold = 3;
        if ($this->_logger) {
            $this->_logger->log($level, $message, $context);
        }

        if (!$query instanceof LoggedQuery) {
            return;
        }

        $data = $query->jsonSerialize();
        $duration = (int)$data['took'];

        if ($duration >= $this->_threshold) {
            $sql = (string)$query;
            $location = $this->resolveLocation();

            $event = new SlowQueryEvent($sql, $duration, $location, $query);
            $this->eventManager->dispatch($event);
        }
    }

    /**
     * Resolve the location of the query.
     *
     * @return string|null
     */
    protected function resolveLocation(): ?string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($backtrace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }

            $file = $frame['file'];
            if ($this->isInternalFile($file)) {
                continue;
            }

            return $this->formatLocation($file, $frame['line'] ?? null);
        }

        return null;
    }

    /**
     * Determine whether a file should be considered internal.
     *
     * @param string $file File path
     * @return bool
     */
    protected function isInternalFile(string $file): bool
    {
        $internalPaths = [
            'vendor' . DIRECTORY_SEPARATOR . 'cakephp' . DIRECTORY_SEPARATOR . 'cakephp',
            'vendor' . DIRECTORY_SEPARATOR . 'cakephp' . DIRECTORY_SEPARATOR . 'debug_kit',
            'vendor' . DIRECTORY_SEPARATOR . 'cakephp' . DIRECTORY_SEPARATOR . 'rhythm',
            'vendor' . DIRECTORY_SEPARATOR,
            'plugins' . DIRECTORY_SEPARATOR . 'Rhythm',
        ];

        foreach ($internalPaths as $path) {
            if (str_contains($file, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format a file and line number.
     *
     * @param string $file File path
     * @param int|null $line Line number
     * @return string
     */
    protected function formatLocation(string $file, ?int $line): string
    {
        $relativePath = str_replace(ROOT . DIRECTORY_SEPARATOR, '', $file);

        return $relativePath . (is_int($line) ? ':' . $line : '');
    }
}

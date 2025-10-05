<?php
declare(strict_types=1);

namespace Rhythm\Recorder;

use Cake\Error\FatalErrorException;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\I18n\DateTime;
use Rhythm\Recorder\Trait\IgnoresTrait;
use Rhythm\Recorder\Trait\SamplingTrait;
use Throwable;

/**
 * Exceptions Recorder
 *
 * Records metrics for application exceptions.
 */
class ExceptionsRecorder extends BaseRecorder implements EventListenerInterface
{
    use SamplingTrait;
    use IgnoresTrait;

    /**
     * Implemented events.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Error.beforeRender' => 'recordException',
            'Exception.beforeRender' => 'recordException',
        ];
    }

    /**
     * Record exception.
     *
     * @param \Cake\Event\EventInterface<\Rhythm\Rhythm> $event Event instance
     * @return void
     */
    public function recordException(EventInterface $event): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
            return;
        }

        $exception = $event->getData('exception');
        if ($exception instanceof Throwable) {
            $this->record($exception);
        }
    }

    /**
     * Record metric data.
     *
     * @param mixed $data Data to record
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
            return;
        }

        if (!$data instanceof Throwable) {
            return;
        }
        $exception = $data;
        $class = $this->resolveClass($exception);

        if ($this->shouldIgnore($class)) {
            return;
        }

        $location = $this->resolveLocation($exception);

        $key = json_encode([
            'class' => $class,
            'location' => $location,
        ]);

        if ($key === false) {
            return;
        }

        $timestamp = (new DateTime())->getTimestamp();
        $this->rhythm->record('exception', $key, $timestamp)->max()->count();
    }

    /**
     * Resolve the exception class to record.
     *
     * @param \Throwable $e The exception instance.
     * @return class-string<\Throwable>
     */
    protected function resolveClass(Throwable $e): string
    {
        $previous = $e->getPrevious();

        if ($e instanceof FatalErrorException && $previous) {
            return $previous::class;
        }

        return $e::class;
    }

    /**
     * Resolve the exception location to record.
     *
     * @param \Throwable $e The exception instance.
     * @return string
     */
    protected function resolveLocation(Throwable $e): string
    {
        return $this->resolveLocationFromTrace($e);
    }

    /**
     * Resolve the location for the given exception.
     *
     * @param \Throwable $e The exception instance.
     * @return string
     */
    protected function resolveLocationFromTrace(Throwable $e): string
    {
        $trace = collection($e->getTrace());

        $frame = $trace->filter(function (array $frame) {
            return isset($frame['file']) && !$this->isInternalFile($frame['file']);
        })->first();

        if (!$this->isInternalFile($e->getFile()) || $frame === null) {
            return $this->formatLocation($e->getFile(), $e->getLine());
        }

        return $this->formatLocation($frame['file'] ?? 'unknown', $frame['line'] ?? null);
    }

    /**
     * Determine whether a file should be considered internal.
     *
     * @param string $file The file path.
     * @return bool
     */
    protected function isInternalFile(string $file): bool
    {
        $vendorDir = ROOT . DS . 'vendor';
        if (str_starts_with($file, $vendorDir)) {
            return true;
        }

        $internalPaths = $this->config['internal_paths'] ?? ['config', 'webroot/index.php'];
        foreach ($internalPaths as $path) {
            if (str_starts_with($file, ROOT . DS . $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format a file and line number and strip the base path.
     *
     * @param string $file The file path.
     * @param int|null $line The line number.
     * @return string
     */
    protected function formatLocation(string $file, ?int $line): string
    {
        $file = str_replace(ROOT . DS, '', $file);

        return $file . ($line ? ':' . $line : '');
    }
}

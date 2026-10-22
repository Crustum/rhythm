<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Cake\Core\ContainerInterface;
use Crustum\Rhythm\Rhythm;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Recorder Resolver
 *
 * Resolves recorder class names to instances with dependency injection.
 */
class RecorderResolver
{
    /**
     * Container instance.
     *
     * @var \Cake\Core\ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Rhythm instance.
     *
     * @var \Crustum\Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Constructor.
     *
     * @param \Cake\Core\ContainerInterface $container Container interface
     * @param \Crustum\Rhythm\Rhythm $rhythm Rhythm instance
     */
    public function __construct(ContainerInterface $container, Rhythm $rhythm)
    {
        $this->container = $container;
        $this->rhythm = $rhythm;
    }

    /**
     * Whether resolve() may fetch the recorder itself from the container.
     *
     * Disabled while Rhythm is being constructed: a container-registered
     * recorder whose definition (transitively) depends on Rhythm would
     * re-enter Rhythm construction and recurse until memory is exhausted.
     * Rhythm flips this off around its constructor-time
     * loadRecordersFromConfig() call. BaseRecorder dependencies are still
     * pulled from the container (see createRecorder()).
     *
     * @var bool
     */
    protected bool $resolveFromContainer = true;

    /**
     * Toggle container-first resolution of the recorder class itself.
     *
     * @param bool $resolve True to consult the container first (default), false to always build directly.
     * @return void
     */
    public function setResolveFromContainer(bool $resolve): void
    {
        $this->resolveFromContainer = $resolve;
    }

    /**
     * Resolve recorder class name to a recorder instance.
     *
     * When container-first resolution is enabled and the container holds the
     * recorder class, the container instance wins (shared identity, custom
     * wiring). Otherwise recorders are built directly so the `Rhythm`
     * dependency is satisfied by the already-constructed live instance.
     *
     * @param string $recorderClass Recorder class name
     * @param array<string, mixed> $config Recorder configuration
     * @return \Crustum\Rhythm\Recorder\RecorderInterface
     * @throws \InvalidArgumentException If recorder not found or cannot be instantiated
     */
    public function resolve(string $recorderClass, array $config = []): RecorderInterface
    {
        if ($this->resolveFromContainer && $this->container->has($recorderClass)) {
            $recorder = $this->container->get($recorderClass);

            if ($recorder instanceof RecorderInterface) {
                return $recorder;
            }

            throw new InvalidArgumentException(sprintf(
                'Recorder `%s` from container does not implement RecorderInterface.',
                $recorderClass,
            ));
        }

        return $this->createRecorder($recorderClass, $config);
    }

    /**
     * Create a recorder with auto-injected dependencies.
     *
     * The `Rhythm` dependency is injected from the resolver's live instance;
     * every other typed dependency is pulled from the container, and a `config`
     * array parameter receives the recorder configuration.
     *
     * @param string $recorderClass Recorder class name
     * @param array<string, mixed> $config Recorder configuration
     * @return \Crustum\Rhythm\Recorder\RecorderInterface
     * @throws \InvalidArgumentException If recorder cannot be created
     */
    protected function createRecorder(string $recorderClass, array $config = []): RecorderInterface
    {
        if (!class_exists($recorderClass)) {
            throw new InvalidArgumentException(sprintf(
                'Recorder class `%s` does not exist.',
                $recorderClass,
            ));
        }

        if (!is_subclass_of($recorderClass, RecorderInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Recorder class `%s` must implement RecorderInterface.',
                $recorderClass,
            ));
        }

        $reflection = new ReflectionClass($recorderClass);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $recorderClass();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();

                if ($typeName === Rhythm::class) {
                    $dependencies[] = $this->rhythm;
                } elseif ($this->container->has($typeName)) {
                    $dependencies[] = $this->container->get($typeName);
                } elseif ($typeName === 'array' && $parameter->getName() === 'config') {
                    $dependencies[] = $config;
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new InvalidArgumentException(sprintf(
                        "Cannot resolve dependency '%s' for recorder '%s'. " .
                        'Register it in the container or provide a default value.',
                        $typeName,
                        $recorderClass,
                    ));
                }
            }
        }

        return new $recorderClass(...$dependencies);
    }
}

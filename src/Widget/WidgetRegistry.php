<?php
declare(strict_types=1);

namespace Rhythm\Widget;

use InvalidArgumentException;
use Rhythm\Rhythm;

/**
 * Widget Registry
 *
 * Manages registration and retrieval of dashboard widgets.
 */
class WidgetRegistry
{
    /**
     * Rhythm instance
     *
     * @var \Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Registered widgets
     *
     * @var array<string, array{class: string, config: array}>
     */
    protected array $widgets = [];

    /**
     * Widget instances cache
     *
     * @var array<string, \Rhythm\Widget\BaseWidget>
     */
    protected array $instances = [];

    /**
     * Constructor
     *
     * @param \Rhythm\Rhythm $rhythm Rhythm instance
     */
    public function __construct(Rhythm $rhythm)
    {
        $this->rhythm = $rhythm;
    }

    /**
     * Register a widget
     *
     * @param string $name Widget name
     * @param string $className Widget class name
     * @param array $config Widget configuration
     * @return void
     * @throws \InvalidArgumentException
     */
    public function register(string $name, string $className, array $config = []): void
    {
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Widget class '{$className}' does not exist");
        }

        if (!is_subclass_of($className, BaseWidget::class)) {
            throw new InvalidArgumentException("Widget class '{$className}' must extend BaseWidget");
        }

        $this->widgets[$name] = [
            'class' => $className,
            'config' => $config,
        ];
    }

    /**
     * Get a widget instance
     *
     * @param string $name Widget name
     * @return \Rhythm\Widget\BaseWidget
     * @throws \InvalidArgumentException
     */
    public function get(string $name): BaseWidget
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException("Widget '{$name}' is not registered");
        }

        if (!isset($this->instances[$name])) {
            $widgetInfo = $this->widgets[$name];
            $className = $widgetInfo['class'];
            $config = $widgetInfo['config'];

            $config['widgetName'] = $name;

            /** @var \Rhythm\Widget\BaseWidget $widget */
            $widget = new $className($this->rhythm, $config);
            $this->instances[$name] = $widget;
        }

        return $this->instances[$name];
    }

    /**
     * Check if widget is registered
     *
     * @param string $name Widget name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->widgets[$name]);
    }

    /**
     * Get all registered widgets
     *
     * @return array<string, \Rhythm\Widget\BaseWidget>
     */
    public function getAll(): array
    {
        $widgets = [];

        foreach (array_keys($this->widgets) as $name) {
            $widgets[$name] = $this->get($name);
        }

        return $widgets;
    }

    /**
     * Get all registered widget names
     *
     * @return array<string>
     */
    public function getNames(): array
    {
        return array_keys($this->widgets);
    }

    /**
     * Unregister a widget
     *
     * @param string $name Widget name
     * @return void
     */
    public function unregister(string $name): void
    {
        unset($this->widgets[$name], $this->instances[$name]);
    }

    /**
     * Clear all registered widgets
     *
     * @return void
     */
    public function clear(): void
    {
        $this->widgets = [];
        $this->instances = [];
    }
}

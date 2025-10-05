<?php
declare(strict_types=1);

namespace Rhythm\Widget\Trait;

/**
 * SortableTrait
 *
 * Provides optional sorting functionality for widgets.
 * This trait can be used by any widget that needs sorting capabilities.
 */
trait SortableTrait
{
    /**
     * Current sort order from options
     *
     * @var string|null
     */
    protected ?string $currentSortOrder = null;

    /**
     * Get sort options for this widget
     *
     * @return array Array of sort options [value => label]
     */
    protected function getSortOptions(): array
    {
        return [];
    }

    /**
     * Get default sort order for this widget
     *
     * @return string Default sort value
     */
    protected function getDefaultSort(): string
    {
        $options = $this->getSortOptions();

        return $options ? array_key_first($options) : '';
    }

    /**
     * Get current sort order from options
     *
     * @param array $options Widget options containing sort parameter
     * @return string Current sort order
     */
    protected function getSortOrder(array $options = []): string
    {
        if ($this->currentSortOrder !== null) {
            return $this->currentSortOrder;
        }

        $sortParam = $options['sort'] ?? null;

        $validOptions = array_keys($this->getSortOptions());
        if ($sortParam && in_array($sortParam, $validOptions)) {
            $this->currentSortOrder = $sortParam;

            return $sortParam;
        }

        $defaultSort = $this->getDefaultSort();
        $this->currentSortOrder = $defaultSort;

        return $defaultSort;
    }

    /**
     * Set sort order (for programmatic use)
     *
     * @param string $sortOrder Sort order to set
     * @return void
     */
    protected function setSortOrder(string $sortOrder): void
    {
        $validOptions = array_keys($this->getSortOptions());
        if (in_array($sortOrder, $validOptions)) {
            $this->currentSortOrder = $sortOrder;
        }
    }

    /**
     * Get cache key with sort parameter
     *
     * @param string $baseKey Base cache key
     * @param array $options Widget options containing sort
     * @return string Cache key with sort parameter
     */
    protected function getSortCacheKey(string $baseKey, array $options = []): string
    {
        $sortOrder = $this->getSortOrder($options);
        if (empty($sortOrder)) {
            return $baseKey;
        }

        return $baseKey . '_sort_' . $sortOrder;
    }

    /**
     * Check if widget supports sorting
     *
     * @return bool True if widget has sort options
     */
    public function isSortable(): bool
    {
        return !empty($this->getSortOptions());
    }

    /**
     * Get widget name for parameter isolation
     *
     * @return string|null Widget name or null
     */
    protected function getWidgetName(): ?string
    {
        return $this->config['widgetName'] ?? $this->config['name'] ?? null;
    }

    /**
     * Get sort configuration for templates
     *
     * @param array $options Widget options
     * @return array Sort configuration array
     */
    public function getSortConfig(array $options = []): array
    {
        if (!$this->isSortable()) {
            return [];
        }

        return [
            'options' => $this->getSortOptions(),
            'current' => $this->getSortOrder($options),
            'default' => $this->getDefaultSort(),
            'widget' => $this->getWidgetName(),
            'param' => 'sort',
        ];
    }
}

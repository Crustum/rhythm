<?php
declare(strict_types=1);

namespace Rhythm\View\Helper;

use Cake\View\Helper;
use Exception;

/**
 * Rhythm Helper
 *
 * Provides convenient methods for creating Rhythm components.
 */
class RhythmHelper extends Helper
{
    /**
     * Create a Rhythm card component
     *
     * @param array $options Card options
     * @param string $content Card content
     * @return string
     */
    public function card(array $options = [], string $content = ''): string
    {
        return $this->startCard($options) . $content . $this->endCard();
    }

    /**
     * Starts a card component, rendering the opening tags.
     *
     * @param array<string, mixed> $options Card options.
     * @return string The opening HTML for the card.
     */
    public function startCard(array $options = []): string
    {
        $cols = $options['cols'] ?? 6;
        $rows = $options['rows'] ?? 1;
        $class = $options['class'] ?? '';
        $id = $options['id'] ?? '';
        $widget = $options['widget'] ?? '';
        $loading = $options['loading'] ?? false;

        $defaultCols = is_array($cols) ? ($cols['default'] ?? 12) : $cols;
        $lgCols = is_array($cols) ? ($cols['lg'] ?? null) : null;

        $defaultSpan = 'rhythm-col-span-' . $defaultCols;
        $lgSpan = $lgCols ? 'lg:rhythm-col-span-' . $lgCols : null;

        $cardClasses = [
            'widget-container',
            'rhythm-card',
            $defaultSpan,
            $lgSpan,
            $class,
        ];

        $cardAttributes = [
            'class' => implode(' ', array_filter($cardClasses)),
            'data-cols' => is_array($cols) ? json_encode($cols) : $cols,
            'data-rows' => $rows,
        ];

        if ($widget) {
            $cardAttributes['data-widget'] = $widget;
        }

        if ($id) {
            $cardAttributes['id'] = $id;
        }

        if ($loading) {
            $cardAttributes['data-loading'] = 'true';
        }

        $attributesString = '';
        foreach ($cardAttributes as $key => $value) {
            $attributesString .= ' ' . $key . '="' . h((string)$value) . '"';
        }

        return '<div' . $attributesString . '><div class="widget-wrapper">';
    }

    /**
     * Ends a card component, rendering the closing tags.
     *
     * @return string The closing HTML for the card.
     */
    public function endCard(): string
    {
        return '</div></div>';
    }

    /**
     * Create a card header component
     *
     * @param string $name Header name/title
     * @param array<string, mixed> $options Header options
     * @return string
     */
    public function cardHeader(string $name, array $options = []): string
    {
        return $this->getView()->element('Rhythm.components/card-header', array_merge($options, [
            'name' => $name,
        ]));
    }

    /**
     * Create a stat component
     *
     * @param string $label Stat label
     * @param mixed $value Stat value
     * @param string $unit Stat unit
     * @param array<string, mixed> $options Stat options
     * @return string
     */
    public function stat(string $label, mixed $value, string $unit = '', array $options = []): string
    {
        return $this->getView()->element('Rhythm.components/stat', array_merge($options, [
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
        ]));
    }

    /**
     * Create a badge component
     *
     * @param string $text Badge text
     * @param string $variant Badge variant
     * @param array<string, mixed> $options Badge options
     * @return string
     */
    public function badge(string $text, string $variant = 'secondary', array $options = []): string
    {
        $options += [
            'size' => 'md',
        ];

        return $this->getView()->element('Rhythm.components/badge', compact('text', 'variant', 'options'));
    }

    /**
     * Create a scroll component
     *
     * @param string $content Scrollable content
     * @param array<string, mixed> $options Scroll options
     * @return string
     */
    public function scroll(string $content, array $options = []): string
    {
        return $this->getView()->element('Rhythm.components/scroll', array_merge($options, [
            'content' => $content,
        ]));
    }

    /**
     * Create a stats grid
     *
     * @param array<string, mixed> $stats Array of stat configurations
     * @param array<string, mixed> $options Grid options
     * @return string
     */
    public function statsGrid(array $stats, array $options = []): string
    {
        $columns = $options['columns'] ?? 'repeat(auto-fit, minmax(200px, 1fr))';
        $gap = $options['gap'] ?? '1.5rem';
        $class = $options['class'] ?? '';

        $html = '<div class="stats-grid ' . $class . '" style="display: grid; grid-template-columns: '
            . $columns . '; gap: ' . $gap . ';">';

        foreach ($stats as $stat) {
            $html .= '<div class="stat-card">';
            $html .= $this->stat($stat['label'], $stat['value'], $stat['unit'] ?? '', $stat);
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Create a status indicator
     *
     * @param string $status Status level
     * @param string $text Status text
     * @param array<string, mixed> $options Options
     * @return string
     */
    public function status(string $status, string $text = '', array $options = []): string
    {
        $variants = [
            'normal' => 'success',
            'warning' => 'warning',
            'critical' => 'critical',
            'unknown' => 'unknown',
            'good' => 'success',
            'bad' => 'critical',
        ];

        $variant = $variants[$status] ?? $status;
        $displayText = $text ?: ucfirst($status);

        return $this->badge($displayText, $variant, $options);
    }

    /**
     * Renders a data-driven table component.
     *
     * @param array<string> $head An array of header cell contents.
     * @param array<array<string>> $body A 2D array of body row and cell contents.
     * @param array<string, mixed> $options Additional options for the table.
     * @return string The rendered table HTML.
     */
    public function table(array $head, array $body, array $options = []): string
    {
        return $this->getView()->element('Rhythm.components/table', [
            'head' => $head,
            'body' => $body,
        ] + $options);
    }

    /**
     * Renders a summary stats component.
     *
     * @param array<string, mixed> $stats An array of stat items.
     * @return string The rendered summary stats HTML.
     */
    public function summaryStats(array $stats): string
    {
        return $this->getView()->element('Rhythm.components/summary_stats', compact('stats'));
    }

    /**
     * Renders a full widget by name, passing data and configuration to it.
     *
     * @param string $widgetName The name of the widget (e.g., 'server-state').
     * @param array<string, mixed> $config The configuration and data for the widget.
     *   - `widget`: Optional reference to a different widget (e.g., 'mysql_monitor')
     *   - `data`: The data array for the widget's content.
     *   - `options`: An array of display options (e.g., `cols`).
     * @return string The rendered widget HTML.
     */
    public function widget(string $widgetName, array $config = []): string
    {
        $view = $this->getView();
        $widgetRegistry = $view->get('widgetRegistry');

        $actualWidgetName = $config['widget'] ?? $widgetName;
        $config['widgetName'] = $widgetName;

        if ($widgetRegistry === null) {
            throw new Exception('Widget registry not found');
        }

        if (!$widgetRegistry->has($actualWidgetName)) {
            throw new Exception('Widget not found: ' . $actualWidgetName);
        }

        $widget = $widgetRegistry->get($actualWidgetName);
        $config['widget'] = $widget;
        $widget->setConfig($config);
        $elementPath = $widget->getTemplate();

        return $this->getView()->element($elementPath, $config);
    }

    /**
     * Renders the script tag with the JSON-encoded chart configurations.
     *
     * @param array<string, mixed> $charts An array of chart configurations.
     * @return string The <script> tag.
     */
    public function renderCharts(array $charts): string
    {
        $json = json_encode($charts, JSON_PARTIAL_OUTPUT_ON_ERROR);

        return sprintf(
            '<script type="application/json" data-rhythm-charts>%s</script>',
            $json,
        );
    }

    /**
     * Prettify large numbers for display (e.g., 536M, 195K, <1000 as is)
     *
     * @param string|float|int $value
     * @return string
     */
    public function prettifyNumber(string|float|int $value): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }
        if (is_string($value)) {
            $value = (float)$value;
        }
        $abs = abs($value);
        if ($abs >= 1000000) {
            return round($value / 1000000, (is_float($value) ? 1 : 0)) . 'M';
        }
        if ($abs >= 1000) {
            return round($value / 1000, (is_float($value) ? 1 : 0)) . 'K';
        }
        if (is_float($value)) {
            return (string)round($value, 1);
        }

        return (string)round($value);
    }
}

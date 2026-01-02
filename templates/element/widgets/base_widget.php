<?php
/**
 * Base Widget Template (New System)
 *
 * Provides the common structure (card, header, footer) for all new
 * component-based widgets. Child widgets extend this template and fill
in the blocks.
 *
 * It expects a `$widget` object to be passed in, which should be an
 * instance of a class that extends Rhythm\Widget\BaseWidget.
 *
 * Available Blocks:
 * - `widget_header`: The header content.
 * - `widget_content`: The main body content.
 * - `widget_footer`: The footer content.
 * - `widget_scripts`: For any widget-specific scripts.
 * @var \Rhythm\Widget\BaseWidget $widget BaseWidget instance
 * @var array $data Widget data
 * @var array $config Widget config
 * @var string $widgetName Widget name
 * @var string $widgetId Widget ID
 * @var array $cols Widget columns
 * @var bool $hasError Widget has error
 * @var \App\View\AppView $this
 */

$widget = $widget ?? new stdClass();
$config = $widget->getConfig() ?? [];

$options = ['period' => $this->get('period', 60)];
$sort = $this->get('sort');
if ($sort !== null) {
    $options['sort'] = $sort;
}

$data = $widget->getData($options) ?? [];

$widgetName = $config['name'] ?? 'Unknown Widget';
$widgetId = $config['id'] ?? str_replace(' ', '-', strtolower($widgetName));
$cols = $config['cols'] ?? ['default' => 12, 'lg' => 6];
$hasError = isset($data['error']);
?>

<?= $this->element('Crustum/Rhythm.components/card', [
    'cols' => $cols,
    'widget' => $widgetId,
    'content' =>
        $this->fetch('widget_header', $this->element('Crustum/Rhythm.components/card-header', [
            'name' => $config['name'] ?? 'Untitled Widget',
            'icon' => $widget->getIcon() ?? 'fas fa-question-circle',
            'widget' => $widgetId,
            'details' => $config['details'] ?? '',
            'sortable' => method_exists($widget, 'isSortable') ? $widget->isSortable() : false,
            'sortConfig' => method_exists($widget, 'getSortConfig') ? $widget->getSortConfig($options) : []
        ])) .

        ($hasError
            ? $this->element('Crustum/Rhythm.components/widget_error', ['details' => $data['error']])
            : $this->fetch('widget_content')
        ) .

        $this->fetch('widget_footer', $this->element('Crustum/Rhythm.components/widget_footer')) .

        $this->fetch('widget_scripts')
]);

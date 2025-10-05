<?php
/**
 * Rhythm Widget Base Template
 *
 * This template provides the common structure for a Rhythm widget.
 * It is purely presentational and expects all data to be provided.
 *
 * Required variables:
 * - `widget`: The widget object instance.
 * - `config`: The widget configuration array from the helper.
 * - `data`: The pre-fetched data for the widget, provided by the child.
 *
 * Child templates are expected to define the 'content' block.
 *
 * @var \Rhythm\Widget\BaseWidget $widget The widget object instance.
 * @var array $config The widget configuration array from the helper.
 * @var string $widgetName The name of the widget.
 * @var array $cols The widget columns configuration.
 * @var array $data The widget data.
 * @var string $colClasses The generated column classes.
 * @var \App\View\AppView $this
 */

$config = $widget->getConfig();

$cols = $config['cols'] ?? ['default' => 12];
$id = 'widget-' . str_replace('.', '-', $widgetName);

$cardOptions = [
    'widget' => $widgetName,
    'cols' => $cols,
    'id' => $id,
];

echo $this->Rhythm->startCard($cardOptions);

$sortConfig = $widget->getSortConfig();
$isSortable = !empty($sortConfig);

echo $this->element('Rhythm.components/card-header', [
    'name' => $config['name'] ?? $widgetName,
    'icon' => $config['icon'] ?? $widget->getIcon(),
    'details' => $config['details'] ?? null,
    'widget' => $widgetName,
    'sortable' => $isSortable,
    'sortConfig' => $sortConfig,
]);
?>
<div class="rhythm-card-body">
    <?php if (!empty($data['error'])): ?>
        <?= $this->element('Rhythm.components/widget_error', ['message' => is_string($data['error']) ? $data['error'] : 'An unknown error occurred.']) ?>
    <?php else: ?>
        <?= $this->fetch('widget_body') ?>
    <?php endif; ?>
</div>
<div class="rhythm-card-footer"></div>
<?php
echo $this->Rhythm->endCard();

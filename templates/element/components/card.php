<?php
/**
 * Card Component
 *
 * Base card component for dashboard widgets with grid layout support.
 *
 * Options:
 * - cols: Grid columns to span. Can be a number (1-12), 'full', or an array for responsive sizes (e.g., ['default' => 12, 'lg' => 6]).
 * - rows: Grid rows to span (1-6)
 * - class: Additional CSS classes
 * - id: Element ID
 * - widget: Widget name for refresh functionality
 * - loading: Show loading state
 *
 * @var \App\View\AppView $this
 * @var mixed $content
 */
$cols = $cols ?? 6;
$rows = $rows ?? 1;
$class = $class ?? '';
$id = $id ?? '';
$widget = $widget ?? '';
$loading = $loading ?? false;

$defaultCols = is_array($cols) ? ($cols['default'] ?? 12) : $cols;
$lgCols = is_array($cols) ? ($cols['lg'] ?? null) : null;

$defaultSpan = 'rhythm-col-span-' . ($defaultCols === 'full' ? 'full' : $defaultCols);
$lgSpan = $lgCols ? 'lg:rhythm-col-span-' . $lgCols : null;

$cardClasses = [
    'widget-container',
    'rhythm-card',
    $defaultSpan,
    $lgSpan,
    $class
];

$cardAttributes = [
    'class' => implode(' ', array_filter($cardClasses)),
    'data-cols' => is_array($cols) ? json_encode($cols) : $cols,
    'data-rows' => $rows
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
?>

<div <?= $this->Html->templater()->formatAttributes($cardAttributes) ?>>
    <div class="widget-wrapper">
        <?= $content ?? '' ?>
    </div>
</div>

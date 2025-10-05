<?php
/**
 * Sortable Select Component
 *
 * A generic, attribute-based select component for sorting widgets.
 * All logic is handled by the dashboard.js file.
 *
 * Options:
 * - name: Parameter name for the select
 * - label: Label text for the select
 * - options: Array of options [value => label]
 * - value: Current selected value
 * - widget: Widget name for parameter isolation
 * - class: Additional CSS classes
 *
 * @var \App\View\AppView $this
 */
$name = $name ?? 'sort';
$label = $label ?? 'Sort by';
$options = $options ?? [];
$value = $value ?? '';
$widget = $widget ?? '';
$class = $class ?? '';

$id = 'sort-' . ($widget ? $widget . '-' : '') . uniqid();

$selectClasses = [
    'rhythm-sortable-select',
    'form-control',
    $class
];
?>

<div class="<?= implode(' ', array_filter($selectClasses)) ?>">
    <label for="<?= h($id) ?>" class="control-label">
        <?= h($label) ?>
    </label>
        <select
        id="<?= h($id) ?>"
        name="sort"
        class="rhythm-select rhythm-sortable"
        data-widget="<?= h($widget) ?>"
        data-current="<?= h($value) ?>"
    >
        <?php foreach ($options as $optionValue => $optionLabel): ?>
            <option
                value="<?= h($optionValue) ?>"
                <?= $optionValue === $value ? 'selected' : '' ?>
            >
                <?= h($optionLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

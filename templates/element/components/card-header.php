<?php
/**
 * Card Header Component
 *
 * Provides consistent header styling for dashboard widgets.
 *
 * Options:
 * - name: Header title/name
 * - title: Title attribute for accessibility
 * - details: Additional details text
 * - icon: Icon HTML or class
 * - actions: Action buttons/content
 * - widget: Widget name for refresh button
 * - sortable: Whether widget supports sorting
 * - sortConfig: Sort configuration array
 * - class: Additional CSS classes
 *
 * @var \App\View\AppView $this
 */
$name = $name ?? '';
$title = $title ?? $name;
$details = $details ?? null;
$icon = $icon ?? null;
$actions = $actions ?? null;
$widget = $widget ?? '';
$sortable = $sortable ?? false;
$sortConfig = $sortConfig ?? [];
$class = $class ?? '';

$headerClasses = [
    'widget-header',
    'rhythm-card-header',
    $class
];
?>

<header class="<?= implode(' ', array_filter($headerClasses)) ?>">
    <div class="header-content">
        <div class="header-title-group">
            <?php if ($icon): ?>
                <div class="header-icon">
                    <?php if (is_string($icon) && strpos($icon, '<') === false): ?>
                        <i class="<?= h($icon) ?>"></i>
                    <?php else: ?>
                        <?= $icon ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="header-text">
                <h3 class="header-title" title="<?= h($title) ?>">
                    <?= h($name) ?>
                </h3>
                <?php if ($details): ?>
                    <p class="header-details">
                        <small><?= h($details) ?></small>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($actions || $widget || $sortable): ?>
        <div class="widget-actions header-actions">
            <?= $actions ?? '' ?>

            <?php if ($sortable && !empty($sortConfig)): ?>
                <div class="sort-controls">
                    <?= $this->element('Crustum/Rhythm.components/sortable-select', [
                        'name' => 'sort',
                        'label' => 'Sort',
                        'options' => $sortConfig['options'] ?? [],
                        'value' => $sortConfig['current'] ?? '',
                        'widget' => $sortConfig['widget'] ?? $widget,
                        'class' => 'sort-select-compact'
                    ]) ?>
                </div>
            <?php endif; ?>

            <?php if ($widget): ?>
                <button class="btn btn-sm btn-outline-secondary refresh-widget"
                        data-widget="<?= h($widget) ?>"
                        title="Refresh <?= h($name) ?>">
                    <i class="fas fa-sync-alt"></i>
                    <span class="sr-only">Refresh</span>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</header>

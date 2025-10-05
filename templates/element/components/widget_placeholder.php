<?php
/**
 * Widget Placeholder Component
 *
 * Renders a standardized empty state for a widget.
 *
 * @var \App\View\AppView $this
 * @var string|null $icon The FontAwesome icon class.
 * @var string|null $message The message to display.
 */
$icon = $icon ?? 'fas fa-check-circle';
$message = $message ?? 'No data to display.';
?>
<div class="widget-placeholder">
    <i class="<?= h($icon) ?>"></i>
    <p><?= h($message) ?></p>
</div>
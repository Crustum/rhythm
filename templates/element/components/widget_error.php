<?php
/**
 * Widget Error Component
 *
 * Renders a standardized error state for a widget.
 *
 * @var \App\View\AppView $this
 * @var string|null $message The main error message.
 * @var string|null $details The detailed error string.
 */
$message = $message ?? 'Error loading widget';
$details = $details ?? 'An unknown error occurred.';
?>
<div class="widget-error">
    <i class="fas fa-exclamation-triangle"></i>
    <p><?= h($message) ?></p>
    <small><?= h($details) ?></small>
</div>

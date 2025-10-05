<?php
/**
 * Widget Footer Component
 *
 * Renders a standardized footer for a widget, typically showing
 * the last updated time.
 *
 * @var \App\View\AppView $this
 * @var string|null $text The text to display in the footer.
 */
$text = $text ?? 'Last updated: ' . date('H:i:s');
?>
<div class="widget-footer">
    <small class="text-muted"><?= h($text) ?></small>
</div>
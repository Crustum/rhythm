<?php
/**
 * Widget Layout
 *
 * Clean layout for individual widget AJAX responses.
 * No HTML wrapper, just the widget content for morphdom updates.
 *
 * @var \App\View\AppView $this
 */
?>
<?= $this->fetch('content') ?>

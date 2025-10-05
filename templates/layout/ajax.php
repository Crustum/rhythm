<?php
/**
 * AJAX Layout
 *
 * Clean layout for AJAX responses.
 * No HTML wrapper, just the content for morphdom updates.
 *
 * @var \App\View\AppView $this
 */
?>
<?= $this->fetch('content') ?>

<?php
/**
 * Summary Stats Component
 *
 * Displays a horizontal list of key statistics.
 *
 * Expects:
 * - $stats: An array of stat items, where each item is an array with
 *   'label' and 'value' keys.
 *
 * @var \App\View\AppView $this
 */
$stats = $stats ?? [];
?>
<div class="rhythm-summary-stats">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-item">
            <span class="stat-label"><?= h($stat['label'] ?? '') ?>:</span>
            <span class="stat-value"><?= ($stat['escape'] ?? true) !== false ? h($stat['value'] ?? '') : $stat['value'] ?? '' ?></span>
        </div>
    <?php endforeach; ?>
</div>

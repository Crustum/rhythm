<?php
/**
 * Stat Component
 *
 * Displays a statistic with label, value and optional badge.
 *
 * Options:
 * - label: Stat label/description
 * - value: Main stat value
 * - unit: Unit of measurement
 * - badge: Badge configuration (variant, text)
 * - trend: Trend indicator (up, down, flat)
 * - class: Additional CSS classes
 * - size: Stat size (sm, md, lg)
 * - extra: Additional content below the stat
 *
 * @var \App\View\AppView $this
 */
$label = $label ?? '';
$value = $value ?? 0;
$unit = $unit ?? '';
$badge = $badge ?? null;
$trend = $trend ?? null;
$class = $class ?? '';
$size = $size ?? 'md';
$extra = $extra ?? null;

$statClasses = [
    'rhythm-stat',
    "stat-{$size}",
    $class
];
?>

<div class="<?= implode(' ', array_filter($statClasses)) ?>">
    <?php if ($label): ?>
        <div class="stat-label">
            <?= h($label) ?>
        </div>
    <?php endif; ?>

    <div class="stat-value-group">
        <div class="stat-value">
            <?= h($value) ?>
            <?php if ($unit): ?>
                <span class="stat-unit"><?= h($unit) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($trend): ?>
            <div class="stat-trend trend-<?= h($trend) ?>">
                <?php if ($trend === 'up'): ?>
                    <i class="fas fa-arrow-up"></i>
                <?php elseif ($trend === 'down'): ?>
                    <i class="fas fa-arrow-down"></i>
                <?php else: ?>
                    <i class="fas fa-minus"></i>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($badge): ?>
        <div class="stat-badge">
            <?= $this->element('Rhythm.components/badge', $badge) ?>
        </div>
    <?php endif; ?>

    <?php if ($extra): ?>
        <div class="stat-extra">
            <?= $extra ?>
        </div>
    <?php endif; ?>
</div>

<?php
/**
 * Chart Container Component
 *
 * Wrapper for charts and graphs with consistent styling.
 *
 * Options:
 * - charts: Array of chart data
 * - class: Additional CSS classes
 * - height: Chart height
 *
 * @var \App\View\AppView $this
 */
$charts = $charts ?? [];
$class = $class ?? '';
$height = $height ?? '120px';

$containerClasses = [
    'rhythm-chart-container',
    $class
];
?>

<?php if (!empty($charts)): ?>
<div class="<?= implode(' ', array_filter($containerClasses)) ?>">
    <div class="rhythm-grid rhythm-grid-cols-2 rhythm-gap-md">
        <?php foreach ($charts as $chartName => $chartData): ?>
            <div class="rhythm-col-span-1">
                <div class="chart-wrapper" style="height: <?= h($height) ?>;">
                    <canvas data-chart-name="<?= h($chartName) ?>"></canvas>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

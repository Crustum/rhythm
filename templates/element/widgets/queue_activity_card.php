<?php
/**
 * Queue Activity Card Element
 *
 * Reusable card component for displaying queue activity charts.
 *
 * @var string $queueName Queue name
 * @var array $chartData Chart data
 * @var array $legendColors Color array for legend (label => color)
 * @var array $legendData Legend data
 * @var string $icon Icon class
 * @var string $title Title
 * @var string $content Content
 * @var string $placeholder Placeholder message
 * @var \App\View\AppView $this
 */
?>
<?php if (isset($chartData)): ?>
    <div class="rhythm-card" data-queue="<?= h($queueName) ?>">
        <?= $this->element('Crustum/Rhythm.widgets/queue_card_header', [
            'icon' => 'fas fa-list',
            'title' => $queueName . ' Job Activity'
        ]) ?>

        <div class="widget-content">
            <div class="chart-container" data-vdom-ignore="true">
                <canvas data-chart-name="<?= $chartData['chartId'] ?>"></canvas>
            </div>

            <!-- Queue State Legend -->
            <div class="rhythm-flex rhythm-gap-md rhythm-items-center rhythm-flex-wrap">
                <?php
                $legend = $this->Chart->buildLegend($chartData['legendData'], ['colors' => $legendColors]);
                foreach ($legend as $legendItem): ?>
                    <div class="rhythm-flex rhythm-items-center rhythm-gap-xs">
                        <span class="rhythm-badge badge-sm" style="background: <?= $legendItem['color'] ?>; color: #374151;">&nbsp;</span>
                        <span class="rhythm-text-sm rhythm-text-secondary">
                            <?= $legendItem['label'] ?>: <?= number_format($legendItem['value']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="rhythm-card">
        <?= $this->element('Crustum/Rhythm.widgets/queue_card_header', [
            'icon' => 'fas fa-list',
            'title' => $queueName . ' Job Activity'
        ]) ?>
        <div class="widget-content">
            <?= $this->element('Crustum/Rhythm.components/widget_placeholder', ['message' => 'No activity data for this queue.']) ?>
        </div>
    </div>
<?php endif; ?>

<?php
/**
 * Queue Activity Sub-Element
 *
 * Displays queue activity graphs (queued, processing, processed, released, failed)
 * This is the left side of the full-size queues widget.
 *
 * @var \App\View\AppView $this
 */

$queues = $queues ?? [];
$allChartConfigs = [];
?>

<?php if (empty($queues)): ?>
    <?= $this->element('Crustum/Rhythm.components/widget_placeholder', ['message' => 'No queue activity recorded.']) ?>
<?php else: ?>
    <?php foreach ($queues as $queueName => $queueCollection): ?>
        <?php
        $queueData = $queueCollection->toArray();
        $organizedData = $queueData;

        if (empty($organizedData)) {
            continue;
        }
        ?>

        <div class="rhythm-card rhythm-mb-lg" data-queue="<?= h($queueName) ?>">
            <div class="rhythm-card-header">
                <div class="header-content">
                    <div class="header-title-group">
                        <div class="header-icon">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="header-text">
                            <h3 class="header-title"><?= h($queueName) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="widget-content">
                <?php
                $chartData = [];
                $colors = [
                    'queues.queued' => 'rgba(107,114,128,0.5)',
                    'queues.processing' => 'rgba(147,51,234,0.5)',
                    'queues.processed' => '#9333ea',
                    'queues.released' => '#eab308',
                    'queues.failed' => '#e11d48',
                ];

                $stateLabels = [
                    'queues.queued' => 'Queued',
                    'queues.processing' => 'Processing',
                    'queues.processed' => 'Processed',
                    'queues.released' => 'Released',
                    'queues.failed' => 'Failed',
                ];

                $chartColors = [];
                foreach ($organizedData as $stateType => $stateData) {
                    $chartColors[] = $colors[$stateType] ?? '#4A5568';

                    foreach ($stateData as $timestamp => $count) {
                        if ($count !== null) {
                            $date = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
                            $formattedTime = $date ? $date->format('d.m.y, H:i') : $timestamp;
                            $chartData[$stateLabels[$stateType] ?? $stateType][$formattedTime] = (float)$count;
                        }
                    }
                }

                $chartId = 'queue-activity-' . str_replace([':', '-', '.'], ['-', '', ''], $queueName);

                $chartConfig = $this->Chart->createLineChart($chartId, $chartData, [
                    'colors' => $chartColors,
                    'label' => $queueName,
                ]);
                $allChartConfigs[] = $chartConfig;
                ?>

                <div class="chart-container" data-vdom-ignore="true">
                    <canvas data-chart-name="<?= $chartId ?>"></canvas>
                </div>

                <!-- Queue State Legend -->
                <div class="rhythm-flex rhythm-gap-md rhythm-items-center rhythm-flex-wrap">
                    <?php foreach ($organizedData as $stateType => $stateData): ?>
                        <?php if (isset($colors[$stateType]) && isset($stateLabels[$stateType])): ?>
                            <?php
                            $totalCount = 0;
                            foreach ($stateData as $count) {
                                if ($count !== null) {
                                    $totalCount += (float)$count;
                                }
                            }
                            ?>
                            <div class="rhythm-flex rhythm-items-center rhythm-gap-xs">
                                <span class="rhythm-badge badge-sm" style="background: <?= $colors[$stateType] ?>; color: #374151;">&nbsp;</span>
                                <span class="rhythm-text-sm rhythm-text-secondary">
                                    <?= $stateLabels[$stateType] ?>: <?= number_format($totalCount) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($allChartConfigs)): ?>
    <?php echo $this->Rhythm->renderCharts($allChartConfigs); ?>
<?php endif; ?>

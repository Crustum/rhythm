<?php
/**
 * Queue Statistics Sub-Element
 *
 * Displays queue statistics graphs (depth, health, average wait time, maximum wait time)
 * This is the right side of the full-size queues widget.
 *
 * @var \App\View\AppView $this
 */

$queueStats = $queueStats ?? [];
$allChartConfigs = [];
?>

<?php if (empty($queueStats)): ?>
    <?= $this->element('Crustum/Rhythm.components/widget_placeholder', ['message' => 'No queue statistics recorded.']) ?>
<?php else: ?>
    <?php foreach ($queueStats as $queueName => $queueCollection): ?>
        <?php
        $queueData = $queueCollection->toArray();
        $organizedData = $queueData;

        if (empty($organizedData)) {
            continue;
        }
        ?>

        <div class="rhythm-card" data-queue="<?= h($queueName) ?>">
            <div class="rhythm-card-header">
                <div class="header-content">
                    <div class="header-title-group">
                        <div class="header-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="header-text">
                            <h3 class="header-title"><?= h($queueName) ?> Stats</h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="widget-content">
                <?php
                $chartData = [];
                $colors = [
                    'queue_depth' => '#3b82f6',
                    'queue_health' => '#10b981',
                    'queue_average_wait_time' => '#f59e0b',
                    'queue_maximum_wait_time' => '#ef4444',
                ];

                $statLabels = [
                    'queue_depth' => 'Depth',
                    'queue_health' => 'Health',
                    'queue_average_wait_time' => 'Avg Wait',
                    'queue_maximum_wait_time' => 'Max Wait',
                ];

                $chartColors = [];
                foreach ($organizedData as $statType => $statData) {
                    $chartColors[] = $colors[$statType] ?? '#4A5568';

                    foreach ($statData as $timestamp => $value) {
                        if ($value !== null) {
                            $date = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
                            $formattedTime = $date ? $date->format('d.m.y, H:i') : $timestamp;
                            $chartData[$statLabels[$statType] ?? $statType][$formattedTime] = (float)$value;
                        }
                    }
                }

                $chartId = 'queue-stats-' . str_replace([':', '-', '.'], ['-', '', ''], $queueName);

                $chartConfig = $this->Chart->createLineChart($chartId, $chartData, [
                    'colors' => $chartColors,
                    'label' => $queueName . ' Stats',
                ]);
                $allChartConfigs[] = $chartConfig;
                ?>

                <div class="chart-container" data-vdom-ignore="true">
                    <canvas data-chart-name="<?= $chartId ?>"></canvas>
                </div>

                <!-- Queue Statistics Legend -->
                <div class="rhythm-flex rhythm-gap-md rhythm-items-center rhythm-flex-wrap">
                    <?php foreach ($organizedData as $statType => $statData): ?>
                        <?php if (isset($colors[$statType]) && isset($statLabels[$statType])): ?>
                            <?php
                            $latestValue = 0;
                            $maxValue = 0;
                            foreach ($statData as $value) {
                                if ($value !== null) {
                                    $latestValue = (float)$value;
                                    $maxValue = max($maxValue, (float)$value);
                                }
                            }

                            $displayValue = match($statType) {
                                'queue_depth' => number_format($latestValue),
                                'queue_health' => number_format($latestValue, 1) . '%',
                                'queue_average_wait_time' => number_format($latestValue, 1) . 's',
                                'queue_maximum_wait_time' => number_format($latestValue, 1) . 's',
                                default => number_format($latestValue)
                            };
                            ?>
                            <div class="rhythm-flex rhythm-items-center rhythm-gap-xs">
                                <span class="rhythm-badge badge-sm" style="background: <?= $colors[$statType] ?>; color: #374151;">&nbsp;</span>
                                <span class="rhythm-text-sm rhythm-text-secondary">
                                    <?= $statLabels[$statType] ?>: <?= $displayValue ?>
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

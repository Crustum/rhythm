<?php
/**
 * Queues Widget - Card Layout
 *
 * @var \Rhythm\Widget\QueuesWidget $widget
 * @var array $config
 * @var string $widgetName
 * @var array $data
 *
 * This template displays queue metrics with individual queue cards:
 * - Each queue gets its own row
 * - Left side (50%): Queue activity graph (queued, processing, processed, released, failed)
 * - Right side (50%): Queue statistics graph (depth, health, average wait time, maximum wait time)
 * - Queues are displayed row by row with same queue name in same row
 *
 * @var \App\View\AppView $this
 */
$this->set('widget', $widget);
$this->set('config', $config);
$this->set('widgetName', $widgetName);
$this->loadHelper('Rhythm.Chart');

$period = (int) $this->getRequest()->getQuery('period', 60);
$options = ['period' => $period];
$sort = $this->get('sort');
if ($sort !== null) {
    $options['sort'] = $sort;
}
$data = $widget->getData($options);
$this->set('data', $data);

$this->extend('Rhythm.widgets/widget_base');

$queues = $data['queues'] ?? [];
$queueStats = $data['queueStats'] ?? [];
$summary = $data['summary'] ?? [];
$chartData = $data['chartData'] ?? [];

$this->start('widget_body');
?>

<?php if (empty($queues) && empty($queueStats)): ?>
    <?= $this->element('Rhythm.components/widget_placeholder', ['message' => 'No queue data recorded.']) ?>
<?php else: ?>
    <!-- Summary Statistics -->
    <?php
    $summaryStats = [
        [
            'label' => 'Success Rate',
            'value' => ($summary['success_rate'] ?? 0) . '%'
        ],
        [
            'label' => 'Processed',
            'value' => $this->Sampling->formatMagnifiedValue(
                $summary['total_processed'] ?? 0,
                $summary['raw_processed'] ?? $summary['total_processed'] ?? 0,
                $summary['sample_rate'] ?? 1.0
            ),
            'escape' => false
        ],
        [
            'label' => 'Failed',
            'value' => $this->Sampling->formatMagnifiedValue(
                $summary['total_failed'] ?? 0,
                $summary['raw_failed'] ?? $summary['total_failed'] ?? 0,
                $summary['sample_rate'] ?? 1.0
            ),
            'escape' => false
        ],
        [
            'label' => 'Queued',
            'value' => $this->Sampling->formatMagnifiedValue(
                $summary['total_queued'] ?? 0,
                $summary['raw_queued'] ?? $summary['total_queued'] ?? 0,
                $summary['sample_rate'] ?? 1.0
            ),
            'escape' => false
        ]
    ];
    ?>
    <?= $this->Rhythm->summaryStats($summaryStats) ?>

    <!-- Individual Queue Cards - Row by Row Layout -->
    <?php
    $allChartConfigs = [];
    ?>

    <?php foreach ($chartData as $queueName => $queueChartData): ?>
        <div class="rhythm-grid rhythm-grid-cols-2 rhythm-gap-lg rhythm-mb-lg">
            <!-- Left Side: Queue Activity -->
            <div class="rhythm-col-span-1">
                <?php
                $activityData = $queueChartData['activity'] ?? null;
                if ($activityData && isset($activityData['chartData'])) {
                    // Use colors from chart data (provided by the trait)
                    $activityColors = $activityData['colors'] ?? [];

                    $chartConfig = $this->Chart->createLineChart(
                        $activityData['chartId'],
                        $activityData['chartData'],
                        ['colors' => $activityColors]
                    );
                    $allChartConfigs[] = $chartConfig;
                }
                echo $this->element('Rhythm.widgets/queue_activity_card', [
                    'queueName' => $queueName,
                    'chartData' => $activityData,
                    'legendColors' => $activityColors ?? []
                ]);
                ?>
            </div>
            <!-- Right Side: Queue Statistics -->
            <div class="rhythm-col-span-1">
                <?php
                $statsData = $queueChartData['statistics'] ?? null;
                if ($statsData && isset($statsData['chartData'])) {
                    // Use colors from chart data (provided by the trait)
                    $statisticsColors = $statsData['colors'] ?? [];

                    $chartConfig = $this->Chart->createLineChart(
                        $statsData['chartId'],
                        $statsData['chartData'],
                        ['colors' => $statisticsColors]
                    );
                    $allChartConfigs[] = $chartConfig;
                }
                echo $this->element('Rhythm.widgets/queue_statistics_card', [
                    'queueName' => $queueName,
                    'chartData' => $statsData,
                    'legendColors' => $statisticsColors ?? []
                ]);
                ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($allChartConfigs)): ?>
    <?php echo $this->Rhythm->renderCharts($allChartConfigs); ?>
<?php endif; ?>

<?php $this->end(); ?>

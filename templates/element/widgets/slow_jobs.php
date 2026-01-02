<?php
/**
 * Slow Jobs Widget
 *
 * This template fetches its own data and then extends the base widget
 * to handle the presentation.
 *
 * @var \App\View\AppView $this
 * @var mixed $config
 * @var object $widget
 * @var mixed $widgetName
 */
$this->set('widget', $widget);
$this->set('config', $config);
$this->set('widgetName', $widgetName);

$period = (int) $this->getRequest()->getQuery('period', 60);
$options = ['period' => $period];
$sort = $this->get('sort');
if ($sort !== null) {
    $options['sort'] = $sort;
}
$data = $widget->getData($options);
$this->set('data', $data);

$this->extend('Crustum/Rhythm.widgets/widget_base');

$this->start('widget_body');

$jobsData = $data['jobs'] ?? [];

if (empty($jobsData)) {
    echo $this->element('Crustum/Rhythm.components/widget_placeholder', [
        'message' => 'No slow jobs recorded in the selected period.'
    ]);
} else {
    $totalCountDisplay = $this->Sampling->formatMagnifiedValue(
        $data['total_count'] ?? 0,
        $data['raw_total_count'] ?? $data['total_count'] ?? 0,
        $data['sample_rate'] ?? 1.0
    );

    $summaryStats = [
        ['label' => 'Total Jobs', 'value' => $totalCountDisplay, 'escape' => false],
        ['label' => 'Max Duration', 'value' => ($data['max_duration'] ?? 0) . 'ms'],
    ];

    $head = ['Job', 'Status', 'Duration', 'Count'];
    $body = [];
    foreach ($jobsData as $job) {
        $jobName = '<code>' . h($job['job']) . '</code>';
        if (!empty($job['threshold'])) {
            $jobName .= '<br><small class="text-muted">' . h($job['threshold']) . 'ms threshold</small>';
        }

        $status = '<span class="badge ' . h($job['status_class']) . '">' . h(ucfirst($job['status'])) . '</span>';
        $duration = round($job['max_duration'] ?? 0, 1) . 'ms';

        $countDisplay = $this->Sampling->formatMagnifiedValue(
            $job['count'] ?? 0,
            $job['raw_count'] ?? $job['count'] ?? 0,
            $job['sample_rate'] ?? 1.0
        );

        $body[] = [$jobName, $status, $duration, $countDisplay];
    }
?>
    <div class="widget-content">
        <?= $this->Rhythm->summaryStats($summaryStats) ?>
        <?= $this->Rhythm->scroll($this->Rhythm->table($head, $body)) ?>
    </div>
<?php
}
$this->end();

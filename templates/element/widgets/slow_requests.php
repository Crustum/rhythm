<?php
/**
 * Slow Requests Widget
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

$requestsData = $data['requests'] ?? [];

if (empty($requestsData)) {
    echo $this->element('Crustum/Rhythm.components/widget_placeholder', [
        'message' => 'No slow requests recorded in the selected period.'
    ]);
} else {
    $totalCountDisplay = $this->Sampling->formatMagnifiedValue(
        $data['total_count'] ?? 0,
        $data['raw_total_count'] ?? $data['total_count'] ?? 0,
        $data['sample_rate'] ?? 1.0
    );

    $summaryStats = [
        ['label' => 'Total Slow', 'value' => $totalCountDisplay, 'escape' => false],
        ['label' => 'Slowest', 'value' => ($data['max_duration'] ?? 0) . 'ms'],
    ];

    $head = ['Request', 'Duration', 'Count'];
    $body = [];
    foreach ($requestsData as $request) {
        $path = '<code>' . h($request['method'] ?? 'GET') . ' ' . h($request['path'] ?? '/') . '</code>';
        $duration = round($request['max_duration'] ?? 0, 1) . 'ms';
        $count = round($request['count'] ?? 0);
        $body[] = [$path, $duration, $count];
    }
?>
    <div class="widget-content">
        <?= $this->Rhythm->summaryStats($summaryStats) ?>
        <?= $this->Rhythm->scroll($this->Rhythm->table($head, $body)) ?>
    </div>
<?php
}
$this->end();


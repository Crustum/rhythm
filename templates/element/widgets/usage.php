<?php
/**
 * Usage Widget
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

if (empty($data['requests'])) {
    echo $this->element('Crustum/Rhythm.components/widget_placeholder', ['message' => 'No usage data recorded.']);
} else {
    $requestCountDisplay = $this->Sampling->formatMagnifiedValue(
        $data['requests']['count'] ?? 0,
        $data['requests']['raw_count'] ?? $data['requests']['count'] ?? 0,
        $data['requests']['sample_rate'] ?? 1.0
    );

    $requestsStats = [
        'label' => 'Requests',
        'value' => $requestCountDisplay,
        'escape' => false,
        'badge' => [
            'variant' => $data['requests']['status'] ?? 'unknown',
            'text' => ucfirst($data['requests']['status'] ?? 'Unknown')
        ]
    ];

    $responseTimeStats = [
        'label' => 'Response Time',
        'value' => $data['response_time']['average'] ?? 0,
        'unit' => $data['response_time']['unit'] ?? 'ms',
        'badge' => [
            'variant' => $data['response_time']['status'] ?? 'unknown',
            'text' => ucfirst($data['response_time']['status'] ?? 'Unknown')
        ]
    ];

    $memoryStats = [
        'label' => 'Memory Usage',
        'value' => $data['memory_usage']['average'] ?? 0,
        'unit' => $data['memory_usage']['unit'] ?? 'MB',
        'badge' => [
            'variant' => $data['memory_usage']['status'] ?? 'unknown',
            'text' => ucfirst($data['memory_usage']['status'] ?? 'Unknown')
        ]
    ];
?>
    <div class="widget-content">
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
            <?= $this->element('Crustum/Rhythm.components/stat', $requestsStats) ?>
            <?= $this->element('Crustum/Rhythm.components/stat', $responseTimeStats) ?>
            <?= $this->element('Crustum/Rhythm.components/stat', $memoryStats) ?>
        </div>
    </div>
<?php
}
$this->end();
?>

<?php
/**
 * Exceptions Widget
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
$this->extend('Rhythm.widgets/widget_base');

$this->start('widget_body');

$exceptionsData = $data['exceptions'] ?? [];

if (empty($exceptionsData)) {
    echo $this->element('Rhythm.components/widget_placeholder', [
        'message' => 'No exceptions recorded in the selected period.'
    ]);
} else {
    $totalCountDisplay = $this->Sampling->formatMagnifiedValue(
        $data['total_count'] ?? 0,
        $data['raw_total_count'] ?? $data['total_count'] ?? 0,
        $data['sample_rate'] ?? 1.0
    );

    $summaryStats = [
        ['label' => 'Total', 'value' => $totalCountDisplay, 'escape' => false],
        ['label' => 'Unique', 'value' => $data['unique_count'] ?? 0],
    ];

    $head = ['Exception', 'Count', 'Latest'];
    $body = [];
    foreach ($exceptionsData as $exception) {
        $class = '<code>' . h($exception['class'] ?? 'Unknown Exception') . '</code>';
        if (!empty($exception['location'])) {
            $class .= '<br><small class="text-muted">' . h($exception['location']) . '</small>';
        }

        $countDisplay = $this->Sampling->formatMagnifiedValue(
            $exception['count'] ?? 0,
            $exception['raw_count'] ?? $exception['count'] ?? 0,
            $exception['sample_rate'] ?? 1.0
        ) . 'x';

        $latest = !empty($exception['latest'])
            ? '<time datetime="' . date('c', $exception['latest']) . '">' . date('M j, H:i', $exception['latest']) . '</time>'
            : '-';

        $body[] = [$class, $countDisplay, $latest];
    }
?>
    <div class="widget-content">
        <?= $this->Rhythm->summaryStats($summaryStats) ?>
        <?= $this->Rhythm->scroll($this->Rhythm->table($head, $body)) ?>
    </div>
<?php
}
$this->end();


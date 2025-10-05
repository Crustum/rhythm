<?php
/**
 * Slow Outgoing Requests Widget
 *
 * This template fetches its own data and then extends the base widget
 * to handle the presentation.
 *
 * @var \App\View\AppView $this
 * @var mixed $config
 * @var object $widget
 * @var mixed $widgetName
 */
use Cake\Utility\Text;

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

$requestsData = $data['requests'] ?? [];

if (empty($requestsData)) {
    echo $this->element('Rhythm.components/widget_placeholder', [
        'message' => 'No slow outgoing requests recorded.'
    ]);
} else {
    $tableHeaders = ['Request', 'Duration', 'Count'];
    $tableRows = [];
    foreach ($data['requests'] as $request) {
        $badge = $this->Rhythm->badge(
            strtoupper($request['method']),
            $request['status'] === 'critical' ? 'critical' : ($request['status'] === 'warning' ? 'warning' : 'info'),
            ['size' => 'sm']
        );
        $url = Text::truncate($request['url'], 100, ['ellipsis' => '...']);
        $requestCell = "<div class='request-cell'>{$badge} <span class='url'>{$url}</span></div>";

        $countDisplay = $this->Sampling->formatMagnifiedValue(
            $request['count'] ?? 0,
            $request['raw_count'] ?? $request['count'] ?? 0,
            $request['sample_rate'] ?? 1.0
        );

        $tableRows[] = [
            $requestCell,
            $request['max_duration'] . 'ms',
            $countDisplay,
        ];
    }
    echo $this->Rhythm->table($tableHeaders, $tableRows);
}

$this->end();
?>

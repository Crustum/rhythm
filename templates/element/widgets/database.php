<?php
/**
 * Database Widget Template
 *
 * @var \App\View\AppView $this
 * @var array $config
 * @var object $widget
 * @var string $widgetName
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

$hasError = isset($data['error']);
$empty = $data['empty'] ?? true;
$connections = $data['connections'] ?? [];
$colors = $data['colors'] ?? [];
$metrics = $data['metrics'] ?? [];
$title = $data['title'] ?? 'Database Status';
$values = $data['values'] ?? [];
$graphs = $data['graphs'] ?? [];

if ($hasError): ?>
    <?= $this->element('Crustum/Rhythm.components/widget_error', ['error' => $data['error']]) ?>
<?php elseif ($empty): ?>
    <?= $this->element('Crustum/Rhythm.components/widget_placeholder', ['message' => 'No data recorded.']) ?>
<?php else: ?>
    <?php $allChartConfigs = []; ?>

    <!-- Values Section - Always visible -->
    <?php foreach ($connections as $connectionName => $connectionData): ?>
        <?php $allValues = $connectionData['values'] ?? []; ?>
        <?php if (!empty($allValues)): ?>
            <div class="rhythm-flex rhythm-items-center rhythm-gap-lg rhythm-px-md rhythm-mb-xs rhythm-mx-md">
                <span class="rhythm-flex rhythm-items-center rhythm-gap-xs rhythm-min-w-120">
                    <i class="fas fa-database"></i>
                    <span class="rhythm-font-medium rhythm-text-primary rhythm-pl-sm">
                        <?= h($connectionName) ?>
                    </span>
                </span>
                <?php foreach ($allValues as $metric => $value): ?>
                    <span class="rhythm-flex rhythm-items-center rhythm-gap-xs rhythm-ml-lg">
                        <span class="rhythm-badge badge-sm" style="background: #6b7280; color: #374151;">&nbsp;</span>
                        <span class="rhythm-text-secondary rhythm-text-base">
                            <strong class="rhythm-text-primary rhythm-font-semibold rhythm-mr-xs"><?= h($this->Rhythm->prettifyNumber($value ?? 0)) ?></strong>
                            <?= h(ucwords(str_replace('_', ' ', $metric))) ?>
                        </span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Graphs Section - Only if graphs configured -->
    <?php if (!empty($graphs)): ?>
        <?php foreach ($graphs as $aggregateType => $metricColors): ?>
            <div class="rhythm-mb-lg">
                <?php foreach ($connections as $connectionName => $connectionData): ?>
                    <?php
                    $chart = $connectionData['chartData'][$aggregateType] ?? null;
                    $chartColors = $connectionData['mappedGraphs'][$aggregateType] ?? $metricColors;
                    ?>
                    <?php if ($chart): ?>
                        <div class="rhythm-mb-md">
                            <div class="rhythm-h-32 rhythm-bg-gray-50 rhythm-rounded rhythm-p-2">
                                <div class="chart-container" data-vdom-ignore="true">
                                    <canvas data-chart-name="<?= h($chart['chartId']) ?>"></canvas>
                                    <?php
                                    $chartConfig = $this->Chart->createLineChart(
                                        $chart['chartId'],
                                        $chart['chartData'],
                                        ['colors' => $chartColors]
                                    );
                                    $allChartConfigs[] = $chartConfig;
                                    ?>
                                </div>
                            </div>
                            <?php if (!empty($chart['legendData'])): ?>
                                <div class="rhythm-flex rhythm-gap-md rhythm-items-center rhythm-flex-wrap rhythm-mt-sm">
                                    <?php foreach ($chart['legendData'] as $metricKey => $legend): ?>
                                        <?php
                                        $metricColor = $chartColors[$metricKey] ?? $chartColors[$legend['label']] ?? $colors['primary'] ?? '#3b82f6';
                                        ?>
                                        <div class="rhythm-flex rhythm-items-center rhythm-gap-xs">
                                            <span class="rhythm-badge badge-sm" style="background: <?= h($metricColor) ?>; color: #374151;">&nbsp;</span>
                                            <span class="rhythm-text-sm rhythm-text-secondary">
                                                <?= h($legend['label']) ?>: <strong><?= h($this->Rhythm->prettifyNumber($legend['total'] ?? 0)) ?></strong>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($allChartConfigs)): ?>
        <?= $this->Rhythm->renderCharts($allChartConfigs); ?>
    <?php endif; ?>
<?php endif; ?>
<?php $this->end(); ?>

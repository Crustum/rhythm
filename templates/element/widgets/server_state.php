<?php
/**
 * Server State Widget
 *
 * This template handles multi-server scenarios with chart collection.
 * ServerStateWidget always returns: ['servers' => [serverKey => serverData], 'server_count' => N, 'period' => 60]
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

$allCharts = [];
$colors = [
    'cpu' => '#9333ea',
    'memory' => '#f59e0b',
    'storage' => '#9333ea',
    'online' => '#22c55e',
    'offline' => '#ef4444',
];
?>
<div class="servers-grid">
    <?php foreach ($data['servers'] as $serverKey => $server): ?>
        <?php
        $serverName = $server['name'] ?? 'SERVER';
        $serverStatus = $server['status'] ?? 'online';
        $serverStatusColor = $serverStatus === 'online' ? $colors['online'] : $colors['offline'];

        $cpu = $server['cpu'] ?? [];
        $memory = $server['memory'] ?? [];
        $disk = $server['disk'] ?? [];

        $storageArr = $server['storage'] ?? null;
        if (is_array($storageArr) && !empty($storageArr)) {
            $used = $storageArr[0]['used'] ?? 0;
            $total = $storageArr[0]['total'] ?? 0;
        } else {
            $used = $disk['used'] ?? 0;
            $total = $disk['total'] ?? 0;
        }
        $percent = ($total > 0) ? round($used / $total * 100, 1) : 0;
        $diskStats = [
            'label' => 'STORAGE',
            'value' => round($used / 1024, 0),
            'unit' => 'GB',
            'max' => ($total > 0) ? round($total / 1024, 0) : null,
            'percent' => $percent,
            'badge' => [
                'variant' => $disk['status'] ?? 'unknown',
                'text' => ucfirst($disk['status'] ?? 'Unknown')
            ]
        ];

        $cpuStats = [
            'label' => 'CPU',
            'value' => isset($cpu['current']) ? round($cpu['current'], 1) : 0,
            'unit' => '%',
            'badge' => [
                'variant' => $cpu['status'] ?? 'unknown',
                'text' => ucfirst($cpu['status'] ?? 'Unknown')
            ]
        ];
        $memoryStats = [
            'label' => 'MEMORY',
            'value' => isset($memory['current']) ? round($memory['current'] / 1024, 1) : 0,
            'unit' => 'GB',
            'max' => isset($memory['total']) ? round($memory['total'] / 1024, 1) : null,
            'badge' => [
                'variant' => $memory['status'] ?? 'unknown',
                'text' => ucfirst($memory['status'] ?? 'Unknown')
            ]
        ];

        $cpuData = [];
        foreach (($cpu['graph'] ?? []) as $k => $v) {
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $k);
            $k = $date->format('d.m.y, H:i') . ' — ' . (is_numeric($v) ? number_format($v, 2, '.', '') . ' %' : '');
            $cpuData[$k] = is_numeric($v) ? (float)$v : null;
        }
        $memoryData = [];
        foreach (($memory['graph'] ?? []) as $k => $v) {
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $k);
            $k = $date->format('d.m.y, H:i') . ' — ' . (is_numeric($v) ? number_format($v / 1024, 2, '.', '') . ' GB' : '');
            $memoryData[$k] = is_numeric($v) ? round($v / 1024, 2) : null;
        }

        $cpuLatest = !empty($cpuData) ? end($cpuData) : null;
        $memoryLatest = !empty($memoryData) ? end($memoryData) : null;

        $cpuChartId = 'cpu-spark-' . $serverKey;
        $memoryChartId = 'memory-spark-' . $serverKey;

        $cpuChart = $this->Chart->createSparklineChart($cpuChartId, $cpuData, [
            'label' => 'CPU',
            'unit' => '%',
            'color' => $colors['cpu'],
        ]);
        $memoryChart = $this->Chart->createSparklineChart($memoryChartId, $memoryData, [
            'label' => 'Memory',
            'unit' => 'MB',
            'color' => $colors['memory'],
        ]);

        $allCharts[] = $cpuChart;
        $allCharts[] = $memoryChart;
        ?>
        <div class="server-state-ultra-compact">
            <div class="server-info">
                <span class="server-status-dot" style="background: <?= h($serverStatusColor) ?>;"></span>
                <span class="server-icon"><i class="fa fa-server"></i></span>
                <span class="server-name"><?= h($serverName) ?></span>
            </div>
            <div class="stat-group">
                <span class="stat-label">CPU</span>
                <span class="stat-value"><?= $cpuStats['value'] ?></span>
                <span class="stat-unit">%</span>
                <span class="stat-badge badge-<?= h($cpuStats['badge']['variant']) ?>"><?= h($cpuStats['badge']['text']) ?></span>
                <span class="sparkline" data-vdom-ignore="true">
                    <canvas data-chart-name="<?= $cpuChartId ?>"></canvas>
                </span>
                <?php if ($cpuLatest !== null): ?>
                    <span class="sparkline-value"><?= round($cpuLatest, 1) ?></span>
                <?php endif; ?>
            </div>
            <div class="stat-group">
                <span class="stat-label">MEMORY</span>
                <span class="stat-value"><?= $memoryStats['value'] ?></span>
                <span class="stat-unit">GB</span>
                <?php if ($memoryStats['max']): ?>
                    <span class="stat-max">/ <?= $memoryStats['max'] ?>GB</span>
                <?php endif; ?>
                <span class="stat-badge badge-<?= h($memoryStats['badge']['variant']) ?>"><?= h($memoryStats['badge']['text']) ?></span>
                <span class="sparkline" data-vdom-ignore="true">
                    <canvas data-chart-name="<?= $memoryChartId ?>"></canvas>
                </span>
                <?php if ($memoryLatest !== null && $memoryLatest > 0): ?>
                    <span class="sparkline-value"><?= round($memoryLatest / 1024, 1) ?></span>
                <?php endif; ?>
            </div>
            <div class="stat-group">
                <span class="stat-label">STORAGE</span>
                <span class="hdd-circle">
                    <svg viewBox="0 0 38 38">
                        <circle cx="19" cy="19" r="16" fill="none" stroke="#e5e7eb" stroke-width="5" />
                        <circle cx="19" cy="19" r="16" fill="none" stroke="<?= h($colors['storage']) ?>" stroke-width="5" stroke-dasharray="100" stroke-dashoffset="<?= 100 - $diskStats['percent'] ?>" stroke-linecap="round" />
                    </svg>
                    <span class="hdd-circle-value">
                        <?= $diskStats['value'] ?>
                    </span>
                </span>
                <?php if ($diskStats['max']): ?>
                    <span class="stat-max">/ <?= $diskStats['max'] ?>GB</span>
                <?php endif; ?>
                <span class="stat-badge badge-<?= h($diskStats['badge']['variant']) ?>"><?= h($diskStats['badge']['text']) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($allCharts)): ?>
    <?= $this->Rhythm->renderCharts($allCharts) ?>
<?php endif; ?>

<?php
$this->end();

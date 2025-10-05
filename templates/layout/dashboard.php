<?php
/**
 * @var \App\View\AppView $this
 * @var mixed $availableLayouts
 * @var mixed $currentLayout
 * @var mixed $widgets
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rhythm Dashboard (New System)</title>
    <?= $this->AssetCompress->css('Rhythm.rhythm-dashboard.css', ['raw' => \Cake\Core\Configure::read('debug')]) ?>
    <?= $this->AssetCompress->script('Rhythm.rhythm-dashboard.js', ['raw' => \Cake\Core\Configure::read('debug')]) ?>
</head>
<body class="dashboard-body">
    <div class="dashboard-header">
        <div class="dashboard-title">
            <h1><i class="fas fa-heartbeat"></i> Rythm Dashboard</h1>
            <p class="dashboard-subtitle">System Monitoring & Performance</p>
        </div>
        <div class="dashboard-controls">
            <?php if (!empty($availableLayouts)): ?>
            <div class="control-group">
                <label for="layout-selector">Layout:</label>
                <select id="layout-selector" class="form-control">
                    <?php foreach ($availableLayouts as $layoutName => $layoutConfig): ?>
                        <option value="<?= h($layoutName) ?>" <?= $layoutName === $currentLayout ? 'selected' : '' ?>>
                            <?= ucfirst(h($layoutName)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="control-group">
                <label for="period-selector">Period:</label>
                <select id="period-selector" class="form-control">
                    <option value="60" selected>Last Hour</option>
                    <option value="360">Last 6 Hours</option>
                    <option value="1440">Last 24 Hours</option>
                    <option value="10080">Last 7 Days</option>
                </select>
            </div>
            <button id="refresh-all" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Refresh All
            </button>
            <?= $this->element('theme-toggle') ?>
        </div>
    </div>

    <div class="dashboard-container" data-dashboard="main">
        <?= $this->fetch('content') ?>
    </div>

    <div class="dashboard-footer">
        <p>&copy; <?= date('Y') ?> Rhythm Dashboard </span></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.dashboard = new RhythmDashboard({
                refreshInterval: 300,
                widgets: <?= json_encode($widgets ?? []) ?>,
                baseUrl: '<?= $this->Url->build('/rhythm/dashboard/') ?>'
            });
        });
    </script>
</body>
</html>

<?php
/**
 * App Info Widget
 *
 * Displays application information including CakePHP version, PHP info,
 * debug mode, and other application details.
 *
 * @var \App\View\AppView $this
 * @var mixed $config
 * @var object $widget
 * @var mixed $widgetName
 */
$this->set('widget', $widget);
$this->set('config', $config);
$this->set('widgetName', $widgetName);

$data = $widget->getData();
$this->set('data', $data);

$this->extend('Crustum/Rhythm.widgets/widget_base');

$this->start('widget_body');

$appData = $data ?? [];
$environment = $appData['environment'] ?? [];
$application = $appData['application'] ?? [];
$system = $appData['system'] ?? [];

if (isset($appData['error'])) {
    echo $this->element('Crustum/Rhythm.components/widget_error', [
        'message' => 'Application info error: ' . h($appData['error'])
    ]);
} else {
?>
    <div class="widget-content">
        <div class="rhythm-grid rhythm-grid-cols-3 rhythm-gap-md">
            <!-- Environment Section -->
            <div class="rhythm-card">
                <div class="rhythm-card-header">
                    <div class="header-content">
                        <div class="header-title-group rhythm-p-md">
                            <div class="header-icon">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <div class="header-text">
                                <h6 class="header-title">Environment</h6>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rhythm-card-body rhythm-p-md">
                    <?php foreach ($environment as $label => $value): ?>
                        <div class="rhythm-flex rhythm-justify-between rhythm-items-center rhythm-mb-sm">
                            <span class="rhythm-text-secondary rhythm-text-sm"><?= h($label) ?>:</span>
                            <span class="rhythm-text-end">
                                <?php if ($label === 'Debug Mode'): ?>
                                    <?= $this->Rhythm->badge($value, $value === 'Enabled' ? 'warning' : 'success', ['size' => 'sm']) ?>
                                <?php else: ?>
                                    <code class="rhythm-text-sm"><?= h($value) ?></code>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Application Section -->
            <div class="rhythm-card">
                <div class="rhythm-card-header">
                    <div class="header-content">
                        <div class="header-title-group rhythm-p-md">
                            <div class="header-icon">
                                <i class="fas fa-cube"></i>
                            </div>
                            <div class="header-text">
                                <h6 class="header-title">Application</h6>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rhythm-card-body rhythm-p-md">
                    <?php foreach ($application as $label => $value): ?>
                        <div class="rhythm-flex rhythm-justify-between rhythm-items-center rhythm-mb-sm">
                            <span class="rhythm-text-secondary rhythm-text-sm"><?= h($label) ?>:</span>
                            <span class="rhythm-text-end">
                                <?php if ($label === 'Application Path'): ?>
                                    <code class="rhythm-text-sm rhythm-text-tertiary"><?= h($value) ?></code>
                                <?php else: ?>
                                    <code class="rhythm-text-sm"><?= h($value) ?></code>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- System Section -->
            <div class="rhythm-card">
                <div class="rhythm-card-header">
                    <div class="header-content">
                        <div class="header-title-group rhythm-p-md">
                            <div class="header-icon">
                                <i class="fas fa-server"></i>
                            </div>
                            <div class="header-text">
                                <h6 class="header-title">System</h6>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rhythm-card-body rhythm-p-md">
                    <?php foreach ($system as $label => $value): ?>
                        <div class="rhythm-flex rhythm-justify-between rhythm-items-center rhythm-mb-sm">
                            <span class="rhythm-text-secondary rhythm-text-sm"><?= h($label) ?>:</span>
                            <span class="rhythm-text-end">
                                <code class="rhythm-text-sm"><?= h($value) ?></code>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php
}
$this->end();
?>

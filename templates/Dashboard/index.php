<?php
/**
 * Rhythm New System Dashboard
 *
 * This template renders the dashboard by directly calling self-contained
 * widget elements via the RhythmHelper, passing in layout configuration.
 *
 * @var \App\View\AppView $this
 * @var mixed $layoutConfig
 */
$this->assign('title', 'Rhythm Dashboard (New System)');

$this->loadHelper('Rhythm.Rhythm');
$this->loadHelper('Rhythm.Chart');
?>

<div class="dashboard-grid rhythm-grid rhythm-grid-cols-12 rhythm-gap-md" data-dashboard="new-system">
    <?php if (!empty($layoutConfig)): ?>
        <?php foreach ($layoutConfig as $widgetName => $widgetConfig): ?>
            <?= $this->Rhythm->widget($widgetName, $widgetConfig) ?>
        <?php endforeach; ?>
    <?php else: ?>
        <?= $this->Rhythm->widget('server-state', [
            'cols' => ['default' => 12, 'lg' => 12],
        ]) ?>
        <?= $this->Rhythm->widget('slow-queries', [
            'cols' => ['default' => 12, 'lg' => 6],
        ]) ?>
        <?= $this->Rhythm->widget('slow-requests', [
            'cols' => ['default' => 12, 'lg' => 6],
        ]) ?>
        <?= $this->Rhythm->widget('exceptions', [
            'cols' => ['default' => 12, 'lg' => 6],
        ]) ?>
        <?= $this->Rhythm->widget('usage', [
            'cols' => ['default' => 12, 'lg' => 6],
        ]) ?>
        <?= $this->Rhythm->widget('queues', [
            'cols' => ['default' => 12, 'lg' => 12],
        ]) ?>
        <?= $this->Rhythm->widget('cache', [
            'cols' => ['default' => 12, 'lg' => 6],
        ]) ?>
        <?= $this->Rhythm->widget('BlazeCast.connections', [
            'cols' => ['default' => 12, 'lg' => 6],
        ]) ?>
        <?= $this->Rhythm->widget('BlazeCast.messages', [
            'cols' => ['default' => 12, 'lg' => 6],
        ]) ?>
        <?= $this->Rhythm->widget('slow-outgoing-requests', [
            'cols' => ['default' => 12, 'lg' => 6],
        ]) ?>
        <?= $this->Rhythm->widget('slow-jobs', [
            'cols' => ['default' => 12, 'lg' => 12],
        ]) ?>
    <?php endif; ?>
</div>

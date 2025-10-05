<?php
/**
 * Queue Card Header Element
 *
 * Reusable header component for queue cards with icon and title.
 *
 * @var string $icon Icon class
 * @var string $title Title
 * @var \App\View\AppView $this
 */
?>
<div class="rhythm-card-header">
    <div class="header-content">
        <div class="header-title-group">
            <div class="header-icon">
                <i class="<?= $icon ?>"></i>
            </div>
            <div class="header-text">
                <h3 class="header-title"><?= h($title) ?></h3>
            </div>
        </div>
    </div>
</div>

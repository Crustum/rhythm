<?php
/**
 * Scroll Component
 *
 * Scrollable content area with optional fade effect for long content.
 *
 * Options:
 * - expand: Allow content to expand beyond base height
 * - maxHeight: Maximum height for scrollable area
 * - class: Additional CSS classes
 * - fade: Show fade effect at bottom when scrolled
 *
 * @var \App\View\AppView $this
 * @var mixed $content
 */
$expand = $expand ?? false;
$maxHeight = $maxHeight ?? '300px';
$class = $class ?? '';
$fade = $fade ?? true;

$scrollClasses = [
    'rhythm-scroll-wrapper',
    $expand ? 'scroll-expand' : 'scroll-constrain',
    $class
];

$contentClasses = [
    'rhythm-scroll-content',
    'scrollbar-thin'
];
?>

<div class="<?= implode(' ', array_filter($scrollClasses)) ?>">
    <div class="<?= implode(' ', $contentClasses) ?>"
         style="max-height: <?= h($maxHeight) ?>;">
        <?= $content ?? '' ?>
    </div>

    <?php if ($fade): ?>
        <div class="scroll-fade"></div>
    <?php endif; ?>
</div>

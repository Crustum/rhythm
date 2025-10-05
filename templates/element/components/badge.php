<?php
/**
 * Badge Component
 *
 * Status badge with different variants and sizes.
 *
 * Options:
 * - variant: Badge style (normal, warning, critical, success, info, unknown)
 * - size: Badge size (sm, md, lg)
 * - class: Additional CSS classes
 * - title: Tooltip text
 *
 * @var \App\View\AppView $this
 */
$variant = $variant ?? 'normal';
$size = $size ?? 'md';
$class = $class ?? '';
$title = $title ?? '';
$text = $text ?? '';

$badgeClasses = [
    'rhythm-badge',
    "badge-{$variant}",
    "badge-{$size}",
    $class
];

$badgeAttributes = [
    'class' => implode(' ', array_filter($badgeClasses))
];

if ($title) {
    $badgeAttributes['title'] = $title;
}
?>

<span <?= $this->Html->templater()->formatAttributes($badgeAttributes) ?>>
    <?= h($text) ?>
</span>

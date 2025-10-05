<?php
/**
 * Table Component (Data-Driven)
 *
 * Responsive table with consistent styling.
 *
 * Options:
 * - class: Additional CSS classes
 * - striped: Enable striped rows
 * - hover: Enable hover effects
 * - responsive: Enable responsive wrapper
 *
 * @var \App\View\AppView $this
 */
$class = $class ?? '';
$striped = $striped ?? false;
$hover = $hover ?? true;
$responsive = $responsive ?? true;

$tableClasses = [
    'rhythm-table',
    $striped ? 'table-striped' : '',
    $hover ? 'table-hover' : '',
    $class
];

$head = $head ?? [];
$body = $body ?? [];
?>

<?php if ($responsive): ?>
<div class="rhythm-table-wrapper <?= $responsive ? 'table-responsive' : '' ?>">
<?php endif; ?>

    <table class="<?= implode(' ', array_filter($tableClasses)) ?>">
        <?php if (!empty($head)): ?>
            <thead>
                <tr>
                    <?php foreach ($head as $th): ?>
                        <th><?= $th ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
        <?php endif; ?>

        <tbody>
            <?php if (empty($body)): ?>
                <tr>
                    <td colspan="<?= count($head) ?>" class="text-center rhythm-text-secondary">
                        No results found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($body as $tr): ?>
                    <tr>
                        <?php foreach ($tr as $td): ?>
                            <td><?= $td ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php if ($responsive): ?>
</div>
<?php endif; ?>

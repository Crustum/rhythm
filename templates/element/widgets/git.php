<?php
/**
 * Git Widget
 *
 * This template fetches its own data and then extends the base widget
 * to handle the presentation.
 *
 * @var \App\View\AppView $this
 * @var mixed $config
 * @var object $widget
 * @var mixed $widgetName
 */
$this->set('widget', $widget);
$this->set('config', $config);
$this->set('widgetName', $widgetName);

$commitCount = (int) $this->getRequest()->getQuery('commit_count', $config['commit_count'] ?? 5);
$options = ['commit_count' => $commitCount];
$sort = $this->get('sort');
if ($sort !== null) {
    $options['sort'] = $sort;
}
$data = $widget->getData($options);
$this->set('data', $data);

$this->extend('Crustum/Rhythm.widgets/widget_base');

$this->start('widget_body');

$gitData = $data ?? [];
$commits = $gitData['commits'] ?? [];
$branch = $gitData['branch'] ?? 'Unknown';
$status = $gitData['repository_status'] ?? 'unknown';

if (isset($gitData['error'])) {
    echo $this->element('Crustum/Rhythm.components/widget_error', [
        'message' => 'Git repository error: ' . h($gitData['error'])
    ]);
} elseif (empty($commits)) {
    echo $this->element('Crustum/Rhythm.components/widget_placeholder', [
        'message' => 'No Git commits found or repository not accessible.'
    ]);
} else {
    $statusBadge = $this->Rhythm->badge(ucfirst($status), $status === 'clean' ? 'success' : ($status === 'modified' ? 'warning' : 'unknown'), ['size' => 'sm']);
    $summaryStats = [
        ['label' => 'Branch', 'value' => $branch],
        ['label' => 'Commits', 'value' => $gitData['total_commits'] ?? 0],
        ['label' => 'Status', 'value' => $statusBadge, 'escape' => false],
    ];

    $head = ['Commit', 'Author', 'Date', 'Message'];
    $body = [];

    foreach ($commits as $commit) {
        $commitHash = '<code>' . h($commit['short_hash']) . '</code>';
        $author = h($commit['author']);
        $date = $commit['date'];

        $message = $commit['message'];

        if (!empty($commit['ticket_numbers'])) {
            $message = preg_replace('/#(\d+)/', '<span class="ticket-number">#$1</span>', $message);
        }

        $tagsHtml = '';
        if (!empty($commit['tags'])) {
            foreach ($commit['tags'] as $tag) {
                $tagsHtml .= '<span class="tag" style="margin-right: 0.5em;">' . h($tag) . '</span>';
            }
        }

        if ($commit['is_merge'] && $commit['merge_info']) {
            $mergeInfo = formatMergeInfo($commit['merge_info']);
            $message = $tagsHtml . '<div class="merge-commit">' . $mergeInfo . '<br><span class="merge-message">' . h($message) . '</span></div>';
        } else {
            $message = $tagsHtml . h($message);
        }

        $statusBadge = $this->Rhythm->badge(ucfirst($commit['status']), getCommitBadgeVariant($commit['status']), ['size' => 'sm']);

        $formattedMessage = $statusBadge . ' ' . $message;

        $body[] = [$commitHash, $author, $date, $formattedMessage];
    }
?>
    <div class="widget-content">
        <div class="mb-3">
            <?= $this->Rhythm->summaryStats($summaryStats) ?>
        </div>
        <?= $this->Rhythm->scroll($this->Rhythm->table($head, $body)) ?>
    </div>
<?php
}
$this->end();

/**
 * Get badge variant for commit status
 *
 * @param string $status Commit status
 * @return string Badge variant
 */
function getCommitBadgeVariant(string $status): string
{
    return match ($status) {
        'fix' => 'critical',
        'feature' => 'success',
        'docs' => 'normal',
        'test' => 'warning',
        'refactor' => 'info',
        'merge' => 'primary',
        'chore' => 'normal',
        'style' => 'normal',
        'perf' => 'success',
        'ci' => 'warning',
        'normal' => 'info',
        default => 'unknown',
    };
}

/**
 * Format merge information for display
 *
 * @param array<string, string> $mergeInfo Merge information
 * @return string Formatted merge info HTML
 */
function formatMergeInfo(array $mergeInfo): string
{
    $type = $mergeInfo['type'] ?? 'unknown';
    $sourceBranch = $mergeInfo['source_branch'] ?? '';
    $targetBranch = $mergeInfo['target_branch'] ?? '';
    $remote = $mergeInfo['remote'] ?? '';

    $html = '<span class="merge-indicator">Merge</span>';

    if ($type === 'remote_merge') {
        $html .= ' <span class="merge-details">' . h($sourceBranch) . ' → ' . h($targetBranch) . ' (via ' . h($remote) . ')</span>';
    } elseif ($type === 'local_merge') {
        $html .= ' <span class="merge-details">' . h($sourceBranch) . ' → ' . h($targetBranch) . '</span>';
    }

    return $html;
}

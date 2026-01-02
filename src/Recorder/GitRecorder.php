<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Recorder;

use Cake\Event\EventListenerInterface;
use Cake\I18n\DateTime;
use Crustum\Rhythm\Event\SharedBeat;
use Crustum\Rhythm\Recorder\Trait\ThrottlingTrait;
use Exception;

/**
 * Records Git repository metrics.
 */
class GitRecorder extends BaseRecorder implements EventListenerInterface
{
    use ThrottlingTrait;

    /**
     * @inheritDoc
     */
    public function implementedEvents(): array
    {
        return [
            SharedBeat::class => 'record',
        ];
    }

    /**
     * Record Git repository data.
     *
     * @param mixed $data The shared beat event.
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$data instanceof SharedBeat) {
            return;
        }

        $this->throttle(60, $data, function (SharedBeat $event): void {
            $gitDir = $this->getGitDirectory();

            if (!$gitDir || !$this->isGitAvailable()) {
                return;
            }

            $timestamp = $event->getTimestamp()->getTimestamp();
            $slug = $this->getRepositorySlug($gitDir);

            try {
                $branch = $this->getCurrentBranch($gitDir);
                $commits = $this->getRecentCommits($gitDir, 20);
                $status = $this->getRepositoryStatus($gitDir);

                $this->rhythm->set(
                    'git',
                    $slug,
                    json_encode([
                        'branch' => $branch,
                        'commits' => $commits,
                        'total_commits' => count($commits),
                        'last_commit' => $commits[0] ?? null,
                        'repository_status' => $status,
                        'repository_path' => $gitDir,
                    ], JSON_THROW_ON_ERROR),
                    $timestamp,
                );
            } catch (Exception $e) {
                error_log('GitRecorder Debug - Exception: ' . $e->getMessage());
                $this->rhythm->set(
                    'git',
                    $slug,
                    json_encode([
                        'branch' => 'Unknown',
                        'commits' => [],
                        'total_commits' => 0,
                        'last_commit' => null,
                        'repository_status' => 'error',
                        'error' => $e->getMessage(),
                        'repository_path' => $gitDir,
                    ], JSON_THROW_ON_ERROR),
                    $timestamp,
                );
            }
        });
    }

    /**
     * Check if Git is available and executable
     *
     * @return bool
     */
    protected function isGitAvailable(): bool
    {
        $output = shell_exec('git --version 2>&1');

        return $output !== null && $output !== false && str_contains($output, 'git version');
    }

    /**
     * Get Git directory
     *
     * @return string|null
     */
    protected function getGitDirectory(): ?string
    {
        $currentDir = getcwd();
        if ($currentDir === false) {
            return null;
        }

        $searchDirs = [$currentDir, dirname($currentDir), dirname($currentDir, 2)];

        foreach ($searchDirs as $dir) {
            $gitDir = $dir . DIRECTORY_SEPARATOR . '.git';
            if (is_dir($gitDir)) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * Get repository slug for storage
     *
     * @param string $gitDir Git directory
     * @return string
     */
    protected function getRepositorySlug(string $gitDir): string
    {
        return 'repository_' . md5($gitDir);
    }

    /**
     * Get current branch
     *
     * @param string $gitDir Git directory
     * @return string
     */
    protected function getCurrentBranch(string $gitDir): string
    {
        $headFile = $gitDir . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'HEAD';

        if (!file_exists($headFile)) {
            return 'Unknown';
        }

        $head = file_get_contents($headFile);
        if (!$head) {
            return 'Unknown';
        }

        $head = trim($head);

        if (str_starts_with($head, 'ref: refs/heads/')) {
            return substr($head, 16);
        }

        return substr($head, 0, 7);
    }

    /**
     * Get recent commits
     *
     * @param string $gitDir Git directory
     * @param int $count Number of commits
     * @return array<int, array<string, mixed>>
     */
    protected function getRecentCommits(string $gitDir, int $count): array
    {
        $commits = [];
        $logCommand = $this->buildGitLogCommand($gitDir, $count);
        $output = shell_exec($logCommand . ' 2>&1');

        if (!$output) {
            return [];
        }

        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = explode('|', $line);
            if (count($parts) >= 5) {
                $date = new DateTime($parts[3]);
                $formattedDate = $date->format('M j, Y H:i');

                $message = $parts[4];
                $parsedMessage = $this->parseCommitMessage($message);

                $commits[] = [
                    'hash' => $parts[0],
                    'short_hash' => substr($parts[0], 0, 7),
                    'author' => $parts[1],
                    'email' => $parts[2],
                    'date' => $formattedDate,
                    'raw_date' => $parts[3],
                    'message' => $message,
                    'parsed_message' => $parsedMessage,
                    'status' => $parsedMessage['status'],
                    'ticket_numbers' => $parsedMessage['ticket_numbers'],
                    'is_merge' => $parsedMessage['is_merge'],
                    'merge_info' => $parsedMessage['merge_info'],
                    'tags' => $this->getCommitTags($gitDir, $parts[0]),
                ];
            }
        }

        return $commits;
    }

    /**
     * Parse commit message for tickets, merges, and status
     *
     * @param string $message Commit message
     * @return array<string, mixed>
     */
    protected function parseCommitMessage(string $message): array
    {
        $originalMessage = $message;
        $message = strtolower($message);

        $result = [
            'status' => 'normal',
            'ticket_numbers' => [],
            'is_merge' => false,
            'merge_info' => null,
            'original_message' => $originalMessage,
        ];

        preg_match_all('/#(\d+)/', $originalMessage, $matches);
        if (!empty($matches[1])) {
            $result['ticket_numbers'] = $matches[1];
        }

        if ($this->isMergeCommit($originalMessage)) {
            $result['is_merge'] = true;
            $result['status'] = 'merge';
            $result['merge_info'] = $this->parseMergeInfo($originalMessage);
        } else {
            $result['status'] = $this->getEnhancedCommitStatus($message);
        }

        return $result;
    }

    /**
     * Check if commit is a merge commit
     *
     * @param string $message Commit message
     * @return bool
     */
    protected function isMergeCommit(string $message): bool
    {
        $mergePatterns = [
            "/^merge branch '([^']+)' of /i",
            "/^merge branch '([^']+)' into '([^']+)'/i",
            '/^merge pull request #\d+/i',
            '/^merge/i',
        ];

        foreach ($mergePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse merge information from commit message
     *
     * @param string $message Commit message
     * @return array<string, string>|null
     */
    protected function parseMergeInfo(string $message): ?array
    {
        if (preg_match('/^merge branch \'([^\']+)\' of ([^ ]+) into ([^\s]+)/i', $message, $matches)) {
            return [
                'source_branch' => $matches[1],
                'remote' => $matches[2],
                'target_branch' => $matches[3],
                'type' => 'remote_merge',
            ];
        }

        if (preg_match("/^merge branch '([^']+)' into '([^']+)'/i", $message, $matches)) {
            return [
                'source_branch' => $matches[1],
                'target_branch' => $matches[2],
                'type' => 'local_merge',
            ];
        }

        if (preg_match('/^merge branch \'([^\']+)\' into ([^\s]+)/i', $message, $matches)) {
            return [
                'source_branch' => $matches[1],
                'target_branch' => $matches[2],
                'type' => 'local_merge',
            ];
        }

        return null;
    }

    /**
     * Get enhanced commit status based on message
     *
     * @param string $message Commit message (lowercase)
     * @return string
     */
    protected function getEnhancedCommitStatus(string $message): string
    {
        if (preg_match('/^(fix|bugfix|hotfix)/', $message)) {
            return 'fix';
        }

        if (preg_match('/^(feat|feature|add)/', $message)) {
            return 'feature';
        }

        if (preg_match('/^(docs|documentation|readme)/', $message)) {
            return 'docs';
        }

        if (preg_match('/^(test|testing)/', $message)) {
            return 'test';
        }

        if (preg_match('/^(refactor|refactoring)/', $message)) {
            return 'refactor';
        }

        if (preg_match('/^(chore|maintenance)/', $message)) {
            return 'chore';
        }

        if (preg_match('/^(style|formatting)/', $message)) {
            return 'style';
        }

        if (preg_match('/^(perf|performance)/', $message)) {
            return 'perf';
        }

        if (preg_match('/^(ci|build|deploy)/', $message)) {
            return 'ci';
        }

        if (str_contains($message, 'fix') || str_contains($message, 'bug')) {
            return 'fix';
        }

        if (str_contains($message, 'feat') || str_contains($message, 'add') || str_contains($message, 'new')) {
            return 'feature';
        }

        if (
            str_contains($message, 'docs') ||
            str_contains($message, 'readme') ||
            str_contains($message, 'documentation')
        ) {
            return 'docs';
        }

        if (str_contains($message, 'test')) {
            return 'test';
        }

        if (str_contains($message, 'refactor')) {
            return 'refactor';
        }

        return 'normal';
    }

    /**
     * Get tags for a specific commit
     *
     * @param string $gitDir Git directory
     * @param string $commitHash Full commit hash
     * @return array<string>
     */
    protected function getCommitTags(string $gitDir, string $commitHash): array
    {
        $tagCommand = $this->buildGitTagCommand($gitDir, $commitHash);
        $output = shell_exec($tagCommand . ' 2>&1');

        if (!$output) {
            return [];
        }

        $tags = array_filter(array_map('trim', explode("\n", $output)));

        return array_values($tags);
    }

    /**
     * Build Git log command with proper quoting for platform
     *
     * @param string $gitDir Git directory
     * @param int $count Number of commits
     * @return string
     */
    protected function buildGitLogCommand(string $gitDir, int $count): string
    {
        $format = '%H|%an|%ae|%aI|%s';
        $gitCommand = $this->buildGitBaseCommand();

        return 'cd ' . escapeshellarg($gitDir) .
            " && {$gitCommand} log --oneline --max-count={$count} --pretty=\"format:{$format}\"";
    }

    /**
     * Build Git status command with proper quoting for platform
     *
     * @param string $gitDir Git directory
     * @return string
     */
    protected function buildGitStatusCommand(string $gitDir): string
    {
        $gitCommand = $this->buildGitBaseCommand();

        return 'cd ' . escapeshellarg($gitDir) . " && {$gitCommand} status --porcelain";
    }

    /**
     * Build Git tag command with proper quoting for platform
     *
     * @param string $gitDir Git directory
     * @param string $commitHash Full commit hash
     * @return string
     */
    protected function buildGitTagCommand(string $gitDir, string $commitHash): string
    {
        $gitCommand = $this->buildGitBaseCommand();

        return 'cd ' . escapeshellarg($gitDir) .
            " && {$gitCommand} tag --points-at " . escapeshellarg($commitHash);
    }

    /**
     * Build base Git command with platform-specific flags
     *
     * @return string
     */
    protected function buildGitBaseCommand(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'git';
        }

        return 'GIT_PAGER=cat git';
    }

    /**
     * Get repository status
     *
     * @param string $gitDir Git directory
     * @return string
     */
    protected function getRepositoryStatus(string $gitDir): string
    {
        $statusCommand = $this->buildGitStatusCommand($gitDir);
        $output = shell_exec($statusCommand . ' 2>&1');

        if (!$output) {
            return 'clean';
        }

        $lines = explode("\n", trim($output));
        $modifiedFiles = array_filter($lines);

        if ($modifiedFiles === []) {
            return 'clean';
        }

        return 'modified';
    }
}

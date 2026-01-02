<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Widget;

use Exception;

/**
 * Git Widget
 *
 * Displays current Git branch and recent commits.
 */
class GitWidget extends BaseWidget
{
    /**
     * Get widget data
     *
     * @param array<string, mixed> $options Widget options (period, sort, etc.)
     * @return array<string, mixed>
     */
    public function getData(array $options = []): array
    {
        return $this->remember(function () {
            try {
                $gitData = $this->getGitData();

                if (!$gitData) {
                    return [
                        'branch' => 'Unknown',
                        'commits' => [],
                        'total_commits' => 0,
                        'last_commit' => null,
                        'repository_status' => 'error',
                        'error' => 'No Git data available',
                    ];
                }

                return $gitData;
            } catch (Exception $e) {
                return [
                    'branch' => 'Unknown',
                    'commits' => [],
                    'total_commits' => 0,
                    'last_commit' => null,
                    'repository_status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }, 'git_widget', $this->getRefreshInterval());
    }

    /**
     * Get Git data from recorder
     *
     * @return array<string, mixed>|null
     */
    protected function getGitData(): ?array
    {
        $gitValues = $this->rhythm->getStorage()->values('git');

        if ($gitValues->isEmpty()) {
            return null;
        }

        $latestData = null;
        $latestTimestamp = 0;

        foreach ($gitValues as $gitData) {
            $data = json_decode($gitData->value, true);
            if (!$data) {
                continue;
            }

            if ($gitData->timestamp > $latestTimestamp) {
                $latestTimestamp = $gitData->timestamp;
                $latestData = $data;
            }
        }

        return $latestData;
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Rhythm.widgets/git';
    }

    /**
     * Get refresh interval in seconds
     *
     * @return int
     */
    public function getRefreshInterval(): int
    {
        return $this->getConfigValue('refreshInterval', 60);
    }

    /**
     * Get default icon for this widget
     *
     * @return string|null
     */
    protected function getDefaultIcon(): ?string
    {
        return 'fas fa-code-branch';
    }
}

<?php
declare(strict_types=1);

namespace Rhythm\Recorder\Trait;

use Cake\Core\Configure;

/**
 * Queue Name Trait
 *
 * Shared functionality for handling queue names and prefixes.
 */
trait QueueNameTrait
{
    /**
     * Queue prefixes extracted from configuration.
     *
     * @var array
     */
    protected array $queuePrefixes = [];

    /**
     * Extract queue prefixes from Queue configuration.
     *
     * @return void
     */
    public function extractQueuePrefixes(): void
    {
        $queueConfig = Configure::read('Queue');
        $this->queuePrefixes = [];
        if (!$queueConfig) {
            $this->queuePrefixes = ['enqueue.app.'];

            return;
        }

        foreach ($queueConfig as $config) {
            if (isset($config['url']['client'])) {
                $client = $config['url']['client'];
                $prefix = $client['prefix'] ?? '';
                $separator = $client['separator'] ?? '.';
                $appName = $client['app_name'] ?? 'app';

                if ($prefix) {
                    $this->queuePrefixes[] = $prefix . $separator . $appName . $separator;
                }
            } elseif (isset($config['client'])) {
                $client = $config['client'];
                $prefix = $client['prefix'] ?? '';
                $separator = $client['separator'] ?? '.';
                $appName = $client['app'] ?? 'app';

                if ($prefix) {
                    $this->queuePrefixes[] = $prefix . $separator . $appName . $separator;
                }
            } else {
                $this->queuePrefixes[] = 'enqueue.app.';
            }
        }

        if (empty($this->queuePrefixes)) {
            $this->queuePrefixes = ['enqueue.app.'];
        }
    }

    /**
     * Strip prefix from queue name.
     *
     * @param string $queueName Full queue name with prefix
     * @return string Clean queue name without prefix
     */
    public function stripQueuePrefix(string $queueName): string
    {
        foreach ($this->queuePrefixes as $prefix) {
            if (str_starts_with($queueName, $prefix)) {
                return substr($queueName, strlen($prefix));
            }
        }

        return $queueName;
    }
}

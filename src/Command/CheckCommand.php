<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Command;

use Cake\Cache\Cache;
use Cake\Command\Command;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleOptionParser;
use Cake\Event\EventManager;
use Cake\Event\EventManagerInterface;
use Cake\I18n\DateTime;
use Crustum\Rhythm\Event\SharedBeat;
use Crustum\Rhythm\Rhythm;
use SignalHandler\Command\Trait\SignalHandlerTrait;

/**
 * Check Command for server tracking
 *
 * Continuously monitors server metrics and ingests them into the Rhythm system.
 */
class CheckCommand extends Command
{
    use SignalHandlerTrait;

    /**
     * Rhythm instance.
     *
     * @var \Crustum\Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Event manager instance.
     *
     * @var \Cake\Event\EventManagerInterface
     */
    protected EventManagerInterface $eventManager;

    /**
     * Whether the command should continue running.
     *
     * @var bool
     */
    protected bool $isRunning = true;

    /**
     * Constructor.
     *
     * @param \Crustum\Rhythm\Rhythm $rhythm Rhythm instance
     * @param \Cake\Console\CommandFactoryInterface $factory Command factory instance
     */
    public function __construct(Rhythm $rhythm, ?CommandFactoryInterface $factory = null)
    {
        parent::__construct($factory);
        $this->rhythm = $rhythm;
        $this->eventManager = EventManager::instance();
    }

    /**
     * Hook method for defining this command's option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription("Take a snapshot of the current server's rhythm")
            ->addOption('once', [
                'short' => 'o',
                'help' => 'Take a single snapshot',
                'boolean' => true,
            ])
            ->addOption('interval', [
                'short' => 'i',
                'help' => 'Interval between checks in seconds (default: 1)',
                'default' => 1,
            ]);

        return $parser;
    }

    /**
     * Execute the command.
     *
     * @return int|null The exit code or null for success
     */
    public function execute(): ?int
    {
        $once = $this->args->getOption('once');
        $interval = (int)$this->args->getOption('interval');

        $this->rhythm->loadRecordersFromConfig();

        $this->io->info('Starting Rhythm server tracking...');
        $this->io->info('Press Ctrl+C to stop');

        $this->bindGracefulTermination(function (int $signal): void {
            $this->io->info('Rhythm Check received termination signal, shutting down gracefully...');
            $this->isRunning = false;
        });

        $lastRestart = Cache::read('rhythm:restart', 'default');
        while ($this->isRunning) {
            usleep(100000);
            if ($lastRestart !== Cache::read('rhythm:restart', 'default')) {
                $this->io->info('Restart signal detected, exiting...');

                return self::CODE_SUCCESS;
            }

            $now = new DateTime();
            $instance = gethostname() ?: 'default';

            $this->eventManager->dispatch(new SharedBeat($now, $instance));

            $ingested = $this->rhythm->ingest();
            if ($ingested > 0) {
                $this->io->verbose("Ingested {$ingested} metrics");
            }

            if ($once) {
                $this->io->info('Single snapshot completed');

                return self::CODE_SUCCESS;
            }

            sleep($interval);
        }

        return self::CODE_SUCCESS;
    }

    /**
     * Get the command name.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'rhythm check';
    }

    /**
     * Get the command description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return "Take a snapshot of the current server's rhythm";
    }
}

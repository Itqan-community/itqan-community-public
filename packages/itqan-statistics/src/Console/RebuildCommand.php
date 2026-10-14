<?php

namespace Itqan\Statistics\Console;

use Flarum\Console\AbstractCommand;
use Itqan\Statistics\Service\StatisticsService;
use Symfony\Component\Console\Input\InputOption;

class RebuildCommand extends AbstractCommand
{
    protected StatisticsService $stats;

    public function __construct(StatisticsService $stats)
    {
        $this->stats = $stats;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('itqan:statistics:rebuild')
            ->setDescription('Purges and rebuilds persisted daily statistics buckets.')
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Number of days to rebuild', 365);
    }

    protected function fire(): int
    {
        $days = max(30, min(730, (int) $this->input->getOption('days')));
        $this->info("Rebuilding Itqan statistics daily buckets for the last {$days} days...");

        $res = $this->stats->purgeAndRebuild($days);

        $this->info(sprintf(
            'Done! Purged %d old buckets and rebuilt %d days (%s to %s in %s).',
            $res['purged_buckets'],
            $res['rebuilt_days'],
            $res['from'],
            $res['to'],
            $res['timezone']
        ));

        return 0;
    }
}

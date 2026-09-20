<?php

namespace Itqan\PreviewCards\Console;

use Flarum\Console\AbstractCommand;
use Itqan\PreviewCards\Cache\CardCache;
use Itqan\PreviewCards\Settings;
use Symfony\Component\Console\Input\InputOption;

class PruneCommand extends AbstractCommand
{
    public function __construct(
        protected CardCache $cache,
        protected Settings $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('itqan-cards:prune')
            ->setDescription('Delete cached preview cards older than the retention window')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Retention in days (defaults to the setting)');
    }

    protected function fire(): int
    {
        $days = (int) ($this->input->getOption('days') ?? $this->settings->pruneDays());
        $removed = $this->cache->prune(max(1, $days));
        $stats = $this->cache->stats();

        $this->info("Removed {$removed} card(s) older than {$days} day(s).");
        $this->info("Cache now holds {$stats['files']} file(s), ".number_format($stats['bytes'] / 1024, 1).' KB.');

        return 0;
    }
}

<?php

namespace IanM\Translate\Console;

use Illuminate\Console\Command;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;
use Illuminate\Database\Eloquent\Builder;
use Psr\Log\LoggerInterface;

abstract class AbstractDetermineLanguages extends Command
{
    protected $totalAdded = 0;
    protected $langs = [];
    protected $check = 0;

    public function __construct(protected TranslationProviderInterface $translator, protected LoggerInterface $logger)
    {
        parent::__construct();
    }

    abstract protected function getItemsToProcess(): Builder;
    abstract protected function processItems(Builder $items);
    abstract protected function displayInitialInfo(): void;

    public function handle(): void
    {
        $this->displayInitialInfo();
        
        $items = $this->getItemsToProcess();

        if ($items->count() === 0) {
            $this->info('No items to process');
            return;
        }

        $this->processItems($items);

        $this->displayResults();
    }

    protected function displayResults(): void
    {
        foreach ($this->langs as $lang => $count) {
            $this->check += $count;
            $this->info("Added $count items with language $lang");
        }

        $this->info("Added detected language to $this->totalAdded items in total");

        if ($this->check !== $this->totalAdded) {
            $this->error("Something went wrong, the total added does not match the total checked");
        }
    }
}

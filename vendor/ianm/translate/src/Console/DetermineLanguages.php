<?php

namespace IanM\Translate\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;

class DetermineLanguages extends Command
{
    protected $signature = 'translate:detect
        {--force : Force all items (discussions/posts) to be re-detected}';
    protected $description = 'Detects the language of all items (discussions/posts) that do not have a detected language set.';

    public function handle()
    {
        // Process Discussions
        $this->call(DetermineDiscussionLanguages::class, [
            '--force' => $this->option('force'),
        ]);

        // Process Posts
        $this->call(DeterminePostLanguages::class, [
            '--force' => $this->option('force'),
        ]);

        $this->info('Languages detection for both discussions and posts completed.');
    }
}

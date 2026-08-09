<?php

namespace IanM\Translate\Console;

use Flarum\Discussion\Discussion;
use Illuminate\Database\Eloquent\Builder;

class DetermineDiscussionLanguages extends AbstractDetermineLanguages
{
    protected $signature = 'translate:detect-discussions
        {--discussion= : The discussion ID to detect languages}
        {--force : Force all discussions to be re-detected}';
    protected $description = 'Detects the language of all discussions that do not have a detected language set.';

    protected function getItemsToProcess(): Builder
    {
        $force = $this->option('force') ?? false;
        $discussion = $this->option('discussion') ?? null;

        return Discussion::query()->when(!$force, fn ($query) => $query->whereNull('detected_lang'))
            ->when($discussion, fn ($query) => $query->where('id', $discussion));
    }

    protected function processItems(Builder $discussions): void
    {
        $this->output->progressStart($discussions->count());

        $discussions->orderBy('id', 'desc')->each(function (Discussion $discussion) {
            $discussion->detected_lang = $this->translator->identifyTitleLanguage($discussion);

            if ($discussion->isDirty()) {
                $discussion->save();

                $this->totalAdded++;
                $this->langs[$discussion->detected_lang] = ($this->langs[$discussion->detected_lang] ?? 0) + 1;
            } else {
                $this->logger->info("[ianm/translate][CLI] Could not identify language of discussion {$discussion->id}");
            }

            $this->output->progressAdvance();
        });

        $this->output->progressFinish();
    }

    protected function displayInitialInfo(): void
    {
        $driver = $this->translator->name();
        $force = $this->option('force') ?? false;
        $discussion = $this->option('discussion') ?? null;

        // Construct and display the message based on the provided options
        $message = "Detecting languages of discussions using driver '{$driver}'";
        $message .= $force ? " (forcing all discussions to be re-detected)" : "";
        $message .= $discussion ? " for discussion ID {$discussion}" : "";

        $this->info($message);
    }
}

<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate\Console;

use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Builder;

class DeterminePostLanguages extends AbstractDetermineLanguages
{
    protected $signature = 'translate:detect-posts
        {--post= : The post ID to detect languages of posts}
        {--force : Force all posts to be re-detected}';
    protected $description = 'Detects the language of all posts that do not have a detected language set.';

    protected function displayInitialInfo(): void
    {
        $driver = $this->translator->name();
        $force = $this->option('force') ?? false;
        $post = $this->option('post') ?? null;

        if ($force && !$post) {
            $message = "Forcing all posts to be re-detected using driver '{$driver}'";
        } else if ($force === true && $post !== null) {
            $message = "Forcing all posts in discussion {$post} to be re-detected using driver '{$driver}'";
        } else if (!$force && $post !== null) {
            $message = "Detecting un-identified language posts in discussion {$post} using driver '{$driver}'";
        } else {
            $message = "Detecting languages of posts un-identified language using driver '{$driver}'";
        }

        $this->info($message);
    }

    protected function getItemsToProcess(): Builder
    {
        $force = $this->option('force') ?? false;
        $post = $this->option('post') ?? null;

        return CommentPost::query()->where('type', 'comment')
            ->when(!$force, fn ($query) => $query->whereNull('detected_lang'))
            ->when($post, fn ($query) => $query->where('id', $post));
    }

    protected function processItems(Builder $posts): void
    {
        $this->output->progressStart($posts->count());

        $posts->orderBy('id', 'desc')->each(function (CommentPost $post) {
            $post->detected_lang = $this->translator->identifyLanguage($post);

            if ($post->isDirty()) {
                $post->save();

                $this->totalAdded++;
                $this->langs[$post->detected_lang] = ($this->langs[$post->detected_lang] ?? 0) + 1;
            } else {
                $this->logger->info("[ianm/translate][CLI] Could not identify language of post {$post->id}. In discussion {$post->discussion_id}, number {$post->number}");
            }

            $this->output->progressAdvance();
        });

        $this->output->progressFinish();
    }
}

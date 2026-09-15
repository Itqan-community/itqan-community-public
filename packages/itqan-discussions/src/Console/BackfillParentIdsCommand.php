<?php

namespace Itqan\Discussions\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Symfony\Component\Console\Input\InputOption;

class BackfillParentIdsCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('itqan:backfill-threads')
            ->setDescription('Backfills parent_id and recalculates reply_count for existing posts based on post mentions')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without modifying the database')
            ->addOption('discussion', null, InputOption::VALUE_OPTIONAL, 'Filter by specific discussion ID');
    }

    protected function fire()
    {
        $isDryRun = $this->hasOption('dry-run') && $this->input->getOption('dry-run');
        $discussionId = $this->hasOption('discussion') ? $this->input->getOption('discussion') : null;

        $this->info($isDryRun ? '--- RUNNING IN DRY-RUN MODE (NO CHANGES WILL BE WRITTEN) ---' : '--- STARTING THREAD BACKFILL ---');

        $query = Post::where('type', 'comment')
            ->where('number', '>', 1)
            ->whereNull('parent_id');

        if ($discussionId) {
            $query->where('discussion_id', (int) $discussionId);
        }

        $totalPosts = $query->count();
        $this->info("Found {$totalPosts} candidate comment posts without parent_id.");

        if ($totalPosts === 0) {
            $this->info('Nothing to backfill.');
            return 0;
        }

        $updatedCount = 0;
        $skippedCount = 0;

        // Process in chunks of 100
        $query->chunk(100, function ($posts) use ($isDryRun, &$updatedCount, &$skippedCount) {
            foreach ($posts as $post) {
                $targetParentId = null;

                // 1. Try mentionsPosts relationship (from post_mentions_post pivot table)
                if (method_exists($post, 'mentionsPosts')) {
                    $mentionedPosts = $post->mentionsPosts()
                        ->where('discussion_id', $post->discussion_id)
                        ->where('id', '<', $post->id)
                        ->orderBy('id', 'desc')
                        ->get();

                    if ($mentionedPosts->isNotEmpty()) {
                        $targetParentId = $mentionedPosts->first()->id;
                    }
                }

                // 2. Fallback: Parse parsedContent / content XML for <POSTMENTION id="...">
                if (!$targetParentId) {
                    $content = $post->parsedContent ?? $post->content ?? '';
                    if (preg_match_all('/<POSTMENTION\s+[^>]*id="(\d+)"/i', $content, $matches)) {
                        $mentionIds = array_map('intval', $matches[1]);
                        $validParents = Post::whereIn('id', $mentionIds)
                            ->where('discussion_id', $post->discussion_id)
                            ->where('id', '<', $post->id)
                            ->orderBy('id', 'desc')
                            ->pluck('id')
                            ->all();

                        if (!empty($validParents)) {
                            $targetParentId = $validParents[0];
                        }
                    }
                }

                if ($targetParentId) {
                    $updatedCount++;
                    $this->output->writeln("Post #{$post->id} (Discussion #{$post->discussion_id}) -> Parent #{$targetParentId}");
                    if (!$isDryRun) {
                        Post::where('id', $post->id)->update(['parent_id' => $targetParentId]);
                    }
                } else {
                    $skippedCount++;
                }
            }
        });

        $this->info("Backfilled {$updatedCount} posts. Skipped {$skippedCount} posts (no valid parent mentions found).");

        // Recalculate reply_count for all posts
        $this->info('Recalculating reply counts for parent posts...');
        if (!$isDryRun) {
            Post::where('reply_count', '>', 0)->update(['reply_count' => 0]);

            $counts = Post::whereNotNull('parent_id')
                ->selectRaw('parent_id, COUNT(*) as child_count')
                ->groupBy('parent_id')
                ->get();

            foreach ($counts as $row) {
                Post::where('id', $row->parent_id)->update(['reply_count' => $row->child_count]);
            }
            $this->info('Reply counts recalculated successfully.');
        } else {
            $this->info('[Dry-run] Would recalculate reply counts for all parent posts.');
        }

        $this->info('Done.');
        return 0;
    }
}

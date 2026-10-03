<?php

namespace Itqan\Discussions\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Post\Post;
use Symfony\Component\Console\Input\InputOption;

class BackfillRootDepthCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('itqan:backfill-root-depth')
            ->setDescription('Backfill posts.root_id and posts.depth from parent_id chains')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing')
            ->addOption('discussion', null, InputOption::VALUE_OPTIONAL, 'Limit to one discussion ID');
    }

    protected function fire()
    {
        $isDryRun = (bool) $this->input->getOption('dry-run');
        $discussionId = $this->input->getOption('discussion');

        $this->info($isDryRun ? '--- DRY-RUN root/depth backfill ---' : '--- Backfilling root_id / depth ---');

        $query = Post::query()->where('type', 'comment');
        if ($discussionId) {
            $query->where('discussion_id', (int) $discussionId);
        }

        $byId = [];
        foreach ($query->get(['id', 'number', 'parent_id', 'discussion_id']) as $post) {
            $byId[(int) $post->id] = $post;
        }

        $updates = 0;
        $db = Post::query()->getConnection();

        foreach ($byId as $id => $post) {
            $rootId = null;
            $depth = 0;

            if ((int) $post->number === 1 || $post->parent_id === null) {
                $rootId = null;
                $depth = 0;
            } else {
                $cursor = $post;
                $chain = 0;
                $visited = [];
                while ($cursor && $cursor->parent_id) {
                    $pid = (int) $cursor->parent_id;
                    if (isset($visited[$pid])) {
                        break;
                    }
                    $visited[$pid] = true;
                    $parent = $byId[$pid] ?? null;
                    if (! $parent) {
                        break;
                    }
                    $chain++;
                    if ($parent->parent_id === null || (int) $parent->number === 1) {
                        if ((int) $parent->number === 1) {
                            $rootId = null;
                            $depth = 0;
                        } else {
                            $rootId = (int) $parent->id;
                            $depth = $chain;
                        }
                        break;
                    }
                    $cursor = $parent;
                }
                if ($rootId === null && $chain > 0 && $post->parent_id) {
                    $depth = $chain;
                }
            }

            if ($isDryRun) {
                $updates++;
                continue;
            }

            $affected = $db->table('posts')->where('id', $id)->update([
                'root_id' => $rootId,
                'depth' => $depth,
            ]);
            $updates += $affected ? 1 : 0;
        }

        $this->info(($isDryRun ? 'Would update' : 'Updated') . " {$updates} posts.");
        return 0;
    }
}

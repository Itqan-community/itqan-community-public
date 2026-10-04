<?php

namespace Itqan\PreviewCards\Listener;

use Flarum\Discussion\Event\Deleted as DiscussionDeleted;
use Flarum\Discussion\Event\Renamed;
use Flarum\Post\Event\Deleted as PostDeleted;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored as PostRestored;
use Flarum\Post\Event\Revised;
use Illuminate\Contracts\Queue\Queue;
use Itqan\PreviewCards\Cache\CardCache;
use Itqan\PreviewCards\Job\GenerateCardJob;

class CardInvalidationListener
{
    public function __construct(
        protected Queue $queue,
        protected CardCache $cache,
    ) {
    }

    public function discussionRenamed(Renamed $event): void
    {
        $this->warm($event->discussion?->id);
    }

    public function discussionDeleted(DiscussionDeleted $event): void
    {
        if ($event->discussion) {
            $this->cache->deleteDiscussion((int) $event->discussion->id);
        }
    }

    public function postChanged(Posted|Revised|PostDeleted|PostHidden|PostRestored $event): void
    {
        if ($event->post) {
            $this->warm($event->post->discussion_id);
        }
    }

    private function warm(mixed $discussionId): void
    {
        if ($discussionId) {
            $this->queue->push(new GenerateCardJob((int) $discussionId));
        }
    }
}

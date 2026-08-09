<?php

namespace Itqan\MailerLite\Listeners;

use Flarum\Discussion\Event\Started;
use Illuminate\Contracts\Queue\Queue;
use Itqan\MailerLite\Api\MailerLiteClient;
use Itqan\MailerLite\Jobs\AddToGroupJob;
use Itqan\MailerLite\Models\MailerLiteSubscriber;

class DiscussionStartedListener
{
    private MailerLiteClient $client;
    private Queue $queue;

    public function __construct(MailerLiteClient $client, Queue $queue)
    {
        $this->client = $client;
        $this->queue = $queue;
    }

    public function handle(Started $event): void
    {
        if (!$this->client->isEnabled()) {
            return;
        }

        $user = $event->discussion->user;

        if (!$user) {
            return;
        }

        // Check if this is the user's first post (discussion starter counts as first post)
        $subscriber = MailerLiteSubscriber::findOrCreateForUser($user);

        if (!$subscriber->hasFirstPost()) {
            // This is their first post - add to first-posters group
            $this->queue->push(new AddToGroupJob(
                $user->id,
                'group_first_posters',
                true // isFirstPost flag
            ));
        }
    }
}

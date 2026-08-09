<?php

namespace Itqan\MailerLite\Listeners;

use Flarum\Post\Event\Posted;
use Illuminate\Contracts\Queue\Queue;
use Itqan\MailerLite\Api\MailerLiteClient;
use Itqan\MailerLite\Jobs\AddToGroupJob;
use Itqan\MailerLite\Models\MailerLiteSubscriber;

class PostCreatedListener
{
    private MailerLiteClient $client;
    private Queue $queue;

    public function __construct(MailerLiteClient $client, Queue $queue)
    {
        $this->client = $client;
        $this->queue = $queue;
    }

    public function handle(Posted $event): void
    {
        if (!$this->client->isEnabled()) {
            return;
        }

        $user = $event->post->user;

        if (!$user) {
            return;
        }

        // Skip post #1 — handled by DiscussionStartedListener to avoid duplicate jobs
        if ((int) $event->post->number === 1) {
            return;
        }

        // Check if this is the user's first post
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

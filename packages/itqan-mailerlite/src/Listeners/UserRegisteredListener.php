<?php

namespace Itqan\MailerLite\Listeners;

use Flarum\User\Event\Registered;
use Illuminate\Contracts\Queue\Queue;
use Itqan\MailerLite\Api\MailerLiteClient;
use Itqan\MailerLite\Jobs\SyncSubscriberJob;

class UserRegisteredListener
{
    private MailerLiteClient $client;
    private Queue $queue;

    public function __construct(MailerLiteClient $client, Queue $queue)
    {
        $this->client = $client;
        $this->queue = $queue;
    }

    public function handle(Registered $event): void
    {
        if (!$this->client->isEnabled()) {
            return;
        }

        // Sync user and add to new-members and community-digest groups
        $this->queue->push(new SyncSubscriberJob(
            $event->user->id,
            ['group_new_members', 'group_digest']
        ));
    }
}

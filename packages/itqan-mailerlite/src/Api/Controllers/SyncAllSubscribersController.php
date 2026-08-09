<?php

namespace Itqan\MailerLite\Api\Controllers;

use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;
use Itqan\MailerLite\Api\MailerLiteClient;
use Itqan\MailerLite\Jobs\SyncSubscriberJob;
use Itqan\MailerLite\Models\MailerLiteSubscriber;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SyncAllSubscribersController implements RequestHandlerInterface
{
    private MailerLiteClient $client;
    private Queue $queue;

    public function __construct(MailerLiteClient $client, Queue $queue)
    {
        $this->client = $client;
        $this->queue = $queue;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        if (!$this->client->isEnabled()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'MailerLite integration is disabled or API key is not configured.',
            ], 400);
        }

        $queued = 0;

        // Queue pending/failed subscribers already in the table
        MailerLiteSubscriber::query()
            ->whereIn('sync_status', [
                MailerLiteSubscriber::STATUS_PENDING,
                MailerLiteSubscriber::STATUS_FAILED,
            ])
            ->select('user_id')
            ->each(function (MailerLiteSubscriber $subscriber) use (&$queued) {
                $this->queue->push(new SyncSubscriberJob(
                    $subscriber->user_id,
                    ['group_new_members']
                ));
                $queued++;
            });

        // Queue confirmed users not yet in the mailerlite_subscribers table
        User::query()
            ->where('is_email_confirmed', true)
            ->whereNotNull('email')
            ->whereNotIn('id', function ($q) {
                $q->select('user_id')->from('mailerlite_subscribers');
            })
            ->select(['id', 'email'])
            ->chunkById(100, function ($users) use (&$queued) {
                MailerLiteSubscriber::query()->getConnection()
                    ->table('mailerlite_subscribers')
                    ->insertOrIgnore(
                        $users->map(function ($u) {
                            return [
                                'user_id'     => $u->id,
                                'email'       => $u->email,
                                'sync_status' => MailerLiteSubscriber::STATUS_PENDING,
                            ];
                        })->all()
                    );

                foreach ($users as $user) {
                    $this->queue->push(new SyncSubscriberJob($user->id, ['group_new_members']));
                    $queued++;
                }
            });

        return new JsonResponse([
            'success' => true,
            'queued' => $queued,
            'message' => "Queued {$queued} users for sync.",
        ]);
    }
}

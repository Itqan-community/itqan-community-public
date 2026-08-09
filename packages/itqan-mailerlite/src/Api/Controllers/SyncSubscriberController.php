<?php

namespace Itqan\MailerLite\Api\Controllers;

use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;
use Itqan\MailerLite\Api\MailerLiteClient;
use Itqan\MailerLite\Jobs\SyncSubscriberJob;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SyncSubscriberController implements RequestHandlerInterface
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

        $userId = $request->getAttribute('userId');
        $user = User::find($userId);

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if (!$user->is_email_confirmed) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User email is not confirmed.',
            ], 400);
        }

        // Queue the sync job
        $this->queue->push(new SyncSubscriberJob($user->id, ['group_new_members']));

        return new JsonResponse([
            'success' => true,
            'message' => 'Sync job queued for user: ' . $user->username,
        ]);
    }
}

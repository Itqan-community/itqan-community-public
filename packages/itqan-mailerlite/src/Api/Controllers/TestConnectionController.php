<?php

namespace Itqan\MailerLite\Api\Controllers;

use Flarum\Http\RequestUtil;
use Itqan\MailerLite\Api\MailerLiteClient;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TestConnectionController implements RequestHandlerInterface
{
    private MailerLiteClient $client;

    public function __construct(MailerLiteClient $client)
    {
        $this->client = $client;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        if (!$this->client->isEnabled()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'MailerLite integration is disabled or API key is not configured.',
            ]);
        }

        $result = $this->client->testConnection();

        if ($result['success']) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Successfully connected to MailerLite API.',
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Failed to connect to MailerLite API.',
            'error' => $result['error'] ?? 'Unknown error',
            'status_code' => $result['status_code'] ?? null,
        ]);
    }
}

<?php

namespace Itqan\MailerLite\Api\Controllers;

use Flarum\Settings\SettingsRepositoryInterface;
use Itqan\MailerLite\Models\MailerLiteSyncLog;
use Itqan\MailerLite\Models\MailerLiteSubscriber;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class WebhookController implements RequestHandlerInterface
{
    private SettingsRepositoryInterface $settings;
    private LoggerInterface $logger;

    public function __construct(
        SettingsRepositoryInterface $settings,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Read raw body first so HMAC is computed over the original bytes
        $rawBody = (string) $request->getBody();
        $body = json_decode($rawBody, true) ?? [];

        $webhookSecret = $this->settings->get('itqan-mailerlite.webhook_secret');

        // Secret is mandatory — reject all requests when not configured
        if (empty($webhookSecret)) {
            $this->logger->warning('MailerLite webhook received but webhook_secret is not configured');
            return new JsonResponse(['error' => 'Webhook not configured'], 401);
        }

        $signature = $request->getHeaderLine('X-MailerLite-Signature');

        if (empty($signature)) {
            $this->logger->warning('MailerLite webhook received without signature');
            return new JsonResponse(['error' => 'Missing signature'], 401);
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->warning('MailerLite webhook signature mismatch');
            return new JsonResponse(['error' => 'Invalid signature'], 401);
        }

        $eventType = $body['type'] ?? null;

        $this->logger->info('MailerLite webhook received', [
            'type' => $eventType,
            'data' => $body,
        ]);

        switch ($eventType) {
            case 'subscriber.unsubscribed':
                return $this->handleUnsubscribe($body);

            case 'subscriber.bounced':
                return $this->handleBounce($body);

            case 'subscriber.spam_reported':
                return $this->handleSpamReport($body);

            default:
                // Acknowledge unknown events
                return new JsonResponse(['status' => 'ok', 'message' => 'Event type not handled']);
        }
    }

    private function handleUnsubscribe(array $data): ResponseInterface
    {
        $email = $data['data']['email'] ?? null;
        $subscriberId = $data['data']['id'] ?? null;

        if (!$email) {
            return new JsonResponse(['error' => 'Missing email'], 400);
        }

        // Find subscriber by email or MailerLite ID
        $subscriber = null;

        if ($subscriberId) {
            $subscriber = MailerLiteSubscriber::query()
                ->where('mailerlite_subscriber_id', $subscriberId)
                ->first();
        }

        if (!$subscriber) {
            $subscriber = MailerLiteSubscriber::query()
                ->where('email', $email)
                ->first();
        }

        if ($subscriber) {
            $subscriber->markUnsubscribed();

            MailerLiteSyncLog::log(
                $subscriber->user_id,
                MailerLiteSyncLog::ACTION_UNSUBSCRIBE,
                MailerLiteSyncLog::STATUS_SUCCESS,
                null,
                null,
                null,
                $data
            );

            $this->logger->info('User unsubscribed via webhook', [
                'user_id' => $subscriber->user_id,
                'email' => $email,
            ]);
        } else {
            $this->logger->warning('Unsubscribe webhook for unknown email', ['email' => $email]);
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function handleBounce(array $data): ResponseInterface
    {
        $email = $data['data']['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['error' => 'Missing email'], 400);
        }

        $subscriber = MailerLiteSubscriber::query()
            ->where('email', $email)
            ->first();

        if ($subscriber) {
            $subscriber->update([
                'sync_status' => MailerLiteSubscriber::STATUS_FAILED,
                'updated_at' => now(),
            ]);

            MailerLiteSyncLog::logFailure(
                $subscriber->user_id,
                'bounce',
                'Email bounced',
                null,
                null,
                $data
            );

            $this->logger->info('Email bounce recorded via webhook', [
                'user_id' => $subscriber->user_id,
                'email' => $email,
            ]);
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function handleSpamReport(array $data): ResponseInterface
    {
        $email = $data['data']['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['error' => 'Missing email'], 400);
        }

        $subscriber = MailerLiteSubscriber::query()
            ->where('email', $email)
            ->first();

        if ($subscriber) {
            $subscriber->markUnsubscribed();

            MailerLiteSyncLog::log(
                $subscriber->user_id,
                'spam_report',
                MailerLiteSyncLog::STATUS_SUCCESS,
                null,
                null,
                null,
                $data
            );

            $this->logger->warning('Spam report received via webhook', [
                'user_id' => $subscriber->user_id,
                'email' => $email,
            ]);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}

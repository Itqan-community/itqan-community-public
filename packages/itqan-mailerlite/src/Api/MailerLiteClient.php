<?php

namespace Itqan\MailerLite\Api;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class MailerLiteClient
{
    private const API_BASE_URL = 'https://connect.mailerlite.com/api/';
    private const MAX_RETRIES = 5;
    private const RETRY_DELAY_MS = 1000;
    private const MAX_RATE_LIMIT_WAIT_MS = 60000;

    private Client $httpClient;
    private SettingsRepositoryInterface $settings;
    private LoggerInterface $logger;
    private ?string $apiKey = null;

    public function __construct(
        SettingsRepositoryInterface $settings,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->httpClient = new Client([
            'base_uri' => self::API_BASE_URL,
            'timeout' => 30,
        ]);
    }

    private function getApiKey(): string
    {
        if ($this->apiKey === null) {
            $this->apiKey = $this->settings->get('itqan-mailerlite.api_key', '');
        }
        return $this->apiKey;
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getApiKey(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function request(string $method, string $endpoint, array $data = [], int $attempt = 1): array
    {
        try {
            $options = ['headers' => $this->getHeaders()];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->httpClient->request($method, $endpoint, $options);
            $body = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'data' => $body['data'] ?? $body,
                'status_code' => $response->getStatusCode(),
            ];
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);

            // Handle rate limiting — respect Retry-After header if present, else exponential backoff
            if ($statusCode === 429 && $attempt < self::MAX_RETRIES) {
                $retryAfter = $e->getResponse()->getHeaderLine('Retry-After');
                $waitMs = $retryAfter
                    ? min((int) $retryAfter * 1000, self::MAX_RATE_LIMIT_WAIT_MS)
                    : min(self::RETRY_DELAY_MS * pow(2, $attempt - 1), self::MAX_RATE_LIMIT_WAIT_MS);
                $this->logger->warning('MailerLite rate limit hit, retrying', [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'wait_ms' => $waitMs,
                ]);
                usleep($waitMs * 1000);
                return $this->request($method, $endpoint, $data, $attempt + 1);
            }

            $this->logger->error('MailerLite API error', [
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'response' => $responseBody,
            ]);

            return [
                'success' => false,
                'error' => $responseBody['message'] ?? 'API request failed',
                'status_code' => $statusCode,
                'response' => $responseBody,
            ];
        } catch (GuzzleException $e) {
            // Retry on connection errors
            if ($attempt < self::MAX_RETRIES) {
                $delay = self::RETRY_DELAY_MS * pow(2, $attempt - 1);
                usleep($delay * 1000);
                return $this->request($method, $endpoint, $data, $attempt + 1);
            }

            $this->logger->error('MailerLite API connection error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => 0,
            ];
        }
    }

    public function testConnection(): array
    {
        return $this->request('GET', 'subscribers?limit=1');
    }

    public function upsertSubscriber(User $user, array $fields = [], array $groups = []): array
    {
        $data = [
            'email' => $user->email,
            'fields' => array_merge([
                'name' => $user->display_name ?? $user->username,
                'last_name' => '',
            ], $fields),
            'status' => 'active',
        ];

        if (!empty($groups)) {
            $data['groups'] = $groups;
        }

        return $this->request('POST', 'subscribers', $data);
    }

    public function getSubscriber(string $email): array
    {
        return $this->request('GET', 'subscribers/' . urlencode($email));
    }

    public function updateSubscriber(string $subscriberId, array $data): array
    {
        return $this->request('PUT', 'subscribers/' . $subscriberId, $data);
    }

    public function getGroups(int $limit = 100): array
    {
        return $this->request('GET', 'groups?limit=' . $limit);
    }

    public function createGroup(string $name): array
    {
        return $this->request('POST', 'groups', ['name' => $name]);
    }

    public function getGroup(string $groupId): array
    {
        return $this->request('GET', 'groups/' . $groupId);
    }

    public function findGroupByName(string $name): ?array
    {
        $result = $this->getGroups(100);

        if (!$result['success']) {
            return null;
        }

        foreach ($result['data'] as $group) {
            if (strtolower($group['name']) === strtolower($name)) {
                return $group;
            }
        }

        return null;
    }

    public function addSubscriberToGroup(string $subscriberId, string $groupId): array
    {
        return $this->request('POST', "subscribers/{$subscriberId}/groups/{$groupId}");
    }

    public function removeSubscriberFromGroup(string $subscriberId, string $groupId): array
    {
        return $this->request('DELETE', "subscribers/{$subscriberId}/groups/{$groupId}");
    }

    public function getGroupSubscribers(string $groupId, int $limit = 100, string $cursor = null): array
    {
        $endpoint = "groups/{$groupId}/subscribers?limit={$limit}";
        if ($cursor) {
            $endpoint .= '&cursor=' . $cursor;
        }
        return $this->request('GET', $endpoint);
    }

    public function createCampaign(string $name, string $subject, string $content, array $groupIds): array
    {
        return $this->request('POST', 'campaigns', [
            'name' => $name,
            'type' => 'regular',
            'emails' => [
                [
                    'subject' => $subject,
                    'from_name' => $this->settings->get('itqan-mailerlite.from_name', 'Itqan Community'),
                    'from' => $this->settings->get('itqan-mailerlite.from_email', 'noreply@itqan.dev'),
                    'content' => $content,
                ],
            ],
            'groups' => $groupIds,
        ]);
    }

    public function scheduleCampaign(string $campaignId, string $datetime = null): array
    {
        $data = [];
        if ($datetime) {
            $data['delivery'] = 'scheduled';
            $data['schedule'] = ['date' => $datetime];
        }

        return $this->request('POST', "campaigns/{$campaignId}/schedule", $data);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('itqan-mailerlite.enabled', false)
            && !empty($this->getApiKey());
    }
}

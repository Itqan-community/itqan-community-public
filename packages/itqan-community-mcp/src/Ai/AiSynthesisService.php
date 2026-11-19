<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Ai;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Multi-Tier Failover AI Synthesis Engine.
 *
 * Tier 1: Google Gemini (Free Tier: 15 RPM / 1,500 RPD)
 * Tier 2: Groq Free Tier (Llama 3.3 70B / Gemma 2)
 * Tier 3: Cloudflare Workers AI / Local Ollama (if configured)
 * Tier 4: Native Extractive Summary (100% offline, zero-API, zero-cost)
 */
class AiSynthesisService
{
    private SettingsRepositoryInterface $settings;
    private LoggerInterface $logger;
    private Client $httpClient;

    public function __construct(SettingsRepositoryInterface $settings, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->httpClient = new Client([
            'timeout' => 8.0,
            'connect_timeout' => 3.0,
            'http_errors' => false,
        ]);
    }

    /**
     * Synthesize a natural conversational summary from community discussions and posts.
     * Cascades through available free providers until an answer is produced.
     *
     * @param array<int, array<string, mixed>> $discussions
     * @param array<int, array<string, mixed>> $posts
     * @return array{summary: string, provider: string}
     */
    public function synthesize(string $query, array $discussions, array $posts): array
    {
        $context = $this->buildContext($discussions, $posts);

        // Tier 1: Google Gemini Free API
        $geminiKey = trim((string) $this->settings->get('itqan-community-mcp.gemini_api_key', ''));
        if ($geminiKey === '') {
            $geminiKey = (string) getenv('GEMINI_API_KEY');
        }

        if ($geminiKey !== '') {
            try {
                $answer = $this->callGemini($geminiKey, $query, $context);
                if ($answer !== null && $answer !== '') {
                    return ['summary' => $answer, 'provider' => 'gemini'];
                }
            } catch (Throwable $e) {
                $this->logger->warning('[AskItqan] Gemini failover triggered: ' . $e->getMessage());
            }
        }

        // Tier 2: Groq Free API
        $groqKey = trim((string) $this->settings->get('itqan-community-mcp.groq_api_key', ''));
        if ($groqKey === '') {
            $groqKey = (string) getenv('GROQ_API_KEY');
        }

        if ($groqKey !== '') {
            try {
                $answer = $this->callGroq($groqKey, $query, $context);
                if ($answer !== null && $answer !== '') {
                    return ['summary' => $answer, 'provider' => 'groq'];
                }
            } catch (Throwable $e) {
                $this->logger->warning('[AskItqan] Groq failover triggered: ' . $e->getMessage());
            }
        }

        // Tier 3: Local Ollama (if running on community server)
        $ollamaUrl = trim((string) $this->settings->get('itqan-community-mcp.ollama_endpoint', ''));
        if ($ollamaUrl !== '') {
            try {
                $answer = $this->callOllama($ollamaUrl, $query, $context);
                if ($answer !== null && $answer !== '') {
                    return ['summary' => $answer, 'provider' => 'ollama'];
                }
            } catch (Throwable $e) {
                $this->logger->warning('[AskItqan] Ollama failover triggered: ' . $e->getMessage());
            }
        }

        // Tier 4: Native Smart Extractive Summary (100% Offline, Zero-Cost)
        $fallback = $this->smartExtractiveSummary($query, $discussions, $posts);
        return ['summary' => $fallback, 'provider' => 'local_extractive'];
    }

    private function buildContext(array $discussions, array $posts): string
    {
        $contextLines = [];

        foreach ($discussions as $disc) {
            $title = $disc['title'] ?? '';
            $author = $disc['author']['display_name'] ?? $disc['author']['username'] ?? 'مشارك';
            $snippet = trim($disc['snippet'] ?? '');
            $contextLines[] = "• موضوع: \"{$title}\" (الكاتب: {$author}): {$snippet}";
        }

        foreach ($posts as $post) {
            $author = $post['author']['display_name'] ?? $post['author']['username'] ?? 'مشارك';
            $snippet = trim($post['snippet'] ?? '');
            if ($snippet !== '') {
                $contextLines[] = "• مشاركة من {$author}: {$snippet}";
            }
        }

        return implode("\n", array_slice($contextLines, 0, 8));
    }

    private function getSystemPrompt(): string
    {
        return <<<PROMPT
أنت مساعد «إتقان» الذكي لمجتمع تقنيات القرآن الكريم وعلومه (مجتمع إتقان التقني).
مهمتك:
1. أجب باختصار ولطف ووضوح باللغة العربية الفصحى (فقرة مركزة من 2-4 جمل).
2. لخص الإجابة مباشرة من سياق التجارب والنقاشات المرفقة فقط، مع ذكر اسم صاحب التجربة أو الأداة إن وجدت.
3. إذا لم تجد تفاصيل كافية، أجب بلطف بما ورد فقط ولا تخترع أو تهلوس بأي معلومات إضافية.
4. حافظ على نبرة ودودة ومجتمعية مرحبة.
PROMPT;
    }

    private function callGemini(string $apiKey, string $query, string $context): ?string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $this->getSystemPrompt()]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => "سؤال المستخدم: {$query}\n\nسياق مشاركات ونقاشات المجتمع:\n{$context}\n\nلخص الإجابة بدقة من السياق:"],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 300,
            ],
        ];

        $res = $this->httpClient->post($url, [
            'json' => $payload,
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        if ($res->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode((string) $res->getBody(), true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    private function callGroq(string $apiKey, string $query, string $context): ?string
    {
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $payload = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => "سؤال المستخدم: {$query}\n\nسياق مشاركات المجتمع:\n{$context}"],
            ],
            'temperature' => 0.2,
            'max_tokens' => 300,
        ];

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        if ($res->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode((string) $res->getBody(), true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function callOllama(string $endpoint, string $query, string $context): ?string
    {
        $res = $this->httpClient->post($endpoint, [
            'json' => [
                'model' => 'qwen2.5:7b',
                'prompt' => $this->getSystemPrompt() . "\n\nالسياق:\n{$context}\n\nسؤال: {$query}",
                'stream' => false,
            ],
        ]);

        if ($res->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode((string) $res->getBody(), true);
        return $data['response'] ?? null;
    }

    /**
     * Smart local extractive summary when no external API is available.
     */
    private function smartExtractiveSummary(string $query, array $discussions, array $posts): string
    {
        $topDisc = $discussions[0] ?? null;
        if (! $topDisc) {
            return "بناءً على أرشيف المجتمع، إليك أبرز المشاركات والنقاشات المرتبطة باستفسارك أدناه:";
        }

        $author = $topDisc['author']['display_name'] ?? $topDisc['author']['username'] ?? 'أحد الأعضاء';
        $title = $topDisc['title'] ?? '';
        $snippet = trim($topDisc['snippet'] ?? '');

        // Clean snippet
        $cleanSnippet = preg_replace('/\s+/', ' ', $snippet);

        if (mb_strlen($cleanSnippet, 'UTF-8') > 180) {
            $cleanSnippet = mb_substr($cleanSnippet, 0, 180, 'UTF-8') . '...';
        }

        $summary = "طُرح هذا الموضوع في نقاش **«{$title}»** بواسطة **{$author}**.";
        if ($cleanSnippet !== '') {
            $summary .= "\n\n أبرز ما ورد: \"{$cleanSnippet}\"";
        }

        if (count($discussions) > 1) {
            $otherCount = count($discussions) - 1;
            $summary .= "\n\nكما توجد {$otherCount} نقاشات أخرى ذات صلة يمكنك تصفحها بالأسفل.";
        }

        return $summary;
    }
}

<?php

namespace Itqan\Translate\Api;

use Flarum\Http\RequestUtil;
use Flarum\Post\PostRepository;
use Itqan\Translate\PostTranslation;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class TranslatePostController implements RequestHandlerInterface
{
    public function __construct(protected PostRepository $posts)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $postId = (int) ($request->getAttribute('routeParameters')['id'] ?? 0);

        if ($postId <= 0) {
            return new JsonResponse(['error' => 'invalid post'], 400);
        }

        $post = $this->posts->findOrFail($postId, $actor);

        $body = (array) $request->getParsedBody();
        $language = $this->normaliseLanguage($body['language'] ?? null);

        if ($language === null) {
            return new JsonResponse(['error' => 'invalid language'], 400);
        }

        $detected = strtolower((string) $post->detected_lang);

        if ($detected !== '' && str_starts_with($detected, strtolower(substr($language, 0, 2)))) {
            return new JsonResponse(['sameLanguage' => true, 'language' => $language]);
        }

        $cached = PostTranslation::query()
            ->where('post_id', $post->id)
            ->where('language', $language)
            ->first();

        if ($cached && ! $cached->update_needed && trim((string) $cached->content) !== '') {
            return new JsonResponse([
                'translation' => $cached->content,
                'language' => $language,
                'cached' => true,
            ]);
        }

        $text = $this->plainText((string) $post->content);

        if ($text === '') {
            return new JsonResponse(['translation' => null, 'language' => $language, 'empty' => true]);
        }

        try {
            $translation = $this->translate($text, $language);
        } catch (Throwable $e) {
            resolve('log')->error('itqan-translate: '.$e->getMessage(), ['post_id' => $post->id, 'language' => $language]);

            return new JsonResponse([
                'error' => 'translation provider unavailable',
                'detail' => config('debug') ? $e->getMessage() : null,
            ], 502);
        }

        PostTranslation::query()->updateOrCreate(
            ['post_id' => $post->id, 'language' => $language],
            ['content' => $translation, 'provider' => 'google-free', 'update_needed' => 0]
        );

        return new JsonResponse([
            'translation' => $translation,
            'language' => $language,
            'cached' => false,
        ]);
    }

    private function normaliseLanguage(mixed $language): ?string
    {
        if (! is_string($language) || ! preg_match('/^[a-zA-Z]{2}(-[a-zA-Z]{2})?$/', $language)) {
            return null;
        }

        // Google expects the primary subtag; regional variants are unnecessary here.
        return strtolower(substr($language, 0, 2));
    }

    private function plainText(string $content): string
    {
        $text = str_replace(
            ['</p>', '</li>', '<br>', '<br/>', '<br />'],
            ["\n\n", "\n", "\n", "\n", "\n"],
            $content
        );

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function translate(string $text, string $language): string
    {
        $chunks = mb_strlen($text) <= 4000 ? [$text] : (preg_split('/\n{2,}/u', $text) ?: [$text]);
        $out = [];
        $buffer = '';

        foreach ($chunks as $chunk) {
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($chunk) > 4000) {
                $out[] = $this->request($buffer, $language);
                $buffer = $chunk;
            } else {
                $buffer = $buffer === '' ? $chunk : $buffer."\n\n".$chunk;
            }
        }

        if ($buffer !== '') {
            $out[] = $this->request($buffer, $language);
        }

        return implode("\n\n", $out);
    }

    private function request(string $text, string $language): string
    {
        $url = 'https://translate.googleapis.com/translate_a/single'
            .'?client=gtx&sl=auto&dt=t&tl='.rawurlencode($language)
            .'&q='.rawurlencode($text);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ItqanTranslate/1.0)',
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($body === false || $status >= 400) {
            throw new \RuntimeException("provider failed (status={$status}, error={$error})");
        }

        $json = json_decode($body, true);
        $translated = '';

        foreach (($json[0] ?? []) as $part) {
            if (is_array($part) && isset($part[0])) {
                $translated .= $part[0];
            }
        }

        if (trim($translated) === '') {
            throw new \RuntimeException('provider returned an empty translation');
        }

        return $translated;
    }
}

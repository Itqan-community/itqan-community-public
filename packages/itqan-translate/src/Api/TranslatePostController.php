<?php

namespace Itqan\Translate\Api;

use Flarum\Http\RequestUtil;
use Flarum\Post\PostRepository;
use Itqan\Translate\PostTranslation;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Stichoza\GoogleTranslate\GoogleTranslate;
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
        $translator = new GoogleTranslate($language);

        if (mb_strlen($text) <= 4500) {
            return (string) $translator->translate($text);
        }

        $chunks = preg_split('/\n{2,}/u', $text) ?: [$text];
        $out = [];
        $buffer = '';

        foreach ($chunks as $chunk) {
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($chunk) > 4500) {
                $out[] = (string) $translator->translate($buffer);
                $buffer = $chunk;
            } else {
                $buffer = $buffer === '' ? $chunk : $buffer."\n\n".$chunk;
            }
        }

        if ($buffer !== '') {
            $out[] = (string) $translator->translate($buffer);
        }

        return implode("\n\n", $out);
    }
}

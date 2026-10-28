<?php

namespace Itqan\Translate\Api;

use Flarum\Http\RequestUtil;
use Flarum\Post\PostRepository;
use Itqan\Translate\PostTranslation;
use Itqan\Translate\TranslationProvider;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class TranslatePostController implements RequestHandlerInterface
{
    public function __construct(
        protected PostRepository $posts,
        protected TranslationProvider $provider,
    ) {
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
        $refresh = (bool) ($body['refresh'] ?? false);

        if ($language === null) {
            return new JsonResponse(['error' => 'invalid language'], 400);
        }

        $detected = strtolower((string) $post->detected_lang);

        if ($detected !== '' && str_starts_with($detected, $language)) {
            return new JsonResponse(['sameLanguage' => true, 'language' => $language]);
        }

        $cached = PostTranslation::query()
            ->where('post_id', $post->id)
            ->where('language', $language)
            ->first();

        if (! $refresh && $cached && ! $cached->update_needed && trim((string) $cached->content) !== '') {
            return new JsonResponse($this->payload($cached->content, $language, $cached->provider));
        }

        $text = $this->plainText((string) $post->content);

        if ($text === '') {
            return new JsonResponse(['translation' => null, 'language' => $language, 'empty' => true]);
        }

        try {
            $translation = $this->provider->translate($text, $language);
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

        return new JsonResponse($this->payload($translation, $language, 'google-free'));
    }

    private function payload(string $translation, string $language, string $provider): array
    {
        return [
            'translation' => $translation,
            'language' => $language,
            'provider' => $provider,
            'updatedAt' => gmdate('c'),
        ];
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
}

<?php

namespace Itqan\Translate\Api;

use Flarum\Http\RequestUtil;
use Flarum\Post\Post;
use Flarum\Post\PostRepository;
use Itqan\Translate\PostTranslation;
use Itqan\Translate\Text;
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

        // Translating into the post's own language is allowed on purpose: the
        // reader may want the text rendered in the other script/normalised, and
        // the old extension offered both languages unconditionally.

        $cached = PostTranslation::query()
            ->where('post_id', $post->id)
            ->where('language', $language)
            ->first();

        if (! $refresh && $cached && ! $cached->update_needed && trim((string) $cached->content) !== '') {
            // Rows cached by the 1.8 extension still contain s9e/HTML markup.
            // Heal them on the way out so the reader never sees tags.
            $content = Text::sanitize((string) $cached->content);

            if ($content !== (string) $cached->content) {
                $cached->content = $content;
                $cached->save();
            }

            return new JsonResponse($this->payload($content, $language, $cached->provider));
        }

        $text = $this->plainText($post, $request);

        if ($text === '') {
            return new JsonResponse(['translation' => null, 'language' => $language, 'empty' => true]);
        }

        try {
            $translation = Text::sanitize($this->provider->translate($text, $language));
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

    /**
     * The post as plain text: format it first (so mentions, links and code are
     * resolved), then strip the markup.
     */
    private function plainText(Post $post, ?ServerRequestInterface $request = null): string
    {
        try {
            $html = method_exists($post, 'formatContent')
                ? $post->formatContent($request)
                : (string) $post->content;
        } catch (Throwable) {
            $html = (string) $post->content;
        }

        return Text::sanitize($html);
    }
}

<?php

namespace Itqan\Translate\Api;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Itqan\Translate\DiscussionTranslation;
use Itqan\Translate\Text;
use Itqan\Translate\TranslationProvider;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Translates a discussion title. Mirrors the 1.x
 * `/api/discussions/{id}/translate/{lang}` endpoint, but with a JSON body
 * instead of a JSON:API document.
 */
class TranslateDiscussionController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = (int) ($request->getAttribute('routeParameters')['id'] ?? 0);

        if ($id <= 0) {
            return new JsonResponse(['error' => 'invalid discussion'], 400);
        }

        $discussion = Discussion::whereVisibleTo($actor)->findOrFail($id);

        $body = (array) $request->getParsedBody();
        $language = $this->normaliseLanguage($body['language'] ?? null);
        $refresh = (bool) ($body['refresh'] ?? false);

        if ($language === null) {
            return new JsonResponse(['error' => 'invalid language'], 400);
        }

        // See the post controller: same-language translation is intentional.

        $cached = DiscussionTranslation::query()
            ->where('discussion_id', $discussion->id)
            ->where('language', $language)
            ->first();

        if (! $refresh && $cached && ! $cached->update_needed && trim((string) $cached->translation) !== '') {
            $value = Text::sanitize((string) $cached->translation);

            if ($value !== (string) $cached->translation) {
                $cached->translation = $value;
                $cached->save();
            }

            return new JsonResponse($this->payload($value, $language, $cached->provider));
        }

        try {
            $translated = Text::sanitize($this->translate((string) $discussion->title, $language));
        } catch (Throwable $e) {
            resolve('log')->error('itqan-translate: discussion '.$discussion->id.': '.$e->getMessage());

            return new JsonResponse(['error' => 'translation provider unavailable'], 502);
        }

        DiscussionTranslation::query()->updateOrCreate(
            ['discussion_id' => $discussion->id, 'language' => $language],
            ['translation' => $translated, 'provider' => 'google-free', 'update_needed' => 0]
        );

        return new JsonResponse($this->payload($translated, $language, 'google-free'));
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

        return strtolower(substr($language, 0, 2));
    }

    private function translate(string $text, string $language): string
    {
        return resolve(TranslationProvider::class)->translate($text, $language);
    }
}

<?php

use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\PostResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Locale\LocaleManager;
use Itqan\Translate\Api\TranslateDiscussionController;
use Itqan\Translate\Api\TranslatePostController;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->post('/itqan-translate/posts/{id}', 'itqan-translate.post', TranslatePostController::class)
        ->post('/itqan-translate/discussions/{id}', 'itqan-translate.discussion', TranslateDiscussionController::class),

    (new Extend\ApiResource(PostResource::class))
        ->fields(fn () => [
            Schema\Str::make('detectedLang')
                ->nullable()
                ->get(fn ($post) => $post->detected_lang),
        ]),

    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(fn () => [
            Schema\Str::make('detectedLang')
                ->nullable()
                ->get(fn ($discussion) => $discussion->detected_lang),
        ]),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Arr::make('itqanTranslate.supportedLanguages')
                ->get(function () {
                    /** @var LocaleManager $locales */
                    $locales = resolve(LocaleManager::class);

                    $supported = [];

                    foreach (['ar', 'en'] as $code) {
                        if ($locales->hasLocale($code)) {
                            $supported[$code] = $code;
                        }
                    }

                    return $supported;
                }),
        ]),
];

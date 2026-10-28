<?php

use Flarum\Api\Resource\PostResource;
use Flarum\Extend;
use Itqan\Translate\Api\TranslatePostController;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->post('/itqan-translate/posts/{id}', 'itqan-translate.post', TranslatePostController::class),

    (new Extend\ApiResource(PostResource::class))
        ->fields(fn () => [
            \Flarum\Api\Schema\Str::make('detectedLang')
                ->nullable()
                ->get(fn ($post) => $post->detected_lang),
        ]),
];

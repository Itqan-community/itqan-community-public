<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate;

use Flarum\Api\Controller;
use Flarum\Api\Serializer;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Frontend\Document;
use Flarum\Post\Event\Saving as PostSaving;
use Flarum\Post\Post;
use Flarum\Settings\Event\Saving as SettingsSaving;
use IanM\Translate\Api\Serializers\DiscussionTranslationSerializer;
use IanM\Translate\Api\Serializers\PostTranslationSerializer;
use IanM\Translate\Model\PostTranslation;
use IanM\Translate\Model\DiscussionTranslation;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;
use IanM\Translate\Console;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less')
        ->content(function (Document $document) {
            $document->payload['ianm-translate'] = resolve('ianm-translate.providers.admin');
        }),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\ServiceProvider())
        ->register(Providers\PostTranslatorProvider::class),

    (new Extend\ApiSerializer(Serializer\BasicPostSerializer::class))
        ->attributes(AddCommentPostAttributes::class),

    (new Extend\ApiSerializer(Serializer\CurrentUserSerializer::class))
        ->attributes(AddCurrentUserAttributes::class),

    (new Extend\Routes('api'))
        ->get('/posts/{id}/translate/{lang}', 'show.post.translated', Api\Controllers\ShowPostTranslationController::class)
        ->get('/discussions/{id}/translate/{lang}', 'show.discussion.translated', Api\Controllers\ShowDiscussionTranslationController::class),

    (new Extend\Settings())
        ->default('ianm-translate.provider', 'google-free')
        ->default('ianm-translate.libre.url', 'https://translate.argosopentech.com/')
        ->default('ianm-translate.libre.port', 443)
        ->default('ianm-translate.libre.api_key', null)
        ->default('ianm-translate.deepl.formality', 'default')
        ->default('ianm-translate.deepl.en_choice', 'en-US')
        ->default('ianm-translate.translate-all-enabled', true)
        ->default('ianm-translate.update-translations-after-save', false)
        ->default('ianm-translate.bind-browser-language', false)
        ->default('ianm-translate.use-native-locale-names', false)
        ->serializeToForum('ianm-translate.useNativeLocaleNames', 'ianm-translate.use-native-locale-names', 'boolval'),

    (new Extend\Event())
        ->listen(PostSaving::class, Listener\AddPostContentLanguage::class)
        ->listen(SettingsSaving::class, Listener\ClearCachedSupportedLanguages::class)
        ->subscribe(Listener\PostContentChanged::class)
        ->subscribe(Listener\DiscussionTitle::class),

    (new Extend\ApiSerializer(Serializer\DiscussionSerializer::class))
        ->attributes(AddDiscussionAttributes::class)
        ->hasMany('translations', DiscussionTranslationSerializer::class)
        ->hasOne('translation', DiscussionTranslationSerializer::class),

    (new Extend\ApiSerializer(Serializer\PostSerializer::class))
        ->hasMany('translations', PostTranslationSerializer::class),

    (new Extend\ApiSerializer(Serializer\ForumSerializer::class))
        ->attributes(AddForumAttributes::class),

    (new Extend\Model(Post::class))
        ->cast('detected_lang', 'string')
        ->hasMany('translations', PostTranslation::class, 'post_id', 'id')
        ->relationship('translation', Model\LocaleBasedRelation::class),

    (new Extend\Model(Discussion::class))
        ->cast('detected_lang', 'string')
        ->hasMany('translations', DiscussionTranslation::class, 'discussion_id', 'id')
        ->relationship('translation', Model\LocaleBasedRelation::class),

    (new Extend\ApiController(Controller\ShowDiscussionController::class))
        ->addOptionalInclude(['translations'])
        ->addInclude('translation'),

    (new Extend\ApiController(Controller\ListDiscussionsController::class))
        ->addOptionalInclude('translations')
        ->addInclude('translation'),

    (new Extend\ApiController(Controller\ShowPostController::class))
        ->addOptionalInclude('translations'),

    (new Extend\ApiController(Controller\ListPostsController::class))
        ->addOptionalInclude('translations'),

    (new Extend\Console())
        ->command(Console\DeterminePostLanguages::class)
        ->command(Console\DetermineDiscussionLanguages::class)
        ->command(Console\DetermineLanguages::class),

    (new Extend\User())
        ->registerPreference('ianm-translate.labelAllSource', 'boolVal', false),

    (new Extend\Policy())
        ->globalPolicy(Access\GlobalPolicy::class)
        ->modelPolicy(Discussion::class, Access\DiscussionPolicy::class),

    (new Extend\Middleware('forum'))
        ->add(Middleware\BindBrowserLanguage::class),

    (new Extend\Middleware('api'))
        ->add(Middleware\BindBrowserLanguage::class),
];

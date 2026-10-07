<?php

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Discussion\Event\Deleted as DiscussionDeleted;
use Flarum\Discussion\Event\Renamed;
use Flarum\Extend;
use Flarum\Post\Event\Deleted as PostDeleted;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored as PostRestored;
use Flarum\Post\Event\Revised;
use Flarum\Settings\SettingsRepositoryInterface;
use Itqan\PreviewCards\Api\ClearCacheController;
use Itqan\PreviewCards\Api\DeleteBackgroundController;
use Itqan\PreviewCards\Api\DeleteCardLogoController;
use Itqan\PreviewCards\Api\UploadBackgroundController;
use Itqan\PreviewCards\Api\UploadCardLogoController;
use Itqan\PreviewCards\Console\BrandCommand;
use Itqan\PreviewCards\Console\DoctorCommand;
use Itqan\PreviewCards\Console\PruneCommand;
use Itqan\PreviewCards\Console\WarmCommand;
use Itqan\PreviewCards\Controller\CardController;
use Itqan\PreviewCards\Listener\CardInvalidationListener;
use Itqan\PreviewCards\PreviewCardsServiceProvider;
use Itqan\PreviewCards\Seo\DiscussionCardDriver;
use V17Development\FlarumSeo\Extend\SEO;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('forum'))
        ->get('/og/d/{id:\d+}-{hash:[0-9a-f]+}.png', 'itqan-preview-cards.image', CardController::class),

    (new Extend\Routes('api'))
        ->post('/itqan-preview-cards/logo', 'itqan-preview-cards.logo.upload', UploadCardLogoController::class)
        ->delete('/itqan-preview-cards/logo', 'itqan-preview-cards.logo.delete', DeleteCardLogoController::class)
        ->post('/itqan-preview-cards/background', 'itqan-preview-cards.background.upload', UploadBackgroundController::class)
        ->delete('/itqan-preview-cards/background', 'itqan-preview-cards.background.delete', DeleteBackgroundController::class)
        ->post('/itqan-preview-cards/clear-cache', 'itqan-preview-cards.clear-cache', ClearCacheController::class),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attribute('itqanPreviewCardsLogoUrl', function () {
            $path = (string) resolve(SettingsRepositoryInterface::class)
                ->get('itqan-preview-cards.logo_path', '');

            if ($path === '') {
                return null;
            }

            return resolve('filesystem')->disk('flarum-assets')->url($path);
        })
        ->attribute('itqanPreviewCardsBackgroundUrl', function () {
            $path = (string) resolve(SettingsRepositoryInterface::class)
                ->get('itqan-preview-cards.background_path', '');

            if ($path === '') {
                return null;
            }

            return resolve('filesystem')->disk('flarum-assets')->url($path);
        }),

    (new SEO())
        ->addExtender('itqan_preview_cards', DiscussionCardDriver::class),

    (new Extend\Settings())
        ->default('itqan-preview-cards.enabled', '1')
        ->default('itqan-preview-cards.language', 'auto')
        ->default('itqan-preview-cards.brand_ar', '')
        ->default('itqan-preview-cards.brand_en', '')
        ->default('itqan-preview-cards.tagline_ar', 'مجتمع العاملين على التقنيات القرآنية')
        ->default('itqan-preview-cards.tagline_en', 'A community of Quranic technology builders')
        ->default('itqan-preview-cards.show_logo', '1')
        ->default('itqan-preview-cards.show_brand', '1')
        ->default('itqan-preview-cards.show_tagline', '1')
        ->default('itqan-preview-cards.show_tag', '1')
        ->default('itqan-preview-cards.show_excerpt', '1')
        ->default('itqan-preview-cards.show_author', '1')
        ->default('itqan-preview-cards.show_avatar', '1')
        ->default('itqan-preview-cards.show_date', '1')
        ->default('itqan-preview-cards.show_replies', '1')
        ->default('itqan-preview-cards.show_last_reply', '0')
        ->default('itqan-preview-cards.logo_path', '')
        ->default('itqan-preview-cards.background_path', '')
        ->default('itqan-preview-cards.color_background', '#004638')
        ->default('itqan-preview-cards.color_accent', '#00ad83')
        ->default('itqan-preview-cards.color_title', '#ffffff')
        ->default('itqan-preview-cards.color_text', '#cbd7d4')
        ->default('itqan-preview-cards.color_meta', '#d7e1de')
        ->default('itqan-preview-cards.color_tag', '#c2d2ce')
        ->default('itqan-preview-cards.render_concurrency', '2')
        ->default('itqan-preview-cards.provisional_ttl', '60')
        ->default('itqan-preview-cards.prune_days', '30'),

    (new Extend\Event())
        ->listen(Renamed::class, [CardInvalidationListener::class, 'discussionRenamed'])
        ->listen(DiscussionDeleted::class, [CardInvalidationListener::class, 'discussionDeleted'])
        ->listen(Posted::class, [CardInvalidationListener::class, 'postChanged'])
        ->listen(Revised::class, [CardInvalidationListener::class, 'postChanged'])
        ->listen(PostDeleted::class, [CardInvalidationListener::class, 'postChanged'])
        ->listen(PostHidden::class, [CardInvalidationListener::class, 'postChanged'])
        ->listen(PostRestored::class, [CardInvalidationListener::class, 'postChanged']),

    (new Extend\ServiceProvider())
        ->register(PreviewCardsServiceProvider::class),

    (new Extend\Console())
        ->command(BrandCommand::class)
        ->command(PruneCommand::class)
        ->command(WarmCommand::class)
        ->command(DoctorCommand::class)
        ->schedule(PruneCommand::class, function ($event) {
            $event->daily();
        }),
];

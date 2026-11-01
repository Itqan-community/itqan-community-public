<?php

namespace Itqan\LangArabic;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Locale\LocaleManager;

/**
 * Re-registers this pack's translations last.
 *
 * A language pack's translations are added when extensions are extended, in
 * enabled order. `flarum-lang/arabic` ships an automatically generated (Weblate)
 * catalogue that still contains English source strings for the keys it has not
 * translated yet — `core.lib.search.title: Search`, for example — and it is
 * enabled after this pack on both staging and production, so its English values
 * overwrote the Arabic ones here.
 *
 * Adding the same files again when providers boot puts them at the end of the
 * translator's resource list, which is the order Symfony resolves collisions
 * in. The pack keeps working as a language pack (locale registration, admin
 * title); this only decides who wins.
 */
class ArabicPriorityProvider extends AbstractServiceProvider
{
    public function boot(): void
    {
        /** @var LocaleManager $locales */
        $locales = $this->container->make(LocaleManager::class);

        foreach (glob(dirname(__DIR__).'/locale/*.yml') ?: [] as $file) {
            $locales->addTranslations('ar', $file);
        }
    }
}

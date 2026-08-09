<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate\TranslationProviders;

use Stichoza\GoogleTranslate\GoogleTranslate;

class GoogleFree extends AbstractTranslationProvider implements TranslationProviderInterface
{
    /**
     * @var GoogleTranslate
     */
    protected $translator;

    public static string $name = 'google-free';
    public static string $link = ''; // No link provided for GoogleFree

    protected function initialize()
    {
        $this->translator = new GoogleTranslate();
    }

    public function options(): array
    {
        return [];
    }

    protected function translate(string $content, string $toLanguage, string $from = null): string
    {
        $this->ensureInitialized();
        
        $translation = $this->translator
            ->setSource($from)
            ->setTarget($toLanguage)
            ->translate($content);

        if (!$translation) {
            return '';
        }

        return $translation;
    }

    protected function identify(string $content): string
    {
        $this->ensureInitialized();
        
        $this->translator
            ->setTarget('en')
            ->translate($content);

        return $this->translator->getLastDetectedSource() ?? 'unknown';
    }

    protected function languages(): array
    {
        // There is no endpoint to hit to get the supported languages, so we'll just return the ones that Google Translate supports.
        return [
            'af', 'sq', 'am', 'ar', 'hy', 'as', 'az', 'eu', 'bm', 'eu', 'be', 'bn', 'bs', 'bg', 'ca', 'zh', 'zh-CN', 'zh-TW', 'co', 'hr', 'cs',
            'da', 'dv', 'nl', 'en', 'eo', 'et', 'fi', 'fr', 'fy', 'gl', 'ka', 'de',  'el', 'gn', 'gu', 'ht', 'ha', 'he', 'iw', 'hi', 'hu', 'is',
            'ig', 'id', 'ga', 'it', 'ja', 'jv', 'jw', 'kn', 'kk', 'km', 'rw', 'ko', 'ku', 'ky', 'lo', 'la', 'lv', 'lt', 'lb', 'mk', 'mg', 'ms',
            'ml', 'mt', 'mi', 'mr', 'mn', 'my', 'ne', 'no', 'ny', 'ps', 'fa', 'pl', 'pt', 'pa', 'ro', 'ru', 'sm', 'sa', 'gd', 'sr', 'st', 'sn',
            'sd', 'si', 'sk', 'sl', 'so', 'es', 'su', 'sw', 'sv', 'tl', 'tg', 'ta', 'tt', 'te', 'th', 'tr', 'tk', 'uk', 'ur', 'ug', 'uz', 'vi',
            'cy', 'xh', 'yi', 'yo', 'zu'
        ];
    }
}

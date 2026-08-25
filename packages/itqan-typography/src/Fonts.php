<?php

namespace Itqan\Typography;

use Illuminate\Contracts\Filesystem\Cloud;

/**
 * Where the webfonts live once published, and which of them are worth
 * preloading.
 */
class Fonts
{
    const DIRECTORY = 'extensions/itqan-typography/fonts';

    /**
     * Only the faces that are certain to be needed on every page belong here.
     * A preload that goes unused costs the same bandwidth as one that does,
     * and competes with the page for it — so the Latin subset is deliberately
     * left to be discovered normally.
     */
    const PRELOAD = [
        'NotoSansArabic-arabic.woff2',
    ];

    public static function disk(): Cloud
    {
        // Resolved by name, the way core does it — the Factory contract is not
        // bound in Flarum's container.
        return resolve('filesystem')->disk('flarum-assets');
    }

    public static function baseUrl(): string
    {
        return rtrim(static::disk()->url(self::DIRECTORY), '/');
    }

    public static function preloads(): array
    {
        return array_map(function (string $file) {
            return [
                'href' => static::baseUrl().'/'.$file,
                'as' => 'font',
                'type' => 'font/woff2',
                // Fonts are fetched in anonymous CORS mode even from the same
                // origin. Without this the preload is not reused and the file
                // is downloaded twice.
                'crossorigin' => '',
            ];
        }, self::PRELOAD);
    }
}

<?php

namespace Itqan\Theme\Content;

use Flarum\Frontend\Document;

/**
 * Opens connections to the third-party origins the page always talks to.
 *
 * The newsletter embed (MailerLite fonts + helper script) and analytics load
 * on every page, and each one otherwise pays a fresh DNS + TLS handshake
 * before its first byte. Preconnect lets the browser do that while the forum's
 * own bundle is still parsing. The list is deliberately limited to origins the
 * CSP already allows and that every page actually uses.
 */
class Preconnect
{
    /** @var array<string, bool> origin => needs CORS mode (fonts/CDN) */
    private const ORIGINS = [
        'https://assets.mlcdn.com' => true,
        'https://fonts.mailerlite.com' => true,
        'https://groot.mailerlite.com' => false,
        // googletagmanager is already preconnected by the analytics extension;
        // adding it here produced a duplicate link.
        'https://www.google-analytics.com' => false,
    ];

    public function __invoke(Document $document): void
    {
        foreach (self::ORIGINS as $origin => $cors) {
            $document->head[] = sprintf(
                '<link rel="preconnect" href="%s"%s>',
                $origin,
                $cors ? ' crossorigin' : ''
            );
        }
    }
}

<?php

namespace Itqan\Translate;

/**
 * Text hygiene for translation payloads.
 *
 * Flarum stores post bodies as s9e XML (`<r><p>…<POSTMENTION …>`), and the 1.8
 * extension cached translations of that markup verbatim. Passing it to a
 * provider returns the tags untouched, and the client renders them as literal
 * text. Everything that reaches the API is reduced to plain text here.
 */
class Text
{
    public static function sanitize(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // Block-level boundaries become line breaks before the tags are dropped.
        $text = preg_replace('#<\s*(br|/p|/li|/blockquote|/h[1-6]|/div|/r)\s*/?>#i', "\n", $value) ?? $value;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Does this string still carry markup that would leak into the UI?
     */
    public static function hasMarkup(string $value): bool
    {
        return (bool) preg_match('/<\/?[a-z][^>]*>/i', $value);
    }
}

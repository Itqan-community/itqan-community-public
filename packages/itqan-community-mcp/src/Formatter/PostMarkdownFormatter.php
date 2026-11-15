<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Formatter;

use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Itqan\CommunityMcp\Search\ArabicQueryNormalizer;

/**
 * Convert Flarum post content into clean Markdown for MCP tool results.
 *
 * Post content is stored as s9e/TextFormatter XML (`<t>...</t>`) in both
 * Flarum 1.x and 2.x. The helper tries the configured Flarum formatter first,
 * then falls back to a standalone unparse or a tolerant tag strip, so it also
 * works in isolated tests where the Flarum container is not booted.
 */
final class PostMarkdownFormatter
{
    public const HIDDEN_MESSAGE = '_[تم حذف هذا التعليق / Post hidden]_';

    public const TRUNCATED_SUFFIX = "\n\n...[تم اختصار المحتوى لطوله]...";

    private const PLACEHOLDER_PREFIX = '%%%ITQAN-MCP-BLOCK-';

    private const PLACEHOLDER_SUFFIX = '%%%';

    /**
     * Render a post as Markdown, hiding removed posts and truncating long ones.
     */
    public static function toMarkdown(Post $post, int $maxLength = 15000): string
    {
        if (self::isHidden($post)) {
            return self::HIDDEN_MESSAGE;
        }

        $markdown = self::xmlToMarkdown(self::rawContent($post), $post);

        if ($maxLength > 0 && mb_strlen($markdown, 'UTF-8') > $maxLength) {
            $markdown = rtrim(mb_substr($markdown, 0, $maxLength, 'UTF-8'))
                . self::TRUNCATED_SUFFIX;
        }

        return $markdown;
    }

    /**
     * Convert s9e XML (or legacy HTML) content into Markdown.
     */
    public static function xmlToMarkdown(string $rawXml, ?Post $post = null): string
    {
        $trimmed = trim($rawXml);
        $markdown = '';

        if ($trimmed !== '' && str_starts_with($trimmed, '<')) {
            if (self::looksLikeS9eXml($trimmed)) {
                $unparsed = self::unparseWithFlarum($trimmed);

                if ($unparsed === null) {
                    $unparsed = self::unparseStandalone($trimmed);
                }

                $markdown = $unparsed;
            } else {
                // Already-rendered HTML, for example content_html from a payload.
                $markdown = self::htmlToMarkdown($trimmed);
            }
        } elseif ($trimmed !== '') {
            // Plain Markdown/text was supplied directly.
            $markdown = $trimmed;
        }

        if (trim($markdown) === '' && $post !== null) {
            $html = self::renderedHtml($post);

            if ($html !== '') {
                $markdown = self::htmlToMarkdown($html);
            }
        }

        return self::normalizeWhitespace($markdown);
    }

    /**
     * Convert rendered HTML into Markdown.
     */
    public static function htmlToMarkdown(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        /** @var array<string, string> $blocks */
        $blocks = [];

        $store = static function (string $value) use (&$blocks): string {
            $token = self::PLACEHOLDER_PREFIX . count($blocks) . self::PLACEHOLDER_SUFFIX;
            $blocks[$token] = $value;

            return $token;
        };

        // Fenced code blocks first, so their contents are never tag-stripped or
        // treated as inline markup.
        $html = preg_replace_callback(
            '/<pre\b[^>]*>\s*<code\b([^>]*)>(.*?)<\/code>\s*<\/pre>/is',
            static function (array $matches) use ($store): string {
                $language = '';

                if (
                    preg_match(
                        '/class\s*=\s*["\'][^"\']*(?:language|lang)-([a-z0-9_+-]+)/i',
                        $matches[1],
                        $languageMatch
                    ) === 1
                ) {
                    $language = strtolower($languageMatch[1]);
                }

                $code = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $store("\n\n```{$language}\n" . rtrim($code, "\n") . "\n```\n\n");
            },
            $html
        ) ?? $html;

        // <pre> without an inner <code>.
        $html = preg_replace_callback(
            '/<pre\b[^>]*>(.*?)<\/pre>/is',
            static function (array $matches) use ($store): string {
                $code = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $store("\n\n```\n" . rtrim($code, "\n") . "\n```\n\n");
            },
            $html
        ) ?? $html;

        // Inline code.
        $html = preg_replace_callback(
            '/<code\b[^>]*>(.*?)<\/code>/is',
            static function (array $matches) use ($store): string {
                $code = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $code = trim(preg_replace('/\s+/u', ' ', $code) ?? $code);

                return $store('`' . $code . '`');
            },
            $html
        ) ?? $html;

        // Block quotes: prefix every line with "> ".
        $html = preg_replace_callback(
            '/<blockquote\b[^>]*>(.*?)<\/blockquote>/is',
            static function (array $matches): string {
                $inner = preg_replace('/<br\s*\/?>/i', "\n", $matches[1]) ?? $matches[1];
                $inner = preg_replace('/<\/p>\s*<p\b[^>]*>/i', "\n\n", $inner) ?? $inner;
                $inner = preg_replace('/<\/?(?:p|div)\b[^>]*>/i', '', $inner) ?? $inner;
                $inner = trim($inner);

                if ($inner === '') {
                    return '';
                }

                $lines = preg_split('/\R/u', $inner) ?: [$inner];
                $lines = array_map(
                    static fn (string $line): string => '> ' . trim($line),
                    $lines
                );

                return "\n\n" . implode("\n", $lines) . "\n\n";
            },
            $html
        ) ?? $html;

        // Headings.
        $html = preg_replace_callback(
            '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is',
            static fn (array $matches): string => "\n\n"
                . str_repeat('#', (int) $matches[1])
                . ' '
                . trim($matches[2])
                . "\n\n",
            $html
        ) ?? $html;

        // Links.
        $html = preg_replace_callback(
            '/<a\b([^>]*)>(.*?)<\/a>/is',
            static function (array $matches): string {
                if (
                    preg_match(
                        '/href\s*=\s*(["\'])(.*?)\1/is',
                        $matches[1],
                        $hrefMatch
                    ) !== 1
                ) {
                    return $matches[2];
                }

                $href = html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return '[' . trim($matches[2]) . '](' . $href . ')';
            },
            $html
        ) ?? $html;

        // Emphasis.
        $html = preg_replace('/<(strong|b)\b[^>]*>(.*?)<\/\1>/is', '**$2**', $html) ?? $html;
        $html = preg_replace('/<(em|i)\b[^>]*>(.*?)<\/\1>/is', '*$2*', $html) ?? $html;

        // Lists, line breaks and paragraphs.
        $html = preg_replace('/<li\b[^>]*>/i', "\n- ", $html) ?? $html;
        $html = preg_replace('/<\/li>/i', '', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/p>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<\/(?:div|section|article|tr|ul|ol)>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<hr\b[^>]*\/?>/i', "\n\n---\n\n", $html) ?? $html;

        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($blocks !== []) {
            $html = strtr($html, $blocks);
        }

        return self::normalizeWhitespace($html);
    }

    /**
     * Produce a compact one-line snippet, optionally centred on a query keyword.
     */
    public static function createSnippet(
        string $markdown,
        ?string $query = null,
        int $maxLength = 400
    ): string {
        $maxLength = max(1, $maxLength);

        $text = self::normalizeWhitespace($markdown);
        $text = preg_replace('/\s*\n+\s*/u', ' | ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }

        $haystack = $text;
        $position = null;

        if ($query !== null && trim($query) !== '') {
            $keywords = ArabicQueryNormalizer::extractKeywords($query);

            if ($keywords === []) {
                $keywords = [trim($query)];
            }

            foreach ($keywords as $keyword) {
                if ($keyword === '') {
                    continue;
                }

                $found = mb_stripos($haystack, $keyword, 0, 'UTF-8');

                if ($found !== false) {
                    $position = $found;

                    break;
                }
            }

            if ($position === null) {
                // Retry against letter-normalized text for spelling variants.
                $normalizedText = ArabicQueryNormalizer::normalizeLetters($text);

                foreach ($keywords as $keyword) {
                    $normalizedKeyword = ArabicQueryNormalizer::normalizeLetters($keyword);
                    $found = mb_stripos($normalizedText, $normalizedKeyword, 0, 'UTF-8');

                    if ($found !== false) {
                        $haystack = $text;
                        $position = $found;

                        break;
                    }
                }
            }
        }

        $totalLength = mb_strlen($haystack, 'UTF-8');

        if ($position === null) {
            $snippet = mb_substr($haystack, 0, $maxLength, 'UTF-8');

            return $snippet . ($totalLength > $maxLength ? '...' : '');
        }

        $start = max(0, $position - intdiv($maxLength, 2));
        $snippet = mb_substr($haystack, $start, $maxLength, 'UTF-8');

        if ($start > 0) {
            $snippet = '...' . $snippet;
        }

        if ($start + $maxLength < $totalLength) {
            $snippet .= '...';
        }

        return trim($snippet);
    }

    /**
     * Normalize line endings, trailing spaces and repeated blank lines.
     */
    public static function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;

        $lines = explode("\n", $text);
        $lines = array_map(static fn (string $line): string => rtrim($line), $lines);
        $text = implode("\n", $lines);

        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private static function isHidden(Post $post): bool
    {
        try {
            if ($post->hidden_at !== null) {
                return true;
            }
        } catch (\Throwable) {
            // Date casting needs a database connection; inspect raw attributes.
        }

        try {
            if (method_exists($post, 'getRawOriginal') && $post->getRawOriginal('hidden_at') !== null) {
                return true;
            }
        } catch (\Throwable) {
            // Attribute unavailable.
        }

        try {
            $attributes = $post->getAttributes();

            return isset($attributes['hidden_at']) && $attributes['hidden_at'] !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function rawContent(Post $post): string
    {
        $raw = null;

        if (method_exists($post, 'getRawOriginal')) {
            try {
                $raw = $post->getRawOriginal('content');
            } catch (\Throwable) {
                $raw = null;
            }
        }

        // Unsaved/partially hydrated models keep the value in the attribute bag
        // only; prefer it over the accessor, which needs a configured formatter.
        if (! is_string($raw) || $raw === '') {
            try {
                $attributes = $post->getAttributes();

                if (isset($attributes['content']) && is_string($attributes['content'])) {
                    $raw = $attributes['content'];
                }
            } catch (\Throwable) {
                // Not an Eloquent model after all.
            }
        }

        if (! is_string($raw)) {
            try {
                $raw = $post->content;
            } catch (\Throwable) {
                $raw = null;
            }
        }

        return is_string($raw) ? $raw : '';
    }

    private static function renderedHtml(Post $post): string
    {
        if (method_exists($post, 'formatContent')) {
            try {
                $formatted = $post->formatContent();

                if (is_string($formatted) && trim($formatted) !== '') {
                    return $formatted;
                }
            } catch (\Throwable) {
                // Formatter unavailable; fall through to content_html.
            }
        }

        try {
            $html = $post->content_html;
        } catch (\Throwable) {
            $html = null;
        }

        return is_string($html) ? $html : '';
    }

    private static function looksLikeS9eXml(string $value): bool
    {
        return preg_match('/^<(?:t|r)(?:\s|>)/i', $value) === 1;
    }

    /**
     * Try the Flarum-configured TextFormatter.
     */
    private static function unparseWithFlarum(string $xml): ?string
    {
        if (class_exists(CommentPost::class) && method_exists(CommentPost::class, 'getFormatter')) {
            try {
                $formatter = CommentPost::getFormatter();

                if (is_object($formatter) && method_exists($formatter, 'unparse')) {
                    $result = $formatter->unparse($xml);

                    if (is_string($result)) {
                        return $result;
                    }
                }
            } catch (\Throwable) {
                // Container not booted or formatter not configured.
            }
        }

        if (function_exists('resolve') && class_exists(\Flarum\Formatter\Formatter::class)) {
            try {
                $formatter = resolve(\Flarum\Formatter\Formatter::class);

                if (is_object($formatter) && method_exists($formatter, 'unparse')) {
                    $result = $formatter->unparse($xml);

                    if (is_string($result)) {
                        return $result;
                    }
                }
            } catch (\Throwable) {
                // No container binding available.
            }
        }

        return null;
    }

    /**
     * Standalone s9e unparse, or a tolerant XML tag strip.
     */
    private static function unparseStandalone(string $xml): string
    {
        if (class_exists(\s9e\TextFormatter\Unparser::class)) {
            try {
                $result = \s9e\TextFormatter\Unparser::unparse($xml);

                if (is_string($result)) {
                    return $result;
                }
            } catch (\Throwable) {
                // Malformed XML; fall back to a tag strip.
            }
        }

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

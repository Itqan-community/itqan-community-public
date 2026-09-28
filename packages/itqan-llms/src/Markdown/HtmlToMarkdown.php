<?php

namespace Itqan\Llms\Markdown;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Turns the HTML that Flarum's text formatter renders into Markdown.
 *
 * The reason this class exists: `Post::$content` cannot be used for this.
 * That accessor runs the stored TextFormatter XML back through
 * `s9e\TextFormatter\Unparser::unparse()`, which is
 * `html_entity_decode(strip_tags($xml))`. That throws away every link target,
 * list, code fence and paragraph break, and glues adjacent blocks together.
 * The rendered HTML is the only faithful representation of a post, so the
 * export renders it and converts it here.
 */
class HtmlToMarkdown
{
    /**
     * The forum's base URL, used to resolve in-site links. Null leaves
     * root-relative paths as they are, so the converter stays usable on its own.
     */
    private ?string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl === null ? null : rtrim($baseUrl, '/');
    }

    /**
     * A copy that resolves in-site links against a base URL.
     *
     * Flarum renders a post's own links as root-relative paths. In an exported
     * document those are the citations a provider is most likely to quote, and
     * a relative one has no domain to attribute the quote to, so they are
     * resolved to the community's own URL.
     */
    public function withBaseUrl(?string $baseUrl): self
    {
        $clone = clone $this;
        $clone->baseUrl = $baseUrl === null ? null : rtrim($baseUrl, '/');

        return $clone;
    }
    /**
     * Escapes the characters that would otherwise be read as Markdown syntax.
     */
    private const ESCAPE_PATTERN = '/([\\\\`*_\[\]<>])/';

    /**
     * Sentinel for a `<br>`, swapped for a real Markdown hard break once the
     * whitespace cleanup has run.
     */
    private const HARD_BREAK = "\0br\0";

    /**
     * Stands in for the two trailing spaces of a hard break while tidy() runs.
     *
     * tidy() strips trailing whitespace from every line, which would remove a
     * real "  \n" hard break. The flag carries the line break through instead
     * and becomes the two spaces at the very end.
     */
    private const HARD_FLAG = "\u{E001}";

    /**
     * Marks a line whose leading block syntax this class generated, as opposed
     * to syntax that came out of the post's text.
     *
     * escapeBlockStarts() cannot tell them apart from the document alone, and
     * escaping the generated ones would turn every heading and list item into
     * literal text. It looks for this marker, strips it, and leaves the line
     * alone.
     *
     * U+E000, a private-use codepoint, rather than a control character: PHP's
     * trim() strips " \t\n\r\0\x0B" by default, so a NUL marker is silently
     * removed by the trim() calls that tidy up nested lists, and the first line
     * of a nested list then loses its escape protection. Nothing trims this.
     */
    private const GENERATED = "\u{E000}";

    /**
     * Tags whose text is verbatim and must never be escaped or reflowed.
     */
    private const PREFORMATTED = ['pre', 'code', 'kbd', 'samp'];

    /**
     * Block-level tags, used to decide whether a newline is already needed.
     */
    private const BLOCK = [
        'address', 'article', 'aside', 'blockquote', 'details', 'div', 'dl', 'dd', 'dt',
        'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5',
        'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'table',
        'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
    ];

    /**
     * Convert a fragment of rendered post HTML to Markdown.
     */
    public function convert(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $document = $this->loadFragment($html);
        $markdown = $this->walkChildren($document);

        // The break becomes a real newline first, because that newline is a line
        // start: `<p>text<br># hi</p>` only produces a heading-looking line once
        // the break is materialised. The two trailing spaces are deferred to
        // HARD_FLAG so tidy() cannot strip them.
        $markdown = str_replace(self::HARD_BREAK, self::HARD_FLAG."\n", $markdown);

        $markdown = $this->escapeBlockStarts($markdown);
        $markdown = $this->tidy($markdown);

        return str_replace(self::HARD_FLAG, '  ', $markdown);
    }

    /**
     * Parse a fragment without the HTML5 quirks that would otherwise reshape
     * it. Flarum emits a well-formed subset, and `loadHTML` would silently
     * inject `<html><body>` and relocate leading text nodes.
     */
    private function loadFragment(string $html): DOMNode
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="itqan-llms-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('itqan-llms-root');

        return $root instanceof DOMElement ? $root : $document;
    }

    private function walkChildren(DOMNode $node): string
    {
        $out = '';

        foreach ($node->childNodes as $child) {
            $out .= $this->walk($child);
        }

        return $out;
    }

    private function walk(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $this->escapeText($node->textContent);
        }

        if ($node instanceof DOMComment) {
            return '';
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        // Script and style bodies are CDATA-ish text nodes, not content. A post
        // is already sanitised on the way in, but emitting them would put
        // executable source into a file a crawler then reads.
        if (in_array(strtolower($node->tagName), ['script', 'style', 'noscript', 'template'], true)) {
            return '';
        }

        return $this->walkElement($node);
    }

    private function walkElement(DOMElement $element): string
    {
        $tag = strtolower($element->tagName);

        // Custom tags from the formatter stack (spoilers, mentions, media
        // embeds) are handled by name before the generic tag dispatch.
        $custom = $this->walkCustom($element, $tag);

        if ($custom !== null) {
            return $custom;
        }

        switch ($tag) {
            case 'p':
                return $this->block($this->inline($element));
            case 'br':
                // Placeholder, not the literal two-space hard break: the
                // trailing-whitespace cleanup in tidy() would eat it. It is
                // restored at the very end of convert().
                return self::HARD_BREAK;
            case 'hr':
                return $this->block(self::GENERATED.'---');
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                $level = (int) substr($tag, 1);

                return $this->block($this->padHeading(str_repeat('#', $level).' '.$this->inline($element)));
            case 'strong':
            case 'b':
                return $this->wrapInline($this->inline($element), '**');
            case 'em':
            case 'i':
                return $this->wrapInline($this->inline($element), '*');
            case 'u':
            case 'ins':
                // Markdown has no underline. The text is kept, because
                // dropping it would lose content.
                return $this->inline($element);
            case 's':
            case 'del':
            case 'strike':
                return $this->wrapInline($this->inline($element), '~~');
            case 'a':
                return $this->link($element);
            case 'img':
                return $this->image($element);
            case 'ul':
            case 'ol':
                return $this->block($this->list($element, $tag === 'ol'));
            case 'li':
                // Reached only for a stray <li> outside a list.
                return $this->block($this->inline($element));
            case 'blockquote':
                return $this->block($this->blockquote($element));
            case 'pre':
                return $this->block($this->preformatted($element));
            case 'code':
                return $this->inlineCode($element);
            case 'table':
                return $this->block($this->table($element));
            case 'details':
                return $this->block($this->details($element));
            case 'figure':
            case 'figcaption':
            case 'dl':
            case 'dd':
            case 'dt':
                return $this->block($this->inline($element));
            default:
                // Unknown wrapper: keep the text, recursing so that any
                // formatting inside it survives.
                return $this->inline($element);
        }
    }

    /**
     * Tags the formatter stack adds on top of plain HTML.
     *
     * Returns null when the tag is not one of ours, so the caller falls
     * through to the generic switch.
     */
    private function walkCustom(DOMElement $element, string $tag): ?string
    {
        if ($tag === 'span' && $this->hasClass($element, 'spoiler')) {
            // A spoiler's contents are deliberately hidden in the UI, but an
            // LLM reading a public thread should still get them; they were
            // already visible to anyone who revealed the spoiler.
            return $this->inline($element);
        }

        if ($tag === 'a' && $this->hasClass($element, 'PostMention')) {
            return $this->inline($element);
        }

        if (in_array($tag, ['iframe', 'video', 'audio', 'source'], true)) {
            return $this->embed($element);
        }

        if ($tag === 'img' || $tag === 'a') {
            return null;
        }

        return null;
    }

    /**
     * Render an embedded player as a link, so the target is never lost.
     */
    private function embed(DOMElement $element): string
    {
        $src = $element->getAttribute('src');

        if ($src === '') {
            $source = $element->getElementsByTagName('source')->item(0);

            if ($source instanceof DOMElement) {
                $src = $source->getAttribute('src');
            }
        }

        if ($src === '') {
            return '';
        }

        return '['.$this->embedLabel($element).']('.$this->safeUrl($src).')';
    }

    private function embedLabel(DOMElement $element): string
    {
        $label = strtolower($element->tagName);

        return $label === 'iframe' ? 'Embedded media' : ucfirst($label);
    }

    private function link(DOMElement $element): string
    {
        $text = $this->inline($element);
        $href = $this->safeUrl($element->getAttribute('href'));

        if ($href === '') {
            return $text;
        }

        if ($text === '') {
            return '<'.$href.'>';
        }

        // Compared against the raw text, not the escaped label: escapeText
        // backslash-escapes `_`, so a URL like https://x/a_b would never match
        // its own label and the autolink would degrade into
        // [https://x/a\_b](https://x/a_b).
        if (trim($element->textContent) === $href) {
            return '<'.$href.'>';
        }

        return '['.$text.']('.$href.')';
    }

    private function image(DOMElement $element): string
    {
        $alt = trim($element->getAttribute('alt'));
        $src = $this->safeUrl($element->getAttribute('src'));

        if ($src === '') {
            return '';
        }

        return '!['.$this->escapeText($alt).']('.$src.')';
    }

    private function list(DOMElement $element, bool $ordered): string
    {
        $lines = [];
        $index = 0;

        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }

            $index++;
            $marker = self::GENERATED.($ordered ? $index.'. ' : '- ');

            $lines[] = $marker.$this->listItem($child);
        }

        return implode("\n", $lines);
    }

    /**
     * A list item may itself contain a nested list, which has to be indented
     * under its parent item to stay part of it.
     */
    private function listItem(DOMElement $item): string
    {
        $own = [];
        $nested = [];

        foreach ($item->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if ($tag === 'ul' || $tag === 'ol') {
                    $nested[] = $this->list($child, $tag === 'ol');
                    continue;
                }

                if ($tag === 'p') {
                    $own[] = $this->inline($child);
                    continue;
                }
            }

            $own[] = $this->walk($child);
        }

        $text = trim($this->tidy(implode(' ', $own)));
        $out = $text;

        foreach ($nested as $sub) {
            $sub = trim($sub);

            if ($sub === '') {
                continue;
            }

            $indented = implode("\n", array_map(
                fn (string $line): string => ($line === '' ? '' : '  '.$line),
                explode("\n", $sub)
            ));

            $out .= "\n".$indented;
        }

        return $out;
    }

    private function blockquote(DOMElement $element): string
    {
        $inner = trim($this->tidy($this->block($this->inline($element))));

        if ($inner === '') {
            return '';
        }

        return implode("\n", array_map(
            fn (string $line): string => ($line === '' ? self::GENERATED.'>' : self::GENERATED.'> '.$line),
            explode("\n", $inner)
        ));
    }

    /**
     * Fenced code block, keeping the language hint Flarum puts in the class
     * name (`<code class="language-php">`).
     */
    private function preformatted(DOMElement $element): string
    {
        $code = $element->getElementsByTagName('code')->item(0);
        $target = $code instanceof DOMElement ? $code : $element;

        $text = $target->textContent;
        $text = str_replace("\r\n", "\n", $text);
        $text = rtrim($text, "\n");

        if (trim($text) === '') {
            return '';
        }

        $language = $this->codeLanguage($target);

        return "```".$language."\n".$text."\n```";
    }

    private function codeLanguage(DOMElement $element): string
    {
        $class = $element->getAttribute('class');

        if (preg_match('/(?:^|\s)language-([A-Za-z0-9_+#.-]+)/', $class, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function inlineCode(DOMElement $element): string
    {
        $text = $element->textContent;

        if (trim($text) === '') {
            return '';
        }

        // Count the longest backtick run so a fence inside the code survives.
        preg_match_all('/`+/', $text, $matches);
        $longest = 0;

        foreach ($matches[0] as $run) {
            $longest = max($longest, strlen($run));
        }

        $fence = str_repeat('`', $longest + 1);
        $pad = str_contains($text, '`') ? ' ' : '';

        return $fence.$pad.$text.$pad.$fence;
    }

    /**
     * Flarum has no Markdown table syntax, but askvortsov/flarum-markdown-tables
     * renders one, so the export emits a GFM table and keeps the cells.
     */
    private function table(DOMElement $element): string
    {
        $rows = [];
        $header = [];

        foreach ($element->getElementsByTagName('tr') as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = [];
            $isHeader = false;

            foreach ($row->childNodes as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }

                $tag = strtolower($cell->tagName);

                if ($tag === 'th') {
                    $isHeader = true;
                }

                if (! in_array($tag, ['td', 'th'], true)) {
                    continue;
                }

                $cells[] = $this->tableCell($cell);
            }

            if ($cells === []) {
                continue;
            }

            if ($isHeader && $header === []) {
                $header = $cells;
                $rows[] = $cells;
                continue;
            }

            $rows[] = $cells;
        }

        if ($rows === []) {
            return '';
        }

        if ($header === []) {
            $header = array_fill(0, count($rows[0]), '');
            array_unshift($rows, $header);
        }

        $width = max(count($header), ...array_map('count', $rows));

        $header = array_pad($header, $width, '');
        $lines = [$this->tableRow($header), $this->tableRow(array_fill(0, $width, '---'))];

        foreach (array_slice($rows, 1) as $row) {
            $lines[] = $this->tableRow(array_pad($row, $width, ''));
        }

        return implode("\n", $lines);
    }

    /**
     * A cell cannot contain a raw newline, which would end the table row.
     */
    private function tableCell(DOMElement $cell): string
    {
        $text = trim($this->tidy($this->block($this->inline($cell))));
        $text = str_replace(["\r\n", "\n", '|'], [' ', ' ', '\\|'], $text);

        return $text;
    }

    private function tableRow(array $cells): string
    {
        return '| '.implode(' | ', $cells).' |';
    }

    /**
     * <details>/<summary> is the fallback rendering for Flarum spoilers and
     * collapsed sections. The summary becomes a bold lead-in so the reader
     * still knows a summary existed.
     */
    private function details(DOMElement $element): string
    {
        $summary = $element->getElementsByTagName('summary')->item(0);
        $parts = [];

        if ($summary instanceof DOMElement) {
            $label = trim($this->tidy($this->inline($summary)));

            if ($label !== '') {
                $parts[] = '**'.$label.'**';
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'summary') {
                continue;
            }

            $rendered = trim($this->walk($child));

            if ($rendered !== '') {
                $parts[] = $rendered;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Children rendered as inline content, i.e. paragraph breaks are not
     * invented. Block children are handled by the caller instead.
     */
    private function inline(DOMElement $element): string
    {
        $out = '';

        foreach ($element->childNodes as $child) {
            $out .= $this->walk($child);
        }

        return $out;
    }

    private function wrapInline(string $text, string $marker): string
    {
        $text = trim($text);

        // Emphasis is dropped rather than emitted around whitespace, which
        // Markdown would not render as emphasis anyway.
        if ($text === '' || trim($text) !== $text) {
            return $text;
        }

        return $marker.$text.$marker;
    }

    private function padHeading(string $text): string
    {
        return "\n".self::GENERATED.$text."\n";
    }

    private function block(string $text): string
    {
        $text = trim($text, "\n");

        return $text === '' ? '' : "\n\n".$text."\n\n";
    }

    private function escapeText(string $text): string
    {
        // Already inside code or a link label, where backslashes would show up
        // literally. The caller decides by using raw textContent there.
        return preg_replace(self::ESCAPE_PATTERN, '\\\\$1', $text);
    }

    private function hasClass(DOMElement $element, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [], true);
    }

    /**
     * Only absolute http(s) and mailto targets are kept. A `javascript:` or
     * `data:` URL copied out of a post would otherwise be handed to a crawler
     * verbatim.
     */
    private function safeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');

        if (preg_match('#^(?:https?://|mailto:)#i', $url)) {
            return $url;
        }

        // Protocol-relative, i.e. //host/path. Normalised to https rather than
        // dropped: the s9e emoji plugin renders
        // <img class="emoji" src="//cdn..."> in some configurations, and
        // dropping it would silently delete a character from a post. It carries
        // no scheme of its own, so https is assumed, which is what a browser
        // on a page served over https would do.
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        // A root-relative path into this forum, resolved so a provider lifting
        // the citation can attribute it. A protocol-relative "//host" is a
        // different thing and is handled above.
        if (str_starts_with($url, '/')) {
            return $this->baseUrl === null ? $url : $this->baseUrl.$url;
        }

        // A bare host.
        if (preg_match('#^[^/\\\\]+\.[a-z]{2,}(?:[/:?\#]|$)#i', $url)) {
            return 'https://'.$url;
        }

        return '';
    }

    private function looksLikeUrl(string $text): bool
    {
        return (bool) preg_match('#^(?:https?://|mailto:|/)#i', $text);
    }

    /**
     * Collapse the blank lines the block helpers introduce, and trim the ends.
     */
    private function tidy(string $markdown): string
    {
        $markdown = str_replace("\r\n", "\n", $markdown);

        // Trailing whitespace is collapsed. A `<br>` is carried through as a
        // sentinel rather than as real spaces, so this cannot eat a hard break.
        $markdown = preg_replace('/[ \t]+\n/', "\n", $markdown);
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);

        return trim($markdown, "\n");
    }

    /**
     * Escape Markdown block syntax that would otherwise be introduced by the
     * text itself.
     *
     * escapeText() handles inline characters, which is not enough: `<p># hi</p>`
     * renders as an H1, `<p>1. x</p>` as an ordered list and `<p>- x</p>` as a
     * bullet. A post could then present itself to a model as a heading or a list
     * item, or introduce a section the author did not write.
     *
     * Applied per line and only outside fenced code, because inside a fence
     * those characters are the content and must survive verbatim.
     */
    private function escapeBlockStarts(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $inFence = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*(```|~~~)/', $line)) {
                $inFence = ! $inFence;
                continue;
            }

            if ($inFence) {
                continue;
            }

            // Leading whitespace, then either our own marker or post text.
            if (! preg_match('/^(\s*)(.*)$/', $line, $m)) {
                continue;
            }

            $body = $m[2];

            if (str_starts_with($body, self::GENERATED)) {
                // Generated block syntax. Every marker on the line goes, not
                // only a leading one: wrapping a nested list in a blockquote
                // leaves the inner marker mid-line, where a leading-only match
                // would not find it.
                $lines[$index] = $m[1].str_replace(self::GENERATED, '', $body);
                continue;
            }

            // Two passes, because a backslash only escapes ASCII punctuation.
            // Escaping the whole construct for an ordered list put a backslash
            // in front of the digits — "\1. x" — which is not an escape at all,
            // so the backslash survived into the rendered text as a literal
            // character. The digits are kept and only the "." or ")" is
            // escaped, giving "1\. x".
            $body = preg_replace(
                '/^(\s*)(\d{1,9})([.)])(?=\s)/',
                '$1$2\\\\$3',
                $body,
                1
            );

            $lines[$index] = preg_replace(
                '/^(\s*)(#{1,6}|>|[-+*]|={2,})(?=\s)/',
                '$1\\\\$2',
                $body,
                1
            );
        }

        return implode("\n", $lines);
    }
}

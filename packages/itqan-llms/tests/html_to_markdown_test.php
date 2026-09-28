<?php

// Run: php packages/itqan-llms/tests/html_to_markdown_test.php
// Exits non-zero on the first failed assertion. No Flarum boot needed.
//
// The assertions here are the reason this class exists. The upstream
// flarum-for-llms extension used `$post->content` and described it as "the
// original, raw unparsed Markdown content". It is not: that accessor runs the
// stored TextFormatter XML through `strip_tags`, so a post with a link comes
// back as its label with the URL gone. These cases pin the conversion down so
// that regression cannot come back quietly.

error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__.'/../../../vendor/autoload.php';

use Itqan\Llms\Markdown\HtmlToMarkdown;

$converter = new HtmlToMarkdown;

$failures = 0;

function check(string $name, string $expected, string $actual): void
{
    global $failures;

    if ($expected === $actual) {
        echo "PASS $name\n";

        return;
    }

    $failures++;
    echo "FAIL $name\n";
    echo "  expected: ".var_export($expected, true)."\n";
    echo "  actual:   ".var_export($actual, true)."\n";
}

function checkContains(string $name, string $needle, string $haystack): void
{
    global $failures;

    if (str_contains($haystack, $needle)) {
        echo "PASS $name\n";

        return;
    }

    $failures++;
    echo "FAIL $name\n";
    echo "  expected to contain: ".var_export($needle, true)."\n";
    echo "  actual:              ".var_export($haystack, true)."\n";
}

function checkNotContains(string $name, string $needle, string $haystack): void
{
    global $failures;

    if (! str_contains($haystack, $needle)) {
        echo "PASS $name\n";

        return;
    }

    $failures++;
    echo "FAIL $name\n";
    echo "  expected NOT to contain: ".var_export($needle, true)."\n";
    echo "  actual:                  ".var_export($haystack, true)."\n";
}

// --- the regression that motivated the whole class ----------------------------

$link = $converter->convert('<p>See <a href="https://example.com/a">the docs</a>.</p>');
checkContains('link_url_survives', 'https://example.com/a', $link);
check('link_markdown', 'See [the docs](https://example.com/a).', $link);

$code = $converter->convert('<pre><code class="language-php">echo 1;</code></pre>');
check('code_fence_kept', "```php\necho 1;\n```", $code);

$twoParas = $converter->convert('<p>first</p><p>second</p>');
check('paragraphs_stay_separate', "first\n\nsecond", $twoParas);

// Against what the naive accessor produces, to show the difference is real.
$naive = html_entity_decode(strip_tags('<p>See <a href="https://example.com/a">the docs</a>.</p><p>first</p>'), ENT_QUOTES, 'UTF-8');
checkNotContains('naive_accessor_would_lose_url', 'https://example.com/a', $naive);
check('naive_accessor_glues_paragraphs', 'See the docs.first', $naive);

// --- inline formatting ---------------------------------------------------------

check('strong', 'a **b** c', $converter->convert('<p>a <strong>b</strong> c</p>'));
check('em', 'a *b* c', $converter->convert('<p>a <em>b</em> c</p>'));
check('strikethrough', 'a ~~b~~ c', $converter->convert('<p>a <s>b</s> c</p>'));
check('underline_keeps_text', 'a b c', $converter->convert('<p>a <u>b</u> c</p>'));
check('nested_formatting', '**bold [link](https://x.com) inside**', $converter->convert('<p><strong>bold <a href="https://x.com">link</a> inside</strong></p>'));

// --- links ---------------------------------------------------------------------

check('autolink_when_label_is_url', '<https://example.com/x>', $converter->convert('<p><a href="https://example.com/x">https://example.com/x</a></p>'));
check('relative_link_kept', '[thread](/d/5)', $converter->convert('<p><a href="/d/5">thread</a></p>'));
check('entity_in_href_decoded', '[docs](https://example.com/?a=1&b=2)', $converter->convert('<p><a href="https://example.com/?a=1&amp;b=2">docs</a></p>'));
check('javascript_url_dropped', 'click', $converter->convert('<p><a href="javascript:alert(1)">click</a></p>'));
check('data_url_image_dropped', '', $converter->convert('<p><img src="data:text/html;base64,PHNjcmlwdD4=" alt="x"></p>'));

// --- lists ---------------------------------------------------------------------

check('unordered', "- one\n- two", $converter->convert('<ul><li>one</li><li>two</li></ul>'));
check('ordered', "1. one\n2. two", $converter->convert('<ol><li>one</li><li>two</li></ol>'));
check(
    'nested_lists_indent',
    "- parent\n  - child",
    $converter->convert('<ul><li>parent<ul><li>child</li></ul></li></ul>')
);

// --- code ----------------------------------------------------------------------

check('inline_code', 'Use `composer install` now.', $converter->convert('<p>Use <code>composer install</code> now.</p>'));
check(
    'inline_code_with_backtick',
    '`` a ` b ``',
    $converter->convert('<p><code>a ` b</code></p>')
);
check('code_no_language', "```\nplain\n```", $converter->convert('<pre><code>plain</code></pre>'));

// --- other blocks --------------------------------------------------------------

check('blockquote', "> quoted\n>\n> second", $converter->convert('<blockquote><p>quoted</p><p>second</p></blockquote>'));
check('image', '![alt](https://cdn.example.com/a.png)', $converter->convert('<p><img src="https://cdn.example.com/a.png" alt="alt"></p>'));
check('spoiler_contents_included', 'before hidden after', $converter->convert('<p>before <span class="spoiler">hidden</span> after</p>'));
check('post_mention_text', '@bob replied', $converter->convert('<p><a href="https://f.com/d/9/2" class="PostMention" data-id="2">@bob</a> replied</p>'));
check('iframe_becomes_link', '[Embedded media](https://www.youtube-nocookie.com/embed/abc)', $converter->convert('<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>'));
check('table', "| A | B |\n| --- | --- |\n| 1 | 2 |", $converter->convert('<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>'));
check('hard_break', "line one  \nline two", $converter->convert('<p>line one<br>line two</p>'));

// --- edge cases ----------------------------------------------------------------

check('empty', '', $converter->convert(''));
check('null', '', $converter->convert(null));
check('whitespace_only', '', $converter->convert("   \n  "));
check('arabic_preserved', 'مرحبا **عربي**', $converter->convert('<p>مرحبا <strong>عربي</strong></p>'));
check(
    'markdown_chars_escaped',
    'a \\*b\\* c',
    $converter->convert('<p>a *b* c</p>')
);
check(
    'no_excess_blank_lines',
    "a\n\nb",
    $converter->convert("<p>a</p>\n\n\n\n<p>b</p>")
);
check(
    'script_content_dropped',
    '',
    $converter->convert('<script>alert(1)</script>')
);


// --- Markdown a post could not have written by accident ------------------------
//
// escapeText() only handles inline characters. A paragraph whose first
// character starts a block would be read by the model as structure the author
// never typed: a heading, a list, a quote.

check('heading_injection_escaped', '\\# not a heading', $converter->convert('<p># not a heading</p>'));
// A backslash only escapes ASCII punctuation. Escaping the whole construct
// gave "\1. not a list", where the backslash is not an escape and survives as
// a literal character — so the text was neither a list nor clean. The digits
// stay; only the "." is escaped.
check('ordered_list_injection_escaped', '1\\. not a list', $converter->convert('<p>1. not a list</p>'));
check('ordered_paren_injection_escaped', '1\\) not a list', $converter->convert('<p>1) not a list</p>'));
check('multi_digit_injection_escaped', '12\\. not a list', $converter->convert('<p>12. not a list</p>'));

// Every escape this class emits must be one Markdown recognises, or it shows up
// as a stray backslash in the rendered document. Built with preg_quote rather
// than a hand-written character class: the unescaped "/" inside one terminated
// the pattern, and preg_match then returned false for every input, so this
// assertion passed without testing anything.
$asciiPunctuation = "!\"#$%&'()*+,-./:;<=>?@[\\]^_`{|}~";

// The backslash is written as preg_quote(chr(92)) rather than as a literal or
// an escape: a single "\" in the pattern source escapes the "[" that follows it,
// so the class matched a literal bracket instead of a backslash and nothing was
// ever flagged.
$literalBackslash = preg_quote(chr(92), '/');

$escapesAreReal = true;

foreach ([
    '<p>1. x</p>', '<p>1) x</p>', '<p># x</p>', '<p>### x</p>', '<p>- x</p>',
    '<p>* x</p>', '<p>+ x</p>', '<p>&gt; x</p>', '<p>=== x</p>',
] as $sample) {
    $out = $converter->convert($sample);

    if (preg_match('/\\[^'.preg_quote($asciiPunctuation, '/').']/', $out)) {
        $escapesAreReal = false;
    }
}

check('every_escape_is_valid_markdown', true, $escapesAreReal);

// And the check itself works, rather than passing on every input.
check(
    'escape_check_detects_a_bad_escape',
    1,
    preg_match('/'.$literalBackslash.'[^'.preg_quote($asciiPunctuation, '/').']/', '\\1. x')
);
check(
    'escape_check_accepts_a_good_escape',
    0,
    preg_match('/'.$literalBackslash.'[^'.preg_quote($asciiPunctuation, '/').']/', '1\\. x')
);
check('bullet_injection_escaped', '\\- not a list', $converter->convert('<p>- not a list</p>'));
check('blockquote_injection_escaped', '\\> not a quote', $converter->convert('<p>&gt; not a quote</p>'));
check('setext_injection_escaped', '\\=== not a heading', $converter->convert('<p>=== not a heading</p>'));
check('injection_after_hard_break_escaped', "text  \n\\# hi", $converter->convert('<p>text<br># hi</p>'));
check('injection_in_later_paragraph_escaped', "first\n\n\\- sneaky", $converter->convert('<p>first</p><p>- sneaky</p>'));

// ...and the real headings and lists this class generates must survive.
check('generated_heading_untouched', "## Real Section\n\nbody", $converter->convert('<h2>Real Section</h2><p>body</p>'));
check('generated_list_untouched', "- one\n- two", $converter->convert('<ul><li>one</li><li>two</li></ul>'));
check('generated_nested_list_untouched', "- parent\n  - child", $converter->convert('<ul><li>parent<ul><li>child</li></ul></li></ul>'));
check('generated_ordered_nested_untouched', "1. one\n  1. a", $converter->convert('<ol><li>one<ol><li>a</li></ol></li></ol>'));
check('generated_blockquote_untouched', "> q", $converter->convert('<blockquote><p>q</p></blockquote>'));
check('generated_list_in_quote_untouched', '> - in quote', $converter->convert('<blockquote><ul><li>in quote</li></ul></blockquote>'));
check('generated_hr_untouched', "a\n\n---\n\nb", $converter->convert('<p>a</p><hr><p>b</p>'));

// Inside a fence those characters are the content, not structure.
check(
    'fence_content_not_escaped',
    "```\n# x\n- y\n1. z\n```",
    $converter->convert("<pre><code># x\n- y\n1. z</code></pre>")
);

// A marker must never reach the output.
$markerLeak = false;

foreach ([
    '<h2>H</h2><p>t</p>',
    '<ul><li><ul><li>x</li></ul></li></ul>',
    '<blockquote><blockquote><p>x</p></blockquote></blockquote>',
    '<p># x</p>',
    '<p>a<br># b</p>',
] as $sample) {
    $out = $converter->convert($sample);

    foreach (["\u{E000}", "\u{E001}", "\0"] as $sentinel) {
        if (str_contains($out, $sentinel)) {
            $markerLeak = true;
        }
    }
}

check('no_internal_marker_leaks', false, $markerLeak);

// --- a URL that is its own label ----------------------------------------------

check(
    'autolink_survives_underscores',
    '<https://ex.com/a_b_c>',
    $converter->convert('<p><a href="https://ex.com/a_b_c">https://ex.com/a_b_c</a></p>')
);

// --- protocol-relative URLs ----------------------------------------------------
//
// safeUrl() dropped anything starting with "//", which would silently delete a
// character from a post. The s9e emoji plugin renders
// <img class="emoji" src="//cdn...">, so that shape has to survive.

check(
    'protocol_relative_image_kept',
    '![x](https://cdn.example.com/a.png)',
    $converter->convert('<p><img src="//cdn.example.com/a.png" alt="x"></p>')
);
// The href is normalised to https, but the label is display text and is left
// alone, so this stays a labelled link rather than collapsing to an autolink.
// What matters is that the target survives and is absolute.
checkContains(
    'protocol_relative_link_kept',
    'https://cdn.example.com/a)',
    $converter->convert('<p><a href="//cdn.example.com/a">//cdn.example.com/a</a></p>')
);

// --- in-site links resolve to the community ------------------------------------
//
// Flarum renders a post's own links root-relative. A provider quoting one is
// quoting the community, and "/d/5-my-thread" has no domain to attribute it to
// and cannot be fetched. Resolved against the forum base so every citation in
// the file carries the community URL.

$based = (new HtmlToMarkdown('https://community.itqan.dev'))->withBaseUrl('https://community.itqan.dev');

check('relative_link_becomes_absolute', '[this thread](https://community.itqan.dev/d/5-my-thread)', $based->convert('<p><a href="/d/5-my-thread">this thread</a></p>'));
check('relative_image_becomes_absolute', '![p](https://community.itqan.dev/assets/x.png)', $based->convert('<p><img src="/assets/x.png" alt="p"></p>'));
check('relative_user_link_becomes_absolute', '[@amina](https://community.itqan.dev/u/amina)', $based->convert('<p><a href="/u/amina">@amina</a></p>'));
check('external_link_untouched', '[x](https://other.com/x)', $based->convert('<p><a href="https://other.com/x">x</a></p>'));
check('protocol_relative_still_normalised', '![p](https://cdn.x/a.png)', $based->convert('<p><img src="//cdn.x/a.png" alt="p"></p>'));

// A forum served from a subdirectory keeps its prefix.
$sub = (new HtmlToMarkdown('https://example.test/forum/'))->withBaseUrl('https://example.test/forum/');
check('subdirectory_preserved', '[t](https://example.test/forum/d/5-t)', $sub->convert('<p><a href="/d/5-t">t</a></p>'));

// No base URL means no rewriting, so the converter still works standalone.
check(
    'no_base_url_leaves_relative',
    '[this thread](/d/5-my-thread)',
    (new HtmlToMarkdown)->convert('<p><a href="/d/5-my-thread">this thread</a></p>')
);

// withBaseUrl must not mutate the shared instance.
$shared = new HtmlToMarkdown;
$shared->withBaseUrl('https://community.itqan.dev');
check('withBaseUrl_does_not_mutate', '[t](/d/5-t)', $shared->convert('<p><a href="/d/5-t">t</a></p>'));

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";

exit($failures === 0 ? 0 : 1);

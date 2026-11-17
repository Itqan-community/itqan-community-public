<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Unit;

use Flarum\Post\Post;
use Itqan\CommunityMcp\Formatter\PostMarkdownFormatter;
use Itqan\CommunityMcp\Tests\Support\TestCase;

/**
 * Post content is stored as s9e/TextFormatter XML in Flarum. These tests pin
 * the Markdown conversion for both plain (`<t>`) and rich (`<r>`) posts, the
 * hidden/truncated post behaviour, and the query-centred snippet helper.
 */
final class PostMarkdownFormatterTest extends TestCase
{
    public function testXmlToMarkdownConvertsPlainText(): void
    {
        $this->assertSame('مرحبا بالعالم', PostMarkdownFormatter::xmlToMarkdown('<t>مرحبا بالعالم</t>'));
        $this->assertSame('a & b', PostMarkdownFormatter::xmlToMarkdown('<t>a &amp; b</t>'));
    }

    public function testXmlToMarkdownConvertsRichTextWithS9eMarkdownTags(): void
    {
        $xml = '<r><B><s>**</s>bold<e>**</e></B> and '
            .'<I><s>_</s>italic<e>_</e></I> — '
            .'<CODE><s>`</s>code<e>`</e></CODE></r>';

        $markdown = PostMarkdownFormatter::xmlToMarkdown($xml);

        $this->assertStringContainsString('**bold**', $markdown);
        $this->assertStringContainsString('_italic_', $markdown);
        $this->assertStringContainsString('`code`', $markdown);
        $this->assertStringContainsString('—', $markdown);
    }

    public function testXmlToMarkdownConvertsRichTextLinks(): void
    {
        $xml = '<r>see <URL url="https://example.com/x"><s>[x](</s>link<e>)</e></URL> now</r>';

        $this->assertSame('see [x](link) now', PostMarkdownFormatter::xmlToMarkdown($xml));
    }

    public function testXmlToMarkdownHandlesEmptyStrings(): void
    {
        $this->assertSame('', PostMarkdownFormatter::xmlToMarkdown(''));
        $this->assertSame('', PostMarkdownFormatter::xmlToMarkdown('   '));
    }

    public function testXmlToMarkdownPassesThroughPlainMarkdown(): void
    {
        $this->assertSame('**already** markdown', PostMarkdownFormatter::xmlToMarkdown('  **already** markdown  '));
    }

    public function testHtmlToMarkdownConvertsHeadings(): void
    {
        $this->assertSame('## Title', PostMarkdownFormatter::htmlToMarkdown('<h2>Title</h2>'));
        $this->assertSame('### Sub heading', PostMarkdownFormatter::htmlToMarkdown('<h3>Sub heading</h3>'));
    }

    public function testHtmlToMarkdownConvertsEmphasisAndInlineCode(): void
    {
        $markdown = PostMarkdownFormatter::htmlToMarkdown(
            '<p><strong>bold</strong> <b>also bold</b> <em>it</em> <i>also it</i> <code>a&lt;b</code></p>'
        );

        $this->assertStringContainsString('**bold**', $markdown);
        $this->assertStringContainsString('**also bold**', $markdown);
        $this->assertStringContainsString('*it*', $markdown);
        $this->assertStringContainsString('*also it*', $markdown);
        $this->assertStringContainsString('`a<b`', $markdown);
    }

    public function testHtmlToMarkdownConvertsFencedCodeBlockWithLanguage(): void
    {
        $markdown = PostMarkdownFormatter::htmlToMarkdown(
            '<pre><code class="language-php">&lt;?php echo 1;</code></pre>'
        );

        $this->assertSame("```php\n<?php echo 1;\n```", $markdown);
    }

    public function testHtmlToMarkdownConvertsFencedCodeBlockWithoutLanguage(): void
    {
        $markdown = PostMarkdownFormatter::htmlToMarkdown('<pre>plain   block</pre>');

        $this->assertSame("```\nplain   block\n```", $markdown);
    }

    public function testHtmlToMarkdownConvertsLinksAndDecodesEntities(): void
    {
        $markdown = PostMarkdownFormatter::htmlToMarkdown(
            '<p><a href="https://x.test?a=1&amp;b=2">link</a> &amp; "quoted" &lt;tag&gt;</p>'
        );

        $this->assertStringContainsString('[link](https://x.test?a=1&b=2)', $markdown);
        $this->assertStringContainsString('& "quoted" <tag>', $markdown);
        $this->assertStringContainsString('السلام', PostMarkdownFormatter::htmlToMarkdown('&#x627;&#x644;&#x633;&#x644;&#x627;&#x645;'));
    }

    public function testHtmlToMarkdownConvertsBlockquotes(): void
    {
        $markdown = PostMarkdownFormatter::htmlToMarkdown(
            '<blockquote><p>one</p><p>two</p></blockquote>'
        );

        $this->assertSame("> one\n>\n> two", $markdown);
    }

    public function testHtmlToMarkdownConvertsUnorderedAndOrderedLists(): void
    {
        $this->assertSame(
            "- one\n- two",
            PostMarkdownFormatter::htmlToMarkdown('<ul><li>one</li><li>two</li></ul>')
        );
        $this->assertSame(
            "- first\n- second",
            PostMarkdownFormatter::htmlToMarkdown('<ol><li>first</li><li>second</li></ol>')
        );
    }

    public function testHtmlToMarkdownConvertsLineBreaks(): void
    {
        $this->assertSame(
            "line1\nline2\nline3",
            PostMarkdownFormatter::htmlToMarkdown('line1<br>line2<br />line3')
        );
    }

    public function testHtmlToMarkdownHandlesEmptyHtml(): void
    {
        $this->assertSame('', PostMarkdownFormatter::htmlToMarkdown(''));
        $this->assertSame('', PostMarkdownFormatter::htmlToMarkdown('   '));
    }

    public function testToMarkdownReturnsHiddenMessageForRemovedPost(): void
    {
        $post = $this->post('<t>this should never be shown</t>', '2024-01-01 00:00:00');

        $this->assertSame(PostMarkdownFormatter::HIDDEN_MESSAGE, PostMarkdownFormatter::toMarkdown($post));
        $this->assertSame('_[تم حذف هذا التعليق / Post hidden]_', PostMarkdownFormatter::toMarkdown($post));
    }

    public function testToMarkdownRendersVisiblePost(): void
    {
        $post = $this->post('<t>visible <B><s>**</s>content<e>**</e></B></t>');

        $this->assertSame('visible **content**', PostMarkdownFormatter::toMarkdown($post));
    }

    public function testToMarkdownTruncatesLongPosts(): void
    {
        $post = $this->post('<t>'.str_repeat('abc ', 30).'</t>');
        $markdown = PostMarkdownFormatter::toMarkdown($post, 20);

        $this->assertStringEndsWith(PostMarkdownFormatter::TRUNCATED_SUFFIX, $markdown);

        $prefix = substr($markdown, 0, -strlen(PostMarkdownFormatter::TRUNCATED_SUFFIX));
        $this->assertLessThanOrEqual(20, mb_strlen(rtrim($prefix), 'UTF-8'));
        $this->assertStringContainsString('...[تم اختصار المحتوى لطوله]...', $markdown);
    }

    public function testToMarkdownDoesNotTruncateShortPosts(): void
    {
        $post = $this->post('<t>short</t>');

        $this->assertSame('short', PostMarkdownFormatter::toMarkdown($post, 100));
        $this->assertSame('short', PostMarkdownFormatter::toMarkdown($post, 0));
    }

    public function testCreateSnippetReturnsTextWhenItFits(): void
    {
        $this->assertSame('نص قصير', PostMarkdownFormatter::createSnippet('نص قصير', 'قصير', 100));
        $this->assertSame('', PostMarkdownFormatter::createSnippet('   ', 'x', 10));
    }

    public function testCreateSnippetCentresOnQueryKeyword(): void
    {
        $text = str_repeat('كلمة ', 30).'الوقف والابتداء'.str_repeat(' كلمة', 30);
        $snippet = PostMarkdownFormatter::createSnippet($text, 'الوقف', 60);

        $this->assertStringContainsString('الوقف والابتداء', $snippet);
        $this->assertStringStartsWith('...', $snippet);
        $this->assertStringEndsWith('...', $snippet);
    }

    public function testCreateSnippetMatchesNormalizedArabic(): void
    {
        // The body uses a diacritic while the query does not.
        $text = str_repeat('كلمة ', 30).'الوقْف والابتداء'.str_repeat(' كلمة', 30);
        $snippet = PostMarkdownFormatter::createSnippet($text, 'الوقف', 80);

        $this->assertStringContainsString('الوق', $snippet);
        $this->assertStringContainsString('الابتداء', $snippet);
    }

    public function testCreateSnippetClampsLength(): void
    {
        $text = str_repeat('كلمة ', 50);

        $snippet = PostMarkdownFormatter::createSnippet($text, null, 10);
        $this->assertLessThanOrEqual(13, mb_strlen($snippet, 'UTF-8'));

        $snippet = PostMarkdownFormatter::createSnippet($text, 'غير موجود', 20);
        $this->assertLessThanOrEqual(23, mb_strlen($snippet, 'UTF-8'));

        // A zero/negative budget is treated as the smallest useful window.
        $tiny = PostMarkdownFormatter::createSnippet($text, null, 0);
        $this->assertLessThanOrEqual(4, mb_strlen($tiny, 'UTF-8'));
    }

    public function testCreateSnippetReplacesLineBreaks(): void
    {
        $snippet = PostMarkdownFormatter::createSnippet("# Title\n\nBody paragraph", null, 100);

        $this->assertStringContainsString('|', $snippet);
        $this->assertStringNotContainsString("\n", $snippet);
    }

    private function post(string $content, ?string $hiddenAt = null): Post
    {
        $post = new Post();
        $post->setRawAttributes([
            'content' => $content,
            'hidden_at' => $hiddenAt,
        ], true);

        return $post;
    }
}

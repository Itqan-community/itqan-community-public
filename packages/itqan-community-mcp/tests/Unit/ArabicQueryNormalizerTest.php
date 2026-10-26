<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Tests\Unit;

use Itqan\CommunityMcp\Search\ArabicQueryNormalizer;
use Itqan\CommunityMcp\Tests\Support\TestCase;

/**
 * Covers the Arabic spelling variants that the database fallback search relies
 * on: diacritic stripping, letter normalization, keyword extraction and query
 * variant generation.
 */
final class ArabicQueryNormalizerTest extends TestCase
{
    public function testStripTashkeelRemovesHarakatAndShadda(): void
    {
        $this->assertSame('محمد', ArabicQueryNormalizer::stripTashkeel('مُحَمَّدٌ'));
        $this->assertSame('كتاب', ArabicQueryNormalizer::stripTashkeel('كِتَابٌ'));
        $this->assertSame('مدرسة', ArabicQueryNormalizer::stripTashkeel('مَدْرَسَة'));
    }

    public function testStripTashkeelRemovesDaggerAlif(): void
    {
        // U+0670 ARABIC LETTER SUPERSCRIPT ALEF.
        $this->assertSame('هذا', ArabicQueryNormalizer::stripTashkeel('هٰذَا'));
        $this->assertStringNotContainsString("\u{0670}", ArabicQueryNormalizer::stripTashkeel('هٰذا'));
    }

    public function testStripTashkeelRemovesTatweelKashida(): void
    {
        // U+0640 ARABIC TATWEEL, used for justification inside words.
        $this->assertSame('كتاب', ArabicQueryNormalizer::stripTashkeel('كـــتاب'));
        $this->assertStringNotContainsString("\u{0640}", ArabicQueryNormalizer::stripTashkeel('كـــتاب'));
    }

    public function testStripTashkeelLeavesPlainTextUntouched(): void
    {
        $this->assertSame('Quranic data ASR', ArabicQueryNormalizer::stripTashkeel('Quranic data ASR'));
        $this->assertSame('', ArabicQueryNormalizer::stripTashkeel(''));
    }

    public function testNormalizeLettersUnifiesHamzaForms(): void
    {
        $this->assertSame('احمد', ArabicQueryNormalizer::normalizeLetters('أحمد'));
        $this->assertSame('ابراهيم', ArabicQueryNormalizer::normalizeLetters('إبراهيم'));
        $this->assertSame('امن', ArabicQueryNormalizer::normalizeLetters('آمن'));
        $this->assertSame('القران', ArabicQueryNormalizer::normalizeLetters('ٱلقرآن'));
    }

    public function testNormalizeLettersUnifiesWawAndYaaForms(): void
    {
        $this->assertSame('مومن', ArabicQueryNormalizer::normalizeLetters('مؤمن'));
        $this->assertSame('مسيول', ArabicQueryNormalizer::normalizeLetters('مسئول'));
        $this->assertSame('مصطفي', ArabicQueryNormalizer::normalizeLetters('مصطفى'));
    }

    public function testNormalizeLettersPreservesTaaMarbuta(): void
    {
        $this->assertSame('مدرسة', ArabicQueryNormalizer::normalizeLetters('مدرسة'));
        $this->assertStringContainsString('ة', ArabicQueryNormalizer::normalizeLetters('اللغة العربية'));
    }

    public function testNormalizeLettersAlsoStripsTashkeel(): void
    {
        $this->assertSame('ااااويية', ArabicQueryNormalizer::normalizeLetters('أإآٱؤئىة'));
        $this->assertSame('محمد', ArabicQueryNormalizer::normalizeLetters('مُحَمَّد'));
    }

    public function testExtractKeywordsRemovesArabicAndEnglishStopWords(): void
    {
        $keywords = ArabicQueryNormalizer::extractKeywords('the quick and في من على علي');

        $this->assertSame(['quick', 'علي'], $keywords);
    }

    public function testExtractKeywordsKeepsValidArabicNames(): void
    {
        // `علي` must not be folded into the stop word `على`.
        $this->assertSame(['علي'], ArabicQueryNormalizer::extractKeywords('علي'));
        $this->assertSame(['علي'], ArabicQueryNormalizer::extractKeywords('عَلِيٌّ'));
    }

    public function testExtractKeywordsSplitsPunctuation(): void
    {
        $keywords = ArabicQueryNormalizer::extractKeywords('ASR, tajweed; quranic-data (open) standards!');

        $this->assertSame(
            ['ASR', 'tajweed', 'quranic', 'data', 'open', 'standards'],
            $keywords
        );
    }

    public function testExtractKeywordsRemovesShortWordsAndDeduplicates(): void
    {
        $this->assertSame([], ArabicQueryNormalizer::extractKeywords('a ب'));
        $this->assertSame(['كتاب'], ArabicQueryNormalizer::extractKeywords('كتاب كِتَاب'));
        $this->assertSame(['القرآن'], ArabicQueryNormalizer::extractKeywords('القرآن القرآن'));
    }

    public function testExtractKeywordsHandlesEmptyAndPunctuationOnlyQueries(): void
    {
        $this->assertSame([], ArabicQueryNormalizer::extractKeywords(''));
        $this->assertSame([], ArabicQueryNormalizer::extractKeywords('   '));
        $this->assertSame([], ArabicQueryNormalizer::extractKeywords('؟!...،'));
    }

    public function testGenerateVariantsIncludesStrippedAndNormalizedForms(): void
    {
        $variants = ArabicQueryNormalizer::generateVariants('البرمجة الصوتية للقرآن');

        $this->assertSame('البرمجة الصوتية للقرآن', $variants[0]);
        $this->assertSame('البرمجة الصوتية للقران', $variants[1]);
        $this->assertContains('البرمجة الصوتية للقرآن', $variants);
        $this->assertContains('البرمجة الصوتية للقران', $variants);
    }

    public function testGenerateVariantsStripsTashkeel(): void
    {
        $this->assertSame(['كتاب'], ArabicQueryNormalizer::generateVariants('كِتَابٌ'));
    }

    public function testGenerateVariantsAddsTaaMarbutaAsHaa(): void
    {
        $variants = ArabicQueryNormalizer::generateVariants('مدرسة');

        $this->assertContains('مدرسة', $variants);
        $this->assertContains('مدرسه', $variants);
    }

    public function testGenerateVariantsStripsDefiniteArticleOnLongWords(): void
    {
        $variants = ArabicQueryNormalizer::generateVariants('البرمجة');

        $this->assertContains('البرمجة', $variants);
        $this->assertContains('برمجة', $variants);
    }

    public function testGenerateVariantsKeepsShortWordsIntact(): void
    {
        // `ال` is only two characters; stripping it would leave nothing useful.
        $variants = ArabicQueryNormalizer::generateVariants('ال');

        $this->assertSame(['ال'], $variants);
    }

    public function testGenerateVariantsDeduplicates(): void
    {
        $variants = ArabicQueryNormalizer::generateVariants('تجويد تجويد');

        $this->assertSame(['تجويد تجويد', 'تجويد'], $variants);
    }

    public function testGenerateVariantsHandlesEmptyQuery(): void
    {
        $this->assertSame([], ArabicQueryNormalizer::generateVariants(''));
        $this->assertSame([], ArabicQueryNormalizer::generateVariants('   '));
    }
}

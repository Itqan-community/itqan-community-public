<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Search;

/**
 * Arabic-aware keyword extraction and query normalization.
 *
 * The community search endpoint mostly receives short Arabic queries that are
 * often written with diacritics or with different hamza/yaa/taa-marbuta
 * spellings. This helper produces the small set of variants that the database
 * fallback can try without turning a query into a full-text scan.
 */
final class ArabicQueryNormalizer
{
    /**
     * Arabic diacritics, Quranic annotation marks, superscript alif and tatweel.
     */
    private const TASHKEEL_PATTERN = '/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u';

    /**
     * Common Arabic function words that carry no search value.
     *
     * Stored in their raw spelling and normalized before comparison, so both
     * `إلى` and `الى` are recognised.
     */
    private const ARABIC_STOP_WORDS = [
        'في', 'من', 'على', 'عن', 'ما', 'هو', 'هي', 'هم', 'كيف', 'هل',
        'الذي', 'التي', 'الذين', 'هذا', 'هذه', 'ذلك', 'تلك', 'إلى', 'الى',
        'مع', 'أو', 'او', 'ثم', 'بل', 'لا', 'لم', 'لن', 'قد', 'كل', 'بعض',
        'أي', 'اي', 'بين', 'عند', 'حول', 'خلال', 'بعد', 'قبل', 'فوق',
        'تحت', 'امام', 'أمام', 'وراء', 'ان', 'أن', 'إن', 'كان', 'كانت',
    ];

    /**
     * Common English function words.
     */
    private const ENGLISH_STOP_WORDS = [
        'the', 'is', 'in', 'at', 'of', 'on', 'and', 'or', 'to', 'for',
        'with', 'how', 'what', 'why', 'can', 'i',
    ];

    /**
     * Remove Arabic diacritics, Quranic marks, superscript alif and tatweel.
     */
    public static function stripTashkeel(string $text): string
    {
        $stripped = preg_replace(self::TASHKEEL_PATTERN, '', $text);

        return $stripped ?? $text;
    }

    /**
     * Normalize letter variants so spelling differences do not hide matches.
     */
    public static function normalizeLetters(string $text): string
    {
        $text = self::stripTashkeel($text);

        return str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ؤ', 'ئ', 'ى'],
            ['ا', 'ا', 'ا', 'ا', 'و', 'ي', 'ي'],
            $text
        );
    }

    /**
     * Extract meaningful, de-duplicated keywords from a free-text query.
     *
     * @return list<string>
     */
    public static function extractKeywords(string $query): array
    {
        $query = self::stripTashkeel($query);

        // Split on whitespace and on Arabic/Latin punctuation and symbols.
        $parts = preg_split(
            '/[\s\p{P}\p{S}]+/u',
            $query,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if ($parts === false) {
            return [];
        }

        $arabicStopWords = array_fill_keys(
            array_map(static fn (string $word): string => mb_strtolower($word, 'UTF-8'), self::ARABIC_STOP_WORDS),
            true
        );
        $englishStopWords = array_fill_keys(self::ENGLISH_STOP_WORDS, true);

        $keywords = [];
        $seen = [];

        foreach ($parts as $part) {
            $word = trim($part);

            if ($word === '') {
                continue;
            }

            // Compare the literal (diacritic-free) spelling: normalizing letters
            // here would turn names such as علي into the stop word على.
            $lower = mb_strtolower($word, 'UTF-8');

            if (isset($arabicStopWords[$lower]) || isset($englishStopWords[$lower])) {
                continue;
            }

            if (mb_strlen($word, 'UTF-8') < 2) {
                continue;
            }

            // De-duplicate spelling variants that normalize to the same letters.
            $key = mb_strtolower(self::normalizeLetters($word), 'UTF-8');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $keywords[] = $word;
        }

        return $keywords;
    }

    /**
     * Build the query variants the Arabic fallback search may try, in order.
     *
     * @return list<string>
     */
    public static function generateVariants(string $query): array
    {
        $variants = [];

        $add = static function (?string $value) use (&$variants): void {
            if ($value === null) {
                return;
            }

            $value = trim($value);

            if ($value === '') {
                return;
            }

            $variants[$value] = true;
        };

        $stripped = trim(self::stripTashkeel($query));
        $normalized = self::normalizeLetters($stripped);

        $add($stripped);
        $add($normalized);

        // Taa-marbuta is frequently written as haa in informal Arabic.
        if ($stripped !== '' && mb_strpos($stripped, 'ة', 0, 'UTF-8') !== false) {
            $add(str_replace('ة', 'ه', $stripped));
            $add(str_replace('ة', 'ه', $normalized));
        }

        foreach (self::extractKeywords($query) as $keyword) {
            $add($keyword);
            $add(self::normalizeLetters($keyword));

            // Try the keyword without its definite article (ال) when long enough
            // for the remainder to stay meaningful.
            if (
                mb_strlen($keyword, 'UTF-8') > 4
                && mb_substr($keyword, 0, 2, 'UTF-8') === 'ال'
            ) {
                $add(mb_substr($keyword, 2, null, 'UTF-8'));
            }
        }

        return array_keys($variants);
    }
}

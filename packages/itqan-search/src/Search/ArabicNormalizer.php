<?php

namespace Itqan\Search\Search;

/**
 * Shared Arabic & text normalization helpers for both PHP queries and MySQL expressions.
 */
final class ArabicNormalizer
{
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        // Strip Arabic diacritics (tashkeel) and tatweel.
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return strtr(trim($value), [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ٱ' => 'ا',
            'ة' => 'ه',
            'ى' => 'ي',
        ]);
    }

    public static function sqlNormalize(string $column): string
    {
        $expr = "COALESCE({$column}, '')";

        // Strip tatweel and common Arabic harakat, then unify alef/teh-marbuta/alef-maksura.
        foreach (['ـ', 'ً', 'ٌ', 'ٍ', 'َ', 'ُ', 'ِ', 'ّ', 'ْ'] as $strip) {
            $expr = "REPLACE({$expr}, '{$strip}', '')";
        }

        foreach ([
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ٱ' => 'ا',
            'ة' => 'ه',
            'ى' => 'ي',
        ] as $from => $to) {
            $expr = "REPLACE({$expr}, '{$from}', '{$to}')";
        }

        return "LOWER({$expr})";
    }

    public static function sqlSpaceless(string $normalizedExpr): string
    {
        return "REPLACE({$normalizedExpr}, ' ', '')";
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @return string[]
     */
    public static function splitWords(string $normalized): array
    {
        return preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}

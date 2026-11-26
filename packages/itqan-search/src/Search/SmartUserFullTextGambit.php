<?php

namespace Itqan\Search\Search;

use Flarum\Search\GambitInterface;
use Flarum\Search\SearchState;

/**
 * Smart user full-text gambit for /api/users?filter[q]=...
 *
 * Replaces flarum/nicknames' prefix-only LIKE ('q%') with:
 * - substring matching on username and nickname ('%q%')
 * - spaceless matching ('@محمداحمد' -> 'محمد احمد')
 * - multi-word matching when quoted with spaces ('@"حسن محمد"')
 * - Arabic letter normalization (أ/إ/آ/ٱ -> ا, ة -> ه, ى -> ي, tashkeel stripped)
 * - ranked ordering: prefix first, then word-start, then substring/spaceless.
 */
class SmartUserFullTextGambit implements GambitInterface
{
    public function apply(SearchState $search, $searchValue): bool
    {
        $normalized = ArabicNormalizer::normalize((string) $searchValue);

        if ($normalized === '') {
            return true;
        }

        $spaceless = str_replace(' ', '', $normalized);
        $words = ArabicNormalizer::splitWords($normalized);

        $normUser = ArabicNormalizer::sqlNormalize('username');
        $normNick = ArabicNormalizer::sqlNormalize('nickname');
        $spacelessUser = ArabicNormalizer::sqlSpaceless($normUser);
        $spacelessNick = ArabicNormalizer::sqlSpaceless($normNick);

        $likeFull = '%'.ArabicNormalizer::escapeLike($normalized).'%';
        $likeSpaceless = '%'.ArabicNormalizer::escapeLike($spaceless).'%';
        $likePrefix = ArabicNormalizer::escapeLike($normalized).'%';
        $likeWordSpace = '% '.ArabicNormalizer::escapeLike($normalized).'%';
        $likeWordUnder = '%\\_'.ArabicNormalizer::escapeLike($normalized).'%';

        $query = $search->getQuery();

        $query->where(function ($q) use (
            $normUser,
            $normNick,
            $spacelessUser,
            $spacelessNick,
            $likeFull,
            $likeSpaceless,
            $spaceless,
            $words
        ) {
            $q->whereRaw("{$normUser} LIKE ? ESCAPE '\\\\'", [$likeFull])
                ->orWhereRaw("{$normNick} LIKE ? ESCAPE '\\\\'", [$likeFull]);

            if ($spaceless !== '') {
                $q->orWhereRaw("{$spacelessUser} LIKE ? ESCAPE '\\\\'", [$likeSpaceless])
                    ->orWhereRaw("{$spacelessNick} LIKE ? ESCAPE '\\\\'", [$likeSpaceless]);
            }

            if (count($words) > 1) {
                $q->orWhere(function ($allWords) use ($normUser, $normNick, $words) {
                    foreach ($words as $word) {
                        $likeWord = '%'.ArabicNormalizer::escapeLike($word).'%';
                        $allWords->where(function ($w) use ($normUser, $normNick, $likeWord) {
                            $w->whereRaw("{$normUser} LIKE ? ESCAPE '\\\\'", [$likeWord])
                                ->orWhereRaw("{$normNick} LIKE ? ESCAPE '\\\\'", [$likeWord]);
                        });
                    }
                });
            }
        });

        $query->orderByRaw(
            "CASE
                WHEN {$normNick} LIKE ? ESCAPE '\\\\' OR {$normUser} LIKE ? ESCAPE '\\\\' THEN 0
                WHEN {$normNick} LIKE ? ESCAPE '\\\\' OR {$normUser} LIKE ? ESCAPE '\\\\' OR {$normUser} LIKE ? ESCAPE '\\\\' THEN 1
                ELSE 2
            END ASC, username ASC",
            [$likePrefix, $likePrefix, $likeWordSpace, $likeWordSpace, $likeWordUnder]
        );

        return true;
    }
}

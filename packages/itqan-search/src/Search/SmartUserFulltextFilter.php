<?php

namespace Itqan\Search\Search;

use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;
use Illuminate\Database\Eloquent\Builder;

/**
 * Smart user full-text filter for /api/users?filter[q]=... (Flarum 2.x).
 *
 * @extends AbstractFulltextFilter<DatabaseSearchState>
 */
class SmartUserFulltextFilter extends AbstractFulltextFilter
{
    public function search(SearchState $state, string $value): void
    {
        $normalized = ArabicNormalizer::normalize($value);

        if ($normalized === '') {
            return;
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

        $state->getQuery()->where(function ($q) use (
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

        $state->setDefaultSort(function (Builder $query) use (
            $normNick,
            $normUser,
            $likePrefix,
            $likeWordSpace,
            $likeWordUnder
        ) {
            $query->orderByRaw(
                "CASE
                    WHEN {$normNick} LIKE ? ESCAPE '\\\\' OR {$normUser} LIKE ? ESCAPE '\\\\' THEN 0
                    WHEN {$normNick} LIKE ? ESCAPE '\\\\' OR {$normUser} LIKE ? ESCAPE '\\\\' OR {$normUser} LIKE ? ESCAPE '\\\\' THEN 1
                    ELSE 2
                END ASC, username ASC",
                [$likePrefix, $likePrefix, $likeWordSpace, $likeWordSpace, $likeWordUnder]
            );
        });
    }
}

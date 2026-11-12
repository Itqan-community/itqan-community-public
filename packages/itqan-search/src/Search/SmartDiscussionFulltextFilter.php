<?php

namespace Itqan\Search\Search;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Smart discussion & post full-text filter for /api/discussions?filter[q]=... (Flarum 2.x).
 *
 * @extends AbstractFulltextFilter<DatabaseSearchState>
 */
class SmartDiscussionFulltextFilter extends AbstractFulltextFilter
{
    public function search(SearchState $state, string $value): void
    {
        $normalized = ArabicNormalizer::normalize($value);

        if ($normalized === '') {
            return;
        }

        $spaceless = str_replace(' ', '', $normalized);
        $words = ArabicNormalizer::splitWords($normalized);

        $normTitle = ArabicNormalizer::sqlNormalize('discussions.title');
        $spacelessTitle = ArabicNormalizer::sqlSpaceless($normTitle);
        $normContent = ArabicNormalizer::sqlNormalize('posts.content');

        $likePrefix = ArabicNormalizer::escapeLike($normalized).'%';
        $likeFull = '%'.ArabicNormalizer::escapeLike($normalized).'%';
        $likeSpaceless = '%'.ArabicNormalizer::escapeLike($spaceless).'%';

        $discussionSubquery = Discussion::query()
            ->selectRaw('discussions.id as discussion_id')
            ->selectRaw(
                "CASE WHEN {$normTitle} LIKE ? ESCAPE '\\\\' THEN 100 WHEN {$normTitle} LIKE ? ESCAPE '\\\\' THEN 80 ELSE 60 END as score",
                [$likePrefix, $likeFull]
            )
            ->selectRaw('discussions.first_post_id as most_relevant_post_id')
            ->where(function ($q) use ($normTitle, $spacelessTitle, $likeFull, $likeSpaceless, $spaceless, $words) {
                $q->whereRaw("{$normTitle} LIKE ? ESCAPE '\\\\'", [$likeFull]);

                if ($spaceless !== '') {
                    $q->orWhereRaw("{$spacelessTitle} LIKE ? ESCAPE '\\\\'", [$likeSpaceless]);
                }

                if (count($words) > 1) {
                    $q->orWhere(function ($allWords) use ($normTitle, $words) {
                        foreach ($words as $word) {
                            $likeWord = '%'.ArabicNormalizer::escapeLike($word).'%';
                            $allWords->whereRaw("{$normTitle} LIKE ? ESCAPE '\\\\'", [$likeWord]);
                        }
                    });
                }
            });

        $postSubquery = Post::whereVisibleTo($state->getActor())
            ->selectRaw('posts.discussion_id as discussion_id')
            ->selectRaw('10 as score')
            ->selectRaw('MIN(posts.id) as most_relevant_post_id')
            ->where('posts.type', 'comment')
            ->where(function ($q) use ($normContent, $likeFull, $words) {
                $q->whereRaw("{$normContent} LIKE ? ESCAPE '\\\\'", [$likeFull]);

                if (count($words) > 1) {
                    $q->orWhere(function ($allWords) use ($normContent, $words) {
                        foreach ($words as $word) {
                            $likeWord = '%'.ArabicNormalizer::escapeLike($word).'%';
                            $allWords->whereRaw("{$normContent} LIKE ? ESCAPE '\\\\'", [$likeWord]);
                        }
                    });
                }
            })
            ->groupBy('posts.discussion_id');

        $unionSubquery = $postSubquery->unionAll($discussionSubquery);

        $query = $state->getQuery();
        $grammar = $query->getGrammar();

        $aggregatedSql = sprintf(
            '(SELECT discussion_id, MAX(score) as score, MIN(most_relevant_post_id) as most_relevant_post_id FROM (%s) %s GROUP BY discussion_id) %s',
            $unionSubquery->toSql(),
            $grammar->wrapTable('posts_ft_raw'),
            $grammar->wrapTable('posts_ft')
        );

        $query
            ->addSelect('posts_ft.most_relevant_post_id')
            ->join(
                new Expression($aggregatedSql),
                'posts_ft.discussion_id',
                '=',
                'discussions.id'
            )
            ->groupBy('discussions.id')
            ->addBinding($unionSubquery->getBindings(), 'join');

        $state->setDefaultSort(function (Builder $query) {
            $query->orderBy('posts_ft.score', 'desc')
                ->orderBy('discussions.last_posted_at', 'desc');
        });
    }
}

<?php

namespace Itqan\UserBadges\Search;

use Flarum\Search\Database\AbstractSearcher;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Itqan\UserBadges\UserBadge\UserBadge;

/**
 * Lets `GET /api/user-badges` accept query filters.
 *
 * Flarum 2 refuses `filter[…][]` on a plain database resource — its
 * `AbstractDatabaseResource::filters()` is final and only throws. A model
 * searcher is the supported path, so the badge page's
 * `filter[badge]=<id>` is applied here instead of in the resource.
 */
class UserBadgeSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        // Qualified, because the filters below reference `badge_user.*`.
        return UserBadge::query()->select('badge_user.*');
    }

    /**
     * The endpoint's `defaultSort()` does not reach the searcher, so the
     * "who earned it" list keeps its 1.8 order (newest first) here.
     */
    protected function applySort(DatabaseSearchState $state, ?array $sort = null, bool $sortIsDefault = false): void
    {
        if ($sortIsDefault) {
            $state->getQuery()->orderByDesc('badge_user.assigned_at');

            return;
        }

        parent::applySort($state, $sort, false);
    }
}

<?php

namespace Itqan\UserBadges\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[badge]=<id>` — the badge details page lists who holds a badge.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class BadgeFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'badge';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $badgeId = $this->asInt($value);

        /** @var DatabaseSearchState $state */
        $state->getQuery()->where('badge_user.badge_id', $negate ? '!=' : '=', $badgeId);
    }
}

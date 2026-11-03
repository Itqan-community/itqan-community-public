<?php

namespace Itqan\UserBadges\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[user]=<id>` — one member's badges, kept from the 1.8 API surface.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class UserFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'user';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $userId = $this->asInt($value);

        /** @var DatabaseSearchState $state */
        $state->getQuery()->where('badge_user.user_id', $negate ? '!=' : '=', $userId);
    }
}

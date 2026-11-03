<?php

namespace Itqan\UserBadges\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Itqan\UserBadges\Badge\Badge;
use Tobyz\JsonApiServer\Context as OriginalContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends AbstractDatabaseResource<Badge>
 */
class BadgeResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'badges';
    }

    public function model(): string
    {
        return Badge::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        // `earnedAmount` reads the count for each badge; one query instead of
        // twelve.
        $query->withCount('users');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(100, 100)
                ->defaultSort('order')
                ->defaultInclude(['category'])
                ->eagerLoad(['category']),
            Endpoint\Show::make()
                ->defaultInclude(['category']),
            Endpoint\Create::make()
                ->visible(fn (Context $context) => $context->getActor()->isAdmin()),
            Endpoint\Update::make()
                ->visible(fn (Context $context) => $context->getActor()->isAdmin()),
            Endpoint\Delete::make()
                ->visible(fn (Context $context) => $context->getActor()->isAdmin()),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(200),
            Schema\Str::make('icon')
                ->writable()
                ->nullable(),
            Schema\Integer::make('order')
                ->writable(),
            // The artwork: a URL to an uploaded file, as on production.
            Schema\Str::make('image')
                ->writable()
                ->nullable(),
            Schema\Str::make('description')
                ->writable()
                ->nullable(),
            Schema\Boolean::make('isVisible')
                ->property('is_visible')
                ->writable(),
            Schema\Str::make('backgroundColor')
                ->property('background_color')
                ->writable()
                ->nullable(),
            Schema\Str::make('iconColor')
                ->property('icon_color')
                ->writable()
                ->nullable(),
            Schema\Str::make('labelColor')
                ->property('label_color')
                ->writable()
                ->nullable(),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\Integer::make('earnedAmount')
                ->get(fn (Badge $badge) => (int) ($badge->users_count ?? $badge->users()->count())),
            Schema\Relationship\ToOne::make('category')
                ->type('badge-categories')
                ->includable(),
            Schema\Relationship\ToMany::make('users')
                ->type('user-badges')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('order'),
            SortColumn::make('name'),
        ];
    }
}

<?php

namespace Itqan\UserBadges\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Itqan\UserBadges\BadgeCategory\BadgeCategory;

/**
 * @extends AbstractDatabaseResource<BadgeCategory>
 */
class BadgeCategoryResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'badge-categories';
    }

    public function model(): string
    {
        return BadgeCategory::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(100, 100)
                ->defaultSort('order')
                ->defaultInclude(['badges'])
                ->eagerLoad(['badges']),
            Endpoint\Show::make()
                ->defaultInclude(['badges']),
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
            Schema\Str::make('description')
                ->writable()
                ->nullable(),
            Schema\Integer::make('order')
                ->writable(),
            Schema\Boolean::make('isEnabled')
                ->property('is_enabled')
                ->writable(),
            Schema\Boolean::make('isTable')
                ->property('is_table')
                ->writable(),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\Relationship\ToMany::make('badges')
                ->type('badges')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('order'),
        ];
    }
}

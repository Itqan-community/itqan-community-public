<?php

namespace Itqan\UserBadges\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\Notification\NotificationSyncer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Itqan\UserBadges\Badge\Badge;
use Itqan\UserBadges\Notification\BadgeReceivedBlueprint;
use Itqan\UserBadges\UserBadge\UserBadge;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends AbstractDatabaseResource<UserBadge>
 */
class UserBadgeResource extends AbstractDatabaseResource
{
    public function __construct(protected NotificationSyncer $notifications)
    {
    }

    public function type(): string
    {
        return 'user-badges';
    }

    public function model(): string
    {
        return UserBadge::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        // The badge page asks for `filter[badge]=<id>`; the profile reads the
        // user's own badges through the users relationship instead.
        $filter = (array) ($context->request->getQueryParams()['filter'] ?? []);

        if (! empty($filter['badge'])) {
            $query->where('badge_id', (int) $filter['badge']);
        }

        if (! empty($filter['user'])) {
            $query->where('user_id', (int) $filter['user']);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(18, 50)
                ->defaultSort('-assignedAt')
                ->defaultInclude(['user', 'badge'])
                ->eagerLoad(['user', 'badge']),
            Endpoint\Show::make()
                ->defaultInclude(['user', 'badge']),
            Endpoint\Create::make()
                ->authenticated()
                ->visible(fn (Context $context) => $context->getActor()->can('badges.giveBadge'))
                ->action(function (Context $context) {
                    $actor = $context->getActor();
                    $data = $context->body();

                    $badgeId = (int) Arr::get($data, 'data.relationships.badge.data.id');
                    $userId = (int) Arr::get($data, 'data.relationships.user.data.id');

                    $badge = Badge::findOrFail($badgeId);
                    $user = \Flarum\User\User::findOrFail($userId);

                    $exists = UserBadge::query()
                        ->where('badge_id', $badge->id)
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($exists) {
                        throw new ValidationException([
                            'badge' => 'This user already has that badge.',
                        ]);
                    }

                    $userBadge = UserBadge::build($user->id, $badge->id, Arr::get($data, 'data.attributes.description'));
                    $userBadge->in_user_card = false;
                    $userBadge->is_primary = false;
                    $userBadge->save();

                    $userBadge->load(['badge', 'user']);

                    $this->notifications->sync(new BadgeReceivedBlueprint($userBadge), [$user]);

                    return $userBadge;
                }),
            Endpoint\Update::make()
                ->authenticated()
                ->action(function (Context $context) {
                    /** @var UserBadge $userBadge */
                    $userBadge = $context->model;
                    $actor = $context->getActor();
                    $attributes = Arr::get($context->body(), 'data.attributes', []);

                    // Only the badge's owner or someone allowed to give badges
                    // may change it.
                    if ((int) $userBadge->user_id !== (int) $actor->id) {
                        $actor->assertCan('badges.giveBadge');
                    }

                    if (Arr::get($attributes, 'isPrimary')) {
                        // Clear the older primary first: the 1.8 handler used
                        // `where('user', …)`, which matched nothing, so users
                        // could end up with two primary badges.
                        UserBadge::query()
                            ->where('user_id', $userBadge->user_id)
                            ->where('id', '!=', $userBadge->id)
                            ->update(['is_primary' => false]);

                        $userBadge->is_primary = true;
                    }

                    if ($actor->can('badges.giveBadge') && Arr::has($attributes, 'description')) {
                        $description = Arr::get($attributes, 'description');
                        $userBadge->description = $description !== '' ? $description : null;
                    }

                    $userBadge->save();
                    $userBadge->load(['badge', 'user']);

                    return $userBadge;
                }),
            Endpoint\Delete::make()
                ->authenticated()
                ->visible(fn (Context $context) => $context->getActor()->can('badges.removeBadge'))
                ->action(function (Context $context) {
                    /** @var UserBadge $userBadge */
                    $userBadge = $context->model;
                    $userBadge->delete();
                }),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('description')
                ->writable()
                ->nullable(),
            Schema\Boolean::make('isPrimary')
                ->property('is_primary')
                ->writable(),
            Schema\DateTime::make('assignedAt')
                ->property('assigned_at'),
            Schema\Boolean::make('inUserCard')
                ->property('in_user_card')
                ->writable(),
            Schema\Relationship\ToOne::make('badge')
                ->type('badges')
                ->includable(),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('assignedAt')
                ->column('assigned_at'),
        ];
    }
}

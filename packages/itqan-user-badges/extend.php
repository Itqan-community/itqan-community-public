<?php

use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Search\Database\DatabaseSearchDriver;
use Flarum\User\User;
use Itqan\UserBadges\Access\UserPolicy;
use Itqan\UserBadges\Api\Controller\OrderBadgeCategoriesController;
use Itqan\UserBadges\Api\Controller\OrderBadgesController;
use Itqan\UserBadges\Api\Resource\BadgeCategoryResource;
use Itqan\UserBadges\Api\Resource\BadgeResource;
use Itqan\UserBadges\Api\Resource\UserBadgeResource;
use Itqan\UserBadges\Controllers\BadgeOverviewController;
use Itqan\UserBadges\Notification\BadgeReceivedBlueprint;
use Itqan\UserBadges\Search\Filter\BadgeFilter;
use Itqan\UserBadges\Search\Filter\UserFilter;
use Itqan\UserBadges\Search\UserBadgeSearcher;
use Itqan\UserBadges\UserBadge\UserBadge;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/badges', 'badges', BadgeOverviewController::class)
        ->route('/badges/{id:\d+}', 'badges.item', BadgeOverviewController::class)
        // The profile tab is a client route; without a matching server route a
        // direct link (or a refresh) 404s.
        ->route('/u/{username}/badges', 'user.badges', BadgeOverviewController::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    // API resources (replace the 1.8 serializers + per-action controllers).
    new Extend\ApiResource(BadgeResource::class),
    new Extend\ApiResource(BadgeCategoryResource::class),
    new Extend\ApiResource(UserBadgeResource::class),

    // Query filters (`filter[badge]`, `filter[user]`) go through a model
    // searcher: Flarum 2 refuses them on a database resource.
    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(UserBadge::class, UserBadgeSearcher::class)
        ->addFilter(UserBadgeSearcher::class, BadgeFilter::class)
        ->addFilter(UserBadgeSearcher::class, UserFilter::class),

    // Non-CRUD endpoints keep their 1.8 URLs so the ported admin JS is unchanged.
    (new Extend\Routes('api'))
        ->post('/badges/order', 'badges.order', OrderBadgesController::class)
        ->post('/badge_categories/order', 'badge_categories.order', OrderBadgeCategoriesController::class),

    // The member's badges travel with the user, post and discussion payloads so
    // the byline and user card can render them without extra requests.
    (new Extend\Model(User::class))
        ->relationship('userBadges', fn (User $user) => $user->hasMany(UserBadge::class, 'user_id'))
        ->relationship('userPrimaryBadge', fn (User $user) => $user->hasOne(UserBadge::class, 'user_id')->where('is_primary', true)),

    (new Extend\ApiResource(Resource\UserResource::class))
        ->fields(fn () => [
            Schema\Relationship\ToMany::make('userBadges')
                ->type('user-badges')
                ->includable(),
            Schema\Relationship\ToOne::make('userPrimaryBadge')
                ->type('user-badges')
                ->includable(),
            // What the user card shows. Computed here because Flarum 2 does not
            // ship relationships with every post author, and a per-author
            // request on the discussion list would be far worse.
            Schema\Integer::make('badgeCount')
                ->get(fn (User $user) => $user->relationLoaded('userBadges')
                    ? $user->userBadges->count()
                    : UserBadge::query()->where('user_id', $user->id)->count()),
            Schema\Arr::make('userCardBadges')
                ->get(function (User $user) {
                    $limit = (int) resolve('flarum.settings')->get('v17development-user-badges.number_of_badges_on_user_card', 5);

                    $all = $user->relationLoaded('userBadges')
                        ? $user->userBadges
                        : UserBadge::query()->where('user_id', $user->id)->with('badge')->get();

                    $all = $all->filter(fn (UserBadge $userBadge) => $userBadge->badge !== null);
                    $selected = $all->where('in_user_card', true);
                    $list = ($selected->isEmpty() ? $all : $selected)->take($limit);

                    return $list->map(fn (UserBadge $userBadge) => [
                        'id' => (int) $userBadge->badge->id,
                        'name' => $userBadge->badge->name,
                        'image' => $userBadge->badge->image,
                        'icon' => $userBadge->badge->icon,
                        'backgroundColor' => $userBadge->badge->background_color,
                        'labelColor' => $userBadge->badge->label_color,
                        'iconColor' => $userBadge->badge->icon_color,
                    ])->values()->all();
                })
                ->nullable()
                // Writing the selection. Flarum 2 rejects attributes that no
                // field declares, so the 1.8 `Saving` listener's payload has to
                // be a real writable field here.
                ->writable()
                ->set(function (User $user, $value, \Flarum\Api\Context $context) {
                    $actor = $context->getActor();
                    $actor->assertCan('editUserCardBadges', $user);

                    $limit = (int) resolve('flarum.settings')->get('v17development-user-badges.number_of_badges_on_user_card', 5);
                    $selected = array_values(array_unique(array_map('intval', (array) $value)));

                    if (count($selected) > $limit) {
                        throw new \Flarum\Foundation\ValidationException([
                            'userCardBadges' => 'Too many badges selected.',
                        ]);
                    }

                    UserBadge::query()
                        ->where('user_id', $user->id)
                        ->where('in_user_card', 1)
                        ->when($selected, fn ($query) => $query->whereNotIn('id', $selected))
                        ->update(['in_user_card' => 0]);

                    if ($selected) {
                        UserBadge::query()
                            ->where('user_id', $user->id)
                            ->whereIn('id', $selected)
                            ->update(['in_user_card' => 1]);
                    }

                    return null;
                }),
        ])
        ->endpoint(Endpoint\Show::class, fn (Endpoint\Show $endpoint) => $endpoint->eagerLoad(['userBadges', 'userBadges.badge', 'userBadges.badge.category']))
        ->endpoint(Endpoint\Index::class, fn (Endpoint\Index $endpoint) => $endpoint->eagerLoad(['userBadges', 'userBadges.badge'])),

    (new Extend\ApiResource(Resource\DiscussionResource::class))
        ->endpoint(Endpoint\Index::class, fn (Endpoint\Index $endpoint) => $endpoint->eagerLoad([
            'user.userBadges', 'user.userBadges.badge',
            'lastPostedUser.userBadges', 'lastPostedUser.userBadges.badge',
        ]))
        ->endpoint(Endpoint\Show::class, fn (Endpoint\Show $endpoint) => $endpoint->eagerLoad([
            'user.userBadges', 'user.userBadges.badge',
            'posts.user.userBadges', 'posts.user.userBadges.badge',
        ])),

    (new Extend\ApiResource(Resource\PostResource::class))
        ->endpoint(Endpoint\Index::class, fn (Endpoint\Index $endpoint) => $endpoint->eagerLoad(['user.userBadges', 'user.userBadges.badge']))
        ->endpoint(Endpoint\Show::class, fn (Endpoint\Show $endpoint) => $endpoint->eagerLoad(['user.userBadges', 'user.userBadges.badge'])),

    // Forum flags consumed by the ported JS (were ForumSerializer attributes).
    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canGiveBadge')
                ->get(fn ($model, $context) => $context->getActor()->hasPermission('badges.giveBadge')),
            Schema\Boolean::make('canViewDetailedBadgeUsers')
                ->get(fn ($model, $context) => $context->getActor()->hasPermission('badges.canViewDetailedUsers')),
            Schema\Boolean::make('editOwnUserCardBadges')
                ->get(fn ($model, $context) => $context->getActor()->hasPermission('badges.editOwnUserCardBadges')),
            Schema\Boolean::make('editUserCardBadges')
                ->get(fn ($model, $context) => $context->getActor()->hasPermission('badges.editUserCardBadges')),
            Schema\Boolean::make('showBadgesOnUserCard')
                ->get(fn () => (bool) resolve('flarum.settings')->get('v17development-user-badges.show_badges_on_user_card', true)),
            Schema\Integer::make('numberOfBadgesOnUserCard')
                ->get(fn () => (int) resolve('flarum.settings')->get('v17development-user-badges.number_of_badges_on_user_card', 5)),
        ]),

    (new Extend\Notification())
        ->type(BadgeReceivedBlueprint::class, ['alert']),

    (new Extend\Policy())
        ->modelPolicy(User::class, UserPolicy::class),

    (new Extend\Settings())
        ->default('v17development-user-badges.show_badges_on_user_card', true)
        ->default('v17development-user-badges.number_of_badges_on_user_card', 5),
];

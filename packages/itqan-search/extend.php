<?php

use Flarum\Discussion\Search\DiscussionSearcher;
use Flarum\Extend;
use Flarum\User\Search\UserSearcher;
use Itqan\Search\Search\SmartDiscussionFullTextGambit;
use Itqan\Search\Search\SmartSearchServiceProvider;
use Itqan\Search\Search\SmartUserFullTextGambit;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    (new Extend\ServiceProvider())
        ->register(SmartSearchServiceProvider::class),

    (new Extend\SimpleFlarumSearch(UserSearcher::class))
        ->setFullTextGambit(SmartUserFullTextGambit::class),

    (new Extend\SimpleFlarumSearch(DiscussionSearcher::class))
        ->setFullTextGambit(SmartDiscussionFullTextGambit::class),
];

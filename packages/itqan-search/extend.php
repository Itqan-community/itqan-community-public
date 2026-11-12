<?php

use Flarum\Discussion\Search\DiscussionSearcher;
use Flarum\Extend;
use Flarum\Search\Database\DatabaseSearchDriver;
use Flarum\User\Search\UserSearcher;
use Itqan\Search\Search\SmartDiscussionFulltextFilter;
use Itqan\Search\Search\SmartSearchServiceProvider;
use Itqan\Search\Search\SmartUserFulltextFilter;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    (new Extend\ServiceProvider())
        ->register(SmartSearchServiceProvider::class),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->setFulltext(UserSearcher::class, SmartUserFulltextFilter::class)
        ->setFulltext(DiscussionSearcher::class, SmartDiscussionFulltextFilter::class),
];

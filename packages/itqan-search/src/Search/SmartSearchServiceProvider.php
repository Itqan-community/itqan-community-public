<?php

namespace Itqan\Search\Search;

use Flarum\Discussion\Search\FulltextFilter as CoreDiscussionFulltextFilter;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\User\Search\FulltextFilter as CoreUserFulltextFilter;

class SmartSearchServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(CoreUserFulltextFilter::class, SmartUserFulltextFilter::class);
        $this->container->bind(CoreDiscussionFulltextFilter::class, SmartDiscussionFulltextFilter::class);

        foreach ([
            'Flarum\\Nicknames\\NicknameFulltextFilter',
            'Flarum\\Nicknames\\NicknameFullTextFilter',
        ] as $nicknameFilter) {
            if (class_exists($nicknameFilter)) {
                $this->container->bind($nicknameFilter, SmartUserFulltextFilter::class);
            }
        }
    }
}

<?php

namespace Itqan\Search\Search;

use Flarum\Discussion\Search\Gambit\FulltextGambit;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Nicknames\NicknameFullTextGambit;

class SmartSearchServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        if (class_exists(NicknameFullTextGambit::class)) {
            $this->container->bind(NicknameFullTextGambit::class, SmartUserFullTextGambit::class);
        }

        $this->container->bind(FulltextGambit::class, SmartDiscussionFullTextGambit::class);
    }
}

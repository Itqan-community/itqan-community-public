<?php

namespace IanM\Translate\Access;

use Flarum\Discussion\Discussion;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class DiscussionPolicy extends AbstractPolicy
{
    public function translateAllFor(User $user, Discussion $discussion)
    {
        return $user->can('translateAnyForumLanguage') && $user->can('translateAll', $discussion);
    }
}

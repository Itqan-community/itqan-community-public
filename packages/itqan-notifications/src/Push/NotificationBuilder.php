<?php

namespace Itqan\Notifications\Push;

use Askvortsov\FlarumPWA\NotificationBuilder as Base;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;

class NotificationBuilder extends Base
{
    protected function getTitle(BlueprintInterface $blueprint): string
    {
        if ($blueprint instanceof MailableInterface) {
            return $blueprint->getEmailSubject($this->translator);
        }

        // The parent passes a bare 'username' key; Symfony's strtr() then leaves the
        // braces in place ("{admin} liked your post"). Pass BOTH forms so it is
        // correct whether the stored string is "{username}..." or "username...".
        if ($blueprint->getType() === 'postLiked' && ($user = $blueprint->getFromUser())) {
            $name = $user->getDisplayNameAttribute();

            return $this->translator->trans('flarum-likes.forum.notifications.post_liked_text', [
                '{username}' => $name,
                'username'   => $name,
            ]);
        }

        return parent::getTitle($blueprint);
    }
}

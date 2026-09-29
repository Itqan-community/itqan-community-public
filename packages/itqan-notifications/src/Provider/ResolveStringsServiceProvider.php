<?php

namespace Itqan\Notifications\Provider;

use Askvortsov\FlarumPWA\NotificationBuilder as BaseBuilder;
use Flarum\Foundation\AbstractServiceProvider;
use Itqan\Notifications\Push\NotificationBuilder;

class ResolveStringsServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        // PushSender / PushNotificationDriver resolve NotificationBuilder from the
        // container, so this binding makes them use our subclass.
        $this->container->bind(BaseBuilder::class, NotificationBuilder::class);
    }
}

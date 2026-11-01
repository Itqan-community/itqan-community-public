<?php

namespace Itqan\Notifications\Provider;

use FoF\PWA\NotificationBuilder as BaseBuilder;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Messages\Notification\MessageReceivedBlueprint;
use Itqan\Notifications\Push\NotificationBuilder;

class ResolveStringsServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        // PushSender / PushNotificationDriver resolve NotificationBuilder from the
        // container, so this binding makes them use our subclass.
        $this->container->bind(BaseBuilder::class, NotificationBuilder::class);

        // Push is the channel upstream never opted into; the bell is handled by
        // our own alertable blueprint, so only push is merged here. Blueprint
        // arrays are replaced (not merged) by whichever extension loads last,
        // so the guarantee is made at resolve time instead of relying on order.
        $this->container->extend('flarum.notification.blueprints', function (array $blueprints) {
            $blueprint = MessageReceivedBlueprint::class;

            if (isset($blueprints[$blueprint])) {
                $blueprints[$blueprint] = array_values(array_unique(array_merge($blueprints[$blueprint], ['push'])));
            }

            return $blueprints;
        });
    }
}

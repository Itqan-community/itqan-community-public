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

        // Direct messages ship with the `email` driver only. The blueprint
        // registration in `extend.php` asks for alert + push as well, but
        // blueprint arrays are replaced (not merged) by whichever extension
        // loads last, so the guarantee is made here instead of relying on
        // extension ordering: merge the extra channels into the resolved map.
        $this->container->extend('flarum.notification.blueprints', function (array $blueprints) {
            $blueprint = MessageReceivedBlueprint::class;

            if (isset($blueprints[$blueprint])) {
                $blueprints[$blueprint] = array_values(
                    array_unique(array_merge($blueprints[$blueprint], ['alert', 'push']))
                );
            }

            return $blueprints;
        });
    }
}

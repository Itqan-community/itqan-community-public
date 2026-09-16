<?php

namespace Itqan\Notifications\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Notification\NotificationSyncer;
use Itqan\Notifications\Driver\ChannelFilteringNotificationDriver;
use Itqan\Notifications\FollowTagsChannelDecision;

/**
 * Wraps the already-constructed email and push drivers after core has
 * registered types on the originals. Alert is left native.
 */
class NotificationDriverProvider extends AbstractServiceProvider
{
    public function boot()
    {
        $decision = $this->container->make(FollowTagsChannelDecision::class);

        foreach (NotificationSyncer::getNotificationDrivers() as $name => $driver) {
            if ($name !== 'email' && $name !== 'push') {
                continue;
            }

            if ($driver instanceof ChannelFilteringNotificationDriver) {
                continue;
            }

            NotificationSyncer::addNotificationDriver(
                $name,
                new ChannelFilteringNotificationDriver($driver, $decision, $name)
            );
        }
    }
}

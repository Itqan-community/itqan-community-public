<?php

namespace Itqan\Notifications\Driver;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\Driver\NotificationDriverInterface;
use Itqan\Notifications\FollowTagsChannelDecision;

/**
 * Wraps a native notification driver and, for FoF Follow Tags only, drops
 * recipients whose effective channel forbids this driver.
 *
 * All other blueprints are forwarded unchanged. This class never sends mail
 * or push itself.
 */
class ChannelFilteringNotificationDriver implements NotificationDriverInterface
{
    /**
     * @var NotificationDriverInterface
     */
    protected $inner;

    /**
     * @var FollowTagsChannelDecision
     */
    protected $decision;

    /**
     * @var string
     */
    protected $driverName;

    public function __construct(NotificationDriverInterface $inner, FollowTagsChannelDecision $decision, string $driverName)
    {
        $this->inner = $inner;
        $this->decision = $decision;
        $this->driverName = $driverName;
    }

    public function send(BlueprintInterface $blueprint, array $users): void
    {
        $this->inner->send(
            $blueprint,
            $this->decision->filterForDriver($blueprint, $users, $this->driverName)
        );
    }

    public function registerType(string $blueprintClass, array $driversEnabledByDefault): void
    {
        $this->inner->registerType($blueprintClass, $driversEnabledByDefault);
    }
}

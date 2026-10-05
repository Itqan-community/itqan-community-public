<?php

namespace Itqan\Notifications\Provider;

use Askvortsov\FlarumPWA\NotificationBuilder as BaseBuilder;
use Flarum\Foundation\AbstractServiceProvider;
use Illuminate\Queue\QueueManager;
use Itqan\Notifications\Push\NotificationBuilder;

class ResolveStringsServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        // PushSender / PushNotificationDriver resolve NotificationBuilder from the
        // container, so this binding makes them use our subclass.
        $this->container->bind(BaseBuilder::class, NotificationBuilder::class);
    }

    public function boot()
    {
        // Ensure queued notification jobs (confirmation emails, push notifications, mail)
        // execute synchronously in-line on environments without an active queue daemon.
        if ($this->container->bound(QueueManager::class)) {
            /** @var QueueManager $manager */
            $manager = $this->container->make(QueueManager::class);
            if (method_exists($manager, 'setDefaultDriver')) {
                $manager->setDefaultDriver('sync');
            }
        }

        // Defensive fix for fof/pretty-mail: If fof-pretty-mail.mailhtml template setting
        // is missing or empty in DB settings, set fallback template so BladeCompiler does not crash.
        if ($this->container->bound('flarum.settings')) {
            $settings = $this->container->make('flarum.settings');
            if (empty($settings->get('fof-pretty-mail.mailhtml'))) {
                $settings->set('fof-pretty-mail.mailhtml', '{!! $body !!}');
            }
        }
    }
}

<?php

namespace Bendary\AdminAudit\Listeners;

use Bendary\AdminAudit\AuditLog;
use Flarum\Extension\Event\Disabled;
use Flarum\Extension\Event\Enabled;
use Flarum\Settings\Event\Saving;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;

class AuditLogEvents
{
    public function subscribe(Dispatcher $events)
    {
        $events->listen(Saving::class, [$this, 'whenSettingsSaved']);
        $events->listen(Enabled::class, [$this, 'whenExtensionEnabled']);
        $events->listen(Disabled::class, [$this, 'whenExtensionDisabled']);
    }

    public function whenSettingsSaved(Saving $event)
    {
        $settings = $event->settings;
        $keys = array_keys($settings);
        
        // Categorize the specific setting change
        $types = [];
        foreach ($keys as $key) {
            if (str_starts_with($key, 'theme_') || str_starts_with($key, 'custom_')) {
                $types[] = 'Appearance Settings';
            } elseif (str_starts_with($key, 'mail_')) {
                $types[] = 'Mail Settings';
            } elseif (str_starts_with($key, 'forum_') || $key === 'default_locale') {
                $types[] = 'Basic Settings';
            } elseif (str_contains($key, '.')) {
                $prefix = explode('.', $key)[0];
                $types[] = "Extension ($prefix) Settings";
            } else {
                $types[] = 'Settings';
            }
        }
        $types = array_unique($types);
        $targetDesc = count($types) > 0 ? implode(', ', $types) : 'Settings Updated';

        $actorId = $this->getCurrentUserId();

        $audit = AuditLog::build(
            $actorId,
            'settings',
            'update_settings',
            $targetDesc, 
            null, 
            $settings, 
            null,
            $this->getIpAddress()
        );
        $audit->save();
    }

    public function whenExtensionEnabled(Enabled $event)
    {
        $extension = $event->extension;
        $audit = AuditLog::build(
            $this->getCurrentUserId(),
            'extensions',
            'enable_extension',
            $extension->getId(),
            null,
            null,
            [
                'version' => $extension->getVersion(),
                'name' => $extension->name,
                'title' => $extension->composerJsonAttribute('extra.flarum-extension.title')
            ],
            $this->getIpAddress()
        );
        $audit->save();
    }

    public function whenExtensionDisabled(Disabled $event)
    {
        $extension = $event->extension;
        $audit = AuditLog::build(
            $this->getCurrentUserId(),
            'extensions',
            'disable_extension',
            $extension->getId(),
            null,
            null,
            [
                'version' => $extension->getVersion(),
                'name' => $extension->name,
                'title' => $extension->composerJsonAttribute('extra.flarum-extension.title')
            ],
            $this->getIpAddress()
        );
        $audit->save();
    }

    protected function getCurrentUserId()
    {
        if (app()->bound('audit.current_request')) {
            try {
                $request = app()->make('audit.current_request');
                $actor = \Flarum\Http\RequestUtil::getActor($request);
                if ($actor && $actor instanceof User && !$actor->isGuest()) {
                    return $actor->id;
                }
            } catch (\Exception $e) {
                // Ignore exception fallback
            }
        }

        return null;
    }

    protected function getIpAddress()
    {
        if (app()->bound('audit.current_request')) {
            try {
                $request = app()->make('audit.current_request');
                return Arr::get($request->getServerParams(), 'REMOTE_ADDR');
            } catch (\Exception $e) {
                // Ignore exception fallback
            }
        }

        return null;
    }
}

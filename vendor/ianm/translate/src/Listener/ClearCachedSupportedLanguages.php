<?php

namespace IanM\Translate\Listener;

use Flarum\Settings\Event\Saving;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;

class ClearCachedSupportedLanguages
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;
    
    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }
    
    public function handle(Saving $event)
    {
        $newProvider = Arr::get($event->settings, 'ianm-translate.provider');
        
        if ($newProvider && $newProvider !== $oldProvider = $this->settings->get('ianm-translate.provider')) {
            $this->settings->delete("ianm-translate.{$oldProvider}.supported_languages");
        }
    }
}

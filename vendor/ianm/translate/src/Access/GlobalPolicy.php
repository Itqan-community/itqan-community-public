<?php

namespace IanM\Translate\Access;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class GlobalPolicy extends AbstractPolicy
{
    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }
    
    public function translateAnyForumLanguage(User $user)
    {
        return (bool) $this->settings->get('ianm-translate.translate-all-enabled');
    }
}
<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate\Listener;

use Flarum\Post\CommentPost;
use Flarum\Post\Event\Saving;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;

class AddPostContentLanguage
{
    public function __construct(protected TranslationProviderInterface $translator)
    {
    }
    
    public function handle(Saving $event)
    {
        if ($event->post instanceof CommentPost && !empty($event->post->content) && !$event->post->detected_lang) {
            $event->post->detected_lang = $this->translator->identifyLanguage($event->post);
        }
    }
}

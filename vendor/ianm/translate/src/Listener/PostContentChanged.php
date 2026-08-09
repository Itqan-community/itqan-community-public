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
use Flarum\Post\Event\Revised;
use IanM\Translate\Job\UpdatePostTranslationJob;
use IanM\Translate\Model\PostTranslation;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue;

class PostContentChanged
{
    public function __construct(protected Queue $queue)
    {
    }
    
    public function subscribe(Dispatcher $events)
    {
        $events->listen(Revised::class, [$this, 'postWasRevised']);
    }

    public function postWasRevised(Revised $event)
    {
        if ($event->post instanceof CommentPost) {
            PostTranslation::query()->where('post_id', $event->post->id)->update(['update_needed' => true]);
            $this->queue->push(new UpdatePostTranslationJob($event->post));
        }
    }
}

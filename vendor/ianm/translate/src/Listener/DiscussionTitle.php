<?php

namespace IanM\Translate\Listener;

use Flarum\Discussion\Event\Saving;
use IanM\Translate\Job\UpdateDiscussionTranslationJob;
use IanM\Translate\Model\DiscussionTranslation;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue;

class DiscussionTitle
{
    public function __construct(protected TranslationProviderInterface $translator, protected Queue $queue)
    {
    }
    
    public function subscribe(Dispatcher $events)
    {
        $events->listen(Saving::class, [$this, 'discussionSaving']);
    }
    
    public function discussionSaving(Saving $event)
    {
        if (isset($event->data['attributes']['title'])) {

            if (!empty($event->discussion->title) && !$event->discussion->detected_lang) {
                $event->discussion->detected_lang = $this->translator->identifyTitleLanguage($event->discussion);
            } else {
                DiscussionTranslation::query()->where('discussion_id', $event->discussion->id)->update(['update_needed' => true]);
                $this->queue->push(new UpdateDiscussionTranslationJob($event->discussion));
            }
        }
    }
}

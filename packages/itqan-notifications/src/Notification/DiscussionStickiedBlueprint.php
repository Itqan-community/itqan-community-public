<?php

namespace Itqan\Notifications\Notification;

use Flarum\Discussion\Discussion;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class DiscussionStickiedBlueprint implements BlueprintInterface, MailableInterface
{
    public Discussion $discussion;
    public ?User $actor;

    public function __construct(Discussion $discussion, ?User $actor = null)
    {
        $this->discussion = $discussion;
        $this->actor = $actor;
    }

    public function getFromUser(): ?User
    {
        return $this->actor;
    }

    public function getSubject()
    {
        return $this->discussion;
    }

    public function getData(): array
    {
        return ['discussionId' => (int) $this->discussion->id];
    }

    public function getEmailView(): array
    {
        return ['text' => 'itqan-notifications::emails.discussionStickied'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('itqan-notifications.email.discussion_stickied.subject', [
            '{title}' => $this->discussion->title ?? '',
        ]);
    }

    public static function getType(): string
    {
        return 'discussionStickied';
    }

    public static function getSubjectModel(): string
    {
        return Discussion::class;
    }
}

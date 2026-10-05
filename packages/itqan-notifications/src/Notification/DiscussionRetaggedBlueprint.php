<?php

namespace Itqan\Notifications\Notification;

use Flarum\Discussion\Discussion;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class DiscussionRetaggedBlueprint implements BlueprintInterface, MailableInterface
{
    public Discussion $discussion;
    public ?User $actor;
    public string $tagName;

    public function __construct(Discussion $discussion, ?User $actor = null, string $tagName = '')
    {
        $this->discussion = $discussion;
        $this->actor = $actor;
        $this->tagName = $tagName;
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
        return [
            'discussionId' => (int) $this->discussion->id,
            'newTag'       => $this->tagName,
        ];
    }

    public function getEmailView(): array
    {
        return ['text' => 'itqan-notifications::emails.discussionRetagged'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('itqan-notifications.email.discussion_retagged.subject', [
            '{title}'   => $this->discussion->title ?? '',
            '{new_tag}' => $this->tagName,
        ]);
    }

    public static function getType(): string
    {
        return 'discussionRetagged';
    }

    public static function getSubjectModel(): string
    {
        return Discussion::class;
    }
}

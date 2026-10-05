<?php

namespace Itqan\Notifications\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Post\Post;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class PostRejectedBlueprint implements BlueprintInterface, MailableInterface
{
    public Post $post;
    public string $reason;

    public function __construct(Post $post, string $reason = '')
    {
        $this->post = $post;
        $this->reason = $reason;
    }

    public function getFromUser(): ?User
    {
        return null;
    }

    public function getSubject()
    {
        return $this->post;
    }

    public function getData(): array
    {
        return [
            'postNumber' => (int) $this->post->number,
            'reason'     => $this->reason,
        ];
    }

    public function getEmailView(): array
    {
        return ['text' => 'itqan-notifications::emails.postRejected'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('itqan-notifications.email.post_rejected.subject', [
            '{title}' => $this->post->discussion?->title ?? '',
        ]);
    }

    public static function getType(): string
    {
        return 'postRejected';
    }

    public static function getSubjectModel(): string
    {
        return Post::class;
    }
}

<?php

namespace Itqan\Notifications\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Post\Post;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class PostApprovedBlueprint implements BlueprintInterface, MailableInterface
{
    public Post $post;

    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    public function getFromUser(): ?User
    {
        return null; // System / Moderation notification
    }

    public function getSubject()
    {
        return $this->post;
    }

    public function getData(): array
    {
        return ['postNumber' => (int) $this->post->number];
    }

    public function getEmailView(): array
    {
        return ['text' => 'itqan-notifications::emails.postApproved'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('itqan-notifications.email.post_approved.subject', [
            '{title}' => $this->post->discussion?->title ?? '',
        ]);
    }

    public static function getType(): string
    {
        return 'postApproved';
    }

    public static function getSubjectModel(): string
    {
        return Post::class;
    }
}

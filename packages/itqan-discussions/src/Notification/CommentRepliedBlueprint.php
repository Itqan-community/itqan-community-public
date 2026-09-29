<?php

namespace Itqan\Discussions\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Post\Post;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentRepliedBlueprint implements BlueprintInterface, MailableInterface
{
    /**
     * @var Post The new reply post.
     */
    public $post;

    /**
     * @var Post The parent post that was replied to (the one being replied to).
     */
    public $parentPost;

    /**
     * @param Post $post       The new reply.
     * @param Post $parentPost The comment that was replied to.
     */
    public function __construct(Post $post, Post $parentPost)
    {
        $this->post = $post;
        $this->parentPost = $parentPost;
    }

    /**
     * The user that performed the action (the replier).
     */
    public function getFromUser(): ?User
    {
        return $this->post->user;
    }

    /**
     * The model that is the subject of this activity.
     * The subject is the parent post — the one being replied to.
     */
    public function getSubject()
    {
        return $this->parentPost;
    }

    /**
     * Payload stored alongside the notification record.
     */
    public function getData(): array
    {
        return [
            'postNumber'       => (int) $this->post->number,
            'parentPostNumber' => (int) $this->parentPost->number,
        ];
    }

    /**
     * Blade view used for the email body.
     */
    public function getEmailView(): array
    {
        return ['text' => 'itqan-discussions::emails.commentReplied'];
    }

    /**
     * Localized email subject line.
     */
    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('itqan-discussions.email.comment_replied.subject', [
            '{replier_display_name}' => $this->post->user->display_name,
            '{title}'                => $this->post->discussion->title,
        ]);
    }

    public static function getType(): string
    {
        return 'commentReplied';
    }

    public static function getSubjectModel(): string
    {
        return Post::class;
    }
}
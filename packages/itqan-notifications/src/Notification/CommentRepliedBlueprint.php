<?php

namespace Itqan\Notifications\Notification;

use Flarum\Post\Post;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentRepliedBlueprint implements BlueprintInterface, MailableInterface
{
    /**
     * @var Post The reply post.
     */
    public $post;

    /**
     * @var Post The parent post that was replied to.
     */
    public $parentPost;

    /**
     * @param Post $post The new reply.
     * @param Post $parentPost The comment being replied to.
     */
    public function __construct(Post $post, Post $parentPost)
    {
        $this->post = $post;
        $this->parentPost = $parentPost;
    }

    /**
     * Get the user that sent the notification (the replier).
     */
    public function getFromUser(): ?User
    {
        return $this->post->user;
    }

    /**
     * Get the model that is the subject of this activity.
     * Returns the parent post — the one being replied to.
     */
    public function getSubject()
    {
        return $this->parentPost;
    }

    /**
     * Get the data to be stored in the notification.
     */
    public function getData(): array
    {
        return [
            'postNumber' => (int) $this->post->number,
            'parentPostNumber' => (int) $this->parentPost->number,
        ];
    }

    /**
     * Get the email view for this notification.
     */
    public function getEmailView(): array
    {
        return ['text' => 'itqan-notifications::emails.commentReplied'];
    }

    /**
     * Get the subject line for the notification email.
     */
    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('itqan-notifications.email.comment_replied.subject', [
            '{replier_display_name}' => $this->post->user->display_name,
            '{title}' => $this->post->discussion->title,
        ]);
    }

    /**
     * Get the serialized type of this notification.
     */
    public static function getType(): string
    {
        return 'commentReplied';
    }

    /**
     * Get the model class for the subject of this activity.
     */
    public static function getSubjectModel(): string
    {
        return Post::class;
    }
}

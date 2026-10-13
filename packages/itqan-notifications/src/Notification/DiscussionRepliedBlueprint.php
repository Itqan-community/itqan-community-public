<?php

namespace Itqan\Notifications\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Post\Post;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * "Someone replied to your discussion" — fired for the discussion's author on
 * every new reply in their discussion. Staging mirrors itqan-discussions but
 * under the Itqan\Notifications namespace and the itqan-notifications email
 * view namespace, because the W2 reply notifications have moved out of the
 * discussions extension.
 */
class DiscussionRepliedBlueprint implements BlueprintInterface, MailableInterface
{
    /**
     * @var Post The new reply post.
     */
    public $post;

    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    /**
     * The model that triggered the activity (the new reply itself).
     */
    public function getSubject()
    {
        return $this->post;
    }

    /**
     * The user that performed the action (the replier).
     */
    public function getFromUser()
    {
        return $this->post->user;
    }

    /**
     * Payload stored alongside the notification record.
     */
    public function getData()
    {
        return ['postNumber' => (int) $this->post->number];
    }

    /**
     * Blade view used for the email body.
     */
    public function getEmailView()
    {
        return ['text' => 'itqan-notifications::emails.discussionReplied'];
    }

    /**
     * Localized email subject line.
     */
    public function getEmailSubject(TranslatorInterface $translator)
    {
        return $translator->trans('itqan-notifications.email.discussion_replied.subject', [
            '{poster_display_name}' => $this->post->user?->display_name ?? '',
            '{title}'               => $this->post->discussion?->title ?? '',
        ]);
    }

    public static function getType()
    {
        return 'discussionReplied';
    }

    public static function getSubjectModel()
    {
        return Post::class;
    }
}
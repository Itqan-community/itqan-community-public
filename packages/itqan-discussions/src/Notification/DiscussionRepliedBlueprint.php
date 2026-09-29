<?php

namespace Itqan\Discussions\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Post\Post;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        return ['text' => 'itqan-discussions::emails.discussionReplied'];
    }

    /**
     * Localized email subject line.
     */
    public function getEmailSubject(TranslatorInterface $translator)
    {
        return $translator->trans('itqan-discussions.email.discussion_replied.subject', [
            '{title}' => $this->post->discussion?->title ?? '',
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
<?php

namespace Itqan\Notifications\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Post\Post;
use Symfony\Contracts\Translation\TranslatorInterface;

class DiscussionRepliedBlueprint implements BlueprintInterface, MailableInterface
{
    /**
     * @var Post
     */
    public $post;

    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    public function getSubject()
    {
        return $this->post;
    }

    public function getFromUser()
    {
        return $this->post->user;
    }

    public function getData()
    {
        return ['postNumber' => (int) $this->post->number];
    }

    public function getEmailView()
    {
        return ['text' => 'itqan-notifications::emails.discussionReplied'];
    }

    public function getEmailSubject(TranslatorInterface $translator)
    {
        return $translator->trans('itqan-notifications.email.discussion_replied.subject', [
            '{title}' => $this->post->discussion->title,
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

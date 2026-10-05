<?php

namespace Itqan\Notifications\Push;

use Askvortsov\FlarumPWA\NotificationBuilder as Base;
use Flarum\Discussion\Discussion;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\Post;
use Flarum\User\User;

class NotificationBuilder extends Base
{
    protected function getTitle(BlueprintInterface $blueprint): string
    {
        $type = $blueprint->getType();
        $fromUser = method_exists($blueprint, 'getFromUser') ? $blueprint->getFromUser() : null;
        $username = $fromUser ? $fromUser->display_name : '';

        // Extract discussion title if available
        $discussionTitle = '';
        $subject = $blueprint->getSubject();
        if ($subject instanceof Discussion) {
            $discussionTitle = $subject->title;
        } elseif ($subject instanceof Post && $subject->discussion) {
            $discussionTitle = $subject->discussion->title;
        } elseif (isset($blueprint->post) && $blueprint->post instanceof Post && $blueprint->post->discussion) {
            $discussionTitle = $blueprint->post->discussion->title;
        }

        switch ($type) {
            case 'discussionReplied':
                return $this->translator->trans('itqan-notifications.push.discussion_replied.title', [
                    '{username}' => $username,
                    '{title}'    => $discussionTitle,
                ]);

            case 'commentReplied':
                return $this->translator->trans('itqan-notifications.push.comment_replied.title', [
                    '{username}' => $username,
                    '{title}'    => $discussionTitle,
                ]);

            case 'userMentioned':
                return $this->translator->trans('itqan-notifications.push.user_mentioned.title', [
                    '{username}' => $username,
                    '{title}'    => $discussionTitle,
                ]);

            case 'postMentioned':
                return $this->translator->trans('itqan-notifications.push.post_mentioned.title', [
                    '{username}' => $username,
                    '{title}'    => $discussionTitle,
                ]);

            case 'groupMentioned':
                return $this->translator->trans('itqan-notifications.push.group_mentioned.title', [
                    '{username}' => $username,
                    '{title}'    => $discussionTitle,
                ]);

            case 'newPost':
            case 'newPostInTag':
            case 'newPostByUser':
                return $this->translator->trans('itqan-notifications.push.new_post.title', [
                    '{username}' => $username,
                    '{title}'    => $discussionTitle,
                ]);

            case 'newDiscussionInTag':
            case 'newDiscussionByUser':
                return $this->translator->trans('itqan-notifications.push.new_discussion.title', [
                    '{username}' => $username,
                    '{title}'    => $discussionTitle,
                ]);

            case 'postLiked':
                return $this->translator->trans('itqan-notifications.push.post_liked.title', [
                    '{username}' => $username,
                    '{title}'    => $discussionTitle,
                ]);

            case 'postReacted':
                return $this->translator->trans('itqan-notifications.push.post_reacted.title', [
                    '{username}' => $username,
                    '{title}'    => $discussionTitle,
                ]);

            case 'badgeReceived':
                $badgeName = isset($blueprint->userBadge) && isset($blueprint->userBadge->badge) ? $blueprint->userBadge->badge->name : '';
                return $this->translator->trans('itqan-notifications.push.badge_received.title', [
                    '{badge_name}' => $badgeName,
                ]);

            case 'postApproved':
                return $this->translator->trans('itqan-notifications.push.post_approved.title', [
                    '{title}' => $discussionTitle,
                ]);

            case 'postRejected':
                return $this->translator->trans('itqan-notifications.push.post_rejected.title', [
                    '{title}' => $discussionTitle,
                ]);

            case 'discussionStickied':
                return $this->translator->trans('itqan-notifications.push.discussion_stickied.title', [
                    '{title}' => $discussionTitle,
                ]);

            case 'discussionRetagged':
                return $this->translator->trans('itqan-notifications.push.discussion_retagged.title', [
                    '{title}' => $discussionTitle,
                ]);

            default:
                return parent::getTitle($blueprint);
        }
    }

    protected function getBody(BlueprintInterface $blueprint): string
    {
        $post = null;
        $subject = $blueprint->getSubject();

        if ($subject instanceof Post) {
            $post = $subject;
        } elseif (isset($blueprint->post) && $blueprint->post instanceof Post) {
            $post = $blueprint->post;
        } elseif (isset($blueprint->reply) && $blueprint->reply instanceof Post) {
            $post = $blueprint->reply;
        } elseif ($subject instanceof Discussion) {
            // For new discussions or discussion-level events, use the first post
            $post = $subject->firstPost;
        }

        if ($post) {
            return $this->formatExcerpt($post->content);
        }

        return parent::getBody($blueprint);
    }

    protected function getUrl(BlueprintInterface $blueprint): string
    {
        $post = null;
        $subject = $blueprint->getSubject();

        if ($subject instanceof Post) {
            $post = $subject;
        } elseif (isset($blueprint->post) && $blueprint->post instanceof Post) {
            $post = $blueprint->post;
        } elseif (isset($blueprint->reply) && $blueprint->reply instanceof Post) {
            $post = $blueprint->reply;
        }

        if ($post && $post->discussion_id) {
            return $this->url->to('forum')->route(
                'discussion',
                ['id' => $post->discussion_id, 'near' => $post->number]
            );
        }

        return parent::getUrl($blueprint);
    }

    /**
     * Clean and truncate content into a push notification excerpt.
     */
    protected function formatExcerpt(?string $content, int $length = 120): string
    {
        if (empty($content)) {
            return '';
        }

        // Remove BBCode formatting [quote]...[/quote], [b], etc.
        $text = preg_replace('/\[\/?\w+.*?\]/s', '', $content);

        // Remove HTML tags
        $text = strip_tags($text);

        // Remove Markdown links and formatting characters
        $text = preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $text);
        $text = preg_replace('/[#*_~`]/', '', $text);

        // Collapse multiple spaces/newlines into a single space
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (mb_strlen($text) > $length) {
            return mb_substr($text, 0, $length - 3) . '...';
        }

        return $text;
    }
}

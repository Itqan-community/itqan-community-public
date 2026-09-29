<?php

namespace Itqan\Notifications\Strings;

/**
 * Reviewed (key, locale) => value corrections for the fof/linguist strings that
 * override every locale file. Each entry must be justified by the render harness.
 */
class NotificationStringCorrections
{
    /**
     * Every entry here was derived by:
     *  1. Reading the blueprint's getEmailSubject() and its blade under vendor/
     *     to confirm which keys the code passes to translator->trans().
     *  2. Reading the current fof_linguist_strings row for that (key, locale).
     *  3. Rewriting the row so substitution leaves no literal {braces} in the
     *     rendered output (scan.php rejects ANY `{...}` pattern in output).
     *
     * Notes on placeholder style:
     *  - Flarum core, mentions, subscriptions, and ianm/follow-users pass
     *    {braced} keys from the blade. For these, the corrected string keeps
     *    {braced} tokens; strtr then replaces the full {key} → value and the
     *    braces disappear (matches scan.php rules).
     *  - fof/follow-tags blades pass BARE keys (vendor bug we can't patch).
     *    Using {braced} tokens in the string would leave {value} in the output
     *    because PHP strtr's longest-substring match keeps the surrounding
     *    braces. So the corrected fof-follow-tags body strings use bare
     *    tokens that the blade actually passes.
     */
    /** @return array<int, array{key:string, locale:string, value:string}> */
    public static function all(): array
    {
        return [
            // --- subscriptions (flarum-subscriptions) ---
            // Vendor NewPostBlueprint::getEmailSubject passes ONLY {title}.
            // Old EN used {poster_display_name} which is never substituted,
            // so scan.php flagged `{poster_display_name}` in both email subject
            // and the derived push title. AR was already clean.
            [
                'key' => 'flarum-subscriptions.email.new_post.subject',
                'locale' => 'en',
                'value' => '[New Post] {title}',
            ],

            // --- mentions (flarum-mentions) ---
            // PostMentionedBlueprint::getEmailSubject + the email body blade
            // pass {replier_display_name}; old EN rows used {mentioner_display_name}
            // which is never substituted → leaked braces into subject, body, and
            // the push title (which reuses getEmailSubject). AR rows already
            // use {replier_display_name}.
            [
                'key' => 'flarum-mentions.email.post_mentioned.subject',
                'locale' => 'en',
                'value' => '{replier_display_name} mentioned you in: {title}',
            ],
            [
                'key' => 'flarum-mentions.email.post_mentioned.body',
                'locale' => 'en',
                'value' => "Hey {recipient_display_name}!\n\n{replier_display_name} mentioned you in a post in: {title}\n\nTo view the post, visit the following link:\n{url}\n\n---\n\n{content}",
            ],

            // --- fof/follow-tags (bodies) ---
            // vendor/blades/newDiscussion.blade.php passes BARE keys
            // (recipient_display_name, actor_display_name, discussion_title,
            //  discussion_url, post_content). With {braced} tokens in the
            // string, PHP strtr does longest-substring replacement and keeps
            // the surrounding braces, so output ends up as `{value}` and
            // scan.php flags it. Switching to bare tokens makes strtr produce
            // a clean `value` instead.
            [
                'key' => 'fof-follow-tags.email.body.newDiscussionInTag',
                'locale' => 'en',
                'value' => "Hey {recipient_display_name}!\n\n{actor_display_name} started a new discussion in a tag you follow: {discussion_title}\n\nTo view the discussion, visit the following link:\n{discussion_url}\n\n---\n\n{post_content}",
            ],
            [
                'key' => 'fof-follow-tags.email.body.newDiscussionInTag',
                'locale' => 'ar',
                'value' => "مرحباً {recipient_display_name}!\n\nبدأ {actor_display_name} نقاشاً جديداً في وسم تتابعه: {discussion_title}\n\nلعرض النقاش، تفضل بزيارة الرابط التالي:\n{discussion_url}\n\n---\n\n{post_content}",
            ],
            // vendor/blades/newPost.blade.php passes BARE keys
            // (recipient_display_name, actor_display_name, discussion_title,
            //  post_url, post_content).
            [
                'key' => 'fof-follow-tags.email.body.newPostInTag',
                'locale' => 'en',
                'value' => "Hey {recipient_display_name}!\n\n{actor_display_name} replied in a tag you follow: {discussion_title}\n\nTo view the reply, visit the following link:\n{post_url}\n\n---\n\n{post_content}",
            ],
            [
                'key' => 'fof-follow-tags.email.body.newPostInTag',
                'locale' => 'ar',
                'value' => "مرحباً {recipient_display_name}!\n\nقام {actor_display_name} بالرد على: {discussion_title}\n\nلعرض الرد، تفضل بزيارة الرابط التالي:\n{post_url}\n\n---\n\n{post_content}",
            ],
            // vendor/blades/newTag.blade.php passes BARE keys
            // (recipient_display_name, actor_display_name, author_display_name,
            //  discussion_title, discussion_url). EN body falls back to the
            // vendor YAML (also bare-passed), which already substitutes clean,
            // so only the AR override needs the bare-token treatment.
            [
                'key' => 'fof-follow-tags.email.body.newDiscussionTag',
                'locale' => 'ar',
                'value' => 'مرحباً {recipient_display_name}، قام {actor_display_name} بتغيير الوسم في نقاشٍ أجراه {author_display_name} إلى الوسم الذي تتابعه {discussion_title} للاطلاع على النقاش الجديد، تفضل بزيارة الرابط التالي: {discussion_url}',
            ],

            // --- ianm/follow-users (subjects) ---
            // DB EN rows for these two subjects contain Arabic copy that the
            // linguist cache serves verbatim, polluting the EN locale
            // (scan-linguist-en.php). The blueprint passes only {title};
            // vendor YAML supplies the correct English phrasing.
            [
                'key' => 'ianm-follow-users.email.new_discussion_by_user_subject',
                'locale' => 'en',
                'value' => '[Follow User] {title}',
            ],
            [
                'key' => 'ianm-follow-users.email.new_post_subject',
                'locale' => 'en',
                'value' => '[Follow User] New post in {title}',
            ],
        ];
    }
}

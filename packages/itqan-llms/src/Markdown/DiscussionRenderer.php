<?php

namespace Itqan\Llms\Markdown;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;
use Itqan\Llms\Support\ForumUrls;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renders one discussion as Markdown.
 *
 * Two things here are specific to this forum and are the reason the export is
 * not a plain chronological dump:
 *
 *  - itqan/flarum-discussions stores a vote score on the discussion and on
 *    every post, and a reply tree in `parent_id` / `reply_count`. Both are
 *    surfaced: the score is the community's signal for what was worth reading,
 *    and the tree tells a model who was answering whom.
 *  - Posts are emitted depth-first, so a reply always sits directly beneath the
 *    comment it answers. `number` order interleaves a late reply to post #2
 *    among unrelated comments written after it.
 */
class DiscussionRenderer
{
    /**
     * @var HtmlToMarkdown
     */
    private $html;

    /**
     * @var ThreadTree
     */
    private $tree;

    /**
     * @var ForumUrls
     */
    private $urls;

    public function __construct(HtmlToMarkdown $html, ThreadTree $tree, ForumUrls $urls)
    {
        // The converter is shared, so the base URL is attached to a copy rather
        // than mutating the shared instance.
        $this->html = $html->withBaseUrl($urls->base());
        $this->tree = $tree;
        $this->urls = $urls;
    }

    /**
     * @param Post[] $posts Comments visible to this reader.
     */
    public function render(
        Discussion $discussion,
        array $posts,
        User $actor,
        ServerRequestInterface $request
    ): string {
        $url = $this->urls->toDiscussion($discussion);
        $markdownUrl = $this->urls->toDiscussionMarkdown($discussion);

        $out = '# '.$this->heading($discussion->title)."\n\n";

        $out .= $this->discussionMeta($discussion, $actor, $url, $markdownUrl);

        $tags = $this->tags($discussion);

        if ($tags !== []) {
            $out .= '- **Tags**: '.implode(', ', $tags)."\n";
        }

        $language = $this->language($discussion);

        if ($language !== null) {
            $out .= '- **Language**: '.$language."\n";
        }

        $out .= "\n";

        $notice = $this->visibilityNotice($discussion, $actor);

        if ($notice !== null) {
            $out .= '> '.$notice."\n\n";
        }

        $flat = $this->tree->flatten($this->tree->build($posts));

        if ($flat === []) {
            $out .= "*No comments are visible to you in this discussion.*\n";

            return $out;
        }

        // Post id => number, so a reply can name the post it answers the way a
        // reader would ("#12") rather than by an id that means nothing here.
        $numbers = [];

        foreach ($flat as $node) {
            $numbers[(int) $node->post->id] = (int) $node->post->number;
        }

        foreach ($flat as $node) {
            $out .= $this->post($node, $actor, $request, $numbers);
        }

        return $out;
    }

    private function discussionMeta(
        Discussion $discussion,
        User $actor,
        string $url,
        string $markdownUrl
    ): string {
        $out = '';

        $out .= '- **Discussion ID**: '.$discussion->id."\n";
        $out .= '- **Author**: '.$this->user($actor, $discussion->user)."\n";
        $out .= '- **Created**: '.$this->time($discussion->created_at)."\n";

        if ($discussion->last_posted_at && ! $discussion->last_posted_at->equalTo($discussion->created_at)) {
            $out .= '- **Last activity**: '.$this->time($discussion->last_posted_at)."\n";
        }

        $score = $this->score($discussion);

        if ($score !== null) {
            $out .= '- **Score**: '.$this->signed($score)."\n";
        }

        $out .= '- **URL**: '.$url."\n";
        $out .= '- **Markdown**: '.$markdownUrl."\n";

        return $out;
    }

    private function post(ThreadNode $node, User $actor, ServerRequestInterface $request, array $numbers): string
    {
        $post = $node->post;
        $number = (int) $post->number;

        $out = "\n---\n\n";

        // The heading carries the post number, who wrote it and when, so each
        // post is identifiable on its own even when quoted out of context.
        $out .= '## Post #'.$number.' — '.$this->user($actor, $post->user)."\n\n";

        $meta = [];

        $meta[] = '- **Posted**: '.$this->time($post->created_at);

        if ($post->edited_at) {
            $meta[] = '- **Edited**: '.$this->time($post->edited_at);
        }

        // The explicit parent reference is the part a model can rely on. Depth
        // alone would be ambiguous once a post is quoted on its own.
        if ($node->depth > 0 && $post->parent_id) {
            $meta[] = '- **In reply to**: post #'.$this->numberOf($numbers, (int) $post->parent_id);
        }

        if ($node->depth > 0) {
            $meta[] = '- **Reply depth**: '.$node->depth;
        }

        $score = $this->score($post);

        if ($score !== null) {
            $meta[] = '- **Score**: '.$this->signed($score);
        }

        $replies = (int) ($post->reply_count ?? 0);

        if ($replies > 0) {
            $meta[] = '- **Replies to this comment**: '.$replies;
        }

        $out .= implode("\n", $meta)."\n\n";

        if ($post->hidden_at) {
            $out .= "*This comment was hidden by a moderator.*\n\n";
        }

        $out .= $this->body($post, $actor, $request)."\n";

        return $out;
    }

    /**
     * A hidden comment's text is withheld from anyone who cannot moderate,
     * mirroring what BasicPostSerializer does for the HTML page. Emitting it
     * here would hand out content the site itself refuses to show.
     */
    private function body(Post $post, User $actor, ServerRequestInterface $request): string
    {
        if ($post->hidden_at
            && ! $actor->can('edit', $post)
            && ! $actor->hasPermission('discussion.hidePosts')
        ) {
            return '*[This comment was removed]*';
        }

        return $this->html->convert($post->formatContent($request));
    }

    /**
     * The number of the post a reply points at, so the reference reads as
     * `#12` rather than a bare id. Falls back to the id if the parent is not
     * among the posts this reader can see.
     *
     * @param array<int, int> $numbers
     */
    private function numberOf(array $numbers, int $parentId): int
    {
        return $numbers[$parentId] ?? $parentId;
    }

    /**
     * @param array<int, int> $numbers post id => number, for reply references
     */
    private function score($model): ?int
    {
        // itqan/flarum-discussions owns this column. On a forum without it the
        // attribute is simply absent, and an invented 0 would be a lie about
        // how the community rated the post.
        $value = $model->getAttribute('votes');

        return $value === null ? null : (int) $value;
    }

    /**
     * Why this thread may look different on the site than it does here, or null
     * if it looks the same for everyone.
     *
     * Two different things, kept apart because they are two different states:
     * flarum/approval sets `is_approved` while a discussion waits for a
     * moderator, and `hidden_at` is set once a discussion is hidden, whether
     * that was a rejection or a later moderation action. Reading `hidden_at` and
     * calling it "awaiting approval" mislabels every hidden thread.
     */
    private function visibilityNotice(Discussion $discussion, User $actor): ?string
    {
        if ($actor->hasPermission('discussion.hidePosts')) {
            return null;
        }

        // Present only when flarum/approval is installed, which is when the
        // column exists. Without it the attribute is absent, not false.
        $isApproved = $discussion->getAttribute('is_approved');

        if ($isApproved === null) {
            return $discussion->hidden_at
                ? 'This discussion has been hidden and is not visible to everyone yet.'
                : null;
        }

        if (! $isApproved) {
            return 'This discussion is awaiting approval and is not visible to everyone yet.';
        }

        return $discussion->hidden_at
            ? 'This discussion has been hidden and is not visible to everyone yet.'
            : null;
    }

    private function user(User $actor, $user): string
    {
        if (! $user) {
            return 'Deleted user';
        }

        $name = $user->display_name ?: $user->username;

        return $user->username ? $name.' (@'.$user->username.')' : $name;
    }

    private function tags(Discussion $discussion): array
    {
        if (! $discussion->relationLoaded('tags')) {
            return [];
        }

        $names = [];

        foreach ($discussion->tags as $tag) {
            if (isset($tag->name)) {
                $names[] = $tag->name;
            }
        }

        return array_values(array_unique($names));
    }

    private function language(Discussion $discussion): ?string
    {
        if (! $discussion->relationLoaded('language') || ! $discussion->language) {
            return null;
        }

        $name = $discussion->language->name ?? null;

        return $name ? (string) $name : null;
    }

    private function time($value): string
    {
        return $value ? $value->toIso8601String() : 'unknown';
    }

    private function signed(int $value): string
    {
        return $value > 0 ? '+'.$value : (string) $value;
    }

    /**
     * A title can contain a newline, which would break the single H1 the
     * llms.txt format expects at the top of a document.
     */
    private function heading(string $title): string
    {
        return trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
    }
}

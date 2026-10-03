<?php

namespace Itqan\Llms\Controller;

use Flarum\Discussion\DiscussionRepository;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Itqan\Llms\Markdown\DiscussionRenderer;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves the Markdown version of a discussion at `/d/{id-slug}.md`.
 *
 * Access follows the reader: the discussion is loaded through
 * `findOrFail($id, $actor)` and the comments through `whereVisibleTo($actor)`,
 * so a private or tag-gated thread yields a 404 to a guest and its Markdown to
 * a member who may read it. The `.md` URL is not a way around permissions.
 */
class MarkdownDiscussionController implements RequestHandlerInterface
{
    /**
     * @var DiscussionRepository
     */
    protected $discussions;

    /**
     * @var DiscussionRenderer
     */
    protected $renderer;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    public function __construct(
        DiscussionRepository $discussions,
        DiscussionRenderer $renderer,
        SettingsRepositoryInterface $settings
    ) {
        $this->discussions = $discussions;
        $this->renderer = $renderer;
        $this->settings = $settings;
    }

    public function handle(Request $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = $this->discussionId($request);

        if ($id === null) {
            return $this->error('Discussion not found.', 404);
        }

        try {
            $discussion = $this->discussions->findOrFail($id, $actor);
        } catch (ModelNotFoundException $e) {
            // Only "no such discussion". Catching \Throwable here would also
            // swallow a QueryException and answer a database outage with a 404,
            // which reads as a missing thread rather than an outage and hides it
            // from monitoring.
            return $this->error('Discussion not found.', 404);
        }

        // Eager, not per-model: `loadMissing()` in a loop issues one query per
        // post, which on a long thread is the dominant cost of the request.
        $posts = $discussion->posts()
            ->whereVisibleTo($actor)
            ->where('type', 'comment')
            ->with('user')
            ->orderBy('number')
            ->get();

        $this->loadOptionalRelations($discussion);

        $markdown = $this->renderer->render($discussion, $posts->all(), $actor, $request);

        $lastModified = $this->lastModified($discussion, $posts);
        $etag = $this->etag($discussion, $posts, $lastModified);

        // A crawler refetching an unchanged thread gets a 304 instead of the
        // whole thread re-rendered. Both validators are checked because a client
        // may send either.
        if ($this->matches($request, $etag, $lastModified)) {
            return (new Response)
                ->withStatus(304)
                ->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', 'private, max-age=0, must-revalidate')
                ->withHeader('Vary', 'Cookie, Accept-Encoding');
        }

        $response = new Response;
        $response->getBody()->write($markdown);

        return $response
            ->withHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->withHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->withHeader('Vary', 'Cookie, Accept-Encoding')
            ->withHeader('ETag', $etag)
            ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified).' GMT');
    }

    /**
     * Load the relations the renderer reads when the extension that defines
     * them is actually enabled.
     *
     * `tags` and `language` are added by flarum/tags and
     * fof/discussion-language through Extend\Model(). Without them,
     * `loadMissing` throws RelationNotFoundException and every .md request 500s
     * — so an extension that claims to work without them has to check first.
     *
     * The check is whether the extension is enabled, read from the same
     * `extensions_enabled` setting Flarum's own ExtensionManager uses. Asking
     * the model instead — method_exists($discussion, 'tags') — is always false:
     * Extend\Model() does not add a method, it puts a callback in
     * AbstractModel::$customRelations. That silently dropped Tags and Language
     * from every export, on every forum, including the ones where the
     * extensions were running.
     */
    private function loadOptionalRelations($discussion): void
    {
        $available = ['user'];

        foreach (['tags' => 'flarum-tags', 'language' => 'fof-discussion-language'] as $relation => $extension) {
            if ($this->isEnabled($extension)) {
                $available[] = $relation;
            }
        }

        $discussion->loadMissing($available);
    }

    /**
     * Whether an extension is enabled, read from the same setting Flarum's own
     * ExtensionManager uses.
     */
    private function isEnabled(string $id): bool
    {
        $enabled = $this->settings->get('extensions_enabled');

        if (is_string($enabled)) {
            $enabled = json_decode($enabled, true);
        }

        return is_array($enabled) && in_array($id, $enabled, true);
    }

    /**
     * The middleware hands the slug over as `id`, e.g. `42-my-title`.
     */
    private function discussionId(Request $request): ?int
    {
        $value = $request->getQueryParams()['id'] ?? $request->getAttribute('routeParameters')['id'] ?? null;

        if ($value === null) {
            return null;
        }

        $id = (int) explode('-', (string) $value)[0];

        return $id > 0 ? $id : null;
    }

    private function lastModified($discussion, $posts): int
    {
        $latest = $discussion->last_posted_at ?: $discussion->created_at;

        foreach ($posts as $post) {
            $edited = $post->edited_at ?: $post->created_at;

            if ($edited && $edited->getTimestamp() > $latest->getTimestamp()) {
                $latest = $edited;
            }
        }

        return $latest ? $latest->getTimestamp() : time();
    }

    /**
     * A validator over everything the rendered document depends on.
     *
     * Vote scores are part of it: the Markdown states them, so a score change
     * changes the body even though no post was edited. Leaving them out would
     * let a revalidated thread keep serving a stale score indefinitely.
     */
    private function etag($discussion, $posts, int $lastModified): string
    {
        $parts = [
            $discussion->id,
            $lastModified,
            $posts->count(),
        ];

        foreach ($posts as $post) {
            $parts[] = $post->id.':'.$post->votes.':'.$post->hidden_at.':'.$post->reply_count;
        }

        $parts[] = 'd:'.$discussion->votes.':'.$discussion->hidden_at.':'.($discussion->comment_count ?? 0);

        return '"'.sha1(implode('|', $parts)).'"';
    }

    /**
     * Whether the client already holds this representation.
     *
     * RFC 7232 says If-None-Match takes precedence: when it is present it is
     * the only validator that counts, and If-Modified-Since must be ignored even
     * if the tag does not match. Falling back to the date after a failed tag
     * match would serve a 304 to a client whose ETag is stale — and since the
     * ETag covers vote scores, that means a client that revolved after a vote
     * would keep getting the old score back.
     */
    private function matches(Request $request, string $etag, int $lastModified): bool
    {
        $ifNoneMatch = $request->getHeader('If-None-Match');

        if ($ifNoneMatch !== []) {
            foreach ($ifNoneMatch as $header) {
                foreach (array_map('trim', explode(',', $header)) as $candidate) {
                    // `W/"..."` is a weak validator; it still identifies the body.
                    $candidate = preg_replace('/^W\//', '', $candidate);

                    if ($candidate === '*' || $candidate === $etag) {
                        return true;
                    }
                }
            }

            // Present and not matching. The date is deliberately not consulted.
            return false;
        }

        $since = $request->getHeaderLine('If-Modified-Since');

        if ($since !== '') {
            $parsed = strtotime($since);

            if ($parsed !== false && $parsed >= $lastModified) {
                return true;
            }
        }

        return false;
    }

    private function error(string $message, int $status): ResponseInterface
    {
        $response = new Response;
        $response->getBody()->write("# Not found\n\n".$message."\n");

        // The requested resource was Markdown, so the error is Markdown too.
        return $response
            ->withHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->withStatus($status);
    }
}

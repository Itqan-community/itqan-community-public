<?php

namespace Itqan\Llms\Controller;

use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Itqan\Llms\Support\ForumUrls;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves `/llms.txt`, following https://llmstxt.org.
 *
 * The format is deliberately narrow so it can be parsed rather than guessed at:
 *
 *   - one H1 with the site name, the only required element
 *   - a blockquote holding the short summary
 *   - optional prose, containing no headings
 *   - zero or more H2 sections, each a list of `- [name](url): notes` links
 *
 * The index points at the `.md` version of each discussion, which is the whole
 * point: the detail lives behind those links and is fetched only when needed.
 */
class LlmIndexController implements RequestHandlerInterface
{
    /**
     * Discussions per section. Enough to be useful, small enough to stay well
     * inside a context window once read alongside the sections.
     */
    private const PER_SECTION = 20;

    /**
     * Sections emitted before the rest are folded into `## Optional`. This
     * forum is tagged, so the tag sections come first and the flat recent list
     * is the part an agent can skip.
     */
    private const PRIMARY_SECTIONS = 6;

    /**
     * @var DiscussionRepository
     */
    protected $discussions;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var ForumUrls
     */
    protected $urls;

    public function __construct(
        DiscussionRepository $discussions,
        SettingsRepositoryInterface $settings,
        ForumUrls $urls
    ) {
        $this->discussions = $discussions;
        $this->settings = $settings;
        $this->urls = $urls;
    }

    public function handle(Request $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        $title = trim($this->plain((string) $this->settings->get('forum_title', 'Forum')));
        $description = trim($this->plain((string) $this->settings->get('forum_description', '')));

        $out = '# '.($title !== '' ? $title : 'Forum')."\n\n";

        $summary = $description !== ''
            ? $description
            : 'A community forum. Each discussion below links to a Markdown version of the full thread.';

        $out .= '> '.$summary."\n\n";

        $out .= "Every discussion listed here is also available as Markdown at the same URL with "
            ."`.md` appended. The Markdown version carries the full thread with its reply structure, "
            ."the discussion's tags, and the score each comment received. Follow the `.md` link to read "
            ."one thread.\n\n";

        $query = $this->discussions->query()
            ->whereVisibleTo($actor)
            // `tags` has to be eager loaded: byTag() reads it through
            // relationLoaded(), and without it every discussion silently falls
            // into the "Other discussions" bucket instead of its own section.
            //
            // It is conditional because EagerLoading::eagerLoadRelation() throws
            // for a relation the model does not define, and the relation only
            // exists once flarum/tags' extender has run. Asking for it on a
            // forum without tags would 500 the index rather than degrade.
            ->with(array_values(array_filter(
                ['user', $this->isEnabled('flarum-tags') ? 'tags' : null]
            )))
            ->orderBy('last_posted_at', 'desc')
            ->limit(self::PER_SECTION * 3);

        $recent = $query->get();

        $sections = $this->byTag($recent);

        // A forum with no tags, or one where the recent sample happened to miss
        // every tag, still gets a usable index.
        if ($sections === []) {
            $sections = ['Recent discussions' => $recent->all()];
        }

        $primary = array_slice($sections, 0, self::PRIMARY_SECTIONS, true);
        $optional = array_slice($sections, self::PRIMARY_SECTIONS, null, true);

        if ($optional !== []) {
            $optional['Recent discussions'] = $recent->all();
        }

        foreach ($primary as $heading => $discussions) {
            $out .= $this->section((string) $heading, $discussions);
        }

        if ($optional !== []) {
            // One flat list rather than a heading per tag. A section's content is
            // a file list, and stacking several lists under a single H2 with
            // nothing to separate them leaves an agent unable to tell where one
            // ends and the next begins.
            $flat = [];

            foreach ($optional as $discussions) {
                foreach ($discussions as $discussion) {
                    $flat[] = $discussion;
                }
            }

            $out .= "\n## Optional\n\n";
            $out .= "Secondary listings, flattened into one. An agent with a short context budget can skip this section.\n\n";
            $out .= $this->links($flat);
        }

        $response = new Response;
        $response->getBody()->write($out);

        return $response
            // The spec treats llms.txt as a Markdown file served as plain text.
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            // Not `public`: the index is built with `whereVisibleTo($actor)`, so
            // a shared cache would hand a tag-gated discussion to a guest who
            // may not read it.
            ->withHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->withHeader('Vary', 'Cookie, Accept-Encoding')
            ->withStatus(200);
    }

    /**
     * Whether an extension is enabled, read from the same setting Flarum's own
     * ExtensionManager uses.
     *
     * Preferred over asking the model whether a relation method exists, because
     * that only reflects extenders which have already run, and the answer has to
     * be the same one the rest of the forum is working from.
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
     * @param mixed[] $discussions
     */
    private function section(string $heading, array $discussions): string
    {
        return "\n## ".$heading."\n\n".$this->links($discussions);
    }

    /**
     * @param mixed[] $discussions
     */
    private function links(array $discussions): string
    {
        $out = '';

        foreach ($discussions as $discussion) {
            $url = $this->urls->toDiscussionMarkdown($discussion);
            $name = $this->plain($discussion->title);

            $notes = [];

            $author = $discussion->user ? $discussion->user->display_name : null;

            if ($author) {
                $notes[] = 'by '.$this->plain($author);
            }

            // The score is the community's own signal for what was worth
            // reading, so it is worth handing on. Absent without
            // itqan/flarum-discussions, and omitted rather than faked.
            $votes = $discussion->getAttribute('votes');

            if ($votes !== null) {
                $notes[] = 'score '.(int) $votes;
            }

            $replies = (int) ($discussion->comment_count ?? 0);

            if ($replies > 0) {
                $notes[] = $replies === 1 ? '1 comment' : $replies.' comments';
            }

            if ($discussion->last_posted_at) {
                $notes[] = 'last active '.$discussion->last_posted_at->toIso8601String();
            }

            $suffix = $notes === [] ? '' : ': '.implode(', ', $notes);

            $out .= '- ['.($name !== '' ? $name : 'Discussion '.$discussion->id).']('.$url.')'.$suffix."\n";
        }

        return $out === '' ? "*Nothing here yet.*\n" : $out;
    }

    /**
     * Group the recent discussions by their first tag, so the index is a
     * guided path into the forum rather than one undifferentiated list.
     *
     * @param \Illuminate\Support\Collection $recent
     * @return array<string, mixed[]>
     */
    private function byTag($recent): array
    {
        $sections = [];

        foreach ($recent as $discussion) {
            $tag = null;

            if ($discussion->relationLoaded('tags')) {
                foreach ($discussion->tags as $candidate) {
                    if (isset($candidate->name)) {
                        $tag = $this->plain($candidate->name);
                        break;
                    }
                }
            }

            $key = $tag !== null && $tag !== '' ? $tag : 'Other discussions';

            $sections[$key] ??= [];

            if (count($sections[$key]) < self::PER_SECTION) {
                $sections[$key][] = $discussion;
            }
        }

        return $sections;
    }

    /**
     * Forum titles and tag names are plain text, but `forum_description` is
     * entered as HTML in the admin panel. Both have to be reduced to one line
     * of safe text: a newline would break the H1, and raw tags would be
     * nonsense in a Markdown file.
     */
    private function plain(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}

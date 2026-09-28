# Itqan LLMs

Publishes the forum to LLM crawlers: every discussion is available as clean
Markdown at the same URL with `.md` appended, and `/llms.txt` indexes them
following [llmstxt.org](https://llmstxt.org).

Built for this forum rather than ported from a generic Flarum extension, so it
understands the two things that shape a thread here — the vote score from
`itqan/flarum-discussions` and the nested reply tree in `posts.parent_id`.

## What it adds

| URL | What it serves |
| --- | --- |
| `/d/123-my-title.md` | The whole thread as Markdown, replies in conversation order |
| `/llms.txt` | The index, grouped by tag |
| `<link>` in `<head>` | `rel="alternate"` to the Markdown, `rel="describedby"` to the index |

## Install

The package is a local path repository, so it is already wired into the root
`composer.json`:

```bash
composer require itqan/flarum-llms:@dev
php flarum extension:enable itqan-llms
```

It works without `itqan/flarum-discussions`, `flarum/tags` or
`fof/discussion-language`; those only add the score, the per-tag grouping and the
language respectively. Both optional relations are checked before they are
loaded, because a relation the model does not define makes Eloquent throw
`RelationNotFoundException` — without the check, a forum without those
extensions would get a 500 on every Markdown request rather than a plainer
export.

## What a thread looks like

```markdown
# Best way to learn PHP 8?

- **Discussion ID**: 1
- **Author**: Amina (@amina)
- **Created**: 2026-01-01T10:00:00+00:00
- **Score**: +12
- **URL**: https://community.example/d/1-best-way-to-learn-php-8
- **Markdown**: https://community.example/d/1-best-way-to-learn-php-8.md
- **Tags**: Programming

---

## Post #1 — Amina (@amina)

- **Posted**: 2026-01-01T08:30:00+00:00
- **Score**: +4

I keep coming back to **PHP 8**. The manual is at
<https://www.php.net/manual/en/>.

---

## Post #3 — Yusuf (@yusuf)

- **Posted**: 2026-01-03T09:12:00+00:00
- **In reply to**: post #2
- **Reply depth**: 1
- **Score**: +2
- **Replies to this comment**: 1

Does it cover enums?
```

Replies carry `In reply to` and `Reply depth`, and are emitted directly beneath
the comment they answer.

## Three things this gets right that a naive port does not

**The Markdown is real Markdown.** `$post->content` is not Markdown and cannot
be used. That accessor runs the stored TextFormatter XML back through
`s9e\TextFormatter\Unparser::unparse()`, which is
`html_entity_decode(strip_tags($xml))`. Every link target, list, code fence and
paragraph break is destroyed:

```
<p>See <a href="https://example.com/a">the docs</a>.</p><p>first</p>
  ->  "See the docs.first"
```

`HtmlToMarkdown` renders the post with Flarum's own formatter and converts the
resulting HTML, so links, fenced code with its language, nested lists,
blockquotes, tables and images survive.

**The reply tree is preserved.** Reading a thread in `number` order shreds a
conversation: a reply to post #2 written after post #50 sorts at #50, so the
answer arrives separated from its question by unrelated comments.
`ThreadTree` walks `parent_id` depth-first instead.

**`.md` is served by middleware, not a route.** Core's discussion route is
`/d/{id:\d+(?:-[^/]*)?}` and `[^/]*` matches dots, so for
`/d/123-my-title.md` it matches with `id` = `123-my-title.md`. Core registers
first and wins, so an extension route never fires — verified in
`tests/markdown_route_test.php` with the same FastRoute dispatcher Flarum uses.
`MarkdownDiscussionMiddleware` runs ahead of the router instead.

## Access control

The `.md` URL is not a way around permissions. The discussion is loaded through
`findOrFail($id, $actor)` and the comments through `whereVisibleTo($actor)`, so
a private or tag-gated thread 404s for a guest and renders for a member who may
read it.

The text of a hidden comment is withheld from anyone who cannot moderate,
mirroring `BasicPostSerializer`. The post still appears, marked, so the thread
reads as a complete transcript.

## Caching

Neither response is publicly cacheable. Both are built with
`whereVisibleTo($actor)`, so a shared cache could hand a tag-gated discussion to
a guest who may not read it; both send `private` and `Vary: Cookie` instead.

A thread's Markdown carries an `ETag` and a `Last-Modified`, and both request
validators are handled: a crawler that sends `If-None-Match` or
`If-Modified-Since` for an unchanged thread gets a `304` with no body.

The `ETag` covers the post ids, their vote scores, their hidden state and their
reply counts, not just timestamps. The Markdown states every score, so a vote
change changes the body even though no post was edited; a validator over
timestamps alone would let a revalidated thread keep serving a stale score
indefinitely.

## Tests

Plain PHP scripts — no database, no Flarum boot, no PHPUnit. Run them all:

```bash
php packages/itqan-llms/tests/run_all.php
```

| Script | Covers |
| --- | --- |
| `html_to_markdown_test.php` | The converter, and the `strip_tags` regression it exists to prevent |
| `thread_tree_test.php` | Reply ordering, orphan parents, self-parents, cycles, runaway depth |
| `discussion_render_test.php` | A real `Post` through Flarum's formatter into Markdown |
| `llms_index_test.php` | The llms.txt structure, HTML descriptions, `## Optional` |
| `alternate_links_test.php` | The `alternate` and `describedby` head links |
| `forum_urls_test.php` | URL building against the real generator and both slug drivers |
| `markdown_route_test.php` | Why a route cannot serve `.md`, and what the middleware claims |
| `conditional_request_test.php` | `ETag`/`Last-Modified`, both request validators, vote busts the tag |
| `optional_relations_test.php` | The `RelationNotFoundException` a forum without tags used to get |
| `container_wiring_test.php` | Every class resolves through the real service provider |

## License

MIT.

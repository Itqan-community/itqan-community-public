# Nested Replies for Flarum

Restyles Flarum discussions as nested reply threads: indented replies with depth-colored
thread lines, collapsible comments, an optional vote rail, and admin settings.

See [ARCHITECTURE.md](ARCHITECTURE.md) for how the extension is put together.

## Features

- Threaded comment cards with indentation and depth-colored guide lines.
- Collapse/expand a comment; descendants are hidden while collapsed.
- Native up/down voting with a score rail, stored by this extension. Signed-in
  users can upvote, downvote, or clear their vote.
- Reply sorting per discussion: oldest, newest, top voted, or most replies.
- Reply parent stored explicitly per reply (the post whose Reply button was clicked),
  so quoting several people never changes the thread structure.
- Optional "Reply to {username}" header tag, like color, and more via admin settings.
- Replies are composed inside the card, indented under the post being replied to
  (quick textarea or full embedded composer, per admin setting).
- Optional hide for the discussion scrubber.

## Requirements

- PHP 8.1+ and Flarum `^1.8 || ^2.0`.

## Dependent packages

| Package | Required | Why |
| --- | --- | --- |
| `flarum/core` | Yes | Provides the extension API, post stream, and `PostSerializer`. |
| `flarum/mentions` | No | Optional. Its per-post Reply button is reused when present; otherwise this extension renders one. Threading never depends on mentions. |
| `flarum/likes` | No | Adds the Like action that this theme restyles and colors. Without it, there is simply no Like chip to theme; voting from this extension is unaffected. |

Voting is built into this extension and needs no other package.

## Installation

```bash
composer require mtareq/flarum-nested-replies
```

Then enable the extension in the admin panel and configure it under **Nested Replies**.

## Settings

Configure everything under **Administration → Extensions → Nested Replies**. The
settings are stored with the `mtareq-nested-replies.` prefix and serialized to the
forum as `nestedReplies*` attributes.

| Setting | Key | Type | Default | Description |
| --- | --- | --- | --- | --- |
| Enable nested replies | `mtareq-nested-replies.enabled` | Boolean | `on` | Master switch. When off, the post stream renders with Flarum's default layout. |
| Maximum indent depth | `mtareq-nested-replies.max_depth` | Number | `5` | Deepest indent level a reply is drawn at. Replies past this depth are shown at the cap, keeping deep threads readable. |
| Show vote rail | `mtareq-nested-replies.show_votes` | Boolean | `on` | Shows the up/down vote rail in each post's action bar. |
| Show "Reply to" tag | `mtareq-nested-replies.show_reply_tag` | Boolean | `on` | Shows a "Reply to {username}" tag in the header and hides the inline mention that points at the stored parent. |
| Show "replied to this" indicator | `mtareq-nested-replies.show_replied_indicator` | Boolean | `on` | Shows flarum/mentions' "You replied to this." summary above the post. Turn off to hide it. |
| Like color (active) | `mtareq-nested-replies.like_color` | Color | `#ff4500` | Color of the Like action once a post is liked (requires `flarum/likes`). |
| Start discussions at the first post | `mtareq-nested-replies.start_at_first_post` | Boolean | `on` | Opens a discussion at the original post instead of jumping to the first unread post. Search-result jumps are preserved. |
| Show discussion scrubber | `mtareq-nested-replies.show_scrubber` | Boolean | `on` | Shows the discussion scrubber (Original Post / N of M / unread / Now). Turn off to hide it on desktop and mobile. |
| Reply form | `mtareq-nested-replies.reply_form` | Select | `Quick reply` | `Quick reply` renders a lightweight textarea with basic formatting and preview inside the card. `Full composer` embeds Flarum's full reply composer inside the card. |
| New reply highlight color | `mtareq-nested-replies.highlight_color` | Color | `#00c853` | Color of the brief fade-in highlight shown on a reply the reader just posted, so they can spot it. |
| Nest legacy replies | `mtareq-nested-replies.legacy_mentions` | Boolean | `off` | For posts created before explicit parents existed (no stored parent), derive the parent from a **leading** post mention and nest one level. Posts whose mention is embedded in text, or that mention several posts, stay top-level. |

## Manual test matrix

Verify on a real Flarum install across these combinations:

| Flarum | mentions | Expected |
| --- | --- | --- |
| 2.x | on | Indented tree with depth-colored thread lines |
| 2.x | off | Nested threading works; this extension supplies the per-post Reply action |
| 1.x | on | Same as 2.x equivalent |
| 1.x | off | Nested threading works; this extension supplies the per-post Reply action |

Repeat the matrix with **Reply form = Quick reply** and **Reply form = Full composer**, and with **Show discussion scrubber** on and off. For each reply form, check: Reply on the original post adds a top-level reply; Reply on a reply nests under it; only one form is open at a time; a posted reply appears without a reload; guests still get the login prompt.

For **Reply form = Full composer**, also verify on a real install: the normal fixed composer still works after using an in-card composer (new discussion / edit post); a composer-mode reply scrolls and refreshes correctly on a long thread; rapid switching between reply targets behaves; and a failed composer load shows the loading fallback with a working Cancel.

Votes are available to signed-in users on every supported version; guests see
the rail disabled.

## Development

```bash
cd js
npm install
npm run dev      # watch
npm run build    # production
npm test         # unit tests for pure logic
npm run format-check
```

## License

MIT

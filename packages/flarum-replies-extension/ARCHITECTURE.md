# Architecture

How the Nested Replies extension is put together. Read this before changing the
layout logic, the settings pipeline, or the voting API.

## Overview

The extension has two halves that meet over Flarum's normal extension APIs:

- **PHP backend** — declares settings, serializes them to the forum, exposes a
  vote endpoint, and adds `votes` / `userVote` to every serialized post.
- **JS frontend** — reads the serialized settings, derives reply depth from a
  stored reply-parent link, restyles the post stream into cards, and renders the
  vote and collapse controls.

The reply tree is stored explicitly: the `mtareq_nested_replies_parents` table maps a
reply post to the post it answers. The frontend renders depth from that link, and the
extension supplies its own per-post Reply action when `flarum/mentions` is not installed.

## Package layout

```
composer.json                     Flarum extension manifest (name, namespace, icon)
extend.php                        Backend registration (assets, settings, routes, serializer)
src/
  PostVote.php                    Eloquent model for the votes table
  PostReply.php                   Eloquent model for the reply-parent table
  Listener/
    StoreReplyParent.php          Persists replyToPostId on post creation
  Api/VotePostController.php      POST vote endpoint
migrations/
  2026_09_18_000000_create_nested_replies_votes_table.php
  2026_09_19_000000_create_nested_replies_parents_table.php
locale/
  en.yml, ar.yml                  Admin + forum translations
less/
  forum.less                      Card layout, thread lines, action bar, RTL
js/
  admin.js, forum.js              Webpack entry points
  src/
    admin/index.js                Registers admin settings
    forum/index.js                Orchestrates the whole forum UI
    forum/components/
      VoteRail.js                 Up/down vote control
      CollapseToggle.js           Collapse/expand control
      MoreReplies.js              "Show more replies" control for folded siblings
    forum/utils/threadDepths.js   Pure reply-tree / depth logic
    common/settings.js            Reads serialized forum settings
    common/voteAdapter.js         Vote read/write adapter
  dist/                           Built bundles loaded by Flarum
```

## Backend

### `extend.php`

- Registers the compiled `js/dist/forum.js` + `less/forum.less` on the forum and
  `js/dist/admin.js` on the admin.
- Declares every setting with a default and serializes the relevant ones to the
  forum under `nestedReplies*` keys (see [Settings pipeline](#settings-pipeline)).
- Registers `POST /mtareq-nested-replies/posts/{id}/vote`.
- Extends `Flarum\Api\Serializer\PostSerializer` with two attributes:
  - `votes` — sum of the post's vote values.
  - `userVote` — `'up'`, `'down'`, or `null` for the current actor.

### Reply parent and serialization

- The `Flarum\Post\Event\Saving` listener reads `attributes.replyToPostId` and stores
  it after save; serialization branches on `class_exists(PostResource)` to support 1.x
  (`ApiSerializer`) and 2.x (`ApiResource` fields), including the `votes`/`userVote` port.

### `src/PostVote.php` + migration

A thin model over `mtareq_nested_replies_votes` with `post_id`, `user_id`, and
`value` (`1` / `-1`), unique on `(post_id, user_id)` and indexed on each column.

### `src/Api/VotePostController.php`

Requires a registered actor, then:

- `direction = 'up' | 'down'` → `updateOrCreate` the actor's vote row.
- anything else (`null`) → delete the actor's vote row (clears the vote).

It returns the post serialized through `PostSerializer`, so the updated `votes`
and `userVote` attributes flow back to the client in the response.

## Frontend

### Boot and settings

`js/src/forum/index.js` runs inside an initializer. It calls `readSettings(app)`
(`common/settings.js`) and bails out entirely when `enabled` is off.

`settings.js` reads attributes from `app.forum` once booted, and otherwise falls
back to the initial JSON:API payload (`app.data.resources`) because Flarum runs
initializers before `app.forum` is assigned.

When `flarum/mentions` is present, the initializer also blanks the **post**
mentionable's `initialResults()`/`search()`, so the `@` autocomplete suggests users
only. Programmatic post mentions (quoting) still work, and the reply parent always
comes from the post whose Reply button was clicked.

### Reply tree (`forum/utils/threadDepths.js`)

Pure, dependency-injected functions — the only part covered by fast unit tests:

- `getParentId(post, legacyMentions)` — reads the stored `replyToPostId` attribute;
  returns null for top-level posts. With `legacyMentions` on and no stored parent, falls
  back to `getLeadingMentionId(post)` (a post mention at the very start of the content),
  which re-nests pre-existing replies. `isDerivedParent` reports that case.
- `getDepth(post, maxDepth, lookup, legacyMentions)` — walks parents, never counting the
  original post as a level, and caps at `maxDepth`. Cycle-safe. A legacy-derived link is
  capped at one level and never stacks.
- `getAncestorIds` / `isHidden` — used to hide descendants of a collapsed post.
- `getReplyTarget(post, getPostById, legacyMentions)` — resolves the parent and its author
  for the header tag.
- `planSiblingFolding(posts, options)` — given the replies in render order, works out
  which sibling groups fold, the hidden ids (folded replies and their subtrees), and
  where each "Show more replies" control anchors so it lines up with the depth of the
  hidden replies.

### Stream regrouping (`forum/index.js`)

Flarum's post stream is a flat list. The extension `override`s
`PostStream.prototype.view` to wrap the original post in a
`NestedRepliesThreadCard` and the replies in a `NestedRepliesReplyCard`,
without mutating core state.

`Post` lifecycle hooks (`oncreate` / `onupdate`) call `decorate()`, which adds
the `NestedRepliesPost` class, computes each post's `data-depth` and CSS
`--depth`, tags stream items as `is-top-level` / `is-nested`, and applies the
collapsed/hidden state.

`CommentPost.prototype.headerItems` adds the "Reply to" tag, and
`Post.prototype.actionItems` injects the vote rail and collapse toggle into the
action bar.

### Sorting

`buildReplyOrder(posts, mode)` sorts sibling groups and walks depth-first so
children always follow their parent. `oldest` uses Flarum's native stream; the
other modes (newest, top, replies) first fetch every page of the discussion
(`fetchAllPosts`) and pause native pagination for the sorted view.

### Fold state

Replies are unfolded by default. Two independent mechanisms hide content:

- **Manual collapse** — an in-memory `Set` of post ids. `isHidden` hides any post
  with a collapsed ancestor; the `CollapseToggle` flips it.
- **Sibling folding** — `planSiblingFolding` runs on each stream render. When a
  reply has more direct replies than `visibleReplies`, only the first few stay
  visible and the rest (with their subtrees) are hidden behind a `MoreReplies`
  control. The control is anchored to the last post of the kept branch and
  indented to the depth of the hidden replies. `expandedGroups` remembers groups
  the reader has opened.

A manual `forceRedraw()` invalidates mounted `Post` subtrees so Flarum 1.x
rebuilds without a full page reload.

### Components

- `VoteRail` — reads `score` / `current` from the `voteAdapter`, disables itself
  for guests, and toggles a vote off when the active direction is clicked again.
- `CollapseToggle` — icon button that flips the collapsed state via callback.
- `MoreReplies` — "Show more replies" pill for a folded sibling group; reveals the
  group via callback and lines up with the hidden replies using a depth delta.
- `NestedRepliesInlineReply` — host for the in-card reply form. Renders the quick
  editor or, in `composer` mode, core's loaded `ReplyComposer` body. `index.js`
  intercepts `.item-reply` clicks (capture phase) and records the target in
  `inlineReply`; the target post's footer renders the host indented one level.
  A successful post clears the form and invalidates the cached `allPosts` so the
  tree refreshes. In composer mode the fixed `Composer` shell is suppressed.
- `NestedRepliesQuickReply` — plain textarea with bold/italic/quote/link (pure
  `forum/utils/markdownFormat.js`) and a Write/Preview toggle that renders
  `ComposerPostPreview`.

### Styling (`less/forum.less`)

Cards, depth-colored guide lines (drawn as stacked background gradients per
`data-depth`), the inline action bar, the vote rail, the reply tag, and RTL
mirroring. The active Like color is driven by the `--nested-replies-like`
CSS variable, set from the `like_color` setting. When the scrubber setting is off,
`index.js` adds `NestedRepliesHideScrubber` to the root element and `forum.less`
hides `.PostStreamScrubber`.

## Settings pipeline

```
extend.php default()                 -> stored setting (mtareq-nested-replies.*)
extend.php serializeToForum()        -> forum attribute (nestedReplies*)
settings.js readSettings(app)        -> typed settings object for the UI
```

| Forum attribute | Setting key | Cast |
| --- | --- | --- |
| `nestedRepliesEnabled` | `mtareq-nested-replies.enabled` | bool |
| `nestedRepliesMaxDepth` | `mtareq-nested-replies.max_depth` | int |
| `nestedRepliesShowVotes` | `mtareq-nested-replies.show_votes` | bool |
| `nestedRepliesShowReplyTag` | `mtareq-nested-replies.show_reply_tag` | bool |
| `nestedRepliesShowRepliedIndicator` | `mtareq-nested-replies.show_replied_indicator` | bool |
| `nestedRepliesLikeColor` | `mtareq-nested-replies.like_color` | string |
| `nestedRepliesStartAtFirstPost` | `mtareq-nested-replies.start_at_first_post` | bool |
| `nestedRepliesVisibleReplies` | `mtareq-nested-replies.visible_replies` | int |
| `nestedRepliesShowScrubber` | `mtareq-nested-replies.show_scrubber` | bool |
| `nestedRepliesReplyForm` | `mtareq-nested-replies.reply_form` | string (`quick` \| `composer`) |
| `nestedRepliesHighlightColor` | `mtareq-nested-replies.highlight_color` | string (hex) |
| `nestedRepliesLegacyMentions` | `mtareq-nested-replies.legacy_mentions` | bool |

## Dependencies

- `flarum/core` — required.
- `flarum/mentions` — optional; when present its per-post Reply button is reused,
  otherwise this extension renders one. Threading never depends on mentions.
- `flarum/likes` — optional; only relevant to the themed Like action and the
  `like_color` setting.

## Build and test

Webpack (via `flarum-webpack-config`) bundles `admin.js` / `forum.js` into
`js/dist`. Pure logic modules are tested with Vitest:

```bash
cd js
npm run build         # regenerate dist/ after source changes
npm test              # Vitest for threadDepths, settings, voteAdapter
npm run format-check  # Prettier
```

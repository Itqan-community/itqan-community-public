import app from 'flarum/forum/app';

// How far the indentation is allowed to grow before it stops. The post still
// renders past this depth and still carries its reply context — it just stops
// eating horizontal space. Five steps is already 100px of indent on a 375px
// phone, and a sixth would leave the text a narrower column than the margin
// beside it.
export const MAX_DEPTH = 5;

// Which posts the reader has collapsed, by id. Module state rather than
// component state because PostStream rebuilds its children as the window
// slides, and a toggle that reset itself on scroll would be worse than no
// toggle at all.
const collapsedIds = new Set();

// post id -> { html, parentId }. The parse runs once per post body instead of
// once per render; keying the entry by the html it was derived from means an
// edit that changes the mention recomputes on its own.
// addPostMentionPreviews.js keeps the same kind of cache for the same reason.
const parentCache = new Map();

/**
 * The id of the post this one replies to, or null if it opens a thread.
 *
 * Flarum core has no reply relationship to read: `Post` defines `discussion()`
 * and `user()` and nothing else. What it does have, with flarum/mentions
 * enabled, is the trace the reply button leaves behind — `reply()` in
 * mentions' `utils/reply.js` inserts `@"name"#pID`, which renders into the
 * post body as `<a class="PostMention" data-id="ID">`.
 *
 * The *first* such mention is the parent. A post may mention several others,
 * since quoting two people in one answer is ordinary, so something has to
 * settle which one is the parent — and first is the right rule, because the
 * reply button writes its mention ahead of whatever the member then types.
 */
export function parentIdOf(post) {
  const html = post.contentHtml();
  if (!html) return null;

  const id = String(post.id());
  const cached = parentCache.get(id);
  if (cached && cached.html === html) return cached.parentId;

  // Parsed rather than matched with a regular expression: attribute order in
  // the stored html is not ours to promise, and the cache above means this
  // costs one parse per edit rather than one per render.
  //
  // DOMParser rather than an `innerHTML` on a detached div. The document it
  // returns has no browsing context, so nothing in the markup loads: assigning
  // post bodies to `innerHTML` would set every image in every post fetching a
  // second time, purely to read one attribute off a link.
  const parsed = new DOMParser().parseFromString(html, 'text/html');
  const mention = parsed.querySelector('a.PostMention[data-id]');
  const parentId = mention ? mention.getAttribute('data-id') : null;

  parentCache.set(id, { html, parentId });
  return parentId;
}

/**
 * The loaded parent post, or null.
 *
 * Two cases deliberately return null rather than a post:
 *
 * The parent was never fetched. Arriving deep into a long thread — a permalink,
 * a notification, the scrubber — loads the posts around the landing point and
 * nothing before them, so the parent of a reply on screen may never have been
 * asked for. Such a reply renders flat, the same way addPostMentionPreviews
 * degrades when it cannot find the post a mention points at.
 *
 * Worth being exact about which "not loaded" this is. PostStream keeps a window
 * of twenty and drops the pages it has left behind, but the store underneath it
 * keeps everything it has ever fetched. A parent that has scrolled out of the
 * window is therefore still here, and the reply stays indented — which is the
 * behaviour worth having, since the alternative is a post that changes shape
 * depending on how far the reader has scrolled.
 *
 * The parent is in another discussion. Linking to a post in a different thread
 * is a normal thing to do and says nothing about who this post replies to;
 * following it would indent a post under a parent the reader cannot see.
 */
function loadedParentOf(post) {
  const parentId = parentIdOf(post);
  if (!parentId) return null;

  const parent = app.store.getById('posts', parentId);
  if (!parent) return null;

  const here = post.discussion();
  const there = parent.discussion();
  if (!here || !there || here.id() !== there.id()) return null;

  // A reply comes after the post it answers. Anything earlier in the thread
  // mentioning something later is a reference, not a reply — the only way to
  // write one is to edit a post and name something published after it.
  //
  // This is also what keeps the tree acyclic. Two posts can name each other
  // once one of them has been edited, and without an ordering rule the walk
  // upward has to be defended with a visited set to avoid running forever.
  // Ordering settles it at the source: the earlier post has no parent, the
  // later one does, and the loop cannot close. The visited sets are still
  // there, but as a backstop rather than the thing doing the work.
  const hereAt = position(post);
  const thereAt = position(parent);
  if (hereAt !== null && thereAt !== null && thereAt >= hereAt) return null;

  return parent;
}

// Where a post sits in its discussion. `number` is what the stream sorts and
// scrolls by; id is the fallback for the moment between a reply being created
// and the server handing back its number.
function position(post) {
  const number = post.number();
  if (typeof number === 'number') return number;

  const id = Number(post.id());
  return Number.isFinite(id) ? id : null;
}

/**
 * How deep this post sits: 0 for one that opens a thread, 1 for a reply to it,
 * and so on up to MAX_DEPTH.
 */
export function depthOf(post) {
  let depth = 0;
  let current = post;

  // Two members quoting each other back and forth makes a cycle, and a cycle
  // here would hang the render rather than merely misdraw it.
  const seen = new Set([String(post.id())]);

  while (depth < MAX_DEPTH) {
    const parent = loadedParentOf(current);
    if (!parent) break;

    const parentId = String(parent.id());
    if (seen.has(parentId)) break;

    seen.add(parentId);
    current = parent;
    depth++;
  }

  return depth;
}

/**
 * The posts this one is the parent of.
 *
 * `mentionedBy` is the one direction mentions serializes onto posts by default,
 * so the candidates cost no extra request. But it answers a wider question than
 * this one does — it is every post that mentions this one — and two kinds of
 * those are not replies to it:
 *
 * A post in another discussion. Linking across threads is ordinary, and such a
 * post is not in this stream at all: counting it would promise to fold away
 * something that is not there to fold.
 *
 * A post that mentions this one in passing. Its parent is whatever it mentioned
 * first, and it is indented under that instead. Counting it here would make the
 * label disagree with what the toggle actually hides.
 *
 * So the same rule that decides a parent decides a child, and the two stay
 * consistent by construction.
 */
export function repliesTo(post) {
  const id = String(post.id());
  const discussion = post.discussion();
  const discussionId = discussion ? String(discussion.id()) : null;
  if (!discussionId) return [];

  return (post.mentionedBy() || []).filter((reply) => {
    if (String(parentIdOf(reply)) !== id) return false;

    const other = reply.discussion();
    return !!other && String(other.id()) === discussionId;
  });
}

/**
 * How many posts collapsing this one would take off the screen: its replies,
 * their replies, and so on down.
 *
 * The toggle appears when a post has a direct reply, but the number on it
 * counts the whole branch, because the whole branch is what folds away. Saying
 * "two replies" and then removing four posts is a worse answer than counting
 * the grandchildren as replies too — which, in a thread, they are.
 */
export function descendantCount(post, seen = new Set()) {
  const id = String(post.id());
  if (seen.has(id)) return 0;
  seen.add(id);

  return repliesTo(post).reduce((total, reply) => total + 1 + descendantCount(reply, seen), 0);
}

export function isCollapsed(post) {
  return collapsedIds.has(String(post.id()));
}

export function toggleCollapsed(post) {
  const id = String(post.id());
  if (collapsedIds.has(id)) {
    collapsedIds.delete(id);
  } else {
    collapsedIds.add(id);
  }
}

/**
 * Whether this post is hidden because something above it is collapsed.
 *
 * Walks the same chain as depthOf, but without the depth ceiling: a post
 * indented at the cap is still a descendant, and collapsing its branch has to
 * take it with the rest. The walk stops where the chain leaves the loaded
 * window, so a post whose collapsed ancestor has been dropped from the store
 * reappears — which is the honest outcome, since the control that would put it
 * back is no longer on screen either.
 */
export function isHiddenByAncestor(post) {
  let current = post;
  const seen = new Set([String(post.id())]);

  for (;;) {
    const parent = loadedParentOf(current);
    if (!parent) return false;

    const parentId = String(parent.id());
    if (seen.has(parentId)) return false;
    if (collapsedIds.has(parentId)) return true;

    seen.add(parentId);
    current = parent;
  }
}

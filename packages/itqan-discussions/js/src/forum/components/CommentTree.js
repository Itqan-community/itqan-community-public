import app from 'flarum/forum/app';

// Deepest level that gets its own indent step. Beyond this the indent would eat
// the reading measure, so the server's "continue this thread" link takes over.
export const MAX_VISUAL_DEPTH = 5;

// Global reactive collapsed-thread registry, keyed by the post id whose replies
// are hidden.
if (!app.itqanCollapsedThreads) {
  app.itqanCollapsedThreads = new Set();
}
// A Set mutates in place, so nothing about it can be compared between redraws.
// Flarum guards every post's subtree with a SubtreeRetainer, which only rebuilds
// when one of the values it watches changes — this revision is that value.
app.itqanCollapseRevision = app.itqanCollapseRevision || 0;
app.itqanDiscussionSort = app.itqanDiscussionSort || 'oldest';

/**
 * A parent → children index over the whole post store.
 *
 * Every caller used to walk `app.store.all('posts')` for itself, once per post:
 * the decorate pass was O(n²) and `postHasMoreReplies` added another full scan
 * per rendered comment. On a 124-post discussion that ran to tens of thousands
 * of iterations per redraw. The index is built at most once per frame and shared.
 */
let index = null;
let indexBuiltAt = 0;

function buildIndex() {
  const byId = new Map();
  const childrenOf = new Map();

  const all = (app.store && app.store.all('posts')) || [];

  all.forEach((post) => {
    if (!post || typeof post.id !== 'function') return;
    const id = String(post.id());
    byId.set(id, post);

    const parentId = typeof post.parentId === 'function' ? post.parentId() : null;
    if (!parentId) return;

    const key = String(parentId);
    const siblings = childrenOf.get(key);
    if (siblings) siblings.push(post);
    else childrenOf.set(key, [post]);
  });

  return { byId, childrenOf };
}

export function getStreamIndex() {
  const now = Date.now();
  // One frame's worth. Long enough that a single redraw reuses it, short enough
  // that a reply arriving mid-session is picked up.
  if (!index || now - indexBuiltAt > 16) {
    index = buildIndex();
    indexBuiltAt = now;
  }
  return index;
}

export function invalidateStreamIndex() {
  index = null;
}

function childrenOf(postId) {
  return getStreamIndex().childrenOf.get(String(postId)) || [];
}

function postById(id) {
  if (id == null) return null;
  return getStreamIndex().byId.get(String(id)) || (app.store ? app.store.getById('posts', String(id)) : null);
}

/**
 * Nesting depth of a post. The server sends `depth` with the payload; the local
 * walk is only a fallback for optimistically inserted posts that have not been
 * reconciled yet.
 */
export function getPostDepth(post, visited = new Set()) {
  if (!post) return 0;

  const parentId = typeof post.parentId === 'function' ? post.parentId() : null;
  if (!parentId) return 0;

  if (typeof post.attribute === 'function') {
    const serverDepth = post.attribute('depth');
    if (serverDepth !== null && serverDepth !== undefined) {
      return Number(serverDepth);
    }
  }

  const postId = typeof post.id === 'function' ? String(post.id()) : '';
  if (visited.has(postId)) return 0;
  visited.add(postId);

  const parent = postById(parentId);
  if (!parent) return 0;
  if (typeof parent.number === 'function' && parent.number() === 1) return 0;

  return 1 + getPostDepth(parent, visited);
}

/**
 * Max visual indent steps by viewport. Deeper replies keep their reply chip
 * for lineage but stop pushing the reading measure.
 */
export function getMaxVisualDepthForViewport(width = typeof window !== 'undefined' ? window.innerWidth : 1440) {
  if (width <= 767) return 1;
  if (width <= 1023) return 2;
  return Math.min(MAX_VISUAL_DEPTH, 3);
}

/** Depth used for styling: clamped to the current viewport budget. */
export function getVisualDepth(post) {
  return Math.min(getPostDepth(post), getMaxVisualDepthForViewport());
}

/**
 * The chain of ancestors between a post and its root comment, nearest last.
 * The opening post is not an ancestor for threading purposes.
 */
export function getAncestors(post) {
  const chain = [];
  if (!post) return chain;

  const visited = new Set([typeof post.id === 'function' ? String(post.id()) : '']);
  let current = post;

  while (current) {
    const parentId = typeof current.parentId === 'function' ? current.parentId() : null;
    if (!parentId) break;

    const key = String(parentId);
    if (visited.has(key)) break;
    visited.add(key);

    const parent = postById(key);
    if (!parent) break;
    if (typeof parent.number === 'function' && parent.number() === 1) break;

    chain.unshift(parent);
    current = parent;
  }

  return chain;
}

export function isDescendantOfCollapsed(post) {
  if (!app.itqanCollapsedThreads.size) return false;
  return getAncestors(post).some((ancestor) => app.itqanCollapsedThreads.has(String(ancestor.id())));
}

/** Direct replies to a post that are present in the store. */
export function countLoadedChildren(post) {
  if (!post || typeof post.id !== 'function') return 0;
  return childrenOf(post.id()).length;
}

export function postHasMoreReplies(post) {
  if (!post) return false;

  if (typeof post.attribute === 'function' && post.attribute('hasMoreReplies')) {
    return true;
  }

  const replyCount = typeof post.replyCount === 'function' ? post.replyCount() || 0 : 0;
  if (replyCount <= 0) return false;

  return countLoadedChildren(post) < replyCount;
}

/**
 * A summary of what a collapsed subtree contains, so the reader can decide
 * whether to expand it without expanding it.
 */
export function getThreadSummary(post) {
  const seen = new Set();
  const users = [];
  let latest = null;
  // Collapsing hides the whole subtree, not just the direct replies, so the
  // count has to match what disappears.
  let descendants = 0;

  const walk = (parent) => {
    childrenOf(parent.id()).forEach((child) => {
      descendants++;

      const user = typeof child.user === 'function' ? child.user() : null;
      if (user && !seen.has(String(user.id()))) {
        seen.add(String(user.id()));
        if (users.length < 3) users.push(user);
      }

      const at = typeof child.createdAt === 'function' ? child.createdAt() : null;
      if (at && (!latest || at > latest)) latest = at;

      walk(child);
    });
  };

  walk(post);

  // Replies the server capped away are not in the store, so a declared count
  // higher than what we walked is the truthful one.
  const declared = typeof post.replyCount === 'function' ? post.replyCount() || 0 : 0;

  return { count: Math.max(descendants, declared), users, latest };
}

export function toggleCollapsed(postId) {
  const key = String(postId);
  if (app.itqanCollapsedThreads.has(key)) {
    app.itqanCollapsedThreads.delete(key);
  } else {
    app.itqanCollapsedThreads.add(key);
  }
  app.itqanCollapseRevision++;
  m.redraw();
  decorateStreamTree();
}

/**
 * Highlight a whole subtree while its rail is hovered or focused, so the extent
 * of what a click would collapse is visible first.
 *
 * The descendants are siblings in the DOM, not children, so no selector can
 * express this — hence the classes.
 */
function setSubtreeHighlight(ancestorId, on) {
  const container = document.querySelector('.itqan-comments-card');
  if (!container) return;

  container.querySelectorAll(`.itqan-thread-rail[data-rail-ancestor-id="${ancestorId}"]`).forEach((rail) => {
    rail.classList.toggle('itqan-thread-rail--active', on);
    const row = rail.closest('.PostStream-item');
    if (row) row.classList.toggle('itqan-subtree-hover', on);
  });

  const parentRow = container.querySelector(`.PostStream-item[data-id="${ancestorId}"]`);
  if (parentRow) parentRow.classList.toggle('itqan-subtree-hover', on);
}

let railDelegationBound = false;

function bindRailDelegation() {
  if (railDelegationBound) return;
  railDelegationBound = true;

  const handler = (on) => (e) => {
    const hit = e.target.closest ? e.target.closest('.itqan-thread-rail-hit') : null;
    if (!hit) return;
    const ancestorId = hit.getAttribute('data-rail-ancestor-id');
    if (ancestorId) setSubtreeHighlight(ancestorId, on);
  };

  document.addEventListener('mouseover', handler(true), true);
  document.addEventListener('mouseout', handler(false), true);
  document.addEventListener('focusin', handler(true), true);
  document.addEventListener('focusout', handler(false), true);
}

/**
 * Apply depth, collapse and rail attributes to the rendered rows.
 *
 * Tree order comes from the server payload, which is depth-first — nothing here
 * moves a DOM node or reorders with CSS.
 */
let decorateScheduled = false;

function dayKey(date) {
  if (!date) return null;
  const d = date instanceof Date ? date : new Date(date);
  if (Number.isNaN(d.getTime())) return null;
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
}

function formatDayLabel(date) {
  try {
    return new Intl.DateTimeFormat(app.translator.getLocale() || undefined, {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    }).format(date instanceof Date ? date : new Date(date));
  } catch (e) {
    const d = date instanceof Date ? date : new Date(date);
    return d.toLocaleDateString();
  }
}

/**
 * Insert or refresh tree-aware date separators between consecutive root comments
 * when the calendar day changes. Core's PostStream-timeGap stays hidden.
 */
function syncDateSeparators(container, rows) {
  container.querySelectorAll('.itqan-date-separator').forEach((el) => el.remove());

  let lastDay = null;
  rows.forEach((row) => {
    if (row.hasAttribute('hidden')) return;
    const post = postById(row.dataset.id);
    if (!post || typeof post.number !== 'function' || post.number() === 1) return;
    const parentId = typeof post.parentId === 'function' ? post.parentId() : null;
    if (parentId) return;

    const created = typeof post.createdAt === 'function' ? post.createdAt() : null;
    const key = dayKey(created);
    if (!key) return;

    if (lastDay && key !== lastDay) {
      const sep = document.createElement('div');
      sep.className = 'itqan-date-separator';
      sep.setAttribute('role', 'separator');
      sep.textContent = formatDayLabel(created);
      row.parentNode.insertBefore(sep, row);
    }
    lastDay = key;
  });
}

/**
 * Mark comments newer than the viewer's last-read position.
 */
function syncUnreadMarks(rows) {
  const discussion = app.current && typeof app.current.get === 'function' ? app.current.get('discussion') : null;
  const lastRead =
    discussion && typeof discussion.lastReadPostNumber === 'function' ? discussion.lastReadPostNumber() : null;

  rows.forEach((row) => {
    const post = postById(row.dataset.id);
    if (!post || typeof post.number !== 'function') {
      row.removeAttribute('data-unread');
      return;
    }
    const number = post.number();
    if (lastRead != null && number > 1 && number > lastRead) {
      row.setAttribute('data-unread', 'true');
    } else {
      row.removeAttribute('data-unread');
    }
  });
}

export function decorateStreamTree() {
  if (decorateScheduled) return;
  decorateScheduled = true;

  requestAnimationFrame(() => {
    decorateScheduled = false;
    invalidateStreamIndex();
    bindRailDelegation();

    const container = document.querySelector('.PostStream');
    if (!container) return;

    const rows = Array.from(container.querySelectorAll('.PostStream-item[data-id]'));
    if (!rows.length) return;

    const { childrenOf: children } = getStreamIndex();

    let firstRootSeen = false;

    rows.forEach((row) => {
      const id = row.dataset.id;
      const post = postById(id);
      if (!post) return;

      const depth = getVisualDepth(post);
      if (depth > 0) {
        row.setAttribute('data-thread-depth', String(depth));
      } else {
        row.removeAttribute('data-thread-depth');
      }

      // `hidden` rather than a class alone, so a collapsed reply leaves the
      // accessibility tree as well as the layout.
      if (isDescendantOfCollapsed(post)) {
        row.setAttribute('hidden', '');
      } else {
        row.removeAttribute('hidden');
      }

      if ((children.get(id) || []).length > 0) {
        row.setAttribute('data-has-thread-replies', 'true');
      } else {
        row.removeAttribute('data-has-thread-replies');
      }

      const parentId = typeof post.parentId === 'function' ? post.parentId() : null;
      const isRoot = !parentId && typeof post.number === 'function' && post.number() !== 1;

      if (!isRoot) {
        row.removeAttribute('data-is-first-root');
        row.removeAttribute('data-is-subsequent-root');
      } else if (!firstRootSeen) {
        firstRootSeen = true;
        row.setAttribute('data-is-first-root', 'true');
        row.removeAttribute('data-is-subsequent-root');
      } else {
        row.setAttribute('data-is-subsequent-root', 'true');
        row.removeAttribute('data-is-first-root');
      }
    });

    syncUnreadMarks(rows);
    syncDateSeparators(container, rows);
  });
}

/** @deprecated use decorateStreamTree — kept for import compatibility */
export function reorderStreamTree() {
  decorateStreamTree();
}

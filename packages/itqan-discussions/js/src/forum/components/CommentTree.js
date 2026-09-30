import app from 'flarum/forum/app';

// Global reactive collapsed threads registry
if (!app.itqanCollapsedThreads) {
  app.itqanCollapsedThreads = new Set();
}

/**
 * Calculate arbitrary nesting depth for a post with cycle detection.
 * 0 = root comment, 1 = direct reply to a comment, 2 = reply-to-reply, etc.
 * Comments on Post #1 (OP) are strictly depth 0.
 */
export function getPostDepth(post, visited = new Set()) {
  if (!post) return 0;
  const parentId = (typeof post.parentId === 'function') ? post.parentId() : null;
  if (!parentId) return 0;

  const postId = (typeof post.id === 'function') ? String(post.id()) : '';
  if (visited.has(postId)) return 0;
  visited.add(postId);

  const parent = app.store ? app.store.getById('posts', String(parentId)) : null;
  if (!parent) return 0;

  // Guard: If parent is OP post #1, depth is 0 (root discussion comment)
  if (typeof parent.number === 'function' && parent.number() === 1) {
    return 0;
  }

  return 1 + getPostDepth(parent, visited);
}

/**
 * Check if the post is a descendant of any currently collapsed ancestor thread.
 */
export function isDescendantOfCollapsed(post, visited = new Set()) {
  if (!post) return false;
  const parentId = (typeof post.parentId === 'function') ? post.parentId() : null;
  if (!parentId) return false;

  const parent = app.store ? app.store.getById('posts', String(parentId)) : null;
  if (!parent || (typeof parent.number === 'function' && parent.number() === 1)) {
    return false;
  }

  const parentIdStr = String(parentId);
  if (app.itqanCollapsedThreads.has(parentIdStr)) {
    return true;
  }

  const postId = (typeof post.id === 'function') ? String(post.id()) : '';
  if (visited.has(postId)) return false;
  visited.add(postId);

  return isDescendantOfCollapsed(parent, visited);
}

/**
 * Re-orders the flat .PostStream DOM items into a true hierarchical subtree tree
 * with sibling sorting and visual depth attributes.
 */
let reorderScheduled = false;
export function reorderStreamTree() {
  if (reorderScheduled) return;
  reorderScheduled = true;

  requestAnimationFrame(() => {
    reorderScheduled = false;
    const container = document.querySelector('.PostStream');
    if (!container) return;

    const items = Array.from(container.querySelectorAll('.PostStream-item[data-id]'));
    if (!items.length) return;

    const itemMap = new Map();
    const childrenMap = new Map();
    const rootIds = [];

    items.forEach((el) => {
      const id = el.dataset.id;
      const post = app.store ? app.store.getById('posts', id) : null;
      const parentId = (post && typeof post.parentId === 'function') ? post.parentId() : null;
      itemMap.set(id, el);

      // Attach depth and collapse classes
      if (post) {
        const depth = getPostDepth(post);
        if (depth > 0) {
          el.setAttribute('data-thread-depth', String(depth));
        } else {
          el.removeAttribute('data-thread-depth');
        }

        if (isDescendantOfCollapsed(post)) {
          el.classList.add('thread-item-hidden');
        } else {
          el.classList.remove('thread-item-hidden');
        }
      }

      // If parent is OP post #1, treat as root item
      let isChildOfComment = false;
      if (parentId) {
        const parent = app.store ? app.store.getById('posts', String(parentId)) : null;
        if (parent && (!parent.number || parent.number() > 1)) {
          isChildOfComment = true;
          const pIdStr = String(parentId);
          if (!childrenMap.has(pIdStr)) childrenMap.set(pIdStr, []);
          childrenMap.get(pIdStr).push(id);
        }
      }

      if (!isChildOfComment) {
        rootIds.push(id);
      }
    });

    // Sibling comparator (votes DESC if available, then createdAt ASC)
    function comparePostIds(a, b) {
      const postA = app.store ? app.store.getById('posts', a) : null;
      const postB = app.store ? app.store.getById('posts', b) : null;
      if (!postA || !postB) return 0;

      const votesA = (typeof postA.attribute === 'function') 
        ? (postA.attribute('votes') || 0) 
        : ((typeof postA.votes === 'function') ? (postA.votes() || 0) : 0);
      const votesB = (typeof postB.attribute === 'function') 
        ? (postB.attribute('votes') || 0) 
        : ((typeof postB.votes === 'function') ? (postB.votes() || 0) : 0);

      if (votesB !== votesA) {
        return votesB - votesA;
      }

      const timeA = postA.createdAt && postA.createdAt() ? postA.createdAt().getTime() : 0;
      const timeB = postB.createdAt && postB.createdAt() ? postB.createdAt().getTime() : 0;
      return timeA - timeB;
    }

    // Preserve OP post #1 first at top of root items
    const opId = rootIds.find((id) => {
      const p = app.store ? app.store.getById('posts', id) : null;
      return p && typeof p.number === 'function' && p.number() === 1;
    });

    // Stable Root Ordering:
    // Keep track of root IDs that have already been ordered for this discussion.
    // When "Load More" loads a new page of posts, previously loaded and visible roots
    // maintain their stable relative order, and new roots are appended cleanly in sorted order.
    // This prevents newly loaded posts from jumping above existing comments and shifting the page!
    if (!window.__itqanRootOrderRegistry) {
      window.__itqanRootOrderRegistry = new Map();
    }
    const discussionId = app.current.get('discussion') ? String(app.current.get('discussion').id()) : 'current';
    let knownRoots = window.__itqanRootOrderRegistry.get(discussionId) || [];

    // Filter out known roots that are no longer in rootIds
    knownRoots = knownRoots.filter((id) => rootIds.includes(id) && id !== opId);

    // New roots that haven't been placed in knownRoots yet
    const newRoots = rootIds.filter((id) => id !== opId && !knownRoots.includes(id)).sort(comparePostIds);

    // If knownRoots is empty (initial render of discussion), sort all non-OP roots by votes
    let finalOtherRoots;
    if (knownRoots.length === 0) {
      finalOtherRoots = rootIds.filter((id) => id !== opId).sort(comparePostIds);
    } else {
      finalOtherRoots = [...knownRoots, ...newRoots];
    }
    window.__itqanRootOrderRegistry.set(discussionId, finalOtherRoots);

    const sortedRoots = opId ? [opId, ...finalOtherRoots] : finalOtherRoots;

    const orderedEls = [];
    function traverse(id) {
      const el = itemMap.get(id);
      if (el) orderedEls.push(el);
      const children = childrenMap.get(id) || [];
      children.sort(comparePostIds);
      children.forEach((cId) => traverse(cId));
    }

    sortedRoots.forEach((rId) => traverse(rId));

    // Append any orphaned items
    items.forEach((el) => {

      if (!orderedEls.includes(el)) {
        orderedEls.push(el);
      }
    });

    // Apply the computed tree order via the CSS `order` property instead of
    // physically moving nodes with appendChild. `.PostStream` is a flex
    // column, so `order` reproduces the same visual layout without taking
    // DOM ownership away from Mithril: reparenting these nodes here raced
    // Mithril's own redraws (triggered by hover cards, vote updates, thread
    // collapse, etc.) for control of the same elements, corrupting their
    // position/sizing after subsequent updates.
    orderedEls.forEach((el, index) => {
      el.style.order = String(index);
    });

    const loadPreviousItem = container.querySelector('.PostStream-loadPrevious');
    if (loadPreviousItem) {
      loadPreviousItem.style.order = '-1';
    }

    const loadMoreItem = container.querySelector('.PostStream-loadMore');
    if (loadMoreItem) {
      loadMoreItem.style.order = String(orderedEls.length + 1);
    }

    const replyItem = container.querySelector('.PostStream-item:not([data-id])');
    if (replyItem) {
      replyItem.style.order = String(orderedEls.length + 2);
    }
  });
}

import app from 'flarum/forum/app';

// Global reactive collapsed threads registry
if (!app.itqanCollapsedThreads) {
  app.itqanCollapsedThreads = new Set();
}

/**
 * Calculate arbitrary nesting depth for a post with cycle detection.
 * 0 = root comment, 1 = direct reply, 2 = reply-to-reply, etc.
 */
export function getPostDepth(post, visited = new Set()) {
  if (!post) return 0;
  const parentId = (typeof post.parentId === 'function') ? post.parentId() : null;
  if (!parentId) return 0;

  const postId = (typeof post.id === 'function') ? String(post.id()) : '';
  if (visited.has(postId)) return 0;
  visited.add(postId);

  const parent = app.store ? app.store.getById('posts', String(parentId)) : null;
  if (!parent) return 1;

  return 1 + getPostDepth(parent, visited);
}

/**
 * Check if the post is a descendant of any currently collapsed ancestor thread.
 */
export function isDescendantOfCollapsed(post, visited = new Set()) {
  if (!post) return false;
  const parentId = (typeof post.parentId === 'function') ? post.parentId() : null;
  if (!parentId) return false;

  const parentIdStr = String(parentId);
  if (app.itqanCollapsedThreads.has(parentIdStr)) {
    return true;
  }

  const postId = (typeof post.id === 'function') ? String(post.id()) : '';
  if (visited.has(postId)) return false;
  visited.add(postId);

  const parent = app.store ? app.store.getById('posts', parentIdStr) : null;
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

      if (parentId) {
        const pIdStr = String(parentId);
        if (!childrenMap.has(pIdStr)) childrenMap.set(pIdStr, []);
        childrenMap.get(pIdStr).push(id);
      } else {
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
    const otherRoots = rootIds.filter((id) => id !== opId).sort(comparePostIds);
    const sortedRoots = opId ? [opId, ...otherRoots] : otherRoots;

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

    // Batch re-insert into container
    const fragment = document.createDocumentFragment();
    orderedEls.forEach((el) => fragment.appendChild(el));

    const replyItem = container.querySelector('.PostStream-item:not([data-id])');
    container.appendChild(fragment);
    if (replyItem) {
      container.appendChild(replyItem);
    }
  });
}

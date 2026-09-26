// The post mention that leads a post's content, if any. Flarum's reply action
// inserts the reply-target mention at the very start of the content, so a
// leading post mention is a reliable parent hint for legacy posts (which have no
// stored replyToPostId). A mention embedded in text is ignored.
export function getLeadingMentionId(post) {
  if (!post) return null;

  const html = typeof post.contentHtml === 'function' ? post.contentHtml() : null;
  if (typeof html === 'string' && html) {
    // Allow a wrapping <p> and leading whitespace before the mention anchor.
    const tag = html.match(/^\s*(?:<p\b[^>]*>\s*)?<a\b([^>]*)>/i);
    if (tag && /\bclass\s*=\s*"[^"]*\bPostMention\b/i.test(tag[1])) {
      const id = tag[1].match(/\bdata-id\s*=\s*"(\d+)"/i);
      if (id) return id[1];
    }
  }

  // Fall back to the raw content: `@"name"#pN ...`.
  const raw = typeof post.content === 'function' ? post.content() : null;
  if (typeof raw === 'string') {
    const match = raw.match(/^\s*@"[^"]*"#p(\d+)/);
    if (match) return match[1];
  }

  return null;
}

export function getStoredParentId(post) {
  if (!post || typeof post.attribute !== 'function') return null;

  const parentId = post.attribute('replyToPostId');
  if (parentId == null || parentId === '') return null;

  return String(parentId);
}

export function getParentId(post, legacyMentions = false) {
  const stored = getStoredParentId(post);
  if (stored) return stored;
  if (!legacyMentions) return null;

  return getLeadingMentionId(post);
}

// True when a post's parent comes from a legacy mention rather than the stored
// replyToPostId. Legacy links are capped to a single level of nesting.
export function isDerivedParent(post, legacyMentions = false) {
  if (!legacyMentions || getStoredParentId(post)) return false;

  return getLeadingMentionId(post) != null;
}

export function getReplyTarget(post, getPostById, legacyMentions = false) {
  const parentId = getParentId(post, legacyMentions);
  if (!parentId) return null;

  const parent = getPostById ? getPostById(parentId) : null;
  if (!parent) return null;

  const user = typeof parent.user === 'function' ? parent.user() : null;
  const name = user && typeof user.displayName === 'function' ? user.displayName() : null;

  return { id: parentId, name, post: parent };
}

export function getAncestorIds(post, getPostById, legacyMentions = false) {
  const ids = [];
  const seen = new Set();
  let current = post;

  while (current) {
    const currentId = String(current.id());
    if (seen.has(currentId)) break;
    seen.add(currentId);

    const parentId = getParentId(current, legacyMentions);
    if (!parentId) break;

    ids.push(parentId);

    // Legacy links never stack, so the chain stops after one derived hop.
    if (isDerivedParent(current, legacyMentions)) break;

    current = getPostById ? getPostById(parentId) : null;
  }

  return ids;
}

export function isOriginalPost(post) {
  return Boolean(post && typeof post.number === 'function' && post.number() === 1);
}

export function getDepth(post, maxDepth = Infinity, getPostById = null, legacyMentions = false) {
  if (!post) return 0;

  let depth = 0;
  const seen = new Set();
  let current = post;

  while (current && depth < maxDepth) {
    const currentId = String(current.id());
    if (seen.has(currentId)) break;
    seen.add(currentId);

    // A legacy-derived reply is one level deep and never stacks further.
    if (isDerivedParent(current, legacyMentions)) {
      depth += 1;
      break;
    }

    const parentId = getParentId(current, legacyMentions);
    if (!parentId) break;

    const parent = getPostById ? getPostById(parentId) : null;
    if (!parent) break;

    // Replying to the original post is a top-level reply, not a nested level.
    if (isOriginalPost(parent)) break;

    current = parent;
    depth += 1;
  }

  return depth;
}

export function isHidden(post, collapsedSet, getPostById, legacyMentions = false) {
  return getAncestorIds(post, getPostById, legacyMentions).some((id) => collapsedSet.has(id));
}

// Reddit-style sibling folding. Replies stay unfolded by default, but when a
// reply has more child replies than `visibleReplies`, only the first few stay
// visible and the rest are hidden behind a "Show more replies" control.
//
// `posts` must be in render order (depth-first) so every comment precedes its
// children and sibling groups keep their sorted order. The control is anchored
// to the last visible post of the kept branch so it renders at the depth of the
// hidden replies, after the branch they belong to.
export function planSiblingFolding(posts, options = {}) {
  const list = Array.isArray(posts) ? posts.filter(Boolean) : [];
  const lookup = typeof options.lookup === 'function' ? options.lookup : () => null;
  const visibleReplies = Math.max(1, Number(options.visibleReplies) || 1);
  const expanded = options.expandedParents instanceof Set ? options.expandedParents : new Set();
  const legacyMentions = options.legacyMentions === true;

  const resolve = (id) => {
    const found = lookup(id);
    if (found) return found;
    return list.find((post) => String(post.id()) === String(id)) || null;
  };

  const childrenByParent = new Map();

  list.forEach((post) => {
    const parentId = getParentId(post, legacyMentions);
    if (!parentId) return;

    const parent = resolve(parentId);
    // Replying to the original post is a top-level reply; the original post's
    // direct replies are never folded, matching Reddit.
    if (!parent || isOriginalPost(parent)) return;

    const key = String(parent.id());
    const group = childrenByParent.get(key) || [];
    group.push(post);
    childrenByParent.set(key, group);
  });

  const hidden = new Set();
  const moreAfter = new Map();

  const collectSubtree = (post, members) => {
    const id = String(post.id());
    hidden.add(id);
    if (members) members.push(id);

    let total = 1;
    const children = childrenByParent.get(id) || [];
    children.forEach((child) => {
      total += collectSubtree(child, members);
    });

    return total;
  };

  const isInBranch = (post, branchRootId) => {
    let current = post;
    const seen = new Set();

    while (current) {
      const id = String(current.id());
      if (seen.has(id)) return false;
      seen.add(id);

      if (id === String(branchRootId)) return true;

      const parentId = getParentId(current, legacyMentions);
      current = parentId ? resolve(parentId) : null;
    }

    return false;
  };

  // First pass: decide which groups fold and hide everything they cover, so the
  // anchor search below sees the complete hidden set regardless of group order.
  const foldedGroups = [];

  childrenByParent.forEach((group, parentId) => {
    if (group.length <= visibleReplies || expanded.has(parentId)) return;

    const kept = group.slice(0, visibleReplies);
    const folded = group.slice(visibleReplies);

    let count = 0;
    const members = [];
    folded.forEach((child) => {
      count += collectSubtree(child, members);
    });

    const branchRoot = kept[kept.length - 1];
    if (!branchRoot) return;

    foldedGroups.push({ parentId, branchRoot, count, members });
  });

  // Second pass: anchor each control to the last visible post of its kept
  // branch. Several folded groups can share one anchor (a group nested inside
  // the kept branch), so every control that belongs to a post is collected.
  foldedGroups.forEach(({ parentId, branchRoot, count, members }) => {
    // A group nested under an already hidden reply has no visible control.
    if (hidden.has(parentId)) return;

    let anchor = branchRoot;
    list.forEach((post) => {
      if (hidden.has(String(post.id()))) return;
      if (isInBranch(post, String(branchRoot.id()))) anchor = post;
    });

    const key = String(anchor.id());
    const entries = moreAfter.get(key) || [];
    entries.push({
      parentId,
      count,
      // Hidden post ids of this group (whole subtree) — feeds footer summaries.
      members,
      // Depth the hidden replies belong to, so the control can line up with it.
      targetDepth: getDepth(branchRoot, Infinity, resolve, legacyMentions),
    });
    moreAfter.set(key, entries);
  });

  return { hidden, moreAfter };
}

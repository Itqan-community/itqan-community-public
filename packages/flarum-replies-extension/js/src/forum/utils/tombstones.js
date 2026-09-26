/**
 * Client-synthesized tombstones (spec §5.3): when a reply's stored parent is
 * absent from the store (deleted beyond visibility, or out of the reader's
 * scope), the FIRST child gets a marker so CSS can render an anonymous stub
 * card anchoring the subtree. Later siblings stay quiet (one stub per gap).
 *
 * Self-heals: if the parent loads later (page scroll), `exists` flips and the
 * marker disappears on the next decorate pass.
 */
export function firstChildOfMissingParent(posts, exists) {
  const marked = new Set();
  const firstSeen = new Map(); // parentId -> first (lowest number) child id

  for (const post of posts || []) {
    const id = post && typeof post.id === 'function' ? post.id() : null;
    if (id == null) continue;

    const parentId =
      post && typeof post.attribute === 'function' ? post.attribute('replyToPostId') : null;
    if (parentId == null || parentId === '') continue;

    const key = String(parentId);
    if (!firstSeen.has(key)) firstSeen.set(key, String(id));
  }

  for (const [parentId, firstChildId] of firstSeen) {
    if (!exists(parentId)) marked.add(firstChildId);
  }

  return marked;
}

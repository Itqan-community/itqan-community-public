export const MAX_SUMMARY_AUTHORS = 5;

/**
 * Footer summary for a folded sibling group: distinct authors (capped) + the
 * latest activity among the hidden posts. Returns null when there is nothing
 * resolvable to show.
 *
 * `count` is the number of MEMBERS (ids), not resolved posts — it mirrors the
 * fold plan's `members.length === count` invariant, so a member whose post
 * isn't in the store yet still counts toward "N more replies".
 * (Plan-draft defect #12: the draft counted only resolved posts, which
 * contradicts threadSummary.test.js "drops unresolvable ids" expecting 3.)
 *
 * @param memberIds hidden post ids of the group (planSiblingFolding's `members`)
 * @param lookup    (id) => store post | null
 */
export function summarizeThread(memberIds, lookup) {
  if (!memberIds || !memberIds.length) return null;

  const authors = [];
  const seen = new Set();
  let latestAt = null;
  const count = memberIds.length;

  for (const id of memberIds) {
    const post = lookup ? lookup(id) : null;
    if (!post) continue;

    const createdAt = typeof post.attribute === 'function' ? post.attribute('createdAt') : undefined;
    if (createdAt && (!latestAt || String(createdAt) > String(latestAt))) latestAt = createdAt;

    const user = typeof post.user === 'function' ? post.user() : null;
    const uid = user && typeof user.id === 'function' ? String(user.id()) : null;
    if (uid && !seen.has(uid) && authors.length < MAX_SUMMARY_AUTHORS) {
      seen.add(uid);
      authors.push(user);
    }
  }

  return { authors, latestAt, count };
}

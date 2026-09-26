/**
 * Unread replies for the current reader, or null when there is nothing to show.
 *
 * `lastReadPostNumber` exists only for logged-in users with a read marker;
 * when it is absent the row is "never read" — core already styles that state,
 * so we stay quiet rather than badge every row a guest sees.
 *
 * commentCount and post numbers share one scale (the OP is #1), so the plain
 * difference is the count of replies after the last read post.
 *
 * (Core's `Discussion.unreadCount()` was considered and rejected: it returns 0
 * rather than null for guests/full-read, and reads `app.session`, which makes
 * it impure and untestable here.)
 */
export function unreadReplyCount(discussion) {
  if (!discussion || typeof discussion.attribute !== 'function') return null;

  const lastRead = discussion.attribute('lastReadPostNumber');
  if (lastRead == null || !Number.isFinite(Number(lastRead))) return null;

  const commentCount = typeof discussion.commentCount === 'function' ? Number(discussion.commentCount()) : NaN;
  if (!Number.isFinite(commentCount)) return null;

  const unread = commentCount - Number(lastRead);
  return unread > 0 ? unread : null;
}

const utcDateKey = (iso) => {
  if (typeof iso !== 'string' || !iso) return null;
  const t = Date.parse(iso);
  if (Number.isNaN(t)) return null;
  return new Date(t).toISOString().slice(0, 10); // UTC calendar day
};

/**
 * {postId -> label|null}: the label lands on the FIRST post of each UTC day,
 * every later post of that day gets null (CSS shows nothing).
 *
 * The formatter is caller-owned — the caller passes a translator-aware
 * function so this module stays free of Flarum imports and trivially testable.
 * `today` is the viewer's reference (YYYY-MM-DD, UTC key of now).
 */
export function buildDayLabelMap(posts, today, format) {
  const labels = {};
  let previousDay = null;

  for (const post of posts || []) {
    const id = post && typeof post.id === 'function' ? post.id() : null;
    if (id == null) continue;

    const created = typeof post.attribute === 'function' ? post.attribute('createdAt') : undefined;
    const day = utcDateKey(created);
    if (!day) continue; // no date -> no divider, does not reset the chain

    labels[id] = day !== previousDay ? format(day, today) : null;
    previousDay = day;
  }

  return labels;
}

// Builds the discussion-list sort catalog from Flarum's core sort map.
//
// Keys drive the sidebar buttons and their labels
// (core.forum.index_sort.{key}_button). `votes` and `replies` are ours so a
// language pack cannot clobber their labels; core's `top`/`hot` are dropped.
// `relevance` is preserved (it must stay first) for search results.
export function withCatalogSorts(map = {}) {
  const next = {};

  if (Object.prototype.hasOwnProperty.call(map, 'relevance')) {
    next.relevance = map.relevance;
  }

  next.latest = map.latest || '-lastPostedAt';
  next.votes = '-votes';
  next.newest = map.newest || '-createdAt';
  next.oldest = map.oldest || 'createdAt';
  // Core's `top` is the reply-count sort; it becomes our `replies`.
  next.replies = map.top || '-commentCount';

  return next;
}

// Adds `firstPost` to a discussion-list request's `include`, so every row ships
// its original post (and that post's `votes`/`userVote`). Idempotent and
// non-mutating so it is safe to run on every request.
export function withFirstPostInclude(params = {}) {
  const include = Array.isArray(params.include) ? params.include.slice() : [];

  if (!include.includes('firstPost')) include.push('firstPost');

  return { ...params, include };
}

// Every list row shows the discussion's section pill, so every list request has
// to carry the `tags` relation. Flarum 2.0's client names its own includes
// (`user`, `lastPostedUser`), which replaces the endpoint's defaults, and the
// tags extension only adds `tags` on a tag-filtered page — so without this the
// pills disappear on "All Discussions" and on the first server-rendered paint.
// `tags.parent` is included because a child tag's pill is coloured by its
// parent when the child has no colour of its own.
export function withTagsInclude(params = {}) {
  const include = Array.isArray(params.include) ? params.include.slice() : [];

  for (const name of ['tags', 'tags.parent']) {
    if (!include.includes(name)) include.push(name);
  }

  return { ...params, include };
}

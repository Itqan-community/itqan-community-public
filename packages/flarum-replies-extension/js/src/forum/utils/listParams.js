// Adds `firstPost` to a discussion-list request's `include`, so every row ships
// its original post (and that post's `votes`/`userVote`). Idempotent and
// non-mutating so it is safe to run on every request.
export function withFirstPostInclude(params = {}) {
  const include = Array.isArray(params.include) ? params.include.slice() : [];

  if (!include.includes('firstPost')) include.push('firstPost');

  return { ...params, include };
}

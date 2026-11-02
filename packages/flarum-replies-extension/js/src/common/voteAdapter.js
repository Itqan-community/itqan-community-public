export function createVoteAdapter(app) {
  const readNumber = (post, key) => {
    if (!post || typeof post.attribute !== 'function') return 0;
    const value = post.attribute(key);
    if (value === null || value === undefined) return 0;
    const parsed = Number(value);
    return isNaN(parsed) ? 0 : parsed;
  };

  const readVote = (post) => {
    const value = post && typeof post.attribute === 'function' ? post.attribute('userVote') : null;
    return value === 'up' || value === 'down' ? value : null;
  };

  // Who wrote the post is readable from the store, so the rail can refuse a
  // self-vote even when the server hint is missing (Flarum 2.0 did not
  // serialize `canVote` for a while, and every click on your own post ended in
  // a 403 toaster). The server policy is still the hard gate.
  const isOwnPost = (post) => {
    const user = app && app.session && app.session.user;

    if (!post || !user || typeof post.user !== 'function') return false;

    const author = post.user();
    const userId = user.id && typeof user.id === 'function' ? user.id() : user.id;
    const authorId = author && author.id && typeof author.id === 'function' ? author.id() : author && author.id;

    return userId != null && authorId != null && String(authorId) === String(userId);
  };

  // `canVote` is the serialized hint; a missing hint means "assume available"
  // (the server still decides). `post` is optional so score-only callers work.
  const isAvailable = (post) =>
    Boolean(app && app.session && app.session.user) &&
    !isOwnPost(post) &&
    (!post || post.attribute('canVote') !== false);
  const pending = new Set();

  return {
    isAvailable,
    isOwnPost,

    getScore: (post) => readNumber(post, 'votes'),
    getUserVote: (post) => readVote(post),

    vote(post, direction) {
      if (!post) return Promise.resolve();
      if (!app.session || !app.session.user) return Promise.resolve({ rejected: 'guest' });
      if (isOwnPost(post)) return Promise.resolve({ rejected: 'own' });
      if (!isAvailable(post)) return Promise.resolve({ rejected: 'cannot' });

      const key = String(post.id());
      if (pending.has(key)) return Promise.resolve({ rejected: 'inflight' });

      const value = direction === 'up' || direction === 'down' ? direction : null;
      const url = `${app.forum.attribute('apiUrl')}/mtareq-nested-replies/posts/${post.id()}/vote`;

      // Snapshot for optimistic apply + rollback.
      const before = { votes: readNumber(post, 'votes'), userVote: readVote(post) };
      const delta =
        (value === 'up' ? 1 : 0) - (before.userVote === 'up' ? 1 : 0) +
        (value === 'down' ? -1 : 0) - (before.userVote === 'down' ? -1 : 0);

      pending.add(key);
      if (typeof post.pushAttributes === 'function') post.pushAttributes({ votes: before.votes + delta, userVote: value });
      if (typeof m !== 'undefined' && m.redraw) m.redraw();

      return app
        .request({ method: 'POST', url, body: { direction: value } })
        .then((payload) => {
          // Prefer the server's authoritative attributes when present.
          const attrs = payload && payload.data && payload.data.attributes;
          if (attrs && typeof post.pushAttributes === 'function') {
            post.pushAttributes({
              votes: Number(attrs.votes),
              userVote: attrs.userVote === 'up' || attrs.userVote === 'down' ? attrs.userVote : null,
            });
          }
          if (payload && payload.data && app.store && typeof app.store.pushPayload === 'function') {
            app.store.pushPayload(payload);
          }
          if (typeof m !== 'undefined' && m.redraw) m.redraw();
          return payload;
        })
        .catch((err) => {
          if (typeof post.pushAttributes === 'function') post.pushAttributes({ votes: before.votes, userVote: before.userVote });
          if (typeof m !== 'undefined' && m.redraw) m.redraw();
          throw err; // surface normally, never swallow
        })
        .finally(() => {
          pending.delete(key);
        });
    },
  };
}
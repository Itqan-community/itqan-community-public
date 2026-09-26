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

  const isAvailable = () => Boolean(app && app.session && app.session.user);
  const pending = new Set();

  return {
    isAvailable,

    getScore: (post) => readNumber(post, 'votes'),
    getUserVote: (post) => readVote(post),

    vote(post, direction) {
      if (!post) return Promise.resolve();
      if (!isAvailable()) return Promise.resolve({ rejected: 'guest' });

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
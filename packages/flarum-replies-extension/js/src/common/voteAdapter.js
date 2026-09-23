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

  // Only signed-in users can vote. Closure (not `this.isAvailable()`) so the
  // guard survives call sites that extract the method.
  const isAvailable = () => Boolean(app && app.session && app.session.user);

  // In-flight guard: model id -> pending Set membership while a vote is queued.
  const pending = new Set();

  const keyFor = (model, explicitId) =>
    explicitId != null ? String(explicitId) : model && typeof model.id === 'function' ? String(model.id()) : null;

  return {
    isAvailable,

    getScore: (post) => readNumber(post, 'votes'),

    getUserVote: (post) => readVote(post),

    /**
     * @param model  store model whose attributes are optimistically updated (post or discussion row)
     * @param direction 'up' | 'down' | null (withdraw)
     * @param opts.id  explicit post id to hit (list-row votes target firstPostId)
     */
    vote(model, direction, opts = {}) {
      if (!model) return Promise.resolve();

      // Guest: never fire a request; VoteRail shows the login modal instead.
      if (!isAvailable()) return Promise.resolve({ rejected: 'guest' });

      const key = keyFor(model, opts.id);
      if (key == null) return Promise.resolve();
      if (pending.has(key)) return Promise.resolve({ rejected: 'inflight' });

      const value = direction === 'up' || direction === 'down' ? direction : null;
      const url = `${app.forum.attribute('apiUrl')}/mtareq-nested-replies/posts/${opts.id != null ? opts.id : model.id()}/vote`;

      // Snapshot for rollback.
      const before = { votes: readNumber(model, 'votes'), userVote: readVote(model) };
      const delta =
        (value === 'up' ? 1 : 0) - (before.userVote === 'up' ? 1 : 0) +
        (value === 'down' ? -1 : 0) - (before.userVote === 'down' ? -1 : 0);
      const optimistic = {
        votes: before.votes + delta,
        userVote: value,
      };

      pending.add(key);
      if (typeof model.pushAttributes === 'function') model.pushAttributes(optimistic);

      return app
        .request({
          method: 'POST',
          url,
          body: { direction: value },
        })
        .then((payload) => {
          // Prefer the server's authoritative attributes when present.
          const attrs = payload && payload.data && payload.data.attributes;
          if (attrs && typeof model.pushAttributes === 'function') {
            model.pushAttributes({
              votes: Number(attrs.votes),
              userVote: attrs.userVote === 'up' || attrs.userVote === 'down' ? attrs.userVote : null,
            });
          }

          if (payload && payload.data && app.store && typeof app.store.pushPayload === 'function') {
            app.store.pushPayload(payload);
          }

          if (typeof m !== 'undefined' && m.redraw) {
            m.redraw();
          }

          return payload;
        })
        .catch((err) => {
          // Roll back to the pre-click snapshot.
          if (typeof model.pushAttributes === 'function') {
            model.pushAttributes({ votes: before.votes, userVote: before.userVote });
          }
          if (typeof m !== 'undefined' && m.redraw) {
            m.redraw();
          }
          throw err;
        })
        .finally(() => {
          pending.delete(key);
        });
    },
  };
}

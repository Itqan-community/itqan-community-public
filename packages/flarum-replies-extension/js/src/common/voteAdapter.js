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

  return {
    // Only signed-in users can vote.
    isAvailable: () => Boolean(app && app.session && app.session.user),

    getScore: (post) => readNumber(post, 'votes'),

    getUserVote: (post) => readVote(post),

    vote(post, direction) {
      if (!post) return Promise.resolve();

      const value = direction === 'up' || direction === 'down' ? direction : null;
      const url = `${app.forum.attribute('apiUrl')}/mtareq-nested-replies/posts/${post.id()}/vote`;

      return app
        .request({
          method: 'POST',
          url,
          body: { direction: value },
        })
        .then((payload) => {
          if (payload && payload.data && app.store && typeof app.store.pushPayload === 'function') {
            app.store.pushPayload(payload);
          }

          if (typeof m !== 'undefined' && m.redraw) {
            m.redraw();
          }

          return payload;
        });
    },
  };
}


import { describe, it, expect, vi } from 'vitest';
import { createVoteAdapter } from './voteAdapter';

function fakeApp({ user = { id: 1 }, payload = { data: { type: 'posts', id: '4' } } } = {}) {
  return {
    forum: {
      attribute: (name) => (name === 'apiUrl' ? 'http://localhost/api' : null),
    },
    session: { user },
    store: { pushPayload: vi.fn() },
    request: vi.fn(() => Promise.resolve(payload)),
  };
}

function fakePost(attributes = {}) {
  return {
    id: () => '4',
    attribute: (name) => attributes[name],
  };
}

describe('createVoteAdapter', () => {
  it('is available to signed-in users only', () => {
    expect(createVoteAdapter(fakeApp()).isAvailable()).toBe(true);
    expect(createVoteAdapter(fakeApp({ user: null })).isAvailable()).toBe(false);
  });

  it('reads the score and the current user vote from post attributes', () => {
    const adapter = createVoteAdapter(fakeApp());
    const post = fakePost({ votes: 7, userVote: 'down' });

    expect(adapter.getScore(post)).toBe(7);
    expect(adapter.getUserVote(post)).toBe('down');
  });

  it('defaults a missing score to 0 and missing/invalid votes to null', () => {
    const adapter = createVoteAdapter(fakeApp());

    // Score defaults to a numeric 0 (contract from 4d916764 "default numeric
    // score") so consumers can render it directly without null checks.
    expect(adapter.getScore(fakePost())).toBe(0);
    expect(adapter.getUserVote(fakePost({ userVote: 'sideways' }))).toBeNull();
  });

  it('posts an up vote and pushes the updated post', async () => {
    const app = fakeApp();
    const adapter = createVoteAdapter(app);

    await adapter.vote(fakePost(), 'up');

    expect(app.request).toHaveBeenCalledWith(
      expect.objectContaining({
        method: 'POST',
        url: 'http://localhost/api/mtareq-nested-replies/posts/4/vote',
        body: { direction: 'up' },
      })
    );
    expect(app.store.pushPayload).toHaveBeenCalled();
  });

  it('clears a vote by sending a null direction', async () => {
    const app = fakeApp();
    const adapter = createVoteAdapter(app);

    await adapter.vote(fakePost(), null);

    expect(app.request).toHaveBeenCalledWith(expect.objectContaining({ body: { direction: null } }));
  });

  // Task 9: rollback / response-payload / in-flight guard / list-row target / guest guard.
  it('rolls back optimistic attributes when the request fails', async () => {
    const app = fakeApp();
    app.request = vi.fn(() => Promise.reject(new Error('network')));
    const adapter = createVoteAdapter(app);
    const attrs = { votes: 7, userVote: null };
    const post = fakePost(attrs);
    post.pushAttributes = vi.fn((next) => Object.assign(attrs, next));

    await expect(adapter.vote(post, 'up')).rejects.toThrow('network');

    // Rollback restores the snapshot taken before the request.
    expect(attrs.votes).toBe(7);
    expect(attrs.userVote).toBeNull();
    // Stronger than final state alone: the optimistic update must actually
    // have happened first (otherwise "unchanged" would pass vacuously on an
    // impl with no optimistic update at all), then been reverted.
    expect(post.pushAttributes).toHaveBeenCalledTimes(2);
    expect(post.pushAttributes).toHaveBeenNthCalledWith(1, { votes: 8, userVote: 'up' });
    expect(post.pushAttributes).toHaveBeenNthCalledWith(2, { votes: 7, userVote: null });
  });

  it('applies the response payload attributes to the model', async () => {
    const app = fakeApp({
      payload: { data: { type: 'posts', id: '4', attributes: { votes: 8, userVote: 'up' } } },
    });
    const adapter = createVoteAdapter(app);
    const attrs = { votes: 7, userVote: null };
    const post = fakePost(attrs);
    post.pushAttributes = vi.fn((next) => Object.assign(attrs, next));

    await adapter.vote(post, 'up');

    expect(post.pushAttributes).toHaveBeenCalledWith({ votes: 8, userVote: 'up' });
    expect(attrs.votes).toBe(8);
  });

  it('ignores a second vote while one is in flight', async () => {
    const app = fakeApp();
    let resolveRequest;
    app.request = vi.fn(() => new Promise((r) => { resolveRequest = r; }));
    const adapter = createVoteAdapter(app);
    const post = fakePost({ votes: 0, userVote: null });
    post.pushAttributes = vi.fn();

    const first = adapter.vote(post, 'up');
    const second = adapter.vote(post, 'down');

    resolveRequest({ data: { type: 'posts', id: '4', attributes: { votes: 1, userVote: 'up' } } });
    await first;
    await second;

    expect(app.request).toHaveBeenCalledTimes(1);
  });

  it('votes on a discussion row using an explicit post id', async () => {
    const app = fakeApp();
    const adapter = createVoteAdapter(app);
    const discussion = {
      id: () => '9',
      attribute: (k) => ({ votes: 3, userVote: null, firstPostId: 42 })[k],
      pushAttributes: vi.fn(),
    };

    await adapter.vote(discussion, 'up', { id: 42 });

    expect(app.request).toHaveBeenCalledWith(
      expect.objectContaining({ url: 'http://localhost/api/mtareq-nested-replies/posts/42/vote' })
    );
  });

  it('rejects guest votes without firing a request', async () => {
    const app = fakeApp({ user: null });
    const adapter = createVoteAdapter(app);

    const result = await adapter.vote(fakePost({ votes: 1 }), 'up');

    expect(app.request).not.toHaveBeenCalled();
    expect(result).toEqual({ rejected: 'guest' });
  });
});

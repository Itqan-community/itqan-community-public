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
  const store = { ...attributes };
  const post = {
    id: () => '4',
    attribute: (name) => store[name],
    pushAttributes: vi.fn((attrs) => {
      Object.assign(store, attrs);
    }),
    _store: store,
  };
  return post;
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

  it('returns 0 (never blank) for a missing or unparseable score', () => {
    const adapter = createVoteAdapter(fakeApp());

    expect(adapter.getScore(fakePost())).toBe(0);
    expect(adapter.getScore(fakePost({ votes: 'not-a-number' }))).toBe(0);
  });

  it('returns null for missing or invalid userVote', () => {
    const adapter = createVoteAdapter(fakeApp());

    expect(adapter.getUserVote(fakePost())).toBeNull();
    expect(adapter.getUserVote(fakePost({ userVote: 'sideways' }))).toBeNull();
  });

  it('rejects votes from guests without hitting the API', async () => {
    const app = fakeApp({ user: null });
    const adapter = createVoteAdapter(app);
    const post = fakePost();

    const result = await adapter.vote(post, 'up');

    expect(result).toEqual({ rejected: 'guest' });
    expect(app.request).not.toHaveBeenCalled();
    expect(post.pushAttributes).not.toHaveBeenCalled();
  });

  it('applies the vote optimistically before the request resolves', async () => {
    const app = fakeApp();
    const adapter = createVoteAdapter(app);
    const post = fakePost({ votes: 3, userVote: null });

    await adapter.vote(post, 'up');

    // Optimistic apply must run before the request is sent.
    const applyOrder = post.pushAttributes.mock.invocationCallOrder[0];
    const requestOrder = app.request.mock.invocationCallOrder[0];
    expect(applyOrder).toBeLessThan(requestOrder);
    // First call writes the optimistic value (3 + 1, userVote='up').
    expect(post.pushAttributes).toHaveBeenNthCalledWith(1, { votes: 4, userVote: 'up' });
  });

  it('reconciles the post with the server\'s votes/userVote on success', async () => {
    const serverPayload = {
      data: {
        type: 'posts',
        id: '4',
        attributes: { votes: 99, userVote: 'down' },
      },
    };
    const app = fakeApp({ payload: serverPayload });
    const adapter = createVoteAdapter(app);
    const post = fakePost({ votes: 0, userVote: null });

    await adapter.vote(post, 'up');

    // Final attributes reflect the server, not the optimistic value.
    expect(post.pushAttributes).toHaveBeenLastCalledWith({ votes: 99, userVote: 'down' });
    expect(app.store.pushPayload).toHaveBeenCalledWith(serverPayload);
  });

  it('rolls back the optimistic state and rethrows on failure', async () => {
    const boom = new Error('network down');
    const app = fakeApp();
    app.request = vi.fn(() => Promise.reject(boom));
    const adapter = createVoteAdapter(app);
    const post = fakePost({ votes: 5, userVote: null });

    await expect(adapter.vote(post, 'up')).rejects.toBe(boom);

    // First call: optimistic apply (5 + 1 = 6, userVote='up').
    // Second call: rollback to the snapshot (5, null).
    expect(post.pushAttributes).toHaveBeenCalledTimes(2);
    expect(post.pushAttributes).toHaveBeenLastCalledWith({ votes: 5, userVote: null });
  });

  it('short-circuits a second vote on the same post while the first is in flight', async () => {
    const resolvers = [];
    const app = fakeApp();
    app.request = vi.fn(
      () => new Promise((resolve) => { resolvers.push(resolve); })
    );
    const adapter = createVoteAdapter(app);
    const post = fakePost();

    const first = adapter.vote(post, 'up');
    const second = await adapter.vote(post, 'down');

    expect(second).toEqual({ rejected: 'inflight' });
    expect(app.request).toHaveBeenCalledTimes(1);

    resolvers[0]({ data: { type: 'posts', id: '4', attributes: { votes: 1, userVote: 'up' } } });
    await first;

    // Once the first settles, the post is no longer pending and a fresh call
    // goes through.
    const third = adapter.vote(post, 'down');
    expect(app.request).toHaveBeenCalledTimes(2);
    resolvers[1]({ data: { type: 'posts', id: '4', attributes: { votes: 0, userVote: 'down' } } });
    await third;
  });

  it('clears a vote by sending a null direction', async () => {
    const app = fakeApp();
    const adapter = createVoteAdapter(app);
    const post = fakePost({ votes: 1, userVote: 'up' });

    await adapter.vote(post, null);

    expect(app.request).toHaveBeenCalledWith(expect.objectContaining({ body: { direction: null } }));
    // Clearing an existing up-vote drops the score by 1.
    expect(post.pushAttributes).toHaveBeenNthCalledWith(1, { votes: 0, userVote: null });
  });
});
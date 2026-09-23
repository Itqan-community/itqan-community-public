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
});

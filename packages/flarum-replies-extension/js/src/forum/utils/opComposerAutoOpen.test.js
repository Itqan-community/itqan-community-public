import { describe, it, expect } from 'vitest';
import { canOpenOpComposer, createOpComposerAutoOpen } from './opComposerAutoOpen';

describe('canOpenOpComposer', () => {
  const ready = {
    isCurrentDiscussion: true,
    hasFirstPost: true,
    hasUser: true,
    canReply: true,
    composerVisible: false,
    inlineReplyOpen: false,
  };

  it('opens for a logged-in reader once the original post is loaded', () => {
    expect(canOpenOpComposer(ready)).toBe(true);
  });

  it('waits until the stream owns the discussion being viewed', () => {
    // On an SPA navigation the page update can run before the stream has swapped
    // in the new discussion, leaving `allPosts` holding the previous one.
    expect(canOpenOpComposer({ ...ready, isCurrentDiscussion: false })).toBe(false);
  });

  it('waits until the original post is loaded', () => {
    expect(canOpenOpComposer({ ...ready, hasFirstPost: false })).toBe(false);
  });

  it('never opens for a guest', () => {
    expect(canOpenOpComposer({ ...ready, hasUser: false })).toBe(false);
  });

  it('never opens when the reader cannot reply', () => {
    expect(canOpenOpComposer({ ...ready, canReply: false })).toBe(false);
  });

  it('does not hijack an open composer', () => {
    expect(canOpenOpComposer({ ...ready, composerVisible: true })).toBe(false);
  });

  it('does not stomp a reply form that is already open', () => {
    expect(canOpenOpComposer({ ...ready, inlineReplyOpen: true })).toBe(false);
  });

  it('takes no viewport argument, so phones are allowed too', () => {
    // The gate is deliberately viewport-agnostic: the same composer opens on a
    // phone and a wide screen. A width parameter would mean mobile was excluded.
    expect(canOpenOpComposer.length).toBe(1);
    expect(canOpenOpComposer(ready)).toBe(true);
  });
});

describe('createOpComposerAutoOpen', () => {
  it('opens once per discussion visit', () => {
    const guard = createOpComposerAutoOpen();

    expect(guard.claim('7', true)).toBe(true);
    expect(guard.claim('7', true)).toBe(false);
  });

  it('does not latch a skipped attempt (regression: composer gone until reload)', () => {
    const guard = createOpComposerAutoOpen();

    // First update: the tree is still loading (or a composer is already open),
    // so nothing opens. This must not count as "done".
    expect(guard.claim('7', false)).toBe(false);

    // A later update, once the original post has arrived, must still get its
    // chance — including after navigating back and forth between discussions.
    expect(guard.claim('7', true)).toBe(true);
  });

  it('re-arms for a different discussion', () => {
    const guard = createOpComposerAutoOpen();

    expect(guard.claim('7', true)).toBe(true);
    expect(guard.claim('8', true)).toBe(true);
  });

  it('re-arms after an explicit reset (discussion change)', () => {
    const guard = createOpComposerAutoOpen();

    expect(guard.claim('7', true)).toBe(true);

    guard.reset();

    expect(guard.claim('7', true)).toBe(true);
  });

  it('never opens without a discussion id', () => {
    const guard = createOpComposerAutoOpen();

    expect(guard.claim('', true)).toBe(false);
    expect(guard.claim(null, true)).toBe(false);
    expect(guard.claim(undefined, true)).toBe(false);
  });
});

import { describe, it, expect } from 'vitest';
import { unreadReplyCount } from './unread';

function fakeDiscussion(attrs = {}) {
  return {
    commentCount: () => attrs.commentCount,
    attribute: (k) => (k === 'lastReadPostNumber' ? attrs.lastReadPostNumber : undefined),
  };
}

describe('unreadReplyCount', () => {
  it('returns null when the reader has no read marker (guest / never read)', () => {
    expect(unreadReplyCount(fakeDiscussion({ commentCount: 12 }))).toBeNull();
    expect(unreadReplyCount(fakeDiscussion({ commentCount: 12, lastReadPostNumber: null }))).toBeNull();
  });

  it('returns null for a fully-read discussion', () => {
    expect(unreadReplyCount(fakeDiscussion({ commentCount: 12, lastReadPostNumber: 12 }))).toBeNull();
    expect(unreadReplyCount(fakeDiscussion({ commentCount: 12, lastReadPostNumber: 99 }))).toBeNull();
  });

  it('returns the number of unread replies when behind', () => {
    expect(unreadReplyCount(fakeDiscussion({ commentCount: 12, lastReadPostNumber: 9 }))).toBe(3);
    expect(unreadReplyCount(fakeDiscussion({ commentCount: 5, lastReadPostNumber: 1 }))).toBe(4);
  });

  it('returns null for malformed input', () => {
    expect(unreadReplyCount(null)).toBeNull();
    expect(unreadReplyCount(fakeDiscussion({ commentCount: 'x', lastReadPostNumber: 2 }))).toBeNull();
  });
});

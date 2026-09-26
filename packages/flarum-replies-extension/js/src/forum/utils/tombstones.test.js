import { describe, it, expect } from 'vitest';
import { firstChildOfMissingParent } from './tombstones';

const p = (id, replyToPostId) => ({
  id: () => String(id),
  number: () => id,
  attribute: (k) => (k === 'replyToPostId' ? replyToPostId : undefined),
});

describe('firstChildOfMissingParent', () => {
  it('marks only the lowest-numbered child of a missing parent', () => {
    // Parent 5 is absent from the store (deleted+purged / out of scope).
    const posts = [p(6, 5), p(7, 5), p(8, 5)];
    const exists = () => false;

    expect(firstChildOfMissingParent(posts, exists)).toEqual(new Set(['6']));
  });

  it('marks nothing when the parent exists', () => {
    const posts = [p(6, 5), p(7, 5)];
    const exists = (id) => String(id) === '5';

    expect(firstChildOfMissingParent(posts, exists).size).toBe(0);
  });

  it('ignores root posts (no stored parent)', () => {
    const posts = [p(6, null), p(7, undefined)];
    expect(firstChildOfMissingParent(posts, () => false).size).toBe(0);
  });

  it('treats different missing parents independently', () => {
    const posts = [p(6, 5), p(7, 5), p(9, 8), p(10, 8)];
    expect(firstChildOfMissingParent(posts, () => false)).toEqual(new Set(['6', '9']));
  });

  it('handles store records without a numeric id', () => {
    const posts = [{ id: () => null, attribute: () => 5 }, p(6, 5)];
    expect(firstChildOfMissingParent(posts, () => false)).toEqual(new Set(['6']));
  });
});

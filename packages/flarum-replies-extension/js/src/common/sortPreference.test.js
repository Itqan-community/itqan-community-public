import { describe, it, expect, vi, beforeEach } from 'vitest';
import { readSortPreference, writeSortPreference } from './sortPreference';

const KEY = 'mtareq-nested-replies.sortMode';
const VALID = ['oldest', 'newest', 'top', 'replies'];

function fakeStorage(initial = {}) {
  const map = new Map(Object.entries(initial));
  return {
    getItem: (k) => (map.has(k) ? map.get(k) : null),
    setItem: (k, v) => map.set(k, String(v)),
    removeItem: (k) => map.delete(k),
  };
}

describe('sortPreference', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('returns the stored mode when it is valid', () => {
    const storage = fakeStorage({ [KEY]: 'top' });
    expect(readSortPreference(storage)).toBe('top');
  });

  it('falls back to oldest for unknown or missing values', () => {
    expect(readSortPreference(fakeStorage())).toBe('oldest');
    expect(readSortPreference(fakeStorage({ [KEY]: 'sideways' }))).toBe('oldest');
    expect(readSortPreference(null)).toBe('oldest');
  });

  it('persists a valid mode', () => {
    const storage = fakeStorage();
    // Task 10 fix: drafted as writeSortPreference(storage, 'newest') — impl
    // signature is (mode, storage); plan flagged two such calls, there are
    // three (:1951, :1957, :1967). All corrected here.
    writeSortPreference('newest', storage);
    expect(storage.getItem(KEY)).toBe('newest');
  });

  it('ignores invalid modes when writing', () => {
    const storage = fakeStorage();
    writeSortPreference('bogus', storage);
    expect(storage.getItem(KEY)).toBeNull();
  });

  it('survives a throwing storage (private mode / disabled storage)', () => {
    const throwing = {
      getItem: () => { throw new Error('denied'); },
      setItem: () => { throw new Error('denied'); },
    };
    expect(readSortPreference(throwing)).toBe('oldest');
    expect(() => writeSortPreference('top', throwing)).not.toThrow();
  });
});

import { describe, it, expect } from 'vitest';
import { detectMention } from './mentionQuery';

describe('detectMention', () => {
  it('returns null when there is no @', () => {
    expect(detectMention('plain text', 10)).toBeNull();
  });

  it('detects a bare @ at the start', () => {
    expect(detectMention('@', 1)).toEqual({ start: 0, query: '' });
  });

  it('detects a query after whitespace', () => {
    expect(detectMention('hi @ab', 6)).toEqual({ start: 3, query: 'ab' });
  });

  it('detects a query at the very start', () => {
    expect(detectMention('@ab', 3)).toEqual({ start: 0, query: 'ab' });
  });

  it('ignores an @ glued to a word (email-like)', () => {
    expect(detectMention('hi@ab', 5)).toBeNull();
  });

  it('stops at whitespace inside the query', () => {
    expect(detectMention('hi @a b', 7)).toBeNull();
  });

  it('stops after a trailing space', () => {
    expect(detectMention('hi @ab ', 7)).toBeNull();
  });

  it('only looks before the cursor', () => {
    expect(detectMention('@ab hi', 3)).toEqual({ start: 0, query: 'ab' });
  });

  it('handles non-string input', () => {
    expect(detectMention(null, 0)).toBeNull();
  });
});

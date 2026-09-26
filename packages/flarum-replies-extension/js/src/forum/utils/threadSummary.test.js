import { describe, it, expect } from 'vitest';
import { summarizeThread } from './threadSummary';

function post(id, userId, createdAt) {
  return {
    id: () => String(id),
    attribute: (k) => (k === 'createdAt' ? createdAt : undefined),
    user: () => (userId == null ? null : { id: () => String(userId) }),
  };
}

describe('summarizeThread', () => {
  it('returns null for empty member lists', () => {
    expect(summarizeThread([], () => null)).toBeNull();
    expect(summarizeThread(null, () => null)).toBeNull();
  });

  it('collects distinct authors in first-seen order and the latest timestamp', () => {
    const byId = {
      11: post(11, 1, '2026-09-20T10:00:00Z'),
      12: post(12, 2, '2026-09-21T10:00:00Z'),
      13: post(13, 1, '2026-09-22T10:00:00Z'),
      14: post(14, 3, '2026-09-19T10:00:00Z'),
    };
    const lookup = (id) => byId[String(id)] || null;

    const summary = summarizeThread(['11', '12', '13', '14'], lookup);

    expect(summary.authors.map((u) => u.id())).toEqual(['1', '2', '3']);
    expect(summary.latestAt).toBe('2026-09-22T10:00:00Z');
    expect(summary.count).toBe(4);
  });

  it('drops unresolvable ids and authors, keeping the rest', () => {
    const byId = { 12: post(12, 2, '2026-09-21T10:00:00Z'), 13: post(13, null, '2026-09-22T10:00:00Z') };
    const summary = summarizeThread(['11', '12', '13'], (id) => byId[String(id)] || null);

    expect(summary.authors.map((u) => u.id())).toEqual(['2']);
    expect(summary.latestAt).toBe('2026-09-22T10:00:00Z'); // authorless post still sets latest
    expect(summary.count).toBe(3); // members resolved by id, regardless of author
  });

  it('caps authors at 5 while counting all members', () => {
    const byId = {};
    for (let i = 1; i <= 8; i++) byId[i] = post(i, i, '2026-09-22T10:00:00Z');
    const summary = summarizeThread(Object.keys(byId), (id) => byId[String(id)]);

    expect(summary.authors.length).toBe(5);
    expect(summary.count).toBe(8);
  });
});

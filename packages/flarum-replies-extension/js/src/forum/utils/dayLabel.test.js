import { describe, it, expect } from 'vitest';
import { buildDayLabelMap } from './dayLabel';

const TODAY = '2026-09-23';
const YESTERDAY = '2026-09-22';

function post(id, createdAt) {
  return { id: () => String(id), attribute: (k) => (k === 'createdAt' ? createdAt : undefined) };
}

const format = (isoDate, today) => {
  if (isoDate === today) return 'Today';
  return `D:${isoDate}`; // caller-owned formatter (translator / Intl)
};

describe('buildDayLabelMap', () => {
  it('labels the first post of each calendar day and nulls the rest', () => {
    const posts = [
      post(1, '2026-09-21T10:00:00Z'),
      post(2, '2026-09-21T11:00:00Z'), // same day as 1 -> null
      post(3, '2026-09-22T09:00:00Z'), // new day -> label
      post(4, '2026-09-23T08:00:00Z'), // new day -> label (== today -> 'Today')
      post(5, '2026-09-23T20:00:00Z'), // same day -> null
    ];

    expect(buildDayLabelMap(posts, TODAY, format)).toEqual({
      1: 'D:2026-09-21',
      2: null,
      3: 'D:2026-09-22',
      4: 'Today',
      5: null,
    });
  });

  it('uses the UTC calendar day so server-side dates are stable', () => {
    // 2026-09-23T23:30Z is still 09-23 in UTC regardless of viewer timezone.
    const posts = [post(1, '2026-09-23T23:30:00Z')];
    expect(buildDayLabelMap(posts, TODAY, format)[1]).toBe('Today');
  });

  it('skips posts with no createdAt and never crosses discussions', () => {
    const posts = [
      post(1, undefined),
      post(2, '2026-09-23T10:00:00Z'),
    ];
    expect(buildDayLabelMap(posts, TODAY, format)).toEqual({ 2: 'Today' });
  });

  it('returns an empty map for no posts', () => {
    expect(buildDayLabelMap([], TODAY, format)).toEqual({});
  });
});

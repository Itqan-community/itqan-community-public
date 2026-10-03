import { describe, it, expect } from 'vitest';
import { withCatalogSorts } from './discussionSortMap';

// Core's shape (DiscussionListState.prototype.sortMap()).
const core = () => ({
  latest: '-lastPostedAt',
  top: '-commentCount',
  newest: '-createdAt',
  oldest: 'createdAt',
});

describe('withCatalogSorts', () => {
  it('emits the catalog keys in order, dropping top', () => {
    expect(Object.keys(withCatalogSorts(core()))).toEqual([
      'latest',
      'votes',
      'newest',
      'oldest',
      'replies',
    ]);
  });

  it('maps the vote and reply sorts', () => {
    const map = withCatalogSorts(core());
    expect(map.votes).toBe('-votes');
    expect(map.replies).toBe('-commentCount');
    expect(map).not.toHaveProperty('top');
    expect(map).not.toHaveProperty('hot');
  });

  it('drops hot even when present', () => {
    const map = withCatalogSorts({ ...core(), hot: '-hotness' });
    expect(map).not.toHaveProperty('hot');
    expect(Object.keys(map)).toEqual(['latest', 'votes', 'newest', 'oldest', 'replies']);
  });

  it('keeps core values for the untouched sorts', () => {
    const map = withCatalogSorts(core());
    expect(map.latest).toBe('-lastPostedAt');
    expect(map.newest).toBe('-createdAt');
    expect(map.oldest).toBe('createdAt');
  });

  it('keeps relevance first while searching', () => {
    const map = withCatalogSorts({ relevance: '', ...core() });
    expect(Object.keys(map)).toEqual([
      'relevance',
      'latest',
      'votes',
      'newest',
      'oldest',
      'replies',
    ]);
    expect(map.relevance).toBe('');
  });

  it('does not mutate its input', () => {
    const input = core();
    withCatalogSorts(input);
    expect(input).toEqual(core());
  });

  it('tolerates an empty map', () => {
    expect(Object.keys(withCatalogSorts())).toEqual(['latest', 'votes', 'newest', 'oldest', 'replies']);
  });
});

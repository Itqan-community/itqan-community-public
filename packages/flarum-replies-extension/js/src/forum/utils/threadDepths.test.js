import { describe, it, expect } from 'vitest';
import {
  getParentId,
  getDepth,
  getAncestorIds,
  isHidden,
  getReplyTarget,
  planSiblingFolding,
  getLeadingMentionId,
  isDerivedParent,
} from './threadDepths';

function build(pairs) {
  const posts = {};

  function makePost(id, parentId) {
    return {
      id: () => String(id),
      number: () => Number(id),
      attribute: (name) => (name === 'replyToPostId' ? parentId : undefined),
      user: () => null,
    };
  }

  for (const [id, parent] of pairs) {
    posts[id] = makePost(id, parent);
  }

  return { posts, lookup: (id) => posts[String(id)] || null };
}

describe('getParentId', () => {
  it('reads the stored replyToPostId attribute', () => {
    expect(getParentId({ id: () => '5', attribute: () => 4 })).toBe('4');
  });

  it('returns null without a stored parent', () => {
    expect(getParentId({ id: () => '1', attribute: () => null })).toBeNull();
    expect(getParentId({ id: () => '1', attribute: () => undefined })).toBeNull();
    expect(getParentId({ id: () => '1', attribute: () => '' })).toBeNull();
    expect(getParentId({ id: () => '1' })).toBeNull();
    expect(getParentId(null)).toBeNull();
  });

  it('ignores mention data entirely', () => {
    const post = {
      id: () => '7',
      attribute: () => null,
      mentionsPosts: () => [{ id: () => '4' }],
      contentHtml: () => '<a class="PostMention" data-id="4">x</a>',
    };
    expect(getParentId(post)).toBeNull();
  });
});

describe('getReplyTarget', () => {
  it('resolves the parent post and its author from the store', () => {
    const parent = { id: () => '4', user: () => ({ displayName: () => 'admin' }) };
    const post = { id: () => '5', attribute: () => 4 };
    expect(getReplyTarget(post, () => parent)).toEqual({ id: '4', name: 'admin', post: parent });
  });

  it('returns null when there is no stored parent', () => {
    expect(getReplyTarget({ id: () => '5', attribute: () => null }, () => null)).toBeNull();
  });

  it('returns null when the parent is not loaded', () => {
    expect(getReplyTarget({ id: () => '5', attribute: () => 4 }, () => null)).toBeNull();
  });
});

describe('getDepth', () => {
  it('returns 0 for a root post', () => {
    const { posts, lookup } = build([['1', null]]);
    expect(getDepth(posts['1'], 10, lookup)).toBe(0);
  });

  it('treats a reply to the original post as top-level', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
    ]);
    expect(getDepth(posts['2'], 10, lookup)).toBe(0);
  });

  it('does not count the original post in the ancestor depth', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
    ]);
    expect(getDepth(posts['3'], 10, lookup)).toBe(1);
  });

  it('counts nested ancestors below the first reply', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
      ['4', 3],
    ]);
    expect(getDepth(posts['4'], 10, lookup)).toBe(2);
  });

  it('caps the depth at maxDepth', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
      ['4', 3],
    ]);
    expect(getDepth(posts['4'], 1, lookup)).toBe(1);
  });

  it('treats an unloaded parent as a root', () => {
    const orphan = { id: () => '9', attribute: () => 404 };
    expect(getDepth(orphan, 10, () => null)).toBe(0);
  });

  it('terminates on a cyclic parent graph', () => {
    const a = { id: () => '1', attribute: () => 2 };
    const b = { id: () => '2', attribute: () => 1 };
    const lookup = (id) => (String(id) === '1' ? a : b);
    expect(getDepth(a, 100, lookup)).toBe(2);
  });
});

describe('getAncestorIds', () => {
  it('lists ancestor ids nearest first', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
    ]);
    expect(getAncestorIds(posts['3'], lookup)).toEqual(['2', '1']);
  });
});

describe('isHidden', () => {
  it('is true when a collapsed ancestor exists', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
    ]);
    expect(isHidden(posts['3'], new Set(['2']), lookup)).toBe(true);
  });

  it('is false for the collapsed post itself and unrelated posts', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
    ]);
    expect(isHidden(posts['1'], new Set(['2']), lookup)).toBe(false);
    expect(isHidden(posts['2'], new Set(['2']), lookup)).toBe(false);
  });
});

describe('planSiblingFolding', () => {
  const order = (posts, ids) => ids.map((id) => posts[String(id)]);

  it('keeps the first reply and folds the rest', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
      ['4', 2],
      ['5', 2],
    ]);

    const plan = planSiblingFolding(order(posts, [2, 3, 4, 5]), { lookup, visibleReplies: 1 });

    expect([...plan.hidden].sort()).toEqual(['4', '5']);
    expect(plan.moreAfter.get('3')).toEqual([{ parentId: '2', count: 2, members: ['4', '5'], targetDepth: 1 }]);
  });

  it('never folds the original post direct replies', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 1],
      ['4', 1],
    ]);

    const plan = planSiblingFolding(order(posts, [2, 3, 4]), { lookup, visibleReplies: 1 });

    expect(plan.hidden.size).toBe(0);
    expect(plan.moreAfter.size).toBe(0);
  });

  it('honours a larger visible count', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
      ['4', 2],
      ['5', 2],
    ]);

    const plan = planSiblingFolding(order(posts, [2, 3, 4, 5]), { lookup, visibleReplies: 2 });

    expect([...plan.hidden]).toEqual(['5']);
    expect(plan.moreAfter.get('4')).toEqual([{ parentId: '2', count: 1, members: ['5'], targetDepth: 1 }]);
  });

  it('unfolds a group the reader expanded', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
      ['4', 2],
      ['5', 2],
    ]);

    const plan = planSiblingFolding(order(posts, [2, 3, 4, 5]), {
      lookup,
      visibleReplies: 1,
      expandedParents: new Set(['2']),
    });

    expect(plan.hidden.size).toBe(0);
    expect(plan.moreAfter.size).toBe(0);
  });

  it('anchors the control after the kept branch, at the hidden replies depth', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
      ['4', 2],
      ['5', 2],
      ['6', 3],
    ]);

    const plan = planSiblingFolding(order(posts, [2, 3, 6, 4, 5]), { lookup, visibleReplies: 1 });

    expect([...plan.hidden].sort()).toEqual(['4', '5']);
    expect(plan.moreAfter.get('6')).toEqual([{ parentId: '2', count: 2, members: ['4', '5'], targetDepth: 1 }]);
  });

  it('folds sibling groups at every depth and counts folded subtrees', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
      ['4', 2],
      ['5', 3],
      ['6', 3],
      ['7', 6],
    ]);

    const plan = planSiblingFolding(order(posts, [2, 3, 5, 6, 7, 4]), { lookup, visibleReplies: 1 });

    expect([...plan.hidden].sort()).toEqual(['4', '6', '7']);
    expect(plan.moreAfter.get('5')).toEqual([
      { parentId: '2', count: 1, members: ['4'], targetDepth: 1 },
      { parentId: '3', count: 2, members: ['6', '7'], targetDepth: 2 },
    ]);
  });

  it('does not anchor a control inside an already folded subtree', () => {
    const { posts, lookup } = build([
      ['1', null],
      ['2', 1],
      ['3', 2],
      ['4', 2],
      ['5', 3],
      ['6', 3],
      ['7', 4],
      ['8', 4],
    ]);

    const plan = planSiblingFolding(order(posts, [2, 3, 5, 6, 4, 7, 8]), { lookup, visibleReplies: 1 });

    expect([...plan.hidden].sort()).toEqual(['4', '6', '7', '8']);
    expect(plan.moreAfter.has('7')).toBe(false);
    expect(plan.moreAfter.has('8')).toBe(false);
  });
});

describe('planSiblingFolding with members', () => {
  const order = (posts, ids) => ids.map((id) => posts[String(id)]);

  it('exposes hidden member ids on each group for summaries', () => {
    // Parent 5 with 8 children (6..13); visibleReplies = 3 folds the last 5.
    const { posts, lookup } = build([
      ['1', null],
      ['5', 1],
      ['6', 5],
      ['7', 5],
      ['8', 5],
      ['9', 5],
      ['10', 5],
      ['11', 5],
      ['12', 5],
      ['13', 5],
    ]);

    const plan = planSiblingFolding(order(posts, [5, 6, 7, 8, 9, 10, 11, 12, 13]), {
      lookup,
      visibleReplies: 3,
    });

    const entries = plan.moreAfter.get('8');
    expect(entries).toHaveLength(1);
    const [entry] = entries;

    expect(entry.parentId).toBe('5');
    expect(entry.count).toBe(5);
    // Contract: members.length === count, all strings, all hidden, none visible.
    expect(entry.members).toEqual(['9', '10', '11', '12', '13']);
    expect(entry.members.length).toBe(entry.count);
    entry.members.forEach((id) => {
      expect(typeof id).toBe('string');
      expect(plan.hidden.has(id)).toBe(true);
    });
  });
});

describe('getLeadingMentionId', () => {
  it('reads a leading PostMention from the rendered content', () => {
    const post = { contentHtml: () => '<p><a href="#" class="PostMention" data-id="4">admin</a> hello</p>' };
    expect(getLeadingMentionId(post)).toBe('4');
  });

  it('ignores a mention that is embedded in text', () => {
    const post = { contentHtml: () => '<p>Nested reply to <a class="PostMention" data-id="4">admin</a></p>' };
    expect(getLeadingMentionId(post)).toBeNull();
  });

  it('falls back to a leading post mention in the raw content', () => {
    expect(getLeadingMentionId({ content: () => '@"admin"#p7 hello' })).toBe('7');
  });

  it('returns null without a leading mention', () => {
    expect(getLeadingMentionId({ contentHtml: () => '<p>hi</p>' })).toBeNull();
    expect(getLeadingMentionId(null)).toBeNull();
  });
});

describe('legacy mention parents', () => {
  const makePost = (id, { parentId = null, leadingMention = null } = {}) => ({
    id: () => String(id),
    number: () => Number(id),
    attribute: (name) => (name === 'replyToPostId' ? parentId : undefined),
    contentHtml: () => (leadingMention ? `<p><a class="PostMention" data-id="${leadingMention}">x</a></p>` : '<p>x</p>'),
    user: () => null,
  });

  it('uses the leading mention as the parent only when enabled', () => {
    const post = makePost(8, { leadingMention: 7 });
    expect(getParentId(post, false)).toBeNull();
    expect(getParentId(post, true)).toBe('7');
    expect(isDerivedParent(post, true)).toBe(true);
  });

  it('prefers the stored parent over a mention', () => {
    const post = makePost(8, { parentId: 3, leadingMention: 7 });
    expect(getParentId(post, true)).toBe('3');
    expect(isDerivedParent(post, true)).toBe(false);
  });

  it('caps a legacy reply at depth 1 and does not stack', () => {
    const posts = {
      1: makePost(1),
      2: makePost(2, { leadingMention: 1 }),
      3: makePost(3, { leadingMention: 2 }),
    };
    const lookup = (id) => posts[String(id)] || null;

    expect(getDepth(posts['2'], 10, lookup, true)).toBe(1);
    expect(getDepth(posts['3'], 10, lookup, true)).toBe(1);
    expect(getAncestorIds(posts['3'], lookup, true)).toEqual(['2']);
  });

  it('lets a stored reply nest below a legacy reply', () => {
    const posts = {
      1: makePost(1),
      2: makePost(2, { leadingMention: 1 }),
      3: makePost(3, { parentId: 2 }),
    };
    const lookup = (id) => posts[String(id)] || null;

    expect(getDepth(posts['3'], 10, lookup, true)).toBe(2);
  });
});

import { describe, it, expect } from 'vitest';
import { buildReplyData } from './replyData';

describe('buildReplyData', () => {
  it('builds a reply payload with a numeric parent id', () => {
    const discussion = { id: () => '3' };
    expect(buildReplyData('hi', '5', discussion)).toEqual({
      content: 'hi',
      replyToPostId: 5,
      relationships: { discussion },
    });
  });

  it('coerces a null target to 0 and a null content to an empty string', () => {
    expect(buildReplyData(null, null, {})).toEqual({
      content: '',
      replyToPostId: 0,
      relationships: { discussion: {} },
    });
  });
});

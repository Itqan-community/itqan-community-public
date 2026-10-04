import { describe, it, expect } from 'vitest';
import { readSettings } from './settings';

function fakeApp(attrs) {
  return { forum: { attribute: (name) => attrs[name] } };
}

const defaults = {
  enabled: true,
  maxDepth: 5,
  showVotes: true,
  showReplyTag: true,
  showRepliedIndicator: true,
  likeColor: '#ff4500',
  startAtFirstPost: true,
  visibleReplies: 1,
  showScrubber: true,
  replyForm: 'quick',
  highlightColor: '#00c853',
  legacyMentions: false,
};

describe('readSettings', () => {
  it('uses the serialized forum attributes', () => {
    const settings = readSettings(
      fakeApp({
        nestedRepliesEnabled: false,
        nestedRepliesMaxDepth: 3,
        nestedRepliesShowVotes: false,
        nestedRepliesShowReplyTag: false,
        nestedRepliesShowRepliedIndicator: false,
        nestedRepliesLikeColor: '#00ff00',
        nestedRepliesStartAtFirstPost: false,
        nestedRepliesVisibleReplies: 3,
        nestedRepliesShowScrubber: false,
        nestedRepliesReplyForm: 'composer',
        nestedRepliesHighlightColor: '#123456',
        nestedRepliesLegacyMentions: true,
      })
    );
    expect(settings).toEqual({
      enabled: false,
      maxDepth: 3,
      showVotes: false,
      showReplyTag: false,
      showRepliedIndicator: false,
      likeColor: '#00ff00',
      startAtFirstPost: false,
      visibleReplies: 3,
      showScrubber: false,
      replyForm: 'composer',
      highlightColor: '#123456',
      legacyMentions: true,
    });
  });

  it('falls back to defaults when attributes are missing', () => {
    expect(readSettings(fakeApp({}))).toEqual(defaults);
  });

  it('falls back to defaults when app is absent', () => {
    expect(readSettings(null)).toEqual(defaults);
  });

  it('coerces an unknown reply form to quick', () => {
    expect(readSettings(fakeApp({ nestedRepliesReplyForm: 'nonsense' })).replyForm).toBe('quick');
  });

  it('reads the serialized attributes from the initial payload before boot', () => {
    const app = {
      data: {
        resources: [
          {
            type: 'forums',
            id: '1',
            attributes: { nestedRepliesShowReplyTag: false, nestedRepliesShowRepliedIndicator: false, nestedRepliesMaxDepth: 2 },
          },
        ],
      },
    };
    expect(readSettings(app)).toEqual({
      ...defaults,
      maxDepth: 2,
      showReplyTag: false,
      showRepliedIndicator: false,
    });
  });
});

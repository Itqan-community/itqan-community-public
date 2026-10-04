function forumAttribute(app, name) {
  if (!app) return undefined;

  // After boot, `app.forum` is the Forum model.
  if (app.forum && typeof app.forum.attribute === 'function') {
    const value = app.forum.attribute(name);
    if (value !== undefined && value !== null) return value;
  }

  // Flarum runs initializers before assigning `app.forum`, so fall back to the
  // initial JSON:API payload, which already contains the serialized forum.
  const resources = app.data && Array.isArray(app.data.resources) ? app.data.resources : [];
  const forum = resources.find((resource) => resource && resource.type === 'forums');
  const value = forum && forum.attributes ? forum.attributes[name] : undefined;

  return value === undefined || value === null ? undefined : value;
}

export function readSettings(app) {
  const read = (name, fallback) => {
    const value = forumAttribute(app, name);
    return value === undefined || value === null ? fallback : value;
  };

  return {
    enabled: Boolean(read('nestedRepliesEnabled', true)),
    maxDepth: Number(read('nestedRepliesMaxDepth', 5)) || 0,
    showVotes: Boolean(read('nestedRepliesShowVotes', true)),
    showReplyTag: Boolean(read('nestedRepliesShowReplyTag', true)),
    showRepliedIndicator: Boolean(read('nestedRepliesShowRepliedIndicator', true)),
    likeColor: String(read('nestedRepliesLikeColor', '#ff4500')),
    startAtFirstPost: Boolean(read('nestedRepliesStartAtFirstPost', true)),
    visibleReplies: Number(read('nestedRepliesVisibleReplies', 1)) || 1,
    showScrubber: Boolean(read('nestedRepliesShowScrubber', true)),
    replyForm: read('nestedRepliesReplyForm', 'quick') === 'composer' ? 'composer' : 'quick',
    highlightColor: String(read('nestedRepliesHighlightColor', '#00c853')),
    legacyMentions: Boolean(read('nestedRepliesLegacyMentions', false)),
  };
}

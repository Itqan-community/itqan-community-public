// Whether the original post's inline reply composer may auto-open right now.
// Deliberately viewport-agnostic: a phone reader gets the same composer as a
// wide-screen one, so this takes no width/screen argument.
//
// `isCurrentDiscussion` matters because `allPosts` is a module-level cache that
// is only cleared once the stream has re-rendered for the new discussion. During
// an SPA navigation the page's `onupdate` can fire first, while `allPosts` still
// holds the *previous* discussion's posts — opening from those would target a
// post the new tree does not render. Refuse until the tree has caught up.
export function canOpenOpComposer({ isCurrentDiscussion, hasFirstPost, hasUser, canReply, composerVisible, inlineReplyOpen }) {
  return Boolean(isCurrentDiscussion && hasFirstPost && hasUser && canReply && !composerVisible && !inlineReplyOpen);
}

// One-shot, per-discussion guard for the auto-open.
//
// A *skipped* attempt — the tree is still loading, a composer is already open,
// a leftover reply form is present, the reader cannot reply — must never latch:
// the discussion stays eligible so a later update can still open it. Only a real
// open marks the discussion done, and `reset()` re-arms the guard when the
// discussion changes, so navigating back and forth keeps working.
export function createOpComposerAutoOpen() {
  let openedForId = null;

  return {
    claim(discussionId, canOpen) {
      const id = discussionId == null ? '' : String(discussionId);

      if (!id) return false;
      if (openedForId === id) return false;
      if (!canOpen) return false;

      openedForId = id;
      return true;
    },

    reset() {
      openedForId = null;
    },

    get openedFor() {
      return openedForId;
    },
  };
}

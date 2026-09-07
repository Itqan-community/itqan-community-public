import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Model from 'flarum/common/Model';
import Post from 'flarum/common/models/Post';
import CommentPost from 'flarum/forum/components/CommentPost';
import PostStream from 'flarum/forum/components/PostStream';
import ReplyComposer from 'flarum/forum/components/ReplyComposer';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import DiscussionListState from 'flarum/forum/states/DiscussionListState';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';
import icon from 'flarum/common/helpers/icon';
import extractText from 'flarum/common/utils/extractText';

import VoteButtons from './components/VoteButtons';
import { getPostDepth, isDescendantOfCollapsed, reorderStreamTree } from './components/CommentTree';

export { default as VoteButtons } from './components/VoteButtons';
export * from './components/CommentTree';

function clearActiveReplyTarget() {
  document.querySelectorAll('.is-reply-target').forEach((el) => {
    el.classList.remove('is-reply-target');
  });
}

function setActiveReplyTarget(postId) {
  clearActiveReplyTarget();
  if (!postId) return;
  const targetItem = document.querySelector(`.PostStream-item[data-id="${postId}"]`);
  if (targetItem) {
    targetItem.classList.add('is-reply-target');
  }
}

export function isMainPost(post) {
  if (!post) return false;
  if (typeof post.number === 'function' && post.number() === 1) return true;

  const discussion = typeof post.discussion === 'function' ? post.discussion() : null;
  if (discussion) {
    const firstPostId = (typeof discussion.attribute === 'function') ? discussion.attribute('firstPostId') : null;
    const postId = (typeof post.id === 'function') ? post.id() : null;
    if (firstPostId && postId && String(firstPostId) === String(postId)) return true;
    if (typeof discussion.firstPost === 'function' && discussion.firstPost() && postId && String(discussion.firstPost().id()) === String(postId)) return true;
  }
  return false;
}

app.initializers.add('itqan-discussions', () => {
  // ==========================================
  // 1. Voting System Extensions (PR #30)
  // ==========================================
  extend(DiscussionListState.prototype, 'sortMap', function (map) {
    map.top = '-votes';
    map.hot = '-hotness';
  });

  extend(DiscussionListItem.prototype, 'contentItems', function (items) {
    const discussion = this.attrs.discussion;
    if (discussion.attribute('votes') === undefined) return;

    items.add(
      'itqanVote',
      <VoteButtons model={discussion} postId={discussion.attribute('firstPostId')} vertical />,
      110
    );
  });

  extend(CommentPost.prototype, 'contentItems', function (items) {
    const post = this.attrs.post;
    if (!post || post.isHidden() || post.attribute('votes') === undefined) return;

    // Do NOT render votes on the main post itself (OP)
    if (isMainPost(post)) return;

    items.add('itqanVote', <VoteButtons model={post} postId={post.id()} vertical />, 120);
  });

  // ==========================================
  // 2. Threaded / Nested Replies Extension
  // ==========================================
  if (Model && Post && Post.prototype) {
    Post.prototype.parentId = Model.attribute('parentId');
    Post.prototype.replyCount = Model.attribute('replyCount');
  }

  // Global composer target state
  app.itqanActiveParentId = null;
  app.itqanActiveParentUsername = null;

  // Element attributes for depth indentation & OP styling
  extend(CommentPost.prototype, 'elementAttrs', function (attrs) {
    const post = this.attrs ? this.attrs.post : null;
    if (!post) return;

    if (isMainPost(post)) {
      attrs['data-is-op'] = 'true';
    }

    const depth = getPostDepth(post);
    if (depth > 0) {
      attrs['data-thread-depth'] = String(depth);
    }
  });

  // Reorder stream into recursive tree on render / update
  extend(CommentPost.prototype, 'oncreate', () => {
    reorderStreamTree();
  });
  extend(CommentPost.prototype, 'onupdate', () => {
    reorderStreamTree();
  });

  if (PostStream) {
    extend(PostStream.prototype, 'oncreate', () => {
      reorderStreamTree();
    });
    extend(PostStream.prototype, 'onupdate', () => {
      reorderStreamTree();
    });
  }

  // Header Items: "رد على @اسم" context badge with smooth scroll to parent
  extend(CommentPost.prototype, 'headerItems', function (items) {
    const post = this.attrs ? this.attrs.post : null;
    if (!post) return;

    const parentId = (typeof post.parentId === 'function') ? post.parentId() : null;
    if (parentId) {
      const parentPost = app.store ? app.store.getById('posts', String(parentId)) : null;
      // Guard: Do NOT show reply badge if parent is OP
      if (parentPost && isMainPost(parentPost)) {
        return;
      }

      const parentUser = parentPost && parentPost.user && parentPost.user() ? parentPost.user().displayName() : null;

      items.add(
        'itqan-reply-badge',
        m(
          'a',
          {
            className: 'itqan-reply-badge',
            href: '#',
            title: parentUser
              ? extractText(app.translator.trans('itqan-discussions.forum.replied_to', { username: parentUser }))
              : '',
            onclick: (e) => {
              e.preventDefault();
              const parentEl = document.querySelector(`.PostStream-item[data-id="${parentId}"]`);
              if (parentEl) {
                parentEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                parentEl.classList.add('flash');
                setTimeout(() => parentEl.classList.remove('flash'), 1500);
              }
            },
          },
          [
            icon ? icon('fas fa-reply') : null,
            ' ',
            parentUser
              ? app.translator.trans('itqan-discussions.forum.replied_to', { username: parentUser })
              : ('#' + parentId),
          ]
        ),
        70
      );
    }
  });

  // Action Items: Thread Collapse Toggle & Unified Reply Button
  extend(CommentPost.prototype, 'actionItems', function (items) {
    const post = this.attrs ? this.attrs.post : null;
    if (!post) return;

    const postIdStr = (typeof post.id === 'function') ? String(post.id()) : '';
    const isOP = isMainPost(post);
    const replyCount = (typeof post.replyCount === 'function') ? (post.replyCount() || 0) : 0;
    const isCollapsed = app.itqanCollapsedThreads.has(postIdStr);

    // Dynamic collapse/expand pill button (Only on comments with child replies, not OP)
    if (!isOP && replyCount > 0) {
      const labelText = isCollapsed ? `ردود (${replyCount})` : 'طي';
      const iconName = isCollapsed ? 'fas fa-plus' : 'fas fa-minus';

      items.add(
        'itqan-collapse-thread',
        m(
          'button',
          {
            key: `collapse-btn-${postIdStr}-${isCollapsed ? 'col' : 'exp'}`,
            className: 'Button Button--link',
            'data-post-id': postIdStr,
            onclick: (e) => {
              e.preventDefault();
              e.stopPropagation();
              const btn = e.currentTarget;
              const currentlyCollapsed = app.itqanCollapsedThreads.has(postIdStr);

              if (currentlyCollapsed) {
                app.itqanCollapsedThreads.delete(postIdStr);
                const labelEl = btn.querySelector('.thread-collapse-label');
                if (labelEl) labelEl.textContent = ' طي';
                const iconEl = btn.querySelector('.icon, i');
                if (iconEl) iconEl.className = 'icon fas fa-minus';
              } else {
                app.itqanCollapsedThreads.add(postIdStr);
                const labelEl = btn.querySelector('.thread-collapse-label');
                if (labelEl) labelEl.textContent = ` ردود (${replyCount})`;
                const iconEl = btn.querySelector('.icon, i');
                if (iconEl) iconEl.className = 'icon fas fa-plus';
              }

              reorderStreamTree();
            },
          },
          [
            icon ? icon(iconName) : null,
            m('span.thread-collapse-label', ` ${labelText}`),
          ]
        ),
        15
      );
    }

    // Direct reply action handler
    const replyActionHandler = (e) => {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }

      if (isOP) {
        // On OP (Post #1), open top-level discussion reply without nesting
        app.itqanActiveParentId = null;
        app.itqanActiveParentUsername = null;
        clearActiveReplyTarget();
      } else {
        app.itqanActiveParentId = post.id();
        app.itqanActiveParentUsername = (post.user && post.user()) ? post.user().displayName() : ('#' + post.id());
        setActiveReplyTarget(post.id());
      }

      const disc = post.discussion ? post.discussion() : null;
      if (disc && DiscussionControls && DiscussionControls.replyAction) {
        DiscussionControls.replyAction.call(disc).then(() => {
          app.composer.fields = app.composer.fields || {};
          app.composer.fields.parentId = app.itqanActiveParentId;
          app.composer.fields.replyToUsername = app.itqanActiveParentUsername;
          m.redraw();
        });
      }
    };

    if (items.has('reply')) {
      items.remove('reply');
    }
    items.add(
      'reply',
      m(
        'button',
        {
          className: 'Button Button--link',
          onclick: replyActionHandler,
        },
        [
          icon ? icon('fas fa-reply') : null,
          ' ',
          'رد',
        ]
      ),
      10
    );
  });

  // ReplyComposer Integration: Context Banner and Payload Passing
  if (ReplyComposer) {
    extend(ReplyComposer.prototype, 'headerItems', function (items) {
      const pId = app.itqanActiveParentId || (app.composer.fields && app.composer.fields.parentId);
      const username = app.itqanActiveParentUsername || (app.composer.fields && app.composer.fields.replyToUsername);

      if (pId) {
        const targetUsername = username || ('#' + pId);
        items.add(
          'itqan-replying-banner',
          m('div', { className: 'Composer-replyBanner' }, [
            m('div', { className: 'replyBanner-content' }, [
              icon ? icon('fas fa-reply') : null,
              ' ',
              `الرد على ${targetUsername}`,
            ]),
            m(
              'button',
              {
                className: 'replyBanner-close',
                title: 'إلغاء الرد المتشعب',
                onclick: (e) => {
                  e.stopPropagation();
                  app.itqanActiveParentId = null;
                  app.itqanActiveParentUsername = null;
                  clearActiveReplyTarget();
                  if (app.composer.fields) {
                    app.composer.fields.parentId = null;
                    app.composer.fields.replyToUsername = null;
                  }
                  m.redraw();
                },
              },
              icon ? icon('fas fa-times') : 'x'
            ),
          ]),
          100
        );
      }
    });

    extend(ReplyComposer.prototype, 'data', function (data) {
      const pId =
        app.itqanActiveParentId ||
        (app.composer.fields && app.composer.fields.parentId) ||
        (this.attrs && this.attrs.parentId);
      if (pId) {
        data.parentId = pId;
        data.parent_id = pId;
      }
    });

    extend(ReplyComposer.prototype, 'onsubmit', function () {
      setTimeout(() => {
        app.itqanActiveParentId = null;
        app.itqanActiveParentUsername = null;
        clearActiveReplyTarget();
        if (app.composer.fields) {
          app.composer.fields.parentId = null;
          app.composer.fields.replyToUsername = null;
        }
        reorderStreamTree();
      }, 500);
    });
  }
});

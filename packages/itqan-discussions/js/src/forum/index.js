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

import VoteButtons from './components/VoteButtons';
import { getPostDepth, isDescendantOfCollapsed, reorderStreamTree } from './components/CommentTree';

export { default as VoteButtons } from './components/VoteButtons';
export * from './components/CommentTree';

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

  extend(CommentPost.prototype, 'footerItems', function (items) {
    const post = this.attrs.post;
    if (post.isHidden() || post.attribute('votes') === undefined) return;

    items.add('itqanVote', <VoteButtons model={post} postId={post.id()} />, 100);
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

  // Element attributes for depth indentation
  extend(CommentPost.prototype, 'elementAttrs', function (attrs) {
    const post = this.attrs ? this.attrs.post : null;
    if (!post) return;

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

  // Header Items: "رد على @User" context badge with smooth scroll to parent
  extend(CommentPost.prototype, 'headerItems', function (items) {
    const post = this.attrs ? this.attrs.post : null;
    if (!post) return;

    const parentId = (typeof post.parentId === 'function') ? post.parentId() : null;
    if (parentId) {
      const parentPost = app.store ? app.store.getById('posts', String(parentId)) : null;
      // Guard: Do NOT show reply badge if parent is OP post #1
      if (parentPost && typeof parentPost.number === 'function' && parentPost.number() === 1) {
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
            title: parentUser ? (app.translator.trans('itqan-discussions.forum.replied_to', { username: parentUser }) || `رد على ${parentUser}`) : '',
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
            parentUser ? (app.translator.trans('itqan-discussions.forum.replied_to', { username: parentUser }) || `رد على ${parentUser}`) : ('#' + parentId),
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
    const isOP = typeof post.number === 'function' && post.number() === 1;
    const replyCount = (typeof post.replyCount === 'function') ? (post.replyCount() || 0) : 0;
    const isCollapsed = app.itqanCollapsedThreads.has(postIdStr);

    // Dynamic collapse/expand pill toggle (Only on comments with child replies, not OP)
    if (!isOP && replyCount > 0) {
      items.add(
        'itqan-collapse-thread',
        m(
          'button',
          {
            className: 'Button Button--link thread-collapse-toggle' + (isCollapsed ? ' is-collapsed' : ''),
            'data-post-id': postIdStr,
            onclick: (e) => {
              e.preventDefault();
              e.stopPropagation();
              const btn = e.currentTarget;
              const currentlyCollapsed = app.itqanCollapsedThreads.has(postIdStr);
              if (currentlyCollapsed) {
                app.itqanCollapsedThreads.delete(postIdStr);
                btn.classList.remove('is-collapsed');
              } else {
                app.itqanCollapsedThreads.add(postIdStr);
                btn.classList.add('is-collapsed');
              }

              const nowCollapsed = !currentlyCollapsed;
              const labelText = nowCollapsed
                ? (app.translator.trans('itqan-discussions.forum.expand_thread', { count: replyCount }) || `ردود (${replyCount})`)
                : (app.translator.trans('itqan-discussions.forum.collapse_thread') || 'طي');
              const iconClass = nowCollapsed ? 'fas fa-plus' : 'fas fa-minus';

              btn.innerHTML = `<i aria-hidden="true" class="icon ${iconClass}"></i> ${labelText}`;
              reorderStreamTree();
              m.redraw();
            },
          },
          [
            icon ? icon(isCollapsed ? 'fas fa-plus' : 'fas fa-minus') : null,
            ' ',
            isCollapsed
              ? (app.translator.trans('itqan-discussions.forum.expand_thread', { count: replyCount }) || `ردود (${replyCount})`)
              : (app.translator.trans('itqan-discussions.forum.collapse_thread') || 'طي'),
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
      } else {
        app.itqanActiveParentId = post.id();
        app.itqanActiveParentUsername = (post.user && post.user()) ? post.user().displayName() : ('#' + post.id());
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
          app.translator.trans('itqan-discussions.forum.reply') || 'رد',
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
              app.translator.trans('itqan-discussions.forum.replying_to', { username: targetUsername }) || `الرد على ${targetUsername}`,
            ]),
            m(
              'button',
              {
                className: 'replyBanner-close',
                title: app.translator.trans('itqan-discussions.forum.cancel_reply') || 'إلغاء الرد المتشعب',
                onclick: (e) => {
                  e.stopPropagation();
                  app.itqanActiveParentId = null;
                  app.itqanActiveParentUsername = null;
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
        if (app.composer.fields) {
          app.composer.fields.parentId = null;
          app.composer.fields.replyToUsername = null;
        }
        reorderStreamTree();
      }, 500);
    });
  }
});

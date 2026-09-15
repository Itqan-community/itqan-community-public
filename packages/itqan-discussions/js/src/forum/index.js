import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import Model from 'flarum/common/Model';
import Post from 'flarum/common/models/Post';
import CommentPost from 'flarum/forum/components/CommentPost';
import PostStream from 'flarum/forum/components/PostStream';
import PostStreamState from 'flarum/forum/states/PostStreamState';
import ReplyComposer from 'flarum/forum/components/ReplyComposer';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import DiscussionListState from 'flarum/forum/states/DiscussionListState';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import icon from 'flarum/common/helpers/icon';
import avatar from 'flarum/common/helpers/avatar';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import Button from 'flarum/common/components/Button';
import PostControls from 'flarum/forum/utils/PostControls';

import VoteButtons from './components/VoteButtons';
import {
  getVisualDepth,
  decorateStreamTree,
  postHasMoreReplies,
  countLoadedChildren,
  getThreadSummary,
  toggleCollapsed,
} from './components/CommentTree';
import CommentStreamState from './states/CommentStreamState';

/**
 * Group DFS-ordered comment stream vnodes into one envelope per root:
 * root card + flat reply cards (no indent rails).
 */
function postFromStreamVnode(vnode) {
  if (!vnode || !vnode.attrs) return null;
  const id = vnode.attrs['data-id'];
  if (id == null) return null;
  return app.store ? app.store.getById('posts', String(id)) : null;
}

function isEnvelopeRootVnode(vnode) {
  const post = postFromStreamVnode(vnode);
  if (!post) return false;
  if (typeof post.number === 'function' && post.number() === 1) return false;
  const parentId = typeof post.parentId === 'function' ? post.parentId() : null;
  return !parentId;
}

function isEnvelopeReplyVnode(vnode) {
  const post = postFromStreamVnode(vnode);
  if (!post) return false;
  if (typeof post.number === 'function' && post.number() === 1) return false;
  const parentId = typeof post.parentId === 'function' ? post.parentId() : null;
  return !!parentId;
}

function groupCommentItemsIntoEnvelopes(items) {
  const out = [];
  let i = 0;
  while (i < items.length) {
    const item = items[i];
    if (isEnvelopeRootVnode(item)) {
      const rootId = String(item.attrs['data-id']);
      const replies = [];
      i += 1;
      while (i < items.length && isEnvelopeReplyVnode(items[i])) {
        replies.push(items[i]);
        i += 1;
      }
      const children = [item];
      if (replies.length) {
        children.push(m('div.itqan-thread-replies', { key: `replies-${rootId}` }, replies));
      }
      out.push(
        m(
          'article.itqan-thread-envelope',
          { key: `envelope-${rootId}`, 'data-root-id': rootId },
          children
        )
      );
    } else {
      out.push(item);
      i += 1;
    }
  }
  return out;
}

export { default as VoteButtons } from './components/VoteButtons';
export * from './components/CommentTree';
export { default as CommentStreamState } from './states/CommentStreamState';

const trans = (key, params) => app.translator.trans(`itqan-discussions.forum.${key}`, params);
const text = (key, params) => extractText(trans(key, params));

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
    const firstPostId = typeof discussion.attribute === 'function' ? discussion.attribute('firstPostId') : null;
    const postId = typeof post.id === 'function' ? post.id() : null;
    if (firstPostId && postId && String(firstPostId) === String(postId)) return true;
    if (
      typeof discussion.firstPost === 'function' &&
      discussion.firstPost() &&
      postId &&
      String(discussion.firstPost().id()) === String(postId)
    )
      return true;
  }
  return false;
}

function getCommentStream(discussion) {
  if (!discussion) return null;
  if (!discussion.itqanCommentStream) {
    discussion.itqanCommentStream = new CommentStreamState(discussion);
  }
  return discussion.itqanCommentStream;
}

function syncStreamVisibleRange(stream) {
  if (!stream || !stream.discussion) return;
  stream.visibleStart = 0;
  stream.visibleEnd = stream.count();
}

/**
 * Scroll to a post by number once. Cancels competing timers; skips if the user
 * has already scrolled away from the initial landing.
 */
function scrollToPostNumber(number, { behavior = 'smooth', block = 'center', flash = true } = {}) {
  if (!number || Number(number) <= 1) return false;
  const el = document.querySelector(`.PostStream-item[data-number="${number}"]`);
  if (!el) return false;
  el.scrollIntoView({ behavior, block });
  if (flash) {
    el.classList.add('flash');
    setTimeout(() => el.classList.remove('flash'), 1800);
  }
  return true;
}

function scrollToPostId(postId, { behavior = 'smooth', block = 'nearest', flash = true } = {}) {
  if (!postId) return false;
  const el = document.querySelector(`.PostStream-item[data-id="${postId}"]`);
  if (!el) return false;
  el.scrollIntoView({ behavior, block });
  if (flash) {
    el.classList.add('flash');
    setTimeout(() => el.classList.remove('flash'), 1800);
  }
  return true;
}

const SORT_OPTIONS = [
  { value: 'oldest', key: 'sort.oldest', icon: 'fas fa-clock' },
  { value: 'top', key: 'sort.top', icon: 'fas fa-fire' },
  { value: 'latest', key: 'sort.latest', icon: 'fas fa-bolt' },
];

app.initializers.add('itqan-discussions', () => {
  // ==========================================
  // 1. Voting
  // ==========================================
  extend(DiscussionListState.prototype, 'sortMap', function (map) {
    map.top = '-votes';
    map.hot = '-hotness';
  });

  DiscussionListItem.prototype.getJumpTo = function () {
    const discussion = this.attrs.discussion;
    if (this.attrs.params && this.attrs.params.q) {
      const post = discussion.mostRelevantPost();
      if (post) {
        return post.number();
      }
    }
    return 1;
  };

  extend(DiscussionListItem.prototype, 'contentItems', function (items) {
    const discussion = this.attrs.discussion;
    if (discussion.attribute('votes') === undefined) return;

    items.add(
      'itqanVote',
      <VoteButtons model={discussion} postId={discussion.attribute('firstPostId')} vertical />,
      110
    );
  });

  // ==========================================
  // 2. Threading model
  // ==========================================
  if (Model && Post && Post.prototype) {
    Post.prototype.parentId = Model.attribute('parentId');
    Post.prototype.replyCount = Model.attribute('replyCount');
    Post.prototype.rootId = Model.attribute('rootId');
    Post.prototype.depth = Model.attribute('depth');
    Post.prototype.hasMoreReplies = Model.attribute('hasMoreReplies');
  }

  app.itqanActiveParentId = null;
  app.itqanActiveParentUsername = null;

  extend(CommentPost.prototype, 'elementAttrs', function (attrs) {
    const post = this.attrs ? this.attrs.post : null;
    if (!post) return;

    if (isMainPost(post)) {
      attrs['data-is-op'] = 'true';
      return;
    }

    const depth = getVisualDepth(post);
    if (depth > 0) {
      attrs['data-thread-depth'] = String(depth);
    }
  });

  // Without this the row keeps a stale chevron, title and aria-expanded after a
  // collapse: the retainer sees no watched value change and skips the diff.
  extend(CommentPost.prototype, 'oninit', function () {
    if (this.subtree) {
      this.subtree.check(() => app.itqanCollapseRevision);
    }
  });

  extend(CommentPost.prototype, 'oncreate', () => {
    decorateStreamTree();
  });
  extend(CommentPost.prototype, 'onupdate', () => {
    decorateStreamTree();
  });

  // ---- Windowed root pagination (never unload; never use flat posts API) ----
  if (PostStreamState) {
    extend(PostStreamState.prototype, 'show', function () {
      const discussion = this.discussion;
      if (discussion) {
        getCommentStream(discussion);
        syncStreamVisibleRange(this);
      }
    });

    // Core update() reloads every ID in discussion.posts — which CreatePost used
    // to dump as the full flat list. With a comment stream we only insert locally.
    override(PostStreamState.prototype, 'update', function () {
      syncStreamVisibleRange(this);
      return Promise.resolve();
    });

    // Never render LoadingPost for IDs that are not in the store.
    override(PostStreamState.prototype, 'posts', function () {
      return this.discussion
        .postIds()
        .slice(this.visibleStart, this.visibleEnd)
        .map((id) => {
          const post = app.store.getById('posts', id);
          return post && post.discussion() && typeof post.canEdit() !== 'undefined' ? post : null;
        })
        .filter((post) => post !== null);
    });

    override(PostStreamState.prototype, '_loadNext', function () {
      const commentStream = getCommentStream(this.discussion);
      if (!commentStream || !commentStream.hasMoreRoots || commentStream.loading) {
        return;
      }

      commentStream.loadMoreRoots().then(() => {
        syncStreamVisibleRange(this);
        decorateStreamTree();
        m.redraw();
      });
    });

    override(PostStreamState.prototype, '_loadPrevious', function () {
      const commentStream = getCommentStream(this.discussion);
      if (!commentStream || !commentStream.hasPreviousRoots || commentStream.loadingPrevious) {
        return;
      }

      const anchor = document.querySelector('.itqan-comments-card .PostStream-item[data-id]');
      const anchorId = anchor && anchor.getAttribute('data-id');
      const anchorTop = anchor ? anchor.getBoundingClientRect().top : null;

      commentStream.loadPreviousRoots().then(() => {
        syncStreamVisibleRange(this);
        decorateStreamTree();
        m.redraw();
        requestAnimationFrame(() => {
          if (anchorId && anchorTop != null) {
            const el = document.querySelector(`.PostStream-item[data-id="${anchorId}"]`);
            if (el) {
              const delta = el.getBoundingClientRect().top - anchorTop;
              if (delta) window.scrollBy(0, delta);
            }
          }
        });
      });
    });

    override(PostStreamState.prototype, 'addPost', function (original, post) {
      const discussion = this.discussion;
      if (discussion && post) {
        const commentStream = getCommentStream(discussion);
        if (commentStream) {
          commentStream.insertPost(post);
        }
        syncStreamVisibleRange(this);
        decorateStreamTree();
        m.redraw();
      }
    });

    override(PostStreamState.prototype, 'loadNearIndex', function () {
      syncStreamVisibleRange(this);
      return Promise.resolve();
    });

    override(PostStreamState.prototype, 'goToNumber', function (original, number, noAnimation) {
      if (number === 'reply') {
        syncStreamVisibleRange(this);
        this.needsScroll = true;
        this.targetPost = { index: this.count() - 1, reply: true };
        this.animateScroll = !noAnimation;
        m.redraw();
        return Promise.resolve();
      }

      this.paused = true;
      this.needsScroll = true;
      this.targetPost = { number };
      this.animateScroll = !noAnimation;
      this.number = number;

      return this.loadNearNumber(number).then(() => {
        m.redraw();
        requestAnimationFrame(() => {
          scrollToPostNumber(number, {
            behavior: noAnimation ? 'auto' : 'smooth',
            block: 'center',
          });
        });
      });
    });

    override(PostStreamState.prototype, 'loadNearNumber', function (original, number) {
      if (!number || Number(number) <= 1) {
        syncStreamVisibleRange(this);
        return Promise.resolve();
      }

      const loaded = (this.discussion.postIds() || [])
        .map((id) => app.store.getById('posts', id))
        .find((p) => p && Number(p.number()) === Number(number));

      if (loaded) {
        syncStreamVisibleRange(this);
        return Promise.resolve();
      }

      const commentStream = getCommentStream(this.discussion);
      if (!commentStream) return Promise.resolve();

      return commentStream.loadNearNumber(number).then(async () => {
        syncStreamVisibleRange(this);
        decorateStreamTree();

        // If still missing (capped subtree), expand along parent chain once.
        let target = (this.discussion.posts() || []).find(
          (p) => p && Number(p.number()) === Number(number)
        );
        if (!target) {
          // Target may be in the store from the near payload but not yet related;
          // commentStream already merged. Re-check store by number.
          target = app.store.all('posts').find(
            (p) =>
              p &&
              Number(p.number()) === Number(number) &&
              p.discussion() &&
              String(p.discussion().id()) === String(this.discussion.id())
          );
          if (target) {
            commentStream.insertPost(target);
            syncStreamVisibleRange(this);
          }
        }

        if (!target) {
          // Try expandReplies on any truncated ancestors if we know parentId from store.
          const orphan = app.store.all('posts').find(
            (p) => p && Number(p.number()) === Number(number)
          );
          if (orphan && typeof orphan.parentId === 'function' && orphan.parentId()) {
            const parent = app.store.getById('posts', String(orphan.parentId()));
            if (parent && postHasMoreReplies(parent)) {
              await commentStream.expandReplies(parent, countLoadedChildren(parent));
              commentStream.insertPost(orphan);
              syncStreamVisibleRange(this);
            }
          }
        }

        m.redraw();
      });
    });
  }

  if (PostStream) {
    extend(PostStream.prototype, 'oncreate', function () {
      decorateStreamTree();
      this.itqanSetupInfiniteScroll();
      this.itqanSetupPreviousScroll();
      if (this.stream && typeof this.stream.number === 'function') {
        const num = this.stream.number();
        if (num > 1 && !this.itqanDidDeepLinkScroll) {
          this.itqanDidDeepLinkScroll = true;
          // One scroll after paint — no competing 150/300/400ms timers.
          requestAnimationFrame(() => {
            requestAnimationFrame(() => {
              if (!scrollToPostNumber(num, { behavior: 'auto', block: 'center' })) {
                // Target may still be loading via loadNearNumber; retry once.
                setTimeout(() => scrollToPostNumber(num, { behavior: 'auto', block: 'center' }), 250);
              }
            });
          });
        }
      }
    });

    extend(PostStream.prototype, 'onupdate', function () {
      this.itqanSetupInfiniteScroll();
      this.itqanSetupPreviousScroll();
    });

    extend(PostStream.prototype, 'onremove', function () {
      if (this.itqanObserver) {
        this.itqanObserver.disconnect();
        this.itqanObserver = null;
      }
      if (this.itqanPrevObserver) {
        this.itqanPrevObserver.disconnect();
        this.itqanPrevObserver = null;
      }
    });

    PostStream.prototype.itqanSetupInfiniteScroll = function () {
      const stream = this.stream;
      const commentStream = getCommentStream(stream && stream.discussion);
      if (!commentStream) return;

      const sentinel = this.element && this.element.querySelector('.itqan-infinite-sentinel');
      if (!sentinel) return;

      if (this.itqanObserver) {
        this.itqanObserver.disconnect();
      }

      this.itqanObserver = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting && commentStream.hasMoreRoots && !commentStream.loading) {
              stream.loadNext();
            }
          });
        },
        { root: null, rootMargin: '200px', threshold: 0 }
      );
      this.itqanObserver.observe(sentinel);
    };

    PostStream.prototype.itqanSetupPreviousScroll = function () {
      const stream = this.stream;
      const commentStream = getCommentStream(stream && stream.discussion);
      if (!commentStream) return;

      const sentinel = this.element && this.element.querySelector('.itqan-previous-sentinel');
      if (!sentinel) return;

      if (this.itqanPrevObserver) {
        this.itqanPrevObserver.disconnect();
      }

      this.itqanPrevObserver = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (
              entry.isIntersecting &&
              commentStream.hasPreviousRoots &&
              !commentStream.loadingPrevious
            ) {
              stream.loadPrevious();
            }
          });
        },
        { root: null, rootMargin: '120px', threshold: 0 }
      );
      this.itqanPrevObserver.observe(sentinel);
    };

    // Core's viewport-driven loading fights the observer above and is what
    // produced the scroll jumps.
    PostStream.prototype.loadPostsIfNeeded = function () {};

    // Split the stream into the OP and one surface holding every comment.
    extend(PostStream.prototype, 'view', function (vnode) {
      if (!vnode || !vnode.children || !Array.isArray(vnode.children)) return;

      const children = vnode.children;
      if (children.length === 0) return;

      let opVnode = null;
      let afterFirstPostVnode = null;
      let rest = [];

      const firstChild = children[0];
      if (firstChild && firstChild.tag === '[') {
        const fragChildren = Array.isArray(firstChild.children) ? firstChild.children : [];
        opVnode = fragChildren[0] || null;
        afterFirstPostVnode = fragChildren[1] || null;
        rest = children.slice(1);
      } else {
        const isOp =
          firstChild &&
          firstChild.attrs &&
          (firstChild.attrs['data-number'] === 1 || firstChild.attrs['data-number'] === '1');
        if (isOp) {
          opVnode = firstChild;
          rest = children.slice(1);
        } else {
          opVnode = null;
          rest = children;
        }
      }

      const commentStream = getCommentStream(this.stream && this.stream.discussion);

      const header = [];
      const footer = [];

      // Empty comments: discussion has only the OP (commentCount includes OP).
      const discussionForEmpty = this.stream && this.stream.discussion;
      const emptyCount =
        discussionForEmpty && typeof discussionForEmpty.commentCount === 'function'
          ? discussionForEmpty.commentCount()
          : null;
      if (emptyCount != null && emptyCount <= 1) {
        footer.push(
          m('div.itqan-comments-empty', { key: 'itqan-empty' }, [
            m('div.itqan-comments-empty-title', trans('stream.empty_title')),
            m('div', trans('stream.empty_body')),
          ])
        );
      }

      if (commentStream) {
        if (commentStream.hasPreviousRoots) {
          header.push(
            m('div.itqan-load-previous-roots', { key: 'itqan-load-prev' }, [
              Button.component(
                {
                  className: 'Button',
                  loading: commentStream.loadingPrevious,
                  disabled: commentStream.loadingPrevious,
                  onclick: () => {
                    this.stream.loadPrevious();
                  },
                },
                trans('stream.load_previous')
              ),
            ])
          );
          header.push(
            m('div.itqan-previous-sentinel', { key: 'itqan-prev-sentinel', 'aria-hidden': 'true' })
          );
        }

        if (commentStream.hasMoreRoots) {
          footer.push(
            m('div.itqan-load-more-roots', { key: 'itqan-load-more' }, [
              Button.component(
                {
                  className: 'Button',
                  loading: commentStream.loading,
                  disabled: commentStream.loading,
                  onclick: () => {
                    this.stream.loadNext();
                  },
                },
                trans('stream.load_more')
              ),
            ])
          );
        }
        footer.push(m('div.itqan-infinite-sentinel', { key: 'itqan-sentinel', 'aria-hidden': 'true' }));
      }

      const commentCardChildren = [];
      commentCardChildren.push(...header);
      if (afterFirstPostVnode) {
        commentCardChildren.push(afterFirstPostVnode);
      }
      commentCardChildren.push(...groupCommentItemsIntoEnvelopes(rest));
      commentCardChildren.push(...footer);

      if (commentCardChildren.length > 0) {
        const commentsCard = m(
          'div',
          { className: 'itqan-comments-card', key: 'itqan-comments-card' },
          commentCardChildren
        );
        vnode.children = opVnode ? [opVnode, commentsCard] : [commentsCard];
      }
    });

    // Toolbar at the head of the comments surface: count, and a segmented sort
    // control in place of a native select.
    extend(PostStream.prototype, 'afterFirstPostItems', function (items) {
      const discussion = this.discussion || (this.stream && this.stream.discussion);
      if (!discussion) return;

      const postIds = typeof discussion.postIds === 'function' ? discussion.postIds() : [];
      if (!postIds || postIds.length <= 1) return;

      const commentStream = getCommentStream(discussion);
      const currentSort = (commentStream && commentStream.sort) || app.itqanDiscussionSort || 'oldest';
      const commentCount =
        (typeof discussion.commentCount === 'function' ? discussion.commentCount() : 0) || postIds.length;

      const changeSort = (sort) => {
        if (sort === currentSort) return;
        if (!commentStream) {
          app.itqanDiscussionSort = sort;
          decorateStreamTree();
          m.redraw();
          return;
        }
        commentStream.changeSort(sort).then(() => {
          syncStreamVisibleRange(this.stream);
          decorateStreamTree();
          m.redraw();
        });
      };

      items.add(
        'itqan-thread-toolbar',
        <div className="itqan-stream-toolbar">
          <div className="itqan-stream-toolbar-title">
            {icon('far fa-comments')}
            <span>{trans('stream.heading_count', { count: Math.max(0, commentCount - 1) })}</span>
          </div>
          <div className="itqan-sort-segmented" role="radiogroup" aria-label={text('sort.label')}>
            {SORT_OPTIONS.map((option) => (
              <button
                key={option.value}
                type="button"
                role="radio"
                className="itqan-sort-segment"
                aria-checked={currentSort === option.value ? 'true' : 'false'}
                disabled={commentStream && commentStream.loading}
                title={text(option.key)}
                onclick={() => changeSort(option.value)}
              >
                {icon(option.icon)}
                <span className="itqan-sort-segment-label">{trans(option.key)}</span>
              </button>
            ))}
          </div>
        </div>,
        50
      );
    });
  }

  if (DiscussionPage) {
    // Drop cached comment-stream state when opening a discussion page so a
    // previous discussion's window does not leak into the next one.
    extend(DiscussionPage.prototype, 'oninit', function () {
      if (this.discussion) {
        this.discussion.itqanCommentStream = null;
      }
    });
  }

  // Permalink in the overflow menu (⋯).
  extend(PostControls, 'userControls', function (items, post) {
    if (!post || typeof post.number !== 'function') return;
    const discussion = typeof post.discussion === 'function' ? post.discussion() : null;
    if (!discussion) return;

    items.add(
      'itqan-permalink',
      Button.component(
        {
          icon: 'fas fa-link',
          onclick: () => {
            const path = app.route.discussion(discussion, post.number());
            const url = new URL(path, window.location.origin).href;
            const done = () => {
              app.alerts.show(
                { type: 'success', controls: [] },
                text('permalink_copied')
              );
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(url).then(done).catch(() => {
                window.prompt(text('permalink'), url);
              });
            } else {
              window.prompt(text('permalink'), url);
            }
          },
        },
        trans('permalink')
      ),
      90
    );
  });

  // ==========================================
  // 3. Header: OP badge, reply context
  // ==========================================
  extend(CommentPost.prototype, 'headerItems', function (items) {
    const post = this.attrs ? this.attrs.post : null;
    if (!post) return;

    const discussion = typeof post.discussion === 'function' ? post.discussion() : null;
    const discUser = discussion && typeof discussion.user === 'function' ? discussion.user() : null;
    const postUser = typeof post.user === 'function' ? post.user() : null;
    if (discUser && postUser && discUser.id() && postUser.id() && String(discUser.id()) === String(postUser.id())) {
      items.add('itqan-op-badge', <span className="itqan-op-badge">{trans('op_badge')}</span>, 85);
    }

    // New-since-visit chip.
    const discussionForUnread = discussion;
    const lastRead =
      discussionForUnread && typeof discussionForUnread.lastReadPostNumber === 'function'
        ? discussionForUnread.lastReadPostNumber()
        : null;
    if (
      lastRead != null &&
      typeof post.number === 'function' &&
      post.number() > 1 &&
      post.number() > lastRead
    ) {
      items.add('itqan-unread', <span className="itqan-unread-chip">{trans('new_chip')}</span>, 84);
    }

    let parentId = typeof post.parentId === 'function' ? post.parentId() : null;
    let parentUser = null;
    let parentPost = null;

    if (parentId) {
      parentPost = app.store ? app.store.getById('posts', String(parentId)) : null;
      parentUser = parentPost && parentPost.user && parentPost.user() ? parentPost.user().displayName() : null;
    } else {
      // Older posts predate `parent_id` and carry the relationship as a leading
      // mention in the body instead.
      const html =
        typeof post.contentHtml === 'function' ? post.contentHtml() : post.attribute && post.attribute('contentHtml');
      if (html) {
        const match = html.match(
          /^\s*<p>\s*<a\s+[^>]*class="[^"]*PostMention[^"]*"[^>]*data-id="(\d+)"[^>]*>([^<]+)<\/a>/i
        );
        if (match) {
          parentId = match[1];
          parentUser = match[2].trim().replace(/^@/, '');
          parentPost = app.store ? app.store.getById('posts', String(parentId)) : null;
        }
      }
    }

    if (parentId) {
      const label = parentUser ? trans('replied_to', { username: parentUser }) : `#${parentId}`;
      const labelText = extractText(label);
      const previewHtml =
        parentPost && typeof parentPost.contentHtml === 'function' ? parentPost.contentHtml() : null;
      const previewAuthor =
        parentPost && parentPost.user && parentPost.user() ? parentPost.user().displayName() : parentUser;

      items.add(
        'itqan-reply-badge',
        m(
          'a',
          {
            className: 'itqan-reply-badge',
            href: '#',
            title: labelText,
            'aria-label': labelText,
            onmouseenter: (e) => {
              if (!previewHtml) return;
              const host = e.currentTarget;
              if (host.querySelector('.itqan-parent-preview')) return;
              const tip = document.createElement('div');
              tip.className = 'itqan-parent-preview';
              tip.setAttribute('role', 'tooltip');
              tip.innerHTML = `<div class="itqan-parent-preview-author"></div><div class="itqan-parent-preview-body"></div>`;
              tip.querySelector('.itqan-parent-preview-author').textContent =
                previewAuthor || text('stream.parent_preview');
              tip.querySelector('.itqan-parent-preview-body').innerHTML = previewHtml;
              host.appendChild(tip);
            },
            onmouseleave: (e) => {
              const tip = e.currentTarget.querySelector('.itqan-parent-preview');
              if (tip) tip.remove();
            },
            onfocus: (e) => {
              e.currentTarget.dispatchEvent(new Event('mouseenter'));
            },
            onblur: (e) => {
              const tip = e.currentTarget.querySelector('.itqan-parent-preview');
              if (tip) tip.remove();
            },
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
          [icon('fas fa-reply'), m('span.itqan-reply-badge-label', label)]
        ),
        70
      );
    }
  });

  // ==========================================
  // 5. Action bar
  // ==========================================
  //
  // Order is by frequency of use, and every item is the same height. The overflow
  // dropdown core appends is pushed to the far edge by CSS.
  extend(CommentPost.prototype, 'actionItems', function (items) {
    const post = this.attrs ? this.attrs.post : null;
    if (!post) return;

    const postIdStr = typeof post.id === 'function' ? String(post.id()) : '';
    const isOP = isMainPost(post);

    if (!post.isHidden() && post.attribute('votes') !== undefined) {
      items.add('itqanVote', m(VoteButtons, { model: post, postId: post.id(), vertical: false }), 50);
    }

    // Reply.
    const replyActionHandler = (e) => {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }

      if (isOP) {
        app.itqanActiveParentId = null;
        app.itqanActiveParentUsername = null;
        clearActiveReplyTarget();
      } else {
        app.itqanActiveParentId = post.id();
        app.itqanActiveParentUsername = post.user && post.user() ? post.user().displayName() : `#${post.id()}`;
        setActiveReplyTarget(post.id());
      }

      const disc = post.discussion ? post.discussion() : null;
      if (disc && DiscussionControls && DiscussionControls.replyAction) {
        DiscussionControls.replyAction.call(disc).then(() => {
          app.composer.fields = app.composer.fields || {};
          app.composer.fields.parentId = app.itqanActiveParentId;
          app.composer.fields.replyToUsername = app.itqanActiveParentUsername;

          if (!isOP) {
            const user = post.user && post.user();
            const username = user ? (typeof user.username === 'function' ? user.username() : user.displayName()) : null;
            if (username) {
              const mentionText = `@"${user.displayName()}"#p${post.id()} `;
              const currentContent = app.composer.fields.content ? app.composer.fields.content() : '';
              if (!currentContent.includes(`#p${post.id()}`)) {
                if (app.composer.editor && typeof app.composer.editor.insertAtCursor === 'function') {
                  app.composer.editor.insertAtCursor(mentionText);
                } else if (app.composer.fields.content) {
                  app.composer.fields.content(mentionText + currentContent);
                }
              }
            }
          }
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
          type: 'button',
          className: 'itqan-reply-action',
          onclick: replyActionHandler,
        },
        [icon('fas fa-reply'), m('span.itqan-action-label', trans('reply'))]
      ),
      40
    );

    // Collapse toggle. Collapsed, it summarises the subtree behind it —
    // participants, count, last activity — so it can be judged unopened.
    const loadedChildren = countLoadedChildren(post);
    const declaredReplies = typeof post.replyCount === 'function' ? post.replyCount() || 0 : 0;

    if (!isOP && (loadedChildren > 0 || declaredReplies > 0)) {
      const isCollapsed = app.itqanCollapsedThreads.has(postIdStr);
      const summary = getThreadSummary(post);

      items.add(
        'itqan-collapse-thread',
        m(
          'button',
          {
            type: 'button',
            className: 'itqan-thread-toggle',
            'data-post-id': postIdStr,
            'aria-expanded': isCollapsed ? 'false' : 'true',
            'aria-controls': `post-${postIdStr}`,
            title: text(isCollapsed ? 'tree.expand_rail' : 'tree.collapse_rail'),
            onclick: (e) => {
              e.preventDefault();
              e.stopPropagation();
              toggleCollapsed(postIdStr);
            },
          },
          [
            m('span.itqan-thread-toggle-chevron', icon(isCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-down')),
            // Decorative: the initials fallback would otherwise be read out as
            // part of the button's name, ahead of the count that matters.
            isCollapsed && summary.users.length
              ? m(
                  'span.itqan-avatar-stack',
                  { 'aria-hidden': 'true' },
                  summary.users.map((user) => avatar(user, { key: user.id() }))
                )
              : null,
            m('span.itqan-thread-toggle-count', trans('tree.replies', { count: summary.count })),
            isCollapsed && summary.latest
              ? m('span.itqan-thread-toggle-time', humanTime(summary.latest))
              : null,
          ]
        ),
        30
      );
    }

    // Replies the server capped away.
    if (!isOP && postHasMoreReplies(post)) {
      const discussion = typeof post.discussion === 'function' ? post.discussion() : null;
      const commentStream = getCommentStream(discussion);
      const expanding = commentStream && commentStream.expanding[postIdStr];
      const remaining = Math.max(0, declaredReplies - loadedChildren) || declaredReplies;

      items.add(
        'itqan-continue-thread',
        m('div.itqan-continue-wrap', { key: `continue-${postIdStr}` }, [
          Button.component(
            {
              className: 'itqan-continue-thread',
              icon: 'fas fa-level-down-alt',
              loading: !!expanding,
              disabled: !!expanding,
              onclick: (e) => {
                e.preventDefault();
                if (!commentStream) return;
                commentStream.expandReplies(post, loadedChildren).then(() => {
                  const page = app.current;
                  if (page && page.stream) {
                    syncStreamVisibleRange(page.stream);
                  }
                  decorateStreamTree();
                  m.redraw();
                });
              },
            },
            trans('stream.continue_thread', { count: remaining })
          ),
          expanding
            ? m('div.itqan-continue-skeletons', [
                [0, 1].map((i) =>
                  m('div.itqan-skeleton-row', { key: `sk-${i}` }, [
                    m('div.itqan-skeleton-avatar'),
                    m('div.itqan-skeleton-lines', [
                      m('div.itqan-skeleton-line.itqan-skeleton-line--short'),
                      m('div.itqan-skeleton-line.itqan-skeleton-line--long'),
                      m('div.itqan-skeleton-line.itqan-skeleton-line--mid'),
                    ]),
                  ])
                ),
              ])
            : null,
        ]),
        20
      );
    }
  });

  // The opening post gets a metadata strip between its body and its actions.
  extend(CommentPost.prototype, 'contentItems', function (items) {
    const post = this.attrs ? this.attrs.post : null;
    if (!post || !isMainPost(post)) return;

    const discussion = typeof post.discussion === 'function' ? post.discussion() : null;
    if (!discussion) return;

    const stat = (iconName, value, labelKey) =>
      value == null
        ? null
        : m('span.itqan-op-meta-item', [icon(iconName), m('strong', String(value)), trans(`meta.${labelKey}`)]);

    const commentCount = typeof discussion.commentCount === 'function' ? discussion.commentCount() : null;
    const participantCount =
      typeof discussion.participantCount === 'function' ? discussion.participantCount() : null;
    const viewCount = discussion.attribute ? discussion.attribute('views') : null;

    const stats = [
      commentCount != null ? stat('far fa-comment', Math.max(0, commentCount - 1), 'replies') : null,
      participantCount != null ? stat('far fa-user', participantCount, 'participants') : null,
      viewCount != null ? stat('far fa-eye', viewCount, 'views') : null,
    ].filter(Boolean);

    if (!stats.length) return;

    items.add('itqan-op-meta', <div className="itqan-op-meta">{stats}</div>, 85);
  });

  // ==========================================
  // 6. Composer integration
  // ==========================================
  if (ReplyComposer) {
    extend(ReplyComposer.prototype, 'headerItems', function (items) {
      const pId = app.itqanActiveParentId || (app.composer.fields && app.composer.fields.parentId);
      const username =
        app.itqanActiveParentUsername || (app.composer.fields && app.composer.fields.replyToUsername);

      if (!pId) return;

      const targetUsername = username || `#${pId}`;

      items.add(
        'itqan-replying-banner',
        m('div', { className: 'Composer-replyBanner' }, [
          m('div', { className: 'replyBanner-content' }, [
            icon('fas fa-reply'),
            m('span.replyBanner-target', trans('replying_to', { username: targetUsername })),
          ]),
          m(
            'button',
            {
              type: 'button',
              className: 'replyBanner-close',
              title: text('cancel_reply'),
              'aria-label': text('cancel_reply'),
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
            icon('fas fa-times')
          ),
        ]),
        100
      );
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

    // Own the submit path: snapshot IDs, insert in DFS order, never stream.update()
    // or core goToNumber (those caused end-of-feed jumps and stuck skeletons).
    override(ReplyComposer.prototype, 'onsubmit', function () {
      const discussion = this.attrs.discussion;
      const commentStream = getCommentStream(discussion);
      const snapshotIds = (discussion.postIds() || []).map(String);

      this.loading = true;
      m.redraw();

      const data = this.data();

      app.store
        .createRecord('posts')
        .save(data)
        .then((post) => {
          if (app.viewingDiscussion(discussion)) {
            // CreatePost may still push a truncated discussion.posts relation —
            // restore the pre-submit window, then insert in tree order.
            if (commentStream) {
              commentStream.restorePostIds(snapshotIds);
              const result = commentStream.insertPost(post);
              const stream = app.current.get('stream');
              if (stream) {
                syncStreamVisibleRange(stream);
              }
              decorateStreamTree();
              m.redraw();

              requestAnimationFrame(() => {
                scrollToPostId(post.id(), { behavior: 'smooth', block: 'nearest' });
              });

              if (!result.inserted && result.reason === 'parent_not_loaded') {
                app.alerts.show(
                  { type: 'success' },
                  trans('stream.reply_posted_elsewhere')
                );
              }
            } else {
              const stream = app.current.get('stream');
              if (stream) {
                stream.goToNumber(post.number());
              }
            }
          } else {
            let alert;
            const viewButton = Button.component(
              {
                className: 'Button Button--link',
                onclick: () => {
                  m.route.set(app.route.post(post));
                  app.alerts.dismiss(alert);
                },
              },
              app.translator.trans('core.forum.composer_reply.view_button')
            );
            alert = app.alerts.show(
              {
                type: 'success',
                controls: [viewButton],
              },
              app.translator.trans('core.forum.composer_reply.posted_message')
            );
          }

          this.composer.hide();
          this.loading = false;

          app.itqanActiveParentId = null;
          app.itqanActiveParentUsername = null;
          clearActiveReplyTarget();
          if (app.composer.fields) {
            app.composer.fields.parentId = null;
            app.composer.fields.replyToUsername = null;
          }
        }, this.loaded.bind(this));
    });
  }
});

// Runs after third-party actionItems (e.g. ianm-translate) so Translate leaves the
// primary row and only the overflow ⋯ remains for secondary actions.
app.initializers.add(
  'itqan-discussions-overflow',
  () => {
    extend(CommentPost.prototype, 'actionItems', function (items) {
      ['translate', 'ianm-translate'].forEach((name) => {
        if (items.has(name)) items.remove(name);
      });
    });
  },
  -100
);

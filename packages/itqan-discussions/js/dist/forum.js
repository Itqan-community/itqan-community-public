(() => {
  "use strict";

  const app = flarum.core.compat['forum/app'];
  const { extend, override } = flarum.core.compat['common/extend'];
  const Model = flarum.core.compat['common/Model'];
  const Post = flarum.core.compat['common/models/Post'];
  const Discussion = flarum.core.compat['common/models/Discussion'];
  const CommentPost = flarum.core.compat['forum/components/CommentPost'];
  const PostStream = flarum.core.compat['forum/components/PostStream'];
  const ReplyComposer = flarum.core.compat['forum/components/ReplyComposer'];
  const DiscussionControls = flarum.core.compat['forum/utils/DiscussionControls'];
  const DiscussionListState = flarum.core.compat['forum/states/DiscussionListState'];
  const DiscussionListItem = flarum.core.compat['forum/components/DiscussionListItem'];
  const icon = flarum.core.compat['common/helpers/icon'];
  const extractText = flarum.core.compat['common/utils/extractText'];
  const Component = flarum.core.compat['common/Component'];
  const Button = flarum.core.compat['common/components/Button'];
  const LogInModal = flarum.core.compat['forum/components/LogInModal'];
  const classList = flarum.core.compat['common/utils/classList'];

  // 1. VoteButtons Component (fof/gamification integration)
  class VoteButtons extends Component {
    oninit(vnode) {
      super.oninit(vnode);
      this.saving = false;
    }

    view() {
      const model = this.attrs.model;
      const userVote = model.attribute('userVote') || 0;
      const votes = model.attribute('votes') || 0;

      return m('div', {
        className: classList('VoteButtons', {
          'VoteButtons--saving': this.saving,
          'VoteButtons--vertical': this.attrs.vertical,
        }),
        ontouchstart: (e) => e.stopPropagation(),
      }, [
        this.button(1, this.attrs.vertical ? 'fas fa-caret-up' : 'fas fa-arrow-up', userVote === 1, 'up'),
        m('span', {
          className: classList('VoteButtons-score', {
            'VoteButtons-score--positive': userVote === 1,
            'VoteButtons-score--negative': userVote === -1,
          }),
          'aria-live': 'polite',
          dir: 'ltr',
        }, votes),
        this.button(-1, this.attrs.vertical ? 'fas fa-caret-down' : 'fas fa-arrow-down', userVote === -1, 'down'),
      ]);
    }

    button(voteValue, iconClass, isActive, type) {
      const label = extractText(app.translator.trans(`itqan-discussions.forum.vote.${type}`));

      return m(Button, {
        className: classList('Button Button--icon Button--link VoteButtons-button', `VoteButtons-button--${type}`, {
          'VoteButtons-button--active': isActive,
        }),
        icon: iconClass,
        'aria-pressed': isActive ? 'true' : 'false',
        'aria-label': label,
        onclick: () => this.vote(voteValue),
      });
    }

    vote(direction) {
      const model = this.attrs.model;
      const postId = this.attrs.postId;

      if (!app.session.user) {
        app.modal.show(LogInModal);
        return;
      }

      if (this.saving || !postId || !model.attribute('canVote')) return;

      const current = {
        votes: model.attribute('votes') || 0,
        userVote: model.attribute('userVote') || 0,
      };

      const newVote = current.userVote === direction ? 0 : direction;
      const newScore = current.votes - current.userVote + newVote;

      model.pushAttributes({ votes: newScore, userVote: newVote });
      if (typeof model.discussion === 'function' && model.discussion()) {
        model.discussion().pushAttributes({ votes: newScore, userVote: newVote });
      }

      m.redraw();
      this.saving = true;

      app.request({
        method: 'PATCH',
        url: `${app.forum.attribute('apiUrl')}/posts/${postId}/vote`,
        body: { data: { attributes: { vote: newVote } } },
      })
      .then((res) => {
        app.store.pushPayload(res);
        const serverVotes = res?.data?.attributes?.votes;
        if (serverVotes !== undefined) {
          if (model.data?.type === 'posts') {
            if (typeof model.discussion === 'function' && model.discussion()) {
              model.discussion().pushAttributes({ votes: serverVotes, userVote: newVote });
            }
          } else {
            model.pushAttributes({ votes: serverVotes, userVote: newVote });
          }
        }
      })
      .catch(() => {
        model.pushAttributes(current);
        m.redraw();
      })
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
    }
  }

  // 2. Avatar Dominant Color Extraction
  const AVATAR_COLOR_CACHE = new Map();
  const POST_AVATAR_COLOR_CACHE = new Map();
  const PALETTE = ['#0D9488', '#F97316', '#3B82F6', '#8B5CF6', '#EC4899', '#10B981', '#EF4444', '#F59E0B', '#6366F1'];

  function stringToColor(str) {
    if (!str) return PALETTE[0];
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      hash = (hash << 5) - hash + str.charCodeAt(i);
      hash |= 0;
    }
    return PALETTE[Math.abs(hash) % PALETTE.length];
  }

  function getDominantColorFromImg(img) {
    try {
      if (!img.complete || img.naturalWidth === 0) return null;
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d', { willReadFrequently: true });
      if (!ctx) return null;
      canvas.width = 16;
      canvas.height = 16;
      ctx.drawImage(img, 0, 0, 16, 16);
      const data = ctx.getImageData(0, 0, 16, 16).data;
      let r = 0, g = 0, b = 0, count = 0;
      for (let i = 0; i < data.length; i += 4) {
        if (data[i + 3] < 128) continue;
        const pr = data[i], pg = data[i + 1], pb = data[i + 2];
        if ((pr > 240 && pg > 240 && pb > 240) || (pr < 20 && pg < 20 && pb < 20)) continue;
        r += pr; g += pg; b += pb; count++;
      }
      return count === 0 ? `rgb(${data[544]}, ${data[545]}, ${data[546]})` : `rgb(${Math.round(r / count)}, ${Math.round(g / count)}, ${Math.round(b / count)})`;
    } catch {
      return null;
    }
  }

  function getAvatarColor(avatarEl, username, onResolved = null) {
    if (!avatarEl) return stringToColor(username);
    if (avatarEl.tagName === 'SPAN' || avatarEl.tagName === 'DIV') {
      const bg = avatarEl.style.backgroundColor || window.getComputedStyle(avatarEl).backgroundColor;
      if (bg && bg !== 'transparent' && bg !== 'rgba(0, 0, 0, 0)') return bg;
    }
    const img = avatarEl.tagName === 'IMG' ? avatarEl : avatarEl.querySelector('img');
    if (!img) {
      const bg = avatarEl.style.backgroundColor || window.getComputedStyle(avatarEl).backgroundColor;
      return (bg && bg !== 'transparent' && bg !== 'rgba(0, 0, 0, 0)') ? bg : stringToColor(username);
    }
    const src = img.src || img.getAttribute('src');
    if (!src) return stringToColor(username);
    if (AVATAR_COLOR_CACHE.has(src)) return AVATAR_COLOR_CACHE.get(src);
    if (img.complete && img.naturalWidth > 0) {
      const color = getDominantColorFromImg(img) || stringToColor(username);
      AVATAR_COLOR_CACHE.set(src, color);
      return color;
    }
    if (onResolved) {
      img.addEventListener('load', () => {
        const color = getDominantColorFromImg(img) || stringToColor(username);
        AVATAR_COLOR_CACHE.set(src, color);
        onResolved(color);
      }, { once: true });
    }
    return stringToColor(username);
  }

  function getPostAvatarColor(post) {
    if (!post) return PALETTE[0];
    const postId = typeof post.id === 'function' ? String(post.id()) : String(post.id || '');
    if (POST_AVATAR_COLOR_CACHE.has(postId)) return POST_AVATAR_COLOR_CACHE.get(postId);

    const user = typeof post.user === 'function' ? post.user() : null;
    const username = (user && typeof user.displayName === 'function') ? user.displayName() : (user && user.username) ? user.username() : postId;
    const avatarUrl = (user && typeof user.avatarUrl === 'function') ? user.avatarUrl() : null;
    if (avatarUrl && AVATAR_COLOR_CACHE.has(avatarUrl)) {
      const color = AVATAR_COLOR_CACHE.get(avatarUrl);
      POST_AVATAR_COLOR_CACHE.set(postId, color);
      return color;
    }

    const postItem = document.querySelector(`.PostStream-item[data-id="${postId}"]`);
    const avatarEl = postItem ? postItem.querySelector('.PostUser-avatar, .Avatar') : null;
    const color = getAvatarColor(avatarEl, username, (resolvedColor) => {
      POST_AVATAR_COLOR_CACHE.set(postId, resolvedColor);
      if (window.m) window.m.redraw();
    });

    POST_AVATAR_COLOR_CACHE.set(postId, color);
    return color;
  }

  // 3. Tree Depth & OP Determination
  function getPostDepth(post, visited = new Set()) {
    if (!post) return 0;
    const parentId = typeof post.parentId === 'function' ? post.parentId() : null;
    if (!parentId) return 0;
    const postId = typeof post.id === 'function' ? String(post.id()) : '';
    if (visited.has(postId)) return 0;
    visited.add(postId);

    const parent = app.store ? app.store.getById('posts', String(parentId)) : null;
    if (!parent) return 0;
    if (typeof parent.number === 'function' && parent.number() === 1) return 0;
    return 1 + getPostDepth(parent, visited);
  }

  function isMainPost(post) {
    if (!post) return false;
    if (typeof post.number === 'function' && post.number() === 1) return true;
    const disc = typeof post.discussion === 'function' ? post.discussion() : null;
    if (disc) {
      const firstId = typeof disc.attribute === 'function' ? disc.attribute('firstPostId') : null;
      const pId = typeof post.id === 'function' ? post.id() : null;
      if (firstId && pId && String(firstId) === String(pId)) return true;
    }
    return false;
  }

  function clearActiveReplyTarget() {
    document.querySelectorAll('.is-reply-target').forEach((el) => el.classList.remove('is-reply-target'));
  }

  // 4. Ancestor Rails Calculation
  function getAncestorRails(post) {
    if (!post || isMainPost(post)) return [];
    const ancestors = [];
    const selfId = typeof post.id === 'function' ? String(post.id()) : '';
    const visited = new Set();
    visited.add(selfId);

    let curr = post;
    while (curr) {
      const pId = typeof curr.parentId === 'function' ? curr.parentId() : null;
      if (!pId) break;
      const pIdStr = String(pId);
      if (visited.has(pIdStr)) break;
      visited.add(pIdStr);

      const parentPost = app.store ? app.store.getById('posts', pIdStr) : null;
      if (!parentPost) break;
      if (typeof parentPost.number === 'function' && parentPost.number() === 1) break;

      ancestors.unshift(parentPost);
      curr = parentPost;
    }

    const rails = ancestors.map((anc, colIndex) => ({
      col: colIndex,
      postId: String(anc.id()),
      color: getPostAvatarColor(anc),
      isSelf: false,
    }));

    const replyCount = typeof post.replyCount === 'function' ? post.replyCount() || 0 : 0;
    if (replyCount > 0) {
      rails.push({
        col: ancestors.length,
        postId: selfId,
        color: getPostAvatarColor(post),
        isSelf: true,
      });
    }

    return rails;
  }

  // Global State Initialization
  app.itqanCollapsedThreads = app.itqanCollapsedThreads || new Set();
  app.itqanDiscussionSort = app.itqanDiscussionSort || 'oldest';
  app.itqanActiveParentId = null;
  app.itqanActiveParentUsername = null;

  // Initializer
  app.initializers.add('itqan-discussions', () => {
    // Extend DiscussionListState sorts
    extend(DiscussionListState.prototype, 'sortMap', function (map) {
      map.top = '-votes';
      map.hot = '-hotness';
    });

    // Extend DiscussionListItem
    DiscussionListItem.prototype.getJumpTo = function () {
      const discussion = this.attrs.discussion;
      if (this.attrs.params && this.attrs.params.q) {
        const mostRelevant = discussion.mostRelevantPost();
        if (mostRelevant) return mostRelevant.number();
      }
      return 1;
    };

    extend(DiscussionListItem.prototype, 'contentItems', function (items) {
      const discussion = this.attrs.discussion;
      if (discussion.attribute('votes') !== undefined) {
        items.add('itqanVote', m(VoteButtons, {
          model: discussion,
          postId: discussion.attribute('firstPostId'),
          vertical: true,
        }), 110);
      }
    });

    // Register Post Model attributes
    if (Model && Post && Post.prototype) {
      Post.prototype.parentId = Model.attribute('parentId');
      Post.prototype.replyCount = Model.attribute('replyCount');
    }
    if (Model && Discussion && Discussion.prototype) {
      Discussion.prototype.rootCommentCount = Model.attribute('rootCommentCount');
    }

    // CommentPost element attributes: data-thread-depth & OP marker
    extend(CommentPost.prototype, 'elementAttrs', function (attrs) {
      const post = this.attrs ? this.attrs.post : null;
      if (post) {
        if (isMainPost(post)) {
          attrs['data-is-op'] = 'true';
        }
        const depth = getPostDepth(post);
        if (depth > 0) {
          attrs['data-thread-depth'] = String(depth);
        }
      }
    });

    // Guide Rails in CommentPost
    extend(CommentPost.prototype, 'contentItems', function (items) {
      const post = this.attrs ? this.attrs.post : null;
      if (!post || isMainPost(post)) return;

      const rails = getAncestorRails(post);
      if (!rails || rails.length === 0) return;

      items.add('itqanThreadRails', m('div', {
        className: 'itqan-thread-rails',
        'aria-hidden': 'true',
      }, rails.map((r) => m('div', {
        key: `rail-${r.postId}-${r.col}-${r.isSelf ? 's' : 'a'}`,
        className: `itqan-thread-rail ${r.isSelf ? 'itqan-thread-rail--self' : ''}`,
        style: {
          '--rail-col': r.col,
          '--rail-color': r.color,
        },
        title: r.isSelf ? '' : 'طي / فتح المحادثة',
        onclick: (e) => {
          if (!r.isSelf) {
            e.preventDefault();
            e.stopPropagation();
            if (app.itqanCollapsedThreads.has(r.postId)) {
              app.itqanCollapsedThreads.delete(r.postId);
            } else {
              app.itqanCollapsedThreads.add(r.postId);
            }
            m.redraw();
          }
        },
      }))), 120);
    });

    // OP Badge and Reply Badge in Post Header
    extend(CommentPost.prototype, 'headerItems', function (items) {
      const post = this.attrs ? this.attrs.post : null;
      if (!post) return;

      const discussion = typeof post.discussion === 'function' ? post.discussion() : null;
      const opUser = discussion && typeof discussion.user === 'function' ? discussion.user() : null;
      const postUser = typeof post.user === 'function' ? post.user() : null;

      if (opUser && postUser && opUser.id() && postUser.id() && String(opUser.id()) === String(postUser.id())) {
        items.add('itqan-op-badge', m('span', { className: 'itqan-op-badge' }, 'OP'), 85);
      }

      let parentId = typeof post.parentId === 'function' ? post.parentId() : null;
      let replyToUsername = null;

      if (parentId) {
        const parentPost = app.store ? app.store.getById('posts', String(parentId)) : null;
        replyToUsername = parentPost && parentPost.user && parentPost.user() ? parentPost.user().displayName() : null;
      } else {
        const html = typeof post.contentHtml === 'function' ? post.contentHtml() : post.attribute && post.attribute('contentHtml');
        if (html) {
          const match = html.match(/^\s*<p>\s*<a\s+[^>]*class="[^"]*PostMention[^"]*"[^>]*data-id="(\d+)"[^>]*>([^<]+)<\/a>/i);
          if (match) {
            parentId = match[1];
            replyToUsername = match[2].trim().replace(/^@/, '');
          }
        }
      }

      if (parentId) {
        items.add('itqan-reply-badge', m('a', {
          className: 'itqan-reply-badge',
          href: '#',
          title: replyToUsername ? extractText(app.translator.trans('itqan-discussions.forum.replied_to', { username: replyToUsername })) : '',
          onclick: (e) => {
            e.preventDefault();
            const parentEl = document.querySelector(`.PostStream-item[data-id="${parentId}"]`);
            if (parentEl) {
              parentEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
              parentEl.classList.add('flash');
              setTimeout(() => parentEl.classList.remove('flash'), 1500);
            }
          },
        }, [
          icon ? icon('fas fa-reply') : null,
          ' ',
          replyToUsername ? app.translator.trans('itqan-discussions.forum.replied_to', { username: replyToUsername }) : `#${parentId}`,
        ]), 70);
      }
    });

    // Action Items: Vote, Collapse Toggle, and Reply
    extend(CommentPost.prototype, 'actionItems', function (items) {
      const post = this.attrs ? this.attrs.post : null;
      if (!post) return;

      const postIdStr = typeof post.id === 'function' ? String(post.id()) : '';
      const isOp = isMainPost(post);
      const replyCount = typeof post.replyCount === 'function' ? post.replyCount() || 0 : 0;
      const isCollapsed = app.itqanCollapsedThreads.has(postIdStr);

      if (!post.isHidden() && post.attribute('votes') !== undefined) {
        items.add('itqanVote', m(VoteButtons, {
          model: post,
          postId: post.id(),
          vertical: false,
        }), 50);
      }

      if (!isOp && replyCount > 0) {
        const label = isCollapsed ? `ردود (${replyCount})` : 'طي';
        const iconName = isCollapsed ? 'fas fa-plus' : 'fas fa-minus';

        items.add('itqan-collapse-thread', m('button', {
          key: `collapse-btn-${postIdStr}-${isCollapsed ? 'col' : 'exp'}`,
          className: 'Button Button--link',
          'data-post-id': postIdStr,
          onclick: (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (app.itqanCollapsedThreads.has(postIdStr)) {
              app.itqanCollapsedThreads.delete(postIdStr);
            } else {
              app.itqanCollapsedThreads.add(postIdStr);
            }
            m.redraw();
          },
        }, [
          icon ? icon(iconName) : null,
          m('span.thread-collapse-label', ` ${label}`),
        ]), 15);
      }

      if (items.has('reply')) items.remove('reply');
      items.add('reply', m('button', {
        className: 'Button Button--link',
        onclick: (e) => {
          if (e) {
            e.preventDefault();
            e.stopPropagation();
          }

          if (isOp) {
            app.itqanActiveParentId = null;
            app.itqanActiveParentUsername = null;
            clearActiveReplyTarget();
          } else {
            app.itqanActiveParentId = post.id();
            app.itqanActiveParentUsername = post.user && post.user() ? post.user().displayName() : `#${post.id()}`;
            clearActiveReplyTarget();
            const targetEl = document.querySelector(`.PostStream-item[data-id="${post.id()}"]`);
            if (targetEl) targetEl.classList.add('is-reply-target');
          }

          const discussion = post.discussion ? post.discussion() : null;
          if (discussion && DiscussionControls && DiscussionControls.replyAction) {
            DiscussionControls.replyAction.call(discussion).then(() => {
              app.composer.fields = app.composer.fields || {};
              app.composer.fields.parentId = app.itqanActiveParentId;
              app.composer.fields.replyToUsername = app.itqanActiveParentUsername;
              m.redraw();
            });
          }
        },
      }, [
        icon ? icon('fas fa-reply') : null,
        ' ',
        'رد',
      ]), 10);
    });

    // ReplyComposer Context Banner & Data Injection
    if (ReplyComposer) {
      extend(ReplyComposer.prototype, 'headerItems', function (items) {
        const pId = app.itqanActiveParentId || (app.composer.fields && app.composer.fields.parentId);
        const username = app.itqanActiveParentUsername || (app.composer.fields && app.composer.fields.replyToUsername);

        if (pId) {
          const targetName = username || `#${pId}`;
          items.add('itqan-replying-banner', m('div', { className: 'Composer-replyBanner' }, [
            m('div', { className: 'replyBanner-content' }, [
              icon ? icon('fas fa-reply') : null,
              ' ',
              `الرد على ${targetName}`,
            ]),
            m('button', {
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
            }, icon ? icon('fas fa-times') : 'x'),
          ]), 100);
        }
      });

      extend(ReplyComposer.prototype, 'data', function (data) {
        const pId = app.itqanActiveParentId || (app.composer.fields && app.composer.fields.parentId) || (this.attrs && this.attrs.parentId);
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
          m.redraw();
        }, 500);
      });
    }

    // Sort Bar in PostStream.afterFirstPostItems
    if (PostStream) {
      extend(PostStream.prototype, 'afterFirstPostItems', function (items) {
        const discussion = this.discussion;
        if (!discussion) return;
        const postIds = typeof discussion.postIds === 'function' ? discussion.postIds() : [];
        if (!postIds || postIds.length <= 1) return;

        const currentSort = app.itqanDiscussionSort || 'oldest';

        items.add('itqan-thread-sort', m('div', { className: 'itqan-stream-sort-bar' }, [
          m('div', { className: 'itqan-stream-sort-title' }, [
            m('span', app.translator.trans('itqan-discussions.forum.sort.label') || 'الترتيب:'),
          ]),
          m('div', { className: 'itqan-thread-sort-wrapper' }, [
            m('select', {
              className: 'itqan-thread-sort-select',
              value: currentSort,
              onchange: (e) => {
                app.itqanDiscussionSort = e.target.value;
                if (this.stream && this.stream.goToFirst) {
                  this.stream.goToFirst();
                }
                m.redraw();
              },
            }, [
              m('option', { value: 'oldest' }, app.translator.trans('itqan-discussions.forum.sort.oldest') || 'الأقدم (افتراضي)'),
              m('option', { value: 'top' }, app.translator.trans('itqan-discussions.forum.sort.top') || 'الأعلى تقييماً'),
              m('option', { value: 'latest' }, app.translator.trans('itqan-discussions.forum.sort.latest') || 'الأحدث'),
            ]),
          ]),
        ]), 50);
      });

      // Wrap comments inside the unified Reddit card .itqan-comments-card
      extend(PostStream.prototype, 'view', function (vnode) {
        if (!vnode || !vnode.children || !Array.isArray(vnode.children)) return;

        const children = vnode.children;
        if (children.length === 0) return;

        let opVnode = null;
        let afterFirstPostVnode = null;
        let commentItems = [];

        const firstChild = children[0];
        if (firstChild && firstChild.tag === '[') {
          const fragmentChildren = Array.isArray(firstChild.children) ? firstChild.children : [];
          opVnode = fragmentChildren[0] || null;
          afterFirstPostVnode = fragmentChildren[1] || null;
          commentItems = children.slice(1);
        } else if (firstChild && firstChild.attrs && (firstChild.attrs['data-number'] === 1 || firstChild.attrs['data-number'] === '1')) {
          opVnode = firstChild;
          commentItems = children.slice(1);
        } else {
          opVnode = null;
          commentItems = children;
        }

        const filteredComments = [];
        let subsequentRootSeen = false;

        commentItems.forEach((item) => {
          if (!item || !item.attrs) {
            filteredComments.push(item);
            return;
          }

          const postId = item.attrs['data-id'];
          if (!postId) {
            filteredComments.push(item);
            return;
          }

          const post = app.store ? app.store.getById('posts', String(postId)) : null;
          if (!post) {
            filteredComments.push(item);
            return;
          }

          // Check if descendant of any collapsed thread
          let isHiddenByCollapse = false;
          let pId = typeof post.parentId === 'function' ? post.parentId() : null;
          const cycleCheck = new Set();
          while (pId) {
            const pIdStr = String(pId);
            if (cycleCheck.has(pIdStr)) break;
            cycleCheck.add(pIdStr);

            if (app.itqanCollapsedThreads.has(pIdStr)) {
              isHiddenByCollapse = true;
              break;
            }
            const pPost = app.store ? app.store.getById('posts', pIdStr) : null;
            if (!pPost || (typeof pPost.number === 'function' && pPost.number() === 1)) break;
            pId = typeof pPost.parentId === 'function' ? pPost.parentId() : null;
          }

          if (isHiddenByCollapse) {
            return;
          }

          // Mark subsequent root comments for top border divider
          const isRoot = !pId || (post.number && post.number() === 1);
          if (isRoot && post.number && post.number() > 1) {
            if (subsequentRootSeen) {
              item.attrs['data-is-subsequent-root'] = 'true';
            } else {
              subsequentRootSeen = true;
              item.attrs['data-is-first-root'] = 'true';
            }
          }

          const replyCount = typeof post.replyCount === 'function' ? post.replyCount() || 0 : 0;
          if (replyCount > 0) {
            item.attrs['data-has-thread-replies'] = 'true';
          }

          filteredComments.push(item);
        });

        const cardContents = [];
        if (afterFirstPostVnode) cardContents.push(afterFirstPostVnode);
        cardContents.push(...filteredComments);

        if (cardContents.length > 0) {
          const commentsCard = m('div', { className: 'itqan-comments-card', key: 'itqan-comments-card' }, cardContents);
          vnode.children = opVnode ? [opVnode, commentsCard] : [commentsCard];
        }
      });
    }
  });
})();

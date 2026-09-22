import { extend, override } from 'flarum/common/extend';
import Link from 'flarum/common/components/Link';
import app from 'flarum/forum/app';
import icon from 'flarum/common/helpers/icon';
import Button from 'flarum/common/components/Button';
import Post from 'flarum/forum/components/Post';
import CommentPost from 'flarum/forum/components/CommentPost';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import PostControls from 'flarum/forum/utils/PostControls';
import Composer from 'flarum/forum/components/Composer';
import PostStream from 'flarum/forum/components/PostStream';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';
import Stream from 'flarum/common/utils/Stream';
import { readSettings } from '../common/settings';
import { createVoteAdapter } from '../common/voteAdapter';
import { getDepth, isHidden, isOriginalPost, getReplyTarget, getParentId, isDerivedParent, planSiblingFolding } from './utils/threadDepths';
import VoteRail from './components/VoteRail';
import CollapseToggle from './components/CollapseToggle';
import MoreReplies from './components/MoreReplies';
import NestedRepliesInlineReply from './components/NestedRepliesInlineReply';
import DeleteConfirmModal from './components/DeleteConfirmModal';

app.initializers.add('mtareq-nested-replies', () => {
  const settings = readSettings(app);

  // The scrubber setting is independent of the master switch.
  if (typeof document !== 'undefined' && document.documentElement) {
    document.documentElement.classList.toggle('NestedRepliesHideScrubber', !settings.showScrubber);
  }

  if (!settings.enabled) return;

  const votes = createVoteAdapter(app);
  const collapsed = new Set();
  const expandedGroups = new Set();
  const mounted = new Set();
  const lookup = (id) => app.store.getById('posts', String(id));

  // Recomputed on every stream render. `hidden` holds replies (and their
  // subtrees) folded behind a "Show more replies" control; `moreAfter` maps the
  // post that anchors each control to the groups it reveals.
  let foldPlan = { hidden: new Set(), moreAfter: new Map() };

  // Reply-card sorting. `oldest` uses Flarum's native stream; the other modes
  // fetch every page first so pagination can't leave posts out of the order.
  let sortMode = 'oldest';
  let allPosts = null;
  let loadingAll = false;
  let refreshing = false;
  let currentDiscussion = null;
  let pendingParentId = null;

  // The open in-card reply form, and the draft it holds (shared so a target
  // switch can warn before discarding).
  let inlineReply = null;
  const inlineDraft = Stream('');
  // Whether the embedded composer is showing the rendered preview instead of
  // the editor (toggled by the composer's eye control).
  let composerPreview = false;

  // The post currently being edited inline (replaces the native composer edit).
  let editingPostId = null;
  const editDraft = Stream('');

  // The reply just posted, highlighted briefly so its author can spot it.
  const HIGHLIGHT_DURATION = 3000;
  let highlightedPostId = null;
  let highlightTimer = null;

  if (typeof document !== 'undefined' && document.documentElement) {
    document.documentElement.classList.toggle('NestedRepliesHideMentionedBy', !settings.showRepliedIndicator);
    document.documentElement.style.setProperty('--nested-replies-like-color', settings.likeColor || '#ff4500');
    document.documentElement.style.setProperty('--nested-replies-highlight-rgb', hexToRgbTriplet(settings.highlightColor, '0, 200, 83'));
  }

  // Parse `#rrggbb` into an `r, g, b` triplet for use inside rgba(). Anything
  // else falls back to the default so the highlight never breaks.
  function hexToRgbTriplet(hex, fallback) {
    const match = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());
    if (!match) return fallback;

    const value = parseInt(match[1], 16);
    return `${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}`;
  }

  // The discussion-level Reply button (the one above Follow) calls
  // DiscussionControls.replyAction. Route it to the in-card form on the
  // original post (a top-level reply) so it never opens the fixed composer.
  // Guests and non-replyable discussions keep core's behaviour.
  const originalReplyAction = DiscussionControls.replyAction;

  if (typeof originalReplyAction === 'function') {
    DiscussionControls.replyAction = function (...args) {
      const discussion = this;

      if (!app.session.user || !discussion || typeof discussion.canReply !== 'function' || !discussion.canReply()) {
        return originalReplyAction.apply(this, args);
      }

      const postIds = typeof discussion.postIds === 'function' ? discussion.postIds() : [];
      const op = postIds.length ? app.store.getById('posts', String(postIds[0])) : null;

      if (!op) return originalReplyAction.apply(this, args);

      openInlineReply(op);
      return undefined;
    };
  }

  function removePostFromTree(postId) {
    const id = String(postId);
    if (allPosts && Array.isArray(allPosts)) {
      allPosts = allPosts.filter((post) => String(post.id()) !== id);
    }
    collapsed.delete(id);
    expandedGroups.delete(id);
    forceRedraw();
  }

  // Override PostControls.hideAction & deleteAction to use custom DeleteConfirmModal
  // and instantly remove deleted posts from local stream tree without page reload.
  if (PostControls) {
    override(PostControls, 'hideAction', function (original, context) {
      const post = this;

      app.modal.show(DeleteConfirmModal, {
        post,
        title: app.translator.trans('core.forum.post_controls.hide_confirmation'),
        message: app.translator.trans('core.forum.post_controls.hide_confirmation'),
        confirmLabel: app.translator.trans('core.forum.post_controls.hide_button'),
        onconfirm: () => {
          if (context) context.loading = true;
          const postId = String(post.id());

          post
            .save({ isHidden: true })
            .then(() => {
              removePostFromTree(postId);
            })
            .catch(() => {})
            .then(() => {
              if (context) context.loading = false;
              m.redraw();
            });
        },
      });
    });

    override(PostControls, 'deleteAction', function (original, context) {
      const post = this;

      app.modal.show(DeleteConfirmModal, {
        post,
        title: app.translator.trans('core.forum.post_controls.delete_confirmation'),
        message: app.translator.trans('core.forum.post_controls.delete_confirmation'),
        confirmLabel: app.translator.trans('core.forum.post_controls.delete_button'),
        onconfirm: () => {
          if (context) context.loading = true;
          const discussion = post.discussion();
          const postId = String(post.id());

          post
            .delete()
            .then(() => {
              if (discussion && typeof discussion.removePost === 'function') {
                discussion.removePost(postId);
                if (typeof discussion.postIds === 'function' && !discussion.postIds().length) {
                  if (app.discussions && typeof app.discussions.removeDiscussion === 'function') {
                    app.discussions.removeDiscussion(discussion);
                  }
                  if (app.viewingDiscussion && app.viewingDiscussion(discussion)) {
                    app.history.back();
                  }
                }
              }

              removePostFromTree(postId);
            })
            .catch(() => {})
            .then(() => {
              if (context) context.loading = false;
              m.redraw();
            });
        },
      });
    });
  }

  // Keep the @ autocomplete to users only. Flarum's post mentionable offers the
  // discussion's posts as `@"name"#pN`, which is confusing; stop it suggesting
  // anything while leaving programmatic post mentions (quoting) intact.
  if (app.mentionFormats && typeof app.mentionFormats.mentionable === 'function') {
    const postMentionable = app.mentionFormats.mentionable('post');

    if (postMentionable) {
      postMentionable.initialResults = () => [];
      postMentionable.search = () => Promise.resolve([]);
    }
  }

  // Ensure every post has a Reply action. flarum/mentions supplies one when it
  // is enabled; otherwise we add our own so threading still works.
  extend(CommentPost.prototype, 'actionItems', function (items) {
    if (items.has('reply')) return;

    const post = this.attrs.post;
    if (!post || post.isHidden()) return;
    if (post.isDeleted && post.isDeleted()) return;
    if (app.session.user && !post.discussion().canReply()) return;

    items.add(
      'reply',
      m(
        Button,
        {
          className: 'Button Button--link',
          onclick: () => {
            pendingParentId = String(post.id());
            DiscussionControls.replyAction.call(post.discussion());
          },
        },
        app.translator.trans('mtareq-nested-replies.forum.reply_link')
      ),
      0
    );
  });

  if (typeof document !== 'undefined') {
    document.addEventListener(
      'click',
      (event) => {
        const target = event.target;
        const replyItem = target && target.closest ? target.closest('.item-reply') : null;

        if (replyItem) {
          const item = replyItem.closest('.PostStream-item[data-id]');
          const id = item ? String(item.getAttribute('data-id')) : null;
          if (!id) return;

          const post = lookup(id);

          if (post && app.session.user && post.discussion().canReply()) {
            event.preventDefault();
            event.stopPropagation();
            openInlineReply(post);
            return;
          }

          // Guests, and users without reply permission, use Flarum's native flow.
          pendingParentId = id;

          const body = app.composer && app.composer.body;
          const open =
            app.composer &&
            typeof app.composer.isVisible === 'function' &&
            app.composer.isVisible() &&
            body &&
            body.attrs &&
            body.attrs.discussion &&
            !body.attrs.post;

          if (open) {
            body.attrs.replyToPostId = id;
            pendingParentId = null;
          }
          return;
        }

        if (target && target.closest && target.closest('.ReplyPlaceholder')) {
          pendingParentId = null;
        }
      },
      true
    );
  }

  function patchReplyComposer(componentClass) {
    if (!componentClass || !componentClass.prototype) return;

    const proto = componentClass.prototype;
    if (proto.__nestedRepliesParentPatched) return;
    proto.__nestedRepliesParentPatched = true;

    const originalData = proto.data;
    proto.data = function () {
      const data = (originalData ? originalData.call(this) : {}) || {};
      const attrs = this.attrs || {};
      if (attrs.replyToPostId != null) data.replyToPostId = attrs.replyToPostId;
      return data;
    };

    // Core's preview control navigates to the full-page reply route. Inline,
    // toggle the rendered preview in place instead so the page does not jump.
    const originalJumpToPreview = proto.jumpToPreview;
    proto.jumpToPreview = function (...args) {
      if (!isInlineComposer()) {
        return originalJumpToPreview ? originalJumpToPreview.apply(this, args) : undefined;
      }

      composerPreview = !composerPreview;
      forceRedraw();
    };
  }

  if (app.composer && typeof app.composer.load === 'function') {
    override(app.composer, 'load', function (original, componentClass, attrs) {
      // Intercept the native EditPostComposer and redirect to inline edit.
      if (attrs && attrs.post && componentClass && componentClass.prototype) {
        const name = componentClass.name || componentClass.displayName || '';
        if (name === 'EditPostComposer' || (componentClass.prototype && typeof componentClass.prototype.onsubmit === 'function' && attrs.post)) {
          const post = attrs.post;
          if (app.session.user && typeof post.canEdit === 'function' && post.canEdit()) {
            editPost(post);
            if (typeof this.hide === 'function') this.hide();
            // Return a no-op result — the native composer stays hidden.
            return { then: (fn) => fn && fn() };
          }
        }
      }

      const result = original.call(this, componentClass, attrs);

      const apply = () => {
        const body = this.body;
        if (body && body.attrs && body.attrs.discussion && !body.attrs.post) {
          if (pendingParentId != null) {
            body.attrs.replyToPostId = pendingParentId;
            pendingParentId = null;
          }
          patchReplyComposer(body.componentClass);
        }
      };

      if (result && typeof result.then === 'function') {
        result.then(apply);
      } else {
        apply();
      }

      return result;
    });
  }

  // A successful composer submit (and the shell's own close) calls
  // app.composer.hide(). The inline host renders inside a Post whose
  // SubtreeRetainer caches it, so the host's onupdate cannot observe the change;
  // hook hide() to clear the inline form and refresh the tree.
  if (app.composer && typeof app.composer.hide === 'function') {
    const originalHide = app.composer.hide;

    app.composer.hide = function (...args) {
      const wasInline = Boolean(inlineReply && inlineReply.mode === 'composer');
      const parentId = wasInline ? inlineReply.postId : null;
      const discussion = wasInline ? inlineReply.discussion : null;
      const baselineId = wasInline ? inlineReply.baselinePostId : null;
      const latest = wasInline ? latestPostIn(discussion) : null;
      // hide() runs for both submit and cancel. A new newest post means a reply
      // was actually posted; otherwise this was a cancel and nothing changed.
      const posted = Boolean(latest && latest.id && String(latest.id()) !== String(baselineId));

      const result = originalHide.apply(this, args);

      if (wasInline) {
        inlineReply = null;
        inlineDraft('');
        composerPreview = false;

        if (posted) {
          revealReply(parentId);
          forceRedraw();
          refreshTree(latest.id());
        } else {
          // Cancelled: clear the form without revealing or scrolling anywhere.
          forceRedraw();
        }
      }

      return result;
    };
  }

  // Flarum's discussion-list links resume at the first unread post. Optionally
  // open discussions at the top so readers start with the original post.
  if (settings.startAtFirstPost) {
    override(DiscussionListItem.prototype, 'getJumpTo', function (original) {
      // Keep search-result jumps so the matched post is still highlighted.
      if (this.attrs.params && this.attrs.params.q) return original();

      return 1;
    });
  }

  function isLikedByMe(post) {
    if (!app.session.user || typeof post.likes !== 'function') return false;

    const likes = post.likes();
    if (!Array.isArray(likes)) return false;

    return likes.some(
      (user) => user === app.session.user || (user && typeof user.id === 'function' && String(user.id()) === String(app.session.user.id()))
    );
  }

  function syncLikedClass(element, post) {
    const item = element.querySelector('.item-like');
    if (item) item.classList.toggle('is-liked', isLikedByMe(post));
  }

  function decorate(component) {
    const post = component.attrs.post;
    const element = component.$ ? component.$()[0] : null;
    if (!post || !element) return;

    const id = String(post.id());
    const depth = getDepth(post, settings.maxDepth, lookup, settings.legacyMentions);
    // A reply is hidden when the reader collapsed an ancestor or when a sibling
    // group above it is folded behind a "Show more replies" control.
    const hidden = isHidden(post, collapsed, lookup, settings.legacyMentions) || foldPlan.hidden.has(id);
    const op = isOriginalPost(post);

    element.classList.add('NestedRepliesPost');
    element.classList.toggle('NestedRepliesPost--op', op);
    // Brief highlight on the reply the reader just posted.
    element.classList.toggle('NestedRepliesPost--new', highlightedPostId != null && id === highlightedPostId);

    // Flarum 2.x ships a `.Post-container` wrapper; 1.x has an unnamed div.
    // Tag it ourselves so the LESS works on both.
    const container = element.firstElementChild;
    if (container) container.classList.add('NestedRepliesPost-container');

    element.dataset.depth = String(depth);
    element.style.setProperty('--depth', String(depth));

    // Tag the stream item so the reply card can draw separators between
    // top-level replies only, and never between nested ones.
    const item = element.parentElement;
    if (item && item.classList && item.classList.contains('PostStream-item')) {
      item.classList.toggle('is-top-level', depth === 0 && !op);
      item.classList.toggle('is-nested', depth > 0);
    }

    // The post that anchors a "Show more replies" control sits flush with its
    // fold point, so the indent guides end at the control instead of trailing
    // past it.
    element.classList.toggle('NestedRepliesPost--hasMore', foldPlan.moreAfter.has(id));

    if (collapsed.has(id)) element.dataset.collapsed = 'true';
    else delete element.dataset.collapsed;

    if (hidden) element.dataset.hidden = 'true';
    else delete element.dataset.hidden;

    syncLikedClass(element, post);

    // The reply target is shown as a tag in the header, so hide only the inline
    // mention that points at the stored parent. A legacy-derived parent is the
    // mention itself, so hide it too even when the tag is off.
    if (settings.showReplyTag || isDerivedParent(post, settings.legacyMentions)) {
      const parentId = getParentId(post, settings.legacyMentions);
      const target = parentId ? getReplyTarget(post, lookup, settings.legacyMentions) : null;

      if (parentId && target && target.name) {
        const body = element.querySelector('.Post-body') || element.querySelector('.Post-content');
        if (body) {
          const mention = body.querySelector(`a.PostMention[data-id="${parentId}"]`);
          if (mention) mention.classList.add('NestedRepliesReplyTag-source');
        }
      }
    }
  }

  // Pull every page of the discussion from the API so sorting sees all posts.
  async function fetchAllPosts() {
    if (!currentDiscussion) return [];

    const filter = { discussion: currentDiscussion.id() };
    const limit = 50;
    const collected = [];
    let offset = 0;
    let effective = limit;

    for (let guard = 0; guard < 500; guard++) {
      const page = await app.store.find('posts', { filter, page: { offset, limit }, sort: 'number' });

      if (!page || !page.length) break;
      if (offset === 0) effective = page.length;

      collected.push(...page);

      if (page.length < effective) break;
      offset += page.length;
    }

    return collected;
  }

  // Order the replies as a tree: sort each sibling group by the chosen mode and
  // walk depth-first so children always follow their parent.
  function buildReplyOrder(posts, mode) {
    const byId = new Map();
    posts.forEach((post) => byId.set(String(post.id()), post));

    const op = posts.find((post) => isOriginalPost(post)) || null;
    const opId = op ? String(op.id()) : null;

    const children = new Map();
    const roots = [];

    posts.forEach((post) => {
      if (op && post === op) return;

      const parentId = getParentId(post, settings.legacyMentions);

      if (parentId && parentId !== opId && byId.has(parentId)) {
        const list = children.get(parentId) || [];
        list.push(post);
        children.set(parentId, list);
      } else {
        roots.push(post);
      }
    });

    const counts = new Map();
    const countDescendants = (post, seen) => {
      const id = String(post.id());
      if (seen.has(id)) return 0;
      seen.add(id);

      const kids = children.get(id) || [];
      let total = 0;
      kids.forEach((kid) => {
        total += 1 + countDescendants(kid, seen);
      });

      counts.set(id, total);
      return total;
    };
    posts.forEach((post) => {
      if (!counts.has(String(post.id()))) countDescendants(post, new Set());
    });

    const time = (post) => Number(post.createdAt ? post.createdAt() : 0) || 0;
    const score = (post) => Number(post.attribute ? post.attribute('votes') : 0) || 0;
    const replyCount = (post) => counts.get(String(post.id())) || 0;

    const comparator = (a, b) => {
      if (mode === 'newest') return time(b) - time(a);
      if (mode === 'top') return score(b) - score(a) || time(a) - time(b);
      if (mode === 'replies') return replyCount(b) - replyCount(a) || time(a) - time(b);
      return time(a) - time(b);
    };

    const ordered = [];
    const walk = (list) => {
      list.sort(comparator);
      list.forEach((post) => {
        ordered.push(post);
        const kids = children.get(String(post.id()));
        if (kids) walk(kids);
      });
    };
    walk(roots);

    return { op, ordered };
  }

  function makePostItem(post, index) {
    const PostComponent = app.postComponents[post.contentType()];
    if (!PostComponent) return null;

    const createdAt = post.createdAt ? post.createdAt() : null;

    return m(
      'div.PostStream-item',
      {
        key: 'post' + post.id(),
        'data-index': index,
        'data-number': post.number(),
        'data-id': post.id(),
        'data-type': post.contentType(),
        'data-time': createdAt && createdAt.toISOString ? createdAt.toISOString() : undefined,
      },
      m(PostComponent, { post })
    );
  }

  function replySortVNode() {
    const trans = (key) => app.translator.trans(`mtareq-nested-replies.forum.${key}`);
    const options = [
      ['oldest', trans('sort_oldest')],
      ['newest', trans('sort_newest')],
      ['top', trans('sort_top')],
      ['replies', trans('sort_replies')],
    ];

    return m('div.NestedRepliesReplySort', { key: 'nestedRepliesReplySort' }, [
      m('span.NestedRepliesReplySort-label', trans('sort_by')),
      m(
        'select.NestedRepliesReplySort-select',
        {
          value: sortMode,
          disabled: loadingAll,
          onchange: (e) => setSortMode(e.target.value),
        },
        options.map(([value, label]) => m('option', { value, selected: sortMode === value }, label))
      ),
      loadingAll ? m('span.NestedRepliesReplySort-loading', trans('sort_loading')) : null,
    ]);
  }

  // The tree layout needs every reply in the discussion so children can be
  // nested under their parent, so we load all pages once per discussion (for
  // every sort mode, including the default `oldest`).
  function loadAllPosts() {
    if (allPosts !== null || loadingAll) return;

    loadingAll = true;
    m.redraw();

    fetchAllPosts()
      .then((posts) => {
        allPosts = posts && posts.length ? posts : [];
      })
      .catch(() => {
        allPosts = [];
      })
      .then(() => {
        loadingAll = false;
        m.redraw();
      });
  }

  function setSortMode(mode) {
    sortMode = mode;
    loadAllPosts();
    m.redraw();
  }

  // Core's infinite-scroll pagination anchors on `.PostStream-item[data-index]`
  // elements it expects to own. While our tree renders the whole discussion that
  // pagination must not run: core's anchorScroll throws when its selector finds
  // nothing. Once the tree owns a stream, its loadPage becomes a no-op.
  function guardStreamPagination(stream) {
    if (!stream || stream.__nestedRepliesPaginationGuarded) return;
    if (typeof stream.loadPage !== 'function') return;

    stream.__nestedRepliesPaginationGuarded = true;
    stream.loadPage = function () {};
  }

  // Flarum's post stream is a flat list. A nested-reply layout wants the original
  // post in its own card and every reply inside a second card, so regroup the
  // rendered vnodes without touching core.
  override(PostStream.prototype, 'view', function (original) {
    const nextDiscussion = this.discussion;

    if (nextDiscussion !== currentDiscussion) {
      currentDiscussion = nextDiscussion;
      collapsed.clear();
      expandedGroups.clear();
      allPosts = null;
      loadingAll = false;
      refreshing = false;
      foldPlan = { hidden: new Set(), moreAfter: new Map() };
    }

    // Kick off loading every page so the tree can be ordered. The flat native
    // stream renders meanwhile and is swapped out once the posts arrive.
    loadAllPosts();

    const vnode = original();

    if (allPosts && allPosts.length) {
      // The tree view renders the whole discussion from our own ordering, so
      // stop the native stream from paginating underneath it.
      if (this.stream) {
        this.stream.paused = true;
        guardStreamPagination(this.stream);
      }

      const { op, ordered } = buildReplyOrder(allPosts, sortMode);

      foldPlan = planSiblingFolding(ordered, {
        lookup,
        visibleReplies: settings.visibleReplies,
        expandedParents: expandedGroups,
        legacyMentions: settings.legacyMentions,
      });

      const chrono = [...allPosts].sort((a, b) => Number(a.number()) - Number(b.number()));
      const indexOf = new Map(chrono.map((post, i) => [String(post.id()), i]));

      const opItem = op ? makePostItem(op, indexOf.get(String(op.id())) || 0) : null;
      const replyItems = ordered.map((post) => makePostItem(post, indexOf.get(String(post.id())) || 0)).filter(Boolean);

      const grouped = [];
      if (opItem) grouped.push(m('div.NestedRepliesThreadCard', { key: 'nestedRepliesThreadCard' }, opItem));

      grouped.push(m('div.NestedRepliesReplyCard', { key: 'nestedRepliesReplyCard' }, [replySortVNode(), ...replyItems]));

      return m('div.PostStream', vnode.attrs, grouped);
    }

    if (this.stream) this.stream.paused = false;

    const children = vnode && Array.isArray(vnode.children) ? vnode.children : null;
    if (!children || !children.length) return vnode;

    const isPostItem = (child) => Boolean(child && child.attrs && child.attrs['data-id'] != null);
    const opIndex = children.findIndex((child) => child && child.attrs && String(child.attrs['data-number']) === '1');

    // If the original post isn't in the current page (e.g. scrolled into the
    // middle of a long discussion), leave the stream untouched.
    if (opIndex === -1) return vnode;

    const before = children.slice(0, opIndex);
    const op = children[opIndex];
    const rest = children.slice(opIndex + 1);
    const replies = rest.filter(isPostItem);
    const tail = rest.filter((child) => !isPostItem(child));

    const replyPosts = replies.map((child) => lookup(child.attrs['data-id'])).filter(Boolean);

    foldPlan = planSiblingFolding(replyPosts, {
      lookup,
      visibleReplies: settings.visibleReplies,
      expandedParents: expandedGroups,
      legacyMentions: settings.legacyMentions,
    });

    const grouped = [...before, m('div.NestedRepliesThreadCard', { key: 'nestedRepliesThreadCard' }, op)];

    if (replies.length) {
      grouped.push(m('div.NestedRepliesReplyCard', { key: 'nestedRepliesReplyCard' }, [replySortVNode(), ...replies]));
    }

    grouped.push(...tail);

    return m('div.PostStream', vnode.attrs, grouped);
  });

  // Flarum 1.x skips a Post's redraw unless its SubtreeRetainer says a rebuild
  // is needed, so invalidate the mounted posts ourselves before redrawing.
  function forceRedraw() {
    mounted.forEach((post) => {
      if (post.subtree && typeof post.subtree.invalidate === 'function') {
        post.subtree.invalidate();
      }
    });

    m.redraw();
  }

  function isInlineComposer() {
    return Boolean((inlineReply && inlineReply.mode === 'composer' && app.composer && app.composer.isVisible()) || editingPostId !== null);
  }

  // While the reply form is inline, hide the fixed composer shell and disable
  // the shell's layout side effects (page padding, phone backdrop, resizing).
  override(Composer.prototype, 'view', function (original) {
    // Keep the same root tag so Mithril reuses the shell's DOM node (and the
    // lifecycle handlers bound in oncreate) instead of orphaning them.
    if (isInlineComposer()) return m('div.Composer.Composer--nestedInlineHidden');
    return original();
  });

  override(Composer.prototype, 'updateBodyPadding', function (original) {
    if (isInlineComposer()) return;
    return original();
  });

  override(Composer.prototype, 'animatePositionChange', function (original) {
    if (isInlineComposer()) return;
    return original();
  });

  override(Composer.prototype, 'updateHeight', function (original) {
    if (isInlineComposer()) return;
    return original();
  });

  function closeInlineReply() {
    if (inlineReply && inlineReply.mode === 'composer' && app.composer && app.composer.isVisible()) {
      app.composer.close();

      // A cancelled discard confirmation leaves the composer visible; keep the
      // inline host so the draft is not lost.
      if (app.composer.isVisible()) return;
    }

    inlineReply = null;
    inlineDraft('');
    composerPreview = false;
    forceRedraw();
  }

  // Make a freshly posted reply visible: clear any collapsed ancestor and
  // expand every sibling group along the parent chain, so a reply that would
  // otherwise be folded behind "Show more replies" is shown.
  function revealReply(parentId) {
    let id = parentId ? String(parentId) : null;
    const seen = new Set();

    while (id && !seen.has(id)) {
      seen.add(id);
      collapsed.delete(id);
      expandedGroups.add(id);

      const post = lookup(id);
      id = post ? getParentId(post, settings.legacyMentions) : null;
    }
  }

  // The newest loaded post in a discussion. Used to focus a reply just posted
  // through the native composer, whose id we never receive.
  function latestPostIn(discussion) {
    if (!discussion || !app.store || typeof app.store.all !== 'function') return null;

    const discussionId = String(discussion.id());
    const posts = app.store.all('posts').filter((post) => {
      if (!post || typeof post.number !== 'function') return false;

      const related = post.discussion && post.discussion();
      if (related) return String(related.id()) === discussionId;

      const attr = post.attribute ? post.attribute('discussionId') : null;
      return attr != null && String(attr) === discussionId;
    });

    if (!posts.length) return null;

    return posts.reduce((latest, post) => (Number(post.number()) >= Number(latest.number()) ? post : latest));
  }

  // Refetch every page without dropping the current tree, so the view never
  // flashes back to the native fallback. Optionally scroll a just-posted reply
  // into view and briefly highlight it.
  function refreshTree(focusPostId) {
    if (refreshing) return;
    refreshing = true;

    fetchAllPosts()
      .then((posts) => {
        if (posts && posts.length) allPosts = posts;
      })
      .catch(() => {})
      .then(() => {
        refreshing = false;

        if (focusPostId != null) {
          highlightedPostId = String(focusPostId);

          if (highlightTimer) clearTimeout(highlightTimer);
          highlightTimer = setTimeout(() => {
            highlightedPostId = null;
            forceRedraw();
          }, HIGHLIGHT_DURATION);
        }

        forceRedraw();

        if (focusPostId != null) {
          requestAnimationFrame(() => {
            const element = document.querySelector(`.PostStream-item[data-id="${focusPostId}"]`);
            if (element && element.scrollIntoView) element.scrollIntoView({ block: 'center', behavior: 'smooth' });
          });
        }
      });
  }

  function openInlineReply(post) {
    const id = String(post.id());

    if (inlineReply && inlineReply.postId !== id && String(inlineDraft() || '').trim()) {
      app.modal.show(DeleteConfirmModal, {
        title: app.translator.trans('mtareq-nested-replies.forum.reply_form_discard_title'),
        message: app.translator.trans('mtareq-nested-replies.forum.reply_form_discard'),
        confirmLabel: app.translator.trans('core.lib.continue'),
        onconfirm: () => {
          inlineDraft('');
          openInlineReply(post);
        },
      });
      return;
    }

    const discussion = post.discussion();

    if (settings.replyForm === 'composer') {
      const previous = inlineReply;
      const baseline = latestPostIn(discussion);
      inlineReply = {
        postId: id,
        discussion,
        mode: 'composer',
        // Newest post id when the form opened; hide() compares against it to
        // tell a real submit apart from a cancel.
        baselinePostId: baseline && baseline.id ? String(baseline.id()) : null,
      };
      composerPreview = false;
      pendingParentId = id;
      originalReplyAction.call(discussion);

      const body = app.composer && app.composer.body;
      const isReply = body && body.attrs && body.attrs.discussion && !body.attrs.post;
      const sameDiscussion = isReply && body.attrs.discussion === discussion;

      // An unrelated composer is open (e.g. editing a post), or a reply composer
      // for another discussion: do not hijack it.
      if (app.composer && app.composer.isVisible() && !sameDiscussion) {
        inlineReply = previous;
        pendingParentId = null;
        forceRedraw();
        return;
      }

      if (sameDiscussion) {
        // Already open for this discussion: replyAction skipped the load (and our
        // load override), so retarget the parent directly.
        body.attrs.replyToPostId = id;
        pendingParentId = null;
      }
      // Otherwise the load is in flight; the app.composer.load override applies
      // pendingParentId when the body arrives.

      forceRedraw();
      return;
    }

    inlineReply = { postId: id, discussion, mode: 'quick' };
    inlineDraft('');
    forceRedraw();

    // Scroll the reply form into view after it renders.
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        const targetEl =
          document.querySelector(`.PostStream-item[data-id="${id}"] .NestedRepliesInlineReply`) ||
          document.querySelector(`.PostStream-item[data-id="${id}"] .item-nestedRepliesInlineReply`) ||
          document.querySelector(`.PostStream-item[data-id="${id}"] .Post-footer`) ||
          document.querySelector(`.PostStream-item[data-id="${id}"]`);

        if (targetEl && targetEl.scrollIntoView) {
          targetEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
      });
    });
  }

  function editPost(post) {
    if (!post) return;

    // Close any open reply form first.
    if (inlineReply) closeInlineReply();

    if (app.composer && typeof app.composer.hide === 'function') {
      app.composer.hide();
    }

    const content = typeof post.content === 'function' ? post.content() : '';
    editingPostId = String(post.id());
    editDraft(content || '');
    forceRedraw();

    // Scroll the post into view so the edit form is visible.
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        const targetEl =
          document.querySelector(`.PostStream-item[data-id="${editingPostId}"] .NestedRepliesInlineEdit`) ||
          document.querySelector(`.PostStream-item[data-id="${editingPostId}"] .item-nestedRepliesInlineEdit`) ||
          document.querySelector(`.PostStream-item[data-id="${editingPostId}"] .Post-footer`) ||
          document.querySelector(`.PostStream-item[data-id="${editingPostId}"]`);

        if (targetEl && targetEl.scrollIntoView) {
          targetEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
      });
    });
  }

  function cancelEdit() {
    editingPostId = null;
    editDraft('');
    forceRedraw();
  }

  function toggleCollapse(post) {
    const id = String(post.id());

    if (collapsed.has(id)) collapsed.delete(id);
    else collapsed.add(id);

    forceRedraw();
  }

  // Reveal every reply hidden behind a folded sibling group's control.
  function expandGroup(parentId) {
    expandedGroups.add(String(parentId));
    forceRedraw();
  }

  extend(Post.prototype, 'oncreate', function () {
    mounted.add(this);
    decorate(this);

    // Keep the liked colour in sync even when the post doesn't rebuild.
    if (this.element) {
      this.element.addEventListener('click', (e) => {
        if (e.target && e.target.closest && e.target.closest('.item-like')) {
          requestAnimationFrame(() => {
            if (this.element) syncLikedClass(this.element, this.attrs.post);
          });
        }
      });
    }
  });

  extend(Post.prototype, 'onupdate', function () {
    decorate(this);
  });

  extend(Post.prototype, 'onremove', function () {
    mounted.delete(this);
  });

  // Surface who a reply answers as a tag in the header, instead of the inline
  // mention Flarum renders at the start of the body.
  extend(CommentPost.prototype, 'headerItems', function (items) {
    if (!settings.showReplyTag) return;

    const post = this.attrs.post;
    const target = getReplyTarget(post, lookup, settings.legacyMentions);

    if (!target || !target.name) return;

    items.add(
      'nestedRepliesReplyTag',
      m(
        Link,
        {
          className: 'NestedRepliesReplyTag',
          href: target.post ? app.route.post(target.post) : '#',
          title: target.name,
          onclick: (e) => {
            if (target.post) {
              const targetEl =
                document.querySelector(`.PostStream-item[data-id="${target.post.id()}"]`) ||
                document.querySelector(`[data-id="${target.post.id()}"]`);
              if (targetEl) {
                e.preventDefault();
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                targetEl.classList.remove('pulsate');
                void targetEl.offsetWidth;
                targetEl.classList.add('pulsate');
                setTimeout(() => targetEl.classList.remove('pulsate'), 2000);
              }
            }
          },
        },
        [
          icon('fas fa-reply'),
          m('span.NestedRepliesReplyTag-label', app.translator.trans('mtareq-nested-replies.forum.reply_to', { username: target.name })),
        ]
      ),
      95
    );
  });

  // Reddit-style controls: a circular fold/unfold button on every comment, and
  // an "N more replies" line underneath a folded comment.
  // CommentPost defines its own actionItems() and does not call the base
  // Post.actionItems(), so this must target CommentPost.
  extend(CommentPost.prototype, 'actionItems', function (items) {
    const post = this.attrs.post;
    if (!post) return;

    // Skip vote rail and collapse toggle on deleted / hidden posts — they
    // should not be interactive and the card layout breaks around them.
    if (post.isHidden && post.isHidden()) return;
    if (post.isDeleted && post.isDeleted()) return;

    // Only offer collapse when the reply actually has replies. The backend
    // serializes the subtree count, so this is accurate even before every page
    // of nested replies has loaded.
    const replyCount = Number(post.attribute ? post.attribute('nestedRepliesReplyCount') : 0);

    if (!isOriginalPost(post) && replyCount > 0) {
      items.add(
        'nestedRepliesCollapse',
        m(CollapseToggle, {
          collapsed: collapsed.has(String(post.id())),
          onclick: () => toggleCollapse(post),
        }),
        11
      );
    }

    if (settings.showVotes) {
      items.add('nestedRepliesVotes', m(VoteRail, { post, adapter: votes }), 10);
    }
  });

  // Remove Like and Reply actions on deleted posts — they should not be
  // interactive. This runs after core and extensions add their items.
  extend(CommentPost.prototype, 'actionItems', function (items) {
    const post = this.attrs.post;
    if (!post) return;
    if (!post.isDeleted || !post.isDeleted()) return;

    items.delete('like');
    items.delete('reply');
  });

  extend(CommentPost.prototype, 'footerItems', function (items) {
    const post = this.attrs.post;
    if (!post) return;

    const id = String(post.id());

    // Folded sibling groups anchor their "Show more replies" control to the
    // last visible reply of the kept branch. A group can be nested inside
    // another group's kept branch, so render deepest-first.
    const groups = foldPlan.moreAfter.get(id);
    if (groups && groups.length) {
      const actualDepth = getDepth(post, settings.maxDepth, lookup, settings.legacyMentions);

      [...groups]
        .sort((a, b) => b.targetDepth - a.targetDepth)
        .forEach((group, index) => {
          items.add(
            'nestedRepliesShowMore' + index,
            m(MoreReplies, {
              count: group.count,
              depth: group.targetDepth,
              // Line the control up with the depth of the hidden replies. Hidden
              // replies always belong one level below a post in the anchor's own
              // branch, so the control may sit beside or to the right of the
              // anchor's content column.
              indent: group.targetDepth - actualDepth,
              onclick: () => expandGroup(group.parentId),
            }),
            20
          );
        });
    }

    if (inlineReply && inlineReply.postId === id) {
      const depth = getDepth(post, settings.maxDepth, lookup, settings.legacyMentions);
      const childDepth = Math.min(depth + 1, settings.maxDepth);

      items.add(
        'nestedRepliesInlineReply',
        m(NestedRepliesInlineReply, {
          post,
          discussion: post.discussion(),
          mode: inlineReply.mode,
          indent: childDepth - depth,
          draft: inlineDraft,
          preview: composerPreview,
          onRedraw: forceRedraw,
          onCancel: closeInlineReply,
          onSubmitted: (created) => {
            const parentId = inlineReply ? inlineReply.postId : null;
            closeInlineReply();
            revealReply(parentId);
            refreshTree(created && created.id ? created.id() : null);
          },
          onClosed: () => {
            closeInlineReply();
            refreshTree();
          },
        }),
        5
      );
    }

    // Inline edit form — rendered in the same position as the reply form but
    // for editing an existing post.
    if (editingPostId === id) {
      const depth = getDepth(post, settings.maxDepth, lookup, settings.legacyMentions);
      const childDepth = Math.min(depth + 1, settings.maxDepth);

      items.add(
        'nestedRepliesInlineEdit',
        m(NestedRepliesInlineReply, {
          post,
          discussion: post.discussion(),
          mode: 'quick',
          indent: childDepth - depth,
          draft: editDraft,
          editMode: true,
          onRedraw: forceRedraw,
          onCancel: cancelEdit,
          onSubmitted: () => {
            cancelEdit();
            refreshTree(post.id());
          },
        }),
        5
      );
    }

    if (collapsed.has(id)) {
      const count = Number(post.attribute ? post.attribute('nestedRepliesReplyCount') : 0);
      if (count <= 0) return;

      items.add(
        'nestedRepliesMoreReplies',
        m(
          Button,
          {
            className: 'Button Button--link NestedRepliesMoreReplies',
            onclick: () => toggleCollapse(post),
          },
          [icon('fas fa-plus'), m('span', app.translator.trans('mtareq-nested-replies.forum.more_replies', { count }))]
        ),
        10
      );
    }
  });
});

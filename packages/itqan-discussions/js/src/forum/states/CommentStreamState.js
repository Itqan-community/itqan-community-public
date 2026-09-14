import app from 'flarum/forum/app';

/**
 * Append-only comment stream for threaded discussions.
 * Paginate by root comments; never unload prior pages; preserve scroll on append.
 */
export default class CommentStreamState {
  constructor(discussion) {
    this.discussion = discussion;
    this.sort = discussion.attribute('commentSort') || app.itqanDiscussionSort || 'oldest';
    this.loading = false;
    this.expanding = {}; // postId -> bool
    this.rootsOffset = discussion.attribute('rootsLoaded') || this.countLoadedRoots();
    this.hasMoreRoots =
      discussion.attribute('rootsHasMore') !== null && discussion.attribute('rootsHasMore') !== undefined
        ? !!discussion.attribute('rootsHasMore')
        : true;
    this.error = null;
  }

  countLoadedRoots() {
    const posts = this.discussion.posts() || [];
    let n = 0;
    posts.forEach((post) => {
      if (!post || typeof post.number !== 'function') return;
      if (post.number() === 1) return;
      const parentId = typeof post.parentId === 'function' ? post.parentId() : null;
      if (!parentId) n++;
    });
    return n;
  }

  /**
   * Merge newly loaded posts into the discussion's posts relationship (append-only).
   */
  mergePosts(posts) {
    if (!posts || !posts.length) return;

    const existingIds = new Set((this.discussion.postIds() || []).map(String));
    const relationship = (this.discussion.data.relationships && this.discussion.data.relationships.posts) || {
      data: [],
    };
    const data = Array.isArray(relationship.data) ? relationship.data.slice() : [];

    posts.forEach((post) => {
      const id = String(post.id());
      if (existingIds.has(id)) return;
      existingIds.add(id);
      data.push({ type: 'posts', id });
    });

    this.discussion.pushData({
      relationships: {
        posts: { data },
      },
    });
  }

  applyMeta(meta) {
    if (!meta) return;
    if (typeof meta.rootsLoaded === 'number') {
      this.rootsOffset = (meta.offset || 0) + meta.rootsLoaded;
    }
    if (typeof meta.rootsHasMore === 'boolean') {
      this.hasMoreRoots = meta.rootsHasMore;
    }
    if (meta.sort) {
      this.sort = meta.sort;
    }

    this.discussion.pushAttributes({
      rootsLoaded: this.rootsOffset,
      rootsHasMore: this.hasMoreRoots,
      commentSort: this.sort,
      rootCommentCount: meta.rootCommentCount ?? this.discussion.attribute('rootCommentCount'),
    });
  }

  async loadMoreRoots() {
    if (this.loading || !this.hasMoreRoots) return [];

    this.loading = true;
    this.error = null;
    m.redraw();

    try {
      const payload = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/discussions/${this.discussion.id()}/comment-tree`,
        params: {
          'page[offset]': this.rootsOffset,
          'page[limit]': 20,
          sort: this.sort,
        },
      });

      const posts = app.store.pushPayload(payload) || [];
      this.mergePosts(posts);
      this.applyMeta(payload.meta || {});
      this.markTruncatedFromMeta(payload.meta);

      return posts;
    } catch (e) {
      this.error = e;
      throw e;
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  markTruncatedFromMeta(meta) {
    const ids = (meta && meta.truncatedParentIds) || [];
    ids.forEach((id) => {
      const post = app.store.getById('posts', String(id));
      if (post) {
        post.pushAttributes({ hasMoreReplies: true });
      }
    });
  }

  /**
   * Continue this thread — load more direct replies under a post.
   */
  async expandReplies(post, offset = 0, limit = 20) {
    const postId = String(post.id());
    if (this.expanding[postId]) return [];

    this.expanding[postId] = true;
    m.redraw();

    try {
      const payload = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/posts/${postId}/replies`,
        params: {
          'page[offset]': offset,
          'page[limit]': limit,
          sort: this.sort,
        },
      });

      const posts = app.store.pushPayload(payload) || [];
      this.mergePosts(posts);
      this.markTruncatedFromMeta(payload.meta);

      const hasMore = !!(payload.meta && payload.meta.hasMore);
      // Direct children may still remain; keep hasMoreReplies if meta says so
      // or if replyCount exceeds loaded children in store.
      const loadedChildren = this.countLoadedChildren(post);
      const replyCount = typeof post.replyCount === 'function' ? post.replyCount() : 0;
      post.pushAttributes({
        hasMoreReplies: hasMore || loadedChildren < replyCount,
      });

      return posts;
    } finally {
      this.expanding[postId] = false;
      m.redraw();
    }
  }

  countLoadedChildren(post) {
    const parentId = String(post.id());
    const posts = this.discussion.posts() || [];
    let n = 0;
    posts.forEach((p) => {
      if (!p || typeof p.parentId !== 'function') return;
      if (String(p.parentId()) === parentId) n++;
    });
    return n;
  }

  /**
   * Change sort: clear non-OP posts and refetch first page of roots.
   */
  async changeSort(sort) {
    if (this.loading) return;
    this.sort = sort;
    app.itqanDiscussionSort = sort;
    this.loading = true;
    this.error = null;
    m.redraw();

    try {
      const payload = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/discussions/${this.discussion.id()}/comment-tree`,
        params: {
          'page[offset]': 0,
          'page[limit]': 20,
          sort: this.sort,
        },
      });

      const posts = app.store.pushPayload(payload) || [];

      // Keep OP only, then merge new tree
      const allPosts = this.discussion.posts() || [];
      const op = allPosts.find((p) => p && typeof p.number === 'function' && p.number() === 1);
      const data = op ? [{ type: 'posts', id: String(op.id()) }] : [];
      this.discussion.pushData({
        relationships: {
          posts: { data },
        },
      });

      this.rootsOffset = 0;
      this.hasMoreRoots = true;
      this.mergePosts(posts);
      this.applyMeta(Object.assign({}, payload.meta || {}, { offset: 0 }));
      this.markTruncatedFromMeta(payload.meta);

      return posts;
    } catch (e) {
      this.error = e;
      throw e;
    } finally {
      this.loading = false;
      m.redraw();
    }
  }
}

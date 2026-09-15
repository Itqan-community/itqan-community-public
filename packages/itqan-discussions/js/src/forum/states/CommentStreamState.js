import app from 'flarum/forum/app';

/**
 * Windowed comment stream for threaded discussions.
 * Paginate by root comments; never unload; insert replies in DFS order.
 */
export default class CommentStreamState {
  constructor(discussion) {
    this.discussion = discussion;
    this.sort = discussion.attribute('commentSort') || app.itqanDiscussionSort || 'oldest';
    this.loading = false;
    this.loadingPrevious = false;
    this.expanding = {}; // postId -> bool
    this.rootsOffset = discussion.attribute('rootsOffset');
    if (this.rootsOffset === null || this.rootsOffset === undefined) {
      // Legacy: rootsLoaded meant "how many roots from start are loaded"
      const loaded = discussion.attribute('rootsLoaded');
      this.rootsOffset = typeof loaded === 'number' ? Math.max(0, loaded - this.countLoadedRoots()) : 0;
      if (this.rootsOffset === 0 && typeof loaded === 'number') {
        // First page from start: next offset = loaded count
        this.nextRootsOffset = loaded;
      } else {
        this.nextRootsOffset = this.rootsOffset + this.countLoadedRoots();
      }
    } else {
      this.nextRootsOffset =
        discussion.attribute('rootsLoaded') != null
          ? this.rootsOffset + Number(discussion.attribute('rootsLoaded'))
          : this.rootsOffset + this.countLoadedRoots();
    }
    this.hasMoreRoots =
      discussion.attribute('rootsHasMore') !== null && discussion.attribute('rootsHasMore') !== undefined
        ? !!discussion.attribute('rootsHasMore')
        : true;
    this.hasPreviousRoots =
      discussion.attribute('rootsHasPrevious') !== null &&
      discussion.attribute('rootsHasPrevious') !== undefined
        ? !!discussion.attribute('rootsHasPrevious')
        : this.rootsOffset > 0;
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

  setPostIds(ids) {
    const data = ids.map((id) => ({ type: 'posts', id: String(id) }));
    this.discussion.pushData({
      relationships: {
        posts: { data },
      },
    });
  }

  /**
   * Merge newly loaded posts into the discussion's posts relationship (append).
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

  /**
   * Prepend newly loaded earlier roots (and their trees) before the first
   * non-OP post, preserving everything already loaded.
   */
  prependPosts(posts) {
    if (!posts || !posts.length) return;

    const existingIds = new Set((this.discussion.postIds() || []).map(String));
    const newRefs = [];
    posts.forEach((post) => {
      const id = String(post.id());
      if (existingIds.has(id)) return;
      existingIds.add(id);
      newRefs.push({ type: 'posts', id });
    });
    if (!newRefs.length) return;

    const relationship = (this.discussion.data.relationships && this.discussion.data.relationships.posts) || {
      data: [],
    };
    const data = Array.isArray(relationship.data) ? relationship.data.slice() : [];

    // Keep OP first if present, then prepended batch, then the rest.
    let insertAt = 0;
    if (data.length) {
      const first = app.store.getById('posts', String(data[0].id));
      if (first && typeof first.number === 'function' && first.number() === 1) {
        insertAt = 1;
      }
    }

    data.splice(insertAt, 0, ...newRefs);
    this.discussion.pushData({
      relationships: {
        posts: { data },
      },
    });
  }

  applyMeta(meta, { prepend = false } = {}) {
    if (!meta) return;

    const offset = typeof meta.offset === 'number' ? meta.offset : null;
    const rootsLoaded = typeof meta.rootsLoaded === 'number' ? meta.rootsLoaded : null;

    if (offset !== null) {
      if (prepend) {
        this.rootsOffset = offset;
      } else if (this.rootsOffset === null || this.rootsOffset === undefined) {
        this.rootsOffset = offset;
      }
      if (rootsLoaded !== null) {
        const end = offset + rootsLoaded;
        if (prepend) {
          // Keep nextRootsOffset as the high-water mark of the window.
          this.nextRootsOffset = Math.max(this.nextRootsOffset || end, end);
        } else {
          this.nextRootsOffset = end;
          if (this.rootsOffset === null || this.rootsOffset === undefined || offset < this.rootsOffset) {
            this.rootsOffset = offset;
          }
        }
      }
    } else if (rootsLoaded !== null && !prepend) {
      this.nextRootsOffset = (this.rootsOffset || 0) + rootsLoaded;
    }

    if (typeof meta.rootsHasMore === 'boolean') {
      this.hasMoreRoots = meta.rootsHasMore;
    } else if (typeof meta.rootCommentCount === 'number' && this.nextRootsOffset != null) {
      this.hasMoreRoots = this.nextRootsOffset < meta.rootCommentCount;
    }

    if (typeof meta.rootsHasPrevious === 'boolean') {
      this.hasPreviousRoots = meta.rootsHasPrevious;
    } else if (this.rootsOffset != null) {
      this.hasPreviousRoots = this.rootsOffset > 0;
    }

    if (meta.sort) {
      this.sort = meta.sort;
    }

    this.discussion.pushAttributes({
      rootsOffset: this.rootsOffset,
      rootsLoaded: (this.nextRootsOffset || 0) - (this.rootsOffset || 0),
      rootsHasMore: this.hasMoreRoots,
      rootsHasPrevious: this.hasPreviousRoots,
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
      const offset = this.nextRootsOffset != null ? this.nextRootsOffset : this.rootsOffset || 0;
      const payload = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/discussions/${this.discussion.id()}/comment-tree`,
        params: {
          'page[offset]': offset,
          'page[limit]': 20,
          sort: this.sort,
        },
      });

      const posts = app.store.pushPayload(payload) || [];
      this.mergePosts(posts);
      this.applyMeta(Object.assign({}, payload.meta || {}, { offset }));
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

  /**
   * Load earlier root comments and prepend them without unloading later ones.
   */
  async loadPreviousRoots() {
    if (this.loadingPrevious || !this.hasPreviousRoots) return [];
    if ((this.rootsOffset || 0) <= 0) {
      this.hasPreviousRoots = false;
      return [];
    }

    this.loadingPrevious = true;
    this.error = null;
    m.redraw();

    try {
      const limit = 20;
      const offset = Math.max(0, (this.rootsOffset || 0) - limit);
      const payload = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/discussions/${this.discussion.id()}/comment-tree`,
        params: {
          'page[offset]': offset,
          'page[limit]': Math.min(limit, this.rootsOffset || limit),
          sort: this.sort,
          direction: 'prev',
        },
      });

      const posts = app.store.pushPayload(payload) || [];
      this.prependPosts(posts);
      this.applyMeta(Object.assign({}, payload.meta || {}, { offset }), { prepend: true });
      this.markTruncatedFromMeta(payload.meta);

      return posts;
    } catch (e) {
      this.error = e;
      throw e;
    } finally {
      this.loadingPrevious = false;
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
      this.insertPostsAfterParent(post, posts);
      this.markTruncatedFromMeta(payload.meta);

      const hasMore = !!(payload.meta && payload.meta.hasMore);
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

  /**
   * Insert expanded replies after the parent (or after its last loaded descendant).
   */
  insertPostsAfterParent(parent, posts) {
    if (!posts || !posts.length) return;

    const parentId = String(parent.id());
    const existingIds = new Set((this.discussion.postIds() || []).map(String));
    const toInsert = posts.filter((p) => p && !existingIds.has(String(p.id())));
    if (!toInsert.length) return;

    const ids = (this.discussion.postIds() || []).map(String);
    const parentIdx = ids.indexOf(parentId);
    if (parentIdx < 0) {
      this.mergePosts(toInsert);
      return;
    }

    const end = this.findSubtreeEndIndex(ids, parentId);
    const refs = toInsert.map((p) => ({ type: 'posts', id: String(p.id()) }));
    const data = ids.map((id) => ({ type: 'posts', id }));
    data.splice(end, 0, ...refs);

    this.discussion.pushData({
      relationships: {
        posts: { data },
      },
    });
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

  isDescendantOf(post, ancestorId) {
    if (!post) return false;
    const target = String(ancestorId);
    const visited = new Set();
    let current = post;
    while (current) {
      const pid = typeof current.parentId === 'function' ? current.parentId() : null;
      if (!pid) return false;
      const key = String(pid);
      if (key === target) return true;
      if (visited.has(key)) return false;
      visited.add(key);
      current = app.store.getById('posts', key);
    }
    return false;
  }

  /**
   * Index after the last loaded descendant of parentId (exclusive end).
   */
  findSubtreeEndIndex(ids, parentId) {
    const parentKey = String(parentId);
    const parentIdx = ids.indexOf(parentKey);
    if (parentIdx < 0) return ids.length;

    let end = parentIdx + 1;
    while (end < ids.length) {
      const post = app.store.getById('posts', ids[end]);
      if (!post || !this.isDescendantOf(post, parentKey)) break;
      end++;
    }
    return end;
  }

  /**
   * Insert a newly created post in DFS tree order.
   * @returns {{ inserted: boolean, reason?: string }}
   */
  insertPost(post) {
    if (!post) return { inserted: false, reason: 'missing' };

    const newId = String(post.id());
    const ids = (this.discussion.postIds() || []).map(String);
    if (ids.includes(newId)) {
      return { inserted: true };
    }

    const parentId = typeof post.parentId === 'function' ? post.parentId() : null;
    const parentKey = parentId != null ? String(parentId) : null;

    // Nested reply whose parent is not in the loaded window — do not dump at end.
    if (parentKey) {
      const parentIdx = ids.indexOf(parentKey);
      if (parentIdx < 0) {
        return { inserted: false, reason: 'parent_not_loaded' };
      }

      const end = this.findSubtreeEndIndex(ids, parentKey);
      ids.splice(end, 0, newId);
      this.setPostIds(ids);

      const parent = app.store.getById('posts', parentKey);
      if (parent && typeof parent.replyCount === 'function') {
        const count = parent.replyCount() || 0;
        parent.pushAttributes({ replyCount: count + 1 });
      }

      return { inserted: true };
    }

    // Root comment — place by current sort among loaded roots.
    if (typeof post.number === 'function' && post.number() > 1) {
      const count = this.discussion.attribute('rootCommentCount') || 0;
      this.discussion.pushAttributes({ rootCommentCount: count + 1 });
    }

    if (this.sort === 'latest') {
      // Newest first among roots: after OP.
      let insertAt = 0;
      if (ids.length) {
        const first = app.store.getById('posts', ids[0]);
        if (first && typeof first.number === 'function' && first.number() === 1) {
          insertAt = 1;
        }
      }
      ids.splice(insertAt, 0, newId);
    } else {
      // oldest / top: append after last loaded post (end of current window).
      ids.push(newId);
    }

    this.setPostIds(ids);
    return { inserted: true };
  }

  /** @deprecated use insertPost — kept for call-site compatibility */
  addPost(post) {
    return this.insertPost(post);
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

      const allPosts = this.discussion.posts() || [];
      const op = allPosts.find((p) => p && typeof p.number === 'function' && p.number() === 1);
      const data = op ? [{ type: 'posts', id: String(op.id()) }] : [];
      this.discussion.pushData({
        relationships: {
          posts: { data },
        },
      });

      this.rootsOffset = 0;
      this.nextRootsOffset = 0;
      this.hasMoreRoots = true;
      this.hasPreviousRoots = false;
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

  /**
   * Load a root window around a post number (deep link). Replaces non-OP posts
   * with the near window so earlier/later roots can be loaded via prev/next.
   */
  async loadNearNumber(number) {
    if (this.loading || !number) return [];
    this.loading = true;
    this.error = null;
    m.redraw();

    try {
      const payload = await app.request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/discussions/${this.discussion.id()}/comment-tree`,
        params: {
          near: number,
          'page[limit]': 20,
          sort: this.sort,
        },
      });

      const posts = app.store.pushPayload(payload) || [];

      // Keep OP, replace the rest with the near window (honest windowed load).
      const allPosts = this.discussion.posts() || [];
      const op = allPosts.find((p) => p && typeof p.number === 'function' && p.number() === 1);
      const data = op ? [{ type: 'posts', id: String(op.id()) }] : [];
      this.discussion.pushData({
        relationships: {
          posts: { data },
        },
      });

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

  /**
   * Restore a snapshot of post IDs (used after CreatePost payload clobber).
   */
  restorePostIds(snapshotIds) {
    if (!Array.isArray(snapshotIds)) return;
    this.setPostIds(snapshotIds.map(String));
  }
}

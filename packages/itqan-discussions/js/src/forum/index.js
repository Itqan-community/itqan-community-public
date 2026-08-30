import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import classList from 'flarum/common/utils/classList';
import Button from 'flarum/common/components/Button';
import CommentPost from 'flarum/forum/components/CommentPost';
import DiscussionListState from 'flarum/forum/states/DiscussionListState';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';

import VoteButtons from './components/VoteButtons';
import {
  depthOf,
  descendantCount,
  isCollapsed,
  isHiddenByAncestor,
  repliesTo,
  toggleCollapsed,
} from './components/CommentTree';

export { default as VoteButtons } from './components/VoteButtons';
export * as CommentTree from './components/CommentTree';

// Voting replaces flarum/likes, as agreed on #21, and the two must not be
// shown together: a like and an upvote ask the same question, and running both
// splits the answer across two tallies.
//
// Removing the like button from here was tried and dropped. Both extensions
// add to the same item list, and which one wins depends on the order Flarum
// happens to boot them in — a coin toss is no way to decide whether a control
// exists. Disabling flarum/likes is the admin action that settles it, and the
// conversion migration carries the existing likes over as upvotes so nothing
// is lost by doing so.
app.initializers.add('itqan-discussions', () => {
  // Core ships a "top" sort that means most-commented. The brief defines it as
  // highest-voted (الأعلى تصويتاً), so it is repointed rather than duplicated —
  // two entries both labelled "Top" would be worse than one that means what
  // the label says. "Hot" is new: score and age together, so a good answer
  // from this morning can outrank an old one with more votes.
  extend(DiscussionListState.prototype, 'sortMap', function (map) {
    map.top = '-votes';
    map.hot = '-hotness';
  });

  // A column of its own at the leading edge of the row, before the avatar —
  // where a reader scanning a list of answers looks for the score, and where
  // it can be acted on without opening the discussion. Stacked rather than in
  // a line so it costs one narrow column instead of three.
  //
  // The discussion votes on its opening post, which is what `firstPostId` is
  // for; the score shown is that post's.
  extend(DiscussionListItem.prototype, 'contentItems', function (items) {
    const discussion = this.attrs.discussion;

    if (discussion.attribute('votes') === undefined) return;

    items.add(
      'itqanVote',
      <VoteButtons model={discussion} postId={discussion.attribute('firstPostId')} vertical />,
      110
    );
  });

  // Only real comments carry a score. Event posts — renames, locks, tag
  // changes — are not opinions to agree with.
  //
  // The footer, not the action row. Core hides `.Post-actions` at
  // `opacity: 0` until the post is hovered, and there is no hover on a phone
  // at all — a score nobody can see until they reach for it is not a score.
  // The footer is where flarum/likes puts "N people liked this", and it is
  // always visible.
  extend(CommentPost.prototype, 'footerItems', function (items) {
    const post = this.attrs.post;

    if (post.isHidden() || post.attribute('votes') === undefined) return;

    items.add('itqanVote', <VoteButtons model={post} postId={post.id()} />, 100);
  });

  // Core keeps a SubtreeRetainer on every post and returns it from
  // `onbeforeupdate`, so Mithril skips a post whose watched values have not
  // moved. Collapsing is not one of those values, so without this the state
  // changes and the stream carries on showing the old classes — the toggle
  // appears to do nothing at all. The descendants are registered as well as the
  // post itself: folding a branch changes how every post under it renders, and
  // each of them has to be told it is stale.
  extend(CommentPost.prototype, 'oninit', function () {
    this.subtree.check(
      () => isCollapsed(this.attrs.post),
      () => isHiddenByAncestor(this.attrs.post),
      // Depth too, and not only for tidiness. A post whose parent has not been
      // loaded renders flat; scrolling up loads that parent and the depth
      // becomes real, but nothing about the post itself changed, so without
      // watching this the reply would keep its flat rendering until something
      // else happened to redraw it.
      () => depthOf(this.attrs.post)
    );
  });

  // The tree is drawn by indenting posts where they already are, not by
  // reordering them. A reply is later than the post it answers, so in a stream
  // sorted by time it is already below its parent — the indentation is the
  // only thing missing. Moving posts instead would mean fighting PostStream on
  // its own ground: it renders a sliding window of 20 and hangs `data-index`
  // and `data-number` off each item, and the scrubber, `calculatePosition`,
  // `scrollToNumber` and `goToIndex` all read those as a straight line.
  //
  // The depth is a class rather than an inline style so the whole ladder lives
  // in one place in tree.less, where the right-to-left build can flip it.
  extend(CommentPost.prototype, 'elementAttrs', function (attrs) {
    const post = this.attrs.post;

    if (post.isHidden()) return;

    const depth = depthOf(post);

    attrs.className = classList(attrs.className, {
      itqanTree: depth > 0,
      [`itqanTree--depth${depth}`]: depth > 0,
      'itqanTree--collapsed': isCollapsed(post),
      'itqanTree--hidden': isHiddenByAncestor(post),
    });
  });

  // In the footer, next to the score, and for the same reason it was put there
  // rather than in `actionItems`: core keeps `.Post-actions` at `opacity: 0`
  // until the post is hovered, and a phone has no hover at all. A control that
  // hides a run of posts has to be visible before the reader reaches for it.
  extend(CommentPost.prototype, 'footerItems', function (items) {
    const post = this.attrs.post;

    if (post.isHidden()) return;

    const replies = repliesTo(post);
    if (!replies.length) return;

    const collapsed = isCollapsed(post);

    items.add(
      'itqanTreeToggle',
      <Button
        className="Button Button--link itqanTree-toggle"
        icon={collapsed ? 'fas fa-caret-right' : 'fas fa-caret-down'}
        aria-expanded={collapsed ? 'false' : 'true'}
        onclick={() => {
          toggleCollapsed(post);

          // Redrawn here, and synchronously, because neither happens on its
          // own. Which posts are collapsed is not held on any model, so the
          // click carries no store update for Mithril to notice; and the
          // deferred `m.redraw()` still left the stream showing its old
          // classes, while the synchronous form applies them. PostStream:429
          // reaches for the same call for the same kind of reason — it needs
          // the stream settled before it measures rather than a frame later.
          m.redraw.sync();
        }}
      >
        {app.translator.trans(
          collapsed ? 'itqan-discussions.forum.tree.expand' : 'itqan-discussions.forum.tree.collapse',
          // The whole branch, not just the direct replies: that is what the
          // press actually removes from the stream.
          { count: descendantCount(post) }
        )}
      </Button>,
      90
    );
  });
});

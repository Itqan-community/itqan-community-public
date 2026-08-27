import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import DiscussionListState from 'flarum/forum/states/DiscussionListState';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';

import VoteButtons from './components/VoteButtons';

export { default as VoteButtons } from './components/VoteButtons';

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
});

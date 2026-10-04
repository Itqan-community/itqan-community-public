import { extend } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import NewDiscussionFab from './components/NewDiscussionFab';
import ReplyDock from './components/ReplyDock';

/**
 * Adds the composer shortcuts that the header and sidebar controls do not cover
 * on a phone, or once a long thread has been scrolled away from:
 *
 * - discussion list → a "new discussion" button (phone only, see the LESS);
 * - discussion page → a sticky reply input (phone) and, after scrolling, a
 *   floating reply button (wide screens).
 *
 * The extra component is appended to the page vnode's own children rather than
 * returned alongside it: a component `view()` that returns a second root would
 * have to be a fragment, and Mithril requires the children of one to be either
 * all keyed or all unkeyed — the page's own children are unkeyed, so mutating
 * them in place is both simpler and safer.
 */
export default function addReplyAffordances() {
  extend(DiscussionPage.prototype, 'view', function (view) {
    if (!view || !this.discussion) return;

    appendChild(view, <ReplyDock discussion={this.discussion} />);
  });

  extend(IndexPage.prototype, 'view', function (view) {
    if (!view) return;

    appendChild(view, <NewDiscussionFab />);
  });
}

function appendChild(view, child) {
  if (Array.isArray(view.children)) {
    view.children.push(child);

    return;
  }

  view.children = view.children == null ? [child] : [view.children, child];
}

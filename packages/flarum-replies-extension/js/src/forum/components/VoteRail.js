import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import icon from '../../common/icon';

export default class VoteRail extends Component {
  view() {
    const { post, adapter } = this.attrs;
    const available = adapter.isAvailable(post);
    // Ownership decides this, not the serialized hint alone: 2.0 shipped
    // without `canVote`, and a click then reached the server and raised a 403
    // toaster on your own post.
    const ownPost =
      typeof adapter.isOwnPost === 'function'
        ? adapter.isOwnPost(post)
        : Boolean(app.session.user) && post.attribute('canVote') === false;
    const score = adapter.getScore(post);
    const current = adapter.getUserVote(post);

    const button = (direction, iconName, key) => {
      const label = app.translator.trans(ownPost ? 'mtareq-nested-replies.forum.vote_own_post' : `mtareq-nested-replies.forum.${key}`);

      return m(
        `button.NestedRepliesVoteButton.NestedRepliesVoteButton--${direction}`,
        {
          type: 'button',
          className: current === direction ? 'is-active' : '',
          disabled: !available,
          title: label,
          'aria-label': label,
          onclick: () => {
            if (!available) return;
            adapter.vote(post, current === direction ? null : direction);
          },
        },
        icon(iconName)
      );
    };

    // A disabled button does not reliably show its own title tooltip, so the
    // explanation also sits on the rail itself.
    return m(
      'div.NestedRepliesVoteRail',
      {
        className: [available ? '' : 'is-disabled', ownPost ? 'is-own' : ''].filter(Boolean).join(' '),
        title: ownPost ? app.translator.trans('mtareq-nested-replies.forum.vote_own_post') : undefined,
      },
      [
        button('up', 'fas fa-arrow-up', 'upvote'),
        m('span.NestedRepliesVoteScore', score == null ? '' : String(score)),
        button('down', 'fas fa-arrow-down', 'downvote'),
      ]
    );
  }
}

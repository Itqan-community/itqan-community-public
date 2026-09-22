import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import icon from 'flarum/common/helpers/icon';

export default class VoteRail extends Component {
  view() {
    const { post, adapter } = this.attrs;
    const available = adapter.isAvailable();
    const score = adapter.getScore(post);
    const current = adapter.getUserVote(post);

    const button = (direction, iconName, key) =>
      m(
        `button.NestedRepliesVoteButton.NestedRepliesVoteButton--${direction}`,
        {
          type: 'button',
          className: current === direction ? 'is-active' : '',
          disabled: !available,
          title: app.translator.trans(`mtareq-nested-replies.forum.${key}`),
          onclick: () => {
            if (!available) return;
            adapter.vote(post, current === direction ? null : direction);
          },
        },
        icon(iconName)
      );

    return m('div.NestedRepliesVoteRail', { className: available ? '' : 'is-disabled' }, [
      button('up', 'fas fa-arrow-up', 'upvote'),
      m('span.NestedRepliesVoteScore', score == null ? '' : String(score)),
      button('down', 'fas fa-arrow-down', 'downvote'),
    ]);
  }
}

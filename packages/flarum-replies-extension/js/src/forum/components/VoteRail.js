import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import icon from 'flarum/common/helpers/icon';
import LogInModal from 'flarum/forum/components/LogInModal';

/**
 * Vertical vote rail. Works on either model shape:
 *   - a post:        attrs = { post, adapter }          (post rail)
 *   - a discussion row: attrs = { model, postId, adapter } (list rail, votes the first post)
 * Score/userVote are always read from `model` attributes.
 */
export default class VoteRail extends Component {
  view() {
    const { post, model: modelAttr, postId, adapter } = this.attrs;
    const model = modelAttr || post;
    const available = adapter.isAvailable();
    const score = adapter.getScore(model);
    const current = adapter.getUserVote(model);
    // The post actually voted on: explicit postId (list row) or the model itself.
    const targetId = postId != null ? postId : model && typeof model.id === 'function' ? model.id() : null;

    const handleClick = (direction) => {
      if (!available) {
        // Guests: invite login instead of a dead button (cycle-completer).
        app.modal.show(LogInModal);
        return;
      }
      adapter.vote(model, current === direction ? null : direction, targetId != null ? { id: targetId } : {});
    };

    const button = (direction, iconName, key) =>
      m(
        `button.NestedRepliesVoteButton.NestedRepliesVoteButton--${direction}`,
        {
          type: 'button',
          className: current === direction ? 'is-active' : '',
          'aria-pressed': current === direction ? 'true' : 'false',
          'aria-label': app.translator.trans(`mtareq-nested-replies.forum.${key}`),
          title: app.translator.trans(`mtareq-nested-replies.forum.${key}`),
          onclick: () => handleClick(direction),
        },
        icon(iconName)
      );

    return m(
      'div.NestedRepliesVoteRail',
      { className: available ? '' : 'is-disabled', role: 'group', 'aria-label': app.translator.trans('mtareq-nested-replies.forum.vote_rail_label') },
      [
        button('up', 'fas fa-arrow-up', 'upvote'),
        m('span.NestedRepliesVoteScore', { 'aria-live': 'polite' }, score == null ? '' : String(score)),
        button('down', 'fas fa-arrow-down', 'downvote'),
      ]
    );
  }
}

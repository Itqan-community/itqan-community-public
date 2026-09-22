import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import icon from 'flarum/common/helpers/icon';

// "Show more replies" control for a folded sibling group. `indent` is the
// difference between the depth the control belongs to and the depth of the post
// it is anchored to, so the control lines up with the hidden replies.
export default class MoreReplies extends Component {
  view() {
    const { count, indent } = this.attrs;

    return m(
      'button.NestedRepliesShowMore.Button.Button--link',
      {
        type: 'button',
        title: app.translator.trans('mtareq-nested-replies.forum.more_replies', { count }),
        // Move the control to the hidden replies' depth and centre its + badge
        // on that depth's guide line: the badge sits 16px inside the button, so
        // the margin carries an extra offset to put the badge's centre on the
        // line. The offset is derived from --gutter so it stays centred when
        // the gutter changes on mobile: it equals -29px at the desktop 24px
        // gutter. The guide line itself ends at the control's top edge.
        style: `margin-inline-start: calc(${indent || 0} * var(--indent) - var(--gutter) - 5px)`,
        onclick: this.attrs.onclick,
      },
      [
        m('span.NestedRepliesShowMore-toggle', icon('fas fa-plus')),
        m('span.NestedRepliesShowMore-label', app.translator.trans('mtareq-nested-replies.forum.show_more_replies')),
        m('span.NestedRepliesShowMore-icon', icon('fas fa-angles-down')),
      ]
    );
  }
}

import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import icon from 'flarum/common/helpers/icon';
import extractText from 'flarum/common/utils/extractText';
import humanTime from 'flarum/common/helpers/humanTime';

// "Show more replies" control for a folded sibling group. `indent` is the
// difference between the depth the control belongs to and the depth of the post
// it is anchored to, so the control lines up with the hidden replies.
// `summary` (optional, threadSummary.summarizeThread) adds the footer: distinct
// authors (already capped at MAX_SUMMARY_AUTHORS upstream) + last activity.
export default class MoreReplies extends Component {
  view() {
    const { count, indent, summary } = this.attrs;
    // summarizeThread returns `{ authors, latestAt, count }` — `authors` are
    // resolved User models, capped at 5 (plan-draft defect #23 read a
    // nonexistent `members` field, which would throw on every folded group).
    const authors = (summary && summary.authors) || [];

    return m(
      'button.NestedRepliesShowMore.Button.Button--link',
      {
        type: 'button',
        'aria-expanded': 'false',
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
        summary &&
          m('span.NestedRepliesSummary', [
            m(
              'span.NestedRepliesSummary-count',
              app.translator.trans('mtareq-nested-replies.forum.summary_replies', { count: summary.count })
            ),
            authors.length > 0 &&
              m(
                'span.NestedRepliesSummary-avatars',
                authors.map((user) => {
                  // Test the avatar's RESULT, not the method: avatarless users
                  // return null and would otherwise paint url(null) (defect #20).
                  const avatar = typeof user.avatarUrl === 'function' ? user.avatarUrl() : null;
                  return m('span.NestedRepliesSummary-avatar', {
                    key: user.id(),
                    title: extractText(user.displayName()),
                    style: avatar ? `background-image:url(${extractText(avatar)})` : '',
                  });
                })
              ),
            summary.latestAt &&
              m(
                'span.NestedRepliesSummary-activity',
                app.translator.trans('mtareq-nested-replies.forum.summary_last_activity', {
                  time: humanTime(summary.latestAt),
                })
              ),
          ]),
      ]
    );
  }
}

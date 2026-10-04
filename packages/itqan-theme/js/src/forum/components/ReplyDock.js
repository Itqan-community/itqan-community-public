import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import avatar from 'flarum/common/helpers/avatar';
import icon from 'flarum/common/helpers/icon';
import ReplyComposer from 'flarum/forum/components/ReplyComposer';

/**
 * A reply shortcut pinned to the bottom of a phone screen, always visible while
 * the composer is closed.
 *
 * Wide screens have no shortcut: the original post's reply composer opens there
 * on its own (see the replies extension). This is the phone's way to reach the
 * same composer without scrolling back to the top.
 *
 * It opens the same composer as core's reply placeholder, with the same guard:
 * `composer.load` is skipped when the composer is already composing a reply to
 * this discussion, so an open draft is never thrown away.
 */
export default class ReplyDock extends Component {
  canReply() {
    const discussion = this.attrs.discussion;

    return Boolean(app.session.user && discussion && (!discussion.canReply || discussion.canReply()));
  }

  open() {
    if (!this.canReply()) return;

    const discussion = this.attrs.discussion;

    if (!app.composer.composingReplyTo(discussion)) {
      app.composer.load(ReplyComposer, { user: app.session.user, discussion });
    }

    app.composer.show();
  }

  view() {
    // Guests and locked discussions keep core's own affordances; this only
    // adds a shortcut for someone who is going to be able to use it.
    if (!this.canReply()) return null;

    // The composer is where this button would send you anyway.
    if (app.composer.isVisible()) return null;

    return (
      <button
        type="button"
        className="ReplyDock ReplyDock--bar"
        aria-label={app.translator.trans('itqan-theme.forum.reply_dock.accessible_label')}
        onclick={() => this.open()}
      >
        {avatar(app.session.user, { className: 'ReplyDock-avatar' })}
        <span className="ReplyDock-text">{app.translator.trans('itqan-theme.forum.reply_dock.placeholder')}</span>
        <span className="ReplyDock-icon">{icon('fas fa-reply')}</span>
      </button>
    );
  }
}

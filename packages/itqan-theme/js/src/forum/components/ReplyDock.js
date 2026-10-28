import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import avatar from '../../common/avatar';
import icon from '../../common/icon';

/**
 * A reply shortcut pinned to the bottom of a phone screen, always visible while
 * the composer is closed.
 *
 * On a wide screen there is no shortcut: the original post's reply composer
 * opens there on its own (see the replies extension). That form now opens on a
 * phone too, so this bar is the way back to it once the form has been closed or
 * scrolled away — and it hides (see the LESS) while the form is open.
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
    import('flarum/forum/utils/DiscussionControls').then((module) => {
      const DiscussionControls = module.default;
      if (DiscussionControls && typeof DiscussionControls.replyAction === 'function') {
        DiscussionControls.replyAction.call(discussion);
      }
    }).catch(() => {
      if (app.composer && typeof app.composer.show === 'function') {
        app.composer.show();
      }
    });

    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        const target = document.querySelector('.NestedRepliesInlineReply') || document.querySelector('.NestedRepliesQuickReply') || document.querySelector('.PostStream-item');
        if (target) {
          if (target.scrollIntoView) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          const input = target.querySelector ? (target.querySelector('textarea') || target) : null;
          if (input && input.focus) input.focus();
        }
      });
    });
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

import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import avatar from 'flarum/common/helpers/avatar';
import icon from 'flarum/common/helpers/icon';
import ReplyComposer from 'flarum/forum/components/ReplyComposer';

// How far down the page the reader has to be before the desktop button is
// worth showing. Roughly "the opening post has left the viewport".
const SCROLLED_THRESHOLD = 260;

/**
 * The two ways to reach the reply composer without scrolling back:
 *
 * - `ReplyDock--bar` — pinned to the bottom of a phone screen, always visible.
 * - `ReplyDock--float` — a pill in the corner on a wide screen, revealed once
 *   the opening post has scrolled away.
 *
 * Both open the same composer as core's reply placeholder, with the same
 * guards: `composer.load` is skipped when the composer is already composing a
 * reply to this discussion, so an open draft is never thrown away.
 */
export default class ReplyDock extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.scrolled = false;

    // Toggled on the DOM node rather than through a redraw: this runs on every
    // scroll event, and the state is purely presentational.
    this.handleScroll = () => {
      const next = window.scrollY > SCROLLED_THRESHOLD;

      if (next === this.scrolled) return;

      this.scrolled = next;

      if (this.floatEl) this.floatEl.classList.toggle('is-visible', next);
    };
  }

  oncreate(vnode) {
    super.oncreate(vnode);

    window.addEventListener('scroll', this.handleScroll, { passive: true });
    this.handleScroll();
  }

  onremove(vnode) {
    super.onremove(vnode);

    window.removeEventListener('scroll', this.handleScroll);
  }

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

    // A fresh vnode per button: Mithril consumes a vnode when it renders it, so
    // the two cannot share one.
    return [this.button('bar', 'ReplyDock--bar'), this.button('float', 'ReplyDock--float')];
  }

  button(key, className) {
    const float = key === 'float';

    return (
      <button
        type="button"
        className={`ReplyDock ${className}`}
        aria-label={app.translator.trans('itqan-theme.forum.reply_dock.accessible_label')}
        onclick={() => this.open()}
        oncreate={float ? (btn) => (this.floatEl = btn.dom) : undefined}
      >
        {avatar(app.session.user, { className: 'ReplyDock-avatar' })}
        <span className="ReplyDock-text">{app.translator.trans('itqan-theme.forum.reply_dock.placeholder')}</span>
        <span className="ReplyDock-icon">{icon('fas fa-reply')}</span>
      </button>
    );
  }
}

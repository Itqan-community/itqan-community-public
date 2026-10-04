import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import icon from 'flarum/common/helpers/icon';
import LogInModal from 'flarum/forum/components/LogInModal';
import DiscussionComposer from 'flarum/forum/components/DiscussionComposer';

/**
 * A thumb-reach "start a discussion" button for phones.
 *
 * Core's own control lives in the index sidebar, which a phone collapses behind
 * the nav dropdown — so the action exists but is not where a reader looks. This
 * puts it on the discussion list, above the mobile tab bar.
 *
 * The action itself is copied from `IndexPage.newDiscussionAction()` so a guest
 * gets the same log-in prompt and a member the same composer.
 */
export default class NewDiscussionFab extends Component {
  view() {
    // The same capability rule core uses for its sidebar control: the forum
    // attribute is the permission, and a guest is allowed as far as the prompt.
    if (app.session.user && !app.forum.attribute('canStartDiscussion')) return null;

    // Nothing to add while an editor is already on screen.
    if (app.composer.isVisible()) return null;

    return (
      <button
        type="button"
        className="NewDiscussionFab"
        aria-label={app.translator.trans('itqan-theme.forum.new_discussion.accessible_label')}
        onclick={() => this.start()}
      >
        {icon('fas fa-pen')}
      </button>
    );
  }

  start() {
    if (app.session.user) {
      app.composer.load(DiscussionComposer, { user: app.session.user });
      app.composer.show();

      return;
    }

    app.modal.show(LogInModal);
  }
}

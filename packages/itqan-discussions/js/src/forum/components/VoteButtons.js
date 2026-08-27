import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LogInModal from 'flarum/forum/components/LogInModal';
import extractText from 'flarum/common/utils/extractText';
import classList from 'flarum/common/utils/classList';

const UP = 1;
const DOWN = -1;

/**
 * The score and the two arrows.
 *
 * Takes any model carrying `votes`, `userVote` and `canVote` — a post in the
 * stream, or a discussion in the list, which votes on its opening post. The
 * button state is read from that model rather than kept locally, so a redraw
 * from the store shows what the server actually holds.
 *
 * @param model  the Post or Discussion whose attributes to read and update
 * @param postId the post the vote is actually cast on
 */
export default class VoteButtons extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    // Guards a second click while the first is still in flight; without it a
    // double tap sends two conflicting values and the loser wins.
    this.saving = false;
  }

  view() {
    const model = this.attrs.model;
    const mine = model.attribute('userVote') || 0;
    const score = model.attribute('votes') || 0;

    return (
      <div
        className={classList('VoteButtons', {
          'VoteButtons--saving': this.saving,
          'VoteButtons--vertical': this.attrs.vertical,
        })}
      >
        {this.button(UP, 'fas fa-arrow-up', mine === UP, 'up')}
        {/* Coloured by what this reader did, not by the sign of the total: a
            green number above grey arrows reads as a state nobody chose. */}
        <span
          className={classList('VoteButtons-score', {
            'VoteButtons-score--positive': mine === UP,
            'VoteButtons-score--negative': mine === DOWN,
          })}
          aria-live="polite"
          // Not `direction: ltr` in the stylesheet: Flarum's right-to-left
          // build rewrites any property ending in `direction` and flips the
          // value, so the guard against `-2` rendering as `2-` was itself
          // inverted in the only sheet this forum serves. An attribute is
          // beyond the reach of that pass.
          dir="ltr"
        >
          {score}
        </span>
        {this.button(DOWN, 'fas fa-arrow-down', mine === DOWN, 'down')}
      </div>
    );
  }

  button(value, icon, active, name) {
    return (
      <Button
        className={classList('Button Button--icon Button--link VoteButtons-button', `VoteButtons-button--${name}`, {
          'VoteButtons-button--active': active,
        })}
        icon={icon}
        aria-pressed={active ? 'true' : 'false'}
        title={extractText(app.translator.trans(`itqan-discussions.forum.vote.${name}`))}
        onclick={() => this.vote(value)}
      />
    );
  }

  vote(value) {
    const model = this.attrs.model;
    const postId = this.attrs.postId;

    if (!app.session.user) {
      app.modal.show(LogInModal);
      return;
    }

    if (this.saving || !postId || !model.attribute('canVote')) return;

    const previous = { votes: model.attribute('votes') || 0, userVote: model.attribute('userVote') || 0 };

    // Clicking the arrow you already chose withdraws it, which is what every
    // site with these arrows does.
    const next = previous.userVote === value ? 0 : value;

    // Applied before the request so the arrow answers the finger immediately.
    // `pushAttributes` writes into the store, so every component showing this
    // post updates, not just this one.
    model.pushAttributes({
      votes: previous.votes - previous.userVote + next,
      userVote: next,
    });

    this.saving = true;

    app
      .request({
        method: 'PATCH',
        url: `${app.forum.attribute('apiUrl')}/posts/${postId}/vote`,
        body: { data: { attributes: { vote: next } } },
      })
      .then((result) => {
        // The server's score is authoritative: between the click and the
        // reply, other people may have voted too.
        app.store.pushPayload(result);

        // Pushing the post updates the post model. A discussion holding the
        // same score is a separate record and has to be told.
        const authoritative = result?.data?.attributes?.votes;

        if (authoritative !== undefined && model.data?.type !== 'posts') {
          model.pushAttributes({ votes: authoritative, userVote: next });
        }
      })
      .catch((error) => {
        model.pushAttributes(previous);
        throw error;
      })
      .then(() => {
        this.saving = false;
        m.redraw();
      });
  }
}

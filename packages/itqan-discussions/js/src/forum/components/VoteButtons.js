import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LogInModal from 'flarum/forum/components/LogInModal';
import extractText from 'flarum/common/utils/extractText';
import classList from 'flarum/common/utils/classList';

const UP = 1;
const DOWN = -1;

/**
 * The score and the two arrows, shown under every post.
 *
 * The button state is driven by the post's own attributes rather than by local
 * state, so a post redrawn from the store — after a reply, a page change, or
 * another tab — shows what the server actually holds.
 */
export default class VoteButtons extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    // Guards a second click while the first is still in flight; without it a
    // double tap sends two conflicting values and the loser wins.
    this.saving = false;
  }

  view() {
    const post = this.attrs.post;
    const mine = post.attribute('userVote') || 0;
    const score = post.attribute('votes') || 0;

    return (
      <div className={classList('VoteButtons', { 'VoteButtons--saving': this.saving })}>
        {this.button(UP, 'fas fa-arrow-up', mine === UP, 'up')}
        {/* Coloured by what this reader did, not by the sign of the total: a
            green number above grey arrows reads as a state nobody chose. */}
        <span
          className={classList('VoteButtons-score', {
            'VoteButtons-score--positive': mine === UP,
            'VoteButtons-score--negative': mine === DOWN,
          })}
          aria-live="polite"
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
    const post = this.attrs.post;

    if (!app.session.user) {
      app.modal.show(LogInModal);
      return;
    }

    if (this.saving || !post.attribute('canVote')) return;

    const previous = { votes: post.attribute('votes') || 0, userVote: post.attribute('userVote') || 0 };

    // Clicking the arrow you already chose withdraws it, which is what every
    // site with these arrows does.
    const next = previous.userVote === value ? 0 : value;

    // Applied before the request so the arrow answers the finger immediately.
    // `pushAttributes` writes into the store, so every component showing this
    // post updates, not just this one.
    post.pushAttributes({
      votes: previous.votes - previous.userVote + next,
      userVote: next,
    });

    this.saving = true;

    app
      .request({
        method: 'PATCH',
        url: `${app.forum.attribute('apiUrl')}/posts/${post.id()}/vote`,
        body: { data: { attributes: { vote: next } } },
      })
      .then((result) => {
        // The server's score is authoritative: between the click and the
        // reply, other people may have voted too.
        app.store.pushPayload(result);
      })
      .catch((error) => {
        post.pushAttributes(previous);
        throw error;
      })
      .then(() => {
        this.saving = false;
        m.redraw();
      });
  }
}

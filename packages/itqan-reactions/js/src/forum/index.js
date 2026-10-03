import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import LogInModal from 'flarum/forum/components/LogInModal';
import classList from 'flarum/common/utils/classList';
import Component from 'flarum/common/Component';
import extractText from 'flarum/common/utils/extractText';

class ReactionBar extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.saving = false;
    this.pickerOpen = false;
    this.onDocClick = this.onDocClick.bind(this);
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    document.addEventListener('click', this.onDocClick, true);
  }

  onremove(vnode) {
    document.removeEventListener('click', this.onDocClick, true);
    super.onremove(vnode);
  }

  onDocClick(e) {
    if (!this.pickerOpen) return;
    if (this.element && this.element.contains(e.target)) return;
    this.pickerOpen = false;
    m.redraw();
  }

  view() {
    const post = this.attrs.post;
    const summary = post.attribute('reactionSummary') || [];
    const canReact = !!post.attribute('canReact');
    const types = app.forum.attribute('itqanReactionTypes') || [];
    const reactLabel = extractText(app.translator.trans('itqan-reactions.forum.react')) || 'React';

    return (
      <div className="ItqanReactionBar">
        <div className="ItqanReactionBar-chips">
          {summary.map((item) => (
            <button
              key={item.identifier}
              type="button"
              className={classList('ItqanReactionChip', {
                'ItqanReactionChip--me': item.me,
                'ItqanReactionChip--saving': this.saving,
              })}
              disabled={this.saving}
              onclick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggle(item.identifier);
              }}
              title={item.identifier}
            >
              <span className="ItqanReactionChip-emoji">{item.emoji}</span>
              <span className="ItqanReactionChip-count">{item.count}</span>
            </button>
          ))}
          <div className="ItqanReactionBar-pickerWrap">
            <button
              type="button"
              className={classList('Button Button--link ItqanReactionBar-add', {
                'ItqanReactionBar-add--open': this.pickerOpen,
              })}
              aria-label={reactLabel}
              aria-expanded={this.pickerOpen ? 'true' : 'false'}
              title={reactLabel}
              onclick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                this.openPicker();
              }}
            >
              <span className="ItqanReactionBar-addGlyph" aria-hidden="true">
                😊
              </span>
              <span className="ItqanReactionBar-addPlus" aria-hidden="true">
                +
              </span>
            </button>
            {this.pickerOpen && (
              <div className="ItqanReactionPicker" role="menu">
                {types.map((type) => (
                  <button
                    key={type.identifier}
                    type="button"
                    className="ItqanReactionPicker-item"
                    role="menuitem"
                    onclick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      this.pickerOpen = false;
                      this.toggle(type.identifier);
                    }}
                    title={type.label || type.identifier}
                  >
                    {type.emoji}
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    );
  }

  openPicker() {
    if (!app.session.user) {
      app.modal.show(LogInModal);
      return;
    }

    const post = this.attrs.post;
    if (!post.attribute('canReact')) {
      return;
    }

    this.pickerOpen = !this.pickerOpen;
    m.redraw();
  }

  toggle(identifier) {
    const post = this.attrs.post;
    if (!app.session.user) {
      app.modal.show(LogInModal);
      return;
    }
    if (!post.attribute('canReact')) return;
    if (this.saving) return;

    this.saving = true;
    m.redraw();

    const prev = JSON.parse(JSON.stringify(post.attribute('reactionSummary') || []));
    const types = app.forum.attribute('itqanReactionTypes') || [];
    const type = types.find((t) => t.identifier === identifier);
    let next = prev.slice();
    const idx = next.findIndex((s) => s.identifier === identifier);
    const mineIdx = next.findIndex((s) => s.me && s.identifier !== identifier);

    if (mineIdx >= 0 && (idx < 0 || next[idx].identifier !== identifier || !next[idx].me)) {
      next[mineIdx].me = false;
      next[mineIdx].count = Math.max(0, (next[mineIdx].count || 1) - 1);
      if (next[mineIdx].count === 0) next.splice(mineIdx, 1);
    }

    const refreshedIdx = next.findIndex((s) => s.identifier === identifier);
    if (refreshedIdx >= 0 && next[refreshedIdx].me) {
      next[refreshedIdx].me = false;
      next[refreshedIdx].count = Math.max(0, next[refreshedIdx].count - 1);
      if (next[refreshedIdx].count === 0) next.splice(refreshedIdx, 1);
    } else if (refreshedIdx >= 0) {
      next[refreshedIdx].me = true;
      next[refreshedIdx].count = (next[refreshedIdx].count || 0) + 1;
    } else if (type) {
      next.push({ identifier, emoji: type.emoji, count: 1, me: true });
    }

    post.pushAttributes({ reactionSummary: next });
    m.redraw();

    app
      .request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/posts/${post.id()}/reactions`,
        body: { data: { attributes: { reaction: identifier } } },
      })
      .then((payload) => {
        app.store.pushPayload(payload);
      })
      .catch(() => {
        post.pushAttributes({ reactionSummary: prev });
      })
      .then(() => {
        this.saving = false;
        m.redraw();
      });
  }
}

app.initializers.add('itqan-reactions', () => {
  extend(CommentPost.prototype, 'actionItems', function (items) {
    const post = this.attrs ? this.attrs.post : null;
    if (!post || post.isHidden()) return;

    items.add('itqanReactions', m(ReactionBar, { post }), 45);
  });
});

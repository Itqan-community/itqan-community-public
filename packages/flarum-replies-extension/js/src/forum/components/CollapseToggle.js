import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import icon from 'flarum/common/helpers/icon';

export default class CollapseToggle extends Component {
  view() {
    const collapsed = Boolean(this.attrs.collapsed);
    const key = collapsed ? 'expand' : 'collapse';

    return m(
      'button.NestedRepliesCollapseToggle.Button.Button--icon',
      {
        type: 'button',
        'aria-expanded': collapsed ? 'false' : 'true',
        'aria-label': app.translator.trans(`mtareq-nested-replies.forum.${key}`),
        title: app.translator.trans(`mtareq-nested-replies.forum.${key}`),
        onclick: this.attrs.onclick,
      },
      icon(collapsed ? 'fas fa-plus' : 'fas fa-minus')
    );
  }
}

import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Tooltip from 'flarum/common/components/Tooltip';
import extractText from 'flarum/common/utils/extractText';
import classList from 'flarum/common/utils/classList';

export default class DndToggle extends Component {
  view() {
    const user = app.session.user;
    if (!user) return null;

    const active = !!user.preferences().dndEnabled;

    return (
      <Tooltip text={extractText(app.translator.trans(`itqan-notifications.forum.dnd.${active ? 'disable' : 'enable'}`))}>
        <Button
          className={classList('Button', 'Button--link', { active })}
          icon={active ? 'fas fa-bell-slash' : 'far fa-bell'}
          onclick={() => this.toggle()}
        />
      </Tooltip>
    );
  }

  toggle() {
    const user = app.session.user;
    const active = !!user.preferences().dndEnabled;
    user.savePreferences({ dndEnabled: !active }).then(() => m.redraw());
  }
}
import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Switch from 'flarum/common/components/Switch';

export default class DndToggle extends Component {
  view() {
    const active = !!app.session.user.preferences().dndEnabled;

    return (
      <div className="DndToggle">
        <Switch state={active} onchange={(value) => this.save(value)}>
          {app.translator.trans('itqan-notifications.forum.dnd_toggle_label')}
        </Switch>
      </div>
    );
  }

  save(value) {
    app.session.user.savePreferences({ dndEnabled: value }).then(() => m.redraw());
  }
}
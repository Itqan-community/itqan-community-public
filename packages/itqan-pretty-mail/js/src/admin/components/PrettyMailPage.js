import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Switch from 'flarum/common/components/Switch';

const PREFIX = 'fof-pretty-mail.';

const TEMPLATES = [
  ['mailhtml', 'mailhtml'],
  ['newPost', 'newPost'],
  ['postMentioned', 'postMentioned'],
  ['userMentioned', 'userMentioned'],
];

/**
 * Same editors as the 1.x `fof/pretty-mail` extension page, rendered as a
 * custom page instead of `registerSetting` callbacks so the layout (and the
 * save button) is explicit.
 */
export default class PrettyMailPage extends ExtensionPage {
  content() {
    return (
      <div className="PrettyMailPage">
        <div className="Form-group">
          <h3>{app.translator.trans('itqan-pretty-mail.admin.settings.heading')}</h3>
          <div className="helpText">{app.translator.trans('itqan-pretty-mail.admin.settings.help')}</div>
        </div>

        {TEMPLATES.map(([setting, key]) => (
          <div className="Form-group" key={setting}>
            <label>{app.translator.trans(`itqan-pretty-mail.admin.settings.${key}_label`)}</label>
            <textarea className="FormControl" rows={16} spellcheck={false} bidi={this.setting(PREFIX + setting)} />
            <div className="helpText">{app.translator.trans(`itqan-pretty-mail.admin.settings.${key}_help`)}</div>
          </div>
        ))}

        <div className="Form-group">
          <Switch state={this.setting(PREFIX + 'includeCSS')() === '1'} onchange={(value) => this.setting(PREFIX + 'includeCSS')(value ? '1' : '0')}>
            {app.translator.trans('itqan-pretty-mail.admin.settings.includeCSS_label')}
          </Switch>
          <div className="helpText">{app.translator.trans('itqan-pretty-mail.admin.settings.includeCSS_help')}</div>
        </div>

        <div className="Form-group">{this.submitButton()}</div>
      </div>
    );
  }
}

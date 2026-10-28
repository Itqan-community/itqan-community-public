import app from 'flarum/admin/app';

const PREFIX = 'fof-pretty-mail.';

function templateEditor(setting, key) {
  return function () {
    return (
      <div className="Form-group">
        <label>{app.translator.trans(`itqan-pretty-mail.admin.settings.${key}_label`)}</label>
        <textarea className="FormControl" rows={18} spellcheck={false} bidi={this.setting(PREFIX + setting)} />
        <div className="helpText">{app.translator.trans(`itqan-pretty-mail.admin.settings.${key}_help`)}</div>
      </div>
    );
  };
}

app.initializers.add('itqan-pretty-mail', () => {
  app.registry
    .for('itqan-pretty-mail')
    .registerSetting(templateEditor('mailhtml', 'mailhtml'), 30)
    .registerSetting(templateEditor('newPost', 'newPost'), 20)
    .registerSetting(templateEditor('postMentioned', 'postMentioned'), 10)
    .registerSetting(templateEditor('userMentioned', 'userMentioned'), 0)
    .registerSetting(
      {
        setting: PREFIX + 'includeCSS',
        type: 'bool',
        label: app.translator.trans('itqan-pretty-mail.admin.settings.includeCSS_label'),
        help: app.translator.trans('itqan-pretty-mail.admin.settings.includeCSS_help'),
      },
      -10
    );
});

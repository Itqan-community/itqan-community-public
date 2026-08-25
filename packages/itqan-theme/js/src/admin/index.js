import app from 'flarum/admin/app';

app.initializers.add('itqan-theme', () => {
  app.extensionData.for('itqan-theme').registerSetting({
    setting: 'itqan-theme.default_mode',
    type: 'select',
    options: {
      auto: app.translator.trans('itqan-theme.admin.settings.default_mode_auto'),
      light: app.translator.trans('itqan-theme.admin.settings.default_mode_light'),
      dark: app.translator.trans('itqan-theme.admin.settings.default_mode_dark'),
    },
    default: 'auto',
    label: app.translator.trans('itqan-theme.admin.settings.default_mode_label'),
    help: app.translator.trans('itqan-theme.admin.settings.default_mode_help'),
  });
});

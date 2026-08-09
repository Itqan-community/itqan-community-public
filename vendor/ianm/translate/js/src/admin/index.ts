import app from 'flarum/admin/app';
import TranslateSettingsPage from './components/TranslateSettingsPage';

app.initializers.add('ianm-translate', () => {
  app.extensionData
    .for('ianm-translate')
    .registerPage(TranslateSettingsPage)
    .registerPermission(
      {
        icon: 'fas fa-language',
        permission: 'translateLocale',
        label: app.translator.trans('ianm-translate.admin.permission.translate-to-locale'),
        allowGuest: true,
      },
      'view',
      63
    )
    .registerPermission(
      {
        icon: 'fas fa-sync',
        permission: 'refreshTranslation',
        label: app.translator.trans('ianm-translate.admin.permission.refresh-translation'),
      },
      'moderate'
    );

  if (app.data['settings']['ianm-translate.translate-all-enabled']) {
    app.extensionData.for('ianm-translate').registerPermission(
      {
        icon: 'fas fa-language',
        permission: 'translateAll',
        label: app.translator.trans('ianm-translate.admin.permission.translate-to-all-locales'),
      },
      'view',
      62
    );
  }
});

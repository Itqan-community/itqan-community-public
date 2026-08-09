import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import ItemList from 'flarum/common/utils/ItemList';
import Mithril from 'mithril';
import Stream from 'flarum/common/utils/Stream';

interface Provider {
  name: string;
  link: string;
  options: Record<string, any>;
}

export default class TranslateSettingsPage extends ExtensionPage {
  selectedProvider: Stream<string> = Stream(app.data.settings['ianm-translate.provider']);

  content() {
    return (
      <div className="container">
        <div className="TranslateSettingsPage">
          <div className="Form">
            <div className="Form-group">
              {this.buildSettingComponent({
                setting: 'ianm-translate.use-native-locale-names',
                type: 'boolean',
                label: app.translator.trans('ianm-translate.admin.settings.use-native-locale-names'),
                help: app.translator.trans('ianm-translate.admin.settings.use-native-locale-names-help'),
              })}
              {this.buildSettingComponent({
                setting: 'ianm-translate.translate-all-enabled',
                type: 'boolean',
                label: app.translator.trans('ianm-translate.admin.settings.translate-all-enabled'),
                help: app.translator.trans('ianm-translate.admin.settings.translate-all-help'),
              })}
              {this.buildSettingComponent({
                setting: 'ianm-translate.bind-browser-language',
                type: 'boolean',
                label: app.translator.trans('ianm-translate.admin.settings.bind-browser-language'),
                help: app.translator.trans('ianm-translate.admin.settings.bind-browser-language-help'),
              })}
            </div>
            <div className="Form-group">
              {this.buildSettingComponent({
                setting: 'ianm-translate.provider',
                type: 'select',
                label: app.translator.trans('ianm-translate.admin.settings.provider'),
                options: this.providers(),
                onchange: (value: string) => {
                  this.settings['ianm-translate.provider'] = Stream(value);
                  this.selectedProvider(value);
                  m.redraw();
                },
                value: this.selectedProvider(),
              })}
              {this.providerSettingsItems().toArray()}
            </div>
            {this.submitButton()}
          </div>
        </div>
      </div>
    );
  }

  providers(): Record<string, string> {
    const providersData = app.data['ianm-translate'];
    const providers: Provider[] = Array.isArray(providersData) ? providersData : Object.values(providersData);

    return providers.reduce((o, p) => {
      o[p.name] = app.translator.trans(`ianm-translate.lib.providers.${p.name}`);
      return o;
    }, {});
  }

  providerSettingsItems(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();
    const providers: Provider[] = Object.values(app.data['ianm-translate']);

    providers.forEach((provider) => {
      if (this.selectedProvider() !== provider.name) return;

      if (provider.options.length === 0) {
        items.add('readme-info', <div className="helpText">{app.translator.trans('ianm-translate.admin.settings.readme-info')}</div>);
        items.add(`${provider.name}-settings`, <div className="helpText">{app.translator.trans('ianm-translate.admin.settings.readme-none')}</div>);

        return items;
      }

      items.add('readme-info', <div className="helpText">{app.translator.trans('ianm-translate.admin.settings.readme-info')}</div>);
      items.add(
        `${provider.name}-settings`,
        <div className={`${provider.name}-settings`}>
          <h3>{app.translator.trans(`ianm-translate.lib.providers.${provider.name}`)}</h3>
          <div>
            {Object.entries(provider.options).map(([key, values]) => {
              const [type, required, options] = Object.values(values);
              const selectOptions = options ? this.createOptions(options, provider.name, key) : {};

              return this.buildSettingComponent({
                setting: `ianm-translate.${provider.name}.${key}`,
                type: type as string,
                label: app.translator.trans(`ianm-translate.admin.settings.${provider.name}.${key}.label`),
                help: app.translator.trans(`ianm-translate.admin.settings.${provider.name}.${key}.help`, {
                  link: (
                    <a href={provider.link} target="_blank">
                      {app.translator.trans(`ianm-translate.lib.providers.${provider.name}`)}
                    </a>
                  ),
                }),
                required,
                options: selectOptions,
              });
            })}
          </div>
        </div>
      );
    });

    return items;
  }

  private createOptions(options: Record<string, any>, providerName: string, key: string): Record<string, string> {
    return Object.values(options).reduce((o, p) => {
      o[p] = app.translator.trans(`ianm-translate.admin.settings.${providerName}.${key}.options.${p}`);
      return o;
    }, {});
  }
}

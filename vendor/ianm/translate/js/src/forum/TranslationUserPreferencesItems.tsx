import app from 'flarum/forum/app';
import User from 'flarum/common/models/User';
import ItemList from 'flarum/common/utils/ItemList';
import Switch from 'flarum/common/components/Switch';
import type Mithril from 'mithril';

export default function TranslationUserPreferencesItems(user?: User): ItemList<Mithril.Children> {
  const items = new ItemList<Mithril.Children>();

  let labelAllSourceLoading = false;

  const switchComponent = (
    <Switch
      state={user?.preferences()?.['ianm-translate.labelAllSource']}
      onchange={(value: boolean) => {
        labelAllSourceLoading = true;

        user?.savePreferences({ 'ianm-translate.labelAllSource': value }).then(() => {
          labelAllSourceLoading = false;
          m.redraw();
        });
      }}
      loading={labelAllSourceLoading}
    >
      {app.translator.trans('ianm-translate.forum.user.settings.source-language.label')}
    </Switch>
  );

  const helpTextComponent = <p className="helpText">{app.translator.trans('ianm-translate.forum.user.settings.source-language.help')}</p>;

  items.add('sourceLanguage', [switchComponent, helpTextComponent], 80);

  return items;
}

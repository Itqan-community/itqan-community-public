import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import ItemList from 'flarum/common/utils/ItemList';
import SettingsPage from 'flarum/forum/components/SettingsPage';
import FieldSet from 'flarum/common/components/FieldSet';
import type Mithril from 'mithril';
import TranslationUserPreferencesItems from '../TranslationUserPreferencesItems';

export default function extendUserSettings() {
  extend(SettingsPage.prototype, 'settingsItems', function (items: ItemList<Mithril.Children>) {
    const user = this.user;

    if (!user) {
      return;
    }

    items.add(
      'translationItems',
      <FieldSet className="Settings-translation" label={app.translator.trans('ianm-translate.forum.user.settings.heading')}>
        {TranslationUserPreferencesItems(user).toArray()}
      </FieldSet>,
      40
    );
  });
}

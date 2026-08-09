import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import ItemList from 'flarum/common/utils/ItemList';
import Mithril from 'mithril';
import Stream from 'flarum/common/utils/Stream';
export default class TranslateSettingsPage extends ExtensionPage {
    selectedProvider: Stream<string>;
    content(): JSX.Element;
    providers(): Record<string, string>;
    providerSettingsItems(): ItemList<Mithril.Children>;
    private createOptions;
}

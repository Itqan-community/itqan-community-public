import Mithril, { Vnode } from 'mithril';
import Component from 'flarum/common/Component';
interface TranslatedPostFooterAttrs {
    updatedAt: Date;
    provider: string;
}
export default class TranslatedPostFooter extends Component<TranslatedPostFooterAttrs> {
    view(vnode: Vnode<TranslatedPostFooterAttrs>): Mithril.Children;
}
export {};

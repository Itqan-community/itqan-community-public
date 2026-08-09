import Mithril, { Vnode } from 'mithril';
import Component from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import TranslationState from '../states/TranslationState';
import Discussion from 'flarum/common/models/Discussion';
export interface IDiscussionTitleAttrs {
    discussion: Discussion;
    languages: string[];
    iconOnly: boolean;
}
export default class DiscussionTitle extends Component<IDiscussionTitleAttrs> {
    translationState: TranslationState;
    oninit(vnode: Vnode<IDiscussionTitleAttrs>): void;
    view(): JSX.Element;
    titleItems(): ItemList<Mithril.Children>;
    handleTranslate: (code: string) => void;
}

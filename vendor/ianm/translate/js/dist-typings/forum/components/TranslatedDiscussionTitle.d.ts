import Component, { ComponentAttrs } from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import TranslationState from '../states/TranslationState';
import Discussion from 'flarum/common/models/Discussion';
import DiscussionTranslation from '../../common/model/DiscussionTranslation';
interface ITranslatedDiscussionTitleAttrs extends ComponentAttrs {
    state: TranslationState;
}
export default class TranslatedDiscussionTitle extends Component<ITranslatedDiscussionTitleAttrs> {
    view(): JSX.Element;
    headerItems(discussion: Discussion, translation: DiscussionTranslation): ItemList<Mithril.Children>;
    actionItems(item: Discussion, translation: DiscussionTranslation): ItemList<Mithril.Children>;
    footerItems(discussion: Discussion, translation: DiscussionTranslation): ItemList<Mithril.Children>;
}
export {};

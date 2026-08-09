import Component, { ComponentAttrs } from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import Post from 'flarum/common/models/Post';
import Translation from '../../common/model/PostTranslation';
import TranslationState from '../states/TranslationState';
interface ITranslatedCommentPostAttrs extends ComponentAttrs {
    state: TranslationState;
}
export default class TranslatedCommentPost extends Component<ITranslatedCommentPostAttrs> {
    view(): JSX.Element;
    headerItems(item: Post, translation: Translation): ItemList<Mithril.Children>;
    actionItems(item: Post, translation: Translation): ItemList<Mithril.Children>;
    footerItems(item: Post, translation: Translation): ItemList<Mithril.Children>;
}
export {};

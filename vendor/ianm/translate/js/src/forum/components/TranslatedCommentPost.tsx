import app from 'flarum/forum/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import LangDisplayName from '../util/LangDisplayName';
import ItemList from 'flarum/common/utils/ItemList';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import Post from 'flarum/common/models/Post';
import Translation from '../../common/model/PostTranslation';
import TranslationState from '../states/TranslationState';
import TranslatedPostFooter from './TranslatedPostFooter';
import TranslationSourceLabel from './TranslationSourceLabel';
import { generateActionItems } from '../util/generateActionItems';

interface ITranslatedCommentPostAttrs extends ComponentAttrs {
  state: TranslationState;
}

export default class TranslatedCommentPost extends Component<ITranslatedCommentPostAttrs> {
  view() {
    const { state } = this.attrs;
    const { currentTranslation, loading, item } = state;

    if (loading || !currentTranslation) {
      return (
        <div className="CommentPost CommentPost--translated">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="CommentPost CommentPost--translated" data-id={currentTranslation.id()}>
        <div className="Post-header">{this.headerItems(item, currentTranslation).toArray()}</div>
        <div className="Post-body">{m.trust(currentTranslation.translationHtml())}</div>
        <div className="Post-footer">{this.footerItems(item, currentTranslation).toArray()}</div>
      </div>
    );
  }

  headerItems(item: Post, translation: Translation): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();
    const from = item.detectedLang() ? LangDisplayName(item.detectedLang()) : app.translator.trans('ianm-translate.forum.unknown-language');
    const to = LangDisplayName(translation.language());

    items.add('translation-ai', <TranslationSourceLabel from={from} to={to} />, 50);

    items.add('actions', <div className="PostMeta PostTranslation--action-items">{this.actionItems(item, translation).toArray()}</div>, -100);

    return items;
  }

  actionItems(item: Post, translation: Translation): ItemList<Mithril.Children> {
    return generateActionItems(item, translation, this.attrs.state);
  }

  footerItems(item: Post, translation: Translation): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add('translation-date', <TranslatedPostFooter updatedAt={translation.updatedAt()} provider={translation.provider()} />, 90);

    return items;
  }
}

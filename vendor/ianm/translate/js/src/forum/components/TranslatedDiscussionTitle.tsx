import app from 'flarum/forum/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import LangDisplayName from '../util/LangDisplayName';
import ItemList from 'flarum/common/utils/ItemList';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import TranslationState from '../states/TranslationState';
import Discussion from 'flarum/common/models/Discussion';
import DiscussionTranslation from '../../common/model/DiscussionTranslation';
import TranslatedPostFooter from './TranslatedPostFooter';
import TranslationSourceLabel from './TranslationSourceLabel';
import { generateActionItems } from '../util/generateActionItems';

interface ITranslatedDiscussionTitleAttrs extends ComponentAttrs {
  state: TranslationState;
}

export default class TranslatedDiscussionTitle extends Component<ITranslatedDiscussionTitleAttrs> {
  view() {
    const { state } = this.attrs;
    const { currentTranslation, loading, item } = state;

    if (loading || !currentTranslation) {
      return (
        <div className="DiscussionTitle DiscussionTitle--translated">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="DiscussionHero-title DiscussionTitle--translated">
        <div className="Title-header">{this.headerItems(item as Discussion, currentTranslation as DiscussionTranslation).toArray()}</div>
        <h1 className="DiscussionHero-title">{currentTranslation.translation()}</h1>
        <div className="Title-footer">{this.footerItems(item as Discussion, currentTranslation as DiscussionTranslation).toArray()}</div>
      </div>
    );
  }

  headerItems(discussion: Discussion, translation: DiscussionTranslation): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();
    const from = discussion.detectedLang()
      ? LangDisplayName(discussion.detectedLang())
      : app.translator.trans('ianm-translate.forum.unknown-language');
    const to = LangDisplayName(translation.language());

    items.add('translation-ai', <TranslationSourceLabel from={from} to={to} />, 50);

    items.add('actions', <div className="TitleMeta TitleTranslation--action-items">{this.actionItems(discussion, translation).toArray()}</div>, -100);

    return items;
  }

  actionItems(item: Discussion, translation: DiscussionTranslation): ItemList<Mithril.Children> {
    return generateActionItems(item, translation, this.attrs.state);
  }

  footerItems(discussion: Discussion, translation: DiscussionTranslation): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add('translation-date', <TranslatedPostFooter updatedAt={translation.updatedAt()} provider={translation.provider()} />, 90);

    return items;
  }
}

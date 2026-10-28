import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import ItemList from 'flarum/common/utils/ItemList';
import LangDisplayName from '../util/LangDisplayName';
import TranslatedPostFooter from './TranslatedPostFooter';
import TranslationSourceLabel from './TranslationSourceLabel';
import { generateActionItems } from '../util/generateActionItems';

/**
 * Translated discussion title, rendered in place of the hero title.
 */
export default class TranslatedDiscussionTitle extends Component {
  view() {
    const { state } = this.attrs;

    if (state.loading || !state.currentTranslation) {
      return (
        <div className="DiscussionTitle DiscussionTitle--translated">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="DiscussionHero-title DiscussionTitle--translated">
        <div className="Title-header">{this.headerItems(state).toArray()}</div>
        <h1 className="DiscussionHero-title">{state.currentTranslation}</h1>
        <div className="Title-footer">{this.footerItems(state).toArray()}</div>
      </div>
    );
  }

  headerItems(state) {
    const items = new ItemList();
    const discussion = state.item;
    const detected = discussion?.detectedLang?.();
    const from = detected && detected !== 'unknown' ? LangDisplayName(detected) : app.translator.trans('itqan-translate.forum.unknown-language');
    const to = LangDisplayName(state.language);

    items.add('translation-ai', <TranslationSourceLabel from={from} to={to} />, 50);

    items.add('actions', <div className="TitleMeta TitleTranslation--action-items">{generateActionItems(state).toArray()}</div>, -100);

    return items;
  }

  footerItems(state) {
    const items = new ItemList();

    items.add('translation-date', <TranslatedPostFooter updatedAt={state.updatedAt} provider={state.provider} />, 90);

    return items;
  }
}

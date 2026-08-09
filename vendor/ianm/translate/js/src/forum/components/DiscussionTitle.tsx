import app from 'flarum/forum/app';
import Mithril, { Vnode } from 'mithril';
import Component from 'flarum/common/Component';
import DetectedLanguageLabel from './DetectedLanguageLabel';
import TranslateButton from './TranslateButton';
import TranslatedDiscussionTitle from './TranslatedDiscussionTitle'; // Import the new component
import ItemList from 'flarum/common/utils/ItemList';
import TranslationState from '../states/TranslationState';
import Discussion from 'flarum/common/models/Discussion';

export interface IDiscussionTitleAttrs {
  discussion: Discussion;
  languages: string[];
  iconOnly: boolean;
}

export default class DiscussionTitle extends Component<IDiscussionTitleAttrs> {
  translationState!: TranslationState;

  oninit(vnode: Vnode<IDiscussionTitleAttrs>) {
    super.oninit(vnode);
    this.translationState = new TranslationState(vnode.attrs.discussion);
  }

  view() {
    return <div className="DiscussionTitle-container">{this.titleItems().toArray()}</div>;
  }

  titleItems(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();
    const { languages, iconOnly } = this.attrs;
    const discussion = this.attrs.discussion;
    let currentDetectedLang = discussion.detectedLang();
    const user = app.session.user;

    if (currentDetectedLang === app.translator.getLocale() && !user?.preferences()['ianm-translate.labelAllSource']) {
      currentDetectedLang = '';
    }

    // If translated title is available, show it
    if (this.translationState.showing) {
      items.add('title-translated', <TranslatedDiscussionTitle state={this.translationState} />, 45);
    } else {
      // Otherwise, show the original title
      items.add('title-original', <h1 className="DiscussionHero-title">{discussion.title()}</h1>, 50);

      // Language label
      currentDetectedLang && items.add('title-original-label', <DetectedLanguageLabel detectedLang={currentDetectedLang} context="discussion" />, 40);
    }

    // Translation action button
    items.add(
      'title-translate-action',
      <div className="DiscussionTranslationAction">
        <TranslateButton languages={languages} detectedLang={currentDetectedLang} onTranslate={this.handleTranslate} iconOnly={iconOnly} />
      </div>,
      30
    );

    return items;
  }

  handleTranslate = (code: string) => {
    this.translationState.loadTranslation(code);
  };
}

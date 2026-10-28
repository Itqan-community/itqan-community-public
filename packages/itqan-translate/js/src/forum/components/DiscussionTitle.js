import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import DetectedLanguageLabel from './DetectedLanguageLabel';
import TranslateButton from './TranslateButton';
import TranslatedDiscussionTitle from './TranslatedDiscussionTitle';
import TranslationState from '../states/TranslationState';

/**
 * Discussion hero title plus its translate control.
 */
export default class DiscussionTitle extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.translationState = new TranslationState(vnode.attrs.discussion, 'discussion');

    this.subtree?.check(() => this.translationState.loading, () => this.translationState.showing, () => this.translationState.currentTranslation);
  }

  view() {
    return <div className="DiscussionTitle-container">{this.titleItems().toArray()}</div>;
  }

  titleItems() {
    const items = new ItemList();
    const { languages, iconOnly } = this.attrs;
    const discussion = this.attrs.discussion;
    let currentDetectedLang = discussion.detectedLang?.() || '';
    const user = app.session.user;

    if (currentDetectedLang === app.translator.getLocale() && !user?.preferences()?.['itqan-translate.labelAllSource']) {
      currentDetectedLang = '';
    }

    if (this.translationState.showing) {
      items.add('title-translated', <TranslatedDiscussionTitle state={this.translationState} />, 45);
    } else {
      items.add('title-original', <h1 className="DiscussionHero-title">{discussion.title()}</h1>, 50);

      if (currentDetectedLang) {
        items.add('title-original-label', <DetectedLanguageLabel detectedLang={currentDetectedLang} context="discussion" />, 40);
      }
    }

    items.add(
      'title-translate-action',
      <div className="DiscussionTranslationAction">
        <TranslateButton languages={languages} detectedLang={currentDetectedLang} onTranslate={(code) => this.translationState.loadTranslation(code)} iconOnly={iconOnly} />
      </div>,
      30
    );

    return items;
  }
}

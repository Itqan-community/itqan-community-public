import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import ItemList from 'flarum/common/utils/ItemList';
import CommentPost from 'flarum/forum/components/CommentPost';
import DiscussionHero from 'flarum/forum/components/DiscussionHero';
import TranslationState from './states/TranslationState';
import TranslateButton from './components/TranslateButton';
import DetectedLanguageLabel from './components/DetectedLanguageLabel';
import TranslatedCommentPost from './components/TranslatedCommentPost';
import DiscussionTitle from './components/DiscussionTitle';

function languages() {
  const supported = app.forum.attribute('itqanTranslate.supportedLanguages') || { ar: 'ar', en: 'en' };

  return Object.values(supported);
}

app.initializers.add('itqan-translate', () => {
  // --- posts -------------------------------------------------------------
  extend(CommentPost.prototype, 'oninit', function () {
    this.tqaTranslationState = new TranslationState(this.attrs.post, 'post');

    this.subtree?.check(
      () => this.tqaTranslationState.loading,
      () => this.tqaTranslationState.showing,
      () => this.tqaTranslationState.currentTranslation
    );
  });

  extend(CommentPost.prototype, 'headerItems', function (items) {
    const detectedLang = this.attrs.post.detectedLang?.();
    const user = app.session.user;

    if (!detectedLang || this.attrs.post.isHidden()) {
      return;
    }

    if (detectedLang === app.translator.getLocale() && !user?.preferences()?.['itqan-translate.labelAllSource']) {
      return;
    }

    items.add('detected-language', <DetectedLanguageLabel detectedLang={detectedLang} context="post" />, -5);
  });

  extend(CommentPost.prototype, 'actionItems', function (items) {
    if (this.attrs.post.isHidden()) {
      return;
    }

    items.add(
      'translate',
      <TranslateButton
        languages={languages()}
        detectedLang={this.attrs.post.detectedLang?.()}
        loading={this.tqaTranslationState.loading}
        onTranslate={(code) => this.tqaTranslationState.loadTranslation(code)}
      />,
      10
    );
  });

  extend(CommentPost.prototype, 'footerItems', function (items) {
    if (this.isEditing?.()) {
      this.tqaTranslationState.closeTranslation();

      return;
    }

    if (this.tqaTranslationState.showing || this.tqaTranslationState.loading) {
      items.add('translated-content', <TranslatedCommentPost state={this.tqaTranslationState} post={this.attrs.post} />, -10);
    }
  });

  // --- discussion title --------------------------------------------------
  extend(DiscussionHero.prototype, 'items', function (items) {
    const discussion = this.attrs.discussion;

    items.setContent('title', <DiscussionTitle discussion={discussion} languages={languages()} iconOnly={false} />);

    return items;
  });
});

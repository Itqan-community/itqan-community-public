import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import ItemList from 'flarum/common/utils/ItemList';
import CommentPost from 'flarum/forum/components/CommentPost';
import TranslatedCommentPost from '../components/TranslatedCommentPost';
import PostTranslationState from '../states/TranslationState';
import type Mithril from 'mithril';
import DetectedLanguageLabel from '../components/DetectedLanguageLabel';
import TranslateButton from '../components/TranslateButton';

export default function extendPosts() {
  extend(CommentPost.prototype, 'oninit', onInit);
  extend(CommentPost.prototype, 'headerItems', addHeaderItems);
  extend(CommentPost.prototype, 'actionItems', addActionItems);
  extend(CommentPost.prototype, 'footerItems', addFooterItems);
}

function onInit() {
  this.translationState = new PostTranslationState(this.attrs.post);

  this.subtree.check(
    () => this.translationState.loading,
    () => this.translationState.currentTranslation,
    () => this.translationState.showing
  );
}

function addHeaderItems(items: ItemList<Mithril.Children>) {
  const detectedLang = this.attrs.post.detectedLang?.();
  const user = app.session?.user;

  if (!detectedLang || this.attrs.post.isHidden()) {
    return;
  }

  if (detectedLang === app.translator.getLocale() && !user?.preferences()['ianm-translate.labelAllSource']) {
    return;
  }

  items.add('detected-language', <DetectedLanguageLabel detectedLang={detectedLang} context="post" />, -5);
}

function addActionItems(items: ItemList<Mithril.Children>) {
  if (!this.attrs.post.discussion().canTranslate() || this.attrs.post.isHidden()) {
    return;
  }

  const detectedLang = this.attrs.post.detectedLang?.();
  const availableLanguages = Object.values(app.forum.attribute('ianm-translate.supportedLanguages')).filter((code) => code !== detectedLang);

  items.add(
    'translate',
    <TranslateButton
      languages={availableLanguages}
      detectedLang={detectedLang}
      onTranslate={(code: string) => this.translationState.loadTranslation(code)}
    />,
    10
  );
}

function addFooterItems(items: ItemList<Mithril.Children>) {
  if (!this.attrs.post.discussion().canTranslate() || this.isEditing()) {
    if (this.isEditing()) {
      this.translationState.closeTranslation();
    }
    return;
  }

  if (this.translationState.showing) {
    items.add('translated-content', <TranslatedCommentPost state={this.translationState} />, -10);
  }
}

import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import ItemList from 'flarum/common/utils/ItemList';
import LangDisplayName from '../util/LangDisplayName';
import TranslatedPostFooter from './TranslatedPostFooter';
import TranslationSourceLabel from './TranslationSourceLabel';
import { generateActionItems } from '../util/generateActionItems';

/**
 * The translated copy of a post, rendered below it in the post footer exactly
 * like ianm/translate: `CommentPost CommentPost--translated`.
 */
export default class TranslatedCommentPost extends Component {
  view() {
    const { state } = this.attrs;

    if (state.loading || !state.currentTranslation) {
      return (
        <div className="CommentPost CommentPost--translated">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="CommentPost CommentPost--translated">
        <div className="Post-header">{this.headerItems(state).toArray()}</div>
        <div className="Post-body" dir={app.translator.getLocale() === 'ar' ? 'rtl' : 'ltr'}>
          {this.translatedContent(state.currentTranslation)}
        </div>
        <div className="Post-footer">{this.footerItems(state).toArray()}</div>
      </div>
    );
  }

  translatedContent(text) {
    return m.trust(
      String(text)
        .split(/\n{2,}/)
        .map((paragraph) =>
          paragraph
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>')
        )
        .map((paragraph) => `<p>${paragraph}</p>`)
        .join('')
    );
  }

  headerItems(state) {
    const items = new ItemList();
    const post = this.attrs.post || state.item;
    const detected = post?.detectedLang?.() || state.detectedLang;
    const from = detected && detected !== 'unknown' ? LangDisplayName(detected) : app.translator.trans('itqan-translate.forum.unknown-language');
    const to = LangDisplayName(state.language);

    items.add('translation-ai', <TranslationSourceLabel from={from} to={to} />, 50);

    items.add('actions', <div className="PostMeta PostTranslation--action-items">{generateActionItems(state).toArray()}</div>, -100);

    return items;
  }

  footerItems(state) {
    const items = new ItemList();

    items.add('translation-date', <TranslatedPostFooter updatedAt={state.updatedAt} provider={state.provider} />, 90);

    return items;
  }
}

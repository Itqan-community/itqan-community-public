import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';

// Per-post, per-locale request state. The translation itself lives on the Post
// model so it survives the stream re-creating component instances.
const pending = new Set();
const errors = new Set();

const localeOf = () => String(app.data.locale || 'en').split('-')[0].toLowerCase();
const keyFor = (post, target) => `${post.id()}:${target}`;

// Bilingual forum: translate into the reader's language when the post is in the
// other one, and into the other language when it already matches the reader's.
const targetFor = (post) => {
  const detected = String(post.attribute('detectedLang') || '').split('-')[0].toLowerCase();
  const locale = localeOf();

  if (detected && detected !== locale) return locale;

  return locale === 'ar' ? 'en' : 'ar';
};

/**
 * On-demand translation appended under a post. Mirrors the behaviour of the
 * retired ianm/translate: translate the post into the reader's current locale,
 * cache the result server-side, and let the reader hide it again.
 */
export default class TranslationBlock extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.open = true;
  }

  view() {
    const post = this.attrs.post;

    if (!post.id() || post.isHidden?.()) {
      return null;
    }

    const target = targetFor(post);
    const key = keyFor(post, target);
    const translation = post.attribute('itqanTranslation');
    const translationLocale = String(post.attribute('itqanTranslationLocale') || '').toLowerCase();

    if (translation && translationLocale === target) {
      return (
        <div className="ItqanTranslation">
          <div className="ItqanTranslation-label">{app.translator.trans('itqan-translate.forum.label')}</div>
          {this.open && <div className="ItqanTranslation-text">{translation}</div>}
          <Button
            className="Button Button--text ItqanTranslation-toggle"
            onclick={() => {
              this.open = !this.open;
              m.redraw();
            }}
          >
            {app.translator.trans(
              this.open ? 'itqan-translate.forum.show_original' : 'itqan-translate.forum.show_translation'
            )}
          </Button>
        </div>
      );
    }

    if (pending.has(key)) {
      return (
        <div className="ItqanTranslation ItqanTranslation--loading">
          {app.translator.trans('itqan-translate.forum.translating')}
        </div>
      );
    }

    if (errors.has(key)) {
      return (
        <div className="ItqanTranslation ItqanTranslation--error">
          <div className="ItqanTranslation-text">{app.translator.trans('itqan-translate.forum.error')}</div>
          <Button className="Button Button--text ItqanTranslation-toggle" onclick={() => this.translate()}>
            {app.translator.trans('itqan-translate.forum.translate_button')}
          </Button>
        </div>
      );
    }

    return (
      <div className="ItqanTranslation-actions">
        <Button
          className="Button Button--text ItqanTranslation-button"
          icon="fas fa-language"
          onclick={() => this.translate()}
        >
          {app.translator.trans('itqan-translate.forum.translate_button')}
        </Button>
      </div>
    );
  }

  async translate() {
    const post = this.attrs.post;
    const target = targetFor(post);
    const key = keyFor(post, target);

    if (pending.has(key)) return;

    pending.add(key);
    errors.delete(key);
    m.redraw();

    try {
      const data = await app.request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/itqan-translate/posts/${post.id()}`,
        body: { language: target },
      });

      if (data && data.translation) {
        post.pushAttributes({
          itqanTranslation: data.translation,
          itqanTranslationLocale: target,
        });
      } else if (!(data && data.sameLanguage)) {
        errors.add(key);
      }
    } catch (e) {
      errors.add(key);
    } finally {
      pending.delete(key);
      m.redraw();
    }
  }
}

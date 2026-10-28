import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';

// Per-post, per-language request state. The translation itself lives on the Post
// model so it survives the stream re-creating component instances.
const pending = new Set();
const errors = new Set();
const selection = new Map();

const keyFor = (post, language) => `${post.id()}:${language}`;

const LANGUAGES = [
  { code: 'ar', label: 'العربية' },
  { code: 'en', label: 'English' },
];

/**
 * Translate a post into Arabic or English, chosen from a dropdown — the same
 * interaction the old ianm/translate offered. Results are cached server-side in
 * the existing translation table.
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

    const active = String(post.attribute('itqanTranslationLocale') || '').toLowerCase();
    const selected = selection.get(post.id()) || active;
    const translation = post.attribute('itqanTranslation');
    const key = selected ? keyFor(post, selected) : null;
    const loading = key ? pending.has(key) : false;
    const failed = key ? errors.has(key) : false;
    const showing = !!(translation && this.open && !loading && !failed);

    return (
      <div className="ItqanTranslation">
        <div className="ItqanTranslation-controls">
          <i className="icon fas fa-language ItqanTranslation-icon" aria-hidden="true" />
          <select
            className="FormControl ItqanTranslation-select"
            aria-label={app.translator.trans('itqan-translate.forum.select_language')}
            value={selected}
            onchange={(e) => {
              const language = e.target.value;
              if (!language) return;

              selection.set(post.id(), language);
              this.translate(language);
            }}
          >
            <option value="">{app.translator.trans('itqan-translate.forum.select_language')}</option>
            {LANGUAGES.map((language) => (
              <option value={language.code}>{language.label}</option>
            ))}
          </select>

          {translation && (
            <Button
              className="Button Button--text ItqanTranslation-toggle"
              onclick={() => {
                this.open = !this.open;
                m.redraw();
              }}
            >
              {app.translator.trans(this.open ? 'itqan-translate.forum.hide' : 'itqan-translate.forum.show')}
            </Button>
          )}
        </div>

        {loading && <div className="ItqanTranslation-text ItqanTranslation--loading">{app.translator.trans('itqan-translate.forum.translating')}</div>}

        {failed && !loading && <div className="ItqanTranslation-text ItqanTranslation--error">{app.translator.trans('itqan-translate.forum.error')}</div>}

        {showing && <div className="ItqanTranslation-text">{translation}</div>}
      </div>
    );
  }

  async translate(language) {
    const post = this.attrs.post;
    const key = keyFor(post, language);

    if (pending.has(key)) return;

    pending.add(key);
    errors.delete(key);
    m.redraw();

    try {
      const data = await app.request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/itqan-translate/posts/${post.id()}`,
        body: { language },
      });

      if (data && data.translation) {
        post.pushAttributes({
          itqanTranslation: data.translation,
          itqanTranslationLocale: language,
        });
        this.open = true;
        selection.delete(post.id());
      } else if (data && data.sameLanguage) {
        post.pushAttributes({ itqanTranslation: null, itqanTranslationLocale: null });
        selection.delete(post.id());
      } else {
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

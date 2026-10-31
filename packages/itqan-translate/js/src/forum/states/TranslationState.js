import app from 'flarum/forum/app';

/**
 * Per-post / per-discussion translation request state.
 *
 * Mirrors `ianm/translate`'s TranslationState, except that the payload is kept
 * locally instead of being pushed into the API store: the 2.0 backend returns a
 * plain `{ translation, language, provider, updatedAt }` object rather than a
 * JSON:API document.
 */
export default class TranslationState {
  constructor(item, type = 'post') {
    this.item = item;
    this.itemType = type;

    this.showing = false;
    this.loading = false;
    this.error = false;
    this.language = null;
    this.currentTranslation = null;
    this.provider = null;
    this.updatedAt = null;

    this.closeTranslation = this.closeTranslation.bind(this);
  }

  closeTranslation() {
    this.showing = false;
    m.redraw();
  }

  endpoint() {
    const base = this.itemType === 'post' ? 'itqan-translate/posts' : 'itqan-translate/discussions';

    return `${app.forum.attribute('apiUrl')}/${base}/${this.item.id()}`;
  }

  loadTranslation(code, refresh = false) {
    this.loading = true;
    this.error = false;
    m.redraw();

    return app
      .request({
        method: 'POST',
        url: this.endpoint(),
        body: { language: code, refresh },
      })
      .then((response) => {
        this.currentTranslation = response.translation || null;
        this.language = response.language || code;
        this.provider = response.provider || 'google-free';
        this.updatedAt = response.updatedAt ? new Date(response.updatedAt) : new Date();
        this.loading = false;
        this.showing = !!this.currentTranslation;
        m.redraw();
      })
      .catch((error) => {
        console.error('itqan-translate: translation failed', error);
        this.loading = false;
        this.error = true;
        m.redraw();
      });
  }

  refreshTranslation() {
    if (this.language) {
      return this.loadTranslation(this.language, true);
    }
  }
}

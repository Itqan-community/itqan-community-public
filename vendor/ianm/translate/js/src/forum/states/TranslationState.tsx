import app from 'flarum/forum/app';
import Post from 'flarum/common/models/Post';
import Discussion from 'flarum/common/models/Discussion';
import PostTranslation from '../../common/model/PostTranslation';
import DiscussionTranslation from '../../common/model/DiscussionTranslation';

export default class TranslationState {
  item: Post | Discussion;
  itemType: 'post' | 'discussion';

  constructor(item: Post | Discussion) {
    this.item = item;
    this.itemType = item instanceof Post ? 'post' : 'discussion';
    this.closeTranslation = this.closeTranslation.bind(this);
  }

  showing = false;
  loading = false;
  language: string | null = null;
  currentTranslation: PostTranslation | DiscussionTranslation | undefined = undefined;

  closeTranslation() {
    this.showing = false;
    m.redraw();
  }

  constructUrl(code: string, refresh = false): string {
    const base = this.itemType === 'post' ? 'posts' : 'discussions';
    return `${app.forum.attribute('apiUrl')}/${base}/${this.item.id()}/translate/${code}${refresh ? '?refresh=true' : ''}`;
  }

  handleResponse(response: any, code: string) {
    app.store.pushPayload(response);
    const translationId = `${this.item.id()}${code}`;
    this.currentTranslation =
      this.itemType === 'post' ? app.store.getById('post-translation', translationId) : app.store.getById('discussion-translation', translationId);
    this.loading = false;
    this.showing = true;
    this.language = code;
    m.redraw();
  }

  loadTranslation(code: string) {
    this.loading = true;

    app
      .request({
        method: 'GET',
        url: this.constructUrl(code),
      })
      .then((response) => this.handleResponse(response, code))
      .catch((error) => {
        console.error('Error loading translation:', error);
        this.loading = false;
        m.redraw();
      });
  }

  refreshTranslation(translation: PostTranslation | DiscussionTranslation) {
    this.loading = true;

    app
      .request({
        method: 'GET',
        url: this.constructUrl(translation.language(), true),
      })
      .then((response) => this.handleResponse(response, translation.language()))
      .catch((error) => {
        console.error('Error refreshing translation:', error);
        this.loading = false;
        m.redraw();
      });
  }
}

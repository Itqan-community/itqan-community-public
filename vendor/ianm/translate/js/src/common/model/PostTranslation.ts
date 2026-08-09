import Model from 'flarum/common/Model';
import Post from 'flarum/common/models/Post';

export default class PostTranslation extends Model {
  postId() {
    return Model.attribute<number>('postId').call(this);
  }

  language() {
    return Model.attribute<string>('language').call(this);
  }

  translation() {
    return Model.attribute<string>('translation').call(this);
  }

  provider() {
    return Model.attribute<string>('provider').call(this);
  }

  translationHtml() {
    return Model.attribute<string>('translationHtml').call(this);
  }

  createdAt() {
    return Model.attribute('createdAt', Model.transformDate).call(this);
  }

  updatedAt() {
    return Model.attribute('updatedAt', Model.transformDate).call(this);
  }

  post() {
    return Model.hasOne<Post>('post').call(this);
  }
}

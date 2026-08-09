import Model from 'flarum/common/Model';
import Discussion from 'flarum/common/models/Discussion';

export default class DiscussionTranslation extends Model {
  discussionId() {
    return Model.attribute<number>('discussionId').call(this);
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

  createdAt() {
    return Model.attribute('createdAt', Model.transformDate).call(this);
  }

  updatedAt() {
    return Model.attribute('updatedAt', Model.transformDate).call(this);
  }

  discussion() {
    return Model.hasOne<Discussion>('discussion').call(this);
  }
}

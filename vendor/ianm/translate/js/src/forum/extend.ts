import Extend from 'flarum/common/extenders';
import Discussion from 'flarum/common/models/Discussion';
import Post from 'flarum/common/models/Post';
import User from 'flarum/common/models/User';
import PostTranslation from '../common/model/PostTranslation';
import DiscussionTranslation from '../common/model/DiscussionTranslation';

export default [
  new Extend.Store() //
    .add('post-translation', PostTranslation)
    .add('discussion-translation', DiscussionTranslation),

  new Extend.Model(Post) //
    .attribute<string>('detectedLang')
    .hasMany<PostTranslation>('translations'),

  new Extend.Model(User) //
    .attribute<boolean>('canForceRefreshTranslations'),

  new Extend.Model(Discussion) //
    .attribute<boolean>('canTranslate')
    .attribute<string>('detectedLang')
    .hasMany<DiscussionTranslation>('translations')
    .hasOne<DiscussionTranslation>('translation'),
];

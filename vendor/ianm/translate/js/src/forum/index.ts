import app from 'flarum/forum/app';
import extendPosts from './extend/extendPosts';
import extendUserSettings from './extend/extendUserSettings';
import extendDiscussions from './extend/extendDiscussions';

export { default as extend } from './extend';

app.initializers.add('ianm-translate', () => {
  extendPosts();
  extendDiscussions();
  extendUserSettings();
});

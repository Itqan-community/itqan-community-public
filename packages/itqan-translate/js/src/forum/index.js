import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import TranslationBlock from './components/TranslationBlock';

app.initializers.add('itqan-translate', () => {
  // Append the translation block after the post body. The method returns an
  // array, so `extend` can push into it.
  extend('flarum/forum/components/CommentPost', 'content', function (items) {
    items.push(<TranslationBlock post={this.attrs.post} />);
  });
});

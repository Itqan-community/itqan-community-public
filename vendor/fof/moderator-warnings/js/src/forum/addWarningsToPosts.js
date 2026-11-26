import { extend } from 'flarum/common/extend';
import Model from 'flarum/common/Model';
import Post from 'flarum/common/models/Post';
import CommentPost from 'flarum/forum/components/CommentPost';
import PostWarningList from './components/PostWarningList';

export default function addWarningsToPosts() {
  Post.prototype.warnings = Model.hasMany('warnings');

  extend(CommentPost.prototype, 'footerItems', function (items) {
    const post = this.attrs.post;
    const warnings = post.warnings();

    if (!warnings) return;
    items.add(
      `warnings`,
      PostWarningList.component({
        post: post,
      })
    );
  });
}

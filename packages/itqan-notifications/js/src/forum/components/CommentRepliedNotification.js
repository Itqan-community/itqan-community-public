import Notification from 'flarum/forum/components/Notification';
// Task 8 fix: the ported branch used bare `common/...` paths, which webpack
// cannot resolve (only `flarum/...` requests are externalized) — its build had
// never actually run. `humanTime` here is the *string* util (utils/humanTime),
// not the vnode helper. The branch's unused `icon`/`extractText` imports were
// dropped with it.
import humanTime from 'flarum/common/utils/humanTime';
import app from 'flarum/forum/app';

export default class CommentRepliedNotification extends Notification {
  icon() {
    return 'fas fa-reply';
  }

  href() {
    const post = this.attrs.notification.subject();
    if (!post) return '#';

    const discussion = post.discussion();
    if (!discussion) return '#';

    return app.route.discussion(discussion, post.number());
  }

  content() {
    const post = this.attrs.notification.subject();
    const user = this.attrs.notification.fromUser();

    if (!post || !user) return null;

    return (
      <div className="Notification-content">
        <span className="Notification-label">
          {app.translator.trans('itqan-notifications.forum.notification.comment_replied_text', {
            username: user.username(),
          })}
        </span>
        {post.createdAt() && (
          <time className="Notification-time">{humanTime(post.createdAt())}</time>
        )}
      </div>
    );
  }

  excerpt() {
    const post = this.attrs.notification.subject();
    if (!post) return null;

    const content = post.contentPlain();
    if (!content) return null;

    return content.substring(0, 200);
  }
}

import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';

export default class CommentRepliedNotification extends Notification {
  icon() {
    return 'fas fa-reply';
  }

  /**
   * Deep-link to the new reply (the post that triggered the alert). Falls back
   * to the parent post number if the reply number is unavailable.
   */
  href() {
    const subject = this.attrs.notification.subject();
    if (!subject) return '#';

    const discussion = subject.discussion ? subject.discussion() : null;
    if (!discussion) return '#';

    const data = this.attrs.notification.data() || {};
    const targetNumber =
      data.postNumber || data.parentPostNumber || (typeof subject.number === 'function' ? subject.number() : null);

    if (!targetNumber) return '#';

    return app.route.discussion(discussion, targetNumber);
  }

  /**
   * The base `Notification` view already wraps this in `.Notification-content`
   * and renders the timestamp, so return only the translated label.
   */
  content() {
    return app.translator.trans('itqan-discussions.forum.notifications.comment_replied_text', {
      user: this.attrs.notification.fromUser(),
    });
  }

  /**
   * Best-effort preview of the new reply. The reply may not be in the store
   * (only the parent is bundled with the notification), so fall back to the
   * parent comment's content.
   */
  excerpt() {
    const subject = this.attrs.notification.subject();
    if (!subject) return null;

    const data = this.attrs.notification.data() || {};
    const discussion = subject.discussion ? subject.discussion() : null;
    const discussionId = discussion && typeof discussion.id === 'function' ? discussion.id() : null;
    const replyNumber = data.postNumber;

    if (discussionId && replyNumber && app.store && typeof app.store.all === 'function') {
      const posts = app.store.all('posts') || [];

      for (let i = 0; i < posts.length; i++) {
        const p = posts[i];
        if (!p) continue;

        const pNumber = typeof p.number === 'function' ? p.number() : p.attribute && p.attribute('number');
        const pDiscussion = p.discussion && p.discussion();
        const pDiscussionId =
          pDiscussion && typeof pDiscussion.id === 'function' ? pDiscussion.id() : p.attribute && p.attribute('discussionId');

        if (String(pNumber) === String(replyNumber) && String(pDiscussionId) === String(discussionId)) {
          const content = typeof p.contentPlain === 'function' ? p.contentPlain() : null;
          if (content) return content.substring(0, 200);
        }
      }
    }

    const parentContent = typeof subject.contentPlain === 'function' ? subject.contentPlain() : null;

    return parentContent ? parentContent.substring(0, 200) : null;
  }
}

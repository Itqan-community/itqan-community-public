import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import ComposerPostPreview from 'flarum/forum/components/ComposerPostPreview';
import NestedRepliesQuickReply from './NestedRepliesQuickReply';

// Host for the in-card reply form. `indent` is 1 (child level) or 0 (at the
// depth cap); the LESS converts it to an inline-start margin.
export default class NestedRepliesInlineReply extends Component {
  view() {
    const { post, discussion, draft, mode, onCancel, onSubmitted, onRedraw, editMode } = this.attrs;

    return m(
      'div.NestedRepliesInlineReply',
      { style: `--form-indent: ${this.attrs.indent}` },
      mode === 'composer' ? this.composerBody() : m(NestedRepliesQuickReply, { post, discussion, draft, onCancel, onSubmitted, onRedraw, editMode })
    );
  }

  composerBody() {
    const body = app.composer && app.composer.body;
    const user = this.attrs.post.user();
    const preview = Boolean(this.attrs.preview);

    return m('div.NestedRepliesInlineComposer', [
      m('div.NestedRepliesInlineComposer-head', [
        m(
          'span.NestedRepliesInlineComposer-title',
          app.translator.trans('mtareq-nested-replies.forum.reply_form_replying', { username: user ? user.displayName() : '' })
        ),
        m(
          Button,
          { className: 'Button Button--link', onclick: () => this.attrs.onCancel() },
          app.translator.trans('mtareq-nested-replies.forum.reply_form_cancel')
        ),
      ]),
      body && body.componentClass
        ? [
            preview
              ? m(ComposerPostPreview, { className: 'Post-body NestedRepliesInlineComposer-preview', composer: app.composer })
              : null,
            m(
              'div.NestedRepliesInlineComposer-body' + (preview ? '.is-previewing' : ''),
              m(body.componentClass, { ...body.attrs, composer: app.composer })
            ),
          ]
        : m('div.NestedRepliesInlineComposer-loading', app.translator.trans('mtareq-nested-replies.forum.sort_loading')),
    ]);
  }

  oninit(vnode) {
    super.oninit(vnode);
    this.seenVisible = false;
  }

  onupdate() {
    // ReplyComposer hides the composer state on a successful submit; clear the
    // inline host when that happens. Wait until the composer has been visible
    // once so an in-flight async show() (2.x) cannot cancel the open form.
    if (this.attrs.mode !== 'composer' || !app.composer) return;

    if (app.composer.isVisible()) {
      this.seenVisible = true;
      return;
    }

    if (this.seenVisible) this.attrs.onClosed();
  }
}

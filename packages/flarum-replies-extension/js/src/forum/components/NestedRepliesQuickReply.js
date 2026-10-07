import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import icon from 'flarum/common/helpers/icon';
import ComposerPostPreview from 'flarum/forum/components/ComposerPostPreview';
import { applyMarkdown } from '../utils/markdownFormat';
import { buildReplyData } from '../utils/replyData';

export default class NestedRepliesQuickReply extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.saving = false;
    this.uploadingImage = false;
    this.error = null;
    this.preview = false;
    this.editMode = Boolean(this.attrs.editMode);
    this.wasEmpty = !String(this.attrs.draft() || '').trim();
  }

  uploadImage(file) {
    if (!file || !file.type || !file.type.startsWith('image/')) return;

    this.uploadingImage = true;
    this.redraw();

    const body = new FormData();
    body.append('files[]', file);

    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/fof/upload',
      serialize: (raw) => raw,
      body,
    })
    .then((response) => {
      this.uploadingImage = false;
      const uploaded = response && response.data && response.data[0];
      if (uploaded && uploaded.attributes) {
        const bbcode = uploaded.attributes.bbcode || `![${file.name || 'image'}](${uploaded.attributes.url})`;
        const current = String(this.attrs.draft() || '');
        this.attrs.draft(current ? current + '\n' + bbcode : bbcode);
      }
      this.redraw();
    })
    .catch(() => {
      this.uploadingImage = false;
      this.error = app.translator.trans('mtareq-nested-replies.forum.reply_form_error');
      this.redraw();
    });
  }

  // The form renders inside a Post, whose SubtreeRetainer caches the post vnode;
  // a plain m.redraw() does not reach this component. Ask the host to invalidate
  // the enclosing post so state changes (preview, formatting, submit) render.
  redraw() {
    if (this.attrs.onRedraw) this.attrs.onRedraw();
    else m.redraw();
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    const textarea = vnode.dom.querySelector('.NestedRepliesQuickReply-input');
    if (textarea) textarea.focus();
  }

  format(key) {
    const textarea = this.$('.NestedRepliesQuickReply-input')[0];
    if (!textarea) return;

    const result = applyMarkdown(textarea.value, textarea.selectionStart, textarea.selectionEnd, key);
    this.attrs.draft(result.value);
    this.redraw();

    requestAnimationFrame(() => {
      const next = this.$('.NestedRepliesQuickReply-input')[0];
      if (!next) return;
      next.focus();
      next.setSelectionRange(result.selectionStart, result.selectionEnd);
    });
  }

  submit() {
    const content = String(this.attrs.draft() || '').trim();
    if (!content || this.saving) return;

    this.saving = true;
    this.error = null;
    this.redraw();

    const savePromise = this.editMode
      ? this.attrs.post.save({ content })
      : app.store.createRecord('posts').save(buildReplyData(content, this.attrs.post.id(), this.attrs.discussion));

    savePromise
      .then((post) => {
        this.saving = false;
        this.attrs.onSubmitted(post);
      })
      .catch(() => {
        this.saving = false;
        this.error = app.translator.trans('mtareq-nested-replies.forum.reply_form_error');
        this.redraw();
      });
  }

  view() {
    const trans = (key, params) => app.translator.trans(`mtareq-nested-replies.forum.${key}`, params);
    const user = this.attrs.post.user();
    const placeholder = this.editMode
      ? trans('reply_form_placeholder_op')
      : (user ? trans('reply_form_placeholder', { username: user.displayName() }) : trans('reply_form_placeholder_op'));
    const submitLabel = this.editMode ? trans('edit_form_submit') : trans('reply_form_submit');

    return m('div.NestedRepliesQuickReply', [
      m('div.NestedRepliesQuickReply-tabs', [
        m(
          'button.NestedRepliesQuickReply-tab' + (this.preview ? '' : '.is-active'),
          {
            type: 'button',
            onclick: () => {
              this.preview = false;
              this.redraw();
            },
          },
          trans('reply_form_write')
        ),
        m(
          'button.NestedRepliesQuickReply-tab' + (this.preview ? '.is-active' : ''),
          {
            type: 'button',
            onclick: () => {
              this.preview = true;
              this.redraw();
            },
          },
          trans('reply_form_preview')
        ),
      ]),
      this.preview
        ? m(ComposerPostPreview, {
            className: 'Post-body NestedRepliesQuickReply-preview',
            composer: { isVisible: () => true, fields: { content: () => this.attrs.draft() } },
          })
        : m('textarea.NestedRepliesQuickReply-input', {
            placeholder,
            value: this.attrs.draft(),
            disabled: this.saving,
            oninput: (e) => {
              const value = e.target.value;
              this.attrs.draft(value);
              const empty = !String(value).trim();
              if (empty !== this.wasEmpty) {
                this.wasEmpty = empty;
                this.redraw();
              }
            },
            onpaste: (e) => {
              const items = e.clipboardData && e.clipboardData.items;
              if (items) {
                for (let i = 0; i < items.length; i++) {
                  if (items[i].type && items[i].type.startsWith('image/')) {
                    const file = items[i].getAsFile();
                    if (file) {
                      e.preventDefault();
                      this.uploadImage(file);
                      break;
                    }
                  }
                }
              }
            },
            onkeydown: (e) => {
              if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                this.submit();
              }
              if (e.key === 'Escape') this.attrs.onCancel();
            },
          }),
      m('div.NestedRepliesQuickReply-toolbar', [
        m('label.Button.Button--icon.NestedRepliesQuickReply-format', {
          title: 'رفع صورة',
          style: 'cursor: pointer; display: inline-flex; align-items: center; justify-content: center;',
        }, [
          icon(this.uploadingImage ? 'fas fa-spinner fa-spin' : 'fas fa-image', { className: 'Button-icon' }),
          m('input', {
            type: 'file',
            accept: 'image/*',
            style: 'display: none',
            onchange: (e) => {
              if (e.target.files && e.target.files[0]) {
                this.uploadImage(e.target.files[0]);
                e.target.value = '';
              }
            }
          })
        ]),
        this.formatButton('bold', 'fas fa-bold', 'reply_form_bold'),
        this.formatButton('italic', 'fas fa-italic', 'reply_form_italic'),
        this.formatButton('quote', 'fas fa-quote-right', 'reply_form_quote'),
        this.formatButton('link', 'fas fa-link', 'reply_form_link'),
        this.formatButton('mention', 'fas fa-at', 'reply_form_mention'),
      ]),
      this.error ? m('div.NestedRepliesQuickReply-error', this.error) : null,
      m('div.NestedRepliesQuickReply-actions', [
        m(
          Button,
          {
            className: 'Button Button--primary NestedRepliesQuickReply-submit',
            disabled: this.saving || !String(this.attrs.draft() || '').trim(),
            onclick: () => this.submit(),
          },
          submitLabel
        ),
        m(Button, { className: 'Button Button--link', onclick: () => this.attrs.onCancel() }, trans('reply_form_cancel')),
      ]),
    ]);
  }

  formatButton(key, iconName, labelKey) {
    return m(
      Button,
      {
        icon: iconName,
        className: 'Button Button--icon NestedRepliesQuickReply-format',
        title: app.translator.trans(`mtareq-nested-replies.forum.${labelKey}`),
        onclick: () => this.format(key),
      }
    );
  }
}

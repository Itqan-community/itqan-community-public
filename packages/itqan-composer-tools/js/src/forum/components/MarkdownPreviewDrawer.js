/* global s9e */

import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';

const UPDATE_INTERVAL = 50;

/**
 * Render the current composer content with the same TextFormatter instance
 * Flarum uses for published posts.
 */
export default class MarkdownPreviewDrawer extends Component {
  view() {
    return (
      <section
        id={this.attrs.id}
        className="MarkdownPreviewDrawer Composer-flexible"
        role="region"
        aria-labelledby={`${this.attrs.id}-title`}
      >
        <header className="MarkdownPreviewDrawer-header">
          <h4 id={`${this.attrs.id}-title`}>{app.translator.trans('itqan-composer-tools.forum.preview.title')}</h4>
        </header>

        <p className="MarkdownPreviewDrawer-state MarkdownPreviewDrawer-empty">
          {app.translator.trans('itqan-composer-tools.forum.preview.empty')}
        </p>
        <p className="MarkdownPreviewDrawer-state MarkdownPreviewDrawer-error" hidden>
          {app.translator.trans('itqan-composer-tools.forum.preview.error')}
        </p>
        <div className="MarkdownPreviewDrawer-content Post-body" hidden />
      </section>
    );
  }

  oncreate(vnode) {
    super.oncreate(vnode);

    this.previewElement = vnode.dom.querySelector('.MarkdownPreviewDrawer-content');
    this.emptyElement = vnode.dom.querySelector('.MarkdownPreviewDrawer-empty');
    this.errorElement = vnode.dom.querySelector('.MarkdownPreviewDrawer-error');
    this.lastContent = undefined;

    this.updatePreview();
    this.updateInterval = window.setInterval(() => this.updatePreview(), UPDATE_INTERVAL);
  }

  onremove(vnode) {
    super.onremove(vnode);

    window.clearInterval(this.updateInterval);
  }

  updatePreview() {
    if (!this.attrs.composer.isVisible()) return;

    const content = this.attrs.composer.fields.content();
    if (content === this.lastContent) return;

    this.lastContent = content;

    if (!content.trim()) {
      this.previewElement.replaceChildren();
      this.previewElement.hidden = true;
      this.errorElement.hidden = true;
      this.emptyElement.hidden = false;
      return;
    }

    try {
      s9e.TextFormatter.preview(content, this.previewElement);
      this.previewElement.hidden = false;
      this.emptyElement.hidden = true;
      this.errorElement.hidden = true;
    } catch (error) {
      this.previewElement.replaceChildren();
      this.previewElement.hidden = true;
      this.emptyElement.hidden = true;
      this.errorElement.hidden = false;
    }
  }
}

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
    this.disposed = false;
    this.waitingForPolyfill = false;
    this.polyfillSettled = false;

    this.updatePreview();
    this.updateInterval = window.setInterval(() => this.updatePreview(), UPDATE_INTERVAL);
  }

  onremove(vnode) {
    super.onremove(vnode);

    this.disposed = true;
    window.clearInterval(this.updateInterval);
  }

  showError(content) {
    this.lastContent = content;
    this.previewElement.replaceChildren();
    this.previewElement.hidden = true;
    this.emptyElement.hidden = true;
    this.errorElement.hidden = false;
  }

  updatePreview() {
    if (this.disposed || this.waitingForPolyfill || !this.attrs.composer.isVisible()) return;

    const content = this.attrs.composer.fields.content();
    if (content === this.lastContent) return;

    if (!content.trim()) {
      this.lastContent = content;
      this.previewElement.replaceChildren();
      this.previewElement.hidden = true;
      this.errorElement.hidden = true;
      this.emptyElement.hidden = false;
      return;
    }

    try {
      s9e.TextFormatter.preview(content, this.previewElement);
      this.lastContent = content;
      this.previewElement.hidden = false;
      this.emptyElement.hidden = true;
      this.errorElement.hidden = true;
    } catch (error) {
      if (!this.polyfillSettled && typeof window !== 'undefined' && typeof window.xsltPolyfillReady === 'function') {
        this.waitingForPolyfill = true;
        Promise.resolve()
          .then(() => window.xsltPolyfillReady())
          .then(
            () => {
              this.waitingForPolyfill = false;
              this.polyfillSettled = true;
              if (!this.disposed) this.updatePreview();
            },
            () => {
              this.waitingForPolyfill = false;
              this.polyfillSettled = true;
              if (!this.disposed) this.showError(content);
            }
          );
        return;
      }

      this.showError(content);
    }
  }
}

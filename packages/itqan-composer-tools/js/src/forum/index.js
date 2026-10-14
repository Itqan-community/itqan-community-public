import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import TextEditor from 'flarum/common/components/TextEditor';
import Tooltip from 'flarum/common/components/Tooltip';
import classList from 'flarum/common/utils/classList';
import DiscussionComposer from 'flarum/forum/components/DiscussionComposer';

import MarkdownPreviewDrawer from './components/MarkdownPreviewDrawer';

export { default as MarkdownPreviewDrawer } from './components/MarkdownPreviewDrawer';

const PREVIEW_ID = 'itqan-markdown-preview';

function isDiscussionComposer(composer) {
  return composer.bodyMatches(DiscussionComposer);
}

app.initializers.add('itqan-composer-tools', () => {
  extend(TextEditor.prototype, 'oninit', function () {
    this.itqanMarkdownPreviewOpen = false;
  });

  extend(TextEditor.prototype, 'controlItems', function (items) {
    if (!isDiscussionComposer(this.attrs.composer)) return;

    const open = this.itqanMarkdownPreviewOpen;
    const label = app.translator.trans(
      open ? 'itqan-composer-tools.forum.preview.hide_button' : 'itqan-composer-tools.forum.preview.show_button'
    );

    items.add(
      'itqanMarkdownPreview',
      <Tooltip text={label}>
        <Button
          className={classList('Button Button--icon MarkdownPreviewToggle', { active: open })}
          icon={open ? 'far fa-eye-slash' : 'far fa-eye'}
          aria-label={label}
          aria-controls={PREVIEW_ID}
          aria-expanded={open ? 'true' : 'false'}
          aria-pressed={open ? 'true' : 'false'}
          onclick={() => {
            this.itqanMarkdownPreviewOpen = !this.itqanMarkdownPreviewOpen;
            m.redraw.sync();
          }}
        />
      </Tooltip>,
      5
    );
  });

  extend(TextEditor.prototype, 'view', function (vdom) {
    if (!isDiscussionComposer(this.attrs.composer)) return;

    vdom.attrs.className = classList(vdom.attrs.className, {
      'TextEditor--markdownPreview': this.itqanMarkdownPreviewOpen,
    });

    if (this.itqanMarkdownPreviewOpen) {
      vdom.children.push(<MarkdownPreviewDrawer id={PREVIEW_ID} composer={this.attrs.composer} />);
    }
  });
});

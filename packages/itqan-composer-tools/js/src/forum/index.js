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

// Helper function to insert text at cursor
function insertAtCursor(composer, text) {
  if (!composer) return;

  const currentContent = composer.fields.content();
  const newContent = currentContent + '\n' + text;
  composer.fields.content(newContent);
}

app.initializers.add('itqan-composer-tools', () => {
  extend(TextEditor.prototype, 'oninit', function () {
    this.itqanMarkdownPreviewOpen = false;
  });

  extend(TextEditor.prototype, 'controlItems', function (items) {
    if (!isDiscussionComposer(this.attrs.composer)) return;

    const composer = this.attrs.composer;

    // Table Button
    items.add(
      'itqanTableInsert',
      <Tooltip text="Insert Table">
        <Button
          className="Button Button--icon"
          icon="fas fa-table"
          aria-label="Insert Table"
          onclick={() => {
            const tableMarkdown = `| Header 1 | Header 2 | Header 3 |
|----------|----------|----------|
| Cell 1   | Cell 2   | Cell 3   |`;
            insertAtCursor(composer, tableMarkdown);
            m.redraw();
          }}
        />
      </Tooltip>,
      8
    );

    // Blockquote Button
    items.add(
      'itqanBlockquoteInsert',
      <Tooltip text="Insert Blockquote">
        <Button
          className="Button Button--icon"
          icon="fas fa-quote-left"
          aria-label="Insert Blockquote"
          onclick={() => {
            const blockquoteMarkdown = `> This is a blockquote.
> Add your text here.`;
            insertAtCursor(composer, blockquoteMarkdown);
            m.redraw();
          }}
        />
      </Tooltip>,
      7
    );

    // Code Block Button
    items.add(
      'itqanCodeBlockInsert',
      <Tooltip text="Insert Code Block">
        <Button
          className="Button Button--icon"
          icon="fas fa-code"
          aria-label="Insert Code Block"
          onclick={() => {
            const codeBlockMarkdown = `\`\`\`javascript
// Your code here
console.log('Hello');
\`\`\``;
            insertAtCursor(composer, codeBlockMarkdown);
            m.redraw();
          }}
        />
      </Tooltip>,
      6
    );

    // Original Preview Button
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
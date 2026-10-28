import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import Tooltip from 'flarum/common/components/Tooltip';
import TextEditor from 'flarum/common/components/TextEditor';
import DiscussionComposer from 'flarum/forum/components/DiscussionComposer';

function isDiscussionComposer(composer) {
  return composer.bodyMatches("flarum/forum/components/DiscussionComposer");
}

export default function addRichToolbarButtons() {
  extend('flarum/common/components/TextEditor', 'controlItems', function (items) {
    if (!isDiscussionComposer(this.attrs.composer)) return;

    items.add(
      'itqanTable',
      <Tooltip text={app.translator.trans('itqan-theme.forum.toolbar.table_tooltip')}>
        <Button
          className="Button Button--icon"
          icon="fas fa-table"
          aria-label={app.translator.trans('itqan-theme.forum.toolbar.table_tooltip')}
          onclick={() => {
            const tableMarkdown = `| Header | Header |
|--------|--------|
| Cell   | Cell   |
| Cell   | Cell   |
`;
            this.attrs.composer.editor.insertAtCursor(tableMarkdown);
          }}
        />
      </Tooltip>,
      10
    );

    items.add(
      'itqanQuote',
      <Tooltip text={app.translator.trans('itqan-theme.forum.toolbar.quote_tooltip')}>
        <Button
          className="Button Button--icon"
          icon="fas fa-quote-left"
          aria-label={app.translator.trans('itqan-theme.forum.toolbar.quote_tooltip')}
          onclick={() => {
            const quoteMarkdown = `> Blockquote text here
`;
            this.attrs.composer.editor.insertAtCursor(quoteMarkdown);
          }}
        />
      </Tooltip>,
      9
    );

    items.add(
      'itqanCode',
      <Tooltip text={app.translator.trans('itqan-theme.forum.toolbar.code_tooltip')}>
        <Button
          className="Button Button--icon"
          icon="fas fa-code"
          aria-label={app.translator.trans('itqan-theme.forum.toolbar.code_tooltip')}
          onclick={() => {
            const codeMarkdown = `\`\`\`
// Code goes here
\`\`\`
`;
            this.attrs.composer.editor.insertAtCursor(codeMarkdown);
          }}
        />
      </Tooltip>,
      8
    );
  });
}

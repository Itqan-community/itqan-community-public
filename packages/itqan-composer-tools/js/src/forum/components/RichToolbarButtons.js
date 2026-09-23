import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import Tooltip from 'flarum/common/components/Tooltip';
import TextEditor from 'flarum/common/components/TextEditor';
import DiscussionComposer from 'flarum/forum/components/DiscussionComposer';

function isDiscussionComposer(composer) {
  return composer.bodyMatches(DiscussionComposer);
}

export default function addRichToolbarButtons() {
  extend(TextEditor.prototype, 'controlItems', function (items) {
    if (!isDiscussionComposer(this.attrs.composer)) return;

    items.add(
      'itqanTable',
      <Tooltip text={app.translator.trans('itqan-composer-tools.forum.toolbar.table_tooltip', {}, 'Insert Table')}>
        <Button
          className="Button Button--icon"
          icon="fas fa-table"
          onclick={() => {
            this.insertAtCursor('\\n| Header | Header |\\n|--------|--------|\\n| Cell   | Cell   |\\n| Cell   | Cell   |\\n');
          }}
        />
      </Tooltip>,
      10
    );

    items.add(
      'itqanQuote',
      <Tooltip text={app.translator.trans('itqan-composer-tools.forum.toolbar.quote_tooltip', {}, 'Insert Blockquote')}>
        <Button
          className="Button Button--icon"
          icon="fas fa-quote-left"
          onclick={() => {
            this.insertAtCursor('\\n> Blockquote text here\\n');
          }}
        />
      </Tooltip>,
      9
    );

    items.add(
      'itqanCode',
      <Tooltip text={app.translator.trans('itqan-composer-tools.forum.toolbar.code_tooltip', {}, 'Insert Code Block')}>
        <Button
          className="Button Button--icon"
          icon="fas fa-code"
          onclick={() => {
            this.insertAtCursor('\\n```\\n// Code goes here\\n```\\n');
          }}
        />
      </Tooltip>,
      8
    );
  });
}

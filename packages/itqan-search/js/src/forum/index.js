import app from 'flarum/forum/app';
import TextEditor from 'flarum/common/components/TextEditor';

import enhanceMentionSearch from './enhanceMentionSearch';

app.initializers.add('itqan-search', () => {
  enhanceMentionSearch(TextEditor.prototype);
});

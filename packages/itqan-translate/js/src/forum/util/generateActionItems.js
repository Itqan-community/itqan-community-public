import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Tooltip from 'flarum/common/components/Tooltip';
import ItemList from 'flarum/common/utils/ItemList';

/**
 * Close / refresh controls on a rendered translation, matching ianm/translate.
 */
export function generateActionItems(state) {
  const items = new ItemList();

  items.add(
    'translation-close',
    <Tooltip text={app.translator.trans('itqan-translate.forum.discussion.post.close-translation')}>
      <Button
        icon="fas fa-times"
        className="Button Button--icon Button--link Translation-close"
        onclick={state.closeTranslation}
        aria-label={app.translator.trans('itqan-translate.forum.discussion.post.close-translation')}
      />
    </Tooltip>,
    -100
  );

  if (app.session.user?.isAdmin?.()) {
    items.add(
      'translation-refresh',
      <Tooltip text="Refresh translation">
        <Button icon="fas fa-sync" className="Button Button--icon Button--link" onclick={() => state.refreshTranslation()} />
      </Tooltip>,
      90
    );
  }

  return items;
}

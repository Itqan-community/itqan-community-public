// utils/generateActionItems.ts
import ItemList from 'flarum/common/utils/ItemList';
import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Tooltip from 'flarum/common/components/Tooltip';
import TranslationState from '../states/TranslationState';
import type Mithril from 'mithril';
import Post from 'flarum/common/models/Post';
import Discussion from 'flarum/common/models/Discussion';
import DiscussionTranslation from 'src/common/model/DiscussionTranslation';
import PostTranslation from 'src/common/model/PostTranslation';

export function generateActionItems(
  item: Post | Discussion,
  translation: DiscussionTranslation | PostTranslation,
  state: TranslationState
): ItemList<Mithril.Children> {
  const items = new ItemList<Mithril.Children>();

  items.add(
    'translation-close',
    <Tooltip text={app.translator.trans('ianm-translate.forum.discussion.post.close-translation')}>
      <Button
        icon="fas fa-times"
        className="Button Button--icon Button--link Translation-close"
        onclick={state.closeTranslation}
        aria-label={app.translator.trans('ianm-translate.forum.discussion.post.close-translation')}
      />
    </Tooltip>,
    -100
  );

  if (app.session?.user?.canForceRefreshTranslations()) {
    items.add(
      'translation-refresh',
      <Tooltip text="Refresh translation">
        <Button icon="fas fa-sync" className="Button Button--icon Button--link" onclick={() => state.refreshTranslation(translation)} />
      </Tooltip>,
      90
    );
  }

  return items;
}

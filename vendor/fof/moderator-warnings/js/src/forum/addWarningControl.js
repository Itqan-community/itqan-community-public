import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import PostControls from 'flarum/forum/utils/PostControls';
import UserControls from 'flarum/forum/utils/UserControls';
import Button from 'flarum/common/components/Button';

import WarningModal from './components/WarningModal';

export default function () {
  extend(PostControls, 'moderationControls', function (items, post) {
    if (!app.session.user || !app.session.user.canManageWarnings()) return;

    items.add(
      'warning',
      <Button
        icon="fas fa-exclamation-circle"
        onclick={() =>
          app.modal.show(WarningModal, {
            callback: () => {
              location.reload();
            },
            user: post.user(),
            post: post,
          })
        }
      >
        {app.translator.trans('fof-moderator-warnings.forum.post_controls.warning_button')}
      </Button>
    );
  });

  extend(UserControls, 'moderationControls', function (items, user) {
    if (!app.session.user || !app.session.user.canManageWarnings()) return;

    items.add(
      'warning',
      <Button
        icon="fas fa-exclamation-circle"
        onclick={() =>
          app.modal.show(WarningModal, {
            callback: () => {
              location.reload();
            },
            user: user,
          })
        }
      >
        {app.translator.trans('fof-moderator-warnings.forum.post_controls.warning_button')}
      </Button>
    );
  });
}

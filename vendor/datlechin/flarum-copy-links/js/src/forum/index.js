import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import PostControls from 'flarum/forum/utils/PostControls';
import UserControls from 'flarum/forum/utils/UserControls';
import Button from 'flarum/common/components/Button';
import copyTextToClipboard from './utils/copyTextToClipboard';

app.initializers.add('datlechin/flarum-copy-links', () => {
  const CopyLinkButtonComponent = (route) => {
    const url = window.location.origin + route;

    return (
      <Button
        icon="fas fa-copy"
        onclick={() => {
          copyTextToClipboard(url);

          app.alerts.show(
            {
              type: 'success',
            },
            app.translator.trans('datlechin-copy-links.forum.link_copied_message')
          );
        }}
      >
        {app.translator.trans('datlechin-copy-links.forum.copy_link_button')}
      </Button>
    );
  };

  extend(DiscussionControls, 'userControls', function (items, discussion) {
    items.add('copyLink', CopyLinkButtonComponent(app.route.discussion(discussion)));
  });

  extend(PostControls, 'userControls', function (items, post) {
    items.add('copyLink', CopyLinkButtonComponent(app.route.post(post)));
  });

  extend(UserControls, 'userControls', function (items, user) {
    items.add('copyLink', CopyLinkButtonComponent(app.route.user(user)));
  });
});

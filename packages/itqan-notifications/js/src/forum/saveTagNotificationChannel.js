import app from 'flarum/forum/app';

import { canReceiveFollowTagsNotifications, savedChannel } from './channels';

export default function saveTagNotificationChannel(tag, subscription, selectedChannel) {
  if (!canReceiveFollowTagsNotifications(subscription)) {
    return Promise.resolve();
  }

  const next = selectedChannel === 'email' || selectedChannel === 'alert' || selectedChannel === 'mute' ? selectedChannel : null;
  const current = savedChannel(tag);

  if (next === current) {
    return Promise.resolve();
  }

  const url = `${app.forum.attribute('apiUrl')}/tags/${tag.id()}/notification-channel`;

  if (next === null) {
    return app
      .request({
        url,
        method: 'DELETE',
      })
      .then(() => {
        tag.pushAttributes({ itqanNotificationChannel: null });
      });
  }

  return app
    .request({
      url,
      method: 'POST',
      body: {
        data: {
          attributes: {
            channel: next,
          },
        },
      },
    })
    .then((payload) => app.store.pushPayload(payload));
}

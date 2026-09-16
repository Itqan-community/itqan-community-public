import app from 'flarum/forum/app';
import Model from 'flarum/common/Model';

import extendSubscriptionModal from './extendSubscriptionModal';

app.initializers.add('itqan-notifications', () => {
  if (app.store.models.tags) {
    app.store.models.tags.prototype.itqanNotificationChannel = Model.attribute('itqanNotificationChannel');
  }

  if (!('fof-follow-tags' in flarum.extensions)) {
    return;
  }

  extendSubscriptionModal();
});

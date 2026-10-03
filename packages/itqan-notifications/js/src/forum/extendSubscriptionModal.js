import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import followTags from '@fof-follow-tags';

import { canReceiveFollowTagsNotifications, channelOptions, savedChannel } from './channels';
import saveTagNotificationChannel from './saveTagNotificationChannel';

export default function extendSubscriptionModal() {
  const SubscriptionModal = followTags && followTags.components && followTags.components.SubscriptionModal;
  const SubscriptionOptionItem = followTags && followTags.components && followTags.components.SubscriptionOptionItem;

  if (!SubscriptionModal || !SubscriptionOptionItem) {
    return;
  }

  extend(SubscriptionModal.prototype, 'oninit', function () {
    this.channel = savedChannel(this.attrs.model);
  });

  extend(SubscriptionModal.prototype, 'formOptionItems', function (items) {
    const notifying = canReceiveFollowTagsNotifications(this.subscription);
    const loading = this.loading && this.loading();

    items.add(
      'itqan-notification-channel',
      <div className="Form-group ItqanNotificationChannel">
        <label>{app.translator.trans('itqan-notifications.forum.modal.title')}</label>
        <p className="helpText">{app.translator.trans('itqan-notifications.forum.modal.help')}</p>
        {notifying
          ? channelOptions.map((option) => (
              <SubscriptionOptionItem
                key={option.channel === null ? 'default' : option.channel}
                icon={option.icon}
                labelKey={option.labelKey}
                descriptionKey={option.descriptionKey}
                active={this.channel === option.channel}
                onclick={() => {
                  if (loading) {
                    return;
                  }

                  this.channel = option.channel;
                }}
              />
            ))
          : <p className="helpText">{app.translator.trans('itqan-notifications.forum.modal.follow_to_choose')}</p>}
      </div>,
      40
    );
  });

  override(SubscriptionModal.prototype, 'saveSubscription', function (original, subscription) {
    const tag = this.attrs.model;

    this.loading(true);
    this.subscription = subscription;

    return app
      .request({
        url: `${app.forum.attribute('apiUrl')}/tags/${tag.id()}/subscription`,
        method: 'POST',
        body: {
          data: this.requestData(),
        },
      })
      .then((payload) => app.store.pushPayload(payload))
      .then(() => saveTagNotificationChannel(tag, this.subscription, this.channel))
      .then(() => {
        this.loading(false);
        m.redraw();
        this.hide();
      })
      .catch(() => {
        this.loading(false);
        this.subscription = tag.subscription() || 'not_follow';
        this.channel = savedChannel(tag);
        m.redraw();
      });
  });
}

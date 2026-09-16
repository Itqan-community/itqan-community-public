export const NOTIFYING_SUBSCRIPTIONS = ['follow', 'lurk'];

export function canReceiveFollowTagsNotifications(subscription) {
  return NOTIFYING_SUBSCRIPTIONS.indexOf(subscription) !== -1;
}

export function savedChannel(tag) {
  if (!tag || typeof tag.itqanNotificationChannel !== 'function') {
    return null;
  }

  const value = tag.itqanNotificationChannel();

  return value === 'email' || value === 'alert' || value === 'mute' ? value : null;
}

export const channelOptions = [
  {
    channel: null,
    icon: 'fas fa-sliders-h',
    labelKey: 'itqan-notifications.forum.channel.default',
    descriptionKey: 'itqan-notifications.forum.modal.default_help',
  },
  {
    channel: 'email',
    icon: 'far fa-envelope',
    labelKey: 'itqan-notifications.forum.channel.email',
    descriptionKey: 'itqan-notifications.forum.modal.email_help',
  },
  {
    channel: 'alert',
    icon: 'fas fa-bell',
    labelKey: 'itqan-notifications.forum.channel.alert',
    descriptionKey: 'itqan-notifications.forum.modal.alert_help',
  },
  {
    channel: 'mute',
    icon: 'fas fa-volume-mute',
    labelKey: 'itqan-notifications.forum.channel.mute',
    descriptionKey: 'itqan-notifications.forum.modal.mute_help',
  },
];

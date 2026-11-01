<?php

namespace Itqan\Notifications\Listener;

use Flarum\Messages\DialogMessage;
use Flarum\Notification\Notification;
use Flarum\User\User;
use Illuminate\Database\Query\Builder;
use Itqan\Notifications\Notification\MessageAlertedBlueprint;

/**
 * Writes the bell entry for a private message.
 *
 * The recipient set mirrors upstream's `SendMessageNotificationsJob`: only
 * members who had read everything before this message, so a burst of messages
 * produces one notification rather than one per line. Nobody is notified about
 * their own message, and the reader's alert preference (the "web" column of the
 * direct-messages row in notification settings) can turn it off.
 */
class SendMessageAlerts
{
    public function handle(DialogMessage\Event\Created $event): void
    {
        $message = $event->message;

        $recipients = User::query()
            ->whereIn('id', function (Builder $query) use ($message) {
                $query->select('dialog_user.user_id')
                    ->from('dialog_user')
                    ->where('dialog_user.dialog_id', $message->dialog_id)
                    ->where('dialog_user.last_read_message_id', '<', $message->id)
                    ->whereNotExists(function (Builder $query) use ($message) {
                        $query->selectRaw('1')
                            ->from('dialog_messages')
                            ->whereColumn('dialog_messages.dialog_id', 'dialog_user.dialog_id')
                            ->whereColumn('dialog_messages.id', '>', 'dialog_user.last_read_message_id')
                            ->where('dialog_messages.id', '<', $message->id);
                    });
            })
            ->where('id', '!=', $message->user_id)
            ->get()
            ->filter(fn (User $user) => $user->shouldAlert('messageReceived'))
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        Notification::notify($recipients, new MessageAlertedBlueprint($message));
    }
}

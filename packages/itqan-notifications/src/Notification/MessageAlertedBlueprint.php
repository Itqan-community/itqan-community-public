<?php

namespace Itqan\Notifications\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Messages\DialogMessage;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/**
 * The bell entry for a received private message.
 *
 * `flarum/messages` ships `MessageReceivedBlueprint` for email only: it does not
 * implement `AlertableInterface`, so no in-app notification is ever written and
 * the "web" column in the notification settings is decorative.
 *
 * This is a second, alert-only blueprint with its own type. Duplicating the
 * upstream type would be worse than useless: the notification row is keyed by
 * type and subject, so an alert row would make the email driver treat the
 * recipient as already notified and silently drop the email.
 *
 * Rows are written directly (not through the syncer) so the upstream job keeps
 * owning delivery, and the reader's `notify_messageReceived_alert` preference —
 * the one the settings grid shows for direct messages — still decides.
 */
class MessageAlertedBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(public DialogMessage $message)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->message->user;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->message;
    }

    public function getData(): array
    {
        return [
            'dialogId' => (int) $this->message->dialog_id,
            'messageId' => (int) $this->message->id,
        ];
    }

    /**
     * The push and email payloads are built from the upstream blueprint; this
     * one only has to satisfy the interface.
     */
    public function getEmailViews(): array
    {
        return [
            'text' => 'flarum-messages::emails.plain.messageReceived',
            'html' => 'flarum-messages::emails.html.messageReceived',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('flarum-messages.email.message_received.subject', [
            '{user_display_name}' => $this->message->user->display_name ?? '',
        ]);
    }

    public static function getType(): string
    {
        return 'itqanMessageAlerted';
    }

    public static function getSubjectModel(): string
    {
        return DialogMessage::class;
    }
}

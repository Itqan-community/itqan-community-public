@php
/**
 * Html body for a received private message.
 *
 * Shadows `flarum-messages::emails.html.messageReceived` so a DM email uses the
 * same shaped body as every other Itqan notification: greeting, one sentence of
 * context, a direct call to action, then the message itself as a quoted preview.
 *
 * @var \Flarum\Messages\Notification\MessageReceivedBlueprint $blueprint
 */
@endphp

<x-mail::html.notification>
    <x-slot:body>
        {!! $formatter->convert($translator->trans('itqan-notifications.email.message_received.body', [
            '{recipient_display_name}' => $user->display_name,
            '{sender_display_name}' => $blueprint->message->user->display_name ?? $translator->trans('core.lib.username.deleted_text'),
            '{url}' => $url->to('forum')->route('messages.dialog', ['id' => $blueprint->message->dialog_id, 'near' => $blueprint->message->id]),
        ])) !!}
    </x-slot:body>

    <x-slot:preview>
        {!! $blueprint->message->formatContent() !!}
    </x-slot:preview>
</x-mail::html.notification>

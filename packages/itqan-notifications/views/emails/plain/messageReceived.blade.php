@php
/** @var \Flarum\Messages\Notification\MessageReceivedBlueprint $blueprint */
$body = $translator->trans('itqan-notifications.email.message_received.body', [
    '{recipient_display_name}' => $user->display_name,
    '{sender_display_name}' => $blueprint->message->user->display_name ?? $translator->trans('core.lib.username.deleted_text'),
    '{url}' => $url->to('forum')->route('messages.dialog', ['id' => $blueprint->message->dialog_id, 'near' => $blueprint->message->id]),
]);
@endphp

<x-mail::plain.notification>
<x-slot:body>
{!! $body !!}

----------------------------------------

{!! $blueprint->message->content !!}
</x-slot:body>
</x-mail::plain.notification>

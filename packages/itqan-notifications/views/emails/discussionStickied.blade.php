{!! $translator->trans('itqan-notifications.email.discussion_stickied.body', [
    '{recipient_display_name}' => $user->display_name,
    '{title}'                  => $blueprint->discussion->title ?? '',
    '{url}'                    => $url->to('forum')->route('discussion', ['id' => $blueprint->discussion->id]),
]) !!}

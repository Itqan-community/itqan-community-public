{!! $translator->trans('itqan-notifications.email.discussion_retagged.body', [
    '{recipient_display_name}' => $user->display_name,
    '{title}'                  => $blueprint->discussion->title ?? '',
    '{new_tag}'                => $blueprint->tagName,
    '{url}'                    => $url->to('forum')->route('discussion', ['id' => $blueprint->discussion->id]),
]) !!}

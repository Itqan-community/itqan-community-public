{!! $translator->trans('itqan-notifications.email.post_rejected.body', [
    '{recipient_display_name}' => $user->display_name,
    '{title}'                  => $blueprint->post->discussion?->title ?? '',
    '{reason}'                 => $blueprint->reason ?: 'عدم تطابق المشاركة مع قواعد النشر في المجتمع',
]) !!}

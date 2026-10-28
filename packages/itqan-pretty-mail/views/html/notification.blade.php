{{--
    Notification body.

    A stored `fof-pretty-mail.{type}` template is a complete document (that is
    what the 1.x admin UI produced), so it bypasses the outer shell. Otherwise
    core's slot layout is reproduced.
--}}
{!! app(\Itqan\PrettyMail\Renderer::class)->notification(get_defined_vars()) !!}

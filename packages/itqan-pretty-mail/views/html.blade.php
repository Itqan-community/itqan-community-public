{{--
    Outer HTML shell for every Flarum email.

    Flarum 2.0 renders mail through this Blade component and passes the slots
    (`header`, `content`, `footer`). The admin-editable template keeps the
    `fof-pretty-mail.mailhtml` key and receives their concatenation as `$body`,
    which is what the 1.x template syntax expects.
--}}
{!! app(\Itqan\PrettyMail\Renderer::class)->wrapper(get_defined_vars()) !!}

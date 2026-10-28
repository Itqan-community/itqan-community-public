# Pretty Mail (Itqan)

Admin-editable HTML email templates for Flarum 2.0, ported from
[`fof/pretty-mail`](https://github.com/FriendsOfFlarum/pretty-mail) 1.1.2.

## Why a port instead of a dependency

Upstream `fof/pretty-mail` requires `flarum/core ^1.5`. Its 2.0 blockers:

- it subclasses `Illuminate\Mail\Mailer` with a SwiftMailer constructor
  (`Swift_Mailer`), which Flarum 2.0 replaced with Symfony Mailer;
- it reads the logo through `Flarum\Api\Serializer\ForumSerializer`, which no
  longer exists;
- its `NotificationMailer` override relies on `$blueprint->getEmailView()`,
  replaced by `getEmailViews()` returning both a text and an HTML view.

So the behaviour was rebuilt against Flarum 2.0's Blade mail stack rather than
patching internals that no longer exist.

## How it works

| Flarum 2.0 seam | Use |
|---|---|
| `Extend\View::extendNamespace('mail', …)` | shadows `mail::html` (the outer shell) |
| `views/html.blade.php` | renders the stored `fof-pretty-mail.mailhtml` with `$body` |
| `views/html/notification.blade.php` | renders a stored `fof-pretty-mail.{type}` template, else core's layout |

The setting keys are **unchanged** (`fof-pretty-mail.mailhtml`, `.newPost`,
`.postMentioned`, `.userMentioned`, `.includeCSS`), so a database that already
used the 1.x extension — production does — keeps its exact emails with no
migration. `migrations/2026_10_28_000000_seed_templates.php` only fills gaps on
a fresh install, using the templates this forum actually shipped in production.

## Template variables

Same as 1.x, plus the ones Flarum 2.0 provides:

| Variable | Meaning |
|---|---|
| `$body` | the rendered message (outer template only) |
| `$settings` | `SettingsRepositoryInterface` |
| `$url` | `UrlGenerator` |
| `$translator` | mail-safe translator |
| `$formatter` | mail-safe formatter |
| `$blueprint`, `$user` | available in notification templates |
| `$forumStyle` | compiled forum CSS when "inline stylesheet" is on, else `''` |

> **Trust model:** templates are admin-authored and rendered as Blade (PHP), the
> same as upstream. Only accounts with the `settings` permission can edit them.

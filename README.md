# Itqan Community (beta)

Flarum forum source for [community.itqan.dev](https://community.itqan.dev). Private for now — intended to become the public source repo once validated end-to-end (see `itqan-community/terraform/community-v2/` for the parallel validation node at `beta.community.itqan.dev`).

Seeded from `itqan-community/source/flarum/` (a point-in-time production code snapshot — see that repo's `source/README.md` for what's included/excluded) plus `itqan-community/packages/itqan-mailerlite/` (the custom MailerLite extension, referenced by `composer.json` as a local path repository).

## What's here

- `composer.json` / `composer.lock`, `extend.php`, `site.php` — Flarum bootstrap
- `vendor/` — full dependency tree, including `flarum/core` and the custom `packages/itqan-mailerlite` extension (symlinked in via the path repo)
- `public/` — built frontend assets, `index.php`

## What's not here

- `config.php` (DB credentials — never committed)
- `storage/` (cache, logs, sessions — runtime-generated)
- `public/assets/avatars/`, `public/assets/files/` (user-uploaded content/PII)

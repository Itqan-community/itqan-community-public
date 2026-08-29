<div align="center">

  # 🌙 Itqan Community Platform

  **The official open-source community platform for the [Itqan Ecosystem](https://itqan.dev)**

  [![Website](https://img.shields.io/badge/Production-community.itqan.dev-0D9488?style=for-the-badge&logo=google-chrome&logoColor=white)](https://community.itqan.dev)
  [![License](https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge)](LICENSE)
  [![Flarum Core](https://img.shields.io/badge/Flarum-v1.8-4D69FF?style=for-the-badge&logo=flarum&logoColor=white)](https://flarum.org)

</div>

---

## 📌 Overview

**Itqan Community** (`community.itqan.dev`) is the central discussion hub for developers, content managers, and users across the entire **Itqan Ecosystem** ([Itqan.dev](https://itqan.dev)):
- **[Fanar CMS](https://cms.itqan.dev)** — Granular Mushaf content management & dataset engine.
- **[Quran Apps Directory](https://quran-apps.itqan.dev)** — Discovery directory for authentic Quranic applications.
- **[Itqan Community](https://community.itqan.dev)** — Open-source forum powering developer discussions, feature proposals, and support.

This repository (`itqan-community-public`) contains the standalone open-source Flarum forum application source code and custom extensions.

---

## 📦 What's Included & Architecture

### What's here
- `composer.json` / `composer.lock`, `extend.php`, `site.php` — Flarum bootstrap.
- `packages/` — Local extension path repositories (e.g. `packages/itqan-mailerlite`, `packages/itqan-theme`).
- `public/` — Built frontend assets and entry `index.php`.
- `vendor/` — Full dependency tree, including `flarum/core` and custom extensions symlinked via path repositories.

### What's not here (Runtime & Security)
- `config.php` (DB credentials — injected at runtime / never committed).
- `storage/` (cache, logs, sessions — runtime generated).
- `public/assets/avatars/`, `public/assets/files/` (user-uploaded content / PII).

---

## 🧩 Active Extension Packages

All community contributions must be built inside modular extensions under `packages/*`:

| Package Path | Parent Epic | Purpose |
| :--- | :--- | :--- |
| `packages/itqan-theme` | [#1](https://github.com/Itqan-community/itqan-community-public/issues/1) | Branded theme, responsive categories grid, mobile lightbox, badges, dark mode. |
| `packages/itqan-typography` | [#2](https://github.com/Itqan-community/itqan-community-public/issues/2) | Arabic & Quranic typography, webfont preloading, FOUT elimination. |
| `packages/itqan-composer-tools` | [#3](https://github.com/Itqan-community/itqan-community-public/issues/3) | Live Markdown preview, rich toolbar (tables, quotes, code), mobile editor UX. |
| `packages/itqan-notifications` | [#4](https://github.com/Itqan-community/itqan-community-public/issues/4) | DND toggle, granular subscriptions, weekly email digest command. |
| `packages/itqan-developer-profile` | [#5](https://github.com/Itqan-community/itqan-community-public/issues/5) | Developer portfolio, GitHub integration, Quranic tech trophies & badges. |
| `packages/itqan-discussions` | [#6](https://github.com/Itqan-community/itqan-community-public/issues/6) | Nested reply threading, single category enforcement, calendar dates. |
| `packages/itqan-mailerlite` | N/A | MailerLite subscriber synchronization & campaign triggers. |

---

## 🚀 Quickstart for Local Development

### Step 1: Clone & Checkout
```bash
git clone https://github.com/Itqan-community/itqan-community-public.git
cd itqan-community-public
git checkout develop
```

### Step 2: Run via Docker
```bash
docker compose up -d
```
Access the local forum at `http://localhost:8080` (Admin: `admin` / `password123`).

The first run builds the PHP image, waits for MariaDB, installs Flarum and enables the bundled extensions, so give it a few minutes. Every run after that only applies pending migrations. Follow along with `docker compose logs -f web`.

The repository is bind mounted into the container, so an edit under `packages/` is served on the next request. The usual commands run inside the `web` service:

```bash
docker compose exec web php flarum migrate
docker compose exec web php flarum cache:clear
docker compose exec web php flarum extension:enable <id>
```

To start over from an empty database:

```bash
docker compose down -v && rm -rf storage config.php && docker compose up -d
```

> **`public/assets/` gets rewritten.** Flarum compiles its CSS and JS into
> `public/assets/`, which this repository tracks with the production build.
> Running locally replaces those files with your own install's. Restore them
> before committing:
>
> ```bash
> git checkout -- public/assets && git clean -fd public/assets
> ```

> **Note on the local extensions.** `packages/itqan-theme` and
> `packages/itqan-typography` are path repositories that `composer.lock` does
> not list yet, so the container cannot link them into `vendor/` and skips
> them on boot. Linking needs a full Composer resolve, which also reaches the
> private `flarum-lang-arabic` repository. Until a maintainer with access runs
> `composer update itqan/flarum-theme itqan/flarum-typography --lock`, the
> local forum runs without those two extensions. Everything else — core, the
> bundled extensions, migrations, the API — works.

---

## 🤝 Contributing
Please see our [`CONTRIBUTING.md`](./CONTRIBUTING.md) guide. All pull requests MUST target the **`develop`** branch.

---

## 📄 License
This project is licensed under the [MIT License](LICENSE).  
Powered by [Flarum](https://flarum.org) & Built with ❤️ for the [Itqan Ecosystem](https://itqan.dev).

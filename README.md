<div align="center">

  # 🌙 Itqan Community Platform (Beta)

  **The official open-source community platform for the [Itqan Ecosystem](https://itqan.dev)**

  [![Website](https://img.shields.io/badge/Production-community.itqan.dev-0D9488?style=for-the-badge&logo=google-chrome&logoColor=white)](https://community.itqan.dev)
  [![Beta Validation](https://img.shields.io/badge/Beta-beta.community.itqan.dev-8B5CF6?style=for-the-badge&logo=google-chrome&logoColor=white)](https://beta.community.itqan.dev)
  [![License](https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge)](LICENSE)
  [![Flarum Core](https://img.shields.io/badge/Flarum-v1.8-4D69FF?style=for-the-badge&logo=flarum&logoColor=white)](https://flarum.org)

</div>

---

## 📌 Overview

**Itqan Community** (`community.itqan.dev`) is the central discussion hub for developers, content managers, and users across the entire **Itqan Ecosystem** ([Itqan.dev](https://itqan.dev)):
- **[Fanar CMS](https://cms.itqan.dev)** — Granular Mushaf content management & dataset engine.
- **[Quran Apps Directory](https://quran-apps.itqan.dev)** — Discovery directory for authentic Quranic applications.
- **[Itqan Community](https://community.itqan.dev)** — Open-source forum powering developer discussions, feature proposals, and support.

This repository (`itqan-community-beta`) contains the standalone Flarum forum source code. It is designed to become the primary public open-source repository once validated end-to-end on `beta.community.itqan.dev` (see `itqan-community/terraform/community-v2/` for the parallel validation node).

Seeded from `itqan-community/source/flarum/` plus `itqan-community/packages/itqan-mailerlite/` (the custom MailerLite extension, referenced by `composer.json` as a local path repository).

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
| `packages/itqan-theme` | [#1](https://github.com/Itqan-community/itqan-community-beta/issues/1) | Branded theme, responsive categories grid, mobile lightbox, badges, dark mode. |
| `packages/itqan-typography` | [#2](https://github.com/Itqan-community/itqan-community-beta/issues/2) | Arabic & Quranic typography, webfont preloading, FOUT elimination. |
| `packages/itqan-composer-tools` | [#3](https://github.com/Itqan-community/itqan-community-beta/issues/3) | Live Markdown preview, rich toolbar (tables, quotes, code), mobile editor UX. |
| `packages/itqan-notifications` | [#4](https://github.com/Itqan-community/itqan-community-beta/issues/4) | DND toggle, granular subscriptions, weekly email digest command. |
| `packages/itqan-developer-profile` | [#5](https://github.com/Itqan-community/itqan-community-beta/issues/5) | Developer portfolio, GitHub integration, Quranic tech trophies & badges. |
| `packages/itqan-discussions` | [#6](https://github.com/Itqan-community/itqan-community-beta/issues/6) | Nested reply threading, single category enforcement, calendar dates. |
| `packages/itqan-mailerlite` | N/A | MailerLite subscriber synchronization & campaign triggers. |

---

## 🚀 Quickstart for Local Development

### Step 1: Clone & Checkout
```bash
git clone https://github.com/Itqan-community/itqan-community-beta.git
cd itqan-community-beta
git checkout develop
```

### Step 2: Run via Docker
```bash
docker compose up -d
```
Access the local forum at `http://localhost:8080` (Admin: `admin` / `password123`).

---

## 🤝 Contributing
Please see our [`CONTRIBUTING.md`](./CONTRIBUTING.md) guide. All pull requests MUST target the **`develop`** branch.

---

## 📄 License
This project is licensed under the [MIT License](LICENSE).  
Powered by [Flarum](https://flarum.org) & Built with ❤️ for the [Itqan Ecosystem](https://itqan.dev).

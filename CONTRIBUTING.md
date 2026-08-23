# Contributing to Itqan Community (Beta)

Thank you for your interest in contributing to **Itqan Community**! We welcome bug fixes, design improvements, and feature extensions that serve the Quranic tech ecosystem.

---

## 🌟 Code Serves Quran Campaign Rules (حملة كود يخدم القرآن)

All contributions follow our standard open-source campaign rules:

### 1. The 5-Step Contributor Workflow
```
1. Comment on Issue ──► 2. Maintainer Assigns ──► 3. Open PR (target develop)
                                                              │
                                                     4. Review & Merge
                                                              │
                                             5. Share Post on community.itqan.dev
```

1. **Pick an Issue:** Find an issue labeled `good first issue` or `help wanted` on [GitHub Issues](https://github.com/Itqan-community/itqan-community-beta/issues).
2. **Request Assignment:** Leave a comment on the issue. A maintainer will assign it to you before you start.
3. **Branch from `develop`:** Always branch off `develop` and name your branch descriptively (e.g. `feat/categories-grid` or `fix/font-fout`).
4. **Submit PR targeting `develop`:** Ensure all tests pass locally and follow the issue's acceptance criteria.
5. **Share Your Experience:** Once merged, share a short write-up on [community.itqan.dev](https://community.itqan.dev) to claim your campaign points and badges!

---

## 🏗️ Extension-First Contribution Rules

1. **Never edit Flarum core files directly:** All features must live inside custom packages under `packages/<extension-name>`.
2. **Assets & Builds:** Compile Mithril/JS components inside `packages/<extension-name>/js` using `npm run build` or `npm run dev`.
3. **Database Migrations:** Put migrations in `packages/<extension-name>/migrations` and run `docker compose exec web php flarum migrate`.

---

## 🤖 AI Contribution Policy

Using AI tools (ChatGPT, Copilot, Claude, Gemini) to assist your development is welcome and encouraged. However:
- **Do not open PRs you haven't read, understood, or run locally.**
- You must be able to explain how your code works and verify it on your local machine before submitting.
- A half-finished PR you understand is far more welcome than a complete one you don't.

---

## 🚀 Branching Strategy

- **`develop` (Default / Staging):** All active development and community PRs target this branch.
- **`main` (Production):** Protected branch; deployed to production upon maintainer release.

Happy Coding! 🚀

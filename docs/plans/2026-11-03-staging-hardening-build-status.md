# Staging hardening — build status

**Date:** 2026-11-03
**Branch:** `feat/staging-hardening` (cut from `origin/staging` at `2bab4e95`, the live staging commit)
**Baseline tag:** `v2-stable-1` (= `2bab4e95`) · **Latest tag:** `v2-stable-20`
**Golden rule:** nothing was pushed, no live system was touched. Production was read-only. All work is local.

## Why this run

Staging (`staging.community.itqan.dev`) is the release candidate for production. This run audited it
page by page, fixed what was broken or inconsistent, and filled the gaps that would have shipped.

## What shipped

Every row is one commit and one rollback point (`v2-stable-N`). All are on `feat/staging-hardening`.

| # | Tag | Commit | What changed | Verified by |
|---|-----|--------|--------------|-------------|
| 1 | `v2-stable-2` | `ebb4a2b3` | Section pills restored on every discussion row. Flarum 2.0 replaced the endpoint defaults with its own include list and the tags extension only added `tags` on a filtered page. Client include + server default include. | Browser: pills on homepage, tag pages and first paint |
| 2 | `v2-stable-3` | `794ce58c` | List accessibility: deleted-author avatar gets `role="img"`; disabled vote rail no longer fades the score to 4.03:1. | Lighthouse: a11y 0.92 → 0.99 |
| 3 | `v2-stable-4` | `fbc2a924` | Discussion page: flagged-post banner no longer transparent (1.15:1); reply-sort toolbar given an `article` role inside the feed; like colour lifted above AA in dark mode. | Lighthouse: a11y 0.86 → 0.94 |
| 4 | `v2-stable-5` | `7b458a56` | Light-mode muted greys darkened (3.9:1 and 2.3:1 → 5.5:1 and 4.6:1). | Lighthouse: a11y 0.92 → 0.99 |
| 5 | `v2-stable-6` | `211e682c` | Hidden-post reveal button given an accessible name in both locales. | Lighthouse: discussion page a11y 1.00 |
| 6 | `v2-stable-7` | `8705137e` | Discussion scrubber: 1-based index (`0 of N` → `1 of N`), item list no longer cached from the loading paint. | Browser: index reads `1`; count reads the 84 rendered items |
| 7 | `v2-stable-8` | `7bc57ea0` | Tag tiles: last-post footer at full strength (3.97:1 → 5.9:1); child pills 24 px tall (WCAG 2.2 target size). | Lighthouse `/tags`: a11y 0.93 → 0.98 |
| 8 | `v2-stable-9` | `522ef156` | Arabic validation messages completed (34 Laravel keys, incl. `current_password` and `password.*`). | LocaleManager diff: missing ar 35 → 1 (the remaining key belongs to an uninstalled extension) |
| 9 | `v2-stable-10` | `add27568` | Theme gets a real `ar.yml` (36 keys); PWA install prompt/menu/settings moved off hardcoded Arabic. | All 36 theme keys resolve in Arabic; source sweep finds no hardcoded Arabic outside the plural helper |
| 10 | `v2-stable-11` | `501429fa` | MailerLite admin screens translated to Arabic (84 keys). | All 84 keys resolve in Arabic |
| 11 | `v2-stable-12` | `2a03e773` | Quick-reply image button tooltip translated (was hardcoded Arabic). | Source sweep clean |
| 12 | `v2-stable-13` | `8eeee210` | Statistics load-failure message translated. | Rebuilt bundle contains the key; both locales resolve it |
| 13 | `v2-stable-14` | `a62485df` | Every modal dialog gets its visible title as its accessible name. | Injected the same module on staging: search modal labelled "بحث", next modal auto-labelled "تسجيل الدخول" |
| 14 | `v2-stable-15` | `7d925504` | Absolute-date toggle is a button (crawlable anchors); hidden posts dim to 0.7 so the author link clears AA. | Lighthouse: a11y 0.95 → 0.99; `crawlable-anchors` and `color-contrast` clean |
| 15 | `v2-stable-16` | `83beb77d` | Preconnect to MailerLite fonts/helper and Google Analytics on every page. | Rendered head: one preconnect per origin, no duplicates |
| 16 | `v2-stable-17` | `40a889ba` | Detected-language model accessor restored. The per-post language badge had never rendered and translated titles said "from [unknown language]". | Browser: accessors resolve to `ar`; 12 badges render |
| 17 | `v2-stable-18` | `eef1ee29` | Stray `v` removed from the new-post email template; migration repairs the stored setting. | Migration recorded; stored template no longer contains `<style>v` |
| 18 | `v2-stable-19` | `147a08d8` | `theme-color` follows the active scheme (browser chrome / PWA status bar). | Browser: light `#fff`, dark `hsl(0,0%,10%)` |
| 19 | `v2-stable-20` | `77f82fd5` | Visible counts included in accessible names (unread buttons, notification and flag bells). | Lighthouse: `label-content-name-mismatch` clean |
| 20 | `v2-stable-21` | — | This document + final QA pass. | See below |

## Final QA (2026-11-03, local replica, logged in as admin)

| Page | Accessibility | Best practices | SEO | Notes |
|------|---------------|----------------|-----|-------|
| Homepage | **0.99** | 1.00 | 0.83\* | 20 rows, tag pills, bells, theme switcher |
| Discussion (`/d/644`, 84 posts) | **0.99** | 1.00 | 0.83\* | hidden post, flagged banner, scrubber |
| Tags (`/tags`) | **0.98** | 1.00 | 0.83\* | 13 tiles, child pills |
| Staging (deployed, guest) | 0.92 | 1.00 | 1.00 | before this branch; the gaps above are what it reports |

\* `robots-txt` is the only SEO failure locally; staging serves `robots.txt` and scores 1.00.

Remaining audit findings on the local pages: `heading-order` only — the MailerLite footer's `<h4>`,
which is third-party embed markup stored in `custom_footer` (DB content, not this repo).

## What was not verified

- **Mobile layouts.** The in-app browser cannot emulate a phone viewport. Phone CSS was reviewed but not
  rendered. The staging pass should include a real phone check of the discussion list, composer, tab bar
  and messages page.
- **The live translation provider.** The staging call returned a real translation; the local replica has no
  provider key, so only the accessor fix (not an end-to-end translation) was exercised locally.
- **Admin screens** beyond the locale catalogue check: MailerLite/statistics pages were not clicked through
  in a browser (the local admin session is slow and the PC restarted twice during the run).
- **Email rendering.** The template fix was verified at the stored-value level, not by sending a message.
- **The scrubber repaint.** The index fix is proven (`1 of N`); the stale-count repaint could not be
  observed locally because the headless tab does not repaint the parent stream without a resize. The
  facade's count was confirmed to read the 84 rendered items.

## Decisions taken on the owner's behalf

1. **Fix in the extension/theme packages, never in `vendor/`.** Every upstream gap (ARIA roles, include
   lists, model accessors) is patched from `packages/*`.
2. **Repair data with a migration, not a manual DB edit.** The email-template typo is fixed by a migration
   so staging and production are repaired on deploy.
3. **No new packages.** The theme, the replies extension and the Arabic pack already own the surfaces.
4. **Preconnect only to origins the CSP allows and every page uses.** `googletagmanager` is left to the
   analytics extension, which already preconnects it.
5. **Keep the deployed `public/assets/*` out of commits.** The deploy deletes and rebuilds them; only the
   extension `js/dist` bundles are committed.

## Known debt / next steps

- `core.lib.dropdown.toggle_dropdown_accessible_label` still resolves to English in the compiled Arabic
  catalogue even though the pack defines it. The generic label is therefore English on some dropdowns.
  Needs a look at the catalogue merge order, not a UI patch.
- The MailerLite footer `<h4>` is the last `heading-order` failure on every page. It is DB content; either
  demote it in the embed or accept it.
- `itqan-statistics` keeps its bilingual strings as inline `isAr ? … : …` instead of the translator. It
  works, but it is not the project's locale pattern.
- Re-run the phone pass and the authenticated staging pass before the production cutover.

## Rollback

```bash
git checkout feat/staging-hardening
git log --oneline v2-stable-1..HEAD        # every step
git reset --hard v2-stable-1               # back to the live staging tree
# or any intermediate point:
git reset --hard v2-stable-10
```

Nothing was pushed. To ship, the owner pushes `feat/staging-hardening` (or merges it into `staging`) and
lets the deploy workflow run.

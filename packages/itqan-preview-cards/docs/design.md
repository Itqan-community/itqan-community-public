# Preview Cards for Discussions — Design Document

| | |
| :--- | :--- |
| **Working title** | Preview Cards for Discussions |
| **Proposed package** | `itqan/flarum-preview-cards` (`packages/itqan-preview-cards`) |
| **Proposed namespace** | `Itqan\PreviewCards\` |
| **Status** | Draft for review |
| **Target forum** | Flarum 1.8.17 (`composer.json:37`), PHP 8.3, MariaDB 11.4 (`utf8mb4`) |
| **Reference article** | [Drawing link previews that work in Arabic](https://omaralbeik.com/en/blog/link-previews-that-work-in-arabic) — Omar Albeik, 31 Aug 2026 |
| **Date** | 2026-09-18 |

---

## 1. Summary

When a discussion link from this forum is shared on X, WhatsApp, Telegram, Slack or LinkedIn, the platform fetches the URL and renders a preview. Today the forum advertises either no image, the site logo, or a manually uploaded image (see §3). This extension gives every visible discussion a generated **1200×630 PNG preview card** that carries the discussion's own title, category, author and date, typeset correctly in Arabic — the part of the problem the reference article is entirely about.

The card is served from a public route, advertised through `og:image` meta tags that are already emitted server-side by `v17development/flarum-seo`, generated on demand with a real browser text stack (Headless Chromium), cached on disk under a content-addressed URL, and always resolved to a picture: a full card, then a brand card, never a 4xx/5xx where a crawler expects an image.

The extension is deliberately backend-only in v1. No forum JavaScript, no client-side change.

---

## 2. Scope

### 2.1 In scope (v1)

- A generated OG image per discussion route: site brand wordmark or the uploaded forum logo, category pill, title, an excerpt of the first post, the author's display name with their avatar (or an initial-letter circle), the original post date, the reply count and the last reply date, all correctly ordered/shaped/measured in Arabic and in mixed Arabic–Latin text.
- An admin settings page with per-element visibility toggles, a card-specific logo upload (falling back to the forum logo, then the wordmark), background image upload/removal, element colour pickers, the card language policy, and a “clear generated cards” action that reports how many files and bytes it freed.
- Correct, complete Open Graph / Twitter meta tags: one `og:image`, absolute URL, `og:image:width/height/type/alt`, matching `twitter:image`, keep `twitter:card=summary_large_image`.
- On-demand generation + disk cache with content-addressed filenames (no stale cards after an edit).
- Pre-generation through the installed database queue (`blomstra/database-queue`, `composer.json:29`) and cache pruning.
- Layered fallbacks, permission-aware rendering (restricted discussions never leak their title to a crawler).
- Bilingual cards: Arabic and English, direction-aware layout, one template.
- Tests that mechanically verify text order/shaping (ink-profile fingerprints, §7) and meta-tag correctness.
- Admin settings: on/off, card language policy, fallback behavior, cache TTLs.

### 2.2 Out of scope (v1)

- **In-post link unfurling** (a card rendered under a pasted link inside a post, Slack-style). The article is about OG images, not in-post embeds; this is a separate feature with its own storage, crawling and SSRF surface. Recommend a separate issue if wanted later.
- Cards for index/tag/user pages (the article's per-route idea applies; discussions are the requested scope).
- **Live-but-snapshotted counts**: reply count and last reply date are on the card. They change without an edit, so they are part of the content hash: each new reply advertises a new immutable card URL and pre-warms the next render. Social caches keep showing the snapshot taken when the link was shared, which is the correct behaviour for a preview. Votes/likes stay off the card, since they change far more often than they inform a reader.
- Editing card design from the admin UI. v1 ships one designed template; customization is by code.

### 2.3 Non-goals

- Replacing `v17development/flarum-seo`. We extend it; its manually uploaded social image keeps priority over the generated card.
- Replacing `fof/share-social` (sharing buttons) or `datlechin/flarum-copy-links` (copy link). They keep working; only the image behind the shared link changes.

---

## 3. Current state of the repository

### 3.1 Issue check (2026-09-18)

All 82 issues and PRs were checked through the GitHub API. **Nothing tracks link previews, OG images, or generated cards.** The closest items:

| # | Title | Relation |
| :--- | :--- | :--- |
| 6 | `[Epic] Extension: itqan/itqan-discussions` | Discussion browsing; no preview/OG work |
| 2, 29, 33 | Typography extension (font hosting, fatal error fix) | Relevant asset source: fonts, §5.9 |
| 1 | Theme epic (dark/light, brand) | Card visual identity should follow it |
| 34 | `ci: Add continuous integration pipeline...` | Open; the golden render tests in §7 need CI |

No issue was found to close or link on this subject, and no branch or open PR touches `og:`/preview code in `packages/`.

### 3.2 What installed extensions already do

**`v17development/flarum-seo`** is the only extension that emits social meta tags. Its behavior for a discussion page (`src/Page/DiscussionPage.php`, `src/Listeners/PageListener.php`):

1. Site-wide defaults are set first (`PageListener.php:127-169`): title, description, `og:type=website`, `twitter:card` (large if `seo_twitter_card_size`), and a site image from, in order: `seo_social_media_image_path` → `logo_path` → `favicon_path`.
2. For a discussion (`DiscussionPage.php:93-169`): `og:type=article`, `og:url`, title/description from the `seo_meta` table, author JSON-LD, breadcrumbs.
3. An image is set **only** if a moderator uploaded one to the `seo_meta` row (`PageListener.php:518-523`). Otherwise the site logo/favicon stays.

Gaps this extension fills:

- No generated per-discussion image.
- No `og:image:width`, `og:image:height`, `og:image:type`, `og:image:alt`, no `twitter:image:alt`.
- The site fallback is a logo (usually square) with `twitter:card=summary_large_image` — a bad crop everywhere.

**Other relevant extensions:** `fof/share-social` (share buttons), `datlechin/flarum-copy-links` (copy URL), `fof/discussion-language` (per-discussion language, `composer.json:55`), `itqan-typography` (self-hosted Noto Sans Arabic, `packages/itqan-typography/less/forum/fonts.less`), `itqan-theme` (brand identity), `pipecraft/flarum-ext-id-slug` (numeric-id slugs).

### 3.3 Platform facts that constrain the design

| Fact | Where | Consequence |
| :--- | :--- | :--- |
| Discussion route is `/d/{id:\d+(?:-[^/]*)?}[/{near:[^/]*}]` | `vendor/flarum/core/src/Forum/routes.php:23` | Card URL can mirror `/d/...` as `/og/d/...` |
| Slug driver returns the numeric id only | `vendor/pipecraft/flarum-ext-id-slug/src/Discussion/IdSlugDriver.php:34-37` | Controller only needs the id; slug is cosmetic |
| Meta tags are injected server-side into `Document->meta` / `Document->head` before HTML is sent | `vendor/flarum/core/src/Frontend/Document.php:280-288`, `Frontend.php:61-62` | Crawlers see the tags; no JS needed |
| v17 SEO exposes `SeoProperties::setMetaPropertyTag()` / `setMetaTag()` to third-party drivers | `vendor/v17development/flarum-seo/src/SeoProperties.php:181,200` | Clean override point for `og:image` |
| v17 SEO drivers for the same route run in registration order, and extensions are topologically boot-ordered from `composer.json` `require` | `src/Page/PageManager.php:36-51`, `src/Extend/SEO.php:49-56`, `vendor/flarum/core/src/Extension/Extension.php:240-257`, `ExtensionManager.php:512-580` | Declaring `v17development/flarum-seo` as a dependency guarantees our driver runs after theirs and wins |
| Locale comes from user preference or `locale` cookie, not `?lang=` | `vendor/flarum/core/src/Http/Middleware/SetLocale.php:29-41` | Crawlers get the default locale; card language must come from the discussion, not the request |
| Production image has PHP-GD only — no Imagick, no Node, no Chromium | `.docker/Dockerfile:4-17` | A renderer must be added to the image (§5.7) |
| Local dev machine has Chromium, Node 26 and `pango-view` | verified in this environment | Local golden tests possible before Docker changes |
| Queue backend available | `blomstra/database-queue`, `composer.json:29` | Pre-generation and pruning jobs |
| Font assets already in-repo: `NotoSansArabic-{arabic,latin,latin-ext}.woff2` + OFL | `packages/itqan-typography/assets/fonts/`, `less/forum/fonts.less:15-43` | Reuse in card template with the same `unicode-range` split |

---

## 4. What the article changes about our approach

The article is a field report from building per-route OG images in two very different ways. Every hard-won lesson maps directly onto this extension.

### 4.1 The eight rules, mapped

| Article rule | How we apply it |
| :--- | :--- |
| One card per route, `og:image` absolute, with width, height, alt and `twitter:card` | Card URL built from the forum base URL; our SEO driver adds the missing four tags (§5.3) |
| Build time unless the card's content can change after deploy | A discussion changes after deploy, so: **generate on demand, cache under a content hash, pre-warm via queue** (§5.5, §5.6) |
| Load fonts as bytes you control; know which container can read them | Fonts ship inside the extension, embedded as `data:` URLs in the card HTML; no network fetch during render (§5.9) |
| Decide the order of preference for what gets dropped before you need it | Fixed drop order: type size, then description lines, then title ellipsis — implemented inside the page before screenshot (§5.8) |
| Every path has to end in a picture; a 500 means no image | Fallback layers and short-TTL provisional responses; renderer failures never surface as 5xx (§5.12) |
| For RTL: order, face selection and measurement are three separate problems | Choosing a real browser removes all three at once; if we ever drop to Pango, the same three must still be tested separately (§5.7) |
| Verify the ordering mechanically; you cannot see it | Ink-profile fingerprint tests over rendered PNGs, using the article's method, against checked-in browser references (§7) |
| The ordinary mistakes (client-side tags, relative URL, WebP, duplicate tags, bot protection, cache, query strings, reserved slug, advertised-but-missing) | Pre-empted item by item in §4.3 |

### 4.2 The three RTL problems, and why our renderer choice is the whole answer

The article's central finding: **word order** (bidi), **face selection** (per-glyph fallback) and **shaping + measurement** (joined forms are narrower than isolated ones) fail independently, and pure Arabic hides all three.

- Satori gets word order wrong (logical order, LTR) and sizing-from-isolated-advances wrong; resvg implements no bidi at all and no fallback.
- Their build-time answer is bidi-js run reordering + HarfBuzz shaping + glyph outlines (`<path>`) into resvg, plus a generated per-word metrics table.
- Their edge answer is satori + `row-reverse`, with the explicit warning: reverse **directional runs, not whitespace tokens**, or `متجر Google Play` becomes `Play Google`.

Our position: **do not reimplement any of this in PHP.** A browser (Headless Chromium) runs the same text engine that drew every page in the article's screenshots: Unicode bidi, per-glyph font fallback, and real advance measurement of joined glyphs. The article's own conclusion is "a browser does all of it for you, everywhere except the one image you ship that no browser renders" — so we let a browser render that one image. The three problems stop being implementation tasks and become **test obligations** (§7): the tests must still prove order, face and width, because a bug in our template or font setup would reintroduce them.

If Chromium cannot be shipped to production (§5.7, option B), the Pango path has the same correct text stack, and the three problems become configuration (fontconfig stack) rather than code. Pure PHP GD is rejected: it has none of the three, and the only "fix", pre-shaping, is the article's named trap that silently produces different words.

### 4.3 The pitfalls list, translated to this repo

| Article pitfall | Our defense |
| :--- | :--- |
| Meta tags injected on the client | Already server-side (`Document.php:280-288`); we add ours through v17's driver, which writes `head` before HTML is sent |
| Relative `og:image` | Driver builds the absolute URL via `UrlGenerator` |
| WebP/AVIF | PNG only; `og:image:type=image/png` |
| Two `og:image` tags | We **replace** v17's value through `SeoProperties::setImage()`; a test asserts exactly one `og:image` and one `twitter:image` in the HTML |
| Bot protection | The card route is public, unauthenticated and not under Flarum's API throttling; add a light per-IP render limit that serves the brand card instead of blocking (§5.11) |
| "A cache, not a bug" | Documented ops note: re-scrape with the platform debugger; our own URL changes on edit so our cache never explains a stale preview |
| Query string in the image path | Version lives in the **path**, never as `?v=` (`/og/d/{id}-{hash}.png`) |
| Reserved slug shadowing | Our prefix is `/og/`; Flarum's route space here is `/d/`, `/t/`, `/u/` — no collision, but the route is registered before any wildcard later added |
| Advertising a card never drawn | Every discussion that emits `og:image` also has a guaranteed fallback chain ending in the brand card (§5.12); a scheduled command checks generated files against the cache index |
| Reversing strings / presentation forms as a "fix" | No text transformation anywhere in PHP. The raw logical string goes into HTML; Chromium resolves it. This is a hard rule in code review |
| `text-align: right` = RTL | Layout mirrors from `dir` plus CSS logical properties (`padding-inline`, `margin-inline-start`, `text-align: start`), not from alignment hacks |
| Letter-spacing, uppercase, small caps for Arabic | Banned in the card stylesheet; the Arabic "kicker" is set at a balanced size instead |
| Testing with Arabic alone | Mandatory mixed-script fixtures; the article's test string is the seed fixture (§7) |

### 4.4 Arabic-specific layout rules the card must obey

- **Cursive widths**: never size blocks from character counts. The article's example — `المرصد الأورومتوسطي لرصد الزلازل` next to `EMSC` — is the poster child. Type size is chosen from measured overflow in the page, not from `mb_strlen`.
- **Arabic sets smaller and looser**: article numbers 58/52/46 px vs 64/57/50 px for the title, leading 1.52 vs 1.22. Start from those values and, critically for us, **always set an explicit `line-height`**: Noto Sans Arabic's natural line box is 2.112em (`itqan-typography/less/forum/fonts.less:48-53`), which would otherwise open huge gaps.
- **Neutrals**: digits, colons, and brackets attached to Arabic words must be placed and mirrored by the bidi algorithm — `جو (جولانج)` came out with brackets pointing outward in satori. Fixture-tested, no manual handling.
- **No pre-shaping, ever**: the `U+FE70–FEFF` presentation-forms block must not appear in our data or templates. The article's `التجاري → التجاير` disaster is the regression this rule prevents.

---

## 5. Architecture

### 5.1 Components

```
┌─────────────────────────┐        ┌──────────────────────────────────┐
│ forum.request           │        │ crawler GET /og/d/123-hash.png   │
│ route: discussion       │        │ route: itqan-preview-cards.image │
└───────────┬─────────────┘        └───────────────┬──────────────────┘
            │                                      │
┌───────────▼─────────────┐            ┌───────────▼─────────────┐
│ DiscussionCardDriver    │            │ CardController          │
│ (v17 SEO PageDriver)    │            │ - visibility check      │
│ - skips if manual image │            │ - cache hit?  serve     │
│ - sets og/twitter tags  │            │ - miss: lock, render,   │
└───────────┬─────────────┘            │   cache, serve          │
            │                          │ - failure: brand card   │
            │  builds URL              └───────────┬─────────────┘
            │                                      │
┌───────────▼─────────────┐            ┌───────────▼─────────────┐
│ CardUrlBuilder           │            │ CardRenderer (interface)│
│ - id, locale, hash       │            │  ChromiumRenderer |     │
└─────────────────────────┘            │  PangoRenderer          │
                                       └───────────┬─────────────┘
┌─────────────────────────┐            ┌───────────▼─────────────┐
│ CardData (DTO)           │──────────►│ CardTemplate (HTML/CSS) │
│ title, excerpt, author,  │            │ + fit script in page    │
│ tag, date, dir, lang     │            └─────────────────────────┘
└─────────────────────────┘
┌─────────────────────────┐   ┌──────────────────────────────┐
│ GenerateCardJob (queue) │   │ CardCache (storage disk,     │
│ PruneCardsCommand       │   │ flock, atomic writes)        │
└─────────────────────────┘   └──────────────────────────────┘
```

Proposed file layout (mirrors `itqan-discussions` conventions):

```
packages/itqan-preview-cards/
├── composer.json                  # requires flarum/core ^1.8, v17development/flarum-seo *
├── extend.php
├── locale/en.yml, locale/ar.yml
├── assets/
│   ├── fonts/…                    # woff2 subsets + OFL.txt (copied from itqan-typography)
│   └── fallback/brand-ar.png, brand-en.png
├── src/
│   ├── Api/Serializers/…          # none in v1
│   ├── Card/CardData.php
│   ├── Card/CardUrlBuilder.php
│   ├── Card/DiscussionCardFactory.php
│   ├── Cache/CardCache.php
│   ├── Console/PruneCardsCommand.php
│   ├── Console/WarmCardsCommand.php
│   ├── Controller/CardController.php
│   ├── Job/GenerateCardJob.php
│   ├── Listener/DiscussionListener.php
│   ├── Render/CardRenderer.php          # interface
│   ├── Render/ChromiumRenderer.php
│   ├── Render/PangoRenderer.php         # optional, only if Chromium is not shipped
│   ├── Render/CardTemplate.php
│   └── Seo/DiscussionCardDriver.php     # v17 driver
└── tests/
    ├── unit/…
    └── render/…                         # golden PNG + fingerprint fixtures
```

### 5.2 Request flow

**Crawler hits the discussion page** (`GET /d/123-hello`):

1. `v17development/flarum-seo` sets site defaults and runs its `discussion` driver (title, description, URL, JSON-LD).
2. Our `DiscussionCardDriver` runs after it, and:
   - returns early if the extension is disabled, or if a manual `seo_meta.open_graph_image` exists (manual override wins), or if v17 itself skipped this page (the `seo_post_crawler` early-return at `DiscussionPage.php:115-120` is mirrored);
   - builds the content hash from the current card data + template/font version;
   - sets `og:image` to the absolute `/og/d/{id}-{hash}.png` and adds `og:image:width/height/type/alt`, `twitter:image`, `twitter:image:alt`. `twitter:card` is left as configured (must be `summary_large_image`; an admin notice is shown otherwise).
3. Optionally, if no cached file exists for the hash, the driver dispatches `GenerateCardJob` — no rendering in the page request.

**Crawler hits the card** (`GET /og/d/123-ab12cd34ef56.png`):

1. Controller resolves the guest actor, loads the discussion through `whereVisibleTo($actor)`.
2. If not visible (hidden tag, deleted, moderation), serve the brand card with `Cache-Control: public, max-age=300`.
3. If visible and a file exists for the hash: `200`, `Content-Type: image/png`, `Cache-Control: public, max-age=31536000, immutable`, `ETag`, `Last-Modified`; answer conditional requests with `304`.
4. If visible and no file yet: acquire a non-blocking `flock` on the hash.
   - Lock acquired: render (bounded by a timeout), write atomically, serve.
   - Lock busy: serve the brand card with short TTL (`max-age=60`) so the crawler gets a picture now; the worker producing the real card is already running.
5. Renders are queued and retried on worker failure; the controller never waits longer than the render timeout.

### 5.3 Meta tag integration (code sketch)

`extend.php`:

```php
use Flarum\Extend;
use Itqan\PreviewCards\Controller\CardController;
use Itqan\PreviewCards\Seo\DiscussionCardDriver;
use V17Development\FlarumSeo\Extend\SEO;

return [
    (new Extend\Routes('forum'))
        // /og/d/{id}-{hash}.png; hash validated in the controller (§5.4)
        ->get('/og/d/{id:\d+}-{hash:[0-9a-f]+}.png', 'itqan-preview-cards.image', CardController::class),

    (new SEO())
        ->addExtender('itqan_preview_cards', DiscussionCardDriver::class),
];
```

Driver (abridged; the real one also handles the settings, manual image and `seo_post_crawler` checks):

```php
final class DiscussionCardDriver implements PageDriverInterface
{
    public function extensionDependencies(): array
    {
        return ['v17development-seo'];
    }

    public function handleRoutes(): array
    {
        return ['discussion'];
    }

    public function handle(ServerRequestInterface $request, SeoProperties $properties): void
    {
        $id = (int) Arr::get($request->getQueryParams(), 'id');
        $discussion = $this->discussions->findOrFail($id);

        if ($this->hasManualImage($discussion) || ! $this->settings->cardsEnabled()) {
            return; // v17's site image or the manual image stays
        }

        $data = $this->cardFactory->forDiscussion($discussion, $this->localePolicy->for($discussion));
        $url  = $this->urlBuilder->absolute($data);          // absolute, hash in path
        $alt  = $this->cardFactory->altText($data, $discussion);

        $properties
            ->setImage($url)
            ->setMetaPropertyTag('og:image:width', 1200)
            ->setMetaPropertyTag('og:image:height', 630)
            ->setMetaPropertyTag('og:image:type', 'image/png')
            ->setMetaPropertyTag('og:image:alt', $alt)
            ->setMetaTag('twitter:image', $url)
            ->setMetaTag('twitter:image:alt', $alt);

        if (! $this->cache->has($data)) {
            $this->queue->push(new GenerateCardJob($discussion->id, $data->locale));
        }
    }
}
```

Why this integration point and not a second `content()` callback: `SeoProperties` is the extension's public API, it replaces the value instead of appending a second tag, and the extension dependency guarantees ordering (facts in §3.3). `setImage()` also updates the JSON-LD `image`, which is a bonus.

`composer.json` must contain (dependency = guaranteed boot order + enable validation):

```json
"require": {
    "flarum/core": "^1.8.0",
    "v17development/flarum-seo": "*"
}
```

### 5.4 Routes and URLs

| | |
| :--- | :--- |
| Page route (unchanged) | `/d/{id}-{slug}` |
| Card route | `/og/d/{id:\d+}-{hash:[0-9a-f]+}.png` (hash length validated in the controller) |
| Route name | `itqan-preview-cards.image` |
| Cache-busting | content hash in path (recommended, below) |

Two cache-busting options, recommendation and fallback:

1. **Recommended: content hash in the path** — `/og/d/123-ab12cd34ef56.png`, where `hash = substr(sha256(id|title|excerpt|author|avatar_digest|tag|created|reply_count|last_reply|locale|brand|accent|template_version), 0, 12)` plus the font fingerprint. The hash sits where the canonical slug sits in `/d/123-{slug}`, so no query string appears anywhere (the article's query-string warning), URLs are immutable, no CDN purge and no platform re-scrape after an edit: the page simply advertises a new URL. Old hashes are pruned.
2. Fallback if a human-readable slug is preferred in the path: `/og/d/{id}-{slug}.png` + `ETag`/`Last-Modified` + a purge call on edit. Weaker: social caches are long-lived and the article's "cache, not a bug" note applies.

The controller trusts `{id}` for the discussion lookup and `{hash}` for the cache lookup; the slug in option 2 is cosmetic. A mismatched or obsolete hash still renders the current card (never 404 because of a stale link).

### 5.5 Storage and cache

- Root: `storage/preview-cards/{discussion_id}/{hash}.png` plus `…/{hash}.json` (metadata: generated_at, bytes, exit code, duration, template/font versions). Under `storage/`, not `public/`, so titles of restricted discussions are never statically reachable.
- Served only through the controller, which enforces visibility. Optional `X-Sendfile`/`X-Accel-Redirect` fast path if the web server supports it (documented but off by default).
- Atomic write: render to `{hash}.png.tmp.{pid}`, `fsync`, `rename()` — a crawler never sees a half-written PNG.
- Locking: non-blocking `flock` per hash; `flock` on a global counter to cap concurrent renders (default 2; Chromium is memory-hungry on Apache prefork).
- Brand fallbacks are static files committed with the extension and never expire.
- Pruning: `php flarum itqan-cards:prune --days=30` deletes files not referenced by any current hash; scheduled daily. `itqan-cards:warm` pre-generates cards for a range or for recently active discussions.

### 5.6 Invalidation strategy

Content-addressed URLs make invalidation a non-problem for *our* cache: any change to the inputs changes the hash, so a new card is rendered on next demand (or by the queued job the driver pushes).

Listeners that enqueue a warm job when the hash may change:

| Event | Why |
| :--- | :--- |
| `Flarum\Discussion\Event\Renamed` | title |
| `Flarum\Post\Event\Posted` / `Revised` / `Deleted` / `Hidden` / `Restored` | excerpt, reply count, last reply date |
| `Flarum\Discussion\Event\Deleted` | remove files + stop advertising |
| `Flarum\Tags\Event\…` permission changes | only affects visibility, not the hash; the controller re-checks visibility on every request |
| Avatar changes | not listened to; the next page view computes a new hash (avatar digest is an input) and queues the render |

The SEO driver computes the hash from the same `CardData` object the renderer uses, so meta URL and file hash cannot disagree.

### 5.7 Renderer choice

| Option | Bidi/order | Per-glyph fallback | Joined-glyph measurement | Full card design | Ops cost | Verdict |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Headless Chromium (CLI)** | yes | yes | yes (real layout) | HTML/CSS, unlimited | +Chromium in image (~350–450 MB), ~0.3–1.5 s cold render | **Recommended** |
| Pango + Cairo (`pango-view` or a tiny helper) | yes | yes (fontconfig stack) | yes | limited: text layer only, background composed separately | +~30 MB apt, no browser | **Fallback** if image size is unacceptable |
| PHP GD / Imagick | no | no | no | yes | none | **Rejected** — requires pre-shaping; article's `التجاري → التجاير` bug |
| Node + satori + resvg (article's edge path) | needs custom bidi + shaping code | needs per-run stack | needs generated metrics table | yes | +Node + wasm natives | Rejected: reimplements what Chromium gives free |
| External rendering service | depends | depends | depends | yes | network + another service | Deferred; viable later behind the `CardRenderer` interface |

**Recommended:** `ChromiumRenderer` behind a `CardRenderer` interface, so swapping to Pango or a service is a config change.

Command shape (all flags matter):

```bash
timeout 15s chromium \
  --headless=new \
  --no-sandbox \
  --disable-gpu \
  --disable-dev-shm-usage \
  --hide-scrollbars \
  --force-device-scale-factor=1 \
  --window-size=1200,630 \
  --virtual-time-budget=5000 \
  --run-all-compositor-stages-before-draw \
  --user-data-dir="$(mktemp -d)" \
  --screenshot=/abs/out.png \
  file:///abs/card.html
```

Operational rules:

- **Distinct `--user-data-dir` per invocation** — concurrent Chromium processes with one profile collide.
- **No network during render**: the template references only `data:` URLs (fonts) and committed files. Defense in depth: run with `--host-resolver-rules="MAP * 0.0.0.0"` so a template bug cannot fetch anything.
- **Determinism**: pin the Chromium major version in the Docker image; `--disable-lcd-text` and `--font-render-hinting=none` reduce host-specific painting differences.
- **Failure capture**: capture stderr; on non-zero exit or missing output file, log and fall back. Never return a 5xx to the crawler (§5.12).
- Pango alternative, if chosen: `pango-view --font='Noto Sans Arabic 64' --markup --wrap=word --width=1008 --align=start --background=none --foreground='#ffffff' --output=text.png card.txt`, then compose background + logo + text layer with GD (already in the image). Pango handles bidi, shaping and fontconfig fallback; the layout remains manual. This is the article's build-time pipeline expressed in CLI tools.

### 5.8 Card template and text fitting

**Anatomy (1200×630, from the Itqan reference card scaled ×0.75 from 1600×840):**

```
┌──────────────────────────────────────────────────────────────┐
│  [tag]                                       [logo 244×185]  │  logo at inline-start
│                                                   ──────     │  accent bar under logo
│                                     النص التعريفي            │  tagline, aligned to logo
│  ──────────────────────────────────────────────────────────  │
│                     عنوان النقاش في سطر واحد …               │  title (1 line, ellipsis)
│            مقتطف من المشاركة الأولى على سطرين …              │  description (2 lines)
│  ──────────────────────────────────────────────────────────  │  divider
│  (date • replies)                         (name) (avatar)    │  meta: items / author
└──────────────────────────────────────────────────────────────┘
```

The current reference (`itqan_article_card_text20_logo30_no_left_decor.html`) is 1600×840 — the same 1.905 aspect as the OG canvas — so every coordinate was multiplied by 0.75: logo box 244×185 at 29/57, accent 82×5 at 218/79, tagline at 233 (26 px, 2-line clamp), title at 294 (47 px ar / 52 px en, nowrap, ellipsis, 1035 px wide), description at 375 (28 px, 1.55 line-height, 2-line clamp, 1035 px), divider at 518, meta at bottom 40 (25 px items, 26 px author, 29 px icons, 58 px avatar with a 32 px inner circle). There is **no left decor**: the earlier chevrons, rail and diamond were removed in this iteration; the background remains a layered gradient (two radial glows plus a 120deg linear gradient) derived from the configured background colour, with the accent colour feeding the bar, divider and avatar ring.

The brand block is the uploaded card logo (`itqan-preview-cards.logo_path`) when present, otherwise the forum logo (`logo_path`), otherwise the `forum_title` wordmark (or the per-language `brand_ar` / `brand_en` override); `show_logo` and `show_brand` gate the two. A settable tagline (`tagline_ar` / `tagline_en`, falling back to the other language) is right-aligned under the accent bar and gated by `show_tagline`. The author element is the real avatar when uploaded (read from the local avatars disk and inlined as a `data:` URI), otherwise a ring with the first letter of the display name; the name sits inside the avatar, matching the reference. The reply count excludes the opening post (`comment_count - 1`); date, replies and the optional last-reply date form the inline-end meta group, separated by bullets, while the author group sits at the inline-start. A background image, if uploaded, is drawn full-bleed under a gradient derived from the configured background colour, six element colours are configurable, and every element can be toggled off from the admin page.

Layout requirements:

- Single `dir` value on `<html>`; all coordinates use logical insets (`inset-inline-start/end`) plus a few direction overrides in the meta row, so the English card is the mirror image of the Arabic card and the stylesheet is one file.
- `* { margin: 0; box-sizing: border-box }`, a fixed 1200×630 frame with absolute-positioned elements (the reference is absolute too), and overflow hidden at the frame as the final safety net.
- The title is clamped to one line and the excerpt to two. The in-page script steps the title size down through `{ar:[47,42,37], en:[52,47,42]}` while `scrollWidth > clientWidth`, so as much of the title as possible fits before the ellipsis is applied; shrinking the type before abbreviating keeps the article's keep-priority for the one line that remains.
- Fitting algorithm, run in the page before screenshot:

```js
// Runs inside card.html; --virtual-time-budget lets it finish before paint.
(async () => {
  await document.fonts.ready;
  const title = document.querySelector('.title');

  const sizes = FONT_SIZES[document.documentElement.lang]; // {ar:[47,42,37], en:[52,47,42]}

  for (const size of sizes) {
    title.style.fontSize = size + 'px';
    if (title.scrollWidth <= title.clientWidth + 1) {
      document.documentElement.dataset.fit = 'ok';
      return;
    }
  }
  document.documentElement.dataset.fit = 'overflow'; // CSS ellipsis is the net
})();
```

- If the script fails for any reason, CSS `line-clamp` and `overflow: hidden` guarantee a complete-looking card (degraded, never broken).
- Cards are **not** theme-aware: one brand design, fixed colors, independent of the reader's dark/light preference. A preview must look the same in every client.
- All text from the database is HTML-escaped (`e()` / `htmlspecialchars` with `ENT_QUOTES`) before entering the template; the template is a plain PHP heredoc, no Blade, no user-authored markup.
- Excerpt: first post's plain text (`strip_tags` of `contentHtml` after rendering), collapsed whitespace, 180 characters max, truncated on a word boundary. Markdown/mentions/emoji images are stripped; emoji codepoints are kept and rendered by the system emoji font if present (or dropped from the excerpt at build time — decide in M2; default: keep).

### 5.9 Fonts

- **Ship bytes, not families**: copy the three woff2 subsets already in `packages/itqan-typography/assets/fonts/` (plus `OFL.txt`) into this extension. The card template inlines them as base64 `data:font/woff2;base64,…` inside `@font-face` blocks, copying the exact `unicode-range` split from `itqan-typography/less/forum/fonts.less:21-42`.
- Why this exact structure matters (article §"Which face draws the glyph"): the Arabic subset cannot draw `libVLC`; the Latin subset is a separate file of the same family. `unicode-range` performs the per-codepoint face selection in Chromium; a mixed line keeps one typeface voice without manual run splitting.
- Base64 is deliberate: `file://` font fetches are subject to CORS/opaque-origin rules, and a `data:` URL cannot fail that way. Cost: a few hundred KB of extra HTML per render, irrelevant for a server-side one-shot.
- Fallback chain in CSS: `font-family: 'Noto Sans Arabic', 'Noto Sans', system-ui, sans-serif`.
- Explicit `line-height` everywhere (Noto's natural line box is 2.112em; see §4.4).
- **Font source follows `itqan-typography`**: when that extension is installed and enabled, the renderer reads its `assets/fonts` directory; otherwise it uses the copies bundled with this extension. Resolution is per face, so a single missing file falls back on its own. The fingerprint hashes the resolved bytes, so switching source with identical files does not invalidate cached cards, while a typography font update does.
- **Font version participates in the cache hash** (`sha1_file()` of each face). A font swap invalidates every card, mirroring the article's warning about stale metrics tables.
- Pango path (if chosen) needs TTF/OTF rather than woff2: add unsubsetted Noto Sans Arabic + Noto Sans TTF files under `assets/fonts/full/`. Pango cannot read WOFF2 — the article's exact warning about satori.

### 5.10 Bilingual cards and direction

Language policy, in priority order:

1. The discussion's own language when set (`fof/discussion-language` is installed; an Arabic discussion shared into an English chat should preview in Arabic). **M3 must verify how `fof/discussion-language` stores the value on the discussion model before coding this.**
2. Forum default locale (`default_locale` in production; `en` in `install.json` locally).
3. Admin setting to force one card language.

Direction is derived from the language (`ar` → `rtl`, else `ltr`) and set once on `<html dir>`. Both languages share one template; only the type scale and the mirroring differ, per the article's typography numbers in §4.4.

The alt text is generated in the card's language (e.g. «عنوان النقاش — منتدى إتقان» / "Discussion title — Itqan Community"). The final Arabic strings for labels ("نقاش", "قراءة", "دقيقة") will be reviewed against the Arabic style guide when the locale files are written in M3; until then they live in `locale/*.yml`, never hardcoded.

### 5.11 Privacy, permissions and security

- **Visibility**: every card request resolves the guest actor and loads the discussion with the same scope the forum uses (`whereVisibleTo($actor)`), so a restricted tag or hidden discussion cannot leak its title into a card. Non-visible and non-existent discussions return the same brand card with a short TTL — indistinguishable to a prober and safe to cache briefly.
- **No SSRF surface**: the renderer never fetches remote URLs. Avatars/logos are read from local storage and inlined as `data:` at template build time; if the avatar file is missing, the layout omits it (a fallback layer of its own). This is the article's "card without the remote logo" layer, applied per element.
- **No injection**: all interpolated text is escaped; the template is committed code, not user HTML.
- **Abuse/DoS**: rendering costs ~200–400 MB and up to 1.5 s. Global render concurrency cap (default 2), non-blocking locks, HTTP requests never block on rendering beyond the timeout, per-IP soft limit (e.g. 60 card requests/minute) that serves the brand card rather than an error.
- **Crawler access**: card route is outside `/api`, not CSRF-protected, no auth. If a WAF/bot challenge is later added, the article's note applies: exclude `/og/` and test with the platform's user agent.
- **Logging**: render failures log exit code, stderr tail, duration; a fallback served from the real-card path is logged at warning level because it means a crawler is about to cache a degraded image.

### 5.12 Failure and fallback layers

Article rule: every path ends in a picture; a 500 is the only way to show nothing. Our chain, in order:

1. **Full card** (all fields) — the normal case.
2. **Card without optional elements** — avatar, category, or excerpt omitted if their data/source is missing or broken. The fitting algorithm already expresses this preference.
3. **Brand card** — committed PNG for the requested direction, containing no page data at all, so no page can break it. Served for hidden/deleted discussions, renderer failures, lock contention and rate limiting.
4. **Never** a 404/500 for a known discussion; **never** cache a 5xx; brand cards served as a provisional answer carry `max-age=60` (not the immutable one year) so the real card can take over quickly.

Cache semantics summary:

| Response | Cache-Control |
| :--- | :--- |
| Real card, hash URL | `public, max-age=31536000, immutable` |
| Brand card for invisible/unknown discussion | `public, max-age=300` |
| Provisional card while a render is in flight | `public, max-age=60` |
| Any error path | never cached |

### 5.13 Admin settings

A dedicated admin page (Flarum admin JS: `js/admin.js` → `PreviewCardsPage`, `ImageUploader`) is registered through `app.extensionData.for('itqan-preview-cards').registerPage(...)`. It has five sections: element toggles, colour pickers (`ColorPreviewInput`), image uploads (card logo + background), branding and language, and maintenance (clear generated cards). Toggles, colour pickers and text fields use `ExtensionPage`'s `this.setting()` + the standard save button; uploads and cache clearing call custom admin-only API routes.

| Key | Default | Meaning |
| :--- | :--- | :--- |
| `itqan-preview-cards.enabled` | `1` | Master switch; when off, v17 behavior is untouched |
| `itqan-preview-cards.language` | `auto` | `auto` (discussion → default), `ar`, `en` |
| `itqan-preview-cards.brand_ar` | empty | Wordmark override for Arabic cards; empty uses `forum_title` |
| `itqan-preview-cards.brand_en` | empty | Wordmark override for English cards; empty uses `forum_title` |
| `itqan-preview-cards.tagline_ar` | «مجتمع العاملين على التقنيات القرآنية» | Tagline for Arabic cards; empty falls back to the English tagline |
| `itqan-preview-cards.tagline_en` | "A community of Quranic technology builders" | Tagline for English cards; empty falls back to the Arabic tagline |
| `itqan-preview-cards.show_logo` | `1` | Show the card logo, falling back to the forum logo (`logo_path`) |
| `itqan-preview-cards.show_brand` | `1` | Show the wordmark when there is no logo |
| `itqan-preview-cards.show_tagline` | `1` | Show the tagline next to the brand block |
| `itqan-preview-cards.show_tag` | `1` | Show the category tag |
| `itqan-preview-cards.show_excerpt` | `1` | Show the first-post excerpt |
| `itqan-preview-cards.show_author` | `1` | Show the author display name |
| `itqan-preview-cards.show_avatar` | `1` | Show the author avatar or initial-letter circle |
| `itqan-preview-cards.show_date` | `1` | Show the original post date |
| `itqan-preview-cards.show_replies` | `1` | Show the reply count |
| `itqan-preview-cards.show_last_reply` | `0` | Show the last reply date |
| `itqan-preview-cards.logo_path` | empty | Uploaded card logo path; takes precedence over the forum logo |
| `itqan-preview-cards.background_path` | empty | Uploaded background path on the `flarum-assets` disk |
| `itqan-preview-cards.color_background` | `#004638` | Card background; gradients and the image overlay are derived from it |
| `itqan-preview-cards.color_accent` | `#00ad83` | Accent (bar, divider, avatar ring); empty follows `theme_primary_color` |
| `itqan-preview-cards.color_title` | `#ffffff` | Title and author name colour |
| `itqan-preview-cards.color_text` | `#cbd7d4` | Excerpt and tagline colour |
| `itqan-preview-cards.color_meta` | `#d7e1de` | Dates/replies colour |
| `itqan-preview-cards.color_tag` | `#c2d2ce` | Category tag colour |
| `itqan-preview-cards.render_concurrency` | `2` | Max simultaneous Chromium processes |
| `itqan-preview-cards.provisional_ttl` | `60` | Short TTL seconds |
| `itqan-preview-cards.prune_days` | `30` | Retention for orphaned hashes |

API routes (all admin-only):

| Route | Purpose |
| :--- | :--- |
| `POST /api/itqan-preview-cards/logo` | Upload a card logo (PNG/JPEG/WebP/GIF/SVG, ≤ 2 MB), replacing the previous file |
| `DELETE /api/itqan-preview-cards/logo` | Remove the card logo and clear the setting |
| `POST /api/itqan-preview-cards/background` | Upload a background (PNG/JPEG/WebP/GIF/SVG, ≤ 5 MB), replacing the previous file |
| `DELETE /api/itqan-preview-cards/background` | Remove the background file and clear the setting |
| `POST /api/itqan-preview-cards/clear-cache` | Delete every generated card; returns `{cleared, freed}` |

Every element toggle, colour and image digest is part of the content hash, so changing the layout immediately produces new card URLs; the clear-cache action is only for reclaiming disk and does not need to be run after a change.

Not implemented in v1: per-tag inclusion filter and a custom fallback image upload. The equivalent today is the `enabled` switch plus the committed brand assets. `itqan-cards:doctor` plays the health-notice role: Chromium path and version, font fingerprint, cache size, language policy and TTLs.

---

## 6. Data model

**No new tables in v1.** The card store is the filesystem, keyed by content hash; settings live in `settings`. This is reviewable and cheap to change: if observability or multi-server support matters later, add:

```
discussion_cards
  discussion_id  BIGINT     (index)
  locale         VARCHAR(8)
  hash           CHAR(12)   (unique with discussion_id+locale)
  status         ENUM('fresh','stale','failed')
  generated_at   DATETIME
  render_ms      INT
  template_ver   VARCHAR(16)
  font_ver       CHAR(8)
```

which would make pruning a query and multi-node deployment possible. Do not add it before it earns its keep.

---

## 7. Testing and verification plan

The article's strongest claim: *bidi cannot be checked by looking; a reversed Arabic line still looks like Arabic.* Our tests must therefore be mechanical.

### 7.1 Unit (PHPUnit)

- `CardUrlBuilder`: absolute URL, hash stability for identical input, hash change on title/excerpt/locale/font-version change.
- `CardData`: excerpt extraction (HTML tags stripped, whitespace collapsed, truncation on word boundary), escaping.
- Locale/direction policy: discussion language → direction mapping, fallbacks.
- Visibility: guest cannot get a card for a restricted-tag discussion (fake repository/scope), hidden discussion → brand path.
- Meta integration (with v17 SEO enabled, integration test): exactly one `og:image`, absolute, `width/height/type/alt` present, `twitter:image` matches, manual SeoMeta image wins, disabled setting leaves v17 output untouched.
- Cache: atomic write, lock contention path returns provisional response with short TTL, 5xx never cached, prune removes only orphaned hashes.

### 7.2 Golden render tests (the important ones)

Follow the article's fingerprint method, ported to PHP/GD:

1. Fixture discussions, rendered in CI with the pinned Chromium:
   - Arabic-only title (`مناقشة حول تطوير الواجهات`).
   - Mixed Arabic–Latin–digits–punctuation, using the article's own test string:
     `ربط libVLC مباشرةً من Swift 6 — إصدار 3.0.21`.
   - Neutral/bracket mirroring: title containing `جو (جولانج)`.
   - Very long title with no good break point (type stepping must trigger).
   - Long title + long description (type shrinks through every step before a description line is dropped; description is dropped rather than overflowing).
   - Missing avatar, missing tag, empty first post.
2. For each rendered PNG, compute the column-wise ink profile and word widths (the article's `fingerprint()` function, reimplemented with GD: grayscale threshold 128, gap threshold, word widths in pixels).
3. Compare against a checked-in reference JSON generated from the same fixture and Chromium version; allow ±2 px per gap.
4. Fail the build on any mismatch. A wrong word order changes the sequence of widths; a join that fails to shape changes a word's width; a fallback face change shifts them all.

Reference regeneration is an explicit, reviewed command (`composer test:render -- --update-references`), never automatic.

### 7.3 End-to-end smoke (staging)

- `curl -A facebookexternalhit` the discussion page: assert tags, then fetch the advertised image URL and assert `\x89PNG` magic bytes and 1200×630 dimensions.
- Repeat with `WhatsApp`, `Twitterbot`, `TelegramBot` user agents (the article's bot-protection check).
- Edit the discussion title: assert the advertised hash changed and the new URL renders the new title; assert the old URL still returns a picture (current card, per §5.4).
- Restricted tag: as guest, assert the card URL serves the brand card and the HTML advertises the site fallback, not the card.
- Platform validators (Facebook Sharing Debugger, X Card Validator, LinkedIn Post Inspector): run once per card design change, not per post.

### 7.4 CI note

Repo issue #34 (no CI pipeline) is open. These render tests need Linux + Chromium; the natural home is a GitHub Actions job (ubuntu-latest, `apt-get install chromium`). Until CI exists, the render tests run via `composer test:render` locally/on staging before release. This is a dependency to track, and this extension is a good forcing function to land #34.

---

## 8. Operations

### 8.1 Docker image change

Add to `.docker/Dockerfile`:

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
        chromium fonts-noto \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
ENV CHROME_BIN=/usr/bin/chromium
```

Notes:

- Debian bookworm's `chromium` pulls a significant dependency tree; expect +350–450 MB. This is the single biggest cost of the recommended design and the main reason option B (Pango) exists.
- `--no-sandbox` is required as root in the container; the process only ever loads our own file, never a URL.
- `--disable-dev-shm-usage` avoids crashes on containers with small `/dev/shm`.
- Pin the Chromium major version by image tag or apt hold; a browser upgrade changes text metrics and breaks golden references (deliberately, via the review step in §7.2).
- Fonts are inlined, so no system Arabic font is strictly needed; `fonts-noto` is belt-and-braces for the Pango path and emoji coverage.

### 8.2 Performance

- Cold render: estimate 0.3–1.5 s per card. With cache hits this cost is paid once per content revision per discussion.
- The discussion page request only computes a hash and (optionally) queues a job — no rendering, no added latency beyond a few DB reads.
- Card requests on a cold cache are bounded by the render timeout and the concurrency lock; the worst case a crawler sees is the provisional brand card for a minute.
- Queue pre-warming on discussion save means the common case (share right after posting) is already warm.
- Pruning keeps disk bounded: one 1200×630 PNG is ~80–200 KB; 1,000 active discussions ≈ 100–200 MB worst case.

### 8.3 Monitoring

- Log every render: discussion id, hash, duration, exit code.
- Counter of fallbacks served (brand/provisional) with a warning threshold; a spike usually means the browser or a font went missing.
- Command `itqan-cards:doctor` prints: binary found, version, font files present with hashes, cache size, N latest renders, N fallbacks in 24 h.

---

## 9. Rollout milestones

| Milestone | Deliverable | Exit criteria |
| :--- | :--- | :--- |
| **M0** | This document reviewed; decisions in §10 closed | Renderer + language policy chosen |
| **M1** | Package skeleton, route, controller, brand card, v17 driver emitting all tags | E2E smoke passes for a real discussion; unit meta tests green |
| **M2** | Chromium renderer, EN template v1, cache, fallbacks, pruning command | Golden fingerprint test (EN) green; cache/lock tests green |
| **M3** | Arabic template, fonts inlined, bilingual scale, direction, language policy | Mixed-script + neutrals + long-title golden tests green; Arabic labels reviewed against the style guide |
| **M4** | Listeners, queue warm job, visibility handling, admin settings, doctor command | Edit-invalidations E2E green; restricted-tag E2E green |
| **M5** | Polish, README (Arabic), platform validator pass, Dockerfile, release | Cards unfurl correctly on X, WhatsApp, Telegram, LinkedIn, Slack |

Phase 2 candidate (separate issue): in-post link unfurling.

---

## 10. Open questions and decisions needed

1. **Renderer**: Headless Chromium (+~400 MB image, best fidelity) vs Pango (+~30 MB, less design freedom) vs external service. Recommendation: Chromium.
2. **In-post link unfurling**: confirm it is out of scope for v1.
3. **Card language**: discussion language when set, else forum default — confirm, given the forum's EN/AR mix.
4. **Manual image priority**: confirm that a moderator-set `seo_meta` image always wins over the generated card (recommendation: yes).
5. **Card contents**: category, author display name, date, reading time — anything else? No votes/reply counts (they would freeze or force per-request rendering).
6. **Package/route naming**: `itqan/flarum-preview-cards`, `/og/d/...`, route `itqan-preview-cards.image` — approve or rename.
7. **Hash-in-path URL** vs human-readable slug URL (§5.4). Recommendation: hash in path.
8. **Emoji in excerpts**: keep (system font coverage) or strip at build time? Default: keep.
9. **CI (#34)**: schedule the render-test job; this extension benefits most.

---

## Appendix A — Article checklist mapped to artifacts

| Article instruction | Artifact in this design |
| :--- | :--- |
| `og:image` absolute + width/height/alt + `twitter:card` | `DiscussionCardDriver` (§5.3); integration tests (§7.1) |
| 1200×630, 96 px safe margin | `CardTemplate` constants (§5.8) |
| Build time unless content can change | On-demand + hash cache + queue warm (§5.5, §5.6) |
| Fonts as bytes you control | Inlined woff2 subsets with `unicode-range` (§5.9) |
| Decide drop order in advance | `FONT_SIZES` + description-lines loop in card script (§5.8) |
| Every path ends in a picture | Fallback chain, short-TTL provisional, never-5xx rule (§5.12) |
| RTL: order / face / measurement | Solved by Chromium, proven by fingerprints (§4.2, §7.2) |
| Verify ordering mechanically | GD ink-profile fingerprint tests vs browser references (§7.2) |
| Test string with Latin + digits + punctuation | `ربط libVLC مباشرةً من Swift 6 — إصدار 3.0.21` fixture (§7.2) |
| No string reversal / no presentation forms / no letter-spacing | Hard review rules (§4.3) |
| One `og:image` only | Replace via `SeoProperties::setImage()`, test asserts count (§5.3, §7.1) |
| Query string in image path danger | Version in path, never `?v=` (§5.4) |
| Cache vs bug, platform re-scrape | Ops note + hash URLs (§4.3, §5.6) |
| Bot protection blocks crawlers | Public unauthenticated route, UA smoke tests (§5.11, §7.3) |
| Platform validators once per design | M5 exit criterion (§9) |
| Noto line metrics surprise | Explicit `line-height` rule (§5.9), values from `fonts.less:48-53` |
| Arabic sets smaller/looser (58/52/46, leading 1.52) | Per-locale scale in `FONT_SIZES` (§5.8, §4.4) |

## Appendix B — Repository references

| Fact | Location |
| :--- | :--- |
| Flarum core version | `composer.json:37` |
| Queue backend | `composer.json:29` |
| Discussion route pattern | `vendor/flarum/core/src/Forum/routes.php:23` |
| Meta/head rendering, server-side | `vendor/flarum/core/src/Frontend/Document.php:259-289` |
| Frontend `content()` callback order | `vendor/flarum/core/src/Frontend/Frontend.php:43-45,61-62` |
| v17 SEO site image fallback | `vendor/v17development/flarum-seo/src/Listeners/PageListener.php:127-169` |
| v17 SEO per-discussion image (manual only) | `vendor/v17development/flarum-seo/src/Listeners/PageListener.php:517-523` |
| v17 SEO discussion driver + early return | `vendor/v17development/flarum-seo/src/Page/DiscussionPage.php:115-141` |
| `SeoProperties` public API | `vendor/v17development/flarum-seo/src/SeoProperties.php:181,200,128` |
| Driver ordering / PageManager | `vendor/v17development/flarum-seo/src/Page/PageManager.php:36-51`, `src/Extend/SEO.php:49-56` |
| Dependency-based boot order | `vendor/flarum/core/src/Extension/Extension.php:240-257`, `ExtensionManager.php:512-580` |
| Locale resolution for crawlers | `vendor/flarum/core/src/Http/Middleware/SetLocale.php:29-41` |
| Numeric slug driver | `vendor/pipecraft/flarum-ext-id-slug/src/Discussion/IdSlugDriver.php:34-37` |
| Font subsets + unicode ranges | `packages/itqan-typography/less/forum/fonts.less:15-43` |
| Font vertical metrics note | `packages/itqan-typography/less/forum/fonts.less:45-58` |
| Production image capabilities | `.docker/Dockerfile:4-17` |
| Issue check source | GitHub API, 25 issues + 57 PRs, `Itqan-community/itqan-community-public` |

## Appendix C — Local build status (2026-09-18)

The v1 backend is implemented in `packages/itqan-preview-cards` and running on the local stack (`http://localhost:8080`), verified with live discussions (Arabic, English, mixed Arabic–Latin–digits–brackets).

**Working end to end**

- v17 SEO driver emits one absolute `og:image` at `/og/d/{id}-{hash}.png` plus `og:image:width/height/type/alt`, `twitter:image`, `twitter:image:alt`; test discussions produced 1200×630 PNGs that render Arabic correctly (RTL order, embedded Latin runs, mirrored brackets `جو (جولانج)`, Arabic month names, Arabic pluralization of the reply count).
- Card v2 contents: per-language brand wordmark (`مجتمع إتقان` / "Itqan Community" via the new brand settings), tag pill when the discussion has a tag, title, one excerpt line, avatar image when uploaded (local file inlined as `data:`; verified) or a colored initial-letter circle otherwise, original post date, reply count (`comment_count - 1`) and last reply date, each hidden gracefully at zero replies.
- Reply/activity invalidation: any post created, revised, deleted, hidden or restored changes the content hash; the driver detects the missing file and queues a render, and the listener warms it immediately. Verified: three replies moved the card from `3-…` to `3-3ea40ba0ff82.png`, and an avatar upload produced `3-16d793b43366.png`.
- Per-discussion language from `fof/discussion-language` changes the card locale and therefore the hash (verified: `3-0b8b5d9d3ade.png` for EN, `3-fbedeab71c91.png` for AR).
- Cache: hash-addressed file served `immutable` with `ETag` (304 verified); invisible/nonexistent discussion returns the brand card `200` with `max-age=300` and `X-Itqan-Card: brand`; render failure returns the provisional brand card with `max-age=60`; renders are serialized by `flock` with a global concurrency cap.
- Fallbacks: committed brand assets generated by `itqan-cards:brand` (`assets/fallback/brand-{ar,en}.png`, regenerated after the brand settings landed), with generated-cache and GD-solid layers behind them.
- Commands: `itqan-cards:brand`, `warm`, `prune`, `doctor` all run; `warm` rendered the existing discussions and `doctor` reports Chromium 153, font fingerprint `8796e5cc94db`, cache stats and settings.
- No-network render: Chromium runs with `--host-resolver-rules=MAP * ~NOTFOUND`, fonts are inlined `data:` URLs, avatars/logos are read from local disk, never fetched.

**Card v3 additions (same day)**

- Forum logo replaces the wordmark when `logo_path` is set (verified by uploading a logo: the card switched from text to the image; the test logo was then removed so the wordmark returned). The logo is read from the local assets disk and inlined as a `data:` URI; SVG/PNG/JPEG/GIF/WebP up to 2 MB.
- Background upload/removal through the admin-only API (`POST`/`DELETE /api/itqan-preview-cards/background`), stored on the `flarum-assets` disk and inlined into the card under a dark gradient overlay; verified visually.
- Per-element toggles (`show_logo`, `show_brand`, `show_tag`, `show_excerpt`, `show_author`, `show_avatar`, `show_date`, `show_replies`, `show_last_reply`) verified by toggling via the settings API and re-rendering: excerpt reappeared, date/avatar/last-reply disappeared, and the hash changed each time.
- Clear-cache action (`POST /api/itqan-preview-cards/clear-cache`) verified: `{"cleared":16,"freed":963753}`, brand and lock directories untouched.
- Admin JS built with `flarum-webpack-config` (`js/dist/admin.js`) and published into the admin bundle; the compiled `public/assets/admin.js` contains the page and its translations. The admin page itself was not driven headlessly because the CLI session cannot authenticate the SPA; the settings save path and every endpoint it calls were verified independently.
- Brand fallback cards regenerate with the current logo/background when `itqan-cards:brand` runs.

**Card v4 additions (same day)**

- Card-specific logo upload (`POST/DELETE /api/itqan-preview-cards/logo`, stored on `flarum-assets`): verified by uploading a logo while `logo_path` was empty — the card switched to the card logo while the forum header was untouched; removal brought the wordmark back.
- Six colour settings with `ColorPreviewInput` in the admin page (`color_background`, `color_accent`, `color_title`, `color_text`, `color_meta`, `color_tag`); the background overlay gradient, avatar letter colour and dominant text colours are derived from them (`rgba()` helper in the template). Verified with a navy/amber palette end to end; the accent stays empty by default and follows `theme_primary_color`.
- Upload/deletion code was refactored into `UploadAssetController` / `DeleteAssetController` with per-asset subclasses, so logo and background share one validated path (mime whitelist, size caps, old-file cleanup, admin-only).
- Colours, logo digest and background digest are all part of the content hash, so a colour change alone produces a new card URL. The local forum was reset to default colours with no custom logo/background after testing.

**Card v5 layout (same day)**

- Title clamped to one line, excerpt clamped to two, and the header band raised to 96 px so the logo fills it down to the separator rule (brand text bumped to 34 px to sit in the taller band). The fit script now only steps the title size down to fit the single line before ellipsis.
- Verified with a long Arabic title (`نقاش طويل جدًا…`): the title shrank and ellipsized on one line, the excerpt cut at the second line, and a test card logo filled the header band; the test logo and test discussions were removed afterwards.

**Font sourcing (same day)**

- `FontSet` now takes labelled directories in priority order (`itqan-typography` → `bundled`) and resolves each of the three woff2 faces per file. The provider checks `ExtensionManager::isEnabled('itqan-typography')` and its `getPath().'/assets/fonts'`.
- Verified in both states with `itqan-cards:doctor`: with typography disabled all faces resolve to `bundled`; with it enabled all resolve to `itqan-typography`, the fingerprint stayed `8796e5cc94db` (identical bytes, so no card invalidation), and a cleared cache re-rendered the card from the typography files. Renaming `NotoSansArabic-latin-ext.woff2` in the typography package made only that face fall back to `bundled`, confirming per-file resolution.
- `itqan-typography` is enabled on the local forum, so the forum and the cards now share one font source.

**Reference design (same day)**

- The discussion card was rebuilt to imitate the supplied `Itqan Article Card.html` reference: same alignment, content and spacing, with every coordinate scaled ×0.75 from its 1600×840 frame to the 1200×630 OG canvas. Verified side by side against a Chromium screenshot of the reference file.
- New defaults match the reference palette: background `#004638`, accent `#00ad83`, title `#ffffff`, text `#cbd7d4`, meta `#d7e1de`, tag `#c2d2ce`; the tagline defaults to «مجتمع العاملين على التقنيات القرآنية» / "A community of Quranic technology builders", and `show_last_reply` defaults to `0` so the meta row matches the reference's date • replies pair (the toggle re-adds it).
- The meta row follows the reference's physical arrangement for Arabic (author and avatar at the outer inline-start edge, name inside the avatar; date/replies group at inline-end with icons on the text's outer side) and mirrors both groups for English. The reference's own DOM order was measured with `getBoundingClientRect()` before implementing.
- Background layers, motifs, rail, diamond, divider, avatar ring and accent bar are all derived from the two colour settings (`shade()`/`rgba()` helpers), so a palette change restyles the whole decoration.
- The reference logo uploaded through the admin page (the Itqan calligraphy) is what the verification cards above use.
- Second iteration (`itqan_article_card_text20_logo30_no_left_decor.html`): +20% text sizes, +30% logo, and the left decor removed; the template was rescaled to the new coordinates and the motif CSS/HTML deleted. Verified against a Chromium screenshot of the new reference for both Arabic and English.

**Deviations from this document**

- `extensionDependencies()` must return the Flarum extension ID `v17development-seo` (the `flarum-` prefix is stripped by `Extension::nameToId`), not the Composer package name; the code sketch above has been corrected.
- Reading time and the domain line were dropped from the card in design v2 in favour of the brand wordmark, author block and reply/activity meta.
- RTL ordering of the reply cluster (icon → count → last reply) was verified mechanically with `getBoundingClientRect()` inside Chromium after a visual misread; the browser's bidi/flex ordering is correct.
- Emoji policy is "keep" in the excerpt; the image ships `fonts-noto-color-emoji`.
- There is no queue worker running locally, so invalidation/warm jobs execute through the default queue driver; production should run `php flarum queue:work` with `blomstra/database-queue`.
- The CI golden-render tests (§7.2) are not implemented yet; verification so far is the E2E smoke in §7.3 plus visual checks of AR and EN cards.

**Local Composer workaround**

`composer update` cannot resolve because the private VCS repository `flarum-lang-arabic` is no longer reachable (`Repository not found`). The package was therefore wired manually: `vendor/itqan/flarum-preview-cards` symlink, an entry appended to `vendor/composer/installed.json`, then `composer dump-autoload`. `composer.json` carries the proper path repository and `@dev` requirement for when the private repository is fixed; until then `composer.lock` and the local vendor manifest are out of sync.

**Local Composer workaround**

`composer update` cannot resolve because the private VCS repository `flarum-lang-arabic` is no longer reachable (`Repository not found`). The package was therefore wired manually: `vendor/itqan/flarum-preview-cards` symlink, an entry appended to `vendor/composer/installed.json`, then `composer dump-autoload`. `composer.json` carries the proper path repository and `@dev` requirement for when the private repository is fixed; until then `composer.lock` and the local vendor manifest are out of sync.

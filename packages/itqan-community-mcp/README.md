# Itqan Community MCP (itqan/community-mcp)

Model Context Protocol (MCP) server and LLM-friendly Markdown endpoints for
[Itqan Community](https://community.itqan.dev) — an Arabic-first forum about
Quranic technology, AI, speech recognition, Quranic data and open standards.

The extension exposes the forum as read-only context for AI agents:

- an **MCP server** speaking JSON-RPC 2.0 over stateless Streamable HTTP;
- a **Markdown endpoint** for any single discussion, including branched replies;
- an **`llms.txt`** discovery file and an agent **`SKILL.md`**.

It is read-only: no tool can post, edit, vote, delete or change settings.
Every query goes through Flarum's `whereVisibleTo($actor)`, so private tags,
hidden discussions and soft-deleted posts never leak into a result.

---

## Requirements

| Component | Version |
| --- | --- |
| PHP | 8.1+ |
| Flarum core | 1.8+ or 2.0 |

`flarum/tags` is optional. When it is missing — or installed in `vendor/` but
disabled in the admin panel — `list_tags` returns an empty taxonomy, tag
filtering matches nothing, and every other tool keeps working.

The extension is designed for community installs with optional extras —
nested/branched replies, community votes, likes, badges, discussion language and
sticky discussions. Every optional table and column is detected at runtime, so
the same code works with or without those extensions.

---

## Installation

### From this repository (path repository)

Add the package as a path repository and require it:

```json
{
  "repositories": {
    "itqan-community-mcp": {
      "type": "path",
      "url": "packages/itqan-community-mcp"
    }
  }
}
```

```bash
composer require itqan/community-mcp:@dev
php flarum extension:enable itqan/community-mcp
php flarum cache:clear
```

### From a package archive

```bash
composer require itqan/community-mcp
php flarum extension:enable itqan/community-mcp
php flarum cache:clear
```

After enabling, open **Admin → Extensions → Itqan Community MCP** to configure:

| Setting | Default | Purpose |
| --- | --- | --- |
| `itqan-community-mcp.enabled` | on | Master switch; disabling answers MCP calls with a JSON-RPC `-32000` error and HTTP 503. |
| `itqan-community-mcp.rate_limit_per_minute` | `60` | Per-IP request budget for MCP/LLM paths. Set `0` to disable limiting. |
| `itqan-community-mcp.max_search_results` | `20` | Stored search-result ceiling for the admin UI and future server-side caps. |
| `itqan-community-mcp.max_posts_per_page` | `30` | Stored posts-per-page ceiling for the admin UI and future server-side caps. |

---

## Endpoints

All API routes are relative to your forum's API base (normally `/api`).

| Method | Endpoint | Description |
| --- | --- | --- |
| `POST` | `/api/mcp` | MCP JSON-RPC 2.0 request or batch. CSRF-exempt; authenticate with a Flarum API token (`Authorization: Token <token>` or `Bearer <token>`) or use it as a guest. |
| `GET` | `/api/mcp` | Human/model-readable discovery document: server name, versions, endpoints, capabilities and tools. |
| `OPTIONS` | `/api/mcp` | CORS preflight (204). |
| `GET` | `/api/mcp/discussions/{id}.md` | One discussion rendered as Markdown. `{id}` may be a bare ID or `{id}-{slug}`. |
| `GET` | `/api/mcp/discussions/{id}` | Same Markdown without the `.md` suffix. |
| `GET` | `/api/mcp/SKILL.md` | Packaged agent skill file. |
| `GET` | `/llms.txt` | `llms.txt` discovery file, generated from live public tags and discussions and cached for one hour. |

`POST /api/mcp` accepts a single JSON-RPC message or a batch array. Notifications
(`initialize` is followed by `notifications/initialized`, and clients may send
`notifications/cancelled`) receive **HTTP 202** with an empty body. A batch that
contains only notifications also returns 202. Batches are capped at 10 messages
and request bodies at 64 KB; larger payloads are rejected with JSON-RPC
`-32600` before any tool runs.

The server negotiates MCP protocol versions `2025-06-18`, `2025-03-26` and
`2024-11-05`; any other client version falls back to `2025-03-26`. It is
**stateless**, so there is no session ID and no server-initiated SSE stream.

### Query parameters for the Markdown endpoint

| Parameter | Default | Notes |
| --- | --- | --- |
| `post_offset` (or `offset`) | `0` | Number of posts to skip. |
| `post_limit` (or `limit`) | `25` | Maximum posts to return, capped at `50`. |

A missing or invisible discussion returns `404` with a Markdown explanation.

---

## MCP primitives

### Tools (5)

| Tool | Arguments | What it returns |
| --- | --- | --- |
| `list_tags` | `include_empty?` | Primary categories (with child categories) and secondary topic tags: slug, Arabic name, discussion count and description. Call this before filtering by tag. |
| `search_discussions` | `query?`, `tag?`, `author?`, `sort?`, `limit?`, `offset?` | Discussion search across titles and bodies with tag/author filters, relevance/latest/top/newest/oldest sorting, engagement stats and a matching snippet. |
| `get_discussion` | `discussion_id`, `post_offset?`, `post_limit?` | A full thread in reading order: metadata, tags, statistics, posts with permalinks, votes/likes, and the branched reply tree (`in reply to #N by @user`, depth, sub-reply count). |
| `search_posts` | `query`, `discussion_id?`, `author?`, `limit?` | Individual comments and nested replies with permalink, parent post, votes/likes and a keyword snippet. |
| `get_user_contributions` | `username`, `limit?` | Member profile (display name, username, join date, counts, bio, groups, badges) plus recent discussions and replies. Accepts `@username`, `username`, numeric ID or profile URL. |

Every tool is annotated `readOnlyHint: true` and `openWorldHint: false`.

### Resources (2)

| URI | Contents |
| --- | --- |
| `itqan://overview` | Forum title, live public statistics, tool guide, sticky/recent discussions and citation rules. |
| `itqan://tags` | The complete category and tag taxonomy as Markdown, rendered by the same code as `list_tags`. |

`resources/templates/list` is implemented and returns an empty template list, so
clients that probe for templates complete cleanly.

### Prompt (1)

| Name | Arguments | Purpose |
| --- | --- | --- |
| `research_quranic_tech_topic` | `topic` (required), `tag` (optional) | A structured deep-research prompt: keyword expansion, tag discovery, multiple searches (Arabic and English), full thread reading, post-level search, author verification and a cited Arabic answer format. |

---

## Compatibility

### Flarum 1.x and 2.x

- Routes, middleware, CSRF exemptions and settings use only public extenders.
- Flarum 1.x has no `Extend\Routes::options()`, so `extend.php` adds the
  `OPTIONS /api/mcp` route through `OptionsRouteExtender` when the helper is
  missing; Flarum 2.x uses the native helper.
- The MCP response shape is identical on both versions.

### Nested / branched replies

The community uses branched replies (`reply_to_post_id` / `parent_id`, with
sub-reply counts and depth). The Markdown renderer keeps the tree readable by
printing the parent post number, parent author, depth and sub-reply count on
each reply. Soft-deleted placeholders are masked (`[post hidden]`,
`[محذوف]`) while remaining connected to the tree.

### Optional columns

`parent_id`, `reply_to_post_id`, `votes`, `likes_count`, `reply_count`,
`detected_lang`, `is_sticky` and user counters are used only when the column
exists. Installations without nested replies, votes or likes still work.

### Search fallback

Flarum 1.x and 2.x differ in their search layer. The native searcher is used
when available, with an Arabic-aware Eloquent fallback that normalizes
diacritics, hamza and taa-marbuta variants.

---

## Client configuration

Replace `https://community.itqan.dev` with your own forum URL if you self-host.
For authenticated access, create a Flarum API token (**Admin → Users → API
tokens** or the user's access tokens) and send it as a bearer token.

### Claude Code

```bash
claude mcp add --transport http itqan-community \
  https://community.itqan.dev/api/mcp \
  --header "Authorization: Bearer YOUR_FLARUM_TOKEN"
```

Or in `.mcp.json`:

```json
{
  "mcpServers": {
    "itqan-community": {
      "type": "http",
      "url": "https://community.itqan.dev/api/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_FLARUM_TOKEN"
      }
    }
  }
}
```

### Cursor

`~/.cursor/mcp.json` (global) or `.cursor/mcp.json` (project):

```json
{
  "mcpServers": {
    "itqan-community": {
      "url": "https://community.itqan.dev/api/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_FLARUM_TOKEN"
      }
    }
  }
}
```

### Antigravity

Add a custom remote MCP server in **Settings → MCP**:

- Name: `itqan-community`
- URL: `https://community.itqan.dev/api/mcp`
- Transport: HTTP / Streamable HTTP
- Header: `Authorization: Bearer YOUR_FLARUM_TOKEN`

If your build only accepts a JSON config, use the Cursor snippet above.

### OpenCode

`opencode.json`:

```json
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "itqan-community": {
      "type": "remote",
      "url": "https://community.itqan.dev/api/mcp",
      "enabled": true,
      "headers": {
        "Authorization": "Bearer YOUR_FLARUM_TOKEN"
      }
    }
  }
}
```

Without an MCP client, point an agent at the plain endpoints instead:

```
https://community.itqan.dev/llms.txt                 # site discovery
https://community.itqan.dev/api/mcp/SKILL.md         # agent instructions
https://community.itqan.dev/api/mcp/discussions/637.md
```

---

## Security and operations

- **Read-only by design.** No write tools exist.
- **Visibility-scoped.** All queries start from `whereVisibleTo($actor)`;
  hidden, private and unapproved content is excluded. Restricted tags are
  filtered per actor (`tag{id}.view`), and the shared tag cache is always built
  as a guest so restricted tags can never be cached into the public taxonomy.
- **Rate limited** per client IP. `CF-Connecting-IP` and `X-Forwarded-For` are
  only trusted when the request actually arrived from a loopback/private proxy
  or a published Cloudflare edge range; a direct client cannot rotate a spoofed
  header to escape its bucket. The bucket is one minute long and returns HTTP
  `429` with JSON-RPC code `-32029` and `Retry-After: 60`.
- **Bounded requests.** JSON-RPC batches are capped at
  `JsonRpcDispatcher::MAX_BATCH_SIZE` (10) messages and request bodies at
  `MAX_PAYLOAD_BYTES` (64 KB), so one HTTP request cannot amplify into hundreds
  of tool calls. Search text is truncated to 200 characters and `%`, `_`, `\`
  are stripped before `LIKE`, so wildcard or ReDoS-style input is harmless.
- **Kill switch.** Disabling the extension setting makes the MCP routes and
  `/llms.txt` return HTTP `503` with JSON-RPC code `-32000`, without
  uninstalling the extension.
- **CORS.** `POST`, `GET` and `OPTIONS` are allowed from any origin; the
  protocol and rate-limit headers are exposed to browser clients.
- **CSRF.** Only the named MCP routes are exempt; all other API routes keep
  Flarum's normal CSRF protection.
- **Caching.** The public tag hierarchy is cached for 15 minutes and `llms.txt`
  for one hour. Actor-scoped Markdown (`/api/mcp/discussions/{id}`) is
  `private, no-store` so a shared proxy can never serve one user's view to
  another. A cache outage degrades to direct queries, never to an error.

Behind a reverse proxy, make sure the proxy sets `X-Forwarded-For` (or
`CF-Connecting-IP` on Cloudflare) so rate limiting sees the real client IP, and
that it overwrites — rather than appends to — client-supplied values.

---

## Development

Build the admin bundle:

```bash
cd packages/itqan-community-mcp/js
npm install
npm run build
```

Verify PHP syntax:

```bash
find packages/itqan-community-mcp -name '*.php' -not -path '*/vendor/*' -exec php -l {} \;
```

Run the JSON-RPC smoke test against the local Docker install:

```bash
docker compose exec -T web php /var/www/html/packages/itqan-community-mcp/tests/smoke.php
```

The smoke test exercises the dispatcher (`initialize`, `ping`, `tools/list`,
`tools/call`, `resources/list`, `resources/read`, `prompts/list`, `prompts/get`,
notifications, batches, parse/invalid errors), the four controllers,
CORS/rate-limit/disable behaviour, and the full Flarum HTTP stack (routes, CSRF
exemption, middleware).

The same coverage is available as PHPUnit-style Unit and Integration suites.
PHPUnit is optional; the dependency-free runner uses the same test classes:

```bash
# Every suite (integration tests skip themselves without database access).
php packages/itqan-community-mcp/tests/run.php

# One suite, or a single test by name.
php packages/itqan-community-mcp/tests/run.php --suite=unit
php packages/itqan-community-mcp/tests/run.php --filter=Arabic

# When PHPUnit is installed in vendor/.
vendor/bin/phpunit -c packages/itqan-community-mcp/phpunit.xml
```

Inside the container the integration suite runs against the live application:

```bash
docker compose exec -T web php packages/itqan-community-mcp/tests/run.php --suite=integration
```

---

## License

MIT.

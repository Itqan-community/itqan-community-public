---
name: itqan-community-mcp
description: Search and read Itqan Community (community.itqan.dev) discussions, posts, tags, and authors through the itqan-community-mcp MCP server or the public LLM Markdown endpoints. Use for questions about Quranic technology, tajweed software, ASR, Quranic data, AI tooling, open standards, or any itqan-community-search request.
---

# Itqan Community MCP

Itqan Community (https://community.itqan.dev) is an Arabic-first forum for Quranic
technology, AI, speech recognition, Quranic data, and open standards. This skill
explains how to query it through **itqan-community-mcp** (also referred to as
`itqan-community-search`) and how to cite what you find.

## Endpoints

| Purpose | Endpoint |
| --- | --- |
| MCP server (JSON-RPC 2.0, Streamable HTTP) | `https://community.itqan.dev/api/mcp` |
| Single discussion as Markdown | `https://community.itqan.dev/api/mcp/discussions/{id}` |
| Site overview for LLMs | `https://community.itqan.dev/llms.txt` |
| Full corpus summary for LLMs | `https://community.itqan.dev/llms-full.txt` |

The MCP endpoint negotiates protocol versions `2025-06-18`, `2025-03-26`, and
`2024-11-05`; unsupported client versions fall back to `2025-03-26`.

## Available tools

- `search_discussions` — find discussions by keywords, tags, author, or date.
- `search_posts` — find individual posts (including nested replies).
- `list_tags` — list category and topic tags with slugs and descriptions.
- `get_discussion` / `get_post` — read a discussion or post with its reply tree.
- `list_recent_discussions` — browse the latest activity.

Resources and prompts are read-only and not subscribed; call `tools/list` when you
need the exact current schema and limits.

## Search strategy

1. **Use short keywords, 1–4 words.** Pass `tajweed`, `ASR`, `quranic data`, or
   `وقف وابتداء`, never a full question or a long sentence. Split a complex
   question into several keyword searches.
2. **Call `list_tags` first** when the topic maps to a category. Known slugs
   include `ai`, `asr`, `standards`, `quranic-data`, `imad`, and
   `developers-space`. Filter by tag slug to raise precision.
3. **Search Arabic and English separately.** Most discussions are Arabic, but
   project names and technical terms are often English. Try the Arabic keyword,
   then the English one, then a transliteration.
4. **Retry spelling variants when a search comes back empty.** Arabic queries are
   normalized for diacritics, hamza forms (`أ إ آ ٱ` → `ا`), `ؤ`/`ئ`/`ى`, and
   `ة`/`ه`. If results are still thin, drop the definite article `ال` or try the
   root without affixes.
5. **Prefer precision over volume.** Ask for a small page first, then paginate
   only when the first page is relevant.

## Reading discussions correctly

- Discussions support **branched/nested replies**. A reply carries
  `reply_to_post_id` and `parent_id`; read the tree as a conversation, and do not
  assume a flat chronological order.
- Community **voting** is exposed as `votes` and `likesCount`. Treat votes as a
  signal of community agreement, not proof of correctness, especially on
  religious or technical matters.
- Check `hidden_at`, `is_approved`, and author fields before quoting a post;
  hidden or unapproved content must not be presented as community consensus.
- Posts may contain code, Arabic diacritics, and right-to-left text. Preserve
  diacritics when quoting Quranic text.

## Citation rules

Always cite the discussion title, the author, and a direct permalink:

```text
https://community.itqan.dev/d/{discussion_id}/{post_number}
```

For example: “(...) — *عنوان المناقشة*، الكاتب: الاسم،
https://community.itqan.dev/d/123/4”.

- Link the exact post number, not only the discussion.
- Use the author's display name as shown by the API.
- If you summarize several posts, cite each one.
- When the API returns no author (deleted user), say so instead of inventing one.

## Rate limits and errors

- Respect `429 Too Many Requests` and the `Retry-After` header; back off instead
  of retrying in a tight loop.
- The admin can cap requests per minute, search results, and posts per page. If a
  tool returns fewer results than requested, that is the configured limit, not an
  empty corpus.
- On `404`, re-check the discussion or post ID. On `5xx`, retry once, then report
  the failure and offer the web URL `https://community.itqan.dev`.
- Never send credentials, private messages, or personal data to these endpoints.

## Example workflow

```text
1. list_tags()                                  -> confirm `asr` and `quranic-data`
2. search_discussions("ASR tajweed")            -> shortlist IDs and titles
3. get_discussion(id)                           -> read nested replies and votes
4. search_posts("الوقف والابتداء")              -> find the Arabic discussion
5. Cite each useful post with /d/{id}/{post_number}
```

If the MCP server is unavailable, fall back to
`https://community.itqan.dev/llms.txt` for orientation and
`https://community.itqan.dev/api/mcp/discussions/{id}` for a single thread in
Markdown.

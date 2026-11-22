# 0002 — A template that assembles, not a fork; who writes the post abilities

Status: **accepted** (not a fork) · **proposed** (bundle Enable Abilities for MCP, on the audit's conditions below)

## Not a fork

WordPress is building MCP itself: the Abilities API is in core, and the official MCP Adapter is the transport. A fork
would race upstream on its own feature and rebase on every release. This project is a plugin plus an image.

## Where the post-writing abilities come from

Core has none that write. Two ways:

| | Bundle **Enable Abilities for MCP** (community, GPL) | Write our own (a few abilities) |
| --- | --- | --- |
| Covers | 100+ abilities: posts, pages, media from URL, taxonomy, SEO, WooCommerce; plus an OAuth server for claude.ai | create draft, edit, submit, publish, schedule, trash, upload, list, read |
| Levels | its abilities check WordPress capabilities, object-level included, so roles limit them; the guard catches its `force_delete` | the same, written here |
| Cost | a 20k-line dependency with one maintainer, to follow | a small surface to write and maintain |
| Sign-in | solves claude.ai OAuth today | needs OAuth from elsewhere ([0003](0003-sign-in.md)) |

## Audit (2026-11-22): RISKY, usable on conditions

Pinned: v2.17.1 = git `6529de23ed2e39adc22ee85371df0659b3a0ad30`; vendor from its lock: `wp-media/mcp-oauth`
`0031bc3d1e74f07fabd09e05ec114d9fb1863b7c`, `wp-media/apply-filters-typed` `d8f5bca83e85196d1f01d92283b63ef6f886ac4d`.

**What holds:** every write checks the current user against the object (`edit_post`, `publish_post`, `delete_post`,
`upload_files`, `manage_options`), so Contributor, Author and Editor limit it as WordPress does. PKCE S256 only, exact
redirect match, single-use state and code, POST-only consent with a nonce, rotating refresh tokens with reuse detection.
No telemetry, no role changes, no code execution outside the admin-only Code Snippets abilities.

**Why RISKY:**
1. **Consent issues the token to whoever is logged in.** An administrator who clicks Allow in their own browser hands
   claude.ai admin rights; the consent screen doesn't even show which user. This breaks the levels unless something
   stops it.
2. **Version 2.17.1 is four days old**, after a run of authorization fixes (2.13.3–2.16.1). Older versions carry those
   bugs.
3. Smaller: image-from-URL has a blind SSRF residue (IPv6 / DNS rebinding toward internal HTTP on 80/443/8080);
   `get-post-meta` returns protected meta to anyone who can edit the post; the DCR rate limit is keyed on the proxy's
   address (denial of service only).

**Conditions before it ships in the image:**

| # | Condition | Who covers it |
| --- | --- | --- |
| a | Not before **2026-12-02** (14 days); re-check for a 2.17.x security release then | M1 gate |
| b | Consent can only mint a token for an **agent** user | **Agent Ready: refuse consent for any non-agent user** (new guard line, M1, with its check) |
| c | The legacy Bearer API key never generated, `ewpa_bearer_enabled` off | image default + docs |
| d | ChatGPT DCR (`ewpa_oauth_connectors_enabled`) off unless wanted; review its callback allowlist if on | image default + docs |
| e | No unfiltered HTML for agents | **already in the guard** (`unfiltered_html` stripped; checked) |
| f | `ewpa/upload-image` off, or nothing unauthenticated on 80/443/8080 in the Railway project's private network | image default: off; uploads go through the presigned upload link ability |

**Decision (proposed):** bundle 2.17.1 (or a later version re-audited) once a–f hold. If (b) can't be enforced from
outside the plugin, write our own abilities instead and take OAuth from the adapter when it releases it.

**Default if no answer:** as proposed.

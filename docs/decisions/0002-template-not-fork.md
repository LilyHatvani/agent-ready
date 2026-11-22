# 0002 — A template that assembles, not a fork; who writes the post abilities

Status: **accepted** (not a fork) · **proposed** (where post abilities come from; waits on the audit below)

## Not a fork

WordPress is building MCP itself: the Abilities API is in core, and the official MCP Adapter is the transport. A fork
would race upstream on its own feature and rebase on every release. This project is a plugin plus an image.

## Where the post-writing abilities come from

Core has none that write. Two ways:

| | Bundle **Enable Abilities for MCP** (community, GPL) | Write our own (a few abilities) |
| --- | --- | --- |
| Covers | 100+ abilities: posts, pages, media from URL, taxonomy, SEO, WooCommerce; plus an OAuth server for claude.ai | create draft, edit, submit, publish, schedule, trash, upload, list, read |
| Levels | its abilities check WordPress capabilities (`edit_post`, `publish_post`, `delete_post`), so roles limit them; the guard catches its `force_delete` | the same, written here |
| Cost | a 20k-line dependency with one maintainer, to audit and follow | a small surface to write and maintain |
| Sign-in | solves claude.ai OAuth today | needs OAuth from elsewhere ([0003](0003-sign-in.md)) |

**Recommendation:** bundle it, pinned by version and checksum, **if the security audit says SAFE**. Write our own
abilities if it says RISKY or NO, using the adapter's own create-post example as the pattern.

**Default if no answer:** follow the audit's verdict as above.

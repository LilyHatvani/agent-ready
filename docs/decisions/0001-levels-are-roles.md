# 0001 — An agent's level is its WordPress role, plus lines no role crosses

Status: **accepted** (owner, 2026-11-22: "I want the option to have the AI have full power (some people want it)")

## Decision

An agent is a WordPress user marked as an agent. Its level is its role:

| Level | Role | Why this role |
| --- | --- | --- |
| Propose (default) | Contributor | writes and edits its own drafts, submits for review (`pending`), can't publish |
| Publish | Author | publishes, schedules and edits its own posts |
| Full | Editor | everything on the site's content, everyone's posts, categories, comments |
| Off | none | no capability at all; the MCP Adapter refuses its session |

WordPress, the MCP Adapter and every ability that checks capabilities enforce the level. Nothing re-implements them.

## The guard: what holds at every level (`includes/guard.php`)

| Line | How | Why |
| --- | --- | --- |
| No administration | `user_has_cap` removes settings, plugins, themes, users, roles, import, export | an agent never changes its own level or the site's code, even if someone makes it Administrator by hand |
| No raw scripts | `unfiltered_html` removed | Editors normally post raw HTML; agent text goes through KSES |
| No permanent deletes | `pre_delete_post` / `pre_delete_attachment` refuse an agent | trash, never gone |
| Its trash waits for the owner | the same filters refuse WordPress's scheduled emptying for what an agent trashed | nothing an agent did disappears by inaction |
| A schedule is judged when it fires | `publish_future_post` (before core) puts it back to draft if the agent can't publish any more | lowering or revoking an agent stops its schedules |

**One capability is added: every agent with a role may upload media, Propose included.** Contributors can't upload in
WordPress, and a draft without its pictures isn't a draft. The trade, acknowledged: an upload has a public file
address as soon as it exists, as every WordPress upload does.

## How Full is given

Users → Agents shows the levels. Choosing Full needs a ticked box that says, in plain words, that the agent will
publish, change and trash any post without asking, and that revisions and the trash undo it.

## Verified

`tests/integration.php`, against WordPress 7.1.5: 25 checks pass; with the guard switched off, the 10 that test the
guard fail (2026-11-22).

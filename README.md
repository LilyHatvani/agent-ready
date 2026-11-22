# Agent Ready

> Working name. "Agent Ready for WordPress" is the allowed form; "WordPress" never leads a name or a slug
> ([0005](docs/decisions/0005-naming.md)).

WordPress, ready for the AI you already write with. Connect Claude or ChatGPT over MCP, give the agent a level, and
it works on your site with exactly that reach:

| Level | WordPress role | The agent can |
| --- | --- | --- |
| **Propose** (default) | Contributor | write drafts, upload pictures, submit for review. You publish. |
| **Publish** | Author | publish and schedule its own posts |
| **Full** | Editor | run the site's content, everyone's posts included |
| **Off** | none | nothing; can't even connect |

**At every level, an agent never:** changes settings, users, plugins or themes; deletes anything for good or empties the
trash; posts raw scripts. Every edit is a WordPress revision you can restore. A post an agent scheduled goes live only if
the agent can still publish when the time comes. WordPress's own roles enforce the levels; this plugin adds the lines no
role should cross ([0001](docs/decisions/0001-levels-are-roles.md)).

**Status: pre-alpha, M0.** The plugin's guard and levels work and are tested against WordPress 7.1.5 (25 checks, which
fail when the guard is switched off). The MCP Adapter is in the image. What writes posts over MCP, and how claude.ai
signs in, is the next step: [docs/ROADMAP.md](docs/ROADMAP.md).

## Run it locally

```bash
docker compose up --build        # MariaDB + WordPress on http://localhost:8080
tests/run.sh                     # the guard checks against a throwaway WordPress (needs Docker)
```

Then Users → Agents to create an agent and pick its level.

## Read first

| | |
| --- | --- |
| [docs/VISION.md](docs/VISION.md) | what and why, and what already exists |
| [docs/decisions/](docs/decisions/README.md) | every design decision |
| [docs/ROADMAP.md](docs/ROADMAP.md) | milestones with acceptance criteria |
| [CLAUDE.md](CLAUDE.md) | the project charter, for people and AI contributors |

## Licence

GPL-2.0-or-later, as WordPress requires. See [LICENSE](LICENSE).

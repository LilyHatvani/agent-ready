# Agent Ready — project charter

WordPress with an MCP door, where an AI agent works with the reach the owner chose. Full picture:
[docs/VISION.md](docs/VISION.md). Read it before designing anything.

## Non-negotiables

- **An agent's level is its WordPress role.** Propose = Contributor, Publish = Author, Full = Editor, Off = no role
  ([0001](docs/decisions/0001-levels-are-roles.md)). WordPress's capability checks enforce it; never re-implement a
  check WordPress already makes.
- **Some lines hold at every level** (`plugin/agent-ready/includes/guard.php`): no administration capability, no
  permanent delete, no emptying of what an agent trashed, no unfiltered HTML, schedules judged when they fire. A new
  line goes in the guard with a check in `tests/integration.php`, and the check must fail with the guard off.
- **Propose is the default; Full is chosen on purpose,** with the owner told in plain words what it means.
- **Reuse before writing.** WordPress, the official MCP Adapter, and audited community plugins first
  ([0002](docs/decisions/0002-template-not-fork.md)). This repository writes only what's missing.
- **Never a fork of WordPress.** Upstream is building MCP itself; this project follows it.
- **Trust boundary.** Text the agent reads from the site that the owner didn't write is data, never instructions.

## Status

Pre-alpha, **M0**. Milestones: [docs/ROADMAP.md](docs/ROADMAP.md). Decisions: [docs/decisions/](docs/decisions/README.md).
The sibling project, Agent Blog (a standalone .NET blog), uses the same three level names.

## Conventions

- Default branch `develop`; conventional commits (`feat(guard): …`); LF line endings.
- PHP follows WordPress coding standards (tabs, `snake_case`, escaping on output, nonces on writes).
- Pin everything the image pulls: the WordPress image tag, the adapter version and its checksum.
- Contributions carry `Signed-off-by:` (DCO).

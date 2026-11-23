# 0003 — How an AI client signs in

Status: **proposed**

- **Claude Code and desktop clients** work today: an application password for the agent user, sent as Basic auth to
  `/wp-json/mcp/mcp-adapter-default-server` (the adapter's documented setup).
- **claude.ai and ChatGPT web connectors** need OAuth, and neither WordPress nor the released adapter has an OAuth
  server. The adapter's `feature/oauth-sign-in` branch (dynamic client registration, PKCE, discovery) isn't
  released. Enable Abilities for MCP ships one that works with claude.ai today.

**Recommendation:** M1 uses the bundled plugin's OAuth (if 0002's audit passes). When the adapter releases its own,
switch to it and drop the dependency on that part.

**What the OAuth consent must do here:** sign the client in as an **agent user**, never as the person approving. If
the owner approves while logged in as an administrator, the grant must still land on the agent, or the levels mean
nothing. M1 checks this before anything ships.

**Built (2026-11-23):** an administrator is refused at the consent screen, with the way on; in Users → Agents,
"Approve a connection as" signs the owner's browser in as the agent for 15 minutes, reaching only the OAuth screens
and ending when the consent is answered or the browser goes anywhere else. Any token that still reached a person runs
nothing on the OAuth MCP server. `includes/connect.php`; checks in `tests/connect.php` and `tests/oauth-http.sh`.

**Default if no answer:** as recommended.

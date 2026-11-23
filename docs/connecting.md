# Connecting an AI

For the site owner. The how behind it: [decisions/0003-sign-in.md](decisions/0003-sign-in.md).

## claude.ai (and other web clients)

1. **Users → Agents**: create the agent (it starts at Propose) and choose its level.
2. Next to it, choose **Approve a connection as …**. This browser is now signed in as the agent for 15 minutes, only
   for approving; your own account is signed out here. The page shows the address to connect.
3. In claude.ai: **Settings → Connectors → Add custom connector**, paste that address.
4. claude.ai opens your site in this browser: choose **Allow**. The connection is the agent, at its level, and the
   agent's sign-in ends.
5. Sign in again with your own account.

If you choose Allow while signed in as yourself, the site refuses and says why: a connection gets the reach of whoever
approves it, so yours would act with your rights, past every level.

**Ending a connection:** set the agent to Off (every call stops at once), or remove its grant on the agent's profile,
under Application Passwords (that connection stops; others of the same agent keep working).

## Claude Code and desktop clients

On the agent's profile, make an **application password** and give the client the site's MCP address,
`/wp-json/mcp/mcp-adapter-default-server`, with the agent's username and that password.

## What an agent can't do, at any level

Change settings, users, plugins or its own level; post unfiltered HTML; delete anything for good. Everything it changes
is a revision you can restore, and what it trashed waits for you.

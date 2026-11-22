# Vision

## What this is

A WordPress you can deploy with one click, where the AI you already write with (claude.ai, ChatGPT, Claude Code) can
work on the site over MCP, with exactly the reach you gave it.

## What already exists (checked 2026-11-22)

This project is mostly assembly. Being honest about that is the point:

- **WordPress 7.1.5** has the Abilities API in core (since 6.9). Core abilities don't write posts; 7.2 adds read-only
  content queries.
- **The official MCP Adapter** (v0.7.2, 2026-11-19) turns abilities into MCP tools. It signs users in with WordPress
  auth (application passwords). OAuth, which claude.ai and ChatGPT web connectors need, is on an unreleased branch.
- **Enable Abilities for MCP** (community plugin, 2.17.1, reported ~6,000 installs) registers over 100 abilities,
  post writing included, and ships its own OAuth server for claude.ai. Its abilities check WordPress capabilities, so
  a role limits them.

What doesn't exist anywhere: **levels an owner picks, with lines no level crosses, in a one-click deploy.** WordPress
roles come close, but an Editor can delete for good, post scripts, and nothing stops a hand-made Administrator agent.
Hosting it means installing three plugins and configuring auth by hand.

## So this project is

1. **A small plugin, Agent Ready:** agent users, the level picker, and the guard (no administration, no permanent
   deletes, trash that waits for the owner, no raw scripts, schedules judged when they fire).
2. **An image and a Railway template:** WordPress + the MCP Adapter + Agent Ready (+ the abilities and OAuth piece,
   [0002](decisions/0002-template-not-fork.md) and [0003](decisions/0003-sign-in.md)), configured, so the deploy
   is the setup.

## Who it's for

People staying on WordPress who want their AI to write on their site: some with their hand on publish, some letting it
run the site. Both get the same undo.

## Not in scope

A fork of WordPress. Our own MCP transport. Anything that needs the agent to be an administrator.

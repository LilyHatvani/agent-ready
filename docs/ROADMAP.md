# Roadmap

Each milestone is **not done** until every box below it is verified against the running product.

## M0 — repository set up (2026-11-22)

- [x] Charter, vision, decisions 0001–0005.
- [x] Agent Ready plugin: agent users, Users → Agents, levels as roles, the guard.
- [x] `tests/integration.php` passes against WordPress 7.1.5 (24 checks); with the guard off, its 10 checks fail.
- [x] The must-use loader keeps the MCP Adapter on; the owner and a Propose agent open an MCP session; an Off agent
      is refused (checked on WordPress 7.1.5 with SQLite).
- [x] Dockerfile (pinned image, adapter by checksum), compose, CI.
- [ ] CI green on the first push (the Docker jobs haven't run yet: no Docker daemon where M0 was written).

## M1 — your agent writes, you publish

**User use cases:**
- [ ] As a site owner, I deploy from the Railway template and finish WordPress's install in the browser.
- [ ] As a site owner, I create an agent in Users → Agents and connect claude.ai to the site as a custom connector.
- [ ] As a site owner, Claude creates a draft with a picture and submits it; I publish it from the dashboard.

**Behaviour:**
- [ ] Post abilities exist over MCP ([0002](decisions/0002-template-not-fork.md)): create draft, edit, submit for
      review, publish, schedule, trash, upload, list, read.
- [ ] claude.ai signs in over OAuth as the agent user ([0003](decisions/0003-sign-in.md)).

**Invariants:**
- [ ] A claude.ai grant approved by an administrator still acts as the agent, at the agent's level.
- [ ] Every guard check still passes with the abilities plugin active.
- [ ] A Propose agent's publish attempt over MCP is refused with a reason naming the level.

**Edges:**
- [ ] Revoking the grant or setting Off ends the agent's next call.

## M2 — the template goes public

- [ ] Image published on release; the Railway template deploys it with MySQL and an uploads volume.
- [ ] A fresh deploy, filling nothing in, installs and connects.
- [ ] Repository public; README carries the Deploy on Railway button.

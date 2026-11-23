# Roadmap

Each milestone is **not done** until every box below it is verified against the running product.

## M0 — repository set up ✅ (2026-11-22)

- [x] Charter, vision, decisions 0001–0005.
- [x] Agent Ready plugin: agent users, Users → Agents, levels as roles, the guard.
- [x] `tests/integration.php` passes against WordPress 7.1.5 (25 checks); with the guard off, its 10 checks fail.
- [x] The must-use loader keeps the MCP Adapter on; the owner and a Propose agent open an MCP session; an Off agent
      is refused (checked on WordPress 7.1.5 with SQLite).
- [x] Dockerfile (pinned image, adapter by checksum), compose, CI.
- [x] CI green on the first push: lint, the guard checks on real WordPress 7.1.5, and the image installing with its MCP route (2026-11-22, run 1).

## M1 — your agent writes, you publish

**User use cases:**
- [ ] As a site owner, I deploy from the Railway template and finish WordPress's install in the browser.
- [ ] As a site owner, I create an agent in Users → Agents and connect claude.ai to the site as a custom connector.
- [ ] As a site owner, Claude creates a draft with a picture and submits it; I publish it from the dashboard.

**Behaviour:**
- [x] Post abilities exist over MCP ([0002](decisions/0002-template-not-fork.md)): create draft, edit, submit for
      review, publish, schedule, trash, upload (an upload link), list, read; each exercised over MCP at its level
      (`tests/connect.php`, 2026-11-23).
- [ ] claude.ai signs in over OAuth as the agent user ([0003](decisions/0003-sign-in.md)). Built and checked with real
      tokens from the token endpoint over HTTP (`tests/oauth-http.sh`); claude.ai itself waits for a public deploy.

**Invariants:**
- [x] OAuth consent mints a token only for an agent user: an administrator clicking Allow is refused, with a reason
      that says to approve as the agent (audit condition b, [0002](decisions/0002-template-not-fork.md)); a person's
      token that got through some other way runs nothing.
- [ ] The abilities plugin ships only on 0002's conditions a–f, each checked. b–f built and checked; **a** (not before
      2026-12-02, re-checked for a 2.17.x security release then) keeps it out of the image until that date.
- [x] Every guard check still passes with the abilities plugin active (`tests/run.sh` runs them both ways).
- [x] A Propose agent's publish attempt over MCP is refused with a reason naming the level.

**Edges:**
- [x] Revoking the grant or setting Off ends the agent's next call (over HTTP with a real token; Off names the level).

## M2 — the template goes public

- [ ] Image published on release; the Railway template deploys it with MySQL and an uploads volume.
- [ ] A fresh deploy, filling nothing in, installs and connects.
- [ ] Repository public; README carries the Deploy on Railway button.

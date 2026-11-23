# Releasing the image

Status: **M2, built** (2026-11-23) · `.github/workflows/release.yml`

The Railway template and self-hosters run the published image, `ghcr.io/lilyhatvani/agent-ready`, for `linux/amd64`
and `linux/arm64`: the pinned WordPress image, the MCP Adapter by checksum, Agent Ready and its must-use loader.

| You do | The workflow pushes |
| --- | --- |
| Publish a GitHub release `v1.2.3`, or push the tag (`git push origin v1.2.3`) | `:1.2.3`, `:1.2`, `:1`, `:latest` |
| A pre-release version, `v1.3.0-rc.1` | `:1.3.0-rc.1` only |
| Run *release* by hand with **push** on | `:edge`, from the branch you ran it on |
| Run *release* by hand with **push** off | nothing: both platforms build, as a check |

After a push, a second job runs the pushed image with only its database, finishes WordPress's install and checks the
MCP route answers.

**Not before 2026-12-02:** the image doesn't carry Enable Abilities for MCP (the post abilities and claude.ai's OAuth)
until audit condition (a) holds ([decisions/0002](../decisions/0002-template-not-fork.md)). A release before then is
the guard and the adapter only, which serves Claude Code and desktop clients (application passwords).

## The first release (the owner's)

1. Make the package public (GitHub → Packages → agent-ready → Package settings → Change visibility), or the repository.
2. Publish release `v0.1.0` from `develop`.
3. Build the template ([railway-template.md](railway-template.md)).

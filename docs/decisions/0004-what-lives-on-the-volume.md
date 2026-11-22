# 0004 — Code comes from the image; only uploads live on the volume

Status: **proposed**

The image carries WordPress core, the MCP Adapter, Agent Ready and the must-use loader. Only
`wp-content/uploads` is a volume; posts and settings are in the database.

- **Why:** an update is a redeploy. A pinned, checksummed stack can't drift, and the loader keeps the adapter and the
  guard on whatever the database says, so a bad click can't turn the guard off.
- **The trade, said plainly:** plugins and themes installed from the dashboard would vanish on the next deploy, so the
  image turns dashboard installs off (`DISALLOW_FILE_MODS`). The way on: add a plugin or theme to the Dockerfile in
  your copy of the template, or mount a volume on `wp-content/plugins` and accept that you update those yourself.
  Both are documented in the template's overview.
- **Default if no answer:** as above.

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
- **Salts stay put.** Only uploads are on the volume, so each deploy starts from a fresh `wp-config.php`, and the
  official image fills any salt it isn't given with a new random one: every deploy signed everyone out and broke every
  nonce (seen on the Railway deploy, 2026-11-23). The image now passes WordPress's own placeholder, so `wp_salt()`
  generates each salt once and keeps it in the database. The trade: a database copy carries the salts, as it already
  carries everything else. Real `WORDPRESS_*_KEY` / `_SALT` values still override it. CI checks that a sign-in
  survives recreating the container.
- **Default if no answer:** as above.

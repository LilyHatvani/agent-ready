#!/bin/sh
# Runs before the official WordPress entrypoint, at every container start.
set -eu

# 1. Hand the uploads volume to WordPress. Railway (and a fresh Docker volume) mounts it owned by root; Apache runs
#    PHP as www-data, so uploads would fail without this.
uploads=/var/www/html/wp-content/uploads
mkdir -p "$uploads"
chown www-data:www-data "$uploads"

# 2. Exactly one Apache MPM, prefork (mod_php needs it). On Railway mpm_event is enabled at runtime (seen in the deploy log, 2026-11-22) even though the
#    image's config is valid at build time ("AH00534: More than one MPM loaded", 2026-11-22; the same image runs in
#    CI). Whatever adds it, the enabled set is corrected here, right before Apache starts, and what was found is
#    logged so a recurrence is diagnosable from the deploy logs.
found="$(ls /etc/apache2/mods-enabled 2>/dev/null | grep '^mpm_' | tr '\n' ' ' || true)"
echo "agent-ready: MPMs enabled at start: ${found:-none}"
for m in mpm_event mpm_worker; do
  rm -f "/etc/apache2/mods-enabled/$m.load" "/etc/apache2/mods-enabled/$m.conf"
done
if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ]; then
  a2enmod -q mpm_prefork
fi
# mods-available only lists what could be enabled; everything else under /etc/apache2 is loaded.
stray="$(grep -rlE '^\s*LoadModule\s+mpm_(event|worker)_module' /etc/apache2 --exclude-dir=mods-available 2>/dev/null || true)"
if [ -n "$stray" ]; then
  echo "agent-ready: another MPM is loaded outside mods-enabled: $stray" >&2
fi

exec docker-entrypoint.sh "$@"

#!/usr/bin/env bash
# Runs the checks against a throwaway WordPress in Docker: MariaDB + the official WP-CLI image.
#  1. tests/integration.php, the guard, with Agent Ready alone.
#  2. The MCP Adapter and Enable Abilities for MCP as audited (tests/fetch-abilities.sh), activated before Agent Ready
#     as an owner who had them first would, then the guard checks again and tests/connect.php (M1).
#  3. tests/oauth-http.sh over real HTTP (PHP's built-in server): real OAuth tokens, the consent screens, the
#     owner's approval sign-in.
# Needs Docker, git and composer. Used by CI; runs the same on a laptop.
set -euo pipefail

WP_VERSION="${WP_VERSION:-7.1.5}"
CLI_IMAGE="${CLI_IMAGE:-wordpress:cli-2.12.0-php8.4}"
NET=agent-ready-test
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# The adapter as the image pins it (docker/Dockerfile).
MCP_ADAPTER_VERSION="${MCP_ADAPTER_VERSION:-0.7.2}"
MCP_ADAPTER_SHA256="${MCP_ADAPTER_SHA256:-baab734f26bd347a87947d141bb0728a8594ab7f17af03d2ae2e95e31d828b5b}"
WORK="$(mktemp -d)"

cleanup() { rm -rf "$WORK"; docker rm -f agent-ready-web >/dev/null 2>&1 || true; docker rm -f agent-ready-db >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; docker volume rm agent-ready-wp >/dev/null 2>&1 || true; }
trap cleanup EXIT
cleanup

docker network create "$NET" >/dev/null
docker run -d --name agent-ready-db --network "$NET" \
  -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wp mariadb:11 >/dev/null
for i in $(seq 1 60); do
  docker exec agent-ready-db mariadb -uroot -proot -e 'select 1' wp >/dev/null 2>&1 && break
  sleep 1
done

wp() {
  docker run --rm --network "$NET" --user 33:33 -e HOME=/tmp -e AR_STEP -e AR_AGENT -e AR_BASE \
    -v agent-ready-wp:/var/www/html \
    -v "$ROOT/plugin/agent-ready:/var/www/html/wp-content/plugins/agent-ready:ro" \
    -v "$WORK/plugins/mcp-adapter:/var/www/html/wp-content/plugins/mcp-adapter:ro" \
    -v "$WORK/plugins/enable-abilities-for-mcp:/var/www/html/wp-content/plugins/enable-abilities-for-mcp:ro" \
    -v "$ROOT/tests:/tests:ro" \
    "$CLI_IMAGE" wp "$@"
}

mkdir -p "$WORK/plugins"
curl -fsSL -o "$WORK/mcp-adapter.zip" "https://github.com/WordPress/mcp-adapter/releases/download/v${MCP_ADAPTER_VERSION}/mcp-adapter.zip"
echo "${MCP_ADAPTER_SHA256}  $WORK/mcp-adapter.zip" | sha256sum -c - >/dev/null
unzip -q "$WORK/mcp-adapter.zip" -d "$WORK/plugins"
"$ROOT/tests/fetch-abilities.sh" "$WORK/plugins" >/dev/null

docker run --rm -v agent-ready-wp:/var/www/html alpine chown -R 33:33 /var/www/html
wp core download --version="$WP_VERSION" --skip-content
wp config create --dbname=wp --dbuser=root --dbpass=root --dbhost=agent-ready-db --skip-check
wp core install --url=http://localhost --title=Test --admin_user=owner --admin_password=owner --admin_email=owner@example.invalid --skip-email
wp plugin activate agent-ready
echo "== the guard, Agent Ready alone"
wp eval-file /tests/integration.php

# An owner who had the abilities plugin first: Agent Ready arrives after it has saved its settings.
wp plugin deactivate agent-ready
wp plugin activate mcp-adapter enable-abilities-for-mcp
wp plugin activate agent-ready
echo "== the guard, with the MCP Adapter and Enable Abilities for MCP"
wp eval-file /tests/integration.php
echo "== M1: abilities over MCP, agent-only consent, the audit's settings"
wp eval-file /tests/connect.php
echo "== M1 over HTTP: real tokens, the consent screens, the approval sign-in"
docker run -d --name agent-ready-web --network "$NET" --user 33:33 -p 8099:8099 \
  -v agent-ready-wp:/var/www/html \
  -v "$ROOT/plugin/agent-ready:/var/www/html/wp-content/plugins/agent-ready:ro" \
  -v "$WORK/plugins/mcp-adapter:/var/www/html/wp-content/plugins/mcp-adapter:ro" \
  -v "$WORK/plugins/enable-abilities-for-mcp:/var/www/html/wp-content/plugins/enable-abilities-for-mcp:ro" \
  -v "$ROOT/tests:/tests:ro" \
  "$CLI_IMAGE" php -S 0.0.0.0:8099 -t /var/www/html /tests/router.php >/dev/null
for i in $(seq 1 30); do curl -fsS -o /dev/null localhost:8099/wp-login.php 2>/dev/null && break; sleep 1; done
# oauth-http.sh runs its WordPress steps through wp() above.
export -f wp; export NET ROOT WORK CLI_IMAGE
"$ROOT/tests/oauth-http.sh" http://localhost:8099 "wp eval-file" /tests/oauth-http.php

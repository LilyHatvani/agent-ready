#!/usr/bin/env bash
# Runs tests/integration.php against a throwaway WordPress in Docker: MariaDB + the official WP-CLI image.
# Needs Docker. Used by CI; runs the same on a laptop.
set -euo pipefail

WP_VERSION="${WP_VERSION:-7.1.5}"
CLI_IMAGE="${CLI_IMAGE:-wordpress:cli-2.12.0-php8.4}"
NET=agent-ready-test
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

cleanup() { docker rm -f agent-ready-db >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; docker volume rm agent-ready-wp >/dev/null 2>&1 || true; }
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
  docker run --rm --network "$NET" --user 33:33 -e HOME=/tmp \
    -v agent-ready-wp:/var/www/html \
    -v "$ROOT/plugin/agent-ready:/var/www/html/wp-content/plugins/agent-ready:ro" \
    -v "$ROOT/tests:/tests:ro" \
    "$CLI_IMAGE" wp "$@"
}

docker run --rm -v agent-ready-wp:/var/www/html alpine chown -R 33:33 /var/www/html
wp core download --version="$WP_VERSION" --skip-content
wp config create --dbname=wp --dbuser=root --dbpass=root --dbhost=agent-ready-db --skip-check
wp core install --url=http://localhost --title=Test --admin_user=owner --admin_password=owner --admin_email=owner@example.invalid --skip-email
wp plugin activate agent-ready
wp eval-file /tests/integration.php

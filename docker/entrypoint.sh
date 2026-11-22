#!/bin/sh
# Hands the uploads volume to WordPress before the official entrypoint runs. Railway (and a fresh Docker volume) mounts
# it owned by root; Apache runs PHP as www-data, so uploads would fail without this.
set -eu
uploads=/var/www/html/wp-content/uploads
mkdir -p "$uploads"
chown www-data:www-data "$uploads"
exec docker-entrypoint.sh "$@"

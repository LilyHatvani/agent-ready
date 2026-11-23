#!/usr/bin/env bash
# Fetches Enable Abilities for MCP exactly as audited (docs/decisions/0002-template-not-fork.md): its git commit, then
# its vendor from its own composer.lock, and checks all three commits. Prints the directory it built.
#   tests/fetch-abilities.sh [target-dir]
set -euo pipefail

EAM_REPO=https://github.com/FabioMontenegro/enable-abilities-for-mcp
EAM_VERSION=2.17.1
EAM_COMMIT=6529de23ed2e39adc22ee85371df0659b3a0ad30
OAUTH_COMMIT=0031bc3d1e74f07fabd09e05ec114d9fb1863b7c
FILTERS_COMMIT=d8f5bca83e85196d1f01d92283b63ef6f886ac4d

dir="${1:-$(mktemp -d)}/enable-abilities-for-mcp"
rm -rf "$dir" && mkdir -p "$dir"
git -C "$dir" init -q
git -C "$dir" fetch -q --depth 1 "$EAM_REPO" "$EAM_COMMIT"
git -C "$dir" checkout -q FETCH_HEAD
[ "$(git -C "$dir" rev-parse HEAD)" = "$EAM_COMMIT" ] || { echo "wrong commit" >&2; exit 1; }
grep -q "Version: *$EAM_VERSION" "$dir/enable-abilities-for-mcp.php" || { echo "not version $EAM_VERSION" >&2; exit 1; }

# The lock pins the vendor commits; --prefer-source clones them, so their commits can be checked too.
(cd "$dir" && composer install -q --no-dev --no-interaction --prefer-source --no-progress)
check() { [ "$(git -C "$dir/vendor/$1" rev-parse HEAD)" = "$2" ] || { echo "$1 is not at $2" >&2; exit 1; }; }
check wp-media/mcp-oauth "$OAUTH_COMMIT"
check wp-media/apply-filters-typed "$FILTERS_COMMIT"

# The audited code only: no git metadata or tests in what WordPress loads.
find "$dir" -name .git -prune -exec rm -rf {} +
rm -rf "$dir/tests" "$dir/vendor/wp-media/mcp-oauth/Tests"
echo "$dir"

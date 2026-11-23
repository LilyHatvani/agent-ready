#!/usr/bin/env bash
# M1 over real HTTP, against a running WordPress with the adapter, the abilities plugin and Agent Ready:
#   tests/oauth-http.sh <base url> <command that runs a PHP file inside WordPress, given the file last> [that file's path]
# The command gets AR_STEP, AR_AGENT and AR_BASE in its environment; the path defaults to tests/oauth-http.php here.
# Real tokens from the OAuth server's token endpoint, on its MCP route: an agent's works within its level, a person's
# does nothing, Off and a revoked grant end the next call. Then the consent screens: an administrator is refused with
# the reason, and "Approve a connection as" gives a sign-in that reaches only the OAuth screens.
set -euo pipefail
BASE="$1"; RUN_PHP="$2"
HERE="$(cd "$(dirname "$0")" && pwd)"
STEP_FILE="${3:-$HERE/oauth-http.php}"
failures=0
check() { if [ "$2" = 1 ]; then echo "PASS $1"; else echo "FAIL $1"; echo "     $3"; failures=$((failures + 1)); fi; }
has() { case "$1" in *"$2"*) echo 1 ;; *) echo 0 ;; esac; }
lacks() { case "$1" in *"$2"*) echo 0 ;; *) echo 1 ;; esac; }
step() { AR_STEP="$1" AR_AGENT="${AGENT:-}" AR_BASE="$BASE" $RUN_PHP "$STEP_FILE"; }

eval "$(step seed | grep -E '^[A-Z_]+=')"

token() {
  curl -s -X POST "$BASE/oauth/token" -d grant_type=authorization_code -d "code=ar$1" -d "code_verifier=$VERIFIER" \
    -d redirect_uri=https://claude.ai/api/mcp/auth_callback -d client_id=https://claude.ai/oauth/mcp-oauth-client-metadata |
    php -r 'echo json_decode(stream_get_contents(STDIN))->access_token ?? "";'
}
mcp() { curl -s -D - -X POST "$BASE/wp-json/mcp/mcp-oauth-server" -H "Authorization: Bearer $1" -H 'content-type: application/json' \
  -H 'accept: application/json, text/event-stream' ${3:+-H "mcp-session-id: $3"} -d "$2"; }
INIT='{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"check","version":"1"}}}'
session() { mcp "$1" "$INIT" | tr -d '\r' | awk 'tolower($1) == "mcp-session-id:" { print $2 }'; }
post() { echo "{\"jsonrpc\":\"2.0\",\"id\":2,\"method\":\"tools/call\",\"params\":{\"name\":\"mcp-adapter-execute-ability\",\"arguments\":{\"ability_name\":\"ewpa/create-post\",\"parameters\":{\"title\":\"$1\",\"content\":\"x\",\"status\":\"$2\"}}}}"; }

AGENT_TOKEN="$(token agent)"; OWNER_TOKEN="$(token owner)"
check "the token endpoint issues tokens" "$([ -n "$AGENT_TOKEN" ] && [ -n "$OWNER_TOKEN" ] && echo 1 || echo 0)" "no token"

SID="$(session "$AGENT_TOKEN")"
r="$(mcp "$AGENT_TOKEN" "$(post 'Over OAuth' draft)" "$SID")"
check "an agent's token writes a draft" "$(has "$r" 'Post created successfully')" "$r"
r="$(mcp "$AGENT_TOKEN" "$(post 'Over OAuth live' publish)" "$SID")"
check "an agent's token publishing at Propose is refused, naming the level" "$(has "$r" 'This agent is at Propose')" "$r"

OSID="$(session "$OWNER_TOKEN")"
r="$(mcp "$OWNER_TOKEN" "$(post 'As the owner' draft)" "$OSID")"
check "a person's token runs nothing" "$(has "$r" 'approved by a person, not an agent')" "$r"

step off
r="$(mcp "$AGENT_TOKEN" "$(post 'While off' draft)" "$SID")"
check "Off ends the agent's next call, naming the level" "$(has "$r" 'This agent is Off')" "$r"
step propose
step revoke
r="$(mcp "$AGENT_TOKEN" "$(post 'Revoked' draft)" "$SID" | sed -n 1p)"
check "revoking the grant ends the agent's next call" "$(has "$r" ' 401')" "$r"

# The consent screens, in the owner's browser.
r="$(curl -s -o /dev/null -w '%{http_code}' -b "$OWNER_COOKIE" "$BASE/oauth/authorize-callback?state=any")"
b="$(curl -s -b "$OWNER_COOKIE" "$BASE/oauth/authorize-callback?state=any")"
check "the owner is refused the consent screen, with the way on" "$([ "$r" = 403 ] && [ "$(has "$b" 'Approve a connection as')" = 1 ] && echo 1 || echo 0)" "HTTP $r"
r="$(curl -s -o /dev/null -w '%{http_code}' -b "$OWNER_COOKIE" -X POST "$BASE/oauth/consent" -d state=any -d mcp_action=allow)"
check "the owner's Allow is refused" "$([ "$r" = 403 ] && echo 1 || echo 0)" "HTTP $r"

# "Approve a connection as": the browser becomes the agent, for the OAuth screens only.
JAR="$(mktemp)"
r="$(curl -s -c "$JAR" -b "$OWNER_COOKIE" -X POST "$BASE/wp-admin/admin-post.php" -d action=agent_ready_approve_as -d "agent=$AGENT" -d "_wpnonce=$APPROVE_NONCE")"
check "Approve a connection as signs the browser in as the agent" "$(has "$r" 'to approve one connection')" "$(echo "$r" | cut -c1-300 | sed -n 1,3p)"
r="$(curl -s -b "$JAR" "$BASE/oauth/authorize-callback?state=any")"
check "as the agent, the consent screen is reached (the OAuth server judges the state)" "$(has "$r" 'Invalid or expired state')" "$(echo "$r" | cut -c1-300 | sed -n 1,3p)"
r="$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/wp-admin/")"
check "that sign-in never reaches the dashboard" "$([ "$r" = 403 ] && echo 1 || echo 0)" "HTTP $r"
r="$(curl -s -b "$JAR" "$BASE/oauth/authorize-callback?state=any")"
check "and is over after straying" "$(lacks "$r" 'Invalid or expired state')" "$(echo "$r" | cut -c1-300 | sed -n 1,3p)"

# Straying to the public site ends it as well.
: > "$JAR"
curl -s -o /dev/null -c "$JAR" -b "$OWNER_COOKIE" -X POST "$BASE/wp-admin/admin-post.php" -d action=agent_ready_approve_as -d "agent=$AGENT" -d "_wpnonce=$APPROVE_NONCE"
r="$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/")"
check "that sign-in on the public site is ended" "$([ "$r" = 403 ] && echo 1 || echo 0)" "HTTP $r"
r="$(curl -s -b "$JAR" "$BASE/oauth/authorize-callback?state=any")"
check "and is over" "$(lacks "$r" 'Invalid or expired state')" "$(echo "$r" | cut -c1-300 | sed -n 1,3p)"

# Answering the consent screen ends it too, whatever the answer (here a form the OAuth server rejects).
: > "$JAR"
curl -s -o /dev/null -c "$JAR" -b "$OWNER_COOKIE" -X POST "$BASE/wp-admin/admin-post.php" -d action=agent_ready_approve_as -d "agent=$AGENT" -d "_wpnonce=$APPROVE_NONCE"
r="$(curl -s -b "$JAR" "$BASE/oauth/authorize-callback?state=any")"
check "a second approval sign-in reaches the consent screen" "$(has "$r" 'Invalid or expired state')" "$(echo "$r" | cut -c1-300 | sed -n 1,3p)"
curl -s -o /dev/null -b "$JAR" -X POST "$BASE/oauth/consent" -d state=any -d mcp_action=deny
r="$(curl -s -b "$JAR" "$BASE/oauth/authorize-callback?state=any")"
check "answering the consent screen ends that sign-in" "$(lacks "$r" 'Invalid or expired state')" "$(echo "$r" | cut -c1-300 | sed -n 1,3p)"
rm -f "$JAR"

echo
[ "$failures" = 0 ] && echo "all checks passed" || { echo "$failures check(s) failed"; exit 1; }

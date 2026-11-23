<?php
/**
 * M1 checks with the MCP Adapter and Enable Abilities for MCP active (tests/run.sh): the post abilities over MCP at each
 * level, the agent-only OAuth consent, the person-token refusal, the consent-only sign-in, and the audit's defaults
 * (docs/decisions/0002-template-not-fork.md conditions b–f). Run with `wp eval-file tests/connect.php`.
 *
 * @package AgentReady
 */

use AgentReady\Abilities;
use AgentReady\Agents;
use AgentReady\Connect;

$failures = 0;

$check = static function ( string $name, bool $ok, string $detail = '' ) use ( &$failures ): void {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . ( $ok || '' === $detail ? '' : "\n     {$detail}" ) . "\n";
	if ( ! $ok ) {
		++$failures;
	}
};

$owner_id = (int) get_user_by( 'login', 'owner' )->ID;
$check( 'setup: the adapter, the abilities plugin and its OAuth server are loaded', class_exists( '\WP\MCP\Core\McpAdapter' ) && function_exists( 'ewpa_is_ability_enabled' ) && class_exists( '\WPMedia\MCP\OAuth\Bootstrap' ) );

// ---- MCP, in process: the adapter's default server, as the signed-in user. ---------------------------------------
$mcp = static function ( string $method, $params, ?string $session = null ): WP_REST_Response {
	$req = new WP_REST_Request( 'POST', '/mcp/mcp-adapter-default-server' );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_header( 'accept', 'application/json, text/event-stream' );
	if ( $session ) {
		$req->set_header( 'mcp-session-id', $session );
	}
	$req->set_body( wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params ) ) );
	return rest_do_request( $req );
};
$session = static function () use ( $mcp ): ?string {
	$r = $mcp( 'initialize', array( 'protocolVersion' => '2025-11-25', 'capabilities' => new stdClass(), 'clientInfo' => array( 'name' => 'check', 'version' => '1' ) ) );
	$h = array_change_key_case( $r->get_headers() );
	return $h['mcp-session-id'] ?? null;
};
/** Runs one ability over MCP; returns [ok, data or error text]. */
$ability = static function ( string $name, array $input ) use ( $mcp, $session ): array {
	$sid = $session();
	if ( null === $sid ) {
		return array( false, 'no MCP session' );
	}
	$r    = $mcp( 'tools/call', array( 'name' => 'mcp-adapter-execute-ability', 'arguments' => array( 'ability_name' => $name, 'parameters' => (object) $input ) ), $sid );
	$data = json_decode( wp_json_encode( $r->get_data() ), true );
	if ( isset( $data['error'] ) ) {
		return array( false, (string) ( $data['error']['message'] ?? '' ) );
	}
	$text = (string) ( $data['result']['content'][0]['text'] ?? '' );
	$body = json_decode( $text, true );
	if ( ! empty( $data['result']['isError'] ) || ( is_array( $body ) && false === ( $body['success'] ?? true ) ) ) {
		return array( false, $text );
	}
	return array( true, is_array( $body ) ? ( $body['data'] ?? $body ) : $text );
};

wp_set_current_user( $owner_id );
$agent_id = Agents\create( 'claude-' . wp_generate_password( 6, false ) );
wp_set_current_user( $agent_id );

// ---- Propose over MCP: drafts and submissions; publishing names the level. -------------------------------------
[ $ok, $draft ] = $ability( 'ewpa/create-post', array( 'title' => 'Agent draft', 'content' => '<p>Words.</p>' ) );
$draft_id       = $ok ? (int) ( $draft['post_id'] ?? $draft['id'] ?? 0 ) : 0;
$check( 'Propose creates a draft over MCP', $ok && $draft_id > 0 && 'draft' === get_post_status( $draft_id ), wp_json_encode( $draft ) );
[ $ok, $out ] = $ability( 'ewpa/update-post', array( 'post_id' => $draft_id, 'content' => '<p>Better words.</p>' ) );
$check( 'Propose edits its draft over MCP', $ok && false !== strpos( get_post( $draft_id )->post_content, 'Better' ), wp_json_encode( $out ) );
[ $ok, $out ] = $ability( 'ewpa/update-post', array( 'post_id' => $draft_id, 'status' => 'pending' ) );
$check( 'Propose submits it for review over MCP', $ok && 'pending' === get_post_status( $draft_id ), wp_json_encode( $out ) );
[ $ok, $why ] = $ability( 'ewpa/update-post', array( 'post_id' => $draft_id, 'status' => 'publish' ) );
$check( 'Propose publishing over MCP is refused', ! $ok && 'pending' === get_post_status( $draft_id ) );
$check( 'the refusal names the level and what it may do', false !== strpos( (string) $why, 'Propose' ) && false !== strpos( (string) $why, 'submits them for review' ), (string) $why );
[ $ok, $why ] = $ability( 'ewpa/create-post', array( 'title' => 'Straight out', 'content' => 'x', 'status' => 'publish' ) );
$check( 'Propose creating a live post is refused, naming the level', ! $ok && false !== strpos( (string) $why, 'Propose' ), (string) $why );
[ $ok, $out ] = $ability( 'ewpa/create-post', array( 'title' => 'In your name', 'content' => 'x', 'author_id' => $owner_id ) );
$named_id     = $ok ? (int) ( $out['post_id'] ?? $out['id'] ?? 0 ) : 0;
$check( 'Propose cannot file a post under the owner\'s name', ! $ok || (int) get_post( $named_id )->post_author !== $owner_id );

[ $ok, $list ] = $ability( 'ewpa/get-posts', array( 'status' => 'pending' ) );
$check( 'an agent lists posts over MCP', $ok, wp_json_encode( $list ) );
[ $ok, $read ] = $ability( 'ewpa/get-post', array( 'post_id' => $draft_id ) );
$check( 'an agent reads a post over MCP', $ok && false !== strpos( wp_json_encode( $read ), 'Better words' ), wp_json_encode( $read ) );
[ $ok, $link ] = $ability( 'ewpa/create-upload-link', array( 'filename' => 'cat.png' ) );
$check( 'an agent gets an upload link over MCP (pictures go up through links)', $ok && ! empty( $link['upload_url'] ), wp_json_encode( $link ) );
$check( 'image-from-URL is off (audit condition f)', ! ewpa_is_ability_enabled( 'ewpa/upload-image' ) && null === wp_get_ability( 'ewpa/upload-image' ) );

// ---- Publish: its own posts live and scheduled; trash, never a permanent delete. ---------------------------------
wp_set_current_user( $owner_id );
Agents\set_level( $agent_id, 'publish' );
wp_set_current_user( $agent_id );
[ $ok, $out ] = $ability( 'ewpa/update-post', array( 'post_id' => $draft_id, 'status' => 'publish' ) );
$check( 'Publish publishes its post over MCP', $ok && 'publish' === get_post_status( $draft_id ), wp_json_encode( $out ) );
$when         = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
[ $ok, $out ] = $ability( 'ewpa/create-post', array( 'title' => 'Tomorrow', 'content' => 'x', 'status' => 'future', 'post_date' => get_date_from_gmt( $when ) ) );
$sched_id     = $ok ? (int) ( $out['post_id'] ?? $out['id'] ?? 0 ) : 0;
$check( 'Publish schedules over MCP, and the guard records it as the agent\'s schedule', $ok && 'future' === get_post_status( $sched_id ) && (int) get_post_meta( $sched_id, '_agent_ready_scheduled_by', true ) === $agent_id, wp_json_encode( $out ) );
[ $ok, $out ] = $ability( 'ewpa/delete-post', array( 'post_id' => $sched_id ) );
$check( 'Publish trashes its post over MCP', $ok && 'trash' === get_post_status( $sched_id ), wp_json_encode( $out ) );
[ $ok, $out ] = $ability( 'ewpa/delete-post', array( 'post_id' => $sched_id, 'force_delete' => true ) );
$check( 'a permanent delete over MCP is refused (the guard holds under the abilities plugin)', ! $ok && null !== get_post( $sched_id ) );

wp_set_current_user( $owner_id );
$owners = wp_insert_post( array( 'post_title' => 'The owner\'s', 'post_status' => 'publish' ) );
wp_set_current_user( $agent_id );
[ $ok, $why ] = $ability( 'ewpa/update-post', array( 'post_id' => $owners, 'title' => 'Mine now' ) );
$check( 'Publish cannot edit the owner\'s post, and says why', ! $ok && 'The owner\'s' === get_post( $owners )->post_title && false !== strpos( (string) $why, 'Publish' ), (string) $why );

// ---- Off: nothing, and the reason says so. ---------------------------------------------------------------------
wp_set_current_user( $owner_id );
Agents\set_level( $agent_id, 'off' );
wp_set_current_user( $agent_id );
$denied = wp_get_ability( 'ewpa/create-post' )->check_permissions( array( 'title' => 'x' ) );
$check( 'Off is refused before anything runs, naming the level', is_wp_error( $denied ) && 'agent_ready_level' === $denied->get_error_code() && false !== strpos( $denied->get_error_message(), 'Off' ) );
wp_set_current_user( $owner_id );
Agents\set_level( $agent_id, 'propose' );

// ---- A person's refusal is the plugin's own; nothing is rewritten for people. -------------------------------------
$person = wp_insert_user( array( 'user_login' => 'writer-' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'role' => 'contributor' ) );
wp_set_current_user( $person );
[ $ok, $why ] = $ability( 'ewpa/create-post', array( 'title' => 'Person live', 'content' => 'x', 'status' => 'publish' ) );
$check( 'a person refused gets the plugin\'s own reason, untouched', ! $ok && false === strpos( (string) $why, 'This agent is at' ), (string) $why );
wp_set_current_user( $owner_id );
$check( 'the owner publishes over MCP (people pass through unchanged)', $ability( 'ewpa/create-post', array( 'title' => 'Owner live', 'content' => 'x', 'status' => 'publish' ) )[0] );

// ---- OAuth consent: only an agent approves (audit condition b). -------------------------------------------------
wp_set_current_user( $owner_id );
$why = Connect\consent_refusal( 'consent' );
$check( 'an administrator approving a connection is refused', null !== $why );
$check( 'the refusal says to approve as the agent', null !== $why && false !== strpos( $why, 'Approve a connection as' ) && false !== strpos( $why, 'not as an agent' ), (string) $why );
$check( 'an administrator is refused the consent screen itself', null !== Connect\consent_refusal( 'authorize-callback' ) );
$check( 'the authorize step only redirects, so it is left to the OAuth server', null === Connect\consent_refusal( 'authorize' ) );
wp_set_current_user( $agent_id );
$check( 'an agent may approve', null === Connect\consent_refusal( 'consent' ) && null === Connect\consent_refusal( 'authorize-callback' ) );

// Hooked before the OAuth router, so the refusal comes first.
$check( 'the consent rule runs before the OAuth router', has_action( 'template_redirect', 'AgentReady\\Connect\\guard_oauth_screens' ) < 10 && false !== has_action( 'template_redirect', 'AgentReady\\Connect\\guard_oauth_screens' ) );

// ---- A token that reached a person does nothing on the OAuth MCP server (tests/oauth-http.sh drives the real tokens).
$servers = \WP\MCP\Core\McpAdapter::instance()->get_servers();
$oauth   = $servers[ Connect\MCP_SERVER_ID ] ?? null;
$default = $servers['mcp-adapter-default-server'] ?? null;
$check( 'setup: the OAuth MCP server is registered', null !== $oauth && null !== $default );
foreach ( array( 'mcp_adapter_pre_tool_call', 'mcp_adapter_pre_resource_read', 'mcp_adapter_pre_prompt_get' ) as $hook ) {
	wp_set_current_user( $owner_id );
	$res = apply_filters( $hook, array( 'x' => 1 ), 'mcp-adapter-execute-ability', null, $oauth );
	$check( "a person's call on the OAuth server is refused ({$hook})", is_wp_error( $res ) && 'agent_ready_not_an_agent' === $res->get_error_code() );
	wp_set_current_user( $agent_id );
	$check( "an agent's call on the OAuth server goes through ({$hook})", array( 'x' => 1 ) === apply_filters( $hook, array( 'x' => 1 ), 'mcp-adapter-execute-ability', null, $oauth ) );
}
wp_set_current_user( $owner_id );
$check( 'the adapter\'s own server is untouched', array( 'x' => 1 ) === apply_filters( 'mcp_adapter_pre_tool_call', array( 'x' => 1 ), 'mcp-adapter-execute-ability', null, $default ) );

// ---- The audit's defaults (conditions c, d, f) and OAuth on. ------------------------------------------------------
update_option( 'ewpa_bearer_enabled', 1 );
$check( 'the legacy Bearer key stays off whatever is stored (condition c)', ! get_option( 'ewpa_bearer_enabled' ) );
$check( 'its Bearer authentication is not hooked', false === has_filter( 'determine_current_user', 'ewpa_authenticate_api_key' ) );
delete_option( 'ewpa_oauth_connectors_enabled' );
$check( 'ChatGPT connectors start off (condition d)', ! get_option( 'ewpa_oauth_connectors_enabled' ) );
update_option( 'ewpa_oauth_connectors_enabled', 1 );
$check( 'the owner may turn them on', (bool) get_option( 'ewpa_oauth_connectors_enabled' ) );
delete_option( 'ewpa_oauth_connectors_enabled' );
delete_option( 'ewpa_oauth_enabled' );
$check( 'OAuth sign-in starts on', (bool) get_option( 'ewpa_oauth_enabled' ) );
delete_option( 'ewpa_enabled_abilities' );
$check( 'before any save: image-from-URL off, upload links on, posts on', ! ewpa_is_ability_enabled( 'ewpa/upload-image' ) && ewpa_is_ability_enabled( 'ewpa/create-upload-link' ) && ewpa_is_ability_enabled( 'ewpa/create-post' ) );
update_option( 'ewpa_enabled_abilities', ewpa_get_default_ability_keys() );
$first = (array) get_option( 'ewpa_enabled_abilities' );
$check( 'the plugin\'s first save (activation) gets the same defaults', ! in_array( 'ewpa/upload-image', $first, true ) && in_array( 'ewpa/create-upload-link', $first, true ) );
update_option( 'ewpa_enabled_abilities', array_merge( $first, array( 'ewpa/upload-image' ) ) );
$check( 'a later save is the owner\'s choice', in_array( 'ewpa/upload-image', (array) get_option( 'ewpa_enabled_abilities' ), true ) );
update_option( 'ewpa_enabled_abilities', $first );

// Installed before Agent Ready: what it stored is adjusted once, and the owner's later choice stands.
delete_option( Abilities\ADJUSTED );
update_option( 'ewpa_enabled_abilities', array( 'ewpa/create-post', 'ewpa/upload-image' ) );
Abilities\adjust_once();
$after = (array) get_option( 'ewpa_enabled_abilities' );
$check( 'a list stored before Agent Ready is adjusted once', ! in_array( 'ewpa/upload-image', $after, true ) && in_array( 'ewpa/create-upload-link', $after, true ) && in_array( 'ewpa/create-post', $after, true ) );
update_option( 'ewpa_enabled_abilities', array_merge( $after, array( 'ewpa/upload-image' ) ) );
Abilities\adjust_once();
$check( 'only once: the owner turning it back on stands', in_array( 'ewpa/upload-image', (array) get_option( 'ewpa_enabled_abilities' ), true ) );
update_option( 'ewpa_enabled_abilities', $first );

// ---- The owner's consent-only sign-in as the agent. ---------------------------------------------------------------
$check( 'approving as an Off agent is disabled with a reason', ( static function () use ( $owner_id, $agent_id ): bool {
	wp_set_current_user( $owner_id );
	Agents\set_level( $agent_id, 'off' );
	$why = Connect\approve_as_unavailable( $agent_id );
	Agents\set_level( $agent_id, 'propose' );
	return null !== $why && false !== strpos( $why, 'Off' );
} )() );
$check( 'approving as a Propose agent is available', null === Connect\approve_as_unavailable( $agent_id ) );

// No cookies to send from the command line.
add_filter( 'send_auth_cookies', '__return_false' );
$token = Connect\start_consent_only( $agent_id );
$sign  = WP_Session_Tokens::get_instance( $agent_id )->get( $token );
$check( 'the sign-in lasts 15 minutes', is_array( $sign ) && abs( $sign['expiration'] - ( time() + 15 * MINUTE_IN_SECONDS ) ) < 5 );
$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $agent_id, time() + DAY_IN_SECONDS, 'logged_in', $token );
wp_set_current_user( $agent_id );
$check( 'it is known as consent-only', Connect\is_consent_only() );
$rest = apply_filters( 'rest_authentication_errors', null );
$check( 'it is no REST sign-in', is_wp_error( $rest ) && 'agent_ready_consent_only' === $rest->get_error_code() );
Connect\end_this_sign_in();
$check( 'ending it destroys the session', null === WP_Session_Tokens::get_instance( $agent_id )->get( $token ) );
$token2 = WP_Session_Tokens::get_instance( $agent_id )->create( time() + HOUR_IN_SECONDS );
$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $agent_id, time() + DAY_IN_SECONDS, 'logged_in', $token2 );
$check( 'an ordinary sign-in is not consent-only', ! Connect\is_consent_only() );
unset( $_COOKIE[ LOGGED_IN_COOKIE ] );

echo $failures ? "\n{$failures} check(s) failed\n" : "\nall checks passed\n";
exit( $failures ? 1 : 0 );

<?php
/**
 * The WordPress side of tests/oauth-http.sh, one step per run (AR_STEP): sets the site up for HTTP and seeds what a
 * finished consent leaves (an auth code for an agent and one for the owner), or changes the agent between requests.
 * Prints shell assignments for the script to read. Run with `wp eval-file` or any loader of wp-load.php.
 *
 * @package AgentReady
 */

use AgentReady\Agents;

$step  = getenv( 'AR_STEP' ) ?: 'seed';
$owner = get_user_by( 'login', 'owner' );
wp_set_current_user( $owner->ID );

if ( 'seed' === $step ) {
	$base = rtrim( (string) getenv( 'AR_BASE' ), '/' );
	update_option( 'home', $base );
	update_option( 'siteurl', $base );
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules( false );
	$agent     = Agents\create( 'http-' . wp_generate_password( 6, false ) );
	$verifier  = wp_generate_password( 50, false );
	$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	foreach ( array( 'agent' => $agent, 'owner' => $owner->ID ) as $who => $user_id ) {
		// What the consent endpoint stores on Allow (WPMedia\MCP\OAuth\Auth\ConsentEndpoint): the owner's stands for a
		// token that reached a person some other way, which the guard must still stop.
		set_transient(
			"mcp_oauth_code_ar{$who}",
			array(
				'user_id'        => $user_id,
				'client_id'      => 'https://claude.ai/oauth/mcp-oauth-client-metadata',
				'client_name'    => 'Claude',
				'code_challenge' => $challenge,
				'redirect_uri'   => 'https://claude.ai/api/mcp/auth_callback',
			),
			600
		);
	}
	// The owner's browser: a signed-in session's cookies.
	$expires = time() + HOUR_IN_SECONDS;
	$token   = WP_Session_Tokens::get_instance( $owner->ID )->create( $expires );
	echo 'AGENT=' . (int) $agent . "\n";
	echo 'VERIFIER=' . $verifier . "\n";
	echo 'OWNER_COOKIE=' . escapeshellarg( LOGGED_IN_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $owner->ID, $expires, 'logged_in', $token ) ) . '; ' . AUTH_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $owner->ID, $expires, 'auth', $token ) ) ) . "\n";
	// A nonce is bound to the session it was made in: make it inside the owner's.
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $owner->ID, $expires, 'logged_in', $token );
	echo 'APPROVE_NONCE=' . wp_create_nonce( 'agent_ready_approve_as' ) . "\n";
	exit( 0 );
}

$agent = (int) getenv( 'AR_AGENT' );
if ( 'off' === $step ) {
	Agents\set_level( $agent, 'off' );
} elseif ( 'propose' === $step ) {
	Agents\set_level( $agent, 'propose' );
} elseif ( 'revoke' === $step ) {
	foreach ( WP_Application_Passwords::get_user_application_passwords( $agent ) as $grant ) {
		WP_Application_Passwords::delete_application_password( $agent, $grant['uuid'] );
	}
}
exit( 0 );

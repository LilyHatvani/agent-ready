<?php
/**
 * How an AI client connects over OAuth (docs/decisions/0003-sign-in.md), and the line that keeps the levels honest:
 * a connection gets the reach of the user who approves it, so only an agent user may approve one.
 *
 * The OAuth server is wp-media/mcp-oauth, bundled by Enable Abilities for MCP (0002). It issues the code to whoever is
 * signed in and offers no filter to change that, so the rule sits in front of it:
 *
 * 1. Consent: its consent screen and its Allow are refused for anyone who isn't an agent, with the reason and the way
 *    on (`refuse_consent`, before its router on template_redirect).
 * 2. Tokens: its MCP server runs nothing for a person's token, so a token that reached a person by any other path still
 *    does nothing (`refuse_person_token`). Its transport checks the token inside its own callback, not in the REST
 *    permission check, so the refusal sits on the adapter's pre-call filters, which run after the token's user is
 *    signed in and before any tool, resource or prompt runs.
 * 3. The way on: from Users → Agents the owner signs this browser in as the agent for 15 minutes, for approving one
 *    connection and nothing else (`approve_as`). That sign-in reaches only the OAuth screens; anything else ends it,
 *    and approving or denying ends it too.
 *
 * Without the OAuth server active, none of this has anything to guard and the hooks find nothing to do.
 *
 * @package AgentReady
 */

namespace AgentReady\Connect;

use AgentReady\Agents;

defined( 'ABSPATH' ) || exit;

/** The OAuth server's query var naming its endpoint (WPMedia\MCP\OAuth\Auth\Rewrite::OAUTH_QUERY_VAR). */
const ENDPOINT_VAR = 'mcp_oauth_endpoint';

/** Its MCP route, where a token is used (WPMedia\MCP\OAuth\Transport\Server: namespace mcp, route mcp-oauth-server). */
const MCP_ROUTE = '/mcp/mcp-oauth-server';

/** Its MCP server's id, as the adapter knows it. */
const MCP_SERVER_ID = 'mcp-oauth-server';

/** The endpoints a consent-only sign-in may reach. */
const CONSENT_ENDPOINTS = array( 'authorize', 'authorize-callback', 'consent' );

/** Session marker for the owner's sign-in as an agent. */
const CONSENT_ONLY = 'agent_ready_consent_only';

/** How long that sign-in lasts. */
const CONSENT_MINUTES = 15;

const ACTION = 'agent_ready_approve_as';

/** Hooks it in. */
function register(): void {
	add_action( 'template_redirect', __NAMESPACE__ . '\\guard_oauth_screens', 0 );
	add_filter( 'mcp_adapter_pre_tool_call', __NAMESPACE__ . '\\refuse_person_token', 0, 4 );
	add_filter( 'mcp_adapter_pre_resource_read', __NAMESPACE__ . '\\refuse_person_token', 0, 4 );
	add_filter( 'mcp_adapter_pre_prompt_get', __NAMESPACE__ . '\\refuse_person_token', 0, 4 );
	add_action( 'admin_init', __NAMESPACE__ . '\\end_consent_only_outside_oauth', 0 );
	add_filter( 'rest_authentication_errors', __NAMESPACE__ . '\\refuse_consent_only_rest' );
	add_action( 'admin_post_' . ACTION, __NAMESPACE__ . '\\approve_as' );
}

/** The OAuth endpoint this request is for, or ''. */
function current_endpoint(): string {
	return (string) get_query_var( ENDPOINT_VAR, '' );
}

/**
 * Why the signed-in user may not see the consent screen or approve, or null when they may. Only the consent screen
 * and the Allow are judged: that's where a code is issued to the signed-in user.
 *
 * @param string $endpoint The OAuth endpoint.
 */
function consent_refusal( string $endpoint ): ?string {
	if ( ! in_array( $endpoint, array( 'authorize-callback', 'consent' ), true ) || ! is_user_logged_in() || Agents\current_is_agent() ) {
		return null;
	}
	$who = wp_get_current_user()->display_name;
	return sprintf(
		/* translators: %s: the signed-in person's name. */
		__( 'You\'re signed in as %s, not as an agent. A connection gets the reach of whoever approves it, so only an agent can approve one: otherwise the AI would act with your rights, past every level. In Users → Agents, choose "Approve a connection as" next to the agent this AI should be, then connect again from the AI.', 'agent-ready' ),
		$who
	);
}

/** Whether this request's sign-in is the owner's short sign-in as an agent. */
function is_consent_only(): bool {
	$user_id = get_current_user_id();
	$token   = wp_get_session_token();
	if ( 0 === $user_id || '' === $token ) {
		return false;
	}
	$session = \WP_Session_Tokens::get_instance( $user_id )->get( $token );
	return is_array( $session ) && ! empty( $session[ CONSENT_ONLY ] );
}

/** Ends this request's sign-in, server-side and in the browser. */
function end_this_sign_in(): void {
	\WP_Session_Tokens::get_instance( get_current_user_id() )->destroy( wp_get_session_token() );
	wp_clear_auth_cookie();
}

/**
 * Before the OAuth router: refuses a person at consent, keeps a consent-only sign-in on the OAuth screens, and ends
 * that sign-in once the owner has answered.
 */
function guard_oauth_screens(): void {
	$endpoint = current_endpoint();
	if ( is_consent_only() ) {
		if ( ! in_array( $endpoint, CONSENT_ENDPOINTS, true ) ) {
			end_this_sign_in();
			wp_die( esc_html( consent_only_over_text() ), esc_html__( 'Signed out', 'agent-ready' ), array( 'response' => 403 ) );
		}
		if ( 'consent' === $endpoint && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			// The OAuth server redirects and exits; the sign-in ends as the request does, whatever the answer was.
			$user_id = get_current_user_id();
			$token   = wp_get_session_token();
			add_action(
				'shutdown',
				static function () use ( $user_id, $token ): void {
					\WP_Session_Tokens::get_instance( $user_id )->destroy( $token );
				}
			);
		}
	}
	$why = consent_refusal( $endpoint );
	if ( null !== $why ) {
		wp_die( esc_html( $why ), esc_html__( 'Approve as an agent', 'agent-ready' ), array( 'response' => 403 ) );
	}
}

/** Text shown when a consent-only sign-in strays from the OAuth screens. */
function consent_only_over_text(): string {
	return __( 'That sign-in was only for approving a connection as an agent, so it has ended. Sign in with your own account to carry on.', 'agent-ready' );
}

/** A consent-only sign-in never reaches the dashboard: it ends there. */
function end_consent_only_outside_oauth(): void {
	if ( is_consent_only() ) {
		end_this_sign_in();
		wp_die( esc_html( consent_only_over_text() ), esc_html__( 'Signed out', 'agent-ready' ), array( 'response' => 403 ) );
	}
}

/**
 * A consent-only sign-in isn't a REST sign-in either.
 *
 * @param mixed $error An earlier authentication error, true, or null.
 * @return mixed
 */
function refuse_consent_only_rest( $error ) {
	if ( null !== $error || ! is_consent_only() ) {
		return $error;
	}
	return new \WP_Error( 'agent_ready_consent_only', consent_only_over_text(), array( 'status' => 403 ) );
}

/**
 * The OAuth MCP server runs nothing for anyone but an agent: a tool call, a resource read or a prompt from a person's
 * token is refused before it runs. Other MCP servers (the adapter's own, with application passwords) are untouched.
 *
 * @param mixed  $args      The call's arguments, or an earlier refusal.
 * @param mixed  $name      The tool, resource or prompt.
 * @param mixed  $component Its MCP component.
 * @param object $server    The adapter's MCP server handling the call.
 * @return mixed
 */
function refuse_person_token( $args, $name, $component, $server ) {
	if ( is_wp_error( $args ) || ! is_object( $server ) || ! method_exists( $server, 'get_server_id' )
		|| MCP_SERVER_ID !== $server->get_server_id() || Agents\current_is_agent() ) {
		return $args;
	}
	return new \WP_Error(
		'agent_ready_not_an_agent',
		__( 'This connection was approved by a person, not an agent, so it can\'t be used: it would act past every level. Remove it, then connect again approving as an agent (Users → Agents).', 'agent-ready' ),
		array( 'status' => 403 )
	);
}

/**
 * Why the owner can't approve a connection as this agent now, or null when they can. Shown beside the disabled
 * button in Users → Agents.
 *
 * @param int $agent_id Agent user id.
 */
function approve_as_unavailable( int $agent_id ): ?string {
	if ( ! oauth_available() ) {
		return __( 'The OAuth server isn\'t on: activate Enable Abilities for MCP and turn on its OAuth sign-in. Claude Code and other desktop clients can connect with an application password meanwhile.', 'agent-ready' );
	}
	if ( 'off' === Agents\level_of( $agent_id ) ) {
		return __( 'This agent is Off: give it a level first.', 'agent-ready' );
	}
	return null;
}

/** Whether the OAuth server is active on this site. */
function oauth_available(): bool {
	return class_exists( '\WPMedia\MCP\OAuth\Bootstrap' ) && (bool) get_option( 'ewpa_oauth_enabled' );
}

/** The address an AI client connects to over OAuth. */
function connector_url(): string {
	return rest_url( ltrim( MCP_ROUTE, '/' ) );
}

/**
 * Users → Agents → "Approve a connection as": signs this browser in as the agent for CONSENT_MINUTES, only for the
 * OAuth screens, then tells the owner what to do in the AI client.
 */
function approve_as(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Only the site owner can approve a connection for an agent.', 'agent-ready' ), 403 );
	}
	check_admin_referer( ACTION );
	$agent_id = isset( $_POST['agent'] ) ? absint( $_POST['agent'] ) : 0;
	if ( ! Agents\is_agent( $agent_id ) ) {
		wp_die( esc_html__( 'That isn\'t an agent.', 'agent-ready' ), 400 );
	}
	$why = approve_as_unavailable( $agent_id );
	if ( null !== $why ) {
		wp_die( esc_html( $why ), 409 );
	}

	$token = start_consent_only( $agent_id );
	wp_clear_auth_cookie();
	wp_set_auth_cookie( $agent_id, false, '', $token );

	$agent = get_userdata( $agent_id );
	wp_die(
		'<h1>' . esc_html( sprintf( /* translators: %s: agent name. */ __( 'Signed in as %s, to approve one connection', 'agent-ready' ), $agent->user_login ) ) . '</h1>'
		. '<p>' . esc_html( sprintf( /* translators: %d: minutes. */ __( 'For the next %d minutes this browser is the agent, and only on the approval screen. Your own account is signed out here; sign in again when you\'re done.', 'agent-ready' ), CONSENT_MINUTES ) ) . '</p>'
		. '<ol><li>' . esc_html__( 'In the AI client, add a custom connector with this address:', 'agent-ready' ) . ' <code>' . esc_html( connector_url() ) . '</code></li>'
		. '<li>' . esc_html__( 'It opens this site in this browser: choose Allow. The connection is the agent, at the agent\'s level, and this sign-in ends.', 'agent-ready' ) . '</li></ol>',
		esc_html__( 'Approve a connection', 'agent-ready' ),
		array( 'response' => 200 )
	);
}

/**
 * Creates the agent's consent-only session and returns its token.
 *
 * @param int $agent_id Agent user id.
 */
function start_consent_only( int $agent_id ): string {
	$sessions = \WP_Session_Tokens::get_instance( $agent_id );
	$token    = $sessions->create( time() + CONSENT_MINUTES * MINUTE_IN_SECONDS );
	$session  = $sessions->get( $token );
	$session[ CONSENT_ONLY ] = true;
	$sessions->update( $token, $session );
	return $token;
}

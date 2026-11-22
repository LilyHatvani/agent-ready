<?php
/**
 * Agent users and their levels (docs/decisions/0001-levels-are-roles.md).
 *
 * An agent is a WordPress user marked with the `agent_ready_agent` user meta. Its level IS its role, so WordPress's
 * own capability checks (and every ability that respects them) enforce it:
 *
 *   Propose = Contributor   drafts and submits for review; cannot publish
 *   Publish = Author        publishes and schedules its own posts
 *   Full    = Editor        runs the site's content, everyone's posts included
 *   Off     = no role       can't do anything; its schedules stop (guard.php)
 *
 * @package AgentReady
 */

namespace AgentReady\Agents;

defined( 'ABSPATH' ) || exit;

const META_KEY = 'agent_ready_agent';

/** Level slug => WordPress role slug ('' = no role). */
const LEVEL_ROLES = array(
	'propose' => 'contributor',
	'publish' => 'author',
	'full'    => 'editor',
	'off'     => '',
);

/** The level a new agent starts at. */
const DEFAULT_LEVEL = 'propose';

/**
 * Whether a user is an agent.
 *
 * @param int $user_id User id; 0 = nobody.
 */
function is_agent( int $user_id ): bool {
	return $user_id > 0 && '1' === (string) get_user_meta( $user_id, META_KEY, true );
}

/** Whether the request is being made by an agent. */
function current_is_agent(): bool {
	return is_agent( get_current_user_id() );
}

/**
 * An agent's level, read from its role. A role that isn't one of the three (someone changed it by hand) reads as
 * 'custom'; no role reads as 'off'.
 *
 * @param int $user_id Agent user id.
 */
function level_of( int $user_id ): string {
	$user = get_userdata( $user_id );
	if ( ! $user || empty( $user->roles ) ) {
		return 'off';
	}
	$level = array_search( reset( $user->roles ), LEVEL_ROLES, true );
	return false === $level ? 'custom' : (string) $level;
}

/**
 * Sets an agent's level by setting its role. Only the owner calls this (admin.php checks the capability and nonce);
 * an agent calling it is refused here too, so no future caller can let an agent change a level.
 *
 * @param int    $user_id Agent user id.
 * @param string $level   One of LEVEL_ROLES' keys.
 */
function set_level( int $user_id, string $level ): bool {
	if ( current_is_agent() || ! array_key_exists( $level, LEVEL_ROLES ) || ! is_agent( $user_id ) ) {
		return false;
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	$user->set_role( LEVEL_ROLES[ $level ] );
	return true;
}

/**
 * Creates an agent user at the default level. It has no usable password: it signs in only through an MCP client
 * (an application password or OAuth grant the owner creates for it).
 *
 * @param string $login Username, e.g. "claude".
 * @return int|\WP_Error The new user id.
 */
function create( string $login ) {
	$login = sanitize_user( $login, true );
	if ( '' === $login ) {
		return new \WP_Error( 'agent_ready_bad_login', __( 'Give the agent a username: letters, numbers, dots, dashes.', 'agent-ready' ) );
	}
	$user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => wp_generate_password( 64, true, true ),
			'display_name' => $login,
			'role'         => LEVEL_ROLES[ DEFAULT_LEVEL ],
		)
	);
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}
	update_user_meta( $user_id, META_KEY, '1' );
	return $user_id;
}

/**
 * Every agent user.
 *
 * @return \WP_User[]
 */
function all(): array {
	return get_users(
		array(
			'meta_key'   => META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'orderby'    => 'login',
		)
	);
}

<?php
/**
 * The post abilities agents use over MCP come from Enable Abilities for MCP (docs/decisions/0002-template-not-fork.md).
 * They check WordPress capabilities, so an agent's role limits them; this file adds what the audit asked for:
 *
 * - A refusal names the agent's level and the way on (M1: "a Propose agent's publish attempt is refused with a reason
 *   naming the level"), instead of a bare "forbidden".
 * - Its legacy Bearer API key never authenticates (condition c): the option reads off, whatever is stored.
 * - Its ChatGPT connectors start off (d): the plugin's own default, pinned by a check. Its image-from-URL ability
 *   starts off here (f); the owner may turn either on. Uploads go through its single-use upload links, which start on.
 * - Its OAuth sign-in starts on, so claude.ai can connect without a settings visit; consent is guarded in connect.php.
 *
 * @package AgentReady
 */

namespace AgentReady\Abilities;

use AgentReady\Agents;

defined( 'ABSPATH' ) || exit;

/** Abilities that start on here although the plugin leaves them off, and those that start off although it has them on. */
const START_ON  = array( 'ewpa/create-upload-link', 'ewpa/get-upload-status' );
const START_OFF = array( 'ewpa/upload-image' );

/** Hooks it in. */
function register(): void {
	add_filter( 'wp_ability_permission_result', __NAMESPACE__ . '\\name_the_level', 20, 2 );
	add_filter( 'wp_ability_execute_result', __NAMESPACE__ . '\\name_the_level', 20, 2 );
	// Condition c: never on. pre_option wins over anything stored, the plugin's own migrations included.
	add_filter( 'pre_option_ewpa_bearer_enabled', __NAMESPACE__ . '\\off' );
	// Condition f and OAuth on: defaults only, so the owner's own choice in its settings stands.
	add_filter( 'default_option_ewpa_oauth_enabled', __NAMESPACE__ . '\\on' );
	add_filter( 'default_option_ewpa_enabled_abilities', __NAMESPACE__ . '\\default_abilities', 10, 1 );
	// Activating the plugin saves its defaults once; that first save gets the same adjustment.
	add_filter( 'pre_update_option_ewpa_enabled_abilities', __NAMESPACE__ . '\\first_save', 10, 2 );
	// It may have saved them before Agent Ready was here: adjust what's stored, once (before abilities register).
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\adjust_once', 20 );
}

/** Set once Agent Ready has applied START_ON / START_OFF to a list the plugin stored before it was active. */
const ADJUSTED = 'agent_ready_abilities_adjusted';

/**
 * Applies START_ON and START_OFF once to an ability list the plugin stored before Agent Ready was active (the owner
 * installed it first). Later changes are the owner's; the owner may turn image-from-URL back on in its settings.
 */
function adjust_once(): void {
	if ( ! function_exists( 'ewpa_is_ability_enabled' ) || get_option( ADJUSTED ) ) {
		return;
	}
	$stored = get_option( 'ewpa_enabled_abilities', false );
	if ( is_array( $stored ) ) {
		update_option( 'ewpa_enabled_abilities', adjusted( $stored ) );
	}
	update_option( ADJUSTED, 1 );
}

/**
 * A list with START_ON added and START_OFF removed.
 *
 * @param array $keys Ability keys.
 */
function adjusted( array $keys ): array {
	return array_values( array_unique( array_diff( array_merge( $keys, START_ON ), START_OFF ) ) );
}

/** An option that reads off: 0, not false, because false means "not short-circuited" to pre_option. */
function off(): int {
	return 0;
}

/** An option that reads on. */
function on(): int {
	return 1;
}

/**
 * The plugin's ability defaults with START_ON added and START_OFF removed, until the owner saves their own choice.
 * The plugin asks for the option with null as its "not saved" default; anything else passed in is left alone.
 *
 * @param mixed $default The default the caller passed.
 * @return mixed
 */
function default_abilities( $default ) {
	if ( null !== $default || ! function_exists( 'ewpa_get_default_ability_keys' ) ) {
		return $default;
	}
	return adjusted( (array) ewpa_get_default_ability_keys() );
}

/**
 * The first time the plugin stores its ability list (on activation), START_ON and START_OFF apply; every later save
 * is the owner's choice and passes unchanged.
 *
 * @param mixed $value The list being saved.
 * @param mixed $old   What was stored: false when nothing was.
 * @return mixed
 */
function first_save( $value, $old ) {
	if ( false !== $old || ! is_array( $value ) ) {
		return $value;
	}
	return adjusted( $value );
}

/** What each level may do, and what to do instead, for a refusal. */
function level_reason( string $level ): string {
	switch ( $level ) {
		case 'propose':
			return __( 'This agent is at Propose: it writes drafts and submits them for review (status "pending"); the owner publishes, schedules and trashes.', 'agent-ready' );
		case 'publish':
			return __( 'This agent is at Publish: it publishes, schedules and trashes its own posts; other people\'s posts are the owner\'s or an editor\'s.', 'agent-ready' );
		case 'full':
			return __( 'This agent is at Full: it runs the site\'s content, but never settings, users, plugins, unfiltered HTML or a permanent delete.', 'agent-ready' );
		case 'off':
			return __( 'This agent is Off: it can\'t do anything until the owner gives it a level in Users → Agents.', 'agent-ready' );
		default:
			return __( 'This agent has a role set by hand, not one of the levels; the owner can choose a level in Users → Agents.', 'agent-ready' );
	}
}

/**
 * When an ability refuses an agent for want of a capability, says which level it's at and what it may do instead.
 * An Off agent holds no capability at all, so any refusal it meets is its level: that includes the adapter's own
 * abilities, whose "read" check is what an Off agent fails first over MCP. Other errors, and every result for people,
 * pass through unchanged.
 *
 * @param mixed  $result The permission or execution result.
 * @param string $name   The ability's name.
 * @return mixed
 */
function name_the_level( $result, $name ) {
	if ( ! Agents\current_is_agent() ) {
		return $result;
	}
	$level = Agents\level_of( get_current_user_id() );
	if ( false === $result || ( 'off' === $level && is_wp_error( $result ) ) ) {
		return new \WP_Error( 'agent_ready_level', level_reason( $level ), array( 'status' => 403, 'level' => $level, 'ability' => $name ) );
	}
	if ( is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'forbidden', 'ability_invalid_permissions', 'rest_forbidden' ), true ) ) {
		return new \WP_Error(
			'agent_ready_level',
			trim( $result->get_error_message() . ' ' . level_reason( $level ) ),
			array( 'status' => 403, 'level' => $level, 'ability' => $name )
		);
	}
	return $result;
}

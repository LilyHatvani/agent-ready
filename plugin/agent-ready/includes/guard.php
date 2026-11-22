<?php
/**
 * The lines an agent never crosses, at any level (docs/decisions/0001-levels-are-roles.md).
 *
 * 1. No site administration: an agent never holds the capabilities below, whatever role someone gives it. So an agent
 *    can't change its own or anyone's level, install code, or change settings.
 *    One capability is added instead: an agent with any role may upload media, Propose included, because a draft
 *    without its pictures isn't a draft (WordPress's Contributor can't upload). The trade, said in the docs: an upload
 *    has a public file address as soon as it exists, like every upload in WordPress.
 * 2. No permanent deletes: an agent can move posts and media to the trash, never delete them for good or empty the
 *    trash. What an agent trashed waits for the owner: WordPress's automatic trash emptying skips it.
 * 3. A schedule is judged when it fires: a post an agent scheduled goes live only if that agent can still publish it
 *    at that moment. Otherwise it goes back to draft.
 *
 * @package AgentReady
 */

namespace AgentReady\Guard;

use function AgentReady\Agents\current_is_agent;
use function AgentReady\Agents\is_agent;

defined( 'ABSPATH' ) || exit;

/** Capabilities an agent never holds. */
const DENIED_CAPS = array(
	// Site and code.
	'manage_options',
	'install_plugins',
	'activate_plugins',
	'update_plugins',
	'delete_plugins',
	'edit_plugins',
	'install_themes',
	'switch_themes',
	'update_themes',
	'delete_themes',
	'edit_themes',
	'edit_files',
	'update_core',
	'import',
	'export',
	// People and levels.
	'create_users',
	'edit_users',
	'promote_users',
	'delete_users',
	'remove_users',
	// Raw HTML and scripts in content: agent text is treated as untrusted and goes through KSES.
	'unfiltered_html',
	'unfiltered_upload',
);

/** Post meta naming the agent that trashed a post. */
const TRASHED_BY = '_agent_ready_trashed_by';

/** Post meta naming the agent that scheduled a post. */
const SCHEDULED_BY = '_agent_ready_scheduled_by';

/** Hooks the guard in. */
function register(): void {
	add_filter( 'user_has_cap', __NAMESPACE__ . '\\adjust_caps', 999, 4 );
	add_action( 'wp_trash_post', __NAMESPACE__ . '\\record_trash' );
	add_action( 'untrashed_post', __NAMESPACE__ . '\\clear_trash_record' );
	add_filter( 'pre_delete_post', __NAMESPACE__ . '\\refuse_permanent_delete', 999, 2 );
	add_filter( 'pre_delete_attachment', __NAMESPACE__ . '\\refuse_permanent_delete', 999, 2 );
	add_action( 'transition_post_status', __NAMESPACE__ . '\\record_schedule', 10, 3 );
	// Before core's check_and_publish_future_post (priority 10).
	add_action( 'publish_future_post', __NAMESPACE__ . '\\judge_schedule', 9 );
}

/**
 * Removes DENIED_CAPS from an agent, whatever its role, and lets an agent that has a role upload media.
 *
 * @param bool[]   $allcaps All the user's capabilities.
 * @param string[] $caps    Primitive caps being checked.
 * @param array    $args    The check's arguments.
 * @param \WP_User $user    The user.
 * @return bool[]
 */
function adjust_caps( array $allcaps, array $caps, array $args, $user ): array {
	if ( ! $user instanceof \WP_User || ! is_agent( (int) $user->ID ) ) {
		return $allcaps;
	}
	if ( ! empty( $user->roles ) ) {
		$allcaps['upload_files'] = true;
	}
	foreach ( DENIED_CAPS as $cap ) {
		$allcaps[ $cap ] = false;
	}
	return $allcaps;
}

/**
 * Notes which agent trashed a post, so automatic trash emptying leaves it for the owner.
 *
 * @param int $post_id Post being trashed.
 */
function record_trash( int $post_id ): void {
	if ( current_is_agent() ) {
		update_post_meta( $post_id, TRASHED_BY, get_current_user_id() );
	}
}

/**
 * A restored post is no longer the agent's trash.
 *
 * @param int $post_id Restored post.
 */
function clear_trash_record( int $post_id ): void {
	delete_post_meta( $post_id, TRASHED_BY );
}

/**
 * Refuses a permanent delete made by an agent, and the scheduled emptying of anything an agent trashed.
 * Returning false makes wp_delete_post() / wp_delete_attachment() stop and return false.
 *
 * @param mixed    $check Null to let the delete go ahead.
 * @param \WP_Post $post  The post or attachment.
 * @return mixed
 */
function refuse_permanent_delete( $check, $post ) {
	if ( null !== $check ) {
		return $check;
	}
	if ( current_is_agent() ) {
		return false;
	}
	if ( wp_doing_cron() && $post instanceof \WP_Post && '' !== (string) get_post_meta( $post->ID, TRASHED_BY, true ) ) {
		return false;
	}
	return $check;
}

/**
 * Records who scheduled a post: an agent's schedule is judged again when it fires, the owner's isn't.
 *
 * @param string   $new_status New status.
 * @param string   $old_status Old status.
 * @param \WP_Post $post       The post.
 */
function record_schedule( string $new_status, string $old_status, $post ): void {
	if ( 'future' !== $new_status || ! $post instanceof \WP_Post ) {
		return;
	}
	if ( current_is_agent() ) {
		update_post_meta( $post->ID, SCHEDULED_BY, get_current_user_id() );
	} elseif ( get_current_user_id() > 0 ) {
		// A person (re)scheduled it: theirs now.
		delete_post_meta( $post->ID, SCHEDULED_BY );
	}
}

/**
 * When an agent's scheduled post comes due, it goes live only if that agent still exists, is still an agent, and can
 * still publish it. Otherwise it goes back to draft, untouched otherwise.
 *
 * @param int $post_id The post coming due.
 */
function judge_schedule( int $post_id ): void {
	$scheduler = (int) get_post_meta( $post_id, SCHEDULED_BY, true );
	if ( 0 === $scheduler ) {
		return;
	}
	if ( is_agent( $scheduler ) && user_can( $scheduler, 'publish_post', $post_id ) ) {
		return;
	}
	delete_post_meta( $post_id, SCHEDULED_BY );
	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'draft',
		)
	);
}

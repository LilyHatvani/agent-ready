<?php
/**
 * Integration checks against a real WordPress, run with `wp eval-file tests/integration.php` (tests/run.sh).
 * Each check exercises the guard through WordPress's own functions, as an agent and as the owner.
 * Prints one line per check and exits non-zero if any fails.
 *
 * @package AgentReady
 */

use AgentReady\Agents;

$failures = 0;

$check = static function ( string $name, bool $ok ) use ( &$failures ): void {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . "\n";
	if ( ! $ok ) {
		++$failures;
	}
};

$owner = get_user_by( 'login', 'owner' );
if ( ! $owner ) {
	echo "FAIL setup: no user 'owner'\n";
	exit( 1 );
}
$owner_id = (int) $owner->ID;

// ---- An agent starts at Propose. --------------------------------------------------------------------------------
wp_set_current_user( $owner_id );
$agent_id = Agents\create( 'claude-' . wp_generate_password( 6, false ) );
$check( 'an agent is created', is_int( $agent_id ) );
$check( 'a new agent is at Propose (Contributor)', 'propose' === Agents\level_of( $agent_id ) );

// ---- Propose: drafts and uploads, no publishing, no administration. ----------------------------------------------
wp_set_current_user( $agent_id );
$check( 'Propose can write drafts', current_user_can( 'edit_posts' ) );
$check( 'Propose can upload media', current_user_can( 'upload_files' ) );
$check( 'Propose cannot publish', ! current_user_can( 'publish_posts' ) );
$check( 'Propose cannot manage options', ! current_user_can( 'manage_options' ) );
$check( 'Propose cannot change roles', ! current_user_can( 'promote_users' ) );
$check( 'an agent cannot set its own level', false === Agents\set_level( $agent_id, 'full' ) && 'propose' === Agents\level_of( $agent_id ) );

// ---- Full: no administration either, even as Editor or as Administrator set by hand. ------------------------------
wp_set_current_user( $owner_id );
Agents\set_level( $agent_id, 'full' );
wp_set_current_user( $agent_id );
$check( 'Full is Editor', 'full' === Agents\level_of( $agent_id ) );
$check( 'Full can publish', current_user_can( 'publish_posts' ) );
$check( 'Full can edit others\' posts', current_user_can( 'edit_others_posts' ) );
$check( 'Full has no unfiltered HTML', ! current_user_can( 'unfiltered_html' ) );

wp_set_current_user( $owner_id );
get_userdata( $agent_id )->set_role( 'administrator' );
wp_set_current_user( $agent_id );
$check( 'an agent made Administrator by hand still cannot manage options', ! current_user_can( 'manage_options' ) );
$check( 'an agent made Administrator by hand still cannot install plugins', ! current_user_can( 'install_plugins' ) );
$check( 'an agent made Administrator by hand still cannot edit users', ! current_user_can( 'edit_users' ) );
wp_set_current_user( $owner_id );
Agents\set_level( $agent_id, 'full' );

// ---- No permanent deletes by an agent; its trash waits for the owner. ---------------------------------------------
wp_set_current_user( $owner_id );
$post_id = wp_insert_post(
	array(
		'post_title'  => 'Owner post',
		'post_status' => 'publish',
	)
);

wp_set_current_user( $agent_id );
$check( 'Full cannot delete a post for good', false === wp_delete_post( $post_id, true ) );
$check( 'Full can trash a post', false !== wp_trash_post( $post_id ) && 'trash' === get_post_status( $post_id ) );
$check( 'Full cannot empty it from the trash', false === wp_delete_post( $post_id, true ) && null !== get_post( $post_id ) );

// WordPress's scheduled trash emptying, with the trash old enough to go.
wp_set_current_user( 0 );
update_post_meta( $post_id, '_wp_trash_meta_time', time() - 90 * DAY_IN_SECONDS );
add_filter( 'wp_doing_cron', '__return_true' );
wp_scheduled_delete();
remove_filter( 'wp_doing_cron', '__return_true' );
$check( 'scheduled emptying leaves what an agent trashed', null !== get_post( $post_id ) );

wp_set_current_user( $owner_id );
$check( 'the owner can delete it for good', false !== wp_delete_post( $post_id, true ) && null === get_post( $post_id ) );

// ---- A schedule is judged when it fires. ------------------------------------------------------------------------
wp_set_current_user( $owner_id );
Agents\set_level( $agent_id, 'publish' );

wp_set_current_user( $agent_id );
$future   = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
$sched_id = wp_insert_post(
	array(
		'post_title'    => 'Agent schedule',
		'post_status'   => 'future',
		'post_date_gmt' => $future,
		'post_date'     => get_date_from_gmt( $future ),
	)
);
$check( 'Publish can schedule', 'future' === get_post_status( $sched_id ) );

// The owner lowers the agent to Propose before it fires; then it comes due.
wp_set_current_user( $owner_id );
Agents\set_level( $agent_id, 'propose' );
wp_set_current_user( 0 );
wp_update_post(
	array(
		'ID'            => $sched_id,
		'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 60 ),
		'post_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', time() - 60 ) ),
	)
);
do_action( 'publish_future_post', $sched_id );
$check( 'a schedule from an agent lowered to Propose goes back to draft', 'draft' === get_post_status( $sched_id ) );

// An agent that still holds Publish when its schedule fires goes live.
wp_set_current_user( $owner_id );
Agents\set_level( $agent_id, 'publish' );
wp_set_current_user( $agent_id );
$future2 = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
$live_id = wp_insert_post(
	array(
		'post_title'    => 'Agent schedule kept',
		'post_status'   => 'future',
		'post_date_gmt' => $future2,
		'post_date'     => get_date_from_gmt( $future2 ),
	)
);
wp_set_current_user( 0 );
global $wpdb;
$past = gmdate( 'Y-m-d H:i:s', time() - 60 );
$wpdb->update( $wpdb->posts, array( 'post_date_gmt' => $past, 'post_date' => get_date_from_gmt( $past ) ), array( 'ID' => $live_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
clean_post_cache( $live_id );
do_action( 'publish_future_post', $live_id );
$check( 'a schedule from an agent that can still publish goes live', 'publish' === get_post_status( $live_id ) );

// ---- Off: no role, nothing. -------------------------------------------------------------------------------------
wp_set_current_user( $owner_id );
Agents\set_level( $agent_id, 'off' );
wp_set_current_user( $agent_id );
$check( 'Off cannot write', ! current_user_can( 'edit_posts' ) );
$check( 'Off cannot upload', ! current_user_can( 'upload_files' ) );

echo $failures ? "\n{$failures} check(s) failed\n" : "\nall checks passed\n";
exit( $failures ? 1 : 0 );

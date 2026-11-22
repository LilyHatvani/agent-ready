<?php
/**
 * Users → Agents: the owner's page for creating agents and setting their levels.
 *
 * Only a user who can manage options sees it, and an agent never can (guard.php), so no agent reaches this page or
 * changes a level.
 *
 * @package AgentReady
 */

namespace AgentReady\Admin;

use AgentReady\Agents;

defined( 'ABSPATH' ) || exit;

const PAGE   = 'agent-ready';
const ACTION = 'agent_ready_save';

/** Hooks the page in. */
function register(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\\add_page' );
	add_action( 'admin_post_' . ACTION, __NAMESPACE__ . '\\handle_post' );
}

/** Adds Users → Agents. */
function add_page(): void {
	add_users_page(
		__( 'Agents', 'agent-ready' ),
		__( 'Agents', 'agent-ready' ),
		'manage_options',
		PAGE,
		__NAMESPACE__ . '\\render'
	);
}

/** Human labels for the levels. */
function level_labels(): array {
	return array(
		'propose' => __( 'Propose: writes drafts, you publish', 'agent-ready' ),
		'publish' => __( 'Publish: publishes and schedules its own posts', 'agent-ready' ),
		'full'    => __( 'Full: runs the site\'s content, everyone\'s posts included', 'agent-ready' ),
		'off'     => __( 'Off: can\'t do anything', 'agent-ready' ),
	);
}

/** Renders the page. */
function render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$labels = level_labels();
	$notice = isset( $_GET['agent_ready'] ) ? sanitize_key( wp_unslash( $_GET['agent_ready'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Agents', 'agent-ready' ); ?></h1>
		<p><?php esc_html_e( 'An agent is an AI you connect over MCP. Its level decides what it may do without you. Whatever the level, it never changes settings, users or plugins, never deletes anything for good, and every edit it makes is a revision you can restore.', 'agent-ready' ); ?></p>

		<?php if ( 'saved' === $notice ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Saved.', 'agent-ready' ); ?></p></div>
		<?php elseif ( 'confirm_full' === $notice ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Not saved: to give an agent Full, tick the box that says you understand what it means.', 'agent-ready' ); ?></p></div>
		<?php elseif ( 'error' === $notice ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Not saved: that username can\'t be used. Try another one.', 'agent-ready' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( ACTION ); ?>" />
			<?php wp_nonce_field( ACTION ); ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Agent', 'agent-ready' ); ?></th>
						<th><?php esc_html_e( 'Level', 'agent-ready' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( Agents\all() as $agent ) : ?>
					<?php $current = Agents\level_of( (int) $agent->ID ); ?>
					<tr>
						<td><?php echo esc_html( $agent->user_login ); ?></td>
						<td>
							<select name="levels[<?php echo esc_attr( (string) $agent->ID ); ?>]">
								<?php if ( 'custom' === $current ) : ?>
									<option value="" selected><?php esc_html_e( 'Custom role (set by hand); choose a level to replace it', 'agent-ready' ); ?></option>
								<?php endif; ?>
								<?php foreach ( $labels as $level => $label ) : ?>
									<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $current, $level ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
					<tr>
						<td>
							<label for="agent-ready-new"><?php esc_html_e( 'New agent', 'agent-ready' ); ?></label>
							<input id="agent-ready-new" type="text" name="new_agent" placeholder="claude" />
						</td>
						<td><?php echo esc_html( $labels[ Agents\DEFAULT_LEVEL ] ); ?></td>
					</tr>
				</tbody>
			</table>

			<p>
				<label>
					<input type="checkbox" name="confirm_full" value="1" />
					<?php esc_html_e( 'I understand that an agent at Full publishes, changes and trashes any post without asking me. I can see and undo what it did in each post\'s revisions and in the trash.', 'agent-ready' ); ?>
				</label>
			</p>

			<?php submit_button( __( 'Save', 'agent-ready' ) ); ?>
		</form>
	</div>
	<?php
}

/** Saves levels and creates a new agent. */
function handle_post(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Only the site owner can change agents.', 'agent-ready' ), 403 );
	}
	check_admin_referer( ACTION );

	$back    = admin_url( 'users.php?page=' . PAGE );
	$levels  = isset( $_POST['levels'] ) && is_array( $_POST['levels'] ) ? wp_unslash( $_POST['levels'] ) : array();
	$confirm = ! empty( $_POST['confirm_full'] );

	// Full is given only with the box ticked, and only when it's a change: re-saving an existing Full needs no tick.
	foreach ( $levels as $user_id => $level ) {
		$level = sanitize_key( (string) $level );
		if ( 'full' === $level && 'full' !== Agents\level_of( (int) $user_id ) && ! $confirm ) {
			wp_safe_redirect( add_query_arg( 'agent_ready', 'confirm_full', $back ) );
			exit;
		}
	}

	foreach ( $levels as $user_id => $level ) {
		$level = sanitize_key( (string) $level );
		if ( '' !== $level && Agents\level_of( (int) $user_id ) !== $level ) {
			Agents\set_level( (int) $user_id, $level );
		}
	}

	$new = isset( $_POST['new_agent'] ) ? sanitize_user( wp_unslash( (string) $_POST['new_agent'] ), true ) : '';
	if ( '' !== $new && is_wp_error( Agents\create( $new ) ) ) {
		wp_safe_redirect( add_query_arg( 'agent_ready', 'error', $back ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'agent_ready', 'saved', $back ) );
	exit;
}

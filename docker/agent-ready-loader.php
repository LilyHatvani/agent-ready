<?php
/**
 * Plugin Name: Agent Ready loader
 * Description: Keeps the MCP Adapter and Agent Ready always on in this image, so a redeploy can't leave them off.
 *
 * The image ships them in wp-content/plugins; this must-use file loads them before regular plugins. They show in
 * Plugins as inactive (WordPress doesn't know they were loaded here); activating them there is harmless, because
 * each main file below is loaded once.
 *
 * @package AgentReady
 */

defined( 'ABSPATH' ) || exit;

foreach ( array( 'mcp-adapter/mcp-adapter.php', 'agent-ready/agent-ready.php' ) as $agent_ready_plugin ) {
	$agent_ready_file = WP_PLUGIN_DIR . '/' . $agent_ready_plugin;
	if ( is_readable( $agent_ready_file ) ) {
		require_once $agent_ready_file;
	}
}
unset( $agent_ready_plugin, $agent_ready_file );

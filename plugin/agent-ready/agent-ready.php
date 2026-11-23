<?php
/**
 * Plugin Name:       Agent Ready
 * Description:       Lets an AI agent write on your site with the reach you choose: Propose, Publish or Full. Everything it does stays undoable.
 * Version:           0.1.0
 * Requires at least: 7.1
 * Requires PHP:      8.0
 * Author:            Lenka Scherfel
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agent-ready
 *
 * @package AgentReady
 */

defined( 'ABSPATH' ) || exit;

define( 'AGENT_READY_VERSION', '0.1.0' );

require_once __DIR__ . '/includes/agents.php';
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/abilities.php';

AgentReady\Guard\register();
AgentReady\Admin\register();
AgentReady\Connect\register();
AgentReady\Abilities\register();

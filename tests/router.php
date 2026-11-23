<?php
/**
 * Router for PHP's built-in server in tests/run.sh: real files as they are, a directory's index.php, everything else to
 * WordPress (pretty permalinks, so the OAuth server's /oauth/* addresses resolve). WP_ROOT is the WordPress directory.
 *
 * @package AgentReady
 */

$root = rtrim( getenv( 'WP_ROOT' ) ?: '/var/www/html', '/' );
$path = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = $root . $path;
if ( is_dir( $file ) && is_file( rtrim( $file, '/' ) . '/index.php' ) ) {
	$file = rtrim( $file, '/' ) . '/index.php';
}
if ( is_file( $file ) && ! str_ends_with( $file, '.php' ) ) {
	return false;
}
if ( ! is_file( $file ) ) {
	$file = $root . '/index.php';
}
$_SERVER['SCRIPT_NAME'] = substr( $file, strlen( $root ) );
chdir( dirname( $file ) );
require $file;

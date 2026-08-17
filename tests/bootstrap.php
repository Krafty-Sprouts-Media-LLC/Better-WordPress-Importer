<?php
/**
 * Bootstrap the plugin unit testing environment.
 *
 * @package WordPress_Importer
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Determine where the WP test suite lives.
 *
 * Prefers the modern `wp-phpunit/wp-phpunit` Composer package; falls back
 * to the older environment-variable-based checkouts (WP_DEVELOP_DIR,
 * WP_TESTS_DIR, or a /tmp checkout) for anyone still using those.
 */
$test_root = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $test_root ) {
	$test_root = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( $test_root . '/includes/functions.php' ) ) {
	if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
		$test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
	} elseif ( false !== getenv( 'WP_TESTS_DIR' ) ) {
		$test_root = getenv( 'WP_TESTS_DIR' );
	} elseif ( file_exists( '/tmp/wordpress-tests-lib/includes/functions.php' ) ) {
		$test_root = '/tmp/wordpress-tests-lib';
	}
}

require $test_root . '/includes/functions.php';

/**
 * Manually load the plugin under test.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/plugin.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) && file_exists( __DIR__ . '/wp-tests-config.php' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
}

require $test_root . '/includes/bootstrap.php';

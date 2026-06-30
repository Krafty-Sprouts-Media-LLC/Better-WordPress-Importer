<?php
/**
 * Plugin Name: Better WordPress Importer
 * Plugin URI: https://github.com/Krafty-Sprouts-Media-LLC/KSM-WordPress-Importer
 * Description: Import posts, pages, comments, custom fields, categories, tags and more from a WordPress export file. Resumable, batch-based, large-file safe.
 * Version: 1.0.0
 * Author: Krafty Sprouts Media, LLC
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: better-wordpress-importer
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package Better_WordPress_Importer
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'BETTER_IMPORTER_VERSION' ) ) {
	define( 'BETTER_IMPORTER_VERSION', '1.0.0' );
}

if ( ! defined( 'BETTER_IMPORTER_PATH' ) ) {
	define( 'BETTER_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'BETTER_IMPORTER_URL' ) ) {
	define( 'BETTER_IMPORTER_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Load a plugin class file.
 *
 * @since 1.0.0
 *
 * @param string $relative_path Path relative to the plugin root.
 *
 * @return void
 */
function better_importer_require( $relative_path ) {
	require_once BETTER_IMPORTER_PATH . ltrim( $relative_path, '/' );
}

better_importer_require( 'src/Core/class-better-install.php' );
better_importer_require( 'src/Importer/class-better-preflight.php' );
better_importer_require( 'src/Core/class-better-import-queue-item.php' );
better_importer_require( 'src/Core/class-better-import-queue-repository.php' );
better_importer_require( 'src/Core/class-better-import-job.php' );
better_importer_require( 'src/Core/class-better-import-job-repository.php' );

register_activation_hook( __FILE__, array( 'Better_Install', 'activate' ) );

/**
 * Bootstrap the plugin after WordPress loads.
 *
 * @since 1.0.0
 *
 * @return void
 */
function better_importer_init() {
	if ( is_admin() ) {
		Better_Install::install_tables();
	}
}
add_action( 'plugins_loaded', 'better_importer_init' );

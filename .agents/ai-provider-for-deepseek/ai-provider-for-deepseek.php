<?php
/**
 * The plugin bootstrap file.
 *
 * @package           Sajjad67\AiProviderForDeepSeek
 * @author            Sajjad Hossain Sagor <sagorh672@gmail.com>
 *
 * Plugin Name:       AI Provider For DeepSeek
 * Plugin URI:        https://wordpress.org/plugins/ai-provider-for-deepseek/
 * Description:       DeepSeek AI provider for the PHP AI Client SDK.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Sajjad Hossain Sagor
 * Author URI:        https://sajjadhsagor.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ai-provider-for-deepseek
 * Domain Path:       /languages
 */

declare(strict_types=1);

namespace Sajjad67\AiProviderForDeepSeek;

use WordPress\AiClient\AiClient;
use Sajjad67\AiProviderForDeepSeek\Provider\DeepSeekProvider;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Plugin constants.
define( 'AIPRFD_AI_PROVIDER_FOR_DEEPSEEK_PLUGIN_VERSION', '1.0.0' );
define( 'AIPRFD_AI_PROVIDER_FOR_DEEPSEEK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Autoloader.
spl_autoload_register(
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
	function ( string $class ): void {
		$prefix   = 'Sajjad67\AiProviderForDeepSeek\\';
		$base_dir = AIPRFD_AI_PROVIDER_FOR_DEEPSEEK_PLUGIN_DIR . 'src/';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Registers the AI Provider for DeepSeek with the AI Client.
 *
 * @since  1.0.0
 * @return void
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( $registry->hasProvider( DeepSeekProvider::class ) ) {
		return;
	}

	$registry->registerProvider( DeepSeekProvider::class );
}

add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

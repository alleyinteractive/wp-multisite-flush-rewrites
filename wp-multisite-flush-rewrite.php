<?php
/**
 * Plugin Name: WP Multisite Flush Rewrite
 * Plugin URI: https://github.com/alleyinteractive/wp-multisite-flush-rewrite
 * Description: Flush rewrite rules on a WordPress multisite.
 * Version: 0.0.0
 * Author: Sean Fisher
 * Author URI: https://github.com/alleyinteractive/wp-multisite-flush-rewrite
 * Requires at least: 5.9
 * Requires PHP: 8.2
 * Tested up to: 6.7
 *
 * Text Domain: wp-multisite-flush-rewrite
 * Domain Path: /languages/
 *
 * @package wp-multisite-flush-rewrite
 */

namespace Alley\WP\Multisite_Flush_Rewrite;

if ( ! defined( 'ABSPATH' ) || ! is_multisite() ) {
	exit;
}

// Check if Composer is installed (remove if Composer is not required for your plugin).
if ( ! file_exists( __DIR__ . '/vendor/wordpress-autoload.php' ) ) {
	// Will also check for the presence of an already loaded Composer autoloader
	// to see if the Composer dependencies have been installed in a parent
	// folder. This is useful for when the plugin is loaded as a Composer
	// dependency in a larger project.
	if ( ! class_exists( \Composer\InstalledVersions::class ) ) {
		\add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Composer is not installed and wp-multisite-flush-rewrite cannot load. Try using a `*-built` branch if the plugin is being loaded as a submodule.', 'wp-multisite-flush-rewrite' ); ?></p>
				</div>
				<?php
			}
		);

		return;
	}
} else {
	// Load Composer dependencies.
	require_once __DIR__ . '/vendor/wordpress-autoload.php';
}

// Load the plugin's main files.
require_once __DIR__ . '/src/main.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command(
		'multisite-flush-rewrites',
		__NAMESPACE__ . '\flush_rewrite_rules_command',
		[
			'shortdesc' => __( 'Flush the rewrite rules for all sites in the network.', 'wp-multisite-flush-rewrite' ),
			'synopsis'  => '[--network-id=<id>]',
		]
	);
}

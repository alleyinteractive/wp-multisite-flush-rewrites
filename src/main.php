<?php
/**
 * The main plugin function
 *
 * @package wp-multisite-flush-rewrite
 */

namespace Alley\WP\Multisite_Flush_Rewrite;

use Mantle\Http_Client\Factory;
use Mantle\Http_Client\Pool;
use Mantle\Http_Client\Pooled_Pending_Request;

use function Mantle\Support\Helpers\collect;

/**
 * The name of the option used to store the secret token in the network.
 */
const SECRET_OPTION_NAME = 'wp_multisite_flush_rewrite_secret';

/**
 * Flush the network rewrite rules.
 *
 * Go through each site and make a request to flush the rewrite rules for each.
 * This does not use switch_to_blog() but instead makes an individual request to
 * each site's admin-ajax.php.
 *
 * @param int|null $network_id The network ID to flush rewrite rules for. If null, the current network ID is used.
 * @return array<int|string, \Mantle\Http_Client\Response>
 */
function flush_network_rewrite_rules( ?int $network_id = null ): array {
	if ( is_null( $network_id ) ) {
		$network_id = get_current_network_id();
	}

	$requests = [];

	// Ensure the secret token is set for the site.
	$secret = get_network_option( $network_id, SECRET_OPTION_NAME );

	if ( empty( $secret ) ) {
		$secret = md5( wp_generate_password( 32, false ) );

		update_network_option( $network_id, SECRET_OPTION_NAME, $secret );
	}

	foreach ( get_sites( [
		'archived'   => 0,
		'network_id' => $network_id,
		'public'     => 1,
	] ) as $site ) {
		switch_to_blog( (int) $site->blog_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog

		$requests[ home_url() ] = admin_url( 'admin-ajax.php?action=wp_multisite_flush_rewrite_rules' );

		restore_current_blog();
	}

	$requests = Factory::create()->pool(
		fn ( Pool $pool ) => collect( $requests )->map( // @phpstan-ignore-line argument.type
			fn ( $url, $blog ): Pooled_Pending_Request => $pool // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
				->as( $blog )
				->as_form()
				->post( $url, [
					'secret' => $secret,
				] )
		)->all()
	);

	delete_network_option( $network_id, SECRET_OPTION_NAME );

	return $requests;
}

/**
 * Handle the AJAX request to flush the rewrite rules.
 */
function handle_ajax_flush_rewrite_rules(): void {
	if (
		! isset( $_POST['secret'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		// @phpstan-ignore-next-line argument.type
		|| sanitize_text_field( wp_unslash( $_POST['secret'] ) ) !== get_network_option( get_current_network_id(), SECRET_OPTION_NAME ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
	) {
		wp_send_json_error( 'Invalid secret token.', 403 );
	}

	flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules

	wp_send_json_success();
}
add_action( 'wp_ajax_nopriv_wp_multisite_flush_rewrite_rules', __NAMESPACE__ . '\handle_ajax_flush_rewrite_rules' );

/**
 * Register a network admin page under settings that will display the flush
 * rewrite rules form.
 */
function register_network_admin_page(): void {
	if ( ! is_network_admin() ) {
		return;
	}

	\add_submenu_page(
		'settings.php',
		__( 'Flush Network Rewrite Rules', 'wp-multisite-flush-rewrite' ),
		__( 'Flush Network Rewrite Rules', 'wp-multisite-flush-rewrite' ),
		'manage_options',
		'wp-multisite-flush-rewrite',
		__NAMESPACE__ . '\render_settings_page',
	);
}
add_action( 'network_admin_menu', __NAMESPACE__ . '\register_network_admin_page' );

/**
 * Render the settings page.
 */
function render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['wp_multisite_flush_rewrite_rules_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_multisite_flush_rewrite_rules_nonce'] ) ), 'wp_multisite_flush_rewrite_rules' ) ) { // @phpstan-ignore-line argument.type
			wp_die( esc_html__( 'Nonce verification failed. Please try again.', 'wp-multisite-flush-rewrite' ) );
		}

		$results = flush_network_rewrite_rules();

		$success = 0;

		foreach ( $results as $blog => $request ) {
			if ( ! $request->ok() ) {
				wp_admin_notice(
					sprintf(
						/* translators: 1: blog name, 2: error message */
						esc_html__( 'Failed to flush rewrite rules for %1$s: %2$s', 'wp-multisite-flush-rewrite' ),
						"<code>{$blog}</code>",
						"{$request->status()}: " . ( $request->body() ?: 'Unknown error' ),
					),
					[
						'type' => 'error',
					]
				);
			} else {
				$success++;
			}
		}

		if ( $success !== 0 ) {
			wp_admin_notice(
				sprintf(
					/* translators: %d: number of sites */
					__( 'Successfully flushed rewrite rules for %d sites.', 'wp-multisite-flush-rewrite' ),
					$success
				),
				[ 'type' => 'success' ],
			);
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Flush Network Rewrite Rules', 'wp-multisite-flush-rewrite' ); ?></h1>
		<form method="post">
			<p>
				<?php esc_html_e( 'Click the button below to flush the network rewrite rules for all sites in the network.', 'wp-multisite-flush-rewrite' ); ?>
			</p>
			<?php wp_nonce_field( 'wp_multisite_flush_rewrite_rules', 'wp_multisite_flush_rewrite_rules_nonce' ); ?>
			<?php submit_button( esc_html__( 'Flush Network Rewrite Rules', 'wp-multisite-flush-rewrite' ) ); ?>
		</form>
	</div>
	<?php
}

<?php
/**
 * WP Multisite Flush Rewrite Tests: Plugin Test
 *
 * @package wp-multisite-flush-rewrite
 */

namespace Alley\WP\Multisite_Flush_Rewrite\Tests\Feature;

use Alley\WP\Multisite_Flush_Rewrite\Tests\TestCase;
use Mantle\Http_Client\Request;

use function Alley\WP\Multisite_Flush_Rewrite\flush_network_rewrite_rules;
use function Mantle\Testing\mock_http_response;

use const Alley\WP\Multisite_Flush_Rewrite\FLUSH_REWRITES_SECRET_OPTION_NAME;

/**
 * A test suite for the plugin.
 */
class PluginTest extends TestCase {
	/**
	 * Test that it can clear the rewrite rules for multiple sites.
	 */
	public function test_it_can_clear_rewrite_rules_for_multiple_sites(): void {
		$this->fake_request( admin_url( 'admin-ajax.php*' ), mock_http_response()->with_status( 200 ) );
		$this->fake_request( 'http://example.test/wp-admin/admin-ajax.php*', mock_http_response()->with_status( 200 ) );
		$this->fake_request( 'http://another.test/wp-admin/admin-ajax.php*', mock_http_response()->with_status( 200 ) );
		$this->fake_request( 'http://fail.test/wp-admin/admin-ajax.php*', mock_http_response()->with_status( 400 ) );

		self::factory()->blog->create_and_get( [
			'domain' => 'example.test',
			'path'   => '/',
		] );
		self::factory()->blog->create_and_get( [
			'domain' => 'another.test',
			'path'   => '/',
		] );
		self::factory()->blog->create_and_get( [
			'domain' => 'fail.test',
			'path'   => '/',
		] );

		$pool = flush_network_rewrite_rules();

		$this->assertCount( 4, $pool );
		$this->assertTrue( $pool['http://example.org']->ok() );
		$this->assertTrue( $pool['http://example.test']->ok() );
		$this->assertTrue( $pool['http://another.test']->ok() );
		$this->assertTrue( $pool['http://fail.test']->failed() );

		$this->assertRequestCount( 4 );
		$this->assertRequestSent( admin_url( 'admin-ajax.php?action=wp_multisite_flush_rewrite_rules' ), 1 );
		$this->assertRequestSent( 'http://example.test/wp-admin/admin-ajax.php?action=wp_multisite_flush_rewrite_rules', 1 );
		$this->assertRequestSent( 'http://another.test/wp-admin/admin-ajax.php?action=wp_multisite_flush_rewrite_rules', 1 );
		$this->assertRequestSent( 'http://fail.test/wp-admin/admin-ajax.php?action=wp_multisite_flush_rewrite_rules', 1 );

		// Enforce that a secret was passed.
		$this->assertRequestSent( fn ( Request $request ): bool => ! empty( $request->get( 'body.secret' ) ) );

		// Enforce that the secret is cleared after use.
		$this->assertEmpty( get_network_option( null, FLUSH_REWRITES_SECRET_OPTION_NAME ) );
	}

	/**
	 * Test that redirects are not followed when flushing rewrite rules.
	 */
	public function test_it_does_not_follow_redirects_when_flushing_rewrite_rules(): void {
		// Set up a site that responds with a redirect
		$this->fake_request( admin_url( 'admin-ajax.php*' ), mock_http_response()->with_status( 200 ) );
		$this->fake_request( 'http://redirect.test/wp-admin/admin-ajax.php*', mock_http_response()->with_status( 302 )->with_header( 'Location', 'http://redirect.test/redirected' ) );

		self::factory()->blog->create_and_get( [
			'domain' => 'redirect.test',
			'path'   => '/',
		] );

		$pool = flush_network_rewrite_rules();

		$this->assertCount( 2, $pool );
		$this->assertTrue( $pool['http://example.org']->ok() );
		// The redirect response should be treated as received (302), not followed
		$this->assertEquals( 302, $pool['http://redirect.test']->status() );

		$this->assertRequestCount( 2 );
		$this->assertRequestSent( admin_url( 'admin-ajax.php?action=wp_multisite_flush_rewrite_rules' ), 1 );
		$this->assertRequestSent( 'http://redirect.test/wp-admin/admin-ajax.php?action=wp_multisite_flush_rewrite_rules', 1 );
		
		// Verify that no follow-up request was made to the redirect location
		$this->assertRequestNotSent( 'http://redirect.test/redirected' );

		// Enforce that allow_redirects is set to false in the request options
		$this->assertRequestSent( function ( Request $request ): bool {
			$options = $request->get( 'options' );
			return isset( $options['allow_redirects'] ) && $options['allow_redirects'] === false;
		} );
	}
}

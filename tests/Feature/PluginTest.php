<?php
/**
 * WP Multisite Flush Rewrite Tests: Plugin Test
 *
 * @package wp-multisite-flush-rewrite
 */

namespace Alley\WP\Multisite_Flush_Rewrite\Tests\Feature;

use Alley\WP\Multisite_Flush_Rewrite\Tests\TestCase;
use Mantle\Testing\Concerns\Prevent_Remote_Requests;

use function Alley\WP\Multisite_Flush_Rewrite\flush_network_rewrite_rules;
use function Mantle\Testing\mock_http_response;

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
	}
}

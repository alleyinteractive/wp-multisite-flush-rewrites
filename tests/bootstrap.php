<?php
/**
 * WP Multisite Flush Rewrite Tests: Bootstrap
 *
 * phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
 *
 * @package wp-multisite-flush-rewrite
 */

declare(strict_types=1);

/**
 * Visit {@see https://mantle.alley.com/testing/test-framework.html} to learn more.
 */
\Mantle\Testing\manager()
	->maybe_rsync_plugin()
	->with_multisite()
	->loaded( fn () => require_once __DIR__ . '/../wp-multisite-flush-rewrites.php' )
	->install();

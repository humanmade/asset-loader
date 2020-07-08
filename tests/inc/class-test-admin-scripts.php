<?php
/**
 * Test functions in the Admin_Scripts\Admin namespace.
 */

namespace Asset_Loader\Tests;

use Asset_Loader\Admin;
use WP_Mock;

class Test_Admin_Scripts extends Asset_Loader_Test_Case {
	/**
	 * Test the method used to conditionally set up localhost script load error warning banner.
	 *
	 * @dataProvider provide_maybe_setup_ssl_cert_error_handling_cases
	 */
	public function test_maybe_setup_ssl_cert_error_handling( bool $is_admin, string $script_uri, bool $expect_action, string $message ) : void {
		WP_Mock::userFunction( 'is_admin' )->andReturn( $is_admin );
		if ( $expect_action ) {
			WP_Mock::expectActionAdded( 'admin_head', 'Asset_Loader\\Admin\\render_localhost_error_detection_script', 5 );
		} else {
			WP_Mock::expectActionNotAdded( 'admin_head', 'Asset_Loader\\Admin\\render_localhost_error_detection_script' );
		}
		Admin\maybe_setup_ssl_cert_error_handling( $script_uri );

		$this->assertConditionsMet( $message );
	}

	/**
	 * Data provider for method that conditionally sets up script load error handler code.
	 *
	 * @return array
	 */
	public function provide_maybe_setup_ssl_cert_error_handling_cases() : array {
		return [
			[ false, 'https://localhost:9000/some-script.js', false, 'Should have no effect outside of the admin' ],
			[ true, 'https://some-non-local-domain.com/some-script.js', false, 'Should have no effect for non-local scripts' ],
			[ true, 'http://localhost:9000/some-script.js', false, 'Should have no effect for non-HTTPS scripts' ],
			// These next two cases intentionally use the same script.
			[ true, 'https://localhost:9000/some-script.js', true, 'Should set up error handlers for https://localhost scripts' ],
			[ true, 'https://localhost:9000/some-script.js', false, 'Should only bind action hooks the first time a matching script is found' ],
		];
	}

	/**
	 * Test the method used to add an onerror callback to script tags.
	 *
	 * @dataProvider provide_positive_script_filter_cases
	 * @dataProvider provide_negative_script_filter_cases
	 */
	public function test_add_onerror_to_localhost_scripts( bool $is_admin, string $script_tag, string $src, string $expected_script_tag, string $message ) : void {
		WP_Mock::userFunction( 'is_admin' )->andReturn( $is_admin );
		$filtered_tag = Admin\add_onerror_to_localhost_scripts( $script_tag, 'handle does not matter', $src );
		$this->assertEquals( $expected_script_tag, $filtered_tag, $message );
	}

	/**
	 * Data provider for script tag filtering when filter is applied.
	 */
	public function provide_positive_script_filter_cases() : array {
		return [
			[ true, '<script />', 'https://localhost:8000/script.js', '<script onerror="maybeSSLError && maybeSSLError( this );" />', 'https://localhost:8000 script tag should receive onerror handler' ],
			[ true, '<script />', 'https://localhost:9090/script.js', '<script onerror="maybeSSLError && maybeSSLError( this );" />', 'https://localhost:9090 script tag should receive onerror handler' ],
			[ true, '<script />', 'https://127.0.0.1:8000/script.js', '<script onerror="maybeSSLError && maybeSSLError( this );" />', 'https://127.0.0.1:8000 script tag should receive onerror handler' ],
		];
	}

	/**
	 * Data provider for script tag filtering when filter has no effect.
	 */
	public function provide_negative_script_filter_cases() : array {
		return [
			[ false, '<script />', 'https://localhost:8000/script.js', '<script />', 'script tag should not be filtered if is_admin() is false' ],
			[ true, '<script />', 'http://localhost:8000/script.js', '<script />', 'script tag should not be filtered if script URI is not HTTPS' ],
			[ true, '<script />', 'https://example.com/script.js', '<script />', 'script tag should not be filtered if script URI host is not localhost' ],
		];
	}
}

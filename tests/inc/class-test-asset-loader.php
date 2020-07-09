<?php
/**
 * Test methods exported by the base Asset_Loader namespace.
 */

declare( strict_types=1 );

namespace Asset_Loader\Tests;

use Asset_Loader;

class Test_Asset_Loader extends Asset_Loader_Test_Case {
	/**
	 * Test is_css() utility method.
	 *
	 * @dataProvider provide_is_css_cases
	 */
	public function test_is_css( $asset_uri, $expected, $message ) {
		$this->assertEquals( $expected, Asset_Loader\is_css( $asset_uri ), $message );
	}

	/**
	 * Test cases for is_css().
	 */
	public function provide_is_css_cases() {
		return [
			[ 'not-a-css-file.js', false, 'Should return false for JS assets' ],
			[ 'css-file.css', true, 'Should return true for CSS assets' ],
			[ 'css-file.css?with-query=params', true, 'Should return true for CSS assets with query parameters' ],
		];
	}
}

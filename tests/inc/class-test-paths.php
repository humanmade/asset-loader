<?php
/**
 * Test the path & URI helpers in the Asset_Loader\Paths namespace.
 */

namespace Asset_Loader\Tests;

use Asset_Loader\Paths;
use WP_Mock;

class Test_Paths extends Asset_Loader_Test_Case {
	public function setUp() : void {
		parent::setUp();

		WP_Mock::userFunction( 'trailingslashit' )
			->andReturnUsing( function( string $str ) : string {
				return preg_replace( '#/*$#', '/', $str );
			} );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test get_file_uri(), which is the primary function declared in
	 * the Paths namespace that is intended to be used elsewhere.
	 *
	 * @dataProvider provide_get_file_uri_cases
	 */
	public function test_get_file_uri( string $path, string $expected_uri, string $message ) : void {
		WP_Mock::userFunction( 'get_stylesheet_directory' )
			->andReturn( '/root/content/themes/child' );
		WP_Mock::userFunction( 'get_template_directory' )
			->andReturn( '/root/content/themes/parent' );
		WP_Mock::userFunction( 'get_theme_file_uri' )
			->andReturnUsing( function( string $theme_relative_path ) : string {
				// Do not bother distinguishing parent & child theme URIs in this test:
				// Trust that WP's get_theme_file_uri() works as advertised.
				return 'https://example.com/content/theme/' . $theme_relative_path;
			} );
		WP_Mock::userFunction( 'content_url' )
			->andReturnUsing( function( string $path ) : string {
				return 'https://example.com/content/' . $path;
			} );

		$uri = Paths\get_file_uri( $path );
		$this->assertEquals( $expected_uri, $uri, $message );
	}

	/**
	 * Test cases for get_file_uri().
	 */
	public function provide_get_file_uri_cases() : array {
		return [
			[ '/root/content/themes/child/child-file.js', 'https://example.com/content/theme/child-file.js', 'Child theme file should return theme resource URI' ],
			[ '/root/content/themes/parent/parent-file.js', 'https://example.com/content/theme/parent-file.js', 'Parent theme file should return theme resource URI' ],
			[ '/root/content/plugins/some-plugin/bundle.js', 'https://example.com/content/plugins/some-plugin/bundle.js', 'Plugin file path should return plugin resource URI' ],
			[ '/root/content/mu-plugins/gotta-use-it/bundle.min.js', 'https://example.com/content/mu-plugins/gotta-use-it/bundle.min.js', 'Must-use plugin file path should return mu-plugin resource URI' ],
		];
	}
}

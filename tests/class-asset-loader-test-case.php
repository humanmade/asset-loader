<?php
/**
 * Base class enabling WP_Mock.
 */

namespace Asset_Loader\Tests;

use WP_Mock;

class Asset_Loader_Test_Case extends WP_Mock\Tools\TestCase {
	public function setUp() : void {
		WP_Mock::setUp();
	}

	public function tearDown() : void {
		WP_Mock::tearDown();
	}
}

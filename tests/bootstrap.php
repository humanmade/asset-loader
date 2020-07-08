<?php
/**
 * Bootstrap our PHPUnit tests.
 */

namespace Asset_Loader\Tests;

use WP_Mock;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Now call the bootstrap method of WP Mock.
WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();

// Load in namespaces containing code to test.
require_once dirname( __DIR__ ) . '/inc/admin.php';
require_once dirname( __DIR__ ) . '/inc/manifest.php';
require_once dirname( __DIR__ ) . '/inc/namespace.php';
require_once dirname( __DIR__ ) . '/inc/paths.php';

// Load our base test case class.
require_once __DIR__ . '/class-asset-loader-test-case.php';

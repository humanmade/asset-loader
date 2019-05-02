<?php
/**
 * Asset Loader
 *
 * Manage loading Webpack Dev Server assets.
 *
 * @package   Asset_Loader
 * @author    K Adam White & contributors
 * @license   GPL-2.0+
 * @copyright 2019 K Adam White and Contributors
 *
 * @wordpress-plugin
 * Plugin Name: Asset Loader
 * Plugin URI:  https://github.com/humanmade/asset-loader
 * Description: Utilities to seamlessly consume Webpack-bundled assets in WordPress themes & plugins.
 * Version:     0.3.0
 * Author:      K Adam White
 * Author URI:  http://kadamwhite.com
 * License:     GPL-2.0+
 * License URI: https//github.com/humanmade/asset-loader/tree/master/LICENSE
 */
require_once( plugin_dir_path( __FILE__ ) . 'inc/manifest/namespace.php' );
require_once( plugin_dir_path( __FILE__ ) . 'inc/paths/namespace.php' );
require_once( plugin_dir_path( __FILE__ ) . 'inc/namespace.php' );

<?php
/**
 * Define functions for registering a block from metadata with asset-loader.
 */

declare( strict_types=1 );

namespace Asset_Loader\Blocks;

use WP_Block_Type;

/**
 * Get list of keys in block.json which should be filtered to load from a manifest.
 *
 * @return string[] An array of keys for block.json
 */
function get_asset_keys() : array {

	$asset_keys = [
		'editorScript',
		'script',
		'viewScript',
		'editorStyle',

	];

	return apply_filters( 'asset_loader_block_asset_keys', $asset_keys );
}

function register_block_type_with_manifests( string|WP_Block_Type $block_type, array $args = [] ) {

	// Early return if we are not using block.json.
	if ( ! is_string( $block_type ) ) {
		return \register_block_type( $block_type, $args );
	}

	add_filter( 'block_type_metadata', __NAMESPACE__ . '\\filter_block_metadata_scripts', 10, 1 );

	$block = \register_block_type( $block_type, $args );

	remove_filter( 'block_type_metadata', __NAMESPACE__ . '\\filter_block_metadata_scripts' );

	return $block;
}

function filter_block_metadata_scripts( $metadata ) {
	$asset_keys = get_asset_keys();
}

<?php
/**
 * Define utility functions for loading & parsing an asset manifest file.
 */
namespace Asset_Loader\Manifest;

/**
 * Attempt to load a file at the specified path and parse its contents as JSON.
 *
 * @param string $path The path to the JSON file to load.
 * @return array|null;
 */
function load_asset_file( $path ) {
	if ( ! file_exists( $path ) ) {
		return null;
	}
	$contents = file_get_contents( $path );
	if ( empty( $contents ) ) {
		return null;
	}
	return json_decode( $contents, true );
}

/**
 * Check a directory for a root or build asset manifest file, and attempt to
 * decode and return the asset list JSON if found.
 *
 * @param string $directory Root directory containing `src` and `build` directory.
 * @return array|null;
 */
function get_assets_list( string $manifest_path ) {
	$dev_assets = load_asset_file( $manifest_path );
	if ( ! empty( $dev_assets ) ) {
		return array_values( $dev_assets );
	}

	return null;
}

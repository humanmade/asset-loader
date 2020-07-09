<?php
/**
 * Define utility functions for loading & parsing an asset manifest file.
 */

namespace Asset_Loader\Manifest;

/**
 * Attempt to load a manifest at the specified path and parse its contents as JSON.
 *
 * @param string $path The path to the JSON file to load.
 * @return array|null;
 */
function load_asset_manifest( $path ) {
	// Avoid repeatedly opening & decoding the same file.
	static $manifests = [];

	if ( isset( $manifests[ $path ] ) ) {
		return $manifests[ $path ];
	}

	if ( ! file_exists( $path ) ) {
		return null;
	}
	$contents = file_get_contents( $path );
	if ( empty( $contents ) ) {
		return null;
	}

	$manifests[ $path ] = json_decode( $contents, true );

	return $manifests[ $path ];
}

/**
 * Check a directory for a root or build asset manifest file, and attempt to
 * decode and return the asset list JSON if found.
 *
 * @param string $manifest_path Absolute file system path to a JSON asset manifest.
 * @return array|null;
 */
function get_assets_list( string $manifest_path ) {
	$dev_assets = load_asset_manifest( $manifest_path );
	if ( ! empty( $dev_assets ) ) {
		return array_values( $dev_assets );
	}

	return null;
}

/**
 * Attempt to extract a specific value from an asset manifest file.
 *
 * @param string $manifest_path File system path for an asset manifest JSON file.
 * @param string $asset        Asset to retrieve within the specified manifest.
 *
 * @return string|null
 */
function get_manifest_resource( string $manifest_path, string $asset ) : ?string {
	$dev_assets = load_asset_manifest( $manifest_path );

	if ( ! isset( $dev_assets[ $asset ] ) ) {
		return null;
	}

	return $dev_assets[ $asset ];
}

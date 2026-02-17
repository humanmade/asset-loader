<?php
/**
 * Define the Asset_Loader namespace exposing methods for use in other themes & plugins.
 */

declare( strict_types=1 );

namespace Asset_Loader;

/**
 * Helper function to naively check whether or not a given URI is a CSS resource.
 *
 * @param string $uri A URI to test for CSS-ness.
 * @return boolean Whether that URI points to a CSS file.
 */
function is_css( string $uri ): bool {
	return preg_match( '/\.css(\?.*)?$/', $uri ) === 1;
}

/**
 * Register a script, or update an already-registered script using the provided
 * handle which did not declare dependencies of its own to use the dependencies
 * array passed in with a second registration request.
 *
 * @param string              $handle       Handle at which to register this script.
 * @param string              $asset_uri    URI of the registered script file.
 * @param string[]            $dependencies Array of script dependencies.
 * @param string|boolean|null $version      Optional version string for asset.
 * @param boolean             $in_footer    Whether to load this script in footer.
 * @return string
 */
function _register_or_update_script( string $handle, string $asset_uri, array $dependencies, $version = false, $in_footer = true ): ?string {
	// Handle the case where a `register_manifest_asset( 'foo.css' )` call falls back to
	// enqueue the dev bundle's JS. Since the dependencies provided in that CSS-
	// specific registration call would not apply to the world of scripts, but
	// a script asset would still get registered, we may need to update that new
	// script's registration to reflect an actual list of JS dependencies if we
	// later called `register_manifest_asset( 'foo.js' )`.
	if ( ! empty( $dependencies ) ) {
		$existing_scripts = wp_scripts();
		if ( isset( $existing_scripts->registered[ $handle ]->deps ) ) {
			if ( ! empty( $existing_scripts->registered[ $handle ]->deps ) ) {
				// We have dependencies, but so does the already-registered script.
				// This is a weird state, and may be an error in future releases.
				return null;
			}

			$existing_scripts->registered[ $handle ]->deps = $dependencies;

			// Updating those dependencies is assumed to be all that needs to be done.
			return $handle;
		}
	}
	wp_register_script( $handle, $asset_uri, $dependencies, $version, $in_footer );

	return $handle;
}

/**
 * Attempt to register a particular script bundle from a manifest.
 *
 * @param ?string $manifest_path File system path for an asset manifest JSON file.
 * @param string  $target_asset  Asset to retrieve within the specified manifest.
 * @param array   $options {
 *     @type string $handle       Handle to use when enqueuing the asset. Optional.
 *     @type array  $dependencies Script or Style dependencies. Optional.
 * }
 * @return array Array detailing which script and/or style handles got registered.
 */
function register_manifest_asset( ?string $manifest_path, string $target_asset, array $options = [] ): array {
	if ( empty( $manifest_path ) ) {
		trigger_error( sprintf( 'No manifest specified when loading %s', esc_attr( $target_asset ) ), E_USER_NOTICE );
		return [];
	}

	$defaults = [
		'dependencies' => [],
		'in-footer' => true,
	];
	$options = wp_parse_args( $options, $defaults );

	// Track whether we are falling back to a JS file because a CSS asset could not be found.
	$is_js_style_fallback = false;

	$manifest_folder = trailingslashit( dirname( $manifest_path ) );

	$asset_uri = Manifest\get_manifest_resource( $manifest_path, $target_asset );

	// If we fail to match a .css asset, try again with .js in case there is a
	// JS wrapper for that asset available (e.g. when using DevServer).
	if ( empty( $asset_uri ) && is_css( $target_asset ) ) {
		$asset_uri = Manifest\get_manifest_resource( $manifest_path, preg_replace( '/\.css$/', '.js', $target_asset ) );
		if ( ! empty( $asset_uri ) ) {
			$is_js_style_fallback = true;
		}
	}

	// If asset is not present in manifest, attempt to resolve the $target_asset
	// relative to the folder containing the manifest file.
	if ( empty( $asset_uri ) ) {
		// TODO: Consider checking is_readable( $manifest_folder . $target_asset )
		// and warning (in console or error log) if it is not present on disk.
		$asset_uri = $target_asset;
	}

	// Reconcile static asset build paths relative to the manifest's directory.
	if ( strpos( $asset_uri, '//' ) === false ) {
		$asset_uri = Paths\get_file_uri( $manifest_folder . $asset_uri );
	}

	// Use the requested asset as the asset handle if no handle was provided.
	$asset_handle = $options['handle'] ?? $target_asset;
	$asset_version = Manifest\get_version( $asset_uri, $manifest_path );
	$is_asset_css = is_css( $asset_uri ); // Note returns false for the CSS dev JS fallback.
	// If running the development build with runtimeChunk: single, a runtime
	// file will be present in the manifest. Register this and ensure it is
	// loaded only once per page.
	$runtime = Manifest\get_manifest_resource( $manifest_path, 'runtime.js' );
	if ( $runtime && ! $is_asset_css ) {
		// Ensure unique handle based on src.
		$runtime_handle = 'runtime-' . hash( 'crc32', $runtime );
		if ( ! wp_script_is( $runtime_handle, 'registered' ) ) {
			wp_register_script( $runtime_handle, $runtime );
		}
	}

	// Track registered handles so we can enqueue the correct assets later.
	$handles = [];

	if ( $is_asset_css ) {
		// Register a normal CSS bundle.
		wp_register_style(
			$asset_handle,
			$asset_uri,
			$options['dependencies'],
			$asset_version
		);
		$handles['style'] = $asset_handle;
	} elseif ( $is_js_style_fallback ) {
		// We're registering a JS bundle when we originally asked for a CSS bundle.
		// Register the JS, but if any dependencies were passed in, also register a
		// dummy style bundle so that those style dependencies still get loaded.
		Admin\maybe_setup_ssl_cert_error_handling( $asset_uri );
		_register_or_update_script(
			$asset_handle,
			$asset_uri,
			[],
			$asset_version,
			true
		);
		$handles['script'] = $asset_handle;
		if ( ! empty( $options['dependencies'] ) ) {
			wp_register_style(
				$asset_handle,
				false,
				$options['dependencies'],
				$asset_version
			);
			$handles['style'] = $asset_handle;
		}
	} else {
		// Register a normal JS bundle.
		Admin\maybe_setup_ssl_cert_error_handling( $asset_uri );
		_register_or_update_script(
			$asset_handle,
			$asset_uri,
			$options['dependencies'],
			$asset_version,
			$options['in-footer']
		);
		$handles['script'] = $asset_handle;
	}

	// Add dependency after registration to work around HM Asset loader not setting dependencies on JS fallback.
	if ( $runtime && ! $is_asset_css ) {
		$script = wp_scripts()->query( $asset_handle, 'registered' );
		if ( $script && ! in_array( $runtime_handle, $script->deps, true ) ) {
			$script->deps[] = $runtime_handle;
		}
	}

	return $handles;
}


/**
 * Attempt to register a particular script bundle from a manifest.
 *
 * @deprecated 0.8.0 Use register_manifest_asset().
 *
 * @param ?string $manifest_path File system path for an asset manifest JSON file.
 * @param string  $target_asset  Asset to retrieve within the specified manifest.
 * @param array   $options {
 *     @type string $handle       Handle to use when enqueuing the asset. Optional.
 *     @type array  $dependencies Script or Style dependencies. Optional.
 * }
 * @return array Array detailing which script and/or style handles got registered.
 */
function register_asset( ?string $manifest_path, string $target_asset, array $options = [] ): array {
	_deprecated_function( __FUNCTION__, '0.8.0', 'register_manifest_asset' );
	return register_manifest_asset( $manifest_path, $target_asset, $options );
}

/**
 * Register and immediately enqueue a particular asset within a manifest.
 *
 * @param ?string $manifest_path File system path for an asset manifest JSON file.
 * @param string  $target_asset  Asset to retrieve within the specified manifest.
 * @param array   $options {
 *     @type string $handle       Handle to use when enqueuing the asset. Optional.
 *     @type array  $dependencies Script or Style dependencies. Optional.
 * }
 */
function enqueue_manifest_asset( ?string $manifest_path, string $target_asset, array $options = [] ): void {
	$registered_handles = register_manifest_asset( $manifest_path, $target_asset, $options );

	if ( isset( $registered_handles['script'] ) ) {
		wp_enqueue_script( $registered_handles['script'] );
	}
	if ( isset( $registered_handles['style'] ) ) {
		wp_enqueue_style( $registered_handles['style'] );
	}
}


/**
 * Attempt to enqueue a particular script bundle from a manifest.
 *
 * @deprecated 0.8.0 Use enqueue_manifest_asset().
 *
 * @param ?string $manifest_path File system path for an asset manifest JSON file.
 * @param string  $target_asset  Asset to retrieve within the specified manifest.
 * @param array   $options {
 *     @type string $handle       Handle to use when enqueuing the asset. Optional.
 *     @type array  $dependencies Script or Style dependencies. Optional.
 * }
 */
function enqueue_asset( ?string $manifest_path, string $target_asset, array $options = [] ): void {
	_deprecated_function( __FUNCTION__, '0.8.0', 'enqueue_manifest_asset' );
	enqueue_manifest_asset( $manifest_path, $target_asset, $options );
}

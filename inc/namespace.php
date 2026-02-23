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
 * @param ?string|string[] $manifest_path File system path for an asset manifest JSON file (or array thereof).
 * @param string           $target_asset  Asset to retrieve within the specified manifest.
 * @param array            $options {
 *     @type string $handle       Handle to use when enqueuing the asset. Optional.
 *     @type array  $dependencies Script or Style dependencies. Optional.
 * }
 * @return array Array detailing which script and/or style handles got registered.
 */
function register_manifest_asset( $manifest_path, string $target_asset, array $options = [] ): array {
	if ( is_array( $manifest_path ) ) {
		$manifest_path = Manifest\get_active_manifest( $manifest_path );
	}

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
 * @param ?string|string[] $manifest_path File system path for an asset manifest JSON file (or array thereof).
 * @param string           $target_asset  Asset to retrieve within the specified manifest.
 * @param array            $options {
 *     @type string $handle       Handle to use when enqueuing the asset. Optional.
 *     @type array  $dependencies Script or Style dependencies. Optional.
 * }
 */
function enqueue_manifest_asset( $manifest_path, string $target_asset, array $options = [] ): void {
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

/**
 * Register a script asset built with wp-scripts. Loads dependencies and version
 * information from the auto-generated .asset.php file.
 *
 * Script-only: Use normal wp_(enqueue|register)_style methods for handling CSS.
 *
 * @param string   $handle          Handle to use for the script.
 * @param string   $asset_path      Absolute path to this script's JS file within the build folder.
 * @param string[] $additional_deps Optional list of additional script handles on which
 *                                  this asset depends. Gets merged with the asset.php
 *                                  autogenerated WP dependencies list.
 */
function register_script_asset( string $handle, string $asset_path, array $additional_deps = [] ): void {
	$asset_file_path = preg_replace( '/\.js$/', '.asset.php', $asset_path );
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && ! is_readable( $asset_file_path ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		trigger_error(
			sprintf(
				'asset.php file not found for %s, has the build been run? Use wp_enqueue_script for non-wp-scripts assets.',
				esc_attr( $asset_path )
			),
			E_USER_WARNING
		);
		return;
	}

	// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
	$asset_file = include $asset_file_path;

	$asset_file['dependencies'] = array_merge( $asset_file['dependencies'] ?? [], $additional_deps );

	// Check whether a runtime chunk exists, and inject it as a dependency if it does.
	if ( Utilities\includes_hmr_dependency( $asset_file['dependencies'] ) ) {
		if ( wp_get_environment_type() === 'local' ) {
			// Warn if we aren't using SCRIPT_DEBUG when we need to.
			Utilities\warn_if_script_debug_not_enabled();
		}
		// Try to infer and depend upon our custom runtime chunk.
		$runtime_handle = Utilities\detect_and_register_runtime_chunk( $asset_file_path );
		if ( ! empty( $runtime_handle ) ) {
			$asset_file['dependencies'][] = $runtime_handle;
		}
	}

	wp_register_script(
		$handle,
		Paths\get_file_uri( $asset_path ),
		$asset_file['dependencies'],
		$asset_file['version'] ?? filemtime( $asset_file_path ),
		[
			'strategy'  => 'defer',
			'in_footer' => true,
		]
	);
}

/**
 * Enqueue a script asset built with wp-scripts, registering it first if needed.
 * Loads dependencies and version information from the auto-generated .asset.php file.
 *
 * @param string   $handle          Handle to use for the script.
 * @param string   $asset_path      Absolute path to this script's JS file within the build folder.
 * @param string[] $additional_deps Optional list of additional script handles on which
 *                                  this asset depends. Gets merged with the asset.php
 *                                  autogenerated WP dependencies list.
 */
function enqueue_script_asset( string $handle, string $asset_path, array $additional_deps = [] ): void {
	if ( ! wp_script_is( $handle, 'registered' ) ) {
		register_script_asset( $handle, $asset_path, $additional_deps );
	}
	wp_enqueue_script( $handle );
}

/**
 * Register a block.json as an extension of an existing block type.
 *
 * Reads a block.json file and merges its declared assets (editorScript, script,
 * viewScript, editorStyle, style) into the registered block type named in the
 * extension's `name` field. This causes WordPress to automatically enqueue
 * those assets whenever the target block is used, without registering a new
 * block type.
 *
 * Works for core blocks or any registered third-party block.
 *
 * Extensions are applied on `wp_loaded` (after all blocks have been registered).
 * Can be called at any point up through `wp_loaded`, but `init` is recommended.
 *
 * @param string $block_json_path Absolute file system path to a block.json file.
 */
function register_block_extension( string $block_json_path ): void {
	static $extensions = [];
	static $hooked = false;

	if ( ! is_readable( $block_json_path ) ) {
		if ( wp_get_environment_type() === 'local' ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			trigger_error(
				sprintf( 'Block extension file not readable: %s', esc_attr( $block_json_path ) ),
				E_USER_WARNING
			);
		}
		return;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPressVIPMinimum.Performance.FetchingRemoteData
	$block_json_content = file_get_contents( $block_json_path );
	$block_config = json_decode( $block_json_content, true );

	if ( ! is_array( $block_config ) || empty( $block_config['name'] ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		trigger_error(
			sprintf( 'Invalid block.json at %s: missing "name" field', esc_attr( $block_json_path ) ),
			E_USER_WARNING
		);
		return;
	}

	$target_block = $block_config['name'];

	// Set the `file` key so that register_block_script_handle() and
	// register_block_style_handle() can resolve relative asset paths.
	$block_config['file'] = wp_normalize_path( realpath( $block_json_path ) );

	$extensions[ $target_block ][] = $block_config;

	if ( ! $hooked ) {
		$hooked = true;
		if ( did_action( 'wp_loaded' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			trigger_error(
				'register_block_extension() must be called before the wp_loaded hook',
				E_USER_NOTICE
			);
		}
		add_action( 'wp_loaded', function () use ( &$extensions ) {
			Utilities\apply_block_extensions( $extensions );
		} );
	}
}

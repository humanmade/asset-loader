<?php
/**
 * Define the Asset_Loader namespace exposing methods for use in other themes & plugins.
 */

namespace Asset_Loader;

/**
 * Is this a development environment?
 *
 * @return bool
 */
function is_development() {
	return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
}

/**
 * Register some or all scripts and styles defined in a manifest file.
 *
 * @param string $manifest_path Absolute path to a Webpack asset manifest file.
 * @param array  $options {
 *     @type array    $scripts Script dependencies.
 *     @type function $filter  Filter function to limit which scripts are enqueued.
 *     @type string   $handle  Style/script handle. (Default is last part of directory name.)
 *     @type array    $styles  Style dependencies.
 * }
 * @return array|null An array of registered script and style handles, or null.
 */
function register_assets( $manifest_path, $options = [] ) {
	$defaults = [
		'handle'  => basename( plugin_dir_path( $manifest_path ) ),
		'filter'  => '__return_true',
		'scripts' => [],
		'styles'  => [],
	];

	$options = wp_parse_args( $options, $defaults );

	$assets = Manifest\get_assets_list( $manifest_path );

	if ( empty( $assets ) ) {
		// Trust the theme or pluign to handle its own asset loading.
		return false;
	}

	// Generate a hash of the manifest, for script versioning.
	$manifest_hash = md5_file( $manifest_path );

	// Keep track of whether a CSS file has been encountered.
	$has_css = false;

	$registered = [
		'scripts' => [],
		'styles' => [],
	];

	// There should only be one JS and one CSS file emitted per plugin or theme.
	foreach ( $assets as $asset_uri ) {
		if ( ! $options['filter']( $asset_uri ) ) {
			// Ignore file paths which do not pass the provided filter test.
			continue;
		}

		$is_js    = preg_match( '/\.js$/', $asset_uri );
		$is_css   = preg_match( '/\.css$/', $asset_uri );
		$is_chunk = preg_match( '/\.chunk\./', $asset_uri );

		if ( ( ! $is_js && ! $is_css ) || $is_chunk ) {
			// Assets such as source maps and images are also listed; ignore these.
			continue;
		}

		if ( $is_js ) {
			wp_register_script(
				$options['handle'],
				$asset_uri,
				$options['scripts'],
				$manifest_hash,
				true
			);
			$registered['scripts'][] = $options['handle'];
		} elseif ( $is_css ) {
			$has_css = true;
			wp_register_style(
				$options['handle'],
				$asset_uri,
				$options['styles'],
				$manifest_hash
			);
			$registered['styles'][] = $options['handle'];
		}
	}

	// Ensure CSS dependencies are always loaded, even when using CSS-in-JS in
	// development.
	if ( ! $has_css && ! empty( $options['styles'] ) ) {
		wp_register_style(
			$options['handle'],
			null,
			$options['styles']
		);
		$registered['styles'][] = $options['handle'];
	}

	if ( empty( $registered['scripts'] ) && empty( $registered['styles'] ) ) {
		return null;
	}
	return $registered;
}

/**
 * Enqueue some or all scripts and styles defined in a manifest file.
 *
 * @param string $manifest_path Absolute path to a Webpack asset manifest file.
 * @param array  $options {
 *     @type array    $scripts Script dependencies.
 *     @type function $filter  Filter function to limit which scripts are enqueued.
 *     @type string   $handle  Style/script handle. (Default is last part of directory name.)
 *     @type array    $styles  Style dependencies.
 * }
 * @return array|null An array of registered script and style handles, or null.
 */
function enqueue_assets( $manifest_path, $options = [] ) {
	$registered = register_assets( $manifest_path, $options );
	if ( empty( $registered ) ) {
		return false;
	}

	foreach ( $registered['scripts'] as $handle ) {
		wp_enqueue_script( $handle );
	}
	foreach ( $registered['styles'] as $handle ) {
		wp_enqueue_style( $handle );
	}

	// Signal that auto-loading occurred.
	return true;
}

/**
 * Attempt to register a particular script bundle from a manifest, falling back
 * to wp_register_script when the manifest is not available.
 *
 * The manifest, build_path, and target_bundle options are required.
 *
 * @param string $manifest_path Absolute file system path to Webpack asset manifest file.
 * @param string $target_bundle The expected string filename of the bundle to load from the manifest.
 * @param array  $options {
 *     @type string $build_path Absolute file system path to the static asset output folder.
 *                              Optional; defaults to the manifest file's parent folder.
 *     @type string $handle     The handle to use when enqueuing the style/script bundle.
 *                              Optional; defaults to the basename of the build folder's parent folder.
 *     @type array  $scripts    Script dependencies. Optional.
 *     @type array  $styles     Style dependencies. Optional.
 * }
 * @return array|null An array of registered script and style handles, or null.
 */
function autoregister( string $manifest_path, string $target_bundle, array $options = [] ) {
	// Guess that the manifest resides within the build folder if no build path is provided.
	$inferred_build_folder = Paths\containing_folder( $manifest_path );

	// Set up argument defaults and make some informed guesses about the build path and handle.
	$defaults = [
		'build_path' => $inferred_build_folder,
		'handle'     => basename( Paths\containing_folder( $inferred_build_folder ) ),
		'filter'     => '__return_true',
		'scripts'    => [],
		'styles'     => [],
	];

	$options = wp_parse_args( $options, $defaults );

	$registered = register_assets( $manifest_path, [
		'handle'  => $options['handle'],
		'filter'  => $options['filter'] !== $defaults['filter'] ?
			$options['filter'] :
			/**
			 * Default filter function selects only assets matching the provided $target_bundle.
			 */
			function( $script_key ) use ( $target_bundle ) {
				return strpos( $script_key, $target_bundle ) !== false;
			},
		'scripts' => $options['scripts'],
		'styles'  => $options['styles'],
	] );

	$build_path = trailingslashit( $options['build_path'] );

	if ( ! empty( $registered ) ) {
		return $registered;
	}

	// If assets were not auto-registered, attempt to manually register the specified bundle.
	$registered = [
		'scripts' => [],
		'styles' => [],
	];

	$js_bundle = $build_path . $target_bundle;
	// These file naming assumption break down in several instances, such as when
	// using hashed filenames or when naming files .min.js.
	$css_bundle = $build_path . preg_replace( '/\.js$/', '.css', $target_bundle );

	// Production mode. Manually register script bundles.
	if ( file_exists( $js_bundle ) ) {
		wp_register_script(
			$options['handle'],
			Paths\get_file_uri( $js_bundle ),
			$options['scripts'],
			md5_file( $js_bundle ),
			true
		);
		$registered['scripts'][] = $options['handle'];
	}

	if ( file_exists( $css_bundle ) ) {
		wp_register_style(
			$options['handle'],
			Paths\get_file_uri( $css_bundle ),
			$options['styles'],
			md5_file( $css_bundle )
		);
		$registered['styles'][] = $options['handle'];
	}

	if ( empty( $registered['scripts'] ) && empty( $registered['styles'] ) ) {
		return null;
	}

	return $registered;
}

/**
 * Attempt to enqueue a particular script bundle from a manifest, falling back
 * to wp_enqueue_script when the manifest is not available.
 *
 * The manifest, build_path, and target_bundle options are required.
 *
 * @param string $manifest_path Absolute file system path to Webpack asset manifest file.
 * @param string $target_bundle The expected string filename of the bundle to load from the manifest.
 * @param array  $options {
 *     @type string $build_path Absolute file system path to the static asset output folder.
 *                              Optional; defaults to the manifest file's parent folder.
 *     @type string $handle     The handle to use when enqueuing the style/script bundle.
 *                              Optional; defaults to the basename of the build folder's parent folder.
 *     @type array  $scripts    Script dependencies. Optional.
 *     @type array  $styles     Style dependencies. Optional.
 * }
 * @return void
 */
function autoenqueue( string $manifest_path, string $target_bundle, array $options = [] ) {
	$registered = autoregister( $manifest_path, $target_bundle, $options );

	if ( empty( $registered ) ) {
		return;
	}

	foreach ( $registered['scripts'] as $handle ) {
		wp_enqueue_script( $handle );
	}
	foreach ( $registered['styles'] as $handle ) {
		wp_enqueue_style( $handle );
	}
}

/**
 * Helper function to naively check whether or not a given URI is a CSS resource.
 *
 * @param string $uri A URI to test for CSS-ness.
 * @return boolean Whether that URI points to a CSS file.
 */
function is_css( string $uri ) : bool {
	return preg_match( '/\.css(\?.*)?$/', $uri ) === 1;
}

/**
 * Attempt to register a particular script bundle from a manifest.
 *
 * @param string $manifest_path File system path for an asset manifest JSON file.
 * @param string $target_asset  Asset to retrieve within the specified manifest.
 * @param array  $options {
 *     @type string $handle       Handle to use when enqueuing the asset. Required.
 *     @type array  $dependencies Script or Style dependencies. Optional.
 * }
 */
function register_asset( string $manifest_path, string $target_asset, array $options = [] ) : void {
	$defaults = [
		'dependencies' => [],
	];
	$options = wp_parse_args( $options, $defaults );

	$manifest_folder = trailingslashit( dirname( $manifest_path ) );

	$asset_uri = Manifest\get_manifest_resource( $manifest_path, $target_asset );

	// If we fail to match a .css asset, try again with .js in case there is a
	// JS wrapper for that asset available (e.g. when using DevServer).
	if ( empty( $asset_uri ) && is_css( $target_asset ) ) {
		$asset_uri = Manifest\get_manifest_resource( $manifest_path, preg_replace( '/\.css$/', '.js', $target_asset ) );
	}

	// If asset is not present in manifest, attempt to resolve the $target_asset
	// relative to the folder containing the manifest file.
	if ( empty( $asset_uri ) && file_exists( $manifest_folder . $target_asset ) ) {
		// TODO: Consider warning in the console if the asset could not be found.
		// (Failure should be allowed for CSS files; they are not exported in dev).
		$asset_uri = $target_asset;
	}

	// Reconcile static asset build paths relative to the manifest's directory.
	if ( strpos( $asset_uri, '//' ) === false ) {
		$asset_uri = Paths\get_file_uri( $manifest_folder . $asset_uri );
	}

	if ( is_css( $asset_uri ) ) {
		wp_register_style(
			$options['handle'],
			$asset_uri,
			$options['dependencies'],
			$asset_uri
		);
	} else {
		Admin\maybe_setup_ssl_cert_error_handling( $asset_uri );
		wp_register_script(
			$options['handle'],
			$asset_uri,
			$options['dependencies'],
			$asset_uri,
			true
		);
	}
}

/**
 * Attempt to register and then enqueue a particular script bundle from a manifest.
 *
 * @param string $manifest_path File system path for an asset manifest JSON file.
 * @param string $target_asset  Asset to retrieve within the specified manifest.
 * @param array  $options {
 *     @type string $handle       Handle to use when enqueuing the asset. Required.
 *     @type array  $dependencies Script or Style dependencies. Optional.
 * }
 */
function enqueue_asset( string $manifest_path, string $target_asset, array $options = [] ) : void {
	register_asset( $manifest_path, $target_asset, $options );

	// $target_asset will share a filename extension with the enqueued asset.
	if ( is_css( $target_asset ) ) {
		wp_enqueue_style( $options['handle'] );
	} else {
		wp_enqueue_script( $options['handle'] );
	}
}

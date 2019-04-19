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
 * @param array $options {
 *     @type array    $scripts  Script dependencies.
 *     @type function $filter   Filter function to limit which scripts are enqueued.
 *     @type string   $handle   Style/script handle. (Default is last part of directory name.)
 *     @type array    $styles   Style dependencies.
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
				filemtime( $manifest_path ),
				true
			);
			$registered['scripts'][] = $options['handle'];
		} elseif ( $is_css ) {
			$has_css = true;
			wp_register_style(
				$options['handle'],
				$asset_uri,
				$options['styles'],
				filemtime( $manifest_path )
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
 * @param array $options {
 *     @type array    $scripts  Script dependencies.
 *     @type function $filter   Filter function to limit which scripts are enqueued.
 *     @type string   $handle   Style/script handle. (Default is last part of directory name.)
 *     @type array    $styles   Style dependencies.
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
 * @param string   $manifest_path Absolute file system path to Webpack asset manifest file.
 * @param function $target_bundle The expected string filename of the bundle to load from the manifest.
 * @param array    $options {
 *     @type string   $build_path    Absolute file system path to the static asset output folder.
 *                                   Optional; defaults to the manifest file's parent folder.
 *     @type string   $handle        The handle to use when enqueuing the style/script bundle.
 *                                   Optional; defaults to the basename of the build folder's parent folder.
 *     @type array    $scripts       Script dependencies. Optional.
 *     @type array    $styles        Style dependencies. Optional.
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
	// using hashed filenames or when naming files .min.js
	$css_bundle = $build_path . preg_replace( '/\.js$/', '.css', $target_bundle );

	// Production mode. Manually register script bundles.
	if ( file_exists( $js_bundle ) ) {
		wp_register_script(
			$options['handle'],
			Paths\plugin_or_theme_file_uri( $js_bundle ),
			// get_theme_file_uri( 'build/' . $target_bundle ),
			$options['scripts'],
			filemtime( $js_bundle ),
			true
		);
		$registered['scripts'][] = $options['handle'];
	}

	if ( file_exists( $css_bundle ) ) {
		wp_register_style(
			$options['handle'],
			Paths\plugin_or_theme_file_uri( $css_bundle ),
			$options['styles'],
			filemtime( $css_bundle )
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
 * @param string   $manifest_path Absolute file system path to Webpack asset manifest file.
 * @param function $target_bundle The expected string filename of the bundle to load from the manifest.
 * @param array    $options {
 *     @type string   $build_path    Absolute file system path to the static asset output folder.
 *                                   Optional; defaults to the manifest file's parent folder.
 *     @type string   $handle        The handle to use when enqueuing the style/script bundle.
 *                                   Optional; defaults to the basename of the build folder's parent folder.
 *     @type array    $scripts       Script dependencies. Optional.
 *     @type array    $styles        Style dependencies. Optional.
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

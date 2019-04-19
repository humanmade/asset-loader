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

	$assets = get_assets_list( $manifest_path );

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
 * Check if provided path is within the stylesheet directory.
 *
 * @param string $path An absolute file system path.
 * @return boolean
 */
function is_theme_path( string $path ): bool {
	return strpos( $path, get_stylesheet_directory() ) === 0;
}

/**
 * Check if provided path is within the parent theme directory.
 *
 * @param string $path An absolute file system path.
 * @return boolean
 */
function is_parent_theme_path( string $path ): bool {
	return strpos( $path, get_template_directory() ) === 0;
}

function theme_relative_path( string $path ): string {
	if ( is_theme_path( $path ) ) {
		return str_replace( trailingslashit( get_stylesheet_directory() ), '', $path );
	}
	if ( is_parent_theme_path( $path ) ) {
		return str_replace( trailingslashit( get_template_directory() ), '', $path );
	}
	// This is a bad state. How to indicate?
	return '';
	// return plugin_dir_url( $path );
}

/**
 * Check if provided path is within the stylesheet or template directories.
 *
 * @param string $path An absolute file system path.
 * @return boolean
 */
function is_plugin_path( string $path ): bool {
	return ! is_theme_path( $path ) && ! is_parent_theme_path( $path );
}

/**
 * Take in an absolute file system path that may be part of a theme or plugin
 * directory, and return the URL for that file.
 *
 * @param [type] $path
 * @return string
 */
function plugin_or_theme_file_uri( string $path ): string {
	if ( ! is_plugin_path( $path ) ) {
		return get_theme_file_uri( theme_relative_path( $path ) );
	}

	return plugin_dir_url( $path ) . basename( $path );
}

/**
 * Get the filesystem directory path (with trailing slash) for the file passed in.
 *
 * Note: This is a more descriptively-named equivalent to WP's core plugin_dir_path().
 *
 * @param string $file The path to a file on the local file system.
 * @return string The filesystem path of the directory that contains the provided $file.
 */
function containing_folder( $file ): string {
	return trailingslashit( dirname( $file ) );
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
	$inferred_build_folder = containing_folder( $manifest_path );

	// Set up argument defaults and make some informed guesses about the build path and handle.
	$defaults = [
		'build_path' => $inferred_build_folder,
		'handle'     => basename( containing_folder( $inferred_build_folder ) ),
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
			plugin_or_theme_file_uri( $js_bundle ),
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
			plugin_or_theme_file_uri( $css_bundle ),
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

<?php
/**
 * Semi-private helpers namespace to separate the plugin's public loader API
 * methods from its internal utility functions.
 */

declare( strict_types=1 );

namespace Asset_Loader\Utilities;

/**
 * Check whether a given dependencies array includes any of the handles for HMR
 * runtimes that WordPress will inject by default.
 *
 * @param string[] $dependencies Array of dependency script handles.
 * @return bool Whether any dependency is a react-refresh runtime.
 */
function includes_hmr_dependency( array $dependencies ): bool {
	return array_reduce(
		$dependencies,
		function ( $depends_on_runtime, $dependency_script_handle ) {
			return $depends_on_runtime || $dependency_script_handle === 'wp-react-refresh-runtime';
		},
		false
	);
}

/**
 * Check for a runtime file on disk based on the path of the assets file which
 * requires hot-reloading.
 *
 * @param string $asset_file_path Path to a script's asset.php file.
 * @return string URI to a valid runtime.js file, or empty string if not found.
 */
function infer_runtime_file_uri( $asset_file_path ): string {
	// Heuristic: the runtime is expected to be in the same folder, or else no more
	// than two levels up from the target script. The maximum expected nesting within
	// build/ is usually 2: for example, `build/blocks/blockname/asset.js`.
	foreach ( [ 1, 2, 3 ] as $depth ) {
		$expected_runtime_file = dirname( $asset_file_path, $depth ) . '/runtime.js';
		if ( is_readable( $expected_runtime_file ) ) {
			return \Asset_Loader\Paths\get_file_uri( $expected_runtime_file );
		}
	}

	// No runtime found in the asset directory or asset's parent directory.
	return '';
}

/**
 * Get the handle for a script registered with a specific URI.
 *
 * If no script is registered with the given URI, a default handle based on the
 * URI's hash is returned.
 *
 * @param string $runtime_uri    The public URI of a script.
 * @param string $default_handle Handle to use if no existing WP_Script is found.
 * @return string The script handle if found, otherwise a generated handle.
 */
function get_runtime_handle( string $runtime_uri, string $default_handle ): string {
	global $wp_scripts;

	if ( ! isset( $wp_scripts ) || ! ( $wp_scripts instanceof \WP_Scripts ) ) {
		return 'runtime-' . md5( $runtime_uri );
	}

	foreach ( $wp_scripts->registered as $script ) {
		if ( isset( $script->src ) && $script->src === $runtime_uri ) {
			return $script->handle;
		}
	}

	return $default_handle;
}

/**
 * Try to identify the location of a runtime chunk file relative to a requested
 * asset, register that chunk as a script if it hasn't been registered already,
 * then return the script handle for use as a script dependency.
 *
 * @param string $asset_file_path Path to a script's asset.php file.
 * @return string Handle of registered script runtime, or empty string if not found.
 */
function detect_and_register_runtime_chunk( string $asset_file_path ): string {
	$runtime_uri = infer_runtime_file_uri( $asset_file_path );
	if ( empty( $runtime_uri ) ) {
		return '';
	}

	// We shouldn't have multiple runtimes on the page or our bundles may get
	// executed more than once, causing confusing in-editor behavior. Find the
	// One True Handle and ensure it is registered.
	// If no existing handle is found, return the handle normally expected
	// to be created by the core generate_block_asset_handle() method.
	$runtime_handle = get_runtime_handle( $runtime_uri, 'undefined-runtime' );
	// Ensure the runtime is registered.
	if ( ! wp_script_is( $runtime_handle, 'registered' ) ) {
		wp_register_script(
			$runtime_handle,
			$runtime_uri,
			[],
			filemtime( $asset_file_path ),
			false // Load runtime chunk itself in the header.
		);
	}

	return $runtime_handle;
}

/**
 * Show a visible warning if we try to use a hot-reloading dev server while
 * SCRIPT_DEBUG is false: otherwise, the script will silently fail to load.
 */
function warn_if_script_debug_not_enabled(): void {
	static $has_shown;

	$is_script_debug_mode = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;

	if ( $has_shown || $is_script_debug_mode ) {
		return;
	}

	// Runtime only loads in SCRIPT_DEBUG mode. Show a warning.
	if ( is_admin() ) {
		wp_enqueue_script( 'wp-data' );
		add_action( 'admin_footer', __NAMESPACE__ . '\\show_editor_debug_mode_warning', 100 );
	} else {
		add_action( 'wp_footer', __NAMESPACE__ . '\\show_local_frontend_debug_mode_warning', 100 );
	}
	$has_shown = true;
}

/**
 * Single point of access for script debug warning string.
 *
 * @return string
 */
function get_script_debug_warning(): string {
	return __( 'Hot reloading was requested but SCRIPT_DEBUG is false. Your bundle will not load. Please enable SCRIPT_DEBUG or disable hot reloading.', 'asset-loader' );
}

/**
 * Use the block editor notices package to show a warning in the editor if
 * hot reloading is required by a script when SCRIPT_DEBUG is disabled.
 */
function show_editor_debug_mode_warning(): void {
	?>
	<script>
	window.addEventListener( 'DOMContentLoaded', () => {
		wp.data.dispatch( 'core/notices' ).createNotice(
			'warning',
			<?php echo wp_json_encode( get_script_debug_warning() ); ?>,
			{
				isDismissible: false,
			}
		);
	} );
	</script>
	<?php
}

/**
 * Show a visible frontend notice if hot reloading is required by a script when
 * SCRIPT_DEBUG is disabled.
 *
 * Logs to error_log instead of showing visible error if not running locally,
 * though in practice HMR should never be running on deployed environments.
 */
function show_local_frontend_debug_mode_warning(): void {
	if ( wp_get_environment_type() !== 'local' ) {
		// phpcs:ignore -- WordPress.PHP.DevelopmentFunctions
		error_log( get_script_debug_warning() );
		return;
	}
	?>
	<div style="z-index:100000;border-top:5px solid red;background:white;padding:1rem;width:100%;position:fixed;bottom:0;">
		<?php echo esc_html( get_script_debug_warning() ); ?>
	</div>
	<?php
}

/**
 * Map a block.json asset field name to its corresponding WP_Block_Type
 * handles property.
 *
 * @param string $field A block.json asset field (e.g. 'editorScript', 'style').
 * @return string The WP_Block_Type property name (e.g. 'editor_script_handles').
 */
function get_block_handles_property( string $field ): string {
	$map = [
		'editorScript' => 'editor_script_handles',
		'script'       => 'script_handles',
		'viewScript'   => 'view_script_handles',
		'editorStyle'  => 'editor_style_handles',
		'style'        => 'style_handles',
		'viewStyle'    => 'view_style_handles',
	];
	return $map[ $field ] ?? '';
}

/**
 * Merge a single block extension's assets into an already-registered block type.
 *
 * Uses WP core's register_block_script_handle() and register_block_style_handle()
 * to register the extension's assets, then appends the resulting handles to the
 * target block type's handle arrays.
 *
 * @param string $block_name      The registered block type name (e.g. 'core/paragraph').
 * @param array  $block_extension A single block.json config array to merge.
 */
function apply_block_extension( string $block_name, array $block_extension ): void {
	$registry = \WP_Block_Type_Registry::get_instance();

	$block_type = $registry->get_registered( $block_name );

	if ( ! $block_type ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		trigger_error(
			sprintf( 'Block type "%s" is not registered; cannot apply extension.', esc_attr( $block_name ) ),
			E_USER_WARNING
		);
		return;
	}

	$asset_fields  = [ 'editorScript', 'script', 'viewScript', 'editorStyle', 'style', 'viewStyle' ];

	foreach ( $asset_fields as $field ) {
		if ( ! isset( $block_extension[ $field ] ) ) {
			continue;
		}

		$handles_prop = get_block_handles_property( $field );

		// Scripts and styles use different registries and methods.
		$type = strpos( strtolower( $field ), 'script' ) !== false ? 'script' : 'style';

		foreach ( (array) $block_extension[ $field ] as $index => $asset ) {
			// Allow scripts that only exist to trigger a CSS build to self-exclude
			// with a query string, "file:./index.js?skip_enqueue"
			if ( strpos( $asset, '?skip_enqueue' ) > 0 ) {
				continue;
			}

			$meta_for_registration           = $block_extension;
			$meta_for_registration[ $field ] = $asset;

			// Use a high index to avoid handle collisions with the block's existing assets.
			$handle = $type === 'script'
				? register_block_script_handle( $meta_for_registration, $field, $index + 100 )
				: register_block_style_handle( $meta_for_registration, $field, $index + 100 );

			if ( $handle && ! in_array( $handle, $block_type->$handles_prop, true ) ) {
				$block_type->$handles_prop[] = $handle;
			}
		}
	}
}

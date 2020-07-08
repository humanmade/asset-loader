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
		maybe_setup_ssl_cert_error_handling( $dev_assets );
		return array_values( $dev_assets );
	}

	return null;
}

/**
 * Check to see if the manifest contains HTTPS localhost URLs, and set up error
 * detection to display a notice reminding the developer to accept the dev
 * server's SSL certificate if any of those HTTPS scripts fail to load.
 *
 * @param array $dev_assets Array of script URLs to load.
 * @return void
 */
function maybe_setup_ssl_cert_error_handling( $dev_assets ) {
	preg_match_all( '#https://localhost:\d+#', implode( "\n", $dev_assets ), $matches );
	if ( empty( $matches ) || empty( $matches[0] ) ) {
		// No HTTPS URLs? Carry on.
		return;
	}
	if ( is_admin() ) {
		add_action( 'admin_head', __NAMESPACE__ . '\\detect_localhost_script_errors' );
		add_filter( 'script_loader_tag', __NAMESPACE__ . '\\add_onerror_to_scripts', 10, 3 );
	}
}

/**
 * Render inline JS into the page header to register a function which will be
 * called should any of our registered HTTPS localhost scripts fail to load.
 *
 * @return void
 */
function detect_localhost_script_errors() {
	?>
<script>
( function() {
	var scriptsWithErrors = [];

	/**
	 * @param HTMLScriptElement The script which experienced an error.
	 */
	window.maybeSSLError = function( script ) {
		scriptsWithErrors.push( script );
	};

	/**
	 * Check whether an error has occurred, then attempt to display a Block Editor
	 * notice to alert the developer if so.
	 *
	 * @return void
	 */
	function processErrors() {
		if ( ! scriptsWithErrors.length ) {
			// There are no problems to highlight.
			return;
		}

		var notices = null;
		if ( window.wp && window.wp.data && window.wp.data.dispatch ) {
			notices = window.wp.data.dispatch( 'core/notices' );
		}
		if ( ! notices ) {
			// We're not in a context where it is easy to display a notice from JS.
			return;
		}

		// Build a list of problem hosts.
		var hosts = scriptsWithErrors.reduce(
			function( hosts, script ) {
				var src = script.getAttribute( 'src' );
				if ( ! src || ! /https:\/\/localhost/i.test( src ) ) {
					return hosts;
				}
				src = src.replace( /^(https:\/\/localhost:\d+).*$/i, '$1' );
				hosts[ src ] = true;
				return hosts;
			},
			{}
		);
		hosts = Object.keys( hosts );

		// Build the error markup.
		const messageHTML = [
			'<strong>Error loading scripts from localhost!</strong>',
			'<br>',
			'Ensure that ',
			( hosts.length > 1 ? 'these hosts are ' : 'this host is ' ),
			'accessible, and that you have accepted any development server SSL certificates:',
			'<ul>',
			hosts.map( host => '<li><a target="_blank" href="' + host + '">' + host + '</a></li>' ).join( '' ),
			'</ul>'
		].join( '' );

		notices.createErrorNotice( messageHTML, { __unstableHTML: true } );
	}

	// Set up processErrors to run 1 second after page load.
	document.addEventListener( 'DOMContentLoaded', function() {
		setTimeout( processErrors, 1000 );
	} );
} )();
</script>
	<?php
}

/**
 * Inject an onerror attribute into the rendered script tag for any script
 * loaded from localhost with an HTTPS protocol.
 *
 * @param string $tag    The HTML of the script tag to render.
 * @param string $handle The registered script handle for this tag.
 * @param string $src    The src URI of the JavaScript file this script loads.
 * @return string The script tag HTML, conditionally transformed.
 */
function add_onerror_to_scripts( string $tag, string $handle, string $src ) : string {
	if ( ! preg_match( '#https://localhost#', $src ) ) {
		return $tag;
	}
	return preg_replace(
		'/<script/',
		'<script onerror="maybeSSLError && maybeSSLError( this );"',
		$tag
	);
}

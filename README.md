# Asset Loader

This plugin exposes functions which may be used within other WordPress themes or plugins to register and enqueue bundled script assets. It supports both [`wp-scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)-generated builds and custom Webpack configurations that output a JSON asset manifest.

[![Build Status](https://travis-ci.com/humanmade/asset-loader.svg?branch=main)](https://travis-ci.com/humanmade/asset-loader)

## Usage

### Script Asset API (wp-scripts)

This plugin's primary public interface is for loading scripts built with `wp-scripts`. Use these functions for any `wp-scripts`-generated bundle that is not already auto-registered by `register_block_type_from_metadata()`. Dependencies and version information are read automatically from the `.asset.php` file generated alongside each bundle.

```php
<?php
namespace My_Plugin\Scripts;

use Asset_Loader;

add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_block_editor_assets' );

function enqueue_block_editor_assets() {
    // Register and enqueue a wp-scripts bundle in one step.
    Asset_Loader\enqueue_script_asset(
        'my-plugin-editor',
        plugin_dir_path( __DIR__ ) . 'build/editor/index.js'
    );
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_frontend_assets' );

function enqueue_frontend_assets() {
    // You can pass additional dependencies beyond those in asset.php.
    Asset_Loader\enqueue_script_asset(
        'my-plugin-frontend',
        plugin_dir_path( __DIR__ ) . 'build/frontend/index.js',
        [ 'some-other-script-handle' ]
    );
}
```

The `wp-scripts` build does not output an asset file for CSS assets or hash their filenames, so we recommend to use the standard `wp_register_style` and `wp_enqueue_style` functions directly for style assets unrelated to a particular block.

### Manifest Asset API (custom Webpack)

For projects using a custom Webpack configuration that outputs a JSON asset manifest (such as those created with the presets in [@humanmade/webpack-helpers](https://github.com/humanmade/webpack-helpers)), use `Asset_Loader\register_manifest_asset()` and `Asset_Loader\enqueue_manifest_asset()`. The manifest associates asset bundle names with either URIs pointing to asset bundles on a running DevServer instance, or else local file paths on disk.

```php
<?php
namespace My_Theme\Scripts;

use Asset_Loader;

add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_block_editor_assets' );

/**
 * Enqueue the JS and CSS for blocks in the editor.
 */
function enqueue_block_editor_assets() {
  Asset_Loader\enqueue_manifest_asset(
    // In a plugin, this would be `plugin_dir_path( __FILE__ )` or similar.
    get_stylesheet_directory() . '/build/asset-manifest.json',
    // The handle of a resource within the manifest. For static file fallbacks,
    // this should also match the filename on disk of a build production asset.
    'editor.js',
    [
      'handle'       => 'optional-custom-script-handle',
      'dependencies' => [ 'wp-element', 'wp-editor' ],
    ]
  );

  Asset_Loader\enqueue_manifest_asset(
    // In a plugin, this would be `plugin_dir_path( __FILE__ )` or similar.
    get_stylesheet_directory() . '/build/asset-manifest.json',
    // Enqueue CSS for the editor.
    'editor.css',
    [
      'handle'       => 'custom-style-handle',
      'dependencies' => [ 'some-style-dependency' ],
    ]
  );
}
```

## Documentation

For complete documentation, including contributing process, visit the [docs site](https://humanmade.github.io/asset-loader/).

## License

This plugin is free software. You can redistribute it and/or modify it under the terms of the [GNU General Public License](LICENSE) as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

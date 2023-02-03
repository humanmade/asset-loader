---
layout: home
title: Usage
nav_order: 2
permalink: /usage
---

# Usage

This library is designed to work in conjunction with a Webpack configuration (such as those created with the presets in [@humanmade/webpack-helpers](https://github.com/humanmade/webpack-helpers)) which generate an asset manifest file. This manifest associates asset bundle names with either URIs pointing to asset bundles on a running DevServer instance, or else local file paths on disk.

### `Asset_Loader\register_asset()` and `Asset_Loader\enqueue_asset()`

`Asset_Loader` provides a set of methods for reading in this manifest file and registering a specific resource within it to load within your WordPress website. The primary public interface provided by this plugin is a pair of methods, `Asset_Loader\register_asset()` and `Asset_Loader\enqueue_asset()`. To register a manifest asset call one of these methods inside actions like `wp_enqueue_scripts` or `enqueue_block_editor_assets`, in the same manner you would have called the standard WordPress `wp_register_script` or `wp_enqueue_style` functions.

```php
<?php
namespace My_Theme\Scripts;

use Asset_Loader;

add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_block_editor_assets' );

/**
 * Enqueue the JS and CSS for blocks in the editor.
 *
 * @return void
 */
function enqueue_block_editor_assets() {
  Asset_Loader\enqueue_asset(
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

  Asset_Loader\enqueue_asset(
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

To register an asset to be manually enqueued later, use `Asset_Loader\register_asset()` instead of `enqueue_asset()`. Both methods take the same arguments.

If a manifest is not present then `Asset_Loader` will attempt to load the specified resource from the same directory containing the manifest file.

By default, all enqueues will be added at the end of the page, in the `wp_footer` action. If you need your script to be enqueued in the document `<head>`, pass the flag `'in-footer' => false,` within the options array.


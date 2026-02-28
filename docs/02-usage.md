---
layout: home
title: Usage
nav_order: 2
permalink: /usage
---

# Usage

Asset Loader provides two complementary APIs for loading bundled assets in WordPress:

1. **Script Asset API** (`register_script_asset` / `enqueue_script_asset`): The primary public interface, designed for scripts built with [`wp-scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/). Use these functions when your build outputs `.asset.php` dependency files alongside each bundle.

2. **Manifest Asset API** (`register_manifest_asset` / `enqueue_manifest_asset`): For custom Webpack configurations that output a JSON asset manifest, such as those created with [@humanmade/webpack-helpers](https://github.com/humanmade/webpack-helpers).

## Script Asset API

Use `register_script_asset()` and `enqueue_script_asset()` to load JavaScript bundles built using `wp-scripts build`. These functions automatically detect and read the `.asset.php` file generated alongside each bundle to automatically resolve WordPress script dependencies and set the correct version hash.

This API should be used for any script built with `wp-scripts` that is not already auto-registered by `register_block_type_from_metadata()`. If your scripts are exclusively used as a block's `editorScript`, `viewScript`, etc., WordPress should handle registration for you and you do not need Asset Loader.

For CSS, continue using the standard `wp_register_style` / `wp_enqueue_style` functions directly. `wp-scripts` does not generate an asset file or hash stylesheet filenames in a way that requires a custom helper.

### `Asset_Loader\register_script_asset()`

Register a script without enqueuing it.

```php
Asset_Loader\register_script_asset(
    string $handle,
    string $asset_path,
    array  $additional_deps = []
);
```

**Parameters:**

- **`$handle`** _(string)_: The handle to register the script under.
- **`$asset_path`** _(string)_: The absolute file system path to the built `.js` file. A corresponding `.asset.php` file must exist at the same location (e.g., `build/index.js` → `build/index.asset.php`).
- **`$additional_deps`** _(string[], optional)_: Additional script handles to merge into the auto-detected dependency list from the `.asset.php` file.

### `Asset_Loader\enqueue_script_asset()`

Register (if not already registered) and immediately enqueue a script.

```php
Asset_Loader\enqueue_script_asset(
    string $handle,
    string $asset_path,
    array  $additional_deps = []
);
```

Takes the same parameters as `register_script_asset()`.

### Example

```php
<?php
namespace My_Plugin\Scripts;

use Asset_Loader;

add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_block_editor_assets' );

/**
 * Enqueue editor-only scripts.
 */
function enqueue_block_editor_assets() {
    Asset_Loader\enqueue_script_asset(
        'my-plugin-editor',
        plugin_dir_path( __DIR__ ) . 'build/editor/index.js'
    );
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_frontend_assets' );

/**
 * Enqueue frontend scripts with additional dependencies.
 */
function enqueue_frontend_assets() {
    Asset_Loader\enqueue_script_asset(
        'my-plugin-frontend',
        plugin_dir_path( __DIR__ ) . 'build/frontend/index.js',
        [ 'some-other-script-handle' ]
    );
}
```

Scripts registered with this API are loaded with `defer` strategy in the footer by default.

### Hot Module Replacement

If your `wp-scripts` build uses the React Fast Refresh runtime (`wp-react-refresh-runtime`), Asset Loader will automatically detect and register the runtime chunk.

Note that `SCRIPT_DEBUG` must be enabled for HMR to function. Asset Loader will display a warning in both the block editor and on the frontend in local environments if it detects an HMR dependency without `SCRIPT_DEBUG`.

---

## Manifest Asset API

For projects using a custom Webpack configuration that outputs a JSON asset manifest (rather than `wp-scripts`), use `register_manifest_asset()` and `enqueue_manifest_asset()`. The manifest associates asset bundle names with either URIs pointing to bundles on a running DevServer instance, or local file paths on disk.

### `Asset_Loader\register_manifest_asset()` and `Asset_Loader\enqueue_manifest_asset()`

```php
Asset_Loader\enqueue_manifest_asset(
    string|string[] $manifest_path,
    string          $target_asset,
    array           $options = []
);
```

**Parameters:**

- **`$manifest_path`** _(string|string[])_: File system path to an `asset-manifest.json` file, or an array of paths (the first readable manifest will be used).
- **`$target_asset`** _(string)_: The bundle name to look up in the manifest (e.g. `'editor.js'`).
- **`$options`** _(array, optional)_:
  - `handle` _(string)_: Custom handle for the registered asset. Defaults to `$target_asset`.
  - `dependencies` _(string[])_: Script or style dependencies.
  - `in-footer` _(bool)_: Whether to load in the footer. Defaults to `true`.

### Example

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

To register an asset to be manually enqueued later, use `Asset_Loader\register_manifest_asset()` instead of `enqueue_manifest_asset()`. Both methods take the same arguments.

If a manifest is not present then `Asset_Loader` will attempt to load the specified resource from the same directory containing the manifest file.

By default, all enqueues will be added at the end of the page, in the `wp_footer` action. If you need your script to be enqueued in the document `<head>`, pass the flag `'in-footer' => false,` within the options array.

---

## Block Extensions API

Use `register_block_extension()` to attach additional scripts and styles to an already-registered block type. This is useful when you need to augment a core or third-party block with your own assets (_e.g._ adding a `viewScript` to `core/paragraph` or an `editorScript` to `core/image`) without registering a new block.

Write a standard `block.json` whose `name` field references the block you want to extend, and declare any combination of `editorScript`, `script`, `viewScript`, `editorStyle`, and `style` fields using `file:./` relative paths as you would for a normal block. Then call `register_block_extension()` with the path to that file.

Extensions are processed on `wp_loaded`, after all blocks have been registered. You can call `register_block_extension()` at any point up through `wp_loaded`.

### `Asset_Loader\register_block_extension()`

```php
Asset_Loader\register_block_extension(
    string $block_json_path
);
```

**Parameters:**

- **`$block_json_path`** _(string)_: Absolute file system path to a `block.json` file. The file must contain a `name` field identifying the target block, and one or more asset fields (`editorScript`, `script`, `viewScript`, `editorStyle`, `style`) with `file:./` relative paths.

### Example

Given a build directory containing:

```
build/blocks/core/paragraph/
├── block.json
├── view.js
├── view.asset.php
├── style.css
└── style.asset.php
```

With `block.json`:

```json
{
    "name": "core/paragraph",
    "viewScript": "file:./view.js",
    "style": "file:./style.css"
}
```

Register the extension:

```php
<?php
namespace My_Plugin\Blocks;

use Asset_Loader;

add_action( 'init', __NAMESPACE__ . '\\register_block_extensions' );

function register_block_extensions() {
    Asset_Loader\register_block_extension(
        plugin_dir_path( __DIR__ ) . 'build/blocks/core/paragraph/block.json'
    );
}
```

WordPress will now automatically enqueue `view.js` and `style.css` whenever a `core/paragraph` block is rendered, without registering a new block type.

This interface was designed specifically to easily extend core blocks, but it will work for any registered third-party block.

### Skipping enqueue for stub scripts

Some `wp-scripts` builds require a JavaScript entry point to produce a CSS output file, even when there is no meaningful JS to run on the page. In this situation a `block.json` may declare a script field solely to trigger the CSS build, but you don't want that stub JS file to be enqueued at runtime.

Append `?skip_enqueue` to the asset path to opt it out of enqueue processing:

```json
{
    "name": "core/paragraph",
    "editorScript": "file:./index.js?skip_enqueue",
    "style": "file:./style.css"
}
```

When `register_block_extension()` encounters a script or style path containing `?skip_enqueue`, it silently skips that entry instead of registering and enqueuing it. The build tooling still sees a valid entry point, so the CSS file is generated as expected.


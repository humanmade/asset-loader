# Changelog

## v0.4.0

- **Breaking**: Remove undocumented `Asset_Loader\is_development` method.
- **Breaking**: Remove undocumented `Asset_Loader\enqueue_assets` method.
- **New**: Introduce new `Asset_Loader\register_asset()` and `Asset_Loader\enqueue_asset()` public API.
- Refactor how SSL warning notice behavior gets triggered during asset registration.

## v0.3.4

- Added `composer/installers` as a dependency to permit custom installation paths when installing this package.

## v0.3.3

- Display admin notification about accepting Webpack's SSL certificate if `https://localhost` scripts encounter errors when loading.
- Derive script & style version string from file hash, not `filemtime`.

## v0.3.2

- Do not require plugin files if plugin is already active elsewhere in the project.

## v0.3.1

- Transfer plugin to `humanmade` GitHub organization

## v0.3.0

- Fix bug when loading plugin assets outside of `wp-content/plugins`
- Permit installation with `composer`.

## v0.2.0

- Initial release: introduce `autoregister()` and `autoenqueue()` public API.

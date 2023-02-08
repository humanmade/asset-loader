---
layout: home
title: Contributing
nav_order: 4
permalink: /contributing
---

# Contributing

## Local Development

Before submitting a pull request, ensure that your PHP code passes all existing unit tests and conforms to our [coding standards](https://github.com/humanmade/coding-standards) by running these commands:

```sh
composer lint
composer test
```

If the above commands do not work, ensure you have [Composer](https://getcomposer.org/) installed on your machine & run `composer install` from the project root.

## Release Process

This project is [distributed via Packagist for use in Composer projects as `humanmade/asset-loader`](https://packagist.org/packages/humanmade/asset-loader). To release a new version, create a new tag and push it to GitHub, then ensure that tag has been recognized by Packagist. Follow these steps to ensure all version numbers and documentation get properly updated when releasing a new version:

- Merge all relevant pull requests you wish to include in the new version
- Identify whether you are releasing a patch release, point release, or major release, following [semantic versioning](https://semver.org/) principles, and determine the next release's version number accordingly
- Ensure the [Changelog](CHANGELOG.md) is updated for the new version
- Update the version number in the [plugin header comment in `asset-loader.php`](asset-loader.php)
- Create a tag on the `main` branch reflecting the new version number in the format `v#.#.#`, e.g. `v0.6.2` or `v1.1.0`
- Ensure the `main` branch and all tags are pushed to GitHub
- [Create a release](https://github.com/humanmade/asset-loader/releases/new) in GitHub from the new tag
- If the new version does not show up in [the Packagist page for `humanmade/asset-loader`](https://packagist.org/packages/humanmade/asset-loader), request one of the project's packagist maintainers to click the "update" button


---
layout: home
title: About this plugin
description: "Asset Loader provides helper functions for loading bundled scripts and styles in WordPress themes and plugins."
nav_order: 1
permalink: /
---

# Asset Loader

Asset Loader provides helper functions for loading bundled JavaScript and CSS assets in WordPress themes and plugins.

{: .highlight .bg-hm-purple-100 } 
This plugin exposes functions which may be used within other WordPress themes or plugins to register and enqueue script assets. It supports both [`wp-scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)-generated builds (via `.asset.php` dependency files) and custom Webpack configurations that output a JSON asset manifest.

[![Build Status](https://travis-ci.com/humanmade/asset-loader.svg?branch=main)](https://travis-ci.com/humanmade/asset-loader)

## License

This plugin is free software. You can redistribute it and/or modify it under the terms of the [GNU General Public License](LICENSE) as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

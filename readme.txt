=== PostGrid ===
Contributors: richardbaxterseo
Tags: blocks, posts, grid, gutenberg
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 0.1.15
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight posts grid block for the WordPress block editor.

== Description ==

PostGrid provides a simple, performant way to display your posts in a responsive grid layout. Built with modern WordPress standards and no external dependencies.

Features:
* Clean, minimal design
* Responsive grid layout
* Category filtering
* Customisable columns (1-4)
* Show/hide date and excerpt
* REST API powered
* No jQuery or external CSS frameworks
* Lightweight and fast

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/postgrid` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Add the PostGrid block to any post or page

== Frequently Asked Questions ==

= Can I customise the styling? =

Yes! The block uses simple CSS classes that you can override in your theme.

= Does it work with custom post types? =

Currently, the block only displays standard posts. Custom post type support may be added in future versions.

== Screenshots ==

1. PostGrid in the editor
2. Block settings panel
3. Frontend display

== Changelog ==

= 0.1.6 =
* Major architecture refactoring with modular structure
* Added comprehensive caching system with transients
* Implemented REST API with rate limiting
* Added 20+ developer hooks and filters
* Improved security with proper permission callbacks
* Added support for custom post types via filter
* Enhanced CSS with custom properties for theming
* Added PHPUnit test suite structure
* Improved performance with conditional asset loading
* Added admin bar cache clearing functionality
* Full PSR-4 autoloading implementation
* Better error handling throughout

= 0.1.5 =
* Initial release
* Ultra-lean architecture
* Modern block.json structure
* REST API endpoint
* Minimal custom CSS (under 100 lines)
* No external dependencies

== Upgrade Notice ==

= 0.1.6 =
Major improvements to architecture, performance, and developer experience. Full backward compatibility maintained.

= 0.1.5 =
Initial release. A focused, lightweight posts grid block for WordPress.

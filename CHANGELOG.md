# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.14] - 2025-09-06

### Removed
- **BREAKING CHANGE**: Removed legacy Caxton compatibility layer entirely
- Deleted `/includes/Compatibility/` directory and `LegacySupport` class
- Removed support for legacy shortcodes: `caxton/posts`, `caxton/posts-grid`, `caxton/post-grid`, `caxton/grid`
- Removed legacy block support for `caxton/posts-grid`
- Cleaned up all references to Caxton compatibility from codebase
- Simplified asset loading by removing legacy block detection

### Improved
- Cleaner, more focused architecture without legacy compatibility concerns
- Reduced plugin size and complexity
- Better first-time activation experience without legacy compatibility conflicts
- Fixed PHP 8.4 compatibility issues caused by legacy compatibility layer

## [0.1.13] - 2025-07-10

### Changed
- Major repository cleanup: removed development files, debug scripts, and old documentation
- Updated .gitignore to prevent development files from being tracked
- Removed old release ZIP files from repository (use GitHub releases instead)
- Kept only essential build script (release.ps1)

### Removed
- All debug and diagnostic PHP files
- Test HTML files
- Development handover documentation
- Old release notes and summary files
- Redundant build scripts
- Previous version ZIP archives

## [0.1.12] - 2025-07-10

### Added
- Featured image support with modern gaming-inspired dark theme
- Modern dark gradient background design
- Category badges overlay on featured images
- New controls for showThumbnail, showCategories, and showAuthor attributes
- Enhanced typography with bold headings and accent colours
- Coral/orange accent colour scheme (#FF6B6B)
- Smooth hover effects with image scaling
- Gradient overlays on images for text readability
- Support for posts without featured images with fallback design

### Changed
- Complete CSS redesign with dark theme aesthetics
- Featured images now display by default
- Categories now display as badges on images
- Improved content layout with overlay design on images
- Enhanced hover states with transform effects
- Updated card shadows and borders for depth

### Fixed
- Featured images now properly display in both editor and frontend
- Categories position properly as overlays
- Mobile responsive design maintained with new layout

## [0.1.11] - 2025-07-10

### Added
- Mobile-first CSS architecture with progressive enhancement
- Loading skeleton states with shimmer animation
- Staggered animations for grid items
- Enhanced focus indicators for better accessibility
- CSS custom properties for mobile-specific styling
- Reduced motion support for accessibility
- Touch-friendly 44px minimum tap targets

### Changed
- Complete CSS rewrite using mobile-first approach
- Improved responsive breakpoints (768px, 992px, 1200px)
- Better dark mode contrast and colors
- Enhanced hover states with `@media (hover: hover)`
- Optimized grid layout for different viewports
- More efficient CSS custom properties structure

### Fixed
- Touch targets now meet WCAG accessibility guidelines
- Focus indicators are now more visible
- Animations respect user motion preferences
- Better spacing on mobile devices
- Improved text readability on small screens

## [0.1.10] - 2025-07-10

### Fixed
- Fixed missing `use` statement for CacheManager class that caused fatal error
- Added type safety check for post type support to prevent foreach() warning
- Improved error handling for class autoloading

### Changed
- Enhanced register_post_type_support() method with array validation

## [0.1.9] - 2025-07-10

### Fixed
- Improved boolean attribute handling in block settings
- Fixed cache key generation issues

## [0.1.8] - 2025-07-10

### Added
- Build automation scripts for creating release packages
  - `build-release.ps1` for Windows PowerShell
  - `build-release.sh` for Unix/Linux bash
- Production-ready ZIP package generation
- Automated file collection for deployment
- Unit tests for rate limiting, asset loading, and cache management
- Shared CacheManager instance across plugin components
- CacheManager hooks initialization for automatic cache invalidation

### Changed
- Improved release process with automated packaging
- Build scripts now use absolute paths for reliability
- Rate limiting now properly uses `postgrid_rate_limit` option from settings
- REST controller uses shared CacheManager instance instead of creating its own

### Fixed
- Build directory is now included in release packages
- All required files are properly packaged for staging/production deployment
- Improved boolean attribute handling in block settings
- Fixed cache key generation issues
- CacheManager hooks are now properly initialized on plugin load

## [0.1.7] - 2025-07-10

### Fixed
- Critical autoloader issue that was causing "Class not found" fatal errors
- Autoloader now properly handles CamelCase to kebab-case filename conversion
- Namespace resolution for nested classes (e.g., PostGrid\Core\AssetManager)

### Added
- Composer support with PSR-4 autoloading configuration
- Fallback autoloader when Composer is not available
- Comprehensive error handling with user-friendly admin notices
- Build file validation before plugin activation
- Settings page with proper WordPress admin integration
- WordPress coding standards configuration (phpcs.xml)
- Security headers for direct file access prevention
- Plugin initialization state tracking

### Changed
- Refactored from global variable to proper singleton pattern
- Improved error messages to be more helpful and actionable
- Enhanced security with proper escaping throughout
- Better component initialization with error boundaries
- Updated deprecated function handling

### Security
- Added proper 403 headers when blocking direct access
- Enhanced input sanitization and output escaping
- Improved prepared statements in database queries

## [0.1.6] - 2025-01-11

### Added
- Complete architecture refactoring with modular components
- PSR-4 autoloading with proper namespace structure
- Comprehensive caching system with transient and object cache support
- REST API rate limiting (60 requests/minute for non-authenticated users)
- 20+ developer hooks and filters for extensibility
- Support for custom post types via `postgrid_supported_post_types` filter
- Support for custom taxonomies via `postgrid_supported_taxonomies` filter
- Admin bar integration for cache clearing
- PHPUnit test suite with bootstrap configuration
- CSS custom properties for advanced theming
- Dark mode support with prefers-color-scheme
- Print styles for better printing experience
- Accessibility improvements with focus states
- Performance monitoring and optimization tools

### Changed
- Refactored main plugin class into separate components
- Improved asset loading with conditional enqueuing
- Enhanced security with proper permission callbacks
- Better error handling throughout the codebase
- Modernized CSS with custom properties
- Improved mobile responsive design

### Fixed
- Asset loading conflicts with block.json
- Cache invalidation on post updates
- Memory leaks in long-running processes

### Security
- Added rate limiting to REST API endpoints
- Implemented proper CSRF protection
- Enhanced data sanitization and escaping
- Added IP-based request throttling

## [0.1.5] - 2025-01-10

### Added
- Initial release
- Ultra-lean architecture
- Modern block.json structure
- REST API endpoint
- Minimal custom CSS (under 100 lines)
- No external dependencies

# PostGrid - Modern WordPress Posts Grid Block

A lightweight, performant, and extensible posts grid block for WordPress, built with modern development practices.

## Features

- **Modern Architecture**: Clean separation of concerns with PSR-4 autoloading
- **Performance Optimized**: Built-in caching, lazy loading, and optimized queries
- **Developer Friendly**: Extensive hooks and filters for customization
- **Modern WordPress**: Built specifically for the block editor
- **Security First**: Proper data sanitization, escaping, and permission checks
- **REST API**: Complete REST API with rate limiting
- **No Dependencies**: No jQuery or external CSS frameworks

## Architecture Improvements

### 1. Modular Structure
```
includes/
├── Core/
│   ├── AssetManager.php      # Handles all asset loading
│   ├── CacheManager.php       # Caching functionality
│   └── HooksManager.php       # Extensibility hooks
├── Blocks/
│   ├── BlockRegistry.php      # Block registration
│   └── BlockRenderer.php      # Rendering logic
├── Api/
│   └── RestController.php     # REST API endpoints
```

### 2. Caching Strategy
- Transient-based caching with object cache support
- Automatic cache invalidation on post updates
- Admin bar integration for manual cache clearing

### 3. Security Enhancements
- Rate limiting for REST API endpoints
- Proper permission callbacks
- CSRF protection
- Data sanitization throughout

### 4. Performance Optimizations
- Conditional asset loading
- Lazy loading support
- Optimized database queries
- CSS custom properties for efficient theming

## Available Hooks

### Actions
- `postgrid_before_grid` - Before grid output
- `postgrid_after_grid` - After grid output  
- `postgrid_before_item` - Before each item
- `postgrid_after_item` - After each item
- `postgrid_init` - Plugin initialization
- `postgrid_loaded` - After plugin loads

### Filters
- `postgrid_query_args` - Modify WP_Query arguments
- `postgrid_supported_post_types` - Add custom post types
- `postgrid_supported_taxonomies` - Add custom taxonomies
- `postgrid_cache_expiration` - Set cache duration
- `postgrid_item_classes` - Modify item CSS classes
- `postgrid_grid_classes` - Modify grid CSS classes

## Usage Examples

### Enable Custom Post Types
```php
add_filter( 'postgrid_supported_post_types', function( $post_types ) {
    $post_types[] = 'product';
    $post_types[] = 'portfolio';
    return $post_types;
} );
```

### Modify Query Parameters
```php
add_filter( 'postgrid_query_args', function( $args, $attributes ) {
    // Only show posts from specific authors
    $args['author__in'] = array( 1, 2, 3 );
    return $args;
}, 10, 2 );
```

### Add Custom Item Content
```php
add_action( 'postgrid_after_item', function( $post, $attributes ) {
    echo '<div class="custom-content">';
    echo get_post_meta( $post->ID, 'custom_field', true );
    echo '</div>';
}, 10, 2 );
```

### Customize Cache Duration
```php
add_filter( 'postgrid_cache_expiration', function() {
    return 10 * MINUTE_IN_SECONDS;
} );
```

## REST API Endpoints

### Get Posts
```
POST /wp-json/postgrid/v1/posts
```

Parameters:
- `per_page` (int) - Number of posts
- `orderby` (string) - Sort field
- `order` (string) - Sort order
- `category` (int) - Category ID
- `post_type` (string) - Post type
- `search` (string) - Search terms

### Get Categories
```
GET /wp-json/postgrid/v1/categories
```

### Get Post Types
```
GET /wp-json/postgrid/v1/post-types
```

## Testing

Run PHPUnit tests:
```bash
composer test
```

Run JavaScript tests:
```bash
npm run test:unit
```

## Performance Benchmarks

- **Page Load**: < 50ms render time
- **Asset Size**: < 10KB CSS, < 20KB JS
- **Database Queries**: 1-2 queries per block
- **Cache Hit Rate**: > 90% on typical usage

## Browser Support

- Chrome/Edge (last 2 versions)
- Firefox (last 2 versions)
- Safari (last 2 versions)
- Mobile browsers (iOS Safari, Chrome Android)

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

GPL v2 or later

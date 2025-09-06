<?php
/**
 * Block Renderer
 *
 * @package PostGrid
 * @since 1.0.0
 */

namespace PostGrid\Blocks;

use PostGrid\Core\HooksManager;
use PostGrid\Core\CacheManager;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders PostGrid blocks
 */
class BlockRenderer {
	
	/**
	 * Cache manager instance
	 *
	 * @var CacheManager
	 */
	private $cache;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->cache = new CacheManager();
	}
	
	/**
	 * Render block
	 *
	 * @param array     $attributes Block attributes.
	 * @param string    $content Block content.
	 * @param WP_Block $block Block instance.
	 * @return string
	 */
	public function render( $attributes, $content = '', $block = null ) {
		// Debug: Log raw attributes (sanitized for security)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// Sanitize sensitive data before logging
			$sanitized_attrs = array_map( function( $value ) {
				if ( is_array( $value ) ) {
					return '[Array: ' . count( $value ) . ' items]';
				}
				return is_string( $value ) ? substr( sanitize_text_field( $value ), 0, 100 ) : strval( $value );
			}, $attributes );
			error_log( 'PostGrid - Raw attributes: ' . wp_json_encode( $sanitized_attrs ) );
		}
		
		// Ensure frontend styles are loaded
		if ( ! is_admin() ) {
			wp_enqueue_style( 'postgrid-frontend' );
		}
		
		// Normalize attributes
		$attributes = $this->normalize_attributes( $attributes );
		
		// Debug: Log normalized attributes (sanitized for security)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$sanitized_attrs = array_map( function( $value ) {
				if ( is_array( $value ) ) {
					return '[Array: ' . count( $value ) . ' items]';
				}
				return is_string( $value ) ? substr( sanitize_text_field( $value ), 0, 100 ) : strval( $value );
			}, $attributes );
			error_log( 'PostGrid - Normalized attributes: ' . wp_json_encode( $sanitized_attrs ) );
			error_log( 'PostGrid - showDate: ' . ( $attributes['showDate'] ? 'true' : 'false' ) );
			error_log( 'PostGrid - showExcerpt: ' . ( $attributes['showExcerpt'] ? 'true' : 'false' ) );
		}
		
		// Check cache first
		$cache_key = HooksManager::get_cache_key( array(), $attributes );
		$cached_output = $this->cache->get( $cache_key );
		
		if ( false !== $cached_output && ! HooksManager::should_bypass_cache( $attributes, array() ) ) {
			return $cached_output;
		}
		
		// Get posts
		$posts = $this->get_posts( $attributes );
		
		if ( empty( $posts ) ) {
			return $this->render_no_posts_message();
		}
		
		// Render output
		$output = $this->render_grid( $posts, $attributes );
		
		// Cache the output
		$this->cache->set( $cache_key, $output, HooksManager::get_cache_expiration() );
		
		return $output;
	}
	
	/**
	 * Normalize block attributes
	 *
	 * @param array $attributes Raw attributes.
	 * @return array
	 */
	private function normalize_attributes( $attributes ) {
		$defaults = array(
			'postsPerPage' => 6,
			'orderBy' => 'date',
			'order' => 'desc',
			'selectedCategory' => 0,
			'columns' => 3,
			'showDate' => true,
			'showExcerpt' => true,
			'postType' => 'post',
			'excerptLength' => 20,
			'showThumbnail' => true,      // Changed to true by default
			'thumbnailSize' => 'medium_large',  // Better size for modern design
			'showAuthor' => false,
			'showCategories' => true,     // Show categories for visual interest
		);
		
		// Sanitize and validate specific attributes
		if ( isset( $attributes['postsPerPage'] ) ) {
			$attributes['postsPerPage'] = max( 1, min( 100, absint( $attributes['postsPerPage'] ) ) );
		}
		
		if ( isset( $attributes['orderBy'] ) ) {
			$allowed_orderby = array( 'date', 'title', 'menu_order', 'rand', 'comment_count', 'modified' );
			$attributes['orderBy'] = in_array( $attributes['orderBy'], $allowed_orderby, true ) ? $attributes['orderBy'] : 'date';
		}
		
		if ( isset( $attributes['order'] ) ) {
			$attributes['order'] = strtoupper( $attributes['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		}
		
		if ( isset( $attributes['columns'] ) ) {
			$attributes['columns'] = max( 1, min( 6, absint( $attributes['columns'] ) ) );
		}
		
		if ( isset( $attributes['selectedCategory'] ) ) {
			$attributes['selectedCategory'] = absint( $attributes['selectedCategory'] );
		}
		
		if ( isset( $attributes['excerptLength'] ) ) {
			$attributes['excerptLength'] = max( 5, min( 100, absint( $attributes['excerptLength'] ) ) );
		}
		
		if ( isset( $attributes['thumbnailSize'] ) ) {
			$allowed_sizes = get_intermediate_image_sizes();
			$allowed_sizes[] = 'full';
			$attributes['thumbnailSize'] = in_array( $attributes['thumbnailSize'], $allowed_sizes, true ) ? $attributes['thumbnailSize'] : 'medium_large';
		}
		
		// Handle boolean values explicitly
		$boolean_attrs = array('showDate', 'showExcerpt', 'showThumbnail', 'showAuthor', 'showCategories');
		
		foreach ($boolean_attrs as $attr) {
			if (isset($attributes[$attr])) {
				// Ensure boolean type
				$attributes[$attr] = filter_var($attributes[$attr], FILTER_VALIDATE_BOOLEAN);
			}
		}
		
		return wp_parse_args( $attributes, $defaults );
	}
	
	/**
	 * Get posts based on attributes
	 *
	 * @param array $attributes Block attributes.
	 * @return array
	 */
	private function get_posts( $attributes ) {
		$args = array(
			'posts_per_page' => absint( $attributes['postsPerPage'] ),
			'orderby' => sanitize_key( $attributes['orderBy'] ),
			'order' => strtoupper( $attributes['order'] ) === 'ASC' ? 'ASC' : 'DESC',
			'post_status' => 'publish',
			'post_type' => $this->get_allowed_post_type( $attributes['postType'] ),
		);
		
		// Category filter with proper validation
		if ( ! empty( $attributes['selectedCategory'] ) ) {
			$category_id = absint( $attributes['selectedCategory'] );
			if ( $category_id > 0 && term_exists( $category_id, 'category' ) ) {
				$args['cat'] = $category_id;
			}
		}
		
		// Apply filters
		$args = HooksManager::apply_query_filters( $args, $attributes );
		
		// Get posts
		$posts = get_posts( $args );
		
		// Apply post query filter
		return apply_filters( 'postgrid_posts_query', $posts, $attributes );
	}
	
	/**
	 * Get allowed post type
	 *
	 * @param string $post_type Requested post type.
	 * @return string
	 */
	private function get_allowed_post_type( $post_type ) {
		$allowed_types = HooksManager::get_supported_post_types();
		
		if ( in_array( $post_type, $allowed_types, true ) ) {
			return $post_type;
		}
		
		return 'post';
	}
	
	/**
	 * Render the posts grid
	 *
	 * @param array $posts Posts array.
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	private function render_grid( $posts, $attributes ) {
		$classes = array( 'wp-block-postgrid', 'columns-' . absint( $attributes['columns'] ) );
		$classes = apply_filters( 'postgrid_grid_classes', $classes, $attributes );
		
		ob_start();
		
		do_action( 'postgrid_before_grid', $attributes );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php foreach ( $posts as $post ) : ?>
				<?php $this->render_item( $post, $attributes ); ?>
			<?php endforeach; ?>
		</div>
		<?php
		do_action( 'postgrid_after_grid', $attributes );
		
		return ob_get_clean();
	}
	
	/**
	 * Render individual post item
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $attributes Block attributes.
	 */
	private function render_item( $post, $attributes ) {
		// Debug: Log item attributes
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'PostGrid - Rendering item for post ' . $post->ID );
			error_log( 'PostGrid - Item showDate: ' . var_export( $attributes['showDate'], true ) );
			error_log( 'PostGrid - Item showExcerpt: ' . var_export( $attributes['showExcerpt'], true ) );
		}
		
		$item_classes = array( 'wp-block-postgrid__item' );
		
		// Add class if post has thumbnail
		if ( has_post_thumbnail( $post ) ) {
			$item_classes[] = 'has-post-thumbnail';
		}
		
		$item_classes = apply_filters( 'postgrid_item_classes', $item_classes, $post );
		
		do_action( 'postgrid_before_item', $post, $attributes );
		?>
		<article class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>">
			<?php if ( has_post_thumbnail( $post ) ) : ?>
				<div class="wp-block-postgrid__thumbnail">
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" rel="bookmark">
						<?php echo get_the_post_thumbnail( $post, 'medium_large', array(
							'class' => 'wp-block-postgrid__image',
							'loading' => 'lazy'
						) ); ?>
					</a>
				</div>
			<?php endif; ?>
			
			<?php if ( $attributes['showCategories'] ) : ?>
				<?php
				$categories = get_the_category( $post->ID );
				if ( ! empty( $categories ) ) : ?>
					<div class="wp-block-postgrid__categories">
						<?php foreach ( array_slice( $categories, 0, 1 ) as $category ) : ?>
							<a href="<?php echo esc_url( get_category_link( $category ) ); ?>">
								<?php echo esc_html( $category->name ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			
			<div class="wp-block-postgrid__content">
				<h3 class="wp-block-postgrid__title">
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
						<?php echo esc_html( apply_filters( 'postgrid_item_title', get_the_title( $post ), $post ) ); ?>
					</a>
				</h3>
				
				<?php if ( $attributes['showAuthor'] || $attributes['showDate'] ) : ?>
					<div class="wp-block-postgrid__meta">
						<?php $this->render_meta( $post, $attributes ); ?>
					</div>
				<?php endif; ?>
				
				<?php if ( $attributes['showExcerpt'] ) : ?>
					<div class="wp-block-postgrid__excerpt">
						<?php echo wp_kses_post( $this->get_excerpt( $post, $attributes ) ); ?>
					</div>
				<?php endif; ?>
			</div>
		</article>
		<?php
		do_action( 'postgrid_after_item', $post, $attributes );
	}
	
	/**
	 * Render post meta
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $attributes Block attributes.
	 */
	private function render_meta( $post, $attributes ) {
		$meta_items = array();
		
		if ( $attributes['showAuthor'] ) {
			$author_link = get_author_posts_url( $post->post_author );
			$author_name = get_the_author_meta( 'display_name', $post->post_author );
			$meta_items[] = sprintf(
				'<span class="wp-block-postgrid__author">%s <a href="%s">%s</a></span>',
				esc_html__( 'by', 'postgrid' ),
				esc_url( $author_link ),
				esc_html( $author_name )
			);
		}
		
		if ( $attributes['showDate'] ) {
			$date_format = apply_filters( 'postgrid_item_date_format', get_option( 'date_format' ) );
			$meta_items[] = sprintf(
				'<time class="wp-block-postgrid__date" datetime="%s">%s</time>',
				esc_attr( get_the_date( 'c', $post ) ),
				esc_html( get_the_date( $date_format, $post ) )
			);
		}
		
		echo implode( ' <span class="wp-block-postgrid__meta-separator">|</span> ', $meta_items );
	}
	
	/**
	 * Get post excerpt
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $attributes Block attributes.
	 * @return string
	 */
	private function get_excerpt( $post, $attributes ) {
		$excerpt = get_the_excerpt( $post );
		
		// Trim to specified length
		if ( ! empty( $attributes['excerptLength'] ) ) {
			$excerpt = wp_trim_words( $excerpt, absint( $attributes['excerptLength'] ) );
		}
		
		return apply_filters( 'postgrid_item_excerpt', $excerpt, $post );
	}
	
	/**
	 * Render no posts message
	 *
	 * @return string
	 */
	private function render_no_posts_message() {
		$message = __( 'No posts found.', 'postgrid' );
		$message = apply_filters( 'postgrid_no_posts_message', $message );
		
		return sprintf( '<p class="wp-block-postgrid__no-posts">%s</p>', esc_html( $message ) );
	}
}

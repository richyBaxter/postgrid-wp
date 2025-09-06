<?php
/**
 * Legacy Support
 *
 * @package PostGrid
 * @since 1.0.0
 */

namespace PostGrid\Compatibility;

use PostGrid\Blocks\BlockRenderer;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles legacy Caxton compatibility
 */
class LegacySupport {
	
	/**
	 * Block renderer instance
	 *
	 * @var BlockRenderer
	 */
	private $renderer;
	
	/**
	 * Legacy shortcodes
	 *
	 * @var array
	 */
	private $legacy_shortcodes = array(
		'caxton/posts',
		'caxton/posts-grid',
		'caxton/post-grid',
		'caxton/grid',
	);
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->renderer = new BlockRenderer();
	}
	
	/**
	 * Initialize legacy support
	 */
	public function init() {
		// Register legacy blocks
		add_action( 'init', array( $this, 'register_legacy_blocks' ), 20 );
		
		// Register legacy shortcodes
		add_action( 'init', array( $this, 'register_legacy_shortcodes' ) );
		
		// Convert blocks on the fly
		add_filter( 'render_block', array( $this, 'convert_legacy_blocks' ), 10, 2 );
		
		// Migration admin notice
		add_action( 'admin_notices', array( $this, 'migration_notice' ) );
	}
	
	/**
	 * Register legacy Caxton blocks
	 */
	public function register_legacy_blocks() {
		// Only register if not already registered
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'caxton/posts-grid' ) ) {
			register_block_type( 'caxton/posts-grid', array(
				'render_callback' => array( $this, 'render_legacy_block' ),
				'attributes' => $this->get_legacy_attributes(),
			) );
		}
	}
	
	/**
	 * Register legacy shortcodes
	 */
	public function register_legacy_shortcodes() {
		foreach ( $this->legacy_shortcodes as $shortcode ) {
			add_shortcode( $shortcode, array( $this, 'render_legacy_shortcode' ) );
		}
		
		// Also register the modern shortcode
		add_shortcode( 'postgrid', array( $this, 'render_legacy_shortcode' ) );
	}
	
	/**
	 * Render legacy block
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_legacy_block( $attributes ) {
		// Convert legacy attributes
		$attributes = $this->convert_legacy_attributes( $attributes );
		
		// Use the modern renderer
		return $this->renderer->render( $attributes );
	}
	
	/**
	 * Render legacy shortcode
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string
	 */
	public function render_legacy_shortcode( $atts, $content = '' ) {
		// Parse attributes
		$attributes = shortcode_atts( array(
			'posts' => 6,
			'count' => 6,
			'posts_per_page' => 6,
			'columns' => 3,
			'cols' => 3,
			'category' => '',
			'cat' => '',
			'orderby' => 'date',
			'order' => 'desc',
			'show_date' => true,
			'show_excerpt' => true,
		), $atts );
		
		// Convert to modern format
		$modern_atts = array(
			'postsPerPage' => absint( $attributes['posts'] ?: $attributes['count'] ?: $attributes['posts_per_page'] ),
			'columns' => absint( $attributes['columns'] ?: $attributes['cols'] ),
			'orderBy' => sanitize_key( $attributes['orderby'] ),
			'order' => strtolower( $attributes['order'] ),
			'showDate' => $this->parse_bool( $attributes['show_date'] ),
			'showExcerpt' => $this->parse_bool( $attributes['show_excerpt'] ),
		);
		
		// Handle category
		$category = $attributes['category'] ?: $attributes['cat'];
		if ( $category ) {
			if ( is_numeric( $category ) ) {
				$modern_atts['selectedCategory'] = absint( $category );
			} else {
				// Convert slug to ID
				$term = get_term_by( 'slug', $category, 'category' );
				if ( $term ) {
					$modern_atts['selectedCategory'] = $term->term_id;
				}
			}
		}
		
		return $this->renderer->render( $modern_atts );
	}
	
	/**
	 * Convert legacy blocks on the fly
	 *
	 * @param string $block_content Block content.
	 * @param array  $block Block data.
	 * @return string
	 */
	public function convert_legacy_blocks( $block_content, $block ) {
		// Check if this is a legacy Caxton block
		if ( strpos( $block['blockName'], 'caxton/' ) === 0 ) {
			// Log conversion for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'PostGrid: Converting legacy block ' . $block['blockName'] );
			}
			
			// Add migration notice
			if ( current_user_can( 'edit_posts' ) && ! is_admin() ) {
				$block_content = '<!-- PostGrid: Legacy Caxton block detected and converted -->' . $block_content;
			}
		}
		
		return $block_content;
	}
	
	/**
	 * Get legacy block attributes
	 *
	 * @return array
	 */
	private function get_legacy_attributes() {
		return array(
			'postsPerPage' => array(
				'type' => 'number',
				'default' => 6,
			),
			'orderBy' => array(
				'type' => 'string',
				'default' => 'date',
			),
			'order' => array(
				'type' => 'string',
				'default' => 'desc',
			),
			'selectedCategory' => array(
				'type' => 'number',
				'default' => 0,
			),
			'columns' => array(
				'type' => 'number',
				'default' => 3,
			),
			'showDate' => array(
				'type' => 'boolean',
				'default' => true,
			),
			'showExcerpt' => array(
				'type' => 'boolean',
				'default' => true,
			),
			// Legacy attribute names
			'posts' => array(
				'type' => 'number',
				'default' => 6,
			),
			'cols' => array(
				'type' => 'number',
				'default' => 3,
			),
			'category' => array(
				'type' => 'string',
				'default' => '',
			),
		);
	}
	
	/**
	 * Convert legacy attributes to modern format
	 *
	 * @param array $attributes Legacy attributes.
	 * @return array
	 */
	private function convert_legacy_attributes( $attributes ) {
		$converted = array();
		
		// Map legacy attribute names
		$mappings = array(
			'posts' => 'postsPerPage',
			'cols' => 'columns',
			'category' => 'selectedCategory',
			'show_date' => 'showDate',
			'show_excerpt' => 'showExcerpt',
		);
		
		foreach ( $attributes as $key => $value ) {
			if ( isset( $mappings[ $key ] ) ) {
				$converted[ $mappings[ $key ] ] = $value;
			} else {
				$converted[ $key ] = $value;
			}
		}
		
		// Handle category conversion
		if ( isset( $converted['selectedCategory'] ) && ! is_numeric( $converted['selectedCategory'] ) ) {
			$term = get_term_by( 'slug', $converted['selectedCategory'], 'category' );
			if ( $term ) {
				$converted['selectedCategory'] = $term->term_id;
			}
		}
		
		return $converted;
	}
	
	/**
	 * Parse boolean value
	 *
	 * @param mixed $value Value to parse.
	 * @return bool
	 */
	private function parse_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		
		if ( is_string( $value ) ) {
			return ! in_array( strtolower( $value ), array( 'false', '0', 'no', '' ), true );
		}
		
		return (bool) $value;
	}
	
	/**
	 * Show migration notice
	 */
	public function migration_notice() {
		// Only show on posts/pages list
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-post', 'edit-page' ), true ) ) {
			return;
		}
		
		// Check if there are any Caxton blocks
		if ( ! $this->has_legacy_content() ) {
			return;
		}
		
		// Check if notice was dismissed
		if ( get_user_meta( get_current_user_id(), 'postgrid_dismiss_migration_notice', true ) ) {
			return;
		}
		
		?>
		<div class="notice notice-info is-dismissible" id="postgrid-migration-notice">
			<p>
				<strong><?php esc_html_e( 'PostGrid:', 'postgrid' ); ?></strong>
				<?php esc_html_e( 'Legacy Caxton blocks detected. They will continue to work, but we recommend migrating to the new PostGrid blocks for better performance.', 'postgrid' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=postgrid-migration' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Start Migration', 'postgrid' ); ?>
				</a>
				<a href="#" class="button" id="postgrid-dismiss-migration">
					<?php esc_html_e( 'Dismiss', 'postgrid' ); ?>
				</a>
			</p>
		</div>
		<script>
		jQuery( function( $ ) {
			$( '#postgrid-dismiss-migration' ).on( 'click', function( e ) {
				e.preventDefault();
				$.post( ajaxurl, {
					action: 'postgrid_dismiss_migration_notice',
					_wpnonce: '<?php echo wp_create_nonce( 'postgrid-dismiss-migration' ); ?>'
				} );
				$( '#postgrid-migration-notice' ).fadeOut();
			} );
		} );
		</script>
		<?php
	}
	
	/**
	 * Check if site has legacy content
	 *
	 * @return bool
	 */
	private function has_legacy_content() {
		// Check transient first
		$has_legacy = get_transient( 'postgrid_has_legacy_content' );
		
		if ( false !== $has_legacy ) {
			return $has_legacy;
		}
		
		// Check database for Caxton blocks
		global $wpdb;
		
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} 
				WHERE post_content LIKE %s 
				AND post_status IN ('publish', 'draft', 'pending', 'private')",
				'%wp:caxton/%'
			)
		);
		
		$has_legacy = $count > 0;
		
		// Cache for 1 hour
		set_transient( 'postgrid_has_legacy_content', $has_legacy, HOUR_IN_SECONDS );
		
		return $has_legacy;
	}
}

// Handle AJAX dismiss
add_action( 'wp_ajax_postgrid_dismiss_migration_notice', function() {
	check_ajax_referer( 'postgrid-dismiss-migration' );
	
	update_user_meta( get_current_user_id(), 'postgrid_dismiss_migration_notice', true );
	wp_die();
} );

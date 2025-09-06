<?php
/**
 * Asset Manager
 *
 * @package PostGrid
 * @since 1.0.0
 */

namespace PostGrid\Core;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles asset loading and management
 */
class AssetManager {
	
	/**
	 * Enqueued styles tracking
	 *
	 * @var array
	 */
	private $enqueued_styles = array();
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Allow filtering of asset loading
		add_filter( 'postgrid_should_load_assets', array( $this, 'should_load_assets' ), 10, 2 );
	}
	
	/**
	 * Enqueue editor assets
	 */
	public function enqueue_editor_assets() {
		// Check if assets are already loaded by block.json
		if ( $this->is_asset_loaded( 'postgrid-postgrid-editor-script' ) ) {
			return;
		}
		
		$asset_file = $this->get_asset_file( 'index' );
		
		wp_enqueue_script(
			'postgrid-editor',
			POSTGRID_PLUGIN_URL . 'build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);
		
		// Localize script
		wp_localize_script( 'postgrid-editor', 'postgridData', array(
			'apiUrl' => rest_url( 'postgrid/v1' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'cacheTime' => apply_filters( 'postgrid_cache_time', 5 * MINUTE_IN_SECONDS ),
		) );
		
		// Editor styles
		wp_enqueue_style(
			'postgrid-editor',
			POSTGRID_PLUGIN_URL . 'build/index.css',
			array( 'wp-edit-blocks' ),
			$asset_file['version']
		);
	}
	
	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Only load on singular posts/pages or archives
		if ( ! $this->should_load_frontend_assets() ) {
			return;
		}
		
		// Check if block is present
		if ( ! $this->has_postgrid_block() ) {
			return;
		}
		
		$this->enqueue_frontend_styles();
	}
	
	/**
	 * Enqueue frontend styles
	 */
	public function enqueue_frontend_styles() {
		// Prevent duplicate enqueuing
		if ( in_array( 'postgrid-frontend', $this->enqueued_styles, true ) ) {
			return;
		}
		
		$asset_file = $this->get_asset_file( 'style-index' );
		
		wp_enqueue_style(
			'postgrid-frontend',
			POSTGRID_PLUGIN_URL . 'build/style-index.css',
			array(),
			$asset_file['version']
		);
		
		$this->enqueued_styles[] = 'postgrid-frontend';
		
		// Add inline styles for custom properties
		$this->add_inline_styles();
	}
	
	/**
	 * Add inline styles
	 */
	private function add_inline_styles() {
		$custom_css = '';
		
		// Add CSS custom properties for theming
		$custom_css .= ':root {';
		$custom_css .= '--postgrid-gap: ' . apply_filters( 'postgrid_grid_gap', '1.5rem' ) . ';';
		$custom_css .= '--postgrid-item-bg: ' . apply_filters( 'postgrid_item_bg', '#f5f5f5' ) . ';';
		$custom_css .= '--postgrid-item-padding: ' . apply_filters( 'postgrid_item_padding', '1.5rem' ) . ';';
		$custom_css .= '}';
		
		wp_add_inline_style( 'postgrid-frontend', $custom_css );
	}
	
	/**
	 * Get asset file data
	 *
	 * @param string $filename Asset filename without extension.
	 * @return array
	 */
	private function get_asset_file( $filename ) {
		$asset_path = POSTGRID_PLUGIN_DIR . 'build/' . $filename . '.asset.php';
		
		if ( file_exists( $asset_path ) ) {
			return require $asset_path;
		}
		
		// Fallback
		return array(
			'dependencies' => array(),
			'version' => POSTGRID_VERSION,
		);
	}
	
	/**
	 * Check if asset is already loaded
	 *
	 * @param string $handle Asset handle.
	 * @return bool
	 */
	private function is_asset_loaded( $handle ) {
		return wp_script_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'registered' );
	}
	
	/**
	 * Check if should load frontend assets
	 *
	 * @return bool
	 */
	private function should_load_frontend_assets() {
		// Allow filtering
		$should_load = is_singular() || is_home() || is_archive();
		
		return apply_filters( 'postgrid_should_load_assets', $should_load, 'frontend' );
	}
	
	/**
	 * Check if page has PostGrid block
	 *
	 * @return bool
	 */
	private function has_postgrid_block() {
		// Check for PostGrid blocks
		$blocks = array( 'postgrid/postgrid' );
		
		foreach ( $blocks as $block ) {
			if ( has_block( $block ) ) {
				return true;
			}
		}
		
		// Check for shortcodes
		if ( is_singular() ) {
			$post = get_post();
			if ( $post && $this->has_postgrid_shortcode( $post->post_content ) ) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Check if content has PostGrid shortcode
	 *
	 * @param string $content Content to check.
	 * @return bool
	 */
	private function has_postgrid_shortcode( $content ) {
		$shortcodes = array( 'postgrid' );
		
		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $content, $shortcode ) ) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Filter callback for asset loading
	 *
	 * @param bool   $should_load Whether to load assets.
	 * @param string $context Context (frontend/editor).
	 * @return bool
	 */
	public function should_load_assets( $should_load, $context ) {
		// Example: Disable on specific post types
		if ( 'frontend' === $context && is_singular( 'product' ) ) {
			return false;
		}
		
		return $should_load;
	}
}

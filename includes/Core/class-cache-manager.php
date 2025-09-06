<?php
/**
 * Cache Manager
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
 * Handles caching for PostGrid
 */
class CacheManager {
	
	/**
	 * Cache group
	 *
	 * @var string
	 */
	private $cache_group = 'postgrid';
	
	/**
	 * Get cached data
	 *
	 * @param string $key Cache key.
	 * @return mixed
	 */
	public function get( $key ) {
		// Try object cache first
		$cached = wp_cache_get( $key, $this->cache_group );
		
		if ( false !== $cached ) {
			return $cached;
		}
		
		// Fallback to transient
		return get_transient( $key );
	}
	
	/**
	 * Set cached data
	 *
	 * @param string $key Cache key.
	 * @param mixed  $data Data to cache.
	 * @param int    $expiration Expiration time in seconds.
	 * @return bool
	 */
	public function set( $key, $data, $expiration = 0 ) {
		// Validate expiration time to prevent overflow issues
		if ( ! is_numeric( $expiration ) || $expiration < 0 ) {
			$expiration = 0;
		}
		
		// Set in object cache
		wp_cache_set( $key, $data, $this->cache_group, $expiration );
		
		// Also set as transient for persistence
		return set_transient( $key, $data, $expiration );
	}
	
	/**
	 * Delete cached data
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function delete( $key ) {
		wp_cache_delete( $key, $this->cache_group );
		return delete_transient( $key );
	}
	
	/**
	 * Flush all PostGrid cache
	 *
	 * @return bool
	 */
	public function flush() {
		// Flush object cache group
		wp_cache_flush_group( $this->cache_group );
		
		// Delete all PostGrid transients
		global $wpdb;
		
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_postgrid_%'
			)
		);
		
		foreach ( $transients as $transient ) {
			delete_transient( str_replace( '_transient_', '', $transient ) );
		}
		
		return true;
	}
	
	/**
	 * Clear cache on post update
	 *
	 * @param int $post_id Post ID.
	 */
	public function clear_on_post_update( $post_id ) {
		// Only clear for published posts
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}
		
		// Clear all PostGrid cache when a post is updated
		$this->flush();
	}
	
	/**
	 * Initialize cache hooks
	 */
	public function init_hooks() {
		// Clear cache on post save
		add_action( 'save_post', array( $this, 'clear_on_post_update' ) );
		add_action( 'deleted_post', array( $this, 'clear_on_post_update' ) );
		add_action( 'switch_theme', array( $this, 'flush' ) );
		
		// Add admin bar menu item
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );
		
		// Handle cache clear action
		add_action( 'admin_init', array( $this, 'handle_cache_clear' ) );
	}
	
	/**
	 * Add admin bar menu item
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$wp_admin_bar->add_node( array(
			'id' => 'postgrid-clear-cache',
			'title' => __( 'Clear PostGrid Cache', 'postgrid' ),
			'href' => wp_nonce_url( admin_url( '?postgrid-clear-cache=1' ), 'postgrid-clear-cache' ),
			'parent' => 'top-secondary',
		) );
	}
	
	/**
	 * Handle cache clear action
	 */
	public function handle_cache_clear() {
		if ( ! isset( $_GET['postgrid-clear-cache'] ) ) {
			return;
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'postgrid-clear-cache' ) ) {
			return;
		}
		
		$this->flush();
		
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>' . esc_html__( 'PostGrid cache cleared successfully.', 'postgrid' ) . '</p>';
			echo '</div>';
		} );
	}
}

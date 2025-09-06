<?php
/**
 * Main Plugin Class
 *
 * @package PostGrid
 * @since 1.0.0
 */

namespace PostGrid;

use PostGrid\Services\ServiceContainer;
use PostGrid\Services\ComponentInitializer;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class implementing proper singleton pattern
 * Refactored to use service-oriented architecture with dependency injection
 */
class Plugin {
	
	/**
	 * Plugin instance
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;
	
	/**
	 * Service container
	 *
	 * @var ServiceContainer
	 */
	private $container;
	
	/**
	 * Component initializer
	 *
	 * @var ComponentInitializer
	 */
	private $initializer;
	
	/**
	 * Plugin initialization state
	 *
	 * @var bool
	 */
	private $initialized = false;
	
	/**
	 * Get plugin instance
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Constructor - private to enforce singleton
	 */
	private function __construct() {
		// Initialize on the next tick to ensure WordPress is ready
		add_action( 'init', array( $this, 'initialize' ), 0 );
	}
	
	/**
	 * Prevent cloning
	 */
	private function __clone() {
		_doing_it_wrong( 
			__FUNCTION__, 
			esc_html__( 'PostGrid Plugin class is a singleton and should not be cloned.', 'postgrid' ),
			'0.1.8'
		);
	}
	
	/**
	 * Prevent unserializing
	 */
	public function __wakeup() {
		_doing_it_wrong( 
			__FUNCTION__, 
			esc_html__( 'PostGrid Plugin class is a singleton and should not be unserialized.', 'postgrid' ),
			'0.1.8'
		);
	}
	
	/**
	 * Initialize the plugin
	 */
	public function initialize() {
		// Prevent double initialization
		if ( $this->initialized ) {
			return;
		}
		
		$this->initialized = true;
		
		// Load text domain early
		$this->load_textdomain();
		
		// Initialize service container
		$this->container = ServiceContainer::get_instance();
		
		// Initialize component initializer
		$this->initializer = $this->container->get( 'initializer' );
		
		// Initialize all components through the service architecture
		$initialization_results = $this->initializer->initialize_all();
		
		// Only proceed if components initialized successfully
		if ( $this->initializer->all_components_loaded() ) {
			$this->init_hooks();
			
			// Fire action for extensions
			do_action( 'postgrid_loaded', $this );
		} else {
			// Show admin notice for failed initialization
			add_action( 'admin_notices', array( $this->initializer, 'show_initialization_error' ) );
		}
	}
	
	/**
	 * Load plugin text domain
	 */
	private function load_textdomain() {
		load_plugin_textdomain( 
			'postgrid', 
			false, 
			dirname( plugin_basename( POSTGRID_PLUGIN_FILE ) ) . '/languages' 
		);
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Core hooks
		add_action( 'init', array( $this, 'on_init' ), 5 );
		add_action( 'rest_api_init', array( $this->get_service( 'api' ), 'register_routes' ) );
		
		// Asset hooks
		add_action( 'enqueue_block_editor_assets', array( $this->get_service( 'assets' ), 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this->get_service( 'assets' ), 'enqueue_frontend_assets' ) );
		
		// Allow other plugins to hook in
		do_action( 'postgrid_init', $this );
	}
	
	/**
	 * WordPress init hook callback
	 */
	public function on_init() {
		// Register blocks
		$blocks = $this->get_service( 'blocks' );
		if ( $blocks ) {
			$blocks->register();
		}
		
		// Register post type support
		$this->register_post_type_support();
		
		// Fire action for extensions
		do_action( 'postgrid_ready' );
	}
	
	/**
	 * Register post type support
	 */
	private function register_post_type_support() {
		$supported_types = get_option( 'postgrid_supported_post_types', array( 'post' ) );
		
		// Ensure we have an array
		if ( ! is_array( $supported_types ) ) {
			$supported_types = array( 'post' );
		}
		
		foreach ( $supported_types as $post_type ) {
			add_post_type_support( $post_type, 'postgrid' );
		}
	}
	
	/**
	 * Get service from container
	 *
	 * @param string $service_id Service identifier
	 * @return object|null Service instance or null if not found
	 */
	public function get_service( $service_id ) {
		if ( ! $this->container ) {
			return null;
		}
		
		try {
			return $this->container->get( $service_id );
		} catch ( \Exception $e ) {
			error_log( 'PostGrid: Failed to get service "' . $service_id . '": ' . $e->getMessage() );
			return null;
		}
	}
	
	/**
	 * Get service container
	 *
	 * @return ServiceContainer|null
	 */
	public function get_container() {
		return $this->container;
	}
	
	/**
	 * Get component initializer
	 *
	 * @return ComponentInitializer|null
	 */
	public function get_initializer() {
		return $this->initializer;
	}
	
	/**
	 * Get component initialization status
	 *
	 * @return array Component status array
	 */
	public function get_component_status() {
		if ( ! $this->container ) {
			return array();
		}
		
		return $this->container->get_status();
	}
	
	/**
	 * Check if plugin is initialized
	 *
	 * @return bool
	 */
	public function is_initialized() {
		return $this->initialized && $this->initializer && $this->initializer->all_components_loaded();
	}
	
	// Getter methods for accessing services
	
	/**
	 * Get cache manager instance
	 *
	 * @return \PostGrid\Core\CacheManager|null
	 */
	public function get_cache_manager() {
		return $this->get_service( 'cache' );
	}
	
	/**
	 * Get hooks manager
	 *
	 * @return \PostGrid\Core\HooksManager|null
	 */
	public function get_hooks_manager() {
		return $this->get_service( 'hooks' );
	}
	
	/**
	 * Get block registry
	 *
	 * @return \PostGrid\Blocks\BlockRegistry|null
	 */
	public function get_block_registry() {
		return $this->get_service( 'blocks' );
	}
	
	/**
	 * Get API controller
	 *
	 * @return \PostGrid\Api\RestController|null
	 */
	public function get_api_controller() {
		return $this->get_service( 'api' );
	}
	
	/**
	 * Get asset manager
	 *
	 * @return \PostGrid\Core\AssetManager|null
	 */
	public function get_asset_manager() {
		return $this->get_service( 'assets' );
	}
	

	/**
	 * Get settings service
	 *
	 * @return \PostGrid\Services\SettingsService|null
	 */
	public function get_settings_service() {
		return $this->get_service( 'settings' );
	}
	
	// Deprecated method aliases for backward compatibility
	
	/**
	 * Get component instance
	 * @deprecated Use get_service() instead
	 * @param string $component Component name
	 * @return object|null
	 */
	public function get_component( $component ) {
		return $this->get_service( $component );
	}
	
	/**
	 * Get hooks manager
	 * @deprecated Use get_hooks_manager() instead
	 * @return \PostGrid\Core\HooksManager|null
	 */
	public function hooks() {
		return $this->get_hooks_manager();
	}
	
	/**
	 * Get block registry
	 * @deprecated Use get_block_registry() instead
	 * @return \PostGrid\Blocks\BlockRegistry|null
	 */
	public function blocks() {
		return $this->get_block_registry();
	}
	
	/**
	 * Get API controller
	 * @deprecated Use get_api_controller() instead
	 * @return \PostGrid\Api\RestController|null
	 */
	public function api() {
		return $this->get_api_controller();
	}
	
	/**
	 * Get asset manager
	 * @deprecated Use get_asset_manager() instead
	 * @return \PostGrid\Core\AssetManager|null
	 */
	public function assets() {
		return $this->get_asset_manager();
	}

}
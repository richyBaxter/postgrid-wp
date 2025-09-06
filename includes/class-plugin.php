<?php
/**
 * Main Plugin Class
 *
 * @package PostGrid
 * @since 1.0.0
 */

namespace PostGrid;

use PostGrid\Core\AssetManager;
use PostGrid\Core\CacheManager;
use PostGrid\Core\HooksManager;
use PostGrid\Blocks\BlockRegistry;
use PostGrid\Api\RestController;
use PostGrid\Compatibility\LegacySupport;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class implementing proper singleton pattern
 */
class Plugin {
	
	/**
	 * Plugin instance
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;
	
	/**
	 * Asset manager instance
	 *
	 * @var AssetManager
	 */
	private $assets;
	
	/**
	 * Hooks manager instance
	 *
	 * @var HooksManager
	 */
	private $hooks;
	
	/**
	 * Block registry instance
	 *
	 * @var BlockRegistry
	 */
	private $blocks;
	
	/**
	 * REST API controller instance
	 *
	 * @var RestController
	 */
	private $api;
	
	/**
	 * Legacy support instance
	 *
	 * @var LegacySupport
	 */
	private $legacy;
	
	/**
	 * Cache manager instance
	 *
	 * @var CacheManager
	 */
	private $cache;
	
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
	 * Get cache manager instance
	 *
	 * @return CacheManager|null
	 */
	public function get_cache_manager() {
		return $this->cache;
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
		
		// Initialize components with error handling
		$this->init_components();
		
		// Only proceed if components initialized successfully
		if ( $this->components_loaded() ) {
			$this->init_hooks();
			
			// Fire action for extensions
			do_action( 'postgrid_loaded', $this );
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
	 * Initialize plugin components
	 */
	private function init_components() {
		$failed_components = array();
		
		try {
			// Initialize core components with individual error tracking
			$this->cache = new CacheManager();
			if ( ! $this->cache instanceof CacheManager ) {
				$failed_components[] = 'cache';
			}
			
			$this->assets = new AssetManager();
			if ( ! $this->assets instanceof AssetManager ) {
				$failed_components[] = 'assets';
			}
			
			$this->hooks = new HooksManager();
			if ( ! $this->hooks instanceof HooksManager ) {
				$failed_components[] = 'hooks';
			}
			
			$this->blocks = new BlockRegistry();
			if ( ! $this->blocks instanceof BlockRegistry ) {
				$failed_components[] = 'blocks';
			}
			
			$this->api = new RestController();
			if ( ! $this->api instanceof RestController ) {
				$failed_components[] = 'api';
			}
			
			$this->legacy = new LegacySupport();
			if ( ! $this->legacy instanceof LegacySupport ) {
				$failed_components[] = 'legacy';
			}
			
			// Initialize cache hooks if cache component loaded successfully
			if ( $this->cache instanceof CacheManager ) {
				$this->cache->init_hooks();
			}
			
			// Log any partial failures for debugging
			if ( ! empty( $failed_components ) ) {
				error_log( 'PostGrid: Failed to initialize components: ' . implode( ', ', $failed_components ) );
			}
			
		} catch ( \Exception $e ) {
			// Log the error with stack trace for better debugging
			error_log( 'PostGrid: Failed to initialize components - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			
			// Show admin notice
			add_action( 'admin_notices', array( $this, 'show_initialization_error' ), 10, 1 );
			
			// Only set failed components to null, preserve working ones
			foreach ( $failed_components as $component ) {
				$this->{$component} = null;
			}
		}
	}
	
	/**
	 * Check if all components loaded successfully
	 *
	 * @return bool
	 */
	private function components_loaded() {
		$required_components = array(
			'cache' => CacheManager::class,
			'assets' => AssetManager::class,
			'hooks' => HooksManager::class,
			'blocks' => BlockRegistry::class,
			'api' => RestController::class,
			'legacy' => LegacySupport::class
		);
		
		foreach ( $required_components as $property => $class_name ) {
			if ( ! isset( $this->{$property} ) || ! $this->{$property} instanceof $class_name ) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Get component initialization status
	 *
	 * @return array Component status array
	 */
	public function get_component_status() {
		return array(
			'cache' => $this->cache instanceof CacheManager,
			'assets' => $this->assets instanceof AssetManager,
			'hooks' => $this->hooks instanceof HooksManager,
			'blocks' => $this->blocks instanceof BlockRegistry,
			'api' => $this->api instanceof RestController,
			'legacy' => $this->legacy instanceof LegacySupport,
		);
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Core hooks
		add_action( 'init', array( $this, 'on_init' ), 5 );
		add_action( 'rest_api_init', array( $this->api, 'register_routes' ) );
		
		// Asset hooks
		add_action( 'enqueue_block_editor_assets', array( $this->assets, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this->assets, 'enqueue_frontend_assets' ) );
		
		// Admin hooks
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
		
		// Allow other plugins to hook in
		do_action( 'postgrid_init', $this );
	}
	
	/**
	 * WordPress init hook callback
	 */
	public function on_init() {
		// Register blocks
		if ( $this->blocks ) {
			$this->blocks->register();
		}
		
		// Initialize legacy support
		if ( $this->legacy ) {
			$this->legacy->init();
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
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'PostGrid Settings', 'postgrid' ),
			__( 'PostGrid', 'postgrid' ),
			'manage_options',
			'postgrid-settings',
			array( $this, 'render_settings_page' )
		);
	}
	
	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'postgrid_settings', 'postgrid_cache_expiration', array(
			'sanitize_callback' => 'absint'
		) );
		register_setting( 'postgrid_settings', 'postgrid_enable_rest_api', array(
			'sanitize_callback' => array( $this, 'sanitize_boolean' )
		) );
		register_setting( 'postgrid_settings', 'postgrid_supported_post_types', array(
			'sanitize_callback' => array( $this, 'sanitize_post_types' )
		) );
		register_setting( 'postgrid_settings', 'postgrid_rate_limit', array(
			'sanitize_callback' => array( $this, 'sanitize_rate_limit' )
		) );
	}
	
	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Get available post types
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$supported_post_types = get_option( 'postgrid_supported_post_types', array( 'post' ) );
		
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'postgrid_settings' );
				do_settings_sections( 'postgrid_settings' );
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="postgrid_cache_expiration">
								<?php esc_html_e( 'Cache Expiration', 'postgrid' ); ?>
							</label>
						</th>
						<td>
							<input type="number" 
								id="postgrid_cache_expiration" 
								name="postgrid_cache_expiration" 
								value="<?php echo esc_attr( get_option( 'postgrid_cache_expiration', 300 ) ); ?>" 
								min="0" 
								step="60" />
							<p class="description">
								<?php esc_html_e( 'Cache expiration time in seconds. Set to 0 to disable caching.', 'postgrid' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="postgrid_enable_rest_api">
								<?php esc_html_e( 'Enable REST API', 'postgrid' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" 
								id="postgrid_enable_rest_api" 
								name="postgrid_enable_rest_api" 
								value="1" 
								<?php checked( get_option( 'postgrid_enable_rest_api', true ) ); ?> />
							<label for="postgrid_enable_rest_api">
								<?php esc_html_e( 'Enable REST API endpoints for PostGrid', 'postgrid' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Required for the block editor to function properly.', 'postgrid' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Supported Post Types', 'postgrid' ); ?>
						</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text">
									<span><?php esc_html_e( 'Supported Post Types', 'postgrid' ); ?></span>
								</legend>
								<?php foreach ( $post_types as $post_type ) : ?>
									<?php if ( $post_type->name === 'attachment' ) continue; ?>
									<label>
										<input type="checkbox" 
											name="postgrid_supported_post_types[]" 
											value="<?php echo esc_attr( $post_type->name ); ?>"
											<?php checked( in_array( $post_type->name, $supported_post_types, true ) ); ?> />
										<?php echo esc_html( $post_type->labels->name ); ?>
									</label><br />
								<?php endforeach; ?>
							</fieldset>
							<p class="description">
								<?php esc_html_e( 'Select which post types can be displayed in PostGrid blocks.', 'postgrid' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="postgrid_rate_limit">
								<?php esc_html_e( 'API Rate Limit', 'postgrid' ); ?>
							</label>
						</th>
						<td>
							<input type="number" 
								id="postgrid_rate_limit" 
								name="postgrid_rate_limit" 
								value="<?php echo esc_attr( get_option( 'postgrid_rate_limit', 60 ) ); ?>" 
								min="1" 
								max="1000" />
							<p class="description">
								<?php esc_html_e( 'Maximum number of API requests per minute per IP address.', 'postgrid' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
	
	/**
	 * Show initialization error
	 */
	public function show_initialization_error() {
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php esc_html_e( 'PostGrid Error:', 'postgrid' ); ?></strong>
				<?php esc_html_e( 'Failed to initialize plugin components. Please check the error log for details.', 'postgrid' ); ?>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Get component instance
	 *
	 * @param string $component Component name.
	 * @return object|null
	 */
	public function get_component( $component ) {
		if ( property_exists( $this, $component ) && $this->$component !== null ) {
			return $this->$component;
		}
		
		return null;
	}
	
	/**
	 * Get hooks manager
	 *
	 * @return HooksManager|null
	 */
	public function get_hooks_manager() {
		return $this->hooks;
	}
	
	/**
	 * Get block registry
	 *
	 * @return BlockRegistry|null
	 */
	public function get_block_registry() {
		return $this->blocks;
	}
	
	/**
	 * Get API controller
	 *
	 * @return RestController|null
	 */
	public function get_api_controller() {
		return $this->api;
	}
	
	/**
	 * Get asset manager
	 *
	 * @return AssetManager|null
	 */
	public function get_asset_manager() {
		return $this->assets;
	}
	
	/**
	 * Get legacy support
	 *
	 * @return LegacySupport|null
	 */
	public function get_legacy_support() {
		return $this->legacy;
	}
	
	// Backward compatibility aliases (deprecated)
	
	/**
	 * Get hooks manager
	 * @deprecated Use get_hooks_manager() instead
	 * @return HooksManager|null
	 */
	public function hooks() {
		return $this->get_hooks_manager();
	}
	
	/**
	 * Get block registry
	 * @deprecated Use get_block_registry() instead
	 * @return BlockRegistry|null
	 */
	public function blocks() {
		return $this->get_block_registry();
	}
	
	/**
	 * Get API controller
	 * @deprecated Use get_api_controller() instead
	 * @return RestController|null
	 */
	public function api() {
		return $this->get_api_controller();
	}
	
	/**
	 * Get asset manager
	 * @deprecated Use get_asset_manager() instead
	 * @return AssetManager|null
	 */
	public function assets() {
		return $this->get_asset_manager();
	}
	
	/**
	 * Get legacy support
	 * @deprecated Use get_legacy_support() instead
	 * @return LegacySupport|null
	 */
	public function legacy() {
		return $this->get_legacy_support();
	}
	
	/**
	 * Check if plugin is initialized
	 *
	 * @return bool
	 */
	public function is_initialized() {
		return $this->initialized && $this->components_loaded();
	}
	
	/**
	 * Sanitize boolean setting
	 *
	 * @param mixed $value Input value to sanitize
	 * @return bool Sanitized boolean value
	 */
	public function sanitize_boolean( $value ) {
		// Handle various input types that should be considered "true"
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
		}
		
		// For arrays, objects, and other non-scalar types, return false
		if ( ! is_scalar( $value ) ) {
			return false;
		}
		
		return (bool) $value;
	}
	
	/**
	 * Sanitize post types setting
	 *
	 * @param mixed $value Input value to sanitize
	 * @return array Array of valid post type names
	 */
	public function sanitize_post_types( $value ) {
		// Ensure input is either scalar or array - reject other types
		if ( ! is_scalar( $value ) && ! is_array( $value ) ) {
			error_log( 'PostGrid: Invalid input type for sanitize_post_types: ' . gettype( $value ) );
			return array( 'post' );
		}
		
		// Convert scalar to array for consistent processing
		if ( is_scalar( $value ) ) {
			$value = array( $value );
		}
		
		// Ensure we have an array at this point
		if ( ! is_array( $value ) ) {
			return array( 'post' );
		}
		
		$valid_post_types = get_post_types( array( 'public' => true ) );
		$sanitized = array();
		
		foreach ( $value as $post_type ) {
			// Skip non-string values
			if ( ! is_string( $post_type ) && ! is_numeric( $post_type ) ) {
				continue;
			}
			
			$post_type = sanitize_key( (string) $post_type );
			
			// Only include valid, public post types (excluding attachments)
			if ( isset( $valid_post_types[ $post_type ] ) && $post_type !== 'attachment' ) {
				$sanitized[] = $post_type;
			}
		}
		
		// Always return at least 'post' as a fallback
		return empty( $sanitized ) ? array( 'post' ) : $sanitized;
	}
	
	/**
	 * Sanitize rate limit setting
	 *
	 * @param mixed $value Input value to sanitize
	 * @return int Valid rate limit between 1 and 1000
	 */
	public function sanitize_rate_limit( $value ) {
		// Ensure input is numeric or can be converted to numeric
		if ( ! is_numeric( $value ) ) {
			error_log( 'PostGrid: Invalid input type for rate limit: ' . gettype( $value ) );
			return 60; // Default rate limit
		}
		
		$value = absint( $value );
		
		// Ensure value is within acceptable range
		return max( 1, min( 1000, $value ) );
	}
}

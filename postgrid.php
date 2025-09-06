<?php
/**
 * Plugin Name: PostGrid
 * Plugin URI: https://github.com/richardbaxterseo/postgrid-wp
 * Description: A lightweight posts grid block for WordPress
 * Author: Richard Baxter
 * Version: 0.1.15
 * Author URI: https://richardbaxter.co/
 * Text Domain: postgrid
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access with proper security headers
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit( 'Direct access to this file is not allowed. Please access through WordPress.' );
}

// Define plugin constants
define( 'POSTGRID_VERSION', '0.1.15' );
define( 'POSTGRID_PLUGIN_FILE', __FILE__ );
define( 'POSTGRID_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'POSTGRID_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Check if Composer autoloader exists, fall back to custom autoloader
if ( file_exists( POSTGRID_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once POSTGRID_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	// Fallback autoloader for development or when Composer is not available
	spl_autoload_register( function ( $class ) {
		$prefix = 'PostGrid\\';
		$base_dir = POSTGRID_PLUGIN_DIR . 'includes/';
		
		// Does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Get the relative class name
		$relative_class = substr( $class, $len );
		
		// Handle nested namespaces properly
		$parts = explode( '\\', $relative_class );
		
		if ( count( $parts ) === 1 ) {
			// Top-level class (e.g., PostGrid\Plugin)
			$file = $base_dir . 'class-' . strtolower( str_replace( '_', '-', $parts[0] ) ) . '.php';
		} else {
			// Nested namespace (e.g., PostGrid\Core\AssetManager)
			$class_name = array_pop( $parts );
			$namespace_path = implode( '/', $parts );
			
			// Convert CamelCase to kebab-case (AssetManager -> asset-manager)
			$file_name = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name ) );
			
			$file = $base_dir . $namespace_path . '/class-' . $file_name . '.php';
		}
		
		// If the mapped file exists, require it
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

// Initialize the plugin with proper error handling
add_action( 'plugins_loaded', function() {
	try {
		// Check minimum requirements first
		if ( ! postgrid_check_requirements() ) {
			return;
		}
		
		// Check for required build files before initialization
		if ( ! postgrid_check_build_files() ) {
			return;
		}
		
		// Initialize main plugin instance
		PostGrid\Plugin::get_instance();
		
	} catch ( Exception $e ) {
		// Log the error
		error_log( 'PostGrid: Failed to initialize plugin - ' . $e->getMessage() );
		
		// Show admin notice
		add_action( 'admin_notices', function() use ( $e ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong><?php esc_html_e( 'PostGrid Error:', 'postgrid' ); ?></strong>
					<?php echo esc_html( $e->getMessage() ); ?>
				</p>
			</div>
			<?php
		} );
		
		// Prevent plugin execution
		return;
	}
}, 5 );

// Register activation hook
register_activation_hook( __FILE__, 'postgrid_activation' );

// Register deactivation hook
register_deactivation_hook( __FILE__, 'postgrid_deactivation' );

/**
 * Check plugin requirements
 *
 * @return bool
 */
function postgrid_check_requirements() {
	$errors = array();
	
	// Check WordPress version
	if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
		$errors[] = __( 'PostGrid requires WordPress 6.0 or higher.', 'postgrid' );
	}
	
	// Check PHP version
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		$errors[] = __( 'PostGrid requires PHP 7.4 or higher.', 'postgrid' );
	}
	
	// Check if block editor is available
	if ( ! function_exists( 'register_block_type' ) ) {
		$errors[] = __( 'PostGrid requires the WordPress block editor.', 'postgrid' );
	}
	
	// If there are errors, display them
	if ( ! empty( $errors ) ) {
		add_action( 'admin_notices', function() use ( $errors ) {
			?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'PostGrid cannot be activated:', 'postgrid' ); ?></strong></p>
				<ul>
					<?php foreach ( $errors as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		} );
		
		return false;
	}
	
	return true;
}

/**
 * Check if required build files exist
 *
 * @return bool
 */
function postgrid_check_build_files() {
	$required_files = array(
		'build/index.js',
		'build/index.asset.php',
	);
	
	$missing_files = array();
	
	foreach ( $required_files as $file ) {
		if ( ! file_exists( POSTGRID_PLUGIN_DIR . $file ) ) {
			$missing_files[] = $file;
		}
	}
	
	if ( ! empty( $missing_files ) ) {
		add_action( 'admin_notices', function() use ( $missing_files ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong><?php esc_html_e( 'PostGrid Build Error:', 'postgrid' ); ?></strong></p>
				<p><?php esc_html_e( 'The following required files are missing:', 'postgrid' ); ?></p>
				<ul>
					<?php foreach ( $missing_files as $file ) : ?>
						<li><code><?php echo esc_html( $file ); ?></code></li>
					<?php endforeach; ?>
				</ul>
				<p><?php esc_html_e( 'Please run: npm install && npm run build', 'postgrid' ); ?></p>
			</div>
			<?php
		} );
		
		// Deactivate the plugin to prevent errors
		deactivate_plugins( plugin_basename( __FILE__ ) );
		
		return false;
	}
	
	return true;
}

/**
 * Plugin activation
 */
function postgrid_activation() {
	// Check requirements before activation
	if ( ! postgrid_check_requirements() ) {
		// Deactivate immediately
		deactivate_plugins( plugin_basename( __FILE__ ) );
		
		// Redirect to plugins page with error
		wp_die(
			esc_html__( 'PostGrid cannot be activated. Please check the requirements.', 'postgrid' ),
			esc_html__( 'Plugin Activation Error', 'postgrid' ),
			array( 'back_link' => true )
		);
	}
	
	// Check build files
	if ( ! postgrid_check_build_files() ) {
		// Log warning but allow activation for development
		error_log( 'PostGrid: Build directory not found. Please run npm install && npm run build' );
	}
	
	// Create database tables if needed (for future use)
	postgrid_create_tables();
	
	// Set default options
	postgrid_set_default_options();
	
	// Flush rewrite rules
	flush_rewrite_rules();
	
	// Schedule cron events
	if ( ! wp_next_scheduled( 'postgrid_daily_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'postgrid_daily_cleanup' );
	}
	
	// Set activation flag
	set_transient( 'postgrid_activated', true, 5 );
}

/**
 * Plugin deactivation
 */
function postgrid_deactivation() {
	// Clear scheduled events
	wp_clear_scheduled_hook( 'postgrid_daily_cleanup' );
	
	// Flush cache if cache manager is available
	if ( class_exists( 'PostGrid\\Core\\CacheManager' ) ) {
		try {
			$cache = new PostGrid\Core\CacheManager();
			$cache->flush();
		} catch ( Exception $e ) {
			error_log( 'PostGrid: Failed to flush cache on deactivation - ' . $e->getMessage() );
		}
	}
	
	// Flush rewrite rules
	flush_rewrite_rules();
}

/**
 * Create database tables
 */
function postgrid_create_tables() {
	// Reserved for future use
	// Example implementation:
	/*
	global $wpdb;
	
	$charset_collate = $wpdb->get_charset_collate();
	
	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}postgrid_cache (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		cache_key varchar(255) NOT NULL,
		cache_value longtext NOT NULL,
		expiration datetime NOT NULL,
		PRIMARY KEY (id),
		KEY cache_key (cache_key),
		KEY expiration (expiration)
	) $charset_collate;";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	*/
}

/**
 * Set default options
 */
function postgrid_set_default_options() {
	$defaults = array(
		'cache_expiration' => 300,
		'enable_rest_api' => true,
		'supported_post_types' => array( 'post' ),
		'rate_limit' => 60,
	);
	
	foreach ( $defaults as $option => $value ) {
		if ( false === get_option( 'postgrid_' . $option ) ) {
			add_option( 'postgrid_' . $option, $value );
		}
	}
}

/**
 * Get plugin instance
 *
 * @deprecated Use PostGrid\Plugin::get_instance() directly
 * @return PostGrid\Plugin|null
 */
function postgrid() {
	_deprecated_function( __FUNCTION__, '0.2.0', 'PostGrid\\Plugin::get_instance()' );
	return PostGrid\Plugin::get_instance();
}

/**
 * Daily cleanup cron job
 */
add_action( 'postgrid_daily_cleanup', function() {
	// Clean up old transients
	global $wpdb;
	
	$expired = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} 
			WHERE option_name LIKE %s 
			AND option_value < %d",
			$wpdb->esc_like( '_transient_timeout_postgrid_' ) . '%',
			time()
		)
	);
	
	foreach ( $expired as $transient ) {
		$key = str_replace( '_transient_timeout_', '', $transient );
		delete_transient( $key );
	}
	
	// Allow other cleanup tasks
	do_action( 'postgrid_daily_cleanup' );
} );

/**
 * Handle plugin activation redirect
 */
add_action( 'admin_init', function() {
	if ( get_transient( 'postgrid_activated' ) ) {
		delete_transient( 'postgrid_activated' );
		
		// Only redirect if not bulk activation and settings page exists
		if ( ! isset( $_GET['activate-multi'] ) && menu_page_url( 'postgrid-settings', false ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=postgrid-settings' ) );
			exit;
		}
	}
} );

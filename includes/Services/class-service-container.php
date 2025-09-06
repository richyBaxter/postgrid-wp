<?php
/**
 * Service Container for Dependency Injection
 *
 * @package PostGrid
 * @since 0.1.14
 */

namespace PostGrid\Services;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple service container for managing plugin dependencies
 */
class ServiceContainer {
	
	/**
	 * Registered services
	 *
	 * @var array
	 */
	private $services = array();
	
	/**
	 * Service instances
	 *
	 * @var array
	 */
	private $instances = array();
	
	/**
	 * Service definitions
	 *
	 * @var array
	 */
	private $definitions = array();
	
	/**
	 * Container instance
	 *
	 * @var ServiceContainer|null
	 */
	private static $instance = null;
	
	/**
	 * Get container instance
	 *
	 * @return ServiceContainer
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Private constructor
	 */
	private function __construct() {
		// Register default services
		$this->register_default_services();
	}
	
	/**
	 * Register a service
	 *
	 * @param string $id Service identifier
	 * @param callable $factory Factory function
	 * @param bool $shared Whether service should be shared (singleton)
	 * @return void
	 */
	public function register( $id, $factory, $shared = true ) {
		$this->services[ $id ] = array(
			'factory' => $factory,
			'shared' => $shared
		);
	}
	
	/**
	 * Get a service
	 *
	 * @param string $id Service identifier
	 * @return mixed
	 * @throws \Exception If service not found
	 */
	public function get( $id ) {
		if ( ! $this->has( $id ) ) {
			throw new \Exception( 'Service "' . $id . '" not found in container.' );
		}
		
		$service = $this->services[ $id ];
		
		// Return existing instance if shared
		if ( $service['shared'] && isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}
		
		// Create new instance
		$instance = call_user_func( $service['factory'] );
		
		// Store instance if shared
		if ( $service['shared'] ) {
			$this->instances[ $id ] = $instance;
		}
		
		return $instance;
	}
	
	/**
	 * Check if service exists
	 *
	 * @param string $id Service identifier
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->services[ $id ] );
	}
	
	/**
	 * Register default PostGrid services
	 *
	 * @return void
	 */
	private function register_default_services() {
		// Cache Manager
		$this->register( 'cache', function() {
			return new \PostGrid\Core\CacheManager();
		});
		
		// Asset Manager  
		$this->register( 'assets', function() {
			return new \PostGrid\Core\AssetManager();
		});
		
		// Hooks Manager
		$this->register( 'hooks', function() {
			return new \PostGrid\Core\HooksManager();
		});
		
		// Block Registry
		$this->register( 'blocks', function() {
			return new \PostGrid\Blocks\BlockRegistry();
		});
		
		// REST API Controller
		$this->register( 'api', function() {
			return new \PostGrid\Api\RestController();
		});
		

		
		// Plugin Settings Service
		$this->register( 'settings', function() {
			return new \PostGrid\Services\SettingsService();
		});
		
		// Component Initializer Service
		$this->register( 'initializer', function() {
			return new \PostGrid\Services\ComponentInitializer( self::get_instance() );
		});
	}
	
	/**
	 * Get all registered service IDs
	 *
	 * @return array
	 */
	public function get_service_ids() {
		return array_keys( $this->services );
	}
	
	/**
	 * Get component status for all services
	 *
	 * @return array
	 */
	public function get_status() {
		$status = array();
		
		foreach ( $this->get_service_ids() as $service_id ) {
			try {
				$service = $this->get( $service_id );
				$status[ $service_id ] = array(
					'loaded' => true,
					'class' => get_class( $service ),
					'initialized' => method_exists( $service, 'is_initialized' ) ? $service->is_initialized() : 'unknown'
				);
			} catch ( \Exception $e ) {
				$status[ $service_id ] = array(
					'loaded' => false,
					'error' => $e->getMessage(),
					'initialized' => false
				);
			}
		}
		
		return $status;
	}
	
	/**
	 * Initialize all services
	 *
	 * @return array Results of initialization
	 */
	public function initialize_all() {
		$results = array();
		
		foreach ( $this->get_service_ids() as $service_id ) {
			try {
				$service = $this->get( $service_id );
				
				// Initialize if method exists
				if ( method_exists( $service, 'initialize' ) ) {
					$service->initialize();
				}
				
				$results[ $service_id ] = array(
					'success' => true,
					'class' => get_class( $service )
				);
			} catch ( \Exception $e ) {
				$results[ $service_id ] = array(
					'success' => false,
					'error' => $e->getMessage()
				);
				
				error_log( 'PostGrid: Failed to initialize service "' . $service_id . '": ' . $e->getMessage() );
			}
		}
		
		return $results;
	}
}

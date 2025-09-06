<?php
/**
 * Component Initializer Service
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
 * Handles component initialization and dependency management
 */
class ComponentInitializer {
	
	/**
	 * Service container reference
	 *
	 * @var ServiceContainer
	 */
	private $container;
	
	/**
	 * Initialization results
	 *
	 * @var array
	 */
	private $initialization_results = array();
	
	/**
	 * Constructor
	 *
	 * @param ServiceContainer $container Service container instance
	 */
	public function __construct( ServiceContainer $container ) {
		$this->container = $container;
	}
	
	/**
	 * Initialize all core components
	 *
	 * @return array Initialization results
	 */
	public function initialize_all() {
		$this->initialization_results = array();
		
		// Define initialization order for proper dependency management
		$initialization_order = array(
			'cache',    // Initialize cache first (no dependencies)
			'hooks',    // Initialize hooks manager
			'assets',   // Initialize asset manager
			'blocks',   // Initialize block registry
			'api',      // Initialize REST API
			'settings'  // Initialize settings last
		);
		
		foreach ( $initialization_order as $service_id ) {
			$this->initialize_component( $service_id );
		}
		
		// Log overall results
		$this->log_initialization_results();
		
		return $this->initialization_results;
	}
	
	/**
	 * Initialize a specific component
	 *
	 * @param string $service_id Service identifier
	 * @return bool Success status
	 */
	private function initialize_component( $service_id ) {
		try {
			// Get the service from container
			$service = $this->container->get( $service_id );
			
			// Initialize if method exists
			if ( method_exists( $service, 'initialize' ) ) {
				$service->initialize();
			}
			
			// Verify initialization if method exists
			$is_initialized = method_exists( $service, 'is_initialized' ) 
				? $service->is_initialized() 
				: true; // Assume success if no verification method
			
			$this->initialization_results[ $service_id ] = array(
				'success' => true,
				'class' => get_class( $service ),
				'initialized' => $is_initialized,
				'message' => 'Component initialized successfully'
			);
			
			// Special handling for cache component
			if ( $service_id === 'cache' && method_exists( $service, 'init_hooks' ) ) {
				$service->init_hooks();
			}
			
			return true;
			
		} catch ( \Exception $e ) {
			$this->initialization_results[ $service_id ] = array(
				'success' => false,
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'initialized' => false,
				'message' => 'Component initialization failed'
			);
			
			error_log( 'PostGrid ComponentInitializer: Failed to initialize "' . $service_id . '": ' . $e->getMessage() );
			
			return false;
		}
	}
	
	/**
	 * Check if all components initialized successfully
	 *
	 * @return bool
	 */
	public function all_components_loaded() {
		if ( empty( $this->initialization_results ) ) {
			return false;
		}
		
		foreach ( $this->initialization_results as $result ) {
			if ( ! $result['success'] ) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Get initialization results
	 *
	 * @return array
	 */
	public function get_results() {
		return $this->initialization_results;
	}
	
	/**
	 * Get failed components
	 *
	 * @return array
	 */
	public function get_failed_components() {
		$failed = array();
		
		foreach ( $this->initialization_results as $service_id => $result ) {
			if ( ! $result['success'] ) {
				$failed[ $service_id ] = $result;
			}
		}
		
		return $failed;
	}
	
	/**
	 * Get successful components
	 *
	 * @return array
	 */
	public function get_successful_components() {
		$successful = array();
		
		foreach ( $this->initialization_results as $service_id => $result ) {
			if ( $result['success'] ) {
				$successful[ $service_id ] = $result;
			}
		}
		
		return $successful;
	}
	
	/**
	 * Log initialization results
	 *
	 * @return void
	 */
	private function log_initialization_results() {
		$successful = count( $this->get_successful_components() );
		$failed = count( $this->get_failed_components() );
		$total = count( $this->initialization_results );
		
		if ( $failed === 0 ) {
			error_log( 'PostGrid: All ' . $total . ' components initialized successfully' );
		} else {
			error_log( 'PostGrid: ' . $successful . '/' . $total . ' components initialized (' . $failed . ' failed)' );
			
			// Log specific failures
			foreach ( $this->get_failed_components() as $service_id => $result ) {
				error_log( 'PostGrid: Component "' . $service_id . '" failed: ' . $result['error'] );
			}
		}
	}
	
	/**
	 * Show admin notice for initialization errors
	 *
	 * @return void
	 */
	public function show_initialization_error() {
		$failed_components = $this->get_failed_components();
		
		if ( empty( $failed_components ) ) {
			return;
		}
		
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'PostGrid Error:', 'postgrid' ); ?></strong>
				<?php esc_html_e( 'Some plugin components failed to initialize.', 'postgrid' ); ?>
			</p>
			<ul>
				<?php foreach ( $failed_components as $service_id => $result ) : ?>
					<li>
						<strong><?php echo esc_html( $service_id ); ?>:</strong>
						<?php echo esc_html( $result['error'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<?php esc_html_e( 'Please check the error logs for more details or contact support.', 'postgrid' ); ?>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Initialize hooks for this service
	 *
	 * @return void
	 */
	public function initialize() {
		// This service doesn't need specific initialization
		// It's used by the main plugin class during startup
	}
	
	/**
	 * Check if service is initialized
	 *
	 * @return bool
	 */
	public function is_initialized() {
		return ! empty( $this->initialization_results );
	}
}
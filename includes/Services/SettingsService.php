<?php
/**
 * Settings Service
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
 * Handles all plugin settings functionality
 */
class SettingsService {
	
	/**
	 * Settings page slug
	 *
	 * @var string
	 */
	private $page_slug = 'postgrid-settings';
	
	/**
	 * Settings group name
	 *
	 * @var string
	 */
	private $settings_group = 'postgrid_settings';
	
	/**
	 * Initialize the service
	 *
	 * @return void
	 */
	public function initialize() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}
	
	/**
	 * Check if service is initialized
	 *
	 * @return bool
	 */
	public function is_initialized() {
		return has_action( 'admin_menu', array( $this, 'add_settings_page' ) ) !== false;
	}
	
	/**
	 * Add settings page to admin menu
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			esc_html__( 'PostGrid Settings', 'postgrid' ),
			esc_html__( 'PostGrid', 'postgrid' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_settings_page' )
		);
	}
	
	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( $this->settings_group, 'postgrid_cache_expiration', array(
			'sanitize_callback' => array( $this, 'sanitize_cache_expiration' ),
			'default' => 300
		) );
		
		register_setting( $this->settings_group, 'postgrid_enable_rest_api', array(
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
			'default' => true
		) );
		
		register_setting( $this->settings_group, 'postgrid_supported_post_types', array(
			'sanitize_callback' => array( $this, 'sanitize_post_types' ),
			'default' => array( 'post' )
		) );
		
		register_setting( $this->settings_group, 'postgrid_rate_limit', array(
			'sanitize_callback' => array( $this, 'sanitize_rate_limit' ),
			'default' => 60
		) );
	}
	
	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page() {
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'postgrid' ) );
		}
		
		// Get available post types
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$supported_post_types = get_option( 'postgrid_supported_post_types', array( 'post' ) );
		
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php settings_errors(); ?>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( $this->settings_group );
				do_settings_sections( $this->settings_group );
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
								step="60" 
								class="regular-text" />
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
							<fieldset>
								<label for="postgrid_enable_rest_api">
									<input type="checkbox" 
										id="postgrid_enable_rest_api" 
										name="postgrid_enable_rest_api" 
										value="1" 
										<?php checked( get_option( 'postgrid_enable_rest_api', true ) ); ?> />
									<?php esc_html_e( 'Enable REST API endpoints for PostGrid', 'postgrid' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Required for the block editor to function properly.', 'postgrid' ); ?>
								</p>
							</fieldset>
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
								<p class="description">
									<?php esc_html_e( 'Select which post types can be displayed in PostGrid blocks.', 'postgrid' ); ?>
								</p>
							</fieldset>
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
								max="1000" 
								class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Number of requests per minute allowed for REST API calls.', 'postgrid' ); ?>
							</p>
						</td>
					</tr>
				</table>
				
				<?php submit_button(); ?>
			</form>
			
			<hr>
			
			<h2><?php esc_html_e( 'Component Status', 'postgrid' ); ?></h2>
			<?php $this->render_component_status(); ?>
		</div>
		<?php
	}
	
	/**
	 * Render component status section
	 *
	 * @return void
	 */
	private function render_component_status() {
		$container = ServiceContainer::get_instance();
		$status = $container->get_status();
		
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Component', 'postgrid' ); ?></th>
					<th><?php esc_html_e( 'Status', 'postgrid' ); ?></th>
					<th><?php esc_html_e( 'Class', 'postgrid' ); ?></th>
					<th><?php esc_html_e( 'Initialized', 'postgrid' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $status as $component => $info ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $component ); ?></strong></td>
						<td>
							<?php if ( $info['loaded'] ) : ?>
								<span style="color: green;">✓ <?php esc_html_e( 'Loaded', 'postgrid' ); ?></span>
							<?php else : ?>
								<span style="color: red;">✗ <?php esc_html_e( 'Failed', 'postgrid' ); ?></span>
								<?php if ( isset( $info['error'] ) ) : ?>
									<br><small><?php echo esc_html( $info['error'] ); ?></small>
								<?php endif; ?>
							<?php endif; ?>
						</td>
						<td>
							<?php echo isset( $info['class'] ) ? esc_html( $info['class'] ) : '—'; ?>
						</td>
						<td>
							<?php 
							if ( isset( $info['initialized'] ) ) {
								if ( $info['initialized'] === true ) {
									echo '<span style="color: green;">✓ ' . esc_html__( 'Yes', 'postgrid' ) . '</span>';
								} elseif ( $info['initialized'] === false ) {
									echo '<span style="color: orange;">~ ' . esc_html__( 'No', 'postgrid' ) . '</span>';
								} else {
									echo esc_html( $info['initialized'] );
								}
							} else {
								echo '—';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
	
	/**
	 * Sanitize cache expiration setting
	 *
	 * @param mixed $value Input value to sanitize
	 * @return int Valid cache expiration in seconds
	 */
	public function sanitize_cache_expiration( $value ) {
		if ( ! is_numeric( $value ) ) {
			add_settings_error( 'postgrid_cache_expiration', 'invalid_cache_expiration', 
				esc_html__( 'Cache expiration must be a number.', 'postgrid' ) );
			return 300; // Default value
		}
		
		$value = absint( $value );
		
		// Allow 0 (disabled) or minimum 60 seconds
		if ( $value > 0 && $value < 60 ) {
			$value = 60;
			add_settings_error( 'postgrid_cache_expiration', 'minimum_cache_expiration', 
				esc_html__( 'Minimum cache expiration is 60 seconds.', 'postgrid' ), 'updated' );
		}
		
		return $value;
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
			add_settings_error( 'postgrid_supported_post_types', 'invalid_post_types', 
				esc_html__( 'Invalid post types data format.', 'postgrid' ) );
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
		if ( empty( $sanitized ) ) {
			add_settings_error( 'postgrid_supported_post_types', 'no_valid_post_types', 
				esc_html__( 'No valid post types selected. Using default "post" type.', 'postgrid' ), 'updated' );
			return array( 'post' );
		}
		
		return $sanitized;
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
			add_settings_error( 'postgrid_rate_limit', 'invalid_rate_limit', 
				esc_html__( 'Rate limit must be a number.', 'postgrid' ) );
			return 60; // Default rate limit
		}
		
		$value = absint( $value );
		
		// Ensure value is within acceptable range
		if ( $value < 1 ) {
			$value = 1;
			add_settings_error( 'postgrid_rate_limit', 'minimum_rate_limit', 
				esc_html__( 'Minimum rate limit is 1 request per minute.', 'postgrid' ), 'updated' );
		} elseif ( $value > 1000 ) {
			$value = 1000;
			add_settings_error( 'postgrid_rate_limit', 'maximum_rate_limit', 
				esc_html__( 'Maximum rate limit is 1000 requests per minute.', 'postgrid' ), 'updated' );
		}
		
		return $value;
	}
}

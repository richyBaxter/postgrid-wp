<?php
namespace PostGrid;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy PostGrid class - wrapper for backward compatibility
 * 
 * @deprecated Use PostGrid\Plugin instead
 */
class PostGrid {
	
	/**
	 * Initialize the plugin
	 */
	public function init() {
		// Get the main plugin instance
		$plugin = Plugin::get_instance();
		
		// The plugin is already initialized through the main file
		// This method is kept for backward compatibility
	}
	
	/**
	 * Render block - wrapper method
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$plugin = Plugin::get_instance();
		$renderer = $plugin->blocks()->get_renderer();
		return $renderer->render( $attributes );
	}

}

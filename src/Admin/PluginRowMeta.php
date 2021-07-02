<?php
/**
 * Class PluginRowMeta.
 *
 * @package AmpProject\AmpWP
 */

namespace AmpProject\AmpWP\Admin;

use AmpProject\AmpWP\Infrastructure\Delayed;
use AmpProject\AmpWP\Infrastructure\Registerable;
use AmpProject\AmpWP\Infrastructure\Service;

/**
 * Updates the plugin row meta for the plugin.
 *
 * @since 2.1
 * @internal
 */
final class PluginRowMeta implements Delayed, Service, Registerable {

	/**
	 * Get the action to use for registering the service.
	 *
	 * @return string Registration action to use.
	 */
	public static function get_registration_action() {
		return 'admin_init';
	}

	/**
	 * Runs on instantiation.
	 */
	public function register() {
		add_filter( 'plugin_row_meta', [ $this, 'get_plugin_row_meta' ], 10, 2 );
	}

	/**
	 * Updates the plugin row meta with link to review plugin.
	 *
	 * @param string[] $meta        An array of the plugin's metadata, including the version, author, author URI,
	 *                              and plugin URI.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 * @return string[] Plugin row meta.
	 */
	public function get_plugin_row_meta( $meta, $plugin_file ) {
		if ( plugin_basename( AMP__FILE__ ) !== $plugin_file ) {
			return $meta;
		}

		$additional_meta = [
			'<a href="https://wordpress.org/support/plugin/amp/reviews/#new-post" target="_blank" rel="noreferrer noopener">' . esc_html__( 'Leave review', 'amp' ) . '</a>',
		];

		return array_merge( $meta, $additional_meta );
	}
}

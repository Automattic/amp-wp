<?php
/**
 * Interface Registerable.
 *
 * @package AmpProject\AmpWP
 */

namespace AmpProject\AmpWP\Embed;

/**
 * Indicates embed handlers that hook into WordPress to perform any actions necessary for the handler to output an
 * AMP-compatible embed.
 */
interface Registerable {

	/**
	 * Register the embed.
	 *
	 * @return void
	 */
	public function register_embed();

	/**
	 * Unregister the embed.
	 *
	 * @return void
	 */
	public function unregister_embed();
}

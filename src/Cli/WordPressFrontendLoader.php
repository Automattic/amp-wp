<?php
/**
 * Class WordPressFrontendLoader.
 *
 * Run a full WordPress frontend request.
 *
 * @package AmpProject\AmpWP
 */

namespace AmpProject\AmpWP\Cli;

use WP_CLI;

/**
 * Run a full WordPress frontend request.
 *
 * @package AmpProject\AmpWP\Cli
 */
final class WordPressFrontendLoader {

	/**
	 * Runs through the entirety of the WP bootstrap process.
	 *
	 * @return void
	 */
	public function run() {
		// Bail early if WordPress already ran once.
		if ( function_exists( 'add_filter' ) ) {
			return;
		}

		WP_CLI::get_runner()->load_wordpress();

		// Set up 'main_query' main WordPress query.
		wp();

		// Enable theme support.
		define( 'WP_USE_THEMES', true );

		// Template is normally loaded in global scope, so we need to replicate.
		foreach ( $GLOBALS as $key => $value ) {
			global ${$key}; // phpcs:ignore PHPCompatibility.Variables.ForbiddenGlobalVariableVariable.NonBareVariableFound
		}

		// Load the theme template.
		ob_start();
		require_once ABSPATH . WPINC . '/template-loader.php';
		ob_get_clean();
	}
}

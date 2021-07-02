<?php
/**
 * Service class for support.
 *
 * @package AmpProject\AmpWP
 */

namespace AmpProject\AmpWP\Support;

use AmpProject\AmpWP\Infrastructure\Service;
use AmpProject\AmpWP\Infrastructure\CliCommand;
use AmpProject\AmpWP\Validation\ScannableURLProvider;
use AmpProject\AmpWP\Validation\URLScanningContext;

/**
 * Class SupportService
 * Service class for support.
 */
class SupportService implements Service, CliCommand {

	/**
	 * Get the name under which to register the CLI command.
	 *
	 * @return string The name under which to register the CLI command.
	 */
	public static function get_command_name() {

		return 'amp';
	}

	/**
	 * To send support data.
	 *
	 * @param array $args Support data argument.
	 *
	 * @return array|\WP_Error Response from insight server.
	 */
	public function send_data( $args = [] ) {

		$support = new SupportData( $args );

		return $support->send_data();
	}

	/**
	 * To get support data.
	 *
	 * @param array $args Support data argument.
	 *
	 * @return array Support data.
	 */
	public function get_data( $args = [] ) {

		$support = new SupportData( $args );

		return $support->get_data();
	}

	/**
	 * Sends support data to endpoint.
	 *
	 * ## OPTIONS
	 *
	 * [--is-synthetic]
	 * : Whether or not it is synthetic data.
	 * ---
	 * default: false
	 * options:
	 *   - true
	 *   - false
	 *
	 * [--print]
	 * : To print support data.
	 * ---
	 * default: json-pretty
	 * options:
	 *   - json
	 *   - json-pretty
	 *
	 * [--endpoint=<string>]
	 * : Support endpoint. Where support data will send.
	 *
	 * [--urls=<urls>]
	 * : List of URL for which support data need to send. Use comma separator for multiple URLs.
	 *
	 * [--post_ids=<post_ids>]
	 * : List of Post for which support data need to send. Use comma separator for multiple post ids.
	 *
	 * [--term_ids=<term_ids>]
	 * : List of term for which support data need to send. Use comma separator for multiple term ids.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amp send-diagnostic
	 *
	 * @subcommand send-diagnostic
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function cli_command( /** @noinspection PhpUnusedParameterInspection */ $args, $assoc_args ) {

		$is_print     = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'print', false ), FILTER_SANITIZE_STRING );
		$is_synthetic = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'is-synthetic', false ), FILTER_SANITIZE_STRING );
		$endpoint     = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'endpoint', '' ), FILTER_SANITIZE_STRING );
		$endpoint     = untrailingslashit( $endpoint );

		$urls     = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'urls', false ), FILTER_SANITIZE_STRING );
		$post_ids = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'post_ids', false ), FILTER_SANITIZE_STRING );
		$term_ids = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'term_ids', false ), FILTER_SANITIZE_STRING );

		$args = [
			'urls'         => ( ! empty( $urls ) ) ? explode( ',', $urls ) : [],
			'post_ids'     => ( ! empty( $post_ids ) ) ? explode( ',', $post_ids ) : [],
			'term_ids'     => ( ! empty( $term_ids ) ) ? explode( ',', $term_ids ) : [],
			'endpoint'     => $endpoint,
			'is_synthetic' => $is_synthetic,
		];

		$support = new SupportData( $args );
		$data    = $support->get_data();

		if ( $is_print ) {

			// Print the data.
			$print = strtolower( trim( $is_print ) );
			if ( 'json' === $print ) {
				echo wp_json_encode( $data ) . PHP_EOL;
			} else {
				echo wp_json_encode( $data, JSON_PRETTY_PRINT ) . PHP_EOL;
			}
		} else {

			$response = $support->send_data();

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				\WP_CLI::warning( "Something went wrong: $error_message" );
			} else {
				\WP_CLI::success( $response );
			}
		}

		/**
		 * Summary of data.
		 */
		$url_error_relationship = [];

		foreach ( $data['urls'] as $url ) {
			foreach ( $url['errors'] as $error ) {
				foreach ( $error['sources'] as $source ) {
					$url_error_relationship[] = $url['url'] . '-' . $error['error_slug'] . '-' . $source;
				}
			}
		}

		$plugin_count = count( $data['plugins'] );

		if ( $is_synthetic ) {
			$plugin_count_text = ( $plugin_count - 3 ) . " - Excluding common plugins of synthetic sites. ( $plugin_count - 3 )";
		} else {
			$plugin_count_text = $plugin_count;
		}

		$summary = [
			'Site URL'               => SupportData::get_home_url(),
			'Plugin count'           => $plugin_count_text,
			'Themes'                 => count( $data['themes'] ),
			'Errors'                 => count( array_values( $data['errors'] ) ),
			'Error Sources'          => count( array_values( $data['error_sources'] ) ),
			'Validated URL'          => count( array_values( $data['urls'] ) ),
			'URL Error Relationship' => count( array_values( $url_error_relationship ) ),
		];

		if ( $is_synthetic ) {
			$summary['Synthetic Data'] = 'Yes';
		}

		\WP_CLI::log( sprintf( PHP_EOL . "%'=100s", '' ) );
		\WP_CLI::log( 'Summary of AMP data' );
		\WP_CLI::log( sprintf( "%'=100s", '' ) );
		foreach ( $summary as $key => $value ) {
			\WP_CLI::log( sprintf( '%-25s : %s', $key, $value ) );
		}
		\WP_CLI::log( sprintf( "%'=100s" . PHP_EOL, '' ) );
	}
}

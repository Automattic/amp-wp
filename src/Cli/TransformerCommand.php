<?php
/**
 * Class TransformerCommand.
 *
 * Commands that deal with the transformers registered with the AMP optimizer.
 *
 * @package AmpProject\AmpWP
 */

namespace AmpProject\AmpWP\Cli;

use AmpProject\AmpWP\Infrastructure\CliCommand;
use AmpProject\AmpWP\Infrastructure\Service;
use AmpProject\Optimizer\Configuration;
use AmpProject\Optimizer\Exception\UnknownConfigurationClass;
use WP_CLI;

/**
 * Commands that deal with the transformers registered with the AMP optimizer. (EXPERIMENTAL)
 *
 * Note: The Optimizer CLI commands are to be considered experimental, as
 * the output they produce is currently not guaranteed to be consistent
 * with the corresponding output from the web server code path.
 *
 * @since 2.1.0
 * @internal
 */
final class TransformerCommand implements Service, CliCommand {

	/**
	 * @var Configuration
	 */
	private $configuration;

	/**
	 * @var WordPressFrontendLoader
	 */
	private $frontend_loader;

	/**
	 * Get the name under which to register the CLI command.
	 *
	 * @return string The name under which to register the CLI command.
	 */
	public static function get_command_name() {
		return 'amp optimizer transformer';
	}

	/**
	 * TransformerCommand constructor.
	 *
	 * @param Configuration           $configuration   Configuration object instance to use.
	 * @param WordPressFrontendLoader $frontend_loader Frontend loader object instance to use.
	 */
	public function __construct( Configuration $configuration, WordPressFrontendLoader $frontend_loader ) {
		$this->configuration   = $configuration;
		$this->frontend_loader = $frontend_loader;
	}

	/**
	 * List the transformers registered with the AMP Optimizer. (EXPERIMENTAL)
	 *
	 * Note: The Optimizer CLI commands are to be considered experimental, as
	 * the output they produce is currently not guaranteed to be consistent
	 * with the corresponding output from the web server code path.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : Only list the transformers where <field> equals the requested <value>.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to all fields.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each transformer.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - count
	 *   - csv
	 *   - json
	 *   - table
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 * # Show a list of all transformers that were added by the AMP for WordPress plugin:
	 * $ wp amp optimizer transformer list --source=plugin
	 * +----------------------+--------+
	 * | transformer          | source |
	 * +----------------------+--------+
	 * | AmpSchemaOrgMetadata | plugin |
	 * | DetermineHeroImages  | plugin |
	 * +----------------------+--------+
	 *
	 * @subcommand list
	 * @when before_wp_load
	 *
	 * @param array $args       Array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 * @throws WP_CLI\ExitException If the requested file could not be read.
	 */
	public function list_( $args, $assoc_args ) {
		// Load a full frontend request to ensure all filters can be processed.
		$this->frontend_loader->run();

		$default_fields = [
			'transformer',
			'source',
		];

		$defaults = [
			'fields' => implode( ',', $default_fields ),
			'format' => 'table',
		];

		$assoc_args = array_merge( $defaults, $assoc_args );

		$transformers = array_map(
			function ( $transformer_class ) {
				return [
					'transformer' => $this->get_transformer_name( $transformer_class ),
					'source'      => $this->get_transformer_source( $transformer_class ),
				];
			},
			$this->configuration->get( Configuration::KEY_TRANSFORMERS )
		);

		$transformers = $this->filter_entries( $transformers, $default_fields, $assoc_args );

		if ( 'count' === $assoc_args['format'] ) {
			WP_CLI::log( (string) count( $transformers ) );

			return;
		}

		if ( empty( $transformers ) ) {
			WP_CLI::error( 'No matching transformers found.' );
		}

		$formatter = new WP_CLI\Formatter( $assoc_args, $default_fields );
		$formatter->display_items( $transformers );
	}

	/**
	 * List the configuration of a given transformer. (EXPERIMENTAL)
	 *
	 * Note: The Optimizer CLI commands are to be considered experimental, as
	 * the output they produce is currently not guaranteed to be consistent
	 * with the corresponding output from the web server code path.
	 *
	 * ## OPTIONS
	 *
	 * <transformer>
	 * : Name of the transformer to display the configuration for.
	 *
	 * [--<field>=<value>]
	 * : Only list the config entries where <field> equals the requested <value>.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to all fields.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each config entry.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - count
	 *   - csv
	 *   - json
	 *   - table
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 * # Check the current configuration of the RewriteAmpUrls transformer.
	 * $ wp amp optimizer transformer config RewriteAmpUrls
	 * +-------------------+----------------------------+
	 * | key               | value                      |
	 * +-------------------+----------------------------+
	 * | ampRuntimeVersion |                            |
	 * | ampUrlPrefix      | https://cdn.ampproject.org |
	 * | esmModulesEnabled | true                       |
	 * | geoApiUrl         |                            |
	 * | lts               | false                      |
	 * | rtv               | false                      |
	 * +-------------------+----------------------------+
	 *
	 * # Fetch the attribute that is added to store a backup of inlined styles.
	 * $ wp amp optimizer transformer config PreloadHeroImage --key=inlineStyleBackupAttribute --field=value
	 * data-amp-original-style
	 *
	 * # Render the configuration of the AmpRuntimeCss transformer as a JSON array.
	 * $ wp amp optimizer transformer config AmpRuntimeCss --format=json
	 * {"canary":false,"styles":"","version":""}
	 *
	 * @when before_wp_load
	 *
	 * @param array $args       Array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 * @throws WP_CLI\ExitException If the requested file could not be read.
	 */
	public function config( $args, $assoc_args ) {
		// Load a full frontend request to ensure all filters can be processed.
		$this->frontend_loader->run();

		$transformer       = array_shift( $args );
		$transformer_class = $this->deduce_transformer_class( $transformer );

		if ( false === $transformer_class ) {
			WP_CLI::error( "Unknown transformer: {$transformer}." );
		}

		$default_fields = [
			'key',
			'value',
		];

		$defaults = [
			'fields' => implode( ',', $default_fields ),
			'format' => 'table',
		];

		$assoc_args = array_merge( $defaults, $assoc_args );

		try {
			$config_array = $this->configuration->getTransformerConfiguration( $transformer_class )->toArray();
		} catch ( UnknownConfigurationClass $exception ) {
			WP_CLI::error( $exception->getMessage() );

			return;
		}

		$config_entries = [];
		foreach ( $config_array as $key => $value ) {
			if ( is_bool( $value ) && in_array( $assoc_args['format'], [ 'table', 'csv' ], true ) ) {
				$value = $value ? 'true' : 'false';
			}
			$config_entries[] = compact( 'key', 'value' );
		}

		$config_entries = $this->filter_entries( $config_entries, $default_fields, $assoc_args );

		if ( 'count' === $assoc_args['format'] ) {
			WP_CLI::log( (string) count( $config_entries ) );

			return;
		}

		if ( empty( $config_entries ) ) {
			WP_CLI::error( 'No matching config entries found.' );
		}

		if ( 'json' === $assoc_args['format'] ) {
			// Flatten the entries again for producing the JSON output that the spec tests understand.
			$json_array = [];
			foreach ( $config_entries as $config_entry ) {
				$json_array[ $config_entry['key'] ] = $config_entry['value'];
			}

			WP_CLI::log( wp_json_encode( $json_array ) );
			return;
		}

		$formatter = new WP_CLI\Formatter( $assoc_args, $default_fields );
		$formatter->display_items( $config_entries );
	}

	/**
	 * Filters the entries of an associative array based on a provided filter key.
	 *
	 * @param array $entries    Associative array to filter.
	 * @param array $fields     Array of known fields.
	 * @param array $assoc_args Filters to apply.
	 *
	 * @return array
	 */
	private function filter_entries( $entries, $fields, $assoc_args ) {
		$result = [];

		foreach ( $entries as $entry ) {
			foreach ( $fields as $field ) {
				if (
					array_key_exists( $field, $assoc_args )
					&&
					$entry[ $field ] !== $assoc_args[ $field ]
				) {
					continue 2;
				}
			}

			$result[] = $entry;
		}

		return $result;
	}

	/**
	 * Deduce the transformer class from a transformer name.
	 *
	 * @param string $transformer Transformer name to get the class for.
	 * @return string|false Class of the transformer, or false if none found.
	 */
	private function deduce_transformer_class( $transformer ) {
		$transformer_classes = $this->configuration->get( Configuration::KEY_TRANSFORMERS );

		foreach ( $transformer_classes as $transformer_class ) {
			if ( $transformer === $this->get_transformer_name( $transformer_class ) ) {
				return (string) $transformer_class;
			}
		}

		return false;
	}

	/**
	 * Get the name of a transformer from its class.
	 *
	 * @param string $transformer_class Transformer class to get the name for.
	 * @return string Name of the transformer.
	 */
	private function get_transformer_name( $transformer_class ) {
		$name_parts = explode( '\\', $transformer_class );

		return (string) array_pop( $name_parts );
	}

	/**
	 * Get the source of a transformer.
	 *
	 * @param string $transformer_class Class of the transformer to get the source for.
	 * @return string Source of the transformer. Will be one of 'toolbox', 'plugin', 'third-party'.
	 */
	private function get_transformer_source( $transformer_class ) {
		if ( 0 === strpos( $transformer_class, 'AmpProject\\Optimizer\\Transformer\\' ) ) {
			return 'toolbox';
		}

		if ( 0 === strpos( $transformer_class, 'AmpProject\\AmpWP\\Optimizer\\Transformer\\' ) ) {
			return 'plugin';
		}

		return 'third-party';
	}
}

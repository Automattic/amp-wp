<?php
/**
 * REST endpoint providing post types, taxonomies, blocks, and widgets registered by the active theme.
 *
 * @package AMP
 * @since 2.2
 */

namespace AmpProject\AmpWP\Validation;

use AmpProject\AmpWP\DevTools\UserAccess;
use AmpProject\AmpWP\Infrastructure\Conditional;
use AmpProject\AmpWP\Infrastructure\Delayed;
use AmpProject\AmpWP\Infrastructure\Registerable;
use AmpProject\AmpWP\Infrastructure\Service;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Widget_Factory;

/**
 * ThemeEntitiesRESTController class.
 *
 * @since 2.2
 * @internal
 */
final class ThemeEntitiesRESTController extends WP_REST_Controller implements Conditional, Delayed, Service, Registerable {

	/**
	 * The path for the endpoint.
	 * 
	 * @var string
	 */
	const REST_BASE = 'theme-entities';

	/**
	 * Context used to get entities with the theme disabled.
	 * 
	 * @var string
	 */
	const CONTEXT_THEME_DISABLED = 'theme-disabled';

	/**
	 * Context used to get entities registered by the theme.
	 * 
	 * @var string
	 */
	const CONTEXT_THEME_ONLY = 'theme-only';

	/**
	 * Response schema.
	 *
	 * @var array
	 */
	protected $schema;

	/**
	 * UserAccess instance.
	 *
	 * @var UserAccess
	 */
	private $dev_tools_user_access;

	/**
	 * Returns whether the class should be created.
	 *
	 * @return boolean
	 */
	public static function is_needed() {
		// Don't instantiate the class if the server path is not that of the REST endpoint.
		$path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return '/wp-json/amp/v1/' . self::REST_BASE === $path;
	}

	/**
	 * Get the action to use for registering the service.
	 *
	 * @return string Registration action to use.
	 */
	public static function get_registration_action() {
		return 'setup_theme';
	}

	/**
	 * Constructor.
	 * 
	 * @param UserAccess $dev_tools_user_access UserAccess instance.
	 */
	public function __construct( UserAccess $dev_tools_user_access ) {
		$this->namespace             = 'amp/v1';
		$this->rest_base             = self::REST_BASE;
		$this->dev_tools_user_access = $dev_tools_user_access;
	}

	/**
	 * Sets up the controller.
	 */
	public function register() {
		if ( isset( $_GET['context'] ) && self::CONTEXT_THEME_DISABLED === $_GET['context'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$hooks = [
				'pre_option_template',
				'option_template',
				'pre_option_stylesheet',
				'option_stylesheet',
			];

			foreach ( $hooks as $hook ) {
				add_filter( $hook, '__return_empty_string', 999 );
			}
		}

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers all routes for the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_results' ],
					'args'                => [
						'context' => [
							'default'     => self::CONTEXT_THEME_ONLY,
							'description' => __( 'The request context.', 'amp' ),
							'enum'        => [
								self::CONTEXT_THEME_DISABLED,
								self::CONTEXT_THEME_ONLY,
							],
							'type'        => 'string',
						],
					],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
				'schema' => $this->get_public_item_schema(),
			]
		);
	}

	/**
	 * Checks whether the current user has permission to receive URLs.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has permission; WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! $this->dev_tools_user_access->is_user_enabled() ) {
			return new WP_Error(
				'amp_rest_no_dev_tools',
				__( 'Sorry, you do not have access to dev tools for the AMP plugin for WordPress.', 'amp' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Provides registered blocks, post types, taxonomies, and widgets.
	 *
	 * @return array
	 */
	private function get_entities() {
		global $wp_widget_factory;

		return [
			'blocks'     => function_exists( 'get_dynamic_block_names' ) ? get_dynamic_block_names() : [],
			'post_types' => array_values( get_post_types() ),
			'taxonomies' => array_values( get_taxonomies() ),
			'widgets'    => is_a( $wp_widget_factory, WP_Widget_Factory::class ) ? array_keys( $wp_widget_factory->widgets ) : [],
		];
	}

	/**
	 * Retrieves compatibility results.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_results( $request ) {
		// As this request can be slow, we cache the result for the current theme and version.
		$theme         = wp_get_theme();
		$cache_key     = md5( 'amp-theme-entities' . $theme->get( 'Name' ) . $theme->get( 'Version' ) );
		$cached_result = get_transient( $cache_key );
	
		if ( $cached_result ) {
			return rest_ensure_response( $cached_result );
		}

		// If the current request is for the theme-disabled context, the filters to disable the theme will have been added in ::register.
		if ( self::CONTEXT_THEME_DISABLED === $request['context'] ) {
			return rest_ensure_response( $this->get_entities() );
		}

		$entities_with_theme = $this->get_entities();

		// Make a request to this endpoint with the theme disabled context.
		$disabled_theme_request = wp_remote_get(
			add_query_arg(
				[ 'context' => self::CONTEXT_THEME_DISABLED ],
				str_replace( 'https', 'http', rest_url( $this->namespace . '/' . $this->rest_base ) )
			)
		);

		$entities_without_theme = json_decode( wp_remote_retrieve_body( $disabled_theme_request ), true );

		// Collect only those entities that show up only when the theme is active.
		$theme_entities = [];
		foreach ( array_keys( $entities_with_theme ) as $key ) {
			$theme_entities[ $key ] = array_values( array_diff( $entities_with_theme[ $key ], $entities_without_theme[ $key ] ) );
		}

		set_transient( $cache_key, $theme_entities, 30 * DAY_IN_SECONDS );

		return rest_ensure_response( $theme_entities );
	}

	/**
	 * Retrieves the schema for plugin options provided by the endpoint.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( ! $this->schema ) {
			$this->schema = [
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'amp-wp-theme-entities',
				'type'       => 'object',
				'properties' => [
					'blocks'     => [
						'items' => 'string',
						'type'  => 'array',
					],
					'post_types' => [
						'items' => 'string',
						'type'  => 'array',
					],
					'taxonomies' => [
						'items' => 'string',
						'type'  => 'array',
					],
					'widgets'    => [
						'items' => 'string',
						'type'  => 'array',
					],
				],
			];
		}

		return $this->schema;
	}
}

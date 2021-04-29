<?php
/**
 * REST endpoint providing theme scan results.
 *
 * @package AMP
 * @since 2.1
 */

namespace AmpProject\AmpWP\Validation;

use AMP_Validated_URL_Post_Type;
use AMP_Validation_Error_Taxonomy;
use AmpProject\AmpWP\DevTools\UserAccess;
use AmpProject\AmpWP\Infrastructure\Delayed;
use AmpProject\AmpWP\Infrastructure\Registerable;
use AmpProject\AmpWP\Infrastructure\Service;
use WP_Error;
use WP_REST_Controller;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * URLValidationRESTController class.
 *
 * @since 2.1
 * @internal
 */
final class URLValidationRESTController extends WP_REST_Controller implements Delayed, Service, Registerable {

	/**
	 * URLValidationProvider instance.
	 *
	 * @var URLValidationProvider
	 */
	private $url_validation_provider;

	/**
	 * UserAccess instance.
	 *
	 * @var UserAccess
	 */
	private $dev_tools_user_access;

	/**
	 * Response schema.
	 *
	 * @var array
	 */
	protected $schema;

	/**
	 * Get the action to use for registering the service.
	 *
	 * @return string Registration action to use.
	 */
	public static function get_registration_action() {
		return 'rest_api_init';
	}

	/**
	 * Constructor.
	 *
	 * @param URLValidationProvider $url_validation_provider URLValidationProvider instance.
	 * @param UserAccess            $dev_tools_user_access UserAccess instance.
	 */
	public function __construct( URLValidationProvider $url_validation_provider, UserAccess $dev_tools_user_access ) {
		$this->namespace               = 'amp/v1';
		$this->url_validation_provider = $url_validation_provider;
		$this->dev_tools_user_access   = $dev_tools_user_access;
	}

	/**
	 * Registers all routes for the controller.
	 */
	public function register() {
		register_rest_route(
			$this->namespace,
			'/validate-post-url',
			[
				'args'   => [
					'id'            => [
						'description'       => __( 'ID for AMP-enabled post.', 'amp' ),
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'validate_callback' => [ $this, 'validate_post_id_param' ],
					],
					'preview_nonce' => [
						'description' => __( 'Preview nonce.', 'amp' ),
						'required'    => false,
						'type'        => 'string',
						'pattern'     => '^[0-9a-f]+$', // Ensure hexadecimal hash string.
					],
				],
				[
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'validate_post_url' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// @todo Additional endpoint to validate a URL (from a URL rather than a post ID).
	}

	/**
	 * Validate post ID param.
	 *
	 * @param string|int      $id      Post ID.
	 * @param WP_REST_Request $request REST request.
	 * @param string          $param   Param name ('id').
	 * @return true|WP_Error True on valid, WP_Error otherwise.
	 */
	public function validate_post_id_param( $id, $request, $param ) {
		// First enforce the schema to ensure $id is an integer greater than 0.
		$validity = rest_validate_request_arg( $id, $request, $param );
		if ( is_wp_error( $validity ) ) {
			return $validity;
		}

		// Make sure the post exists.
		$post = get_post( (int) $id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'rest_post_invalid_id',
				__( 'Invalid post ID.', 'default' ),
				[ 'status' => 404 ]
			);
		}

		// Make sure AMP is supported for the post.
		if ( ! amp_is_post_supported( $post ) ) {
			return new WP_Error(
				'amp_post_not_supported',
				__( 'AMP is not supported on post.', 'amp' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	/**
	 * Checks whether the current user can view AMP validation results.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has permission; WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
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
	 * Validate preview nonce.
	 *
	 * @see _show_post_preview()
	 *
	 * @param string $preview_nonce Preview nonce.
	 * @param int    $post_id       Post ID.
	 * @return bool Whether the preview nonce is valid.
	 */
	public function is_valid_preview_nonce( $preview_nonce, $post_id ) {
		return false !== wp_verify_nonce( $preview_nonce, 'post_preview_' . $post_id );
	}

	/**
	 * Returns validation information about a URL, validating the URL along the way.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function validate_post_url( $request ) {
		$post_id       = (int) $request['id'];
		$preview_nonce = $request['preview_nonce'];
		$url           = amp_get_permalink( $post_id );

		if ( ! empty( $preview_nonce ) ) {

			// Verify that the preview nonce is valid. Note this is not done in a `validate_callback` because
			// at that point there won't be a validated `id` parameter.
			if ( ! $this->is_valid_preview_nonce( $preview_nonce, $post_id ) ) {
				return new WP_Error(
					'amp_post_preview_denied',
					__( 'Sorry, you are not allowed to validate this post preview.', 'amp' ),
					[ 'status' => 403 ]
				);
			}

			$url = add_query_arg(
				[
					'preview'       => 1,
					'preview_id'    => $post_id,
					'preview_nonce' => $preview_nonce,
				],
				$url
			);
		}

		$validity = $this->url_validation_provider->get_url_validation( $url, get_post_type( $post_id ) );
		if ( is_wp_error( $validity ) ) {
			return $validity;
		}

		$data = [
			'results'      => [],
			'review_link'  => get_edit_post_link( $validity['post_id'], 'raw' ),
			'support_link' => admin_url( 'admin.php?page=amp-support&post_id=' . $validity['post_id'] ),
		];

		foreach ( AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( $validity['post_id'] ) as $result ) {

			// Handle case where a validationError's `sources` are an object (with numeric keys).
			// Note: this will no longer be an issue after https://github.com/ampproject/amp-wp/commit/bbb0e495a817a56b37554dfd721170712c92d7b8
			// but is still required for validation errors stored in the database prior to that commit.
			if ( isset( $result['data']['sources'] ) ) {
				$result['data']['sources'] = array_values( $result['data']['sources'] );
			} else {
				// Make sure sources are always defined.
				$result['data']['sources'] = [];
			}

			$data['results'][] = [
				'error'   => $result['data'],
				'status'  => $result['status'],
				'term_id' => $result['term']->term_id,
				'title'   => AMP_Validation_Error_Taxonomy::get_error_title_from_code( $result['data'] ),
			];
		}

		return rest_ensure_response( $this->filter_response_by_context( $data, $request['context'] ) );
	}

	/**
	 * Retrieves the schema for plugin options provided by the endpoint.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}

		$sources_type = [
			'items' => [
				'type' => 'object',
			],
			'type'  => 'array',
		];

		$sources_type['items']['properties']['sources'] = $sources_type;

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'amp-wp-url-validation',
			'type'       => 'object',
			'properties' => [
				'results'     => [
					'description' => __( 'Validation errors for the post.', 'amp' ),
					'readonly'    => true,
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'error'   => [
								'properties' => [
									'code'            => [
										'context' => [],
										'type'    => 'string',
									],
									'node_attributes' => [
										'context' => [],
										'type'    => 'object',
									],
									'node_name'       => [
										'context' => [],
										'type'    => 'string',
									],
									'node_type'       => [
										'context' => [],
										'type'    => 'integer',
									],
									'parent_name'     => [
										'context' => [],
										'type'    => 'string',
									],
									'sources'         => $sources_type,
									'type'            => [
										'type' => 'string',
									],
								],
								'type'       => 'object',
							],
							'status'  => [
								'type' => 'integer',
							],
							'term_id' => [
								'type' => 'integer',
							],
							'title'   => [
								'type' => 'string',
							],
						],
					],
				],
				'review_link' => [
					'description' => __( 'The URL where validation errors can be reviewed.', 'amp' ),
					'readonly'    => true,
					'type'        => 'string',
				],
				'support_link' => [
					'description' => __( 'The URL for AMP support.', 'amp' ),
					'readonly'    => true,
					'type'        => 'string',
				],
			],
		];

		return $this->schema;
	}
}

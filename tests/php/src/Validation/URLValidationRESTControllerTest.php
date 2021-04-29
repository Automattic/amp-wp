<?php
/**
 * Tests for URLValidationRESTController.
 *
 * @package AMP
 */

namespace AmpProject\AmpWP\Tests\Validation;

use AmpProject\AmpWP\DevTools\UserAccess;
use AmpProject\AmpWP\Tests\Helpers\ValidationRequestMocking;
use AmpProject\AmpWP\Validation\URLValidationProvider;
use AmpProject\AmpWP\Validation\URLValidationRESTController;
use WP_REST_Controller;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests for URLValidationRESTController.
 *
 * @group amp-options
 *
 * @coversDefaultClass \AmpProject\AmpWP\Validation\URLValidationRESTController
 */
class URLValidationRESTControllerTest extends WP_UnitTestCase {
	use ValidationRequestMocking;

	/**
	 * Test instance.
	 *
	 * @var URLValidationRESTController
	 */
	private $controller;

	/**
	 * Test UserAccess instance.
	 *
	 * @var UserAccess
	 */
	private $user_access;

	/**
	 * Set up.
	 */
	public function setUp() {
		parent::setUp();

		do_action( 'rest_api_init' );
		$this->user_access = new UserAccess();
		$this->controller  = new URLValidationRESTController( new URLValidationProvider(), $this->user_access );
		add_filter( 'pre_http_request', [ $this, 'get_validate_response' ] );
	}

	/** @covers ::get_registration_action() */
	public function test_get_registration_action() {
		$this->assertEquals( 'rest_api_init', URLValidationRESTController::get_registration_action() );
	}

	/** @covers ::__construct() */
	public function test__construct() {
		$this->assertInstanceOf( URLValidationRESTController::class, $this->controller );
		$this->assertInstanceOf( WP_REST_Controller::class, $this->controller );
	}

	/** @covers ::register() */
	public function test_register() {
		$this->controller->register();

		$this->assertContains( '/amp/v1/validate-post-url', array_keys( rest_get_server()->get_routes() ) );
	}

	/** @covers ::create_item_permissions_check() */
	public function test_create_item_permissions_check() {
		$this->assertWPError( $this->controller->create_item_permissions_check( new WP_REST_Request( 'POST', '/amp/v1/validate-post-url/' ) ) );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );
		$this->assertWPError( $this->controller->create_item_permissions_check( new WP_REST_Request( 'POST', '/amp/v1/validate-post-url/' ) ) );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->user_access->set_user_enabled( wp_get_current_user(), true );
		$this->assertTrue( $this->controller->create_item_permissions_check( new WP_REST_Request( 'POST', '/amp/v1/validate-post-url/' ) ) );
	}

	/** @covers ::is_valid_preview_nonce() */
	public function test_is_valid_preview_nonce() {
		$user_id = self::factory()->user->create( [ 'role' => 'author' ] );
		$post_id = self::factory()->post->create( [ 'post_author' => $user_id ] );
		wp_set_current_user( $user_id );

		$this->assertFalse( $this->controller->is_valid_preview_nonce( 'bad', 1 ) );
		$this->assertFalse( $this->controller->is_valid_preview_nonce( 'bad', $post_id ) );
		$this->assertFalse( $this->controller->is_valid_preview_nonce( wp_create_nonce( 'post_preview_' . ( $post_id + 1 ) ), $post_id ) );
		$this->assertTrue( $this->controller->is_valid_preview_nonce( wp_create_nonce( 'post_preview_' . $post_id ), $post_id ) );
	}

	/** @return array */
	public function get_data_for_test_validate_post_url() {
		return [
			'not_int'      => [
				[ 'id' => 'foo' ],
				null,
				'administrator',
				'rest_invalid_param',
			],

			'too_small'    => [
				[ 'id' => - 1 ],
				null,
				'administrator',
				'rest_invalid_param',
			],

			'empty_post'   => [
				[ 'id' => 0 ],
				null,
				'administrator',
				'rest_invalid_param',
			],

			'revision_id'  => [
				[ 'id' => '{{id}}' ],
				'revision',
				'administrator',
				'rest_invalid_param',
			],

			'as_author'    => [
				[ 'id' => '{{id}}' ],
				'post',
				'author',
				'amp_rest_no_dev_tools',
			],

			'bad_preview1' => [
				[
					'id'            => '{{id}}',
					'preview_nonce' => 'bad!!',
				],
				'post',
				'administrator',
				version_compare( get_bloginfo( 'version' ), '5.2', '<' ) ? 'amp_post_preview_denied' : 'rest_invalid_param',
			],

			'bad_preview2' => [
				[
					'id'            => '{{id}}',
					'preview_nonce' => wp_create_nonce( 'bad' ),
				],
				'post',
				'administrator',
				'amp_post_preview_denied',
			],

			'post_id'      => [
				[ 'id' => '{{id}}' ],
				'post',
				'administrator',
				true,
			],

			'good_preview' => [
				[
					'id'            => '{{id}}',
					'preview_nonce' => '{{preview_nonce}}',
				],
				'post',
				'administrator',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_data_for_test_validate_post_url()
	 * @covers ::validate_post_url()
	 * @covers ::validate_post_id_param()
	 * @param array $params                  Params (template).
	 * @param string|null $post_type         Post type.
	 * @param string      $user_role         User role.
	 * @param true|string $expected_validity Expected validity.
	 */
	public function test_validate_post_url( $params, $post_type, $user_role, $expected_validity ) {
		add_filter( 'amp_dev_tools_user_default_enabled', '__return_true' );
		$user_id = self::factory()->user->create( [ 'role' => $user_role ] );

		wp_set_current_user( $user_id );

		if ( isset( $params['id'] ) && '{{id}}' === $params['id'] && $post_type ) {
			$params['id'] = self::factory()->post->create( compact( 'post_type' ) );
		}

		if ( isset( $params['id'], $params['preview_nonce'] ) && '{{preview_nonce}}' === $params['preview_nonce'] ) {
			$params['preview_nonce'] = wp_create_nonce( 'post_preview_' . $params['id'] );
		}

		$this->controller->register();
		$request = new WP_REST_Request( 'POST', '/amp/v1/validate-post-url' );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		if ( true === $expected_validity ) {
			$this->assertFalse( $response->is_error() );
			$data = $response->get_data();

			$this->assertEquals(
				[
					'results',
					'review_link',
					'support_link',
				],
				array_keys( $data )
			);

			$this->assertNotEmpty( $data['review_link'] );
			$this->assertEquals( 1, count( $data['results'] ) );
			foreach ( $data['results'] as $result ) {
				$this->assertArrayHasKey( 'error', $result );
				$this->assertArrayHasKey( 'sources', $result['error'] );
				$this->assertArrayHasKey( 'status', $result );
				$this->assertArrayHasKey( 'term_id', $result );
				$this->assertArrayHasKey( 'title', $result );
			}
		} else {
			$this->assertTrue( $response->is_error() );
			$error = $response->as_error();
			$this->assertEquals( $expected_validity, $error->get_error_code() );
		}
	}

	/**
	 * Tests URLValidationRESTController::get_item_schema.
	 *
	 * @covers ::get_item_schema()
	 */
	public function test_get_item_schema() {
		$schema = $this->controller->get_item_schema();

		$this->assertEquals(
			[
				'$schema',
				'title',
				'type',
				'properties',
			],
			array_keys( $schema )
		);

		$this->assertEquals(
			[
				'results',
				'review_link',
				'support_link',
			],
			array_keys( $schema['properties'] )
		);
	}
}

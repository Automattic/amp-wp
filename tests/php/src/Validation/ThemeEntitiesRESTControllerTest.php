<?php
/**
 * Tests for OptionsRESTController.
 *
 * @package AMP
 */

namespace AmpProject\AmpWP\Tests;

use AmpProject\AmpWP\DevTools\UserAccess;
use AmpProject\AmpWP\Infrastructure\Conditional;
use AmpProject\AmpWP\Infrastructure\Delayed;
use AmpProject\AmpWP\Infrastructure\Registerable;
use AmpProject\AmpWP\Infrastructure\Service;
use AmpProject\AmpWP\Tests\Helpers\PrivateAccess;
use AmpProject\AmpWP\Validation\ThemeEntitiesRESTController;
use WP_REST_Controller;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests for ThemeEntitiesRESTController.
 *
 * @coversDefaultClass \AmpProject\AmpWP\VALIDATION\ThemeEntitiesRESTController
 */
class ThemeEntitiesRESTControllerTest extends WP_UnitTestCase {

	use PrivateAccess;

	/**
	 * Test instance.
	 *
	 * @var ThemeEntitiesRESTController
	 */
	private $instance;

	/**
	 * UserAccess instance.
	 *
	 * @var UserAccess
	 */
	private $dev_tools_user_access;

	/**
	 * Set up.
	 */
	public function setUp() {
		parent::setUp();

		$this->dev_tools_user_access = new UserAccess();
		$this->instance              = new ThemeEntitiesRESTController( $this->dev_tools_user_access );

		add_filter( 'pre_http_request', [ $this, 'filter_remote_request' ], 10, 3 );
		do_action( 'rest_api_init' );
	}

	public function tearDown() {
		parent::tearDown();

		$theme     = wp_get_theme();
		$cache_key = md5( 'amp-theme-entities' . $theme->get( 'Name' ) . $theme->get( 'Version' ) );
		delete_transient( $cache_key );
	}

	public function filter_remote_request( $preempt, $parsed_args, $url ) {
		if ( false === strpos( $url, 'theme-entities&context=theme-disabled' ) ) {
			return $preempt;
		}

		return [
			'body' => wp_json_encode( $this->call_private_method( $this->instance, 'get_entities' ) ),
		];

	}

	/** @covers __construct() */
	public function test__construct() {
		$this->assertInstanceOf( ThemeEntitiesRESTController::class, $this->instance );
		$this->assertInstanceOf( WP_REST_Controller::class, $this->instance );
		$this->assertInstanceOf( Conditional::class, $this->instance );
		$this->assertInstanceOf( Delayed::class, $this->instance );
		$this->assertInstanceOf( Service::class, $this->instance );
		$this->assertInstanceOf( Registerable::class, $this->instance );
	}

	/** @covers ::register() */
	public function test_register_with_default_context() {
		$this->instance->register();

		$this->assertEquals( 10, has_action( 'rest_api_init', [ $this->instance, 'register_routes' ] ) );

		$this->assertFalse( has_filter( 'pre_option_template', '__return_empty_string' ) );
		$this->assertFalse( has_filter( 'option_template', '__return_empty_string' ) );
		$this->assertFalse( has_filter( 'pre_option_stylesheet', '__return_empty_string' ) );
		$this->assertFalse( has_filter( 'option_stylesheet', '__return_empty_string' ) );
	}

	/** @covers ::register() */
	public function test_register_with_theme_disabled_context() {
		$_GET['context'] = ThemeEntitiesRESTController::CONTEXT_THEME_DISABLED;
		$this->instance->register();

		$this->assertEquals( 10, has_action( 'rest_api_init', [ $this->instance, 'register_routes' ] ) );

		$this->assertEquals( 999, has_filter( 'pre_option_template', '__return_empty_string' ) );
		$this->assertEquals( 999, has_filter( 'option_template', '__return_empty_string' ) );
		$this->assertEquals( 999, has_filter( 'pre_option_stylesheet', '__return_empty_string' ) );
		$this->assertEquals( 999, has_filter( 'option_stylesheet', '__return_empty_string' ) );
		unset( $_GET['context'] ); // phpcs:ignore
	}

	/** @covers ::register_routes */
	public function test_register_routes() {
		$this->instance->register_routes();

		$this->assertContains( 'amp/v1', rest_get_server()->get_namespaces() );
		$this->assertContains( '/amp/v1/theme-entities', array_keys( rest_get_server()->get_routes( 'amp/v1' ) ) );
	}

	/**
	 * @covers ::get_items_permissions_check()
	 */
	public function test_get_items_permissions_check() {
		$this->assertWPError( $this->instance->get_items_permissions_check( new WP_REST_Request( 'GET', '/amp/v1/' . ThemeEntitiesRESTController::REST_BASE ) ) );

		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertWPError( $this->instance->get_items_permissions_check( new WP_REST_Request( 'GET', '/amp/v1/' . ThemeEntitiesRESTController::REST_BASE ) ) );

		$this->dev_tools_user_access->set_user_enabled( wp_get_current_user(), true );

		$this->assertTrue( $this->instance->get_items_permissions_check( new WP_REST_Request( 'GET', '/amp/v1/' . ThemeEntitiesRESTController::REST_BASE ) ) );
	}

	/**
	 * @covers ::get_results()
	 * @covers ::get_entities()
	 */
	public function test_get_results_with_theme_disabled_context() {
		$request = new WP_REST_Request( 'GET', 'amp/v1/theme-entitites' );
		$request->set_url_params( [ 'context' => ThemeEntitiesRESTController::CONTEXT_THEME_DISABLED ] );

		wp_widgets_init();
		$data = $this->instance->get_results( $request )->get_data();

		$this->assertEquals(
			array_keys( $data ),
			[
				'blocks',
				'post_types',
				'taxonomies',
				'widgets',
			]
		);

		if ( function_exists( 'get_dynamic_block_names' ) ) {
			$this->assertNotEmpty( $data['blocks'] );
		} else {
			$this->assertEmpty( $data['blocks'] );
		}
		
		$this->assertNotEmpty( $data['post_types'] );
		$this->assertNotEmpty( $data['taxonomies'] );
		$this->assertNotEmpty( $data['widgets'] );
	}

	/**
	 * @covers ::get_results()
	 * @covers ::get_entities()
	 */
	public function test_get_results_with_default_context() {
		$request = new WP_REST_Request( 'GET', 'amp/v1/theme-entitites' );

		$data = $this->instance->get_results( $request )->get_data();

		$this->assertEquals(
			array_keys( $data ),
			[
				'blocks',
				'post_types',
				'taxonomies',
				'widgets',
			]
		);

		$this->assertEmpty( $data['blocks'] );
		$this->assertEmpty( $data['post_types'] );
		$this->assertEmpty( $data['taxonomies'] );
		$this->assertEmpty( $data['widgets'] );
	}

	/**
	 * @covers ::get_results()
	 * @covers ::get_entities()
	 */
	public function test_get_results_cache() {
		$request = new WP_REST_Request( 'GET', 'amp/v1/theme-entitites' );
		$this->instance->get_results( $request );

		$theme     = wp_get_theme();
		$cache_key = md5( 'amp-theme-entities' . $theme->get( 'Name' ) . $theme->get( 'Version' ) );

		$this->assertEquals(
			array_keys( get_transient( $cache_key ) ),
			[
				'blocks',
				'post_types',
				'taxonomies',
				'widgets',
			]
		);
	}

	/** @covers ::get_item_schema() */
	public function test_get_item_schema() {
		$schema = $this->instance->get_item_schema();

		$this->assertEquals(
			array_keys( $schema ),
			[
				'$schema',
				'title',
				'type',
				'properties',
			]
		);

		$this->assertEquals(
			array_keys( $schema['properties'] ),
			[
				'blocks',
				'post_types',
				'taxonomies',
				'widgets',
			]
		);
	}
}

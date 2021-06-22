<?php
/**
 * Tests for SupportMenu.
 *
 * @package AmpProject\AmpWP\Tests
 */

namespace AmpProject\AmpWP\Tests\Admin;

use AmpProject\AmpWP\Admin\GoogleFonts;
use AmpProject\AmpWP\Admin\OptionsMenu;
use AmpProject\AmpWP\Admin\ReaderThemes;
use AmpProject\AmpWP\Admin\RESTPreloader;
use AmpProject\AmpWP\Admin\SupportMenu;
use AmpProject\AmpWP\DependencySupport;
use AmpProject\AmpWP\Tests\Helpers\AssertContainsCompatibility;
use WP_UnitTestCase;

/**
 * Tests for SupportMenu.
 *
 * @group support-menu
 * @coversDefaultClass \AmpProject\AmpWP\Admin\SupportMenu
 */
class SupportMenuTest extends WP_UnitTestCase {

	use AssertContainsCompatibility;

	/**
	 * Instance of SupportMenu
	 *
	 * @var SupportMenu
	 */
	public $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {

		parent::setUp();

		$option_menu    = new OptionsMenu( new GoogleFonts(), new ReaderThemes(), new RESTPreloader(), new DependencySupport() );
		$this->instance = new SupportMenu( $option_menu, new GoogleFonts() );
	}

	/** @covers ::__construct() */
	public function test__construct() {

		$this->assertInstanceOf( SupportMenu::class, $this->instance );
	}

	/**
	 * @covers ::is_needed
	 */
	public function test_is_needed() {

		// Without mocking.
		$this->assertFalse( SupportMenu::is_needed() );

		// Mock the is_admin()
		set_current_screen( $this->instance->screen_handle() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		add_filter( 'amp_support_menu_is_enabled', '__return_true', 999 );

		$this->assertTrue( SupportMenu::is_needed() );

		// Reset data.
		unset( $GLOBALS['current_screen'] );
	}

	/**
	 * @covers ::register
	 */
	public function test_register() {

		$this->instance->register();

		$this->assertEquals( 10, has_action( 'admin_enqueue_scripts', [ $this->instance, 'enqueue_assets' ] ) );
		$this->assertEquals( 9, has_action( 'admin_menu', [ $this->instance, 'add_menu_items' ] ) );
		$this->assertEquals(
			10,
			has_action(
				'wp_ajax_' . SupportMenu::AJAX_ACTION,
				[ $this->instance, 'ajax_callback' ]
			)
		);

	}

	/**
	 * @covers ::get_menu_slug
	 */
	public function test_get_menu_slug() {

		$this->assertEquals( 'amp-support', $this->instance->get_menu_slug() );
	}

	/**
	 * @covers ::screen_handle
	 */
	public function test_screen_handle() {

		$this->assertEquals(
			sprintf( 'amp_page_%s', $this->instance->get_menu_slug() ),
			$this->instance->screen_handle()
		);
	}

	/**
	 * @covers ::add_menu_items
	 */
	public function test_add_menu_items() {

		global $submenu;

		wp_set_current_user(
			self::factory()->user->create(
				[
					'role' => 'administrator',
				]
			)
		);

		$this->instance->add_menu_items();

		$this->assertArrayHasKey( 'amp-options', $submenu );
		$this->assertContains(
			[
				'Support',
				'manage_options',
				'amp-support',
				'Support',
			],
			$submenu['amp-options']
		);

	}

	/**
	 * @covers ::enqueue_assets
	 */
	public function test_enqueue_assets() {

		$wp_scripts = wp_scripts();
		$wp_styles  = wp_styles();

		$this->instance->enqueue_assets( '' );

		$this->assertArrayNotHasKey( SupportMenu::ASSET_HANDLE, $wp_scripts->registered );
		$this->assertArrayNotHasKey( SupportMenu::ASSET_HANDLE, $wp_styles->registered );

		$this->instance->enqueue_assets( $this->instance->screen_handle() );

		$this->assertArrayHasKey( SupportMenu::ASSET_HANDLE, $wp_scripts->registered );
		$this->assertArrayHasKey( SupportMenu::ASSET_HANDLE, $wp_styles->registered );

	}

	/**
	 * Data provider for $this->test_ajax_callback()
	 *
	 * @return array[]
	 */
	public function ajax_callback_data_provider() {

		return [
			'fail'    => [
				[
					'status' => 'fail',
					'data'   => [
						'message' => 'Fail to generate UUID',
					],
				],
				'Fail to send data',
			],
			'success' => [
				[
					'status' => 'ok',
					'data'   => [
						'uuid' => 'ampwp-563e5de8-3129-55fb-af71-a6fbd9ef5026',
					],
				],
				'ampwp-563e5de8-3129-55fb-af71-a6fbd9ef5026',
			],
		];
	}

	/**
	 * @covers ::ajax_callback
	 */
	public function test_ajax_callback_unauthorized() {

		$this->perform_test_on_ajax_callback_with(
			[
				'status' => 'ok',
				'data'   => [
					'uuid' => 'ampwp-563e5de8-3129-55fb-af71-a6fbd9ef5026',
				],
			],
			'{"success":false,"data":"Unauthorized."}'
		);
	}

	/**
	 * @dataProvider ajax_callback_data_provider
	 *
	 * @covers ::ajax_callback
	 */
	public function test_ajax_callback( $request_response, $expected ) {

		// Mock User.
		wp_set_current_user(
			self::factory()->user->create(
				[
					'role' => 'administrator',
				]
			)
		);

		$this->perform_test_on_ajax_callback_with( $request_response, $expected );
	}

	/**
	 * To perform test on $this->ajax_callback().
	 *
	 * @param array  $request_response Value to mock for response for API.
	 * @param string $expected         Expected AJAX response.
	 *
	 * @return void
	 */
	private function perform_test_on_ajax_callback_with( $request_response, $expected ) {

		$callback_wp_remote = static function () use ( $request_response ) {

			return [
				'body' => wp_json_encode( $request_response ),
			];
		};

		$callback_wp_die_ajax = static function () {

			return static function () {
			};
		};

		add_filter( 'pre_http_request', $callback_wp_remote );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $callback_wp_die_ajax );

		ob_start();
		$this->instance->ajax_callback();
		$response = ob_get_clean();

		$this->assertStringContains( $expected, $response );

		remove_filter( 'pre_http_request', $callback_wp_remote );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', $callback_wp_die_ajax );
	}

	/**
	 * @covers ::render_screen
	 */
	public function test_render_screen() {

		ob_start();
		$this->instance->render_screen();
		$content = ob_get_clean();

		$this->assertStringContains( '<div id="amp-support-root"></div>', $content );
	}
}

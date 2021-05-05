<?php
/**
 * Test AMP_Admin_Support.
 *
 * @package AMP
 * @since 2.1
 */

use AmpProject\AmpWP\Admin\GoogleFonts;
use AmpProject\AmpWP\Option;
use AmpProject\AmpWP\Admin\OptionsMenu;
use AmpProject\AmpWP\Admin\ReaderThemes;
use AmpProject\AmpWP\Admin\RESTPreloader;
use AmpProject\AmpWP\Tests\Helpers\AssertContainsCompatibility;
use AmpProject\AmpWP\QueryVar;

/**
 * Test AMP_Admin_Support.
 *
 * @coversDefaultClass \AMP_Admin_Support
 */
class AMP_Admin_Support_Test extends WP_UnitTestCase {

	use AssertContainsCompatibility;

	/**
	 * Test instance.
	 *
	 * @var AMP_Admin_Support
	 */
	private $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->options_menu_instance = new OptionsMenu(
			new GoogleFonts(),
			new ReaderThemes(),
			new RESTPreloader()
		);
		$this->instance = new \AMP_Admin_Support();
	}

	/**
	 * Test init method.
	 *
	 * @covers ::init()
	 */
	public function test_init() {
		$this->instance->init();

		$this->assertEquals(
			10,
			has_action( 'admin_enqueue_scripts', [ $this->instance, 'admin_enqueue_scripts' ] )
		);
		$this->assertEquals(
			20,
			has_action( 'admin_menu', [ $this->instance, 'admin_menu' ] )
		);
		$this->assertEquals(
			10,
			has_action( 'wp_ajax_amp_diagnostic', [ $this->instance, 'wp_ajax_amp_diagnostic' ] )
		);
		$this->assertEquals(
			102,
			has_action( 'admin_bar_menu', [ $this->instance, 'admin_bar_menu' ] )
		);
		$this->assertEquals(
			10,
			has_filter( 'amp_validated_url_status_actions', [ $this->instance, 'amp_validated_url_status_actions' ] )
		);
		$this->assertEquals(
			PHP_INT_MAX - 1,
			has_filter( 'post_row_actions', [ $this->instance, 'post_row_actions' ] )
		);
		$this->assertEquals(
			10,
			has_filter( 'plugin_row_meta', [ $this->instance, 'plugin_row_meta' ] )
		);
	}

	/**
	 * Test wp_ajax_amp_diagnostic method.
	 *
	 * @covers ::wp_ajax_amp_diagnostic()
	 */
	public function test_wp_ajax_amp_diagnostic() {
		$sending = $this->instance->wp_ajax_amp_diagnostic();

		$args = [
			'urls'     => [],
			'post_ids' => [],
			'term_ids' => [],
		];

		$amp_data_object = new AMP_Prepare_Data( $args );
		$data            = $amp_data_object->get_data();

		$data = wp_parse_args(
			$data,
			[
				'site_url'      => [],
				'site_info'     => [],
				'plugins'       => [],
				'themes'        => [],
				'errors'        => [],
				'error_sources' => [],
				'urls'          => [],
			]
		);

		$this->assertSame(
			$sending['endpoint'],
			'https://insights.amp-wp.org/api/v1/amp-wp/'
		);
		$this->assertSame(
			$sending['data'],
			$data
		);
	}

	/**
	 * Test admin_enqueue_scripts method.
	 *
	 * @covers ::admin_enqueue_scripts()
	 */
	public function test_admin_enqueue_scripts() {
		$this->instance->admin_enqueue_scripts( '' );

		$this->assertFalse(
			wp_style_is( \AMP_Admin_Support::ASSET_HANDLE, 'enqueued' )
		);

		$this->instance->admin_enqueue_scripts( 'amp_page_amp-support' );

		$this->assertTrue(
			wp_style_is( \AMP_Admin_Support::ASSET_HANDLE, 'enqueued' )
		);
	}

	/**
	 * Test admin_menu method.
	 *
	 * @covers ::admin_menu()
	 */
	public function test_admin_menu() {
		global $submenu;

		$original_submenu = $submenu;

		$test_user = self::factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		wp_set_current_user( $test_user );

		$this->options_menu_instance->add_menu_items();
		$this->instance->admin_menu();

		$this->assertContains( 'Support', wp_list_pluck( $submenu[ $this->options_menu_instance->get_menu_slug() ], 0 ) );

		$submenu = $original_submenu;
	}

	/**
	 * Test support_page method.
	 *
	 * @covers ::support_page()
	 */
	public function test_support_page() {
		ob_start();
		$this->instance->support_page();
		$html = ob_get_clean();

		// There's a send button with class is-primary.
		$this->assertStringContains(
			'<a href="#" class="components-button is-primary">' . esc_html__( 'Send Diagnostics', 'amp' ) . '</a>',
			$html
		);
		// There's an element with ID "status".
		$this->assertStringContains(
			'<p id="status"></p>',
			$html
		);
		// There's a click action on the primary link.
		$this->assertStringContains(
			"$( 'a.is-primary' ).click(function(){",
			$html
		);
		// There's the correct AJAX action.
		$this->assertStringContains(
			"'action': 'amp_diagnostic'",
			$html
		);
		// There's the correct AJAX nonce.
		$this->assertStringContains(
			"'_ajax_nonce': '" . esc_js( wp_create_nonce( 'amp-diagnostic' ) ) . "'",
			$html
		);
	}

	/**
	 * Test admin_bar_menu method.
	 *
	 * @covers ::admin_bar_menu()
	 */
	public function test_admin_bar_menu() {
		$this->go_to( home_url( '/' ) );
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		$admin_bar = new WP_Admin_Bar();

		// AMP-first mode.
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Validation_Manager::add_admin_bar_menu_items( $admin_bar );
		$this->instance->admin_bar_menu( $admin_bar );

		$node = $admin_bar->get_node( 'amp-diagnostic' );
		$this->assertInternalType( 'object', $node );
		$this->assertStringContains( 'page=amp-support', $node->href );
	}

	/**
	 * Test amp_validated_url_status_actions method.
	 *
	 * @covers ::amp_validated_url_status_actions()
	 */
	public function test_amp_validated_url_status_actions() {
		$post = new WP_Post();
		$post->ID = 123;

		$actions = $this->instance->amp_validated_url_status_actions( $actions, $post );

		$this->assertStringContains(
			'page=amp-support',
			$actions['send-diagnostic']
		);
		$this->assertStringContains(
			'post_id=123',
			$actions['send-diagnostic']
		);
	}

	/**
	 * Test plugin_row_meta method.
	 *
	 * @covers ::plugin_row_meta()
	 */
	public function test_plugin_row_meta() {
		$initial_meta = [
			'Link 1',
			'Link 2',
		];

		$expected_meta = array_merge(
			$initial_meta,
			[
				sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						add_query_arg(
							[
								'page'    => 'amp-support',
								'post_id' => 0,
							],
							admin_url( 'admin.php' )
						)
					),
					esc_html__( 'Contact support', 'amp' )
				),
			]
		);

		$this->assertEquals(
			$expected_meta,
			$this->instance->plugin_row_meta(
				$initial_meta,
				'amp/amp.php',
				[],
				''
			)
		);

		$this->assertEquals(
			$expected_meta,
			$this->instance->plugin_row_meta(
				$initial_meta,
				'amp-wp/amp.php',
				[],
				''
			)
		);
	}
}

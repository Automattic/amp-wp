<?php
/**
 * Test AMP_Admin_Support.
 *
 * @package AMP
 * @since 2.1
 */

use AmpProject\AmpWP\Admin\GoogleFonts;
use AmpProject\AmpWP\QueryVar;

/**
 * Test AMP_Admin_Support.
 *
 * @coversDefaultClass \AMP_Admin_Support
 */
class AMP_Admin_Support_Test extends WP_UnitTestCase {

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
			has_action( 'admin_enqueue_scripts', [ $this->instance, 'enqueue_assets' ] )
		);
		$this->assertEquals(
			10,
			has_action( 'admin_menu', [ $this->instance, 'admin_menu' ] )
		);
		$this->assertEquals(
			10,
			has_action( 'wp_ajax_amp_diagnostic', [ $this->instance, 'amp_diagnostic' ] )
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

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
		$this->instance->init();
	}

	/**
	 * Test plugin_row_meta method.
	 *
	 * @covers ::plugin_row_meta()
	 */
	public function test_plugin_row_meta() {
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
			$admin_support->plugin_row_meta(
				$initial_meta,
				'amp/amp.php',
				[],
				''
			)
		);
	}
}

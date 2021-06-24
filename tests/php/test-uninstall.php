<?php
/**
 * Test cases for uninstall.php
 *
 * @package AMP
 */

class Test_Uninstall extends WP_UnitTestCase {

	/**
	 * @covers ::delete_options
	 * @covers ::delete_posts
	 * @covers ::delete_terms
	 */
	public function test_uninstall_php() {

		/**
		 * Create dummy data.
		 */

		update_option( 'amp-options', 'Yes' );

		$amp_validated_post = $this->factory()->post->create_and_get(
			[
				'post_type' => \AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
			]
		);

		$amp_error_term = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'amp_validation_error',
			]
		);

		/**
		 * Mock uninstall const.
		 */
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'Yes' );
		}

		require_once AMP__DIR__ . '/uninstall.php';

		$this->assertEmpty( get_option( 'amp-option', false ) );
		$this->assertEmpty( get_post( $amp_validated_post->ID ) );
		$this->assertEmpty( get_term( $amp_error_term->term_id ) );
	}
}

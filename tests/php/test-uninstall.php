<?php
/**
 * Test cases for uninstall.php
 *
 * @package AMP
 */

/**
 * @runInSeparateProcess
 * @group uninstall
 */
class Test_Uninstall extends WP_UnitTestCase {

	/**
	 * @covers ::delete_options
	 * @covers ::delete_transient
	 * @covers ::delete_posts
	 * @covers ::delete_terms
	 */
	public function test_uninstall_php() {

		$original_value = wp_using_ext_object_cache();

		wp_using_ext_object_cache( false );

		/**
		 * Create dummy data.
		 */
		$blog_name = 'Sample Blog Name';
		update_option( 'blogname', $blog_name );
		update_option( 'amp-options', 'Yes' );

		$amp_validated_post = $this->factory()->post->create_and_get(
			[
				'post_type' => \AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
			]
		);

		$page_post = $this->factory()->post->create_and_get(
			[
				'post_type' => 'page',
			]
		);

		$amp_error_term = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'amp_validation_error',
			]
		);

		$post_tag_term = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'post_tag',
			]
		);

		$transient_groups_to_remove = [
			'amp-parsed-stylesheet-v',
			'amp_img_',
			'amp_new_validation_error_urls_count',
			'amp_error_index_counts',
			'amp_plugin_activation_validation_errors',
			'amp_themes_wporg',
			'amp_lock_',
		];

		foreach ( $transient_groups_to_remove as $transient_group ) {
			set_transient( $transient_group, 'Sample value', 10000 );
		}

		set_transient( 'amp_sample_group', 'AMP Sample value', 10000 );

		/**
		 * Mock uninstall const.
		 */
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'Yes' );
		}

		require_once AMP__DIR__ . '/uninstall.php';

		$this->flush_cache();

		$this->assertEmpty( get_option( 'amp-option', false ) );
		$this->assertEmpty( get_post( $amp_validated_post->ID ) );
		$this->assertEmpty( get_term( $amp_error_term->term_id ) );

		$this->assertTrue( is_a( get_post( $page_post->ID ), 'WP_Post' ) );
		$this->assertTrue( is_a( get_term( $post_tag_term->term_id ), 'WP_Term' ) );

		$this->assertEquals( $blog_name, get_option( 'blogname', false ) );

		foreach ( $transient_groups_to_remove as $transient_group ) {
			$this->assertEmpty( get_transient( $transient_group ) );
		}

		$this->assertEquals( 'AMP Sample value', get_transient( 'amp_sample_group' ) );

		/**
		 * Restore values
		 */
		wp_using_ext_object_cache( $original_value );
	}
}

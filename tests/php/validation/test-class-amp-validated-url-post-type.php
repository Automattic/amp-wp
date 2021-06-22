<?php
/**
 * Tests for AMP_Validated_URL_Post_Type class.
 *
 * @package AMP
 */

use AmpProject\AmpWP\DevTools\UserAccess;
use AmpProject\AmpWP\Option;
use AmpProject\AmpWP\Services;
use AmpProject\AmpWP\Tests\Helpers\AssertContainsCompatibility;
use AmpProject\AmpWP\Tests\Helpers\HandleValidation;
use AmpProject\AmpWP\Tests\Helpers\LoadsCoreThemes;
use AmpProject\AmpWP\Tests\Helpers\PrivateAccess;

// phpcs:disable WordPress.Variables.GlobalVariables.OverrideProhibited

/**
 * Tests for AMP_Validated_URL_Post_Type class.
 *
 * @covers AMP_Validated_URL_Post_Type
 */
class Test_AMP_Validated_URL_Post_Type extends WP_UnitTestCase {

	use AssertContainsCompatibility;
	use HandleValidation;
	use PrivateAccess;
	use LoadsCoreThemes;

	const TESTED_CLASS = 'AMP_Validated_URL_Post_Type';

	public function setUp() {
		parent::setUp();

		$this->register_core_themes();
	}

	public function tearDown() {
		parent::tearDown();

		global $current_screen;
		$current_screen = null;

		$this->restore_theme_directories();
	}

	/**
	 * Test register.
	 *
	 * @covers \AMP_Validated_URL_Post_Type::register()
	 * @covers \AMP_Validated_URL_Post_Type::add_admin_hooks()
	 */
	public function test_register() {
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		$this->assertFalse( is_admin() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		AMP_Validated_URL_Post_Type::register();
		$amp_post_type = get_post_type_object( AMP_Validated_URL_Post_Type::POST_TYPE_SLUG );

		$this->assertContains( AMP_Validated_URL_Post_Type::POST_TYPE_SLUG, get_post_types() );
		$this->assertEquals( [], get_all_post_type_supports( AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ) );
		$this->assertEquals( AMP_Validated_URL_Post_Type::POST_TYPE_SLUG, $amp_post_type->name );
		$this->assertEquals( 'AMP Validated URLs', $amp_post_type->label );
		$this->assertEquals( false, $amp_post_type->public );
		$this->assertTrue( $amp_post_type->show_ui );
		$this->assertEquals( AMP_Options_Manager::OPTION_NAME, $amp_post_type->show_in_menu );
		$this->assertTrue( $amp_post_type->show_in_admin_bar );
		$this->assertNotContains( AMP_Validated_URL_Post_Type::REMAINING_ERRORS, wp_removable_query_args() );
		$this->assertEquals( 10, has_action( 'admin_menu', [ self::TESTED_CLASS, 'update_validated_url_menu_item' ] ) );

		// Make sure that add_admin_hooks() gets called.
		set_current_screen( 'index.php' );
		AMP_Validated_URL_Post_Type::register();
		$this->assertContains( AMP_Validated_URL_Post_Type::REMAINING_ERRORS, wp_removable_query_args() );

		$post = self::factory()->post->create( [ 'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );
		$this->assertTrue( user_can( wp_get_current_user()->ID, 'edit_post', $post ) );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertFalse( current_user_can( 'edit_post', $post ) );
	}

	/**
	 * Test add_admin_hooks.
	 *
	 * @covers \AMP_Validated_URL_Post_Type::add_admin_hooks()
	 */
	public function test_add_admin_hooks() {
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Validated_URL_Post_Type::add_admin_hooks();

		$this->assertEquals( 10, has_action( 'admin_enqueue_scripts', [ self::TESTED_CLASS, 'enqueue_edit_post_screen_scripts' ] ) );
		$this->assertEquals( PHP_INT_MAX, has_action( 'add_meta_boxes', [ self::TESTED_CLASS, 'add_meta_boxes' ] ) );
		$this->assertEquals( 10, has_action( 'edit_form_top', [ self::TESTED_CLASS, 'print_url_as_title' ] ) );

		$this->assertEquals( 10, has_filter( 'the_title', [ self::TESTED_CLASS, 'filter_the_title_in_post_list_table' ] ) );
		$this->assertEquals( 10, has_filter( 'restrict_manage_posts', [ self::TESTED_CLASS, 'render_post_filters' ] ) );
		$this->assertEquals( 10, has_filter( 'manage_' . AMP_Validated_URL_Post_Type::POST_TYPE_SLUG . '_posts_columns', [ self::TESTED_CLASS, 'add_post_columns' ] ) );
		$this->assertEquals( 10, has_filter( 'manage_' . AMP_Validated_URL_Post_Type::POST_TYPE_SLUG . '_columns', [ self::TESTED_CLASS, 'add_single_post_columns' ] ) );
		$this->assertEquals( 10, has_action( 'manage_posts_custom_column', [ self::TESTED_CLASS, 'output_custom_column' ] ) );
		$this->assertEquals( PHP_INT_MAX - 1, has_filter( 'post_row_actions', [ self::TESTED_CLASS, 'filter_post_row_actions' ] ) );
		$this->assertEquals( 10, has_filter( 'bulk_actions-edit-' . AMP_Validated_URL_Post_Type::POST_TYPE_SLUG, [ self::TESTED_CLASS, 'filter_bulk_actions' ] ) );
		$this->assertEquals( 10, has_filter( 'bulk_actions-' . AMP_Validated_URL_Post_Type::POST_TYPE_SLUG, '__return_false' ) );
		$this->assertEquals( 10, has_filter( 'handle_bulk_actions-edit-' . AMP_Validated_URL_Post_Type::POST_TYPE_SLUG, [ self::TESTED_CLASS, 'handle_bulk_action' ] ) );
		$this->assertEquals( 10, has_action( 'admin_notices', [ self::TESTED_CLASS, 'print_admin_notice' ] ) );
		$this->assertEquals( 10, has_action( 'admin_action_' . AMP_Validated_URL_Post_Type::VALIDATE_ACTION, [ self::TESTED_CLASS, 'handle_validate_request' ] ) );
		$this->assertEquals( 10, has_action( 'post_action_' . AMP_Validated_URL_Post_Type::UPDATE_POST_TERM_STATUS_ACTION, [ self::TESTED_CLASS, 'handle_validation_error_status_update' ] ) );

		$post = self::factory()->post->create_and_get( [ 'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );
		$this->assertEquals( '', apply_filters( 'post_date_column_status', 'publish', $post ) );
		$this->assertEquals( 'publish', apply_filters( 'post_date_column_status', 'publish', self::factory()->post->create_and_get() ) );

		$this->assertContains( 'amp_actioned', wp_removable_query_args() );
		$this->assertContains( 'amp_taxonomy_terms_updated', wp_removable_query_args() );
		$this->assertContains( AMP_Validated_URL_Post_Type::REMAINING_ERRORS, wp_removable_query_args() );
		$this->assertContains( 'amp_urls_tested', wp_removable_query_args() );
		$this->assertContains( 'amp_validate_error', wp_removable_query_args() );
	}

	/**
	 * Test update_validated_url_menu_item.
	 *
	 * @covers \AMP_Validated_URL_Post_Type::update_validated_url_menu_item()
	 */
	public function test_update_validated_url_menu_item() {
		$admin_user = self::factory()->user->create_and_get( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user->ID );
		global $submenu;

		$original_submenu = $submenu;

		AMP_Validation_Manager::init(); // Register the post type and taxonomy.
		AMP_Validated_URL_Post_Type::update_validated_url_menu_item();

		$submenu[ AMP_Options_Manager::OPTION_NAME ] = [
			0 => [
				0 => 'General',
				1 => 'manage_options',
				2 => 'amp-options',
				3 => 'AMP Settings',
			],
			1 => [
				0 => 'Analytics',
				1 => 'manage_options',
				2 => 'amp-analytics-options',
				3 => 'AMP Analytics Options',
			],
			2 => [
				0 => 'All Validated URLs',
				1 => 'amp_validate',
				2 => 'edit.php?post_type=amp_validated_url',
				3 => 'AMP Validated URLs',
			],
		];

		AMP_Validated_URL_Post_Type::update_validated_url_menu_item();
		$this->assertSame( 'Validated URLs', $submenu[ AMP_Options_Manager::OPTION_NAME ][2][0] );

		update_user_meta( $admin_user->ID, UserAccess::USER_FIELD_DEVELOPER_TOOLS_ENABLED, wp_json_encode( true ) );

		AMP_Validated_URL_Post_Type::update_validated_url_menu_item();
		if ( Services::get( 'dependency_support' )->has_support() ) {
			$this->assertSame( 'Validated URLs <span id="new-validation-url-count" class="awaiting-mod"><span class="amp-count-loading"></span></span>', $submenu[ AMP_Options_Manager::OPTION_NAME ][2][0] );
		} else {
			$this->assertSame( 'Validated URLs', $submenu[ AMP_Options_Manager::OPTION_NAME ][2][0] );
		}

		$submenu = $original_submenu;
	}

	/**
	 * Test get_invalid_url_validation_errors and display_invalid_url_validation_error_counts_summary.
	 *
	 * @covers \AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors()
	 * @covers \AMP_Validated_URL_Post_Type::display_invalid_url_validation_error_counts_summary()
	 * @covers \AMP_Validated_URL_Post_Type::store_validation_errors()
	 */
	public function test_get_invalid_url_validation_errors() {
		$this->accept_sanitization_by_default( false );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		AMP_Validation_Manager::init();
		$post = self::factory()->post->create();
		$this->assertEmpty( AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( get_permalink( $post ) ) );

		add_filter(
			'amp_validation_error_default_sanitized',
			static function( $sanitized, $error ) {
				if ( 'new accepted' === $error['code'] ) {
					$sanitized = true;
				} elseif ( 'new rejected' === $error['code'] ) {
					$sanitized = false;
				}
				return $sanitized;
			},
			10,
			2
		);

		add_filter(
			'amp_validation_error_sanitized',
			static function( $sanitized, $error ) {
				if ( 'accepted' === $error['code'] ) {
					$sanitized = true;
				} elseif ( 'rejected' === $error['code'] ) {
					$sanitized = false;
				}
				return $sanitized;
			},
			10,
			2
		);

		$invalid_url_post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'accepted' ],
				[ 'code' => 'rejected' ],
				[ 'code' => 'new accepted' ],
				[ 'code' => 'new rejected' ],
			],
			get_permalink( $post )
		);
		$this->assertNotInstanceOf( 'WP_Error', $invalid_url_post_id );

		$errors = AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( get_permalink( $post ) );
		$this->assertCount( 4, $errors );

		$error = array_shift( $errors );
		$this->assertEquals( 'accepted', $error['data']['code'] );
		$this->assertEquals( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS, $error['term_status'] );
		$error = array_shift( $errors );
		$this->assertEquals( 'rejected', $error['data']['code'] );
		$this->assertEquals( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS, $error['term_status'] );
		$error = array_shift( $errors );
		$this->assertEquals( 'new accepted', $error['data']['code'] );
		$this->assertEquals( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS, $error['term_status'] );
		$error = array_shift( $errors );
		$this->assertEquals( 'new rejected', $error['data']['code'] );
		$this->assertEquals( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS, $error['term_status'] );

		$errors = AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( get_permalink( $post ), [ 'ignore_accepted' => true ] );
		$this->assertCount( 2, $errors );
		$error = array_shift( $errors );
		$this->assertEquals( 'rejected', $error['data']['code'] );
		$this->assertEquals( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS, $error['term_status'] );
		$error = array_shift( $errors );
		$this->assertEquals( 'new rejected', $error['data']['code'] );
		$this->assertEquals( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS, $error['term_status'] );

		$summary = get_echo( [ 'AMP_Validated_URL_Post_Type', 'display_invalid_url_validation_error_counts_summary' ], [ $invalid_url_post_id ] );
		$this->assertStringContains( 'Invalid markup kept: 2', $summary );
		$this->assertStringContains( 'Invalid markup removed: 2', $summary );
	}

	/**
	 * Test for get_invalid_url_post().
	 *
	 * @covers \AMP_Validated_URL_Post_Type::get_invalid_url_post()
	 */
	public function test_get_invalid_url_post() {
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		AMP_Validation_Manager::init();
		$post = self::factory()->post->create_and_get();
		$this->assertEquals( null, AMP_Validated_URL_Post_Type::get_invalid_url_post( get_permalink( $post ) ) );

		$invalid_post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'foo' ],
			],
			get_permalink( $post )
		);
		$this->assertNotInstanceOf( 'WP_Error', $invalid_post_id );

		$this->assertEquals(
			$invalid_post_id,
			AMP_Validated_URL_Post_Type::get_invalid_url_post( get_permalink( $post ) )->ID
		);

		// Test trashed.
		wp_trash_post( $invalid_post_id );
		$this->assertNull( AMP_Validated_URL_Post_Type::get_invalid_url_post( get_permalink( $post ) ) );
		$args = [ 'include_trashed' => true ];
		$this->assertEquals(
			$invalid_post_id,
			AMP_Validated_URL_Post_Type::get_invalid_url_post( get_permalink( $post ), $args )->ID
		);
		wp_untrash_post( $invalid_post_id );

		// Test normalized.
		$args = [ 'normalize' => false ];
		$url  = add_query_arg(
			array_fill_keys( wp_removable_query_args(), 'true' ),
			get_permalink( $post ) . '#baz'
		);
		$url  = set_url_scheme( $url, 'http' );
		$this->assertNull( AMP_Validated_URL_Post_Type::get_invalid_url_post( $url, $args ) );
		$args = [ 'normalize' => true ];
		$this->assertEquals( $invalid_post_id, AMP_Validated_URL_Post_Type::get_invalid_url_post( $url, $args )->ID );
		$this->assertEquals( $invalid_post_id, AMP_Validated_URL_Post_Type::get_invalid_url_post( $url )->ID );
		$url = set_url_scheme( get_permalink( $post ), 'http' );
		$this->assertNull( AMP_Validated_URL_Post_Type::get_invalid_url_post( $url, [ 'normalize' => false ] ) );
		$this->assertEquals( $invalid_post_id, AMP_Validated_URL_Post_Type::get_invalid_url_post( $url, [ 'normalize' => true ] )->ID );
	}

	/**
	 * Test get_url_from_post.
	 *
	 * @covers \AMP_Validated_URL_Post_Type::get_url_from_post()
	 */
	public function test_get_url_from_post() {
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		AMP_Validation_Manager::init();
		$post = self::factory()->post->create_and_get();

		$this->assertNull( AMP_Validated_URL_Post_Type::get_url_from_post( 0 ) );
		$this->assertNull( AMP_Validated_URL_Post_Type::get_url_from_post( $post ) );

		$invalid_post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'foo' ],
			],
			get_permalink( $post )
		);
		$this->assertNotInstanceOf( 'WP_Error', $invalid_post_id );

		$this->assertEquals(
			amp_add_paired_endpoint( get_permalink( $post ) ),
			AMP_Validated_URL_Post_Type::get_url_from_post( $invalid_post_id )
		);

		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		$this->assertEquals(
			get_permalink( $post ),
			AMP_Validated_URL_Post_Type::get_url_from_post( $invalid_post_id )
		);

		// Check URL scheme.
		add_filter(
			'home_url',
			static function ( $url ) {
				return set_url_scheme( $url, 'http' );
			},
			10
		);
		$this->assertEquals( 'http', wp_parse_url( AMP_Validated_URL_Post_Type::get_url_from_post( $invalid_post_id ), PHP_URL_SCHEME ) );
		add_filter(
			'home_url',
			static function ( $url ) {
				return set_url_scheme( $url, 'https' );
			},
			10
		);
		$this->assertEquals( 'https', wp_parse_url( AMP_Validated_URL_Post_Type::get_url_from_post( $invalid_post_id ), PHP_URL_SCHEME ) );
	}

	/**
	 * Test for store_validation_errors()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::store_validation_errors()
	 */
	public function test_store_validation_errors() {
		global $post;
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		AMP_Validation_Manager::init();
		$post = self::factory()->post->create_and_get();

		add_filter(
			'amp_validation_error_sanitized',
			static function( $sanitized, $error ) {
				if ( 'accepted' === $error['code'] ) {
					$sanitized = true;
				} elseif ( 'rejected' === $error['code'] ) {
					$sanitized = false;
				}
				return $sanitized;
			},
			10,
			2
		);

		$errors = [
			[
				'code'    => 'accepted',
				'sources' => [
					[
						'type' => 'plugin',
						'name' => 'amp',
						'evil' => '<script>\o/</script>', // Test slash preservation and kses suspension.
					],
				],
			],
			[
				'code'    => 'rejected',
				'evil'    => '<script>\o/</script>', // Test slash preservation and kses suspension.
				'sources' => [
					[
						'type' => 'theme',
						'name' => 'twentyseventeen',
					],
				],
			],
			[
				'code'    => 'rejected',
				'evil'    => '<script>document.write( \'<a href="#" target="_blank" rel="noopener noreferrer">test</a>\' );</script>', // Test protection against wp_targeted_link_rel JSON corruption.
				'sources' => [
					[
						'type' => 'theme',
						'name' => 'twentyseventeen',
					],
				],
			],
			[
				'code'    => 'new',
				'sources' => [
					[
						'type' => 'core',
						'name' => 'wp-includes',
					],
				],
			],
		];

		$invalid_url_post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			$errors,
			get_permalink( $post ),
			[
				'invalid_url_post' => 0,
			]
		);
		$this->assertNotInstanceOf( 'WP_Error', $invalid_url_post_id );
		$this->assertNotEquals( $invalid_url_post_id, $post->ID, 'Passing an empty invalid_url_post should not re-use the global $post ever.' );

		// Test resurrection from trash.
		wp_trash_post( $invalid_url_post_id );
		$this->assertEquals(
			$invalid_url_post_id,
			AMP_Validated_URL_Post_Type::store_validation_errors(
				$errors,
				get_permalink( $post ),
				[
					'queried_object' => [
						'type' => 'post',
						'id'   => $post->ID,
					],
				]
			)
		);
		$this->assertEquals( 'publish', get_post_status( $invalid_url_post_id ) );
		$this->assertEquals(
			[
				'type' => 'post',
				'id'   => $post->ID,
			],
			get_post_meta( $invalid_url_post_id, '_amp_queried_object', true )
		);

		// Test passing specific post to override the URL.
		$this->assertEquals(
			$invalid_url_post_id,
			AMP_Validated_URL_Post_Type::store_validation_errors(
				$errors,
				home_url( '/something/else/' ),
				[
					'invalid_url_post' => $invalid_url_post_id,
				]
			)
		);

		$this->assertEquals(
			home_url( '/something/else/', 'https' ),
			get_post( $invalid_url_post_id )->post_title
		);

		$stored_errors = AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( $invalid_url_post_id );
		$this->assertEquals(
			$errors,
			array_map(
				static function( $stored_error ) {
					return $stored_error['data'];
				},
				$stored_errors
			)
		);

		$error_groups = [
			AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
			AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
			AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
			AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
		];

		foreach ( $errors as $i => $error ) {
			$stored_error = $stored_errors[ $i ];

			$this->assertEquals( $error, $stored_error['data'] );

			$sourceless_error = $error;
			unset( $sourceless_error['sources'] );

			/**
			 * Term.
			 *
			 * @var WP_Term $term
			 */
			$term = $stored_error['term'];
			$this->assertEquals( $sourceless_error, json_decode( $term->description, true ) );

			$this->assertNotEmpty( get_term_meta( $term->term_id, 'created_date_gmt', true ) );
			$this->assertEquals( $error_groups[ $i ], $stored_error['term_status'] );
			$this->assertEquals( $error_groups[ $i ], $term->term_group );
		}
	}

	/** @covers AMP_Validated_URL_Post_Type::delete_stylesheets_postmeta_batch() */
	public function test_delete_stylesheets_postmeta_batch() {

		$old_post_id = self::factory()->post->create(
			[
				'post_type'     => 'post',
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '1 year ago' ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '1 year ago' ) ),
			]
		);
		add_post_meta( $old_post_id, AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY, [ 'Preserved!' ] );
		add_post_meta( $old_post_id, 'other', 'Also preserved!' );

		// Expect none to be deleted initially.
		$this->assertEquals( 0, AMP_Validated_URL_Post_Type::delete_stylesheets_postmeta_batch( 10, '1 week ago' ) );

		// Insert four weeks of validated URLs.
		$post_ids = [];
		for ( $days_ago = 1; $days_ago <= 28; $days_ago++ ) {
			$post_date = gmdate( 'Y-m-d H:i:s', strtotime( "$days_ago days ago" ) + 5 );
			$post_id   = AMP_Validated_URL_Post_Type::store_validation_errors(
				[],
				home_url( "/days-ago-$days_ago/" ),
				[
					'stylesheets'    => [ '/*...*/' ],
					'queried_object' => [
						'type' => 'post',
						'id'   => self::factory()->post->create(),
					],
				]
			);
			wp_update_post(
				[
					'ID'            => $post_id,
					'post_date_gmt' => $post_date,
					'post_date'     => $post_date,
				]
			);
			$post_ids[ $days_ago ] = $post_id;
		}

		// Verify that no data is removed if looking before the oldest post.
		$this->assertEquals( 0, AMP_Validated_URL_Post_Type::delete_stylesheets_postmeta_batch( 100, '1 month ago' ) );
		foreach ( $post_ids as $post_id ) {
			$this->assertNotEmpty( get_post_meta( $post_id, AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY ) );
			$this->assertNotEmpty( get_post_meta( $post_id, AMP_Validated_URL_Post_Type::QUERIED_OBJECT_POST_META_KEY ) );
		}

		// Delete just one post older than 3 weeks.
		$this->assertEquals( 1, AMP_Validated_URL_Post_Type::delete_stylesheets_postmeta_batch( 1, '3 weeks ago' ) );
		foreach ( $post_ids as $days_ago => $post_id ) {
			$this->assertNotEmpty( get_post_meta( $post_id, AMP_Validated_URL_Post_Type::QUERIED_OBJECT_POST_META_KEY ) );
			if ( $days_ago > 27 ) {
				$this->assertEmpty( get_post_meta( $post_id, AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY ), "Expected $days_ago days ago to be empty." );
			} else {
				$this->assertNotEmpty( get_post_meta( $post_id, AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY ), "Expected $days_ago days ago to not be empty." );
			}
		}

		// Delete everything older than 1 week, so that means 20 days of validated URLs since the 21st was deleted above .
		$this->assertEquals( 20, AMP_Validated_URL_Post_Type::delete_stylesheets_postmeta_batch( 100, '1 week ago' ) );
		foreach ( $post_ids as $days_ago => $post_id ) {
			$this->assertNotEmpty( get_post_meta( $post_id, AMP_Validated_URL_Post_Type::QUERIED_OBJECT_POST_META_KEY ) );
			if ( $days_ago > 7 ) {
				$this->assertEmpty( get_post_meta( $post_id, AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY ), "Expected $days_ago days ago to be empty." );
			} else {
				$this->assertNotEmpty( get_post_meta( $post_id, AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY ), "Expected $days_ago days ago to not be empty." );
			}
		}

		// Make sure other postmeta is retained.
		$this->assertEquals( [ 'Preserved!' ], get_post_meta( $old_post_id, AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY, true ) );
		$this->assertEquals( 'Also preserved!', get_post_meta( $old_post_id, 'other', true ) );
	}

	/**
	 * Test get_validated_environment().
	 *
	 * @covers \AMP_Validated_URL_Post_Type::get_validated_environment()
	 */
	public function test_get_validated_environment() {
		switch_theme( 'twentysixteen' );
		update_option( 'active_plugins', [ 'foo/foo.php', 'bar.php' ] );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		$old_env = AMP_Validated_URL_Post_Type::get_validated_environment();
		$this->assertArrayHasKey( 'theme', $old_env );
		$this->assertArrayHasKey( 'plugins', $old_env );
		$this->assertEquals( [ 'twentysixteen' => wp_get_theme( 'twentysixteen' )->get( 'Version' ) ], $old_env['theme'] );
		$this->assertEquals( [ Option::THEME_SUPPORT => AMP_Theme_Support::TRANSITIONAL_MODE_SLUG ], $old_env['options'] );

		switch_theme( 'twentyseventeen' );
		update_option( 'active_plugins', [ 'foo/foo.php', 'baz.php' ] );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		$new_env = AMP_Validated_URL_Post_Type::get_validated_environment();
		$this->assertNotEquals( $old_env, $new_env );
		$this->assertEquals( [ 'twentyseventeen' => wp_get_theme( 'twentyseventeen' )->get( 'Version' ) ], $new_env['theme'] );
		$this->assertEquals( [ Option::THEME_SUPPORT => AMP_Theme_Support::STANDARD_MODE_SLUG ], $new_env['options'] );
	}

	/**
	 * Test get_post_staleness method.
	 *
	 * @covers AMP_Validated_URL_Post_Type::get_post_staleness()
	 * @covers AMP_Validated_URL_Post_Type::get_validated_environment()
	 */
	public function test_get_post_staleness() {
		$error = [ 'code' => 'foo' ];
		switch_theme( 'twentysixteen' );
		update_option( 'active_plugins', [ 'foo/foo.php', 'bar.php' ] );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );

		$plugins = [
			'foo/foo.php' => [
				'Name'    => 'Foo',
				'Version' => '0.1',
			],
			'bar.php'     => [
				'Name'    => 'Bar',
				'Version' => '0.1',
			],
			'baz.php'     => [
				'Name'    => 'Baz',
				'Version' => '0.1',
			],
		];
		wp_cache_set( 'plugins', [ '' => $plugins ], 'plugins' );

		$invalid_url_post_id = AMP_Validated_URL_Post_Type::store_validation_errors( [ $error ], home_url( '/' ) );
		$this->assertInternalType( 'int', $invalid_url_post_id );
		$this->assertEmpty( AMP_Validated_URL_Post_Type::get_post_staleness( $invalid_url_post_id ) );

		// Test deactivating plugin and activating another.
		update_option( 'active_plugins', [ 'foo/foo.php', 'baz.php' ] );
		$staleness = AMP_Validated_URL_Post_Type::get_post_staleness( $invalid_url_post_id );
		$this->assertNotEmpty( $staleness );
		$this->assertArrayHasKey( 'plugins', $staleness );
		$this->assertArrayNotHasKey( 'theme', $staleness );
		$this->assertEqualSets( [ 'baz.php' ], $staleness['plugins']['new'] );
		$this->assertEqualSets( [ 'bar.php' ], $staleness['plugins']['old'] );
		$this->assertArrayNotHasKey( 'options', $staleness );

		// Test theme switch.
		switch_theme( 'twentyseventeen' );
		$next_staleness = AMP_Validated_URL_Post_Type::get_post_staleness( $invalid_url_post_id );
		$this->assertArrayHasKey( 'theme', $next_staleness );
		$this->assertEquals(
			[
				'old' => [ 'twentysixteen' ],
				'new' => [ 'twentyseventeen' ],
			],
			$next_staleness['theme']
		);
		$this->assertSame( $next_staleness['plugins'], $staleness['plugins'] );
		$this->assertArrayNotHasKey( 'options', $staleness );

		// Test updating plugin version, as well as the template mode.
		$plugins['foo/foo.php']['Version'] = '0.2';
		wp_cache_set( 'plugins', [ '' => $plugins ], 'plugins' );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		$last_staleness = AMP_Validated_URL_Post_Type::get_post_staleness( $invalid_url_post_id );
		$this->assertEqualSets( [ 'foo', 'baz.php' ], $last_staleness['plugins']['new'] );
		$this->assertEqualSets( [ 'foo', 'bar.php' ], $last_staleness['plugins']['old'] );
		$this->assertArrayHasKey( 'options', $last_staleness );
		$this->assertEquals( [ Option::THEME_SUPPORT => AMP_Theme_Support::TRANSITIONAL_MODE_SLUG ], $last_staleness['options'] );

		// Re-storing results updates freshness.
		AMP_Validated_URL_Post_Type::store_validation_errors( [ $error ], home_url( '/' ), $invalid_url_post_id );
		$this->assertEmpty( AMP_Validated_URL_Post_Type::get_post_staleness( $invalid_url_post_id ) );
	}

	/**
	 * Test for add_post_columns()
	 *
	 * @covers AMP_Validated_URL_Post_Type::add_post_columns()
	 */
	public function test_add_post_columns() {
		$initial_columns = [
			'cb' => '<input type="checkbox">',
		];
		$this->assertEquals(
			array_keys(
				array_merge(
					$initial_columns,
					[
						AMP_Validation_Error_Taxonomy::ERROR_STATUS => 'Status',
						AMP_Validation_Error_Taxonomy::FOUND_ELEMENTS_AND_ATTRIBUTES => 'Invalid',
						AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT => 'Sources',
						'css_usage' => 'CSS Usage',
					]
				)
			),
			array_keys( AMP_Validated_URL_Post_Type::add_post_columns( $initial_columns ) )
		);
	}

	/**
	 * Test for add_single_post_columns()
	 *
	 * @covers AMP_Validated_URL_Post_Type::add_single_post_columns()
	 */
	public function test_add_single_post_columns() {
		$this->assertEquals(
			[
				'cb'                          => '<input type="checkbox" />',
				'error_code'                  => 'Error',
				'status'                      => 'Markup Status<span class="dashicons dashicons-editor-help tooltip-button" tabindex="0"></span><div class="tooltip" hidden data-content="&lt;h3&gt;Markup Status&lt;/h3&gt;&lt;p&gt;When invalid markup is removed it will not block a URL from being served as AMP; the validation error will be sanitized, where the offending markup is stripped from the response to ensure AMP validity. If invalid AMP markup is kept, then URLs is occurs on will not be served as AMP pages.&lt;/p&gt;"></div>',
				'details'                     => 'Context<span class="dashicons dashicons-editor-help tooltip-button" tabindex="0"></span><div class="tooltip" hidden data-content="&lt;h3&gt;Context&lt;/h3&gt;&lt;p&gt;The parent element of where the error occurred.&lt;/p&gt;"></div>',
				'sources_with_invalid_output' => 'Sources',
				'error_type'                  => 'Type',
				'reviewed'                    => 'Reviewed<span class="dashicons dashicons-editor-help tooltip-button" tabindex="0"></span><div class="tooltip" hidden data-content="&lt;h3&gt;Reviewed&lt;/h3&gt;&lt;p&gt;Confirm that the action being taken on the invalid markup (causing a validation error) has been seen and approved.&lt;/p&gt;"></div>',
			],
			AMP_Validated_URL_Post_Type::add_single_post_columns()
		);
	}

	/**
	 * Gets the test data for test_output_custom_column().
	 *
	 * @return array $columns
	 */
	public function get_custom_columns() {
		$source = [
			'type' => 'plugin',
			'name' => 'AMP',
		];
		$errors = [
			[
				'code'      => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG,
				'node_name' => 'script',
				'sources'   => [ $source ],
			],
			[
				'code'      => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR,
				'node_name' => 'onclick',
				'sources'   => [ $source ],
			],
		];

		return [
			AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG => [
				AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT,
				'<strong class="source"><span class="dashicons dashicons-admin-plugins"></span>AMP</strong>',
				$errors,
			],
			'removed_attributes'    => [
				AMP_Validation_Error_Taxonomy::FOUND_ELEMENTS_AND_ATTRIBUTES,
				'onclick',
				$errors,
			],
			'sources_invalid_input' => [
				AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT,
				'AMP',
				$errors,
			],
		];
	}

	/**
	 * Test for output_custom_column()
	 *
	 * @dataProvider get_custom_columns
	 * @covers       AMP_Validated_URL_Post_Type::output_custom_column()
	 *
	 * @param string $column_name    The name of the column.
	 * @param string $expected_value The value that is expected to be present in the column markup.
	 * @param array  $errors         Errors.
	 */
	public function test_output_custom_column( $column_name, $expected_value, $errors ) {
		AMP_Validation_Manager::init();
		$invalid_url_post_id = AMP_Validated_URL_Post_Type::store_validation_errors( $errors, home_url( '/' ) );

		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'output_custom_column' ], [ $column_name, $invalid_url_post_id ] );
		$this->assertStringContains( $expected_value, $output );
	}

	/**
	 * Test for render_sources_column()
	 *
	 * @covers AMP_Validated_URL_Post_Type::render_sources_column()
	 */
	public function test_render_sources_column() {
		$theme_name    = 'foo-theme';
		$post_id       = 9876;
		$error_summary = [
			'removed_attributes'          => [
				'webkitallowfullscreen' => 1,
			],
			'removed_elements'            => [],
			'sources_with_invalid_output' => [
				'embed' => true,
				'hook'  => 'the_content',
				'theme' => [ $theme_name ],
			],
		];

		// If there is an embed and a theme source, this should only output the embed icon.
		$sources_column = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_sources_column' ], [ $error_summary['sources_with_invalid_output'], $post_id ] );
		$this->assertEquals( '<strong class="source"><span class="dashicons dashicons-wordpress-alt"></span>Embed</strong>', $sources_column );

		// If there is no embed source, but there is a theme, this should output the theme icon.
		unset( $error_summary['sources_with_invalid_output']['embed'] );
		$sources_column      = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_sources_column' ], [ $error_summary['sources_with_invalid_output'], $post_id ] );
		$expected_theme_icon = '<strong class="source"><span class="dashicons dashicons-admin-appearance"></span>' . $theme_name . '</strong>';
		$this->assertEquals( $expected_theme_icon, $sources_column );

		// If there is a plugin and theme source, this should output icons for both of them.
		$plugin_name = 'baz-plugin';
		$error_summary['sources_with_invalid_output']['plugin'] = [ $plugin_name ];
		$expected_plugin_icon                                   = '<strong class="source"><span class="dashicons dashicons-admin-plugins"></span>' . $plugin_name . '</strong>';
		unset( $error_summary['sources_with_invalid_output']['embed'] );
		$sources_column = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_sources_column' ], [ $error_summary['sources_with_invalid_output'], $post_id ] );
		$this->assertEquals( $expected_plugin_icon . $expected_theme_icon, $sources_column );

		// If there is a 'core' source, it should appear in the column output.
		$error_summary['sources_with_invalid_output']['core'] = [];
		$sources_column                                       = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_sources_column' ], [ $error_summary['sources_with_invalid_output'], $post_id ] );
		$this->assertStringContains( '<strong class="source"><span class="dashicons dashicons-wordpress-alt"></span>Other (0)</strong>', $sources_column );

		// Even if there is a hook in the sources, it should not appear in the column if there is any other source.
		$hook_name = 'wp_header';
		$error_summary['sources_with_invalid_output']['hook'] = [ $hook_name ];
		$sources_column                                       = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_sources_column' ], [ $error_summary['sources_with_invalid_output'], $post_id ] );
		$this->assertStringNotContains( $hook_name, $sources_column );

		// If a hook is the only source, it should appear in the column.
		$error_summary['sources_with_invalid_output'] = [ 'hook' => $hook_name ];
		$sources_column                               = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_sources_column' ], [ $error_summary['sources_with_invalid_output'], $post_id ] );
		$this->assertEquals( '<strong class="source"><span class="dashicons dashicons-wordpress-alt"></span>Hook: ' . $hook_name . '</strong>', $sources_column );

		// Content gets a translated name.
		$error_summary['sources_with_invalid_output'] = [ 'hook' => 'the_content' ];
		$sources_column                               = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_sources_column' ], [ $error_summary['sources_with_invalid_output'], $post_id ] );
		$this->assertEquals( '<strong class="source"><span class="dashicons dashicons-edit"></span>Content</strong>', $sources_column );

		// Blocks are listed separately, overriding Content.
		$error_summary['sources_with_invalid_output'] = [
			'hook'   => 'the_content',
			'blocks' => [ 'core/html' ],
		];
		$sources_column                               = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_sources_column' ], [ $error_summary['sources_with_invalid_output'], $post_id ] );
		$this->assertEquals( '<strong class="source"><span class="dashicons dashicons-edit"></span>Custom HTML</strong>', $sources_column );

		// If there's no source in 'sources_with_invalid_output', this should output the theme name.
		update_post_meta( $post_id, '_amp_validated_environment', [ 'theme' => $theme_name ] );
		$error_summary['sources_with_invalid_output'] = [];
		$sources_column                               = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_sources_column' ], [ $error_summary['sources_with_invalid_output'], $post_id ] );
		$this->assertEquals( '<div class="source"><span class="dashicons dashicons-admin-appearance"></span>' . $theme_name . ' (?)</div>', $sources_column );
	}

	/**
	 * Test for filter_bulk_actions()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::filter_bulk_actions()
	 */
	public function test_filter_bulk_actions() {
		$initial_action = [
			'edit'   => 'Edit',
			'trash'  => 'Trash',
			'delete' => 'Delete',
		];
		$actions        = AMP_Validated_URL_Post_Type::filter_bulk_actions( $initial_action );
		$this->assertArrayNotHasKey( 'edit', $actions );
		$this->assertEquals( 'Recheck', $actions[ AMP_Validated_URL_Post_Type::BULK_VALIDATE_ACTION ] );
		$this->assertArrayNotHasKey( 'trash', $actions );
		$this->assertEquals( 'Forget', $actions['delete'] );
	}

	/**
	 * Test for handle_bulk_action()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::handle_bulk_action()
	 */
	public function test_handle_bulk_action() {
		$this->accept_sanitization_by_default( false );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		AMP_Validation_Manager::init();

		$invalid_post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'foo' ],
			],
			home_url( '/' )
		);

		$initial_redirect = admin_url( 'plugins.php' );
		$items            = [ $invalid_post_id ];
		$urls_tested      = (string) count( $items );

		$_GET[ AMP_Validated_URL_Post_Type::URLS_TESTED ] = $urls_tested;

		// The action isn't correct, so the callback should return the URL unchanged.
		$this->assertEquals( $initial_redirect, AMP_Validated_URL_Post_Type::handle_bulk_action( $initial_redirect, 'trash', $items ) );

		$filter = function() {
			return [
				'body' => wp_json_encode(
					[
						'results' => array_map(
							static function( $error ) {
								return array_merge(
									compact( 'error' ),
									[ 'sanitized' => false ]
								);
							},
							$this->get_mock_errors()
						),
					]
				),
			];
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );
		$this->assertEquals(
			add_query_arg(
				[
					AMP_Validated_URL_Post_Type::URLS_TESTED      => $urls_tested,
					AMP_Validated_URL_Post_Type::REMAINING_ERRORS => count( $items ),
				],
				$initial_redirect
			),
			AMP_Validated_URL_Post_Type::handle_bulk_action( $initial_redirect, AMP_Validated_URL_Post_Type::BULK_VALIDATE_ACTION, $items )
		);
		remove_filter( 'pre_http_request', $filter, 10 );

		// Test error scenario.
		add_filter(
			'pre_http_request',
			static function() {
				return [
					'body' => '<html></html>',
				];
			}
		);
		$this->assertStringContains(
			'amp_validate_error=',
			AMP_Validated_URL_Post_Type::handle_bulk_action( $initial_redirect, AMP_Validated_URL_Post_Type::BULK_VALIDATE_ACTION, $items )
		);
	}

	/**
	 * Test for print_admin_notice()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::print_admin_notice()
	 * @covers \AMP_Validation_Manager::serialize_validation_error_messages()
	 * @covers \AMP_Validation_Manager::unserialize_validation_error_messages()
	 */
	public function test_print_admin_notice() {
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		AMP_Validation_Manager::init();

		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_admin_notice' ] );
		$this->assertEmpty( $output );

		$_GET['post_type'] = 'post';
		$output            = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_admin_notice' ] );
		$this->assertEmpty( $output );

		set_current_screen( 'edit.php' );
		get_current_screen()->post_type = AMP_Validated_URL_Post_Type::POST_TYPE_SLUG;

		$_GET[ AMP_Validated_URL_Post_Type::REMAINING_ERRORS ] = '1';
		$_GET[ AMP_Validated_URL_Post_Type::URLS_TESTED ]      = '1';
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_admin_notice' ] );
		$this->assertStringContains( 'The rechecked URL still has remaining invalid markup kept.', $output );

		$_GET[ AMP_Validated_URL_Post_Type::URLS_TESTED ] = '2';
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_admin_notice' ] );
		$this->assertStringContains( 'The rechecked URLs still have remaining invalid markup kept.', $output );

		$_GET[ AMP_Validated_URL_Post_Type::REMAINING_ERRORS ] = '0';
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_admin_notice' ] );
		$this->assertStringContains( 'The rechecked URLs are free of non-removed invalid markup.', $output );

		$_GET[ AMP_Validated_URL_Post_Type::URLS_TESTED ] = '1';
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_admin_notice' ] );
		$this->assertStringContains( 'The rechecked URL is free of non-removed invalid markup.', $output );

		$error_message              = 'Something <code>bad</code> happened!';
		$_GET['amp_validate_error'] = AMP_Validation_Manager::serialize_validation_error_messages( [ $error_message ] );
		$output                     = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_admin_notice' ] );
		$this->assertStringContains( $error_message, $output );

		unset( $GLOBALS['current_screen'] );
	}

	/**
	 * Test for handle_validate_request()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::handle_validate_request()
	 */
	public function test_handle_validate_request() {
		$this->accept_sanitization_by_default( false );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Validation_Manager::init();

		$post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'foo' ],
			],
			home_url( '/' )
		);

		$_REQUEST['_wpnonce'] = wp_create_nonce( AMP_Validated_URL_Post_Type::NONCE_ACTION );

		$exception = null;
		add_filter(
			'wp_redirect',
			static function( $url, $status ) {
				throw new Exception( $url, $status );
			},
			10,
			2
		);

		$filter = function() {
			return [
				'body' => wp_json_encode(
					[
						'results' => array_map(
							static function( $error ) {
								return array_merge(
									compact( 'error' ),
									[ 'sanitized' => false ]
								);
							},
							$this->get_mock_errors()
						),
					]
				),
			];
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$handle_validate_request = static function() {
			try {
				AMP_Validated_URL_Post_Type::handle_validate_request();
			} catch ( Exception $exception ) {
				return $exception;
			}
			return null;
		};

		// Test validating with missing args.
		$exception = $handle_validate_request();
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertEquals( 302, $exception->getCode() );
		$this->assertStringContains(
			'/edit.php?post_type=amp_validated_url&amp_validate_error=',
			$exception->getMessage()
		);
		unset( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Test validating for a non-valid post.
		$_GET['post'] = 1234567890;
		$exception    = $handle_validate_request();
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertEquals( 302, $exception->getCode() );
		$this->assertStringContains(
			'/edit.php?post_type=amp_validated_url&amp_validate_error=',
			$exception->getMessage()
		);
		unset( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Test validating for a non-valid post type.
		$_GET['post'] = self::factory()->post->create();
		$exception    = $handle_validate_request();
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertEquals( 302, $exception->getCode() );
		$this->assertStringContains(
			'/edit.php?post_type=amp_validated_url&amp_validate_error=',
			$exception->getMessage()
		);
		unset( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Verify that redirect is happening for a successful case.
		$_GET['post'] = $post_id;
		$exception    = $handle_validate_request();
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertEquals( 302, $exception->getCode() );
		$this->assertStringContains(
			sprintf( 'post.php?post=%s&action=edit&amp_urls_tested=', $post_id ),
			$exception->getMessage()
		);
		unset( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Test validating by URL.
		$_GET['url'] = home_url( '/' );
		$exception   = $handle_validate_request();
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertEquals( 302, $exception->getCode() );
		$this->assertStringEndsWith(
			sprintf( 'post.php?post=%s&action=edit&amp_urls_tested=1&amp_remaining_errors=2', $post_id ),
			$exception->getMessage()
		);

		// Test validating by URL which doesn't have a post already.
		$_GET['url'] = home_url( '/new-URL/' );
		$exception   = $handle_validate_request();
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertEquals( 302, $exception->getCode() );
		$this->assertStringEndsWith(
			'&action=edit&amp_urls_tested=1&amp_remaining_errors=2',
			$exception->getMessage()
		);

		// Test validating a bad URL.
		$_GET['url'] = 'http://badurl.example.com/';
		$exception   = $handle_validate_request();
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertEquals( 302, $exception->getCode() );
		$this->assertStringContains(
			'wp-admin/edit.php?post_type=amp_validated_url&amp_validate_error=',
			$exception->getMessage()
		);
	}

	/**
	 * Test for recheck_post()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::recheck_post()
	 */
	public function test_recheck_post() {
		AMP_Validation_Manager::init();

		$r = AMP_Validated_URL_Post_Type::recheck_post( 'nope' );
		$this->assertInstanceOf( 'WP_Error', $r );
		$this->assertEquals( 'missing_post', $r->get_error_code() );

		$r = AMP_Validated_URL_Post_Type::recheck_post( self::factory()->post->create() );
		$this->assertInstanceOf( 'WP_Error', $r );
		$this->assertEquals( 'missing_url', $r->get_error_code() );

		$invalid_url_post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'foo' ],
			],
			home_url( '/' )
		);
		add_filter(
			'pre_http_request',
			static function() {
				return [
					'body' => wp_json_encode(
						[
							'results' => [
								[
									'sanitized' => false,
									'error'     => [
										'code' => 'bar',
									],
								],
								[
									'sanitized' => false,
									'error'     => [
										'code' => 'baz',
									],
								],
							],
						]
					),
				];
			}
		);

		$r = AMP_Validated_URL_Post_Type::recheck_post( $invalid_url_post_id );
		$this->assertInternalType( 'array', $r );
		$this->assertCount( 2, $r );
		$this->assertEquals( 'bar', $r[0]['error']['code'] );
		$this->assertEquals( 'baz', $r[1]['error']['code'] );

		$errors = AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( $invalid_url_post_id );
		$this->assertCount( 2, $errors );
		foreach ( $errors as $i => $error ) {
			$this->assertEquals(
				$r[ $i ]['error'],
				$error['data']
			);
		}
	}

	/**
	 * Test for handle_validation_error_status_update()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::handle_validation_error_status_update()
	 */
	public function test_handle_validation_error_status_update() {
		global $post;
		AMP_Validation_Manager::init();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_REQUEST[ AMP_Validated_URL_Post_Type::UPDATE_POST_TERM_STATUS_ACTION . '_nonce' ] = wp_create_nonce( AMP_Validated_URL_Post_Type::UPDATE_POST_TERM_STATUS_ACTION );
		AMP_Validated_URL_Post_Type::handle_validation_error_status_update(); // No-op since no post.

		$errors = [
			// All statuses for errors should be updated.
			[
				'code'   => 'foo',
				'status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
			],
			[
				'code'   => 'bar',
				'status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
			],
			[
				'code'   => 'baz',
				'status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
			],
			[
				'code'   => 'buzz',
				'status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
			],
			// Except for this.
			[
				'code'   => 'status_should_not_be_updated',
				'status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
			],
		];

		$invalid_url_post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			$errors,
			home_url( '/' )
		);

		foreach ( $errors as $data ) {
			$term_data = AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term( $data );
			$term      = AMP_Validation_Error_Taxonomy::get_term( $term_data['slug'] );
			wp_update_term( $term->term_id, AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG, [ 'term_group' => $data['status'] ] );
		}

		add_filter(
			'pre_http_request',
			static function() use ( $errors ) {
				$results = array_map(
					static function ( $error ) {
						return [
							'sanitized' => true,
							'error'     => $error,
						];
					},
					$errors
				);

				return [
					'body' => wp_json_encode( compact( 'results' ) ),
				];
			}
		);

		$post = get_post( $invalid_url_post_id );

		$validation_errors = AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( $invalid_url_post_id );

		$_POST[ AMP_Validated_URL_Post_Type::VALIDATION_ERRORS_INPUT_KEY ] = [
			// Accepted and acknowledged.
			$validation_errors[0]['term']->slug => [
				AMP_Validation_Manager::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR     => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
				AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACKNOWLEDGE_ACTION => 'on',
			],
			// Accepted but not acknowledged.
			$validation_errors[1]['term']->slug => [
				AMP_Validation_Manager::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
			],
			// Rejected and acknowledged.
			$validation_errors[2]['term']->slug => [
				AMP_Validation_Manager::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR     => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
				AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACKNOWLEDGE_ACTION => 'on',
			],
			// Rejected but not acknowledged.
			$validation_errors[3]['term']->slug => [
				AMP_Validation_Manager::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
			],
			// Accepted but not acknowledged. Status should not be changed.
			$validation_errors[4]['term']->slug => [
				AMP_Validation_Manager::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
			],
		];

		add_filter(
			'wp_redirect',
			static function( $url, $status ) {
				throw new Exception( $url, $status );
			},
			10,
			2
		);
		$exception = null;
		try {
			AMP_Validated_URL_Post_Type::handle_validation_error_status_update();
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertEquals( 302, $exception->getCode() );
		// 4 out of the 5 validation error statuses should be updated, with 2 kept errors.
		$this->assertStringEndsWith( 'action=edit&amp_taxonomy_terms_updated=4&amp_remaining_errors=2', $exception->getMessage() );
	}

	/**
	 * Test for enqueue_edit_post_screen_scripts()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::enqueue_edit_post_screen_scripts()
	 */
	public function test_enqueue_edit_post_screen_scripts() {
		wp_enqueue_script( 'autosave' );
		set_current_screen( 'index.php' );
		AMP_Validated_URL_Post_Type::enqueue_edit_post_screen_scripts();
		$this->assertTrue( wp_script_is( 'autosave', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'amp-validated-url-post-edit-screen', 'enqueued' ) );

		global $pagenow;
		$pagenow = 'post.php';
		set_current_screen( AMP_Validated_URL_Post_Type::POST_TYPE_SLUG );
		AMP_Validated_URL_Post_Type::enqueue_edit_post_screen_scripts();
		$this->assertFalse( wp_script_is( 'autosave', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'amp-validated-url-post-edit-screen', 'enqueued' ) );
		$pagenow = null;
	}

	/**
	 * Test render_link_to_error_index_screen.
	 *
	 * @covers \AMP_Validated_URL_Post_Type::render_link_to_error_index_screen()
	 */
	public function test_render_link_to_error_index_screen() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		global $current_screen;
		set_current_screen( 'index.php' );
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_link_to_error_index_screen' ] );
		$this->assertEmpty( $output );

		set_current_screen( 'edit.php' );
		$current_screen->post_type = AMP_Validated_URL_Post_Type::POST_TYPE_SLUG;
		$output                    = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_link_to_error_index_screen' ] );
		$this->assertStringContains( 'View Error Index', $output );
	}

	/**
	 * Test for add_meta_boxes()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::add_meta_boxes()
	 */
	public function test_add_meta_boxes() {
		global $wp_meta_boxes;
		AMP_Validated_URL_Post_Type::add_admin_hooks();
		add_action(
			'add_meta_boxes',
			function () {
				add_meta_box( 'bogus', 'Bogus', '__return_empty_string', AMP_Validated_URL_Post_Type::POST_TYPE_SLUG );
			},
			100
		);
		do_action( 'add_meta_boxes' );
		$side_meta_box = $wp_meta_boxes[ AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ]['side']['default'][ AMP_Validated_URL_Post_Type::STATUS_META_BOX ];
		$this->assertEquals( AMP_Validated_URL_Post_Type::STATUS_META_BOX, $side_meta_box['id'] );
		$this->assertEquals( 'Status', $side_meta_box['title'] );
		$this->assertEquals(
			[
				self::TESTED_CLASS,
				'print_status_meta_box',
			],
			$side_meta_box['callback']
		);
		$this->assertEquals(
			[ '__back_compat_meta_box' => true ],
			$side_meta_box['args']
		);

		foreach ( $wp_meta_boxes[ AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ]['side'] as $context ) {
			$this->assertFalse( $context['submitdiv'] );
		}
		foreach ( $wp_meta_boxes[ AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ]['advanced'] as $context ) {
			$this->assertFalse( $context['bogus'] );
		}
	}

	/**
	 * Test for get_terms_per_page()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::get_terms_per_page()
	 */
	public function test_get_terms_per_page() {
		$initial_counts = [ 0, 22, 1000 ];

		// If 'post.php' === $pagenow, this method should return the same value, no matter what argument is passed to it.
		$GLOBALS['pagenow'] = 'post.php';
		foreach ( $initial_counts as $initial_count ) {
			$this->assertEquals(
				PHP_INT_MAX,
				AMP_Validated_URL_Post_Type::get_terms_per_page( $initial_count )
			);
		}

		// If 'post.php' !== $pagenow, this method should return the same value that is passed to it.
		$GLOBALS['pagenow'] = 'edit-tags.php';
		foreach ( $initial_counts as $initial_count ) {
			$this->assertEquals(
				$initial_count,
				AMP_Validated_URL_Post_Type::get_terms_per_page( $initial_count )
			);
		}
	}

	/**
	 * Test for add_taxonomy()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::add_taxonomy()
	 */
	public function test_add_taxonomy() {
		// The 'pagenow' value is incorrect, so this should not add the taxonomy.
		$GLOBALS['pagenow'] = 'edit.php';
		AMP_Validated_URL_Post_Type::add_taxonomy();
		$this->assertFalse( isset( $_REQUEST['taxonomy'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Though the 'pagenow' value is correct, the $_REQUEST['post'] is not set, and this should not add the taxonomy.
		$GLOBALS['pagenow'] = 'post.php';
		AMP_Validated_URL_Post_Type::add_taxonomy();
		$this->assertFalse( isset( $_REQUEST['taxonomy'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Though the $_REQUEST['post'] is set, it is for a post of the wrong type.
		$wrong_post_type  = self::factory()->post->create();
		$_REQUEST['post'] = $wrong_post_type;
		AMP_Validated_URL_Post_Type::add_taxonomy();
		$this->assertFalse( isset( $_REQUEST['taxonomy'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Now that the post type is correct, this should add the taxonomy to $_REQUEST.
		$correct_post_type = self::factory()->post->create( [ 'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );
		$_REQUEST['post']  = $correct_post_type;
		AMP_Validated_URL_Post_Type::add_taxonomy();
		$this->assertEquals( AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG, $_REQUEST['taxonomy'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Test for print_status_meta_box()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::print_status_meta_box()
	 */
	public function test_print_status_meta_box() {
		AMP_Validation_Manager::init();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$invalid_url_post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'foo' ],
			],
			home_url( '/' )
		);

		$post_storing_error = get_post( $invalid_url_post_id );

		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_status_meta_box' ], [ get_post( $invalid_url_post_id ) ] );

		$this->assertStringContains( date_i18n( 'M j, Y @ H:i', strtotime( $post_storing_error->post_date ) ), $output );
		$this->assertStringContains( 'Last checked:', $output );
		$this->assertStringContains( 'Forget', $output );
		$this->assertStringContains( esc_url( get_delete_post_link( $post_storing_error->ID, '', true ) ), $output );
		$this->assertStringContains( 'misc-pub-section', $output );
	}

	/**
	 * Test for render_single_url_list_table()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::render_single_url_list_table()
	 */
	public function test_render_single_url_list_table() {
		AMP_Validation_Error_Taxonomy::register();
		$post_correct_post_type = self::factory()->post->create_and_get( [ 'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );
		$post_wrong_post_type   = self::factory()->post->create_and_get( [ 'post_type' => 'page' ] );
		$GLOBALS['hook_suffix'] = 'post.php';
		$this->go_to( admin_url( 'post.php' ) );
		set_current_screen( 'post.php' );
		$GLOBALS['current_screen']->taxonomy = AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG;

		// If the post type is wrong, so the conditional should be false, and this should not echo anything.
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_single_url_list_table' ], [ $post_wrong_post_type ] );
		$this->assertEmpty( $output );

		// Now that the current user has permissions, this should output the correct markup.
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_single_url_list_table' ], [ $post_correct_post_type ] );
		$this->assertStringContains( '<form class="search-form wp-clearfix" method="get">', $output );
		$this->assertStringContains( '<div id="remove-keep-buttons" class="hidden">', $output );
		$this->assertStringContains( '<button type="button" class="button action remove">', $output );
		$this->assertStringContains( '<button type="button" class="button action keep">', $output );
	}

	/**
	 * Test for print_url_as_title()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::print_url_as_title()
	 */
	public function test_print_url_as_title() {
		$post_wrong_post_type = self::factory()->post->create_and_get();

		// The $post has the wrong post type, so the method should exit without echoing anything.
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_url_as_title' ], [ $post_wrong_post_type ] );
		$this->assertEmpty( $output );

		// The post type is correct, but it doesn't have a validation URL associated with it, so this shouldn't output anything.
		$post_correct_post_type = self::factory()->post->create_and_get(
			[
				'post-type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
			]
		);
		$output                 = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_url_as_title' ], [ $post_correct_post_type ] );
		$this->assertEmpty( $output );

		// The post has the correct type and a validation URL in the title, so this should output markup.
		$post_correct_post_type = self::factory()->post->create_and_get(
			[
				'post_type'  => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
				'post_title' => home_url(),
			]
		);
		$output                 = get_echo( [ 'AMP_Validated_URL_Post_Type', 'print_url_as_title' ], [ $post_correct_post_type ] );
		$this->assertStringContains( '<h2 class="amp-validated-url">', $output );
		$this->assertStringContains( home_url(), $output );
	}

	/**
	 * Test for filter_the_title_in_post_list_table()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::filter_the_title_in_post_list_table()
	 */
	public function test_filter_the_title_in_post_list_table() {
		global $current_screen;
		$post  = self::factory()->post->create_and_get();
		$title = 'https://example.com/baz';
		set_current_screen( 'front' );

		// The first conditional isn't true yet, so $title should be unchanged.
		$this->assertEquals( $title, AMP_Validated_URL_Post_Type::filter_the_title_in_post_list_table( $title, $post->ID ) );

		/*
		 * The first conditional still isn't true yet, as the $post->post_type isn't correct.
		 * So this should again return $ttile unchanged.
		 */
		set_current_screen( 'edit.php' );
		$current_screen->post_type = AMP_Validated_URL_Post_Type::POST_TYPE_SLUG;
		$this->assertEquals( $title, AMP_Validated_URL_Post_Type::filter_the_title_in_post_list_table( $title, $post->ID ) );

		// The conditional should be true, and this should return the filtered $title.
		$post_correct_post_type = self::factory()->post->create_and_get(
			[
				'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
			]
		);
		$this->assertEquals( '/baz', AMP_Validated_URL_Post_Type::filter_the_title_in_post_list_table( $title, $post_correct_post_type ) );
	}

	/**
	 * Test render_post_filters.
	 *
	 * @covers \AMP_Validated_URL_Post_Type::render_post_filters()
	 */
	public function test_render_post_filters() {
		set_current_screen( 'edit.php' );
		AMP_Validated_URL_Post_Type::register();
		AMP_Validation_Error_Taxonomy::register();

		$number_of_new_errors     = 20;
		$number_of_total_rejected = 24;
		$number_of_total_accepted = 16;

		for ( $i = 0; $i < 40; $i++ ) {
			$invalid_url_post      = self::factory()->post->create( [ 'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );
			$validation_error_term = self::factory()->term->create(
				[
					'taxonomy'    => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
					'description' => wp_json_encode( array_merge( [ 'code' => 'test' ], compact( 'i' ) ) ),
				]
			);
			if ( $i < 9 ) {
				$status = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS;
			} elseif ( $i < 20 ) {
				$status = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS;
			} elseif ( $i < 35 ) {
				$status = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS;
			} else {
				$status = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS;
			}
			wp_update_term(
				$validation_error_term,
				AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
				[
					'term_group' => $status,
				]
			);

			// Associate the validation error term with a URL.
			wp_set_post_terms(
				$invalid_url_post,
				[ $validation_error_term ],
				AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG
			);

		}

		$correct_post_type             = AMP_Validated_URL_Post_Type::POST_TYPE_SLUG;
		$wrong_post_type               = 'page';
		$correct_which_second_argument = 'top';
		$wrong_which_second_argument   = 'bottom';

		// This has an incorrect post type as the first argument, so it should not output anything.
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_post_filters' ], [ $wrong_post_type, $correct_which_second_argument ] );
		$this->assertEmpty( $output );

		// This has an incorrect second argument, so again it should not output anything.
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_post_filters' ], [ $correct_post_type, $wrong_which_second_argument ] );
		$this->assertEmpty( $output );

		// This is now on the invalid URL post type edit.php screen, so it should output a <select> element.
		$output = get_echo( [ 'AMP_Validated_URL_Post_Type', 'render_post_filters' ], [ $correct_post_type, $correct_which_second_argument ] );
		$this->assertStringContains(
			sprintf( 'With unreviewed errors <span class="count">(%d)</span>', $number_of_new_errors ),
			$output
		);
		$this->assertStringContains(
			sprintf( 'With kept markup <span class="count">(%d)</span>', $number_of_total_rejected ),
			$output
		);
		$this->assertStringContains(
			sprintf( 'With removed markup <span class="count">(%d)</span>', $number_of_total_accepted ),
			$output
		);
	}

	/**
	 * Test for get_recheck_url()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::get_recheck_url()
	 */
	public function test_get_recheck_url() {
		AMP_Validation_Manager::init();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$post_id = AMP_Validated_URL_Post_Type::store_validation_errors( $this->get_mock_errors(), home_url( '/' ) );
		$link    = AMP_Validated_URL_Post_Type::get_recheck_url( get_post( $post_id ) );
		$this->assertStringContains( AMP_Validated_URL_Post_Type::VALIDATE_ACTION, $link );
		$this->assertStringContains( wp_create_nonce( AMP_Validated_URL_Post_Type::NONCE_ACTION ), $link );
	}

	/**
	 * Test for get_validated_url_title()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::get_validated_url_title()
	 */
	public function test_get_validated_url_title() {
		$meta_key               = '_amp_queried_object';
		$test_post              = self::factory()->post->create_and_get();
		$amp_validated_url_post = self::factory()->post->create_and_get( [ 'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );

		update_post_meta(
			$amp_validated_url_post->ID,
			$meta_key,
			[
				'type' => 'post',
				'id'   => $test_post->ID,
			]
		);
		$this->assertEquals(
			$test_post->post_title,
			AMP_Validated_URL_Post_Type::get_validated_url_title( $amp_validated_url_post )
		);

		// If the URL with validation error(s) is a term, this should return the term name.
		$term = self::factory()->term->create_and_get();
		update_post_meta(
			$amp_validated_url_post->ID,
			$meta_key,
			[
				'type' => 'term',
				'id'   => $term->term_id,
			]
		);
		$this->assertEquals(
			$term->name,
			AMP_Validated_URL_Post_Type::get_validated_url_title( $amp_validated_url_post )
		);

		// If the URL with validation error(s) is for a user (author), this should return the author's name.
		$user = self::factory()->user->create_and_get();
		update_post_meta(
			$amp_validated_url_post->ID,
			$meta_key,
			[
				'type' => 'user',
				'id'   => $user->ID,
			]
		);
		$this->assertEquals(
			$user->display_name,
			AMP_Validated_URL_Post_Type::get_validated_url_title( $amp_validated_url_post )
		);
	}

	/**
	 * Test for filter_post_row_actions()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::filter_post_row_actions()
	 */
	public function test_filter_post_row_actions() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		AMP_Validation_Manager::init();

		$validated_url   = home_url( '/' );
		$invalid_post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'foo' ],
			],
			$validated_url
		);

		$initial_actions = [
			'trash' => sprintf( '<a href="%s" class="submitdelete" aria-label="Trash &#8220;%s&#8221;">Trash</a>', get_delete_post_link( $invalid_post_id ), $validated_url ),
		];

		$this->assertEquals( $initial_actions, AMP_Validated_URL_Post_Type::filter_post_row_actions( $initial_actions, self::factory()->post->create_and_get() ) );

		$actions = AMP_Validated_URL_Post_Type::filter_post_row_actions( $initial_actions, get_post( $invalid_post_id ) );
		$this->assertArrayNotHasKey( 'inline hide-if-no-js', $actions );
		$this->assertArrayHasKey( 'view', $actions );
		$this->assertArrayHasKey( AMP_Validated_URL_Post_Type::VALIDATE_ACTION, $actions );
		$this->assertArrayNotHasKey( 'trash', $actions );
		$this->assertArrayHasKey( 'delete', $actions );
		$this->assertStringNotContains( 'Trash', $actions['delete'] );
		$this->assertStringContains( 'Forget', $actions['delete'] );

		$this->assertEquals( [], AMP_Validated_URL_Post_Type::filter_post_row_actions( [], null ) );

		$actions = [
			'trash'  => '',
			'delete' => '',
		];

		$post = self::factory()->post->create_and_get(
			[
				'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
				'title'     => 'My Post',
			]
		);

		$filtered_actions = AMP_Validated_URL_Post_Type::filter_post_row_actions( $actions, $post );

		$this->assertArrayNotHasKey( 'trash', $filtered_actions );
		$this->assertArrayHasKey( 'delete', $filtered_actions );
		$this->assertStringContains( 'Forget</a>', $filtered_actions['delete'] );

	}

	/**
	 * Test for filter_table_views()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::filter_table_views()
	 */
	public function test_filter_table_views() {
		$this->assertEquals( [], AMP_Validated_URL_Post_Type::filter_table_views( [] ) );

		$views = [
			'trash' => 'Trash',
		];

		$filtered_views = AMP_Validated_URL_Post_Type::filter_table_views( $views );

		$this->assertEquals( 'Forgotten', $filtered_views['trash'] );
	}

	/**
	 * Test for filter_bulk_post_updated_messages()
	 *
	 * @covers \AMP_Validated_URL_Post_Type::filter_bulk_post_updated_messages()
	 */
	public function test_filter_bulk_post_updated_messages() {
		set_current_screen( 'index.php' );

		$this->assertEquals( [], AMP_Validated_URL_Post_Type::filter_bulk_post_updated_messages( [], [] ) );

		set_current_screen( 'edit.php' );
		get_current_screen()->id = sprintf( 'edit-%s', AMP_Validated_URL_Post_Type::POST_TYPE_SLUG );

		$messages = [
			'post' => [],
		];

		$filtered_messages = AMP_Validated_URL_Post_Type::filter_bulk_post_updated_messages(
			$messages,
			[
				'deleted'   => 1,
				'trashed'   => 99,
				'untrashed' => 99,
			]
		);

		$this->assertEquals( '%s validated URL forgotten.', $filtered_messages['post']['deleted'] );
		$this->assertEquals( '%s validated URLs forgotten.', $filtered_messages['post']['trashed'] );
		$this->assertEquals( '%s validated URLs unforgotten.', $filtered_messages['post']['untrashed'] );
	}

	/**
	 * Gets mock errors for tests.
	 *
	 * @return array $errors[][] {
	 *     The data of the validation errors.
	 *
	 *     @type string    $code        Error code.
	 *     @type string    $node_name   Name of removed node.
	 *     @type string    $parent_name Name of parent node.
	 *     @type array[][] $sources     Source data, including plugins and themes.
	 * }
	 */
	public function get_mock_errors() {
		return [
			[
				'code'            => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG,
				'node_name'       => 'script',
				'parent_name'     => 'div',
				'node_attributes' => [],
				'sources'         => [
					[
						'type' => 'plugin',
						'name' => 'amp',
					],
				],
			],
			[
				'code'               => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR,
				'node_name'          => 'onclick',
				'parent_name'        => 'div',
				'element_attributes' => [
					'onclick' => '',
				],
				'sources'            => [
					[
						'type' => 'plugin',
						'name' => 'amp',
					],
				],
			],
		];
	}

	/**
	 * Test that the code ensures other plugins won't mess up the validation URL action links in the post list table.
	 */
	public function test_post_row_actions_filter() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Validated_URL_Post_Type::add_admin_hooks();

		$post = self::factory()->post->create_and_get(
			[
				'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
				'status'    => 'publish',
			]
		);

		add_filter(
			'post_row_actions',
			static function ( $actions, $post ) {
				$actions['edit'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( get_edit_post_link( $post ) ),
					'Unwanted Edit Action'
				);

				$actions['other_action'] = sprintf(
					'<a href="%s">%s</a>',
					'https://example.com',
					'Unwanted Other Action'
				);

				return $actions;
			},
			10,
			2
		);

		$initial_actions = [
			'trash' => sprintf( '<a href="%s">Trash</a>', get_delete_post_link( $post->ID ) ),
		];

		$actions = apply_filters( 'post_row_actions', $initial_actions, $post );

		$this->assertInternalType( 'array', $actions );
		$this->assertArrayHasKey( 'edit', $actions );
		$this->assertArrayHasKey( 'view', $actions );
		$this->assertArrayHasKey( 'delete', $actions );
		$this->assertArrayHasKey( 'amp_validate', $actions );
		$this->assertArrayNotHasKey( 'other_action', $actions );

		$this->assertStringContains( __( 'Details', 'amp' ), $actions['edit'] );
		$this->assertStringNotContains( 'Unwanted Edit Action', $actions['edit'] );
	}
}

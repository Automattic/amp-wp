<?php
/**
 * Tests for AMP_Validation_Error_Taxonomy class.
 *
 * @package AMP
 */

/**
 * Tests for AMP_Validation_Error_Taxonomy class.
 *
 * @covers AMP_Validation_Error_Taxonomy
 */
class Test_AMP_Validation_Error_Taxonomy extends WP_UnitTestCase {

	use AMP_Test_HandleValidation;

	/**
	 * The tested class.
	 *
	 * @var string
	 */
	const TESTED_CLASS = 'AMP_Validation_Error_Taxonomy';

	/**
	 * Resets the state after each test method.
	 */
	public function tearDown() {
		$_REQUEST = [];
		$_GET     = [];
		remove_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::read_theme_support();
		remove_filter( 'amp_validation_error_sanitized', '__return_true' );
		remove_all_filters( 'amp_validation_error_sanitized' );
		remove_all_filters( 'terms_clauses' );
		AMP_Validation_Manager::$validation_error_status_overrides = [];
		parent::tearDown();
	}

	/**
	 * Test register.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::register()
	 */
	public function test_register() {
		global $wp_taxonomies;
		add_theme_support( AMP_Theme_Support::SLUG );
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );

		AMP_Validation_Error_Taxonomy::register();
		$taxonomy_object = $wp_taxonomies[ AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ];

		$this->assertFalse( $taxonomy_object->public );
		$this->assertTrue( $taxonomy_object->show_ui );
		$this->assertFalse( $taxonomy_object->show_tagcloud );
		$this->assertFalse( $taxonomy_object->show_in_quick_edit );
		$this->assertFalse( $taxonomy_object->hierarchical );
		$this->assertTrue( $taxonomy_object->show_in_menu );
		$this->assertFalse( $taxonomy_object->meta_box_cb );
		$this->assertEquals( 'AMP Validation Error Index', $taxonomy_object->label );
		$this->assertEquals( 'do_not_allow', $taxonomy_object->cap->assign_terms );
		$this->assertEquals( [ AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ], $taxonomy_object->object_type );

		$labels = $taxonomy_object->labels;
		$this->assertEquals( 'AMP Validation Error Index', $labels->name );
		$this->assertEquals( 'AMP Validation Error', $labels->singular_name );
		$this->assertEquals( 'Search AMP Validation Errors', $labels->search_items );
		$this->assertEquals( 'All AMP Validation Errors', $labels->all_items );
		$this->assertEquals( 'Edit AMP Validation Error', $labels->edit_item );
		$this->assertEquals( 'Update AMP Validation Error', $labels->update_item );
		$this->assertEquals( 'Error Index', $labels->menu_name );
		$this->assertEquals( 'Back to AMP Validation Errors', $labels->back_to_items );
		$this->assertEquals( 'Frequent Validation Errors', $labels->popular_items );
		$this->assertEquals( 'View Validation Error', $labels->view_item );
		$this->assertEquals( 'Add New Validation Error', $labels->add_new_item );
		$this->assertEquals( 'New Validation Error Hash', $labels->new_item_name );
		$this->assertEquals( 'No validation errors found.', $labels->not_found );
		$this->assertEquals( 'Validation Error', $labels->no_terms );
		$this->assertEquals( 'Validation errors navigation', $labels->items_list_navigation );
		$this->assertEquals( 'Validation errors list', $labels->items_list );
		$this->assertEquals( 'Most Used Validation Errors', $labels->most_used );
	}

	/**
	 * Test get_term.
	 *
	 * @covers AMP_Validation_Error_Taxonomy::get_term()
	 */
	public function test_get_term() {
		$foo_error = [ 'code' => 'foo' ];
		$bar_error = [ 'code' => 'bar' ];
		AMP_Validated_URL_Post_Type::store_validation_errors(
			[ $foo_error, $bar_error ],
			home_url( '/' )
		);
		$foo_term_data = AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term( $foo_error );

		$term = AMP_Validation_Error_Taxonomy::get_term( $foo_error );
		$this->assertEquals( $term, AMP_Validation_Error_Taxonomy::get_term( $foo_term_data['slug'] ) );
		$this->assertEquals( $foo_error, json_decode( $term->description, true ) );
	}

	/**
	 * Test delete_empty_terms.
	 *
	 * @covers AMP_Validation_Error_Taxonomy::delete_empty_terms()
	 */
	public function test_delete_empty_terms() {
		$this->assertEquals( 0, AMP_Validation_Error_Taxonomy::get_validation_error_count() );
		$this->assertEquals( 0, AMP_Validation_Error_Taxonomy::delete_empty_terms() );

		$post_id_1 = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'foo' ],
				[ 'code' => 'bar' ],
				[ 'code' => 'baz' ],
			],
			home_url( '/1' )
		);
		$post_id_2 = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'foo' ],
			],
			home_url( '/2' )
		);
		$post_id_3 = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'quux' ],
			],
			home_url( '/3' )
		);

		$this->assertEquals( 4, AMP_Validation_Error_Taxonomy::get_validation_error_count() );

		wp_delete_post( $post_id_3, true );

		$this->assertEquals( 4, AMP_Validation_Error_Taxonomy::get_validation_error_count() );
		$this->assertEquals( 1, AMP_Validation_Error_Taxonomy::delete_empty_terms() );
		$this->assertEquals( 3, AMP_Validation_Error_Taxonomy::get_validation_error_count() );

		wp_delete_post( $post_id_1, true );
		$this->assertEquals( 3, AMP_Validation_Error_Taxonomy::get_validation_error_count() );
		$this->assertEquals( 2, AMP_Validation_Error_Taxonomy::delete_empty_terms() );

		wp_delete_post( $post_id_2, true );
		$this->assertEquals( 1, AMP_Validation_Error_Taxonomy::get_validation_error_count() );
		$this->assertEquals( 1, AMP_Validation_Error_Taxonomy::delete_empty_terms() );
		$this->assertEquals( 0, AMP_Validation_Error_Taxonomy::get_validation_error_count() );
	}

	/**
	 * Test sanitize_term_status.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::sanitize_term_status()
	 */
	public function test_sanitize_term_status() {
		$this->assertNull( AMP_Validation_Error_Taxonomy::sanitize_term_status( '' ) );
		$this->assertNull( AMP_Validation_Error_Taxonomy::sanitize_term_status( false ) );
		$this->assertNull( AMP_Validation_Error_Taxonomy::sanitize_term_status( null ) );
		$this->assertSame( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS, AMP_Validation_Error_Taxonomy::sanitize_term_status( '0' ) );
		$this->assertSame( [], AMP_Validation_Error_Taxonomy::sanitize_term_status( '', [ 'multiple' => true ] ) );
		$this->assertNull( AMP_Validation_Error_Taxonomy::sanitize_term_status( '100' ) );
		$this->assertSame( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS, AMP_Validation_Error_Taxonomy::sanitize_term_status( (string) AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS ) );
		$this->assertSame( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS, AMP_Validation_Error_Taxonomy::sanitize_term_status( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS ) );
		$this->assertSame( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS, AMP_Validation_Error_Taxonomy::sanitize_term_status( (string) AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS ) );
		$this->assertSame( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS, AMP_Validation_Error_Taxonomy::sanitize_term_status( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS, [ 'multiple' => false ] ) );

		$this->assertEquals(
			[
				AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
				AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
			],
			AMP_Validation_Error_Taxonomy::sanitize_term_status(
				implode(
					',',
					[
						AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
						AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
						121930,
					]
				),
				[ 'multiple' => true ]
			)
		);
	}

	/**
	 * Test prepare_term_group_in_sql.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::prepare_term_group_in_sql()
	 */
	public function test_prepare_term_group_in_sql() {
		$this->assertEquals( 'IN ( 1, 2, 3 )', AMP_Validation_Error_Taxonomy::prepare_term_group_in_sql( [ 1, 2, 3 ] ) );
		$this->assertEquals( 'IN ( 0 )', AMP_Validation_Error_Taxonomy::prepare_term_group_in_sql( [ '"bad"' ] ) );
	}

	/**
	 * Test prepare_validation_error_taxonomy_term.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term()
	 */
	public function test_prepare_validation_error_taxonomy_term() {
		$error              = $this->get_mock_error();
		$sources            = [
			[
				'type' => 'plugin',
				'name' => 'baz',
			],
		];
		$error_with_sources = array_merge( $error, compact( 'sources' ) );
		ksort( $error );

		$description = wp_json_encode( $error );
		$term_slug   = md5( $description );
		$this->assertEquals(
			AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term( $error_with_sources ),
			[
				'slug'        => $term_slug,
				'name'        => $term_slug,
				'description' => $description,
			]
		);
	}

	/**
	 * Test is_validation_error_sanitized and get_validation_error_sanitization.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::is_validation_error_sanitized()
	 * @covers \AMP_Validation_Error_Taxonomy::get_validation_error_sanitization()
	 */
	public function test_is_validation_error_sanitized_and_get_validation_error_sanitization() {

		// New accepted.
		$this->accept_sanitization_by_default( true );
		$error_foo = array_merge(
			$this->get_mock_error(),
			[ 'foo' => 1 ]
		);
		AMP_Validated_URL_Post_Type::store_validation_errors(
			[ $error_foo ],
			home_url( '/foo' )
		);
		$this->assertTrue( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_foo ) );
		$this->assertEquals(
			[
				'forced'      => false,
				'status'      => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
				'term_status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
			],
			AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $error_foo )
		);

		// New rejected.
		$this->accept_sanitization_by_default( false );
		$error_bar = array_merge(
			$this->get_mock_error(),
			[ 'bar' => 1 ]
		);
		AMP_Validated_URL_Post_Type::store_validation_errors(
			[ $error_bar ],
			home_url( '/bar' )
		);
		$this->assertFalse( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_bar ) );
		$this->assertEquals(
			[
				'forced'      => false,
				'status'      => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
				'term_status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
			],
			AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $error_bar )
		);

		// New accepted.
		$this->accept_sanitization_by_default( true );
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				AMP_Theme_Support::PAIRED_FLAG => false,
			]
		);
		$this->assertTrue( amp_is_canonical() );
		$this->assertTrue( AMP_Validation_Manager::is_sanitization_auto_accepted() );
		$error_baz = array_merge(
			$this->get_mock_error(),
			[ 'baz' => 1 ]
		);
		AMP_Validated_URL_Post_Type::store_validation_errors(
			[ $error_baz ],
			home_url( '/baz' )
		);
		$this->assertTrue( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_baz ) );
		$this->assertEquals(
			[
				'forced'      => false,
				'status'      => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
				'term_status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
			],
			AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $error_baz )
		);

		// New accepted => Ack rejected.
		$this->assertTrue( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_foo ) );
		$term_data = AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term( $error_foo );
		$term      = get_term_by( 'slug', $term_data['slug'], AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG );
		wp_update_term(
			$term->term_id,
			AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
			[
				'term_group' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
			]
		);
		$this->assertFalse( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_foo ) );
		$this->assertEquals(
			[
				'forced'      => false,
				'status'      => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
				'term_status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
			],
			AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $error_foo )
		);

		// New rejected => Ack accepted.
		$this->assertFalse( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_bar ) );
		$term_data = AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term( $error_bar );
		$term      = get_term_by( 'slug', $term_data['slug'], AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG );
		wp_update_term(
			$term->term_id,
			AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
			[
				'term_group' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
			]
		);
		$this->assertTrue( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_bar ) );
		$this->assertEquals(
			[
				'forced'      => false,
				'status'      => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
				'term_status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
			],
			AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $error_bar )
		);

		// Ack rejected => Ack accepted (forcibly by filter). The next time the URL will be re-checked, this validation error will be omitted.
		$this->assertFalse( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_foo ) );
		add_filter( 'amp_validation_error_sanitized', '__return_true' );
		$this->assertTrue( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_foo ) );
		$this->assertEquals(
			[
				'forced'      => 'with_filter',
				'status'      => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
				'term_status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
			],
			AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $error_foo )
		);
		remove_filter( 'amp_validation_error_sanitized', '__return_true' );

		// Ack accepted => Ack rejected (forcibly by preview).
		$this->assertTrue( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_bar ) );
		$term_data = AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term( $error_bar );
		AMP_Validation_Manager::$validation_error_status_overrides[ $term_data['slug'] ] = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS;
		$this->assertFalse( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_bar ) );
		$this->assertEquals(
			[
				'forced'      => 'with_preview',
				'status'      => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
				'term_status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
			],
			AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $error_bar )
		);

		// New rejected => Ack rejected, as the query var should force this to be rejected.
		$_GET[ AMP_Debug::AMP_FLAGS_QUERY_VAR ][ AMP_Debug::REJECT_ALL_VALIDATION_ERRORS_QUERY_VAR ] = '1';
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Validation_Error_Taxonomy::register();
		$this->assertFalse( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error_foo ) );
		$this->assertEquals(
			[
				'forced'      => 'with_filter',
				'status'      => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
				'term_status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
			],
			AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $error_foo )
		);
		$_GET = [];

		// The query var should force the excessive_css error to be accepted.
		$excessive_css_error = array_merge(
			$this->get_mock_error(),
			[
				'example' => 1,
				'code'    => 'excessive_css',
			]
		);
		AMP_Validated_URL_Post_Type::store_validation_errors(
			[ $excessive_css_error ],
			home_url( '/example' )
		);
		$_GET[ AMP_Debug::AMP_FLAGS_QUERY_VAR ][ AMP_Debug::ACCEPT_EXCESSIVE_CSS_ERROR_QUERY_VAR ] = 'true';
		AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term( $error_bar );
		$this->assertTrue( AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $excessive_css_error ) );
		$this->assertEquals(
			[
				'forced'      => 'with_filter',
				'status'      => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
				'term_status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
			],
			AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $excessive_css_error )
		);
	}

	/**
	 * Test accept_validation_errors.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::accept_validation_errors()
	 */
	public function test_accept_validation_errors() {
		$error = $this->get_mock_error();
		AMP_Validation_Error_Taxonomy::accept_validation_errors( [] );
		$this->assertNull( apply_filters( 'amp_validation_error_sanitized', null, $error ) );
		remove_all_filters( 'amp_validation_error_sanitized' );

		AMP_Validation_Error_Taxonomy::accept_validation_errors( [ AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_AT_RULE => true ] );
		$this->assertTrue( apply_filters( 'amp_validation_error_sanitized', null, $error ) );
		remove_all_filters( 'amp_validation_error_sanitized' );

		AMP_Validation_Error_Taxonomy::accept_validation_errors( true );
		$this->assertTrue( apply_filters( 'amp_validation_error_sanitized', null, $error ) );
		remove_all_filters( 'amp_validation_error_sanitized' );
	}

	/**
	 * Test is_array_subset.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::is_array_subset()
	 */
	public function test_is_array_subset() {
		$error = $this->get_mock_error();
		$this->assertTrue( AMP_Validation_Error_Taxonomy::is_array_subset( $error, $error ) );

		// The superset argument now has an extra key and value, but the superset still has all of the values of the subset.
		$this->assertTrue( AMP_Validation_Error_Taxonomy::is_array_subset( array_merge( $error, [ 'foo' => 'bar' ] ), $error ) );

		// The subset has a key and value that the superset doesn't have, so this should be false.
		$this->assertFalse( AMP_Validation_Error_Taxonomy::is_array_subset( $error, array_merge( $error, [ 'foo' => 'bar' ] ) ) );

		$sources = [
			[
				'type' => 'plugin',
				'name' => 'foo',
			],
			[
				'type' => 'theme',
				'name' => 'baz',
			],
		];

		/**
		 * Add only the plugin sources to the superset, but all of the sources to the subset.
		 * This should make is_array_subset() false, as the superset does not have all of the values of the subset.
		 */
		$superset = array_merge( $error, [ 'sources' => [ $sources[0] ] ] );
		$subset   = array_merge( $error, compact( 'sources' ) );
		$this->assertFalse( AMP_Validation_Error_Taxonomy::is_array_subset( $superset, $subset ) );
	}

	/**
	 * Test get_validation_error_count.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::get_validation_error_count()
	 */
	public function test_get_validation_error_count() {
		AMP_Validation_Error_Taxonomy::register();
		$this->assertEquals( 0, AMP_Validation_Error_Taxonomy::get_validation_error_count() );

		self::factory()->term->create(
			[
				'taxonomy' => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
			]
		);
		$this->assertEquals( 1, AMP_Validation_Error_Taxonomy::get_validation_error_count() );

		$terms_to_add = 11;
		for ( $i = 0; $i < $terms_to_add; $i++ ) {
			self::factory()->term->create(
				[
					'taxonomy' => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
				]
			);
		}
		$this->assertEquals( 1 + $terms_to_add, AMP_Validation_Error_Taxonomy::get_validation_error_count() );
	}

	/**
	 * Test filter_posts_where_for_validation_error_status.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::filter_posts_where_for_validation_error_status()
	 */
	public function test_filter_posts_where_for_validation_error_status() {
		global $wpdb;

		$initial_where = 'WHERE foo-condition';
		$wp_query      = new WP_Query();

		// The conditional isn't met, so this shouldn't filter the WHERE clause.
		$this->assertEquals( $initial_where, AMP_Validation_Error_Taxonomy::filter_posts_where_for_validation_error_status( $initial_where, $wp_query ) );

		// Only the first part of the conditional is met, so this still shouldn't filter the WHERE clause.
		$wp_query->set( 'post_type', [ AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );
		$this->assertEquals( $initial_where, AMP_Validation_Error_Taxonomy::filter_posts_where_for_validation_error_status( $initial_where, $wp_query ) );

		// The entire conditional should now be true, so this should filter the WHERE clause.
		$error_status = 1;
		$wp_query->set( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_STATUS_QUERY_VAR, $error_status );
		$filtered_where = AMP_Validation_Error_Taxonomy::filter_posts_where_for_validation_error_status( $initial_where, $wp_query );
		$this->assertContains( 'SELECT 1', $filtered_where );
		$this->assertContains( 'INNER JOIN', $filtered_where );
		$this->assertContains( $wpdb->term_relationships, $filtered_where );
		$this->assertContains( $wpdb->term_taxonomy, $filtered_where );
		$this->assertContains( strval( $error_status ), $filtered_where );

		// Now that there is a query var for error type, that should also appear in the filtered WHERE clause.
		$error_type         = 'js_error';
		$escaped_error_type = 'js\\\\_error';
		$wp_query->set( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_TYPE_QUERY_VAR, $error_type );
		$filtered_where = AMP_Validation_Error_Taxonomy::filter_posts_where_for_validation_error_status( $initial_where, $wp_query );
		$this->assertContains( 'SELECT 1', $filtered_where );
		$this->assertContains( strval( $error_status ), $filtered_where );
		$this->assertContains( $escaped_error_type, $filtered_where );
	}

	/**
	 * Test summarize_validation_errors.
	 *
	 * @covers AMP_Validation_Error_Taxonomy::summarize_validation_errors()
	 */
	public function test_summarize_validation_errors() {
		$attribute_node_name = 'button';
		$element_node_name   = 'nonexistent-element';
		$validation_errors   = [
			[
				'code'      => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR,
				'node_name' => $attribute_node_name,
				'sources'   => [
					[
						'type' => 'plugin',
						'name' => 'foo',
					],
				],
			],
			[
				'code'      => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG,
				'node_name' => $element_node_name,
				'sources'   => [
					[
						'type' => 'theme',
						'name' => 'bar',
					],
				],
			],
		];

		$results          = AMP_Validation_Error_Taxonomy::summarize_validation_errors( $validation_errors );
		$expected_results = [
			AMP_Validation_Error_Taxonomy::REMOVED_ATTRIBUTES => [
				$attribute_node_name => 1,
			],
			AMP_Validation_Error_Taxonomy::REMOVED_ELEMENTS => [
				$element_node_name => 1,
			],
			AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT => [
				'plugin' => [ 'foo' ],
				'theme'  => [ 'bar' ],
			],
			'removed_pis' => [],
		];
		$this->assertEquals( $expected_results, $results );
	}

	/**
	 * Test add_admin_hooks.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::add_admin_hooks()
	 */
	public function test_add_admin_hooks() {
		add_theme_support( AMP_Theme_Support::SLUG );
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Validation_Error_Taxonomy::register();

		// add_group_terms_clauses_filter() needs the screen to be set.
		set_current_screen( 'front' );
		AMP_Validation_Error_Taxonomy::add_admin_hooks();
		do_action( 'load-edit-tags.php' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$this->assertEquals( 10, has_action( 'redirect_term_location', [ self::TESTED_CLASS, 'add_term_filter_query_var' ] ) );
		$this->assertEquals( 10, has_action( 'load-edit-tags.php', [ self::TESTED_CLASS, 'add_group_terms_clauses_filter' ] ) );
		$this->assertEquals( 10, has_action( 'load-edit-tags.php', [ self::TESTED_CLASS, 'add_error_type_clauses_filter' ] ) );
		$this->assertEquals( 10, has_action( 'load-post.php', [ self::TESTED_CLASS, 'add_error_type_clauses_filter' ] ) );
		$this->assertEquals( 10, has_action( sprintf( 'after-%s-table', AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ), [ self::TESTED_CLASS, 'render_taxonomy_filters' ] ) );
		$this->assertEquals( 10, has_action( sprintf( 'after-%s-table', AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ), [ self::TESTED_CLASS, 'render_link_to_invalid_urls_screen' ] ) );
		$this->assertEquals( 10, has_filter( 'user_has_cap', [ self::TESTED_CLASS, 'filter_user_has_cap_for_hiding_term_list_table_checkbox' ] ) );
		$this->assertEquals( 10, has_filter( 'terms_clauses', [ self::TESTED_CLASS, 'filter_terms_clauses_for_description_search' ] ) );
		$this->assertEquals( 10, has_action( 'admin_notices', [ self::TESTED_CLASS, 'add_admin_notices' ] ) );
		$this->assertEquals( 10, has_filter( AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG . '_row_actions', [ self::TESTED_CLASS, 'filter_tag_row_actions' ] ) );
		$this->assertEquals( 10, has_action( 'admin_menu', [ self::TESTED_CLASS, 'add_admin_menu_validation_error_item' ] ) );
		$this->assertEquals( 10, has_filter( 'parse_term_query', [ self::TESTED_CLASS, 'parse_post_php_term_query' ] ) );
		$this->assertEquals( 10, has_filter( 'manage_' . AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG . '_custom_column', [ self::TESTED_CLASS, 'filter_manage_custom_columns' ] ) );
		$this->assertEquals( 10, has_filter( 'manage_' . AMP_Validated_URL_Post_Type::POST_TYPE_SLUG . '_sortable_columns', [ self::TESTED_CLASS, 'add_single_post_sortable_columns' ] ) );
		$this->assertEquals( 10, has_filter( 'posts_where', [ self::TESTED_CLASS, 'filter_posts_where_for_validation_error_status' ] ) );
		$this->assertEquals( 10, has_filter( 'post_action_' . AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_REJECT_ACTION, [ self::TESTED_CLASS, 'handle_single_url_page_bulk_and_inline_actions' ] ) );
		$this->assertEquals( 10, has_filter( 'post_action_' . AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_REJECT_ACTION, [ self::TESTED_CLASS, 'handle_single_url_page_bulk_and_inline_actions' ] ) );
		$this->assertEquals( 10, has_filter( 'handle_bulk_actions-edit-' . AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG, [ self::TESTED_CLASS, 'handle_validation_error_update' ] ) );
		$this->assertEquals( 10, has_action( 'load-edit-tags.php', [ self::TESTED_CLASS, 'handle_inline_edit_request' ] ) );
		$this->assertEquals( 10, has_action( 'admin_enqueue_scripts' ) );

		$cb              = '<input type="checkbox" />';
		$initial_columns = [ 'cb' => $cb ];
		$this->assertEquals(
			array_keys(
				[
					'cb'               => $cb,
					'error_code'       => 'Error',
					'status'           => 'Status<div class="tooltip dashicons dashicons-editor-help"><h3>Statuses tooltip title</h3><p>An accepted validation error is one that will not block a URL from being served as AMP; the validation error will be sanitized, normally resulting in the offending markup being stripped from the response to ensure AMP validity.</p></div>',
					'details'          => 'Details<div class="tooltip dashicons dashicons-editor-help"><h3>Details tooltip title</h3><p>An accepted validation error is one that will not block a URL from being served as AMP; the validation error will be sanitized, normally resulting in the offending markup being stripped from the response to ensure AMP validity.</p></div>',
					'error_type'       => 'Type',
					'created_date_gmt' => 'Last Seen',
					'posts'            => 'Found URLs',
				]
			),
			array_keys( apply_filters( 'manage_edit-' . AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG . '_columns', $initial_columns ) ) // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		);

		// Assert that the 'query_vars' callback adds these query vars.
		$this->assertEmpty(
			array_diff(
				[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_STATUS_QUERY_VAR, AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_TYPE_QUERY_VAR ],
				apply_filters( 'query_vars', [] )
			)
		);
	}

	/**
	 * Test add_group_terms_clauses_filter.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::add_group_terms_clauses_filter()
	 */
	public function test_add_group_terms_clauses_filter() {
		global $current_screen;

		set_current_screen( 'front' );
		$tested_filter = 'terms_clauses';
		remove_all_filters( $tested_filter );
		AMP_Validation_Error_Taxonomy::add_group_terms_clauses_filter();
		$this->assertFalse( has_filter( $tested_filter ) );

		// Only the first part of the conditional will be true, so this shouldn't add the filter.
		$current_screen->taxonomy = AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG;
		AMP_Validation_Error_Taxonomy::add_group_terms_clauses_filter();
		$this->assertFalse( has_filter( $tested_filter ) );

		// The entire conditional should be true, and this should add the filter.
		$_GET[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_STATUS_QUERY_VAR ] = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS;
		AMP_Validation_Error_Taxonomy::add_group_terms_clauses_filter();
		$this->assertTrue( has_filter( $tested_filter ) );
	}

	/**
	 * Test add_term_filter_query_var.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::add_term_filter_query_var()
	 */
	public function test_add_term_filter_query_var() {
		$initial_url      = admin_url( 'edit-tags.php' );
		$correct_taxonomy = new WP_Taxonomy( AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG, AMP_Validated_URL_Post_Type::POST_TYPE_SLUG );
		$wrong_taxonomy   = new WP_Taxonomy( 'category', 'post' );

		// Because the VALIDATION_ERROR_TYPE_QUERY_VAR isn't present in the POST request, this should return the $initial_url unchanged, without adding the query var.
		$this->assertEquals( $initial_url, AMP_Validation_Error_Taxonomy::add_term_filter_query_var( $initial_url, $wrong_taxonomy ) );

		// The $_POST does not have a value for VALIDATION_ERROR_TYPE_QUERY_VAR, so this should again return $initial_url unchanged.
		$wrong_query_var           = 'amp_incorrect_var';
		$_POST[ $wrong_query_var ] = '1';
		$this->assertEquals( $initial_url, AMP_Validation_Error_Taxonomy::add_term_filter_query_var( $initial_url, $wrong_taxonomy ) );

		// The $_POST has the VALIDATION_ERROR_TYPE_QUERY_VAR, but does not have a $taxonomy of AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG.
		$type_query_var_value = AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE;
		$_POST[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_TYPE_QUERY_VAR ] = $type_query_var_value;
		$this->assertEquals( $initial_url, AMP_Validation_Error_Taxonomy::add_term_filter_query_var( $initial_url, $wrong_taxonomy ) );

		// The $_POST has the taxonomy, but does not have the right 'post_type'.
		$_POST['taxonomy']  = AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG;
		$_POST['post_type'] = 'post';
		$this->assertEquals( $initial_url, AMP_Validation_Error_Taxonomy::add_term_filter_query_var( $initial_url, $correct_taxonomy ) );

		// The $_POST has correct 'post_type', so this should add the VALIDATION_ERROR_TYPE_QUERY_VAR to the $initial_url.
		$_POST['post_type'] = AMP_Validated_URL_Post_Type::POST_TYPE_SLUG;
		$expected_url       = add_query_arg(
			AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_TYPE_QUERY_VAR,
			$type_query_var_value,
			$initial_url
		);
		$this->assertEquals( $expected_url, AMP_Validation_Error_Taxonomy::add_term_filter_query_var( $initial_url, $correct_taxonomy ) );

		// The $_POST has a value for the accepted status, so this method should pass that to the redirect URL as a query var.
		$status_query_var_value = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS;
		$_POST[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_STATUS_QUERY_VAR ] = $status_query_var_value;
		$_POST[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_TYPE_QUERY_VAR ]   = null;
		$expected_url = add_query_arg(
			[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_STATUS_QUERY_VAR => [ $status_query_var_value ] ],
			$initial_url
		);
		$this->assertEquals( $expected_url, AMP_Validation_Error_Taxonomy::add_term_filter_query_var( $initial_url, $correct_taxonomy ) );
	}

	/**
	 * Test add_error_type_clauses_filter.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::add_error_type_clauses_filter()
	 */
	public function test_add_error_type_clauses_filter() {
		global $current_screen;

		$initial_where   = 'foo';
		$initial_clauses = [ 'where' => $initial_where ];
		$type            = AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE;
		$taxonomies      = [ AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ];

		set_current_screen( 'front' );
		$tested_filter = 'terms_clauses';
		remove_all_filters( $tested_filter );
		AMP_Validation_Error_Taxonomy::add_error_type_clauses_filter();
		$this->assertFalse( has_filter( $tested_filter ) );

		// The VALIDATION_ERROR_TYPE_QUERY_VAR isn't present, so this should not add the filter.
		$current_screen->taxonomy = AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG;
		AMP_Validation_Error_Taxonomy::add_error_type_clauses_filter();
		$this->assertFalse( has_filter( $tested_filter ) );

		// Both parts of the conditional should be true, and this should add the filter.
		$_GET[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_TYPE_QUERY_VAR ] = $type;
		AMP_Validation_Error_Taxonomy::add_error_type_clauses_filter();
		$this->assertTrue( has_filter( $tested_filter ) );

		// Assert that the filter works as expected.
		$filtered_clauses = apply_filters( $tested_filter, $initial_clauses, $taxonomies );
		$this->assertContains( $initial_where, $filtered_clauses['where'] );
		$this->assertContains( 'AND tt.description LIKE', $filtered_clauses['where'] );

		// If $taxonomies does not have the AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG, the filter should return the clauses unchanged.
		$taxonomies = [ 'post_tag' ];
		$this->assertEquals( $initial_clauses, apply_filters( $tested_filter, $initial_clauses, $taxonomies ) );
	}

	/**
	 * Test render_taxonomy_filters.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::render_taxonomy_filters()
	 */
	public function test_render_taxonomy_filters() {
		AMP_Validation_Error_Taxonomy::register();
		set_current_screen( 'edit-tags.php' );
		// Create one new error.
		self::factory()->term->create(
			[
				'taxonomy'    => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
				'description' => wp_json_encode( $this->get_mock_error() ),
				'term_group'  => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
			]
		);

		// When passing the wrong $taxonomy_name to the method, it should not output anything.
		ob_start();
		AMP_Validation_Error_Taxonomy::render_taxonomy_filters( 'category' );
		$this->assertEmpty( ob_get_clean() );

		// When there are two new errors, the <option> text should be plural, and have a count of (2).
		self::factory()->term->create(
			[
				'taxonomy'    => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
				'description' => wp_json_encode( $this->get_mock_error() ),
				'term_group'  => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
			]
		);
		ob_start();
		AMP_Validation_Error_Taxonomy::render_taxonomy_filters( AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG );
		$this->assertContains( 'New errors <span class="count">(2)</span>', ob_get_clean() );
	}

	/**
	 * Test render_link_to_invalid_urls_screen.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::render_link_to_invalid_urls_screen()
	 */
	public function test_render_link_to_invalid_urls_screen() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		// When passing the wrong $taxonomy argument, this should not render anything.
		$output = get_echo( [ 'AMP_Validation_Error_Taxonomy', 'render_link_to_invalid_urls_screen' ], [ 'category' ] );
		$this->assertEmpty( $output );

		// When passing the correct taxonomy, this should render the link.
		$output = get_echo( [ 'AMP_Validation_Error_Taxonomy', 'render_link_to_invalid_urls_screen' ], [ AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ] );
		$this->assertContains( 'View Validated URLs', $output );
		$this->assertContains(
			add_query_arg(
				'post_type',
				AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
				admin_url( 'edit.php' )
			),
			$output
		);
	}

	/**
	 * Test render_error_status_filter.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::render_error_status_filter()
	 */
	public function test_render_error_status_filter() {
		AMP_Validation_Error_Taxonomy::register();
		set_current_screen( 'post.php' );

		// When this is not on the correct screen, this should not render anything.
		$output = get_echo( [ 'AMP_Validation_Error_Taxonomy', 'render_error_status_filter' ] );
		$this->assertEmpty( $output );

		set_current_screen( 'edit.php' );
		$number_of_errors = 10;
		for ( $i = 0; $i < $number_of_errors; $i++ ) {
			$invalid_url_post      = self::factory()->post->create( [ 'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );
			$validation_error_term = self::factory()->term->create(
				[
					'taxonomy'    => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
					'description' => wp_json_encode( $this->get_mock_error() ),
					'term_group'  => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
				]
			);

			// Associate the validation error term with a URL so that it appears in a query.
			wp_set_post_terms(
				$invalid_url_post,
				[ $validation_error_term ],
				AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG
			);
		}

		// When there are 10 accepted errors, the <option> element for it should end with (10).
		$output = get_echo( [ 'AMP_Validation_Error_Taxonomy', 'render_error_status_filter' ] );
		$this->assertContains(
			sprintf(
				'With new errors <span class="count">(%d)</span>',
				$number_of_errors
			),
			$output
		);
	}

	/**
	 * Test get_error_types.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::get_error_types()
	 */
	public function test_get_error_types() {
		$this->assertEquals(
			[ 'html_element_error', 'html_attribute_error', 'js_error', 'css_error' ],
			AMP_Validation_Error_Taxonomy::get_error_types()
		);
	}

	/**
	 * Test render_error_type_filter.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::render_error_type_filter()
	 */
	public function test_render_error_type_filter() {
		set_current_screen( 'edit-tags.php' );
		$number_of_errors = 10;
		for ( $i = 0; $i < $number_of_errors; $i++ ) {
			self::factory()->term->create(
				[
					'taxonomy'    => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
					'description' => wp_json_encode( $this->get_mock_error() ),
					'term_group'  => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
				]
			);
		}

		// The strings below should be present.
		$markup = get_echo( [ 'AMP_Validation_Error_Taxonomy', 'render_error_type_filter' ] );

		$expected_to_contain = [
			AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_TYPE_QUERY_VAR,
			AMP_Validation_Error_Taxonomy::HTML_ELEMENT_ERROR_TYPE,
			AMP_Validation_Error_Taxonomy::HTML_ATTRIBUTE_ERROR_TYPE,
			AMP_Validation_Error_Taxonomy::JS_ERROR_TYPE,
			AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
		];

		foreach ( $expected_to_contain as $expected ) {
			$this->assertContains( $expected, $markup );
		}

		// On the edit-tags.php page, the <option> text should not have 'With', like 'With JS Errors'.
		$this->assertNotContains( 'With', $markup );

		// On the edit.php page (Errors by URL), the <option> text should have 'With', like 'With JS Errors'.
		set_current_screen( 'edit.php' );
		ob_start();
		AMP_Validation_Error_Taxonomy::render_error_type_filter();
		$this->assertContains( 'With', ob_get_clean() );
	}

	/**
	 * Test render_clear_empty_button.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::render_clear_empty_button()
	 */
	public function test_render_clear_empty_button() {

		$output = get_echo( [ 'AMP_Validation_Error_Taxonomy', 'render_clear_empty_button' ] );
		$this->assertEmpty( $output );

		ob_start();
		$post_id = AMP_Validated_URL_Post_Type::store_validation_errors( [ $this->get_mock_error() ], home_url( '/' ) );
		wp_delete_post( $post_id, true );
		AMP_Validation_Error_Taxonomy::render_clear_empty_button();
		$output = ob_get_clean();
		$this->assertContains( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_CLEAR_EMPTY_ACTION, $output );
	}

	/**
	 * Test filter_user_has_cap_for_hiding_term_list_table_checkbox.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::filter_user_has_cap_for_hiding_term_list_table_checkbox()
	 */
	public function test_filter_user_has_cap_for_hiding_term_list_table_checkbox() {
		$initial_caps = [ 'manage_options' ];
		$this->assertEquals( $initial_caps, AMP_Validation_Error_Taxonomy::filter_user_has_cap_for_hiding_term_list_table_checkbox( $initial_caps, [], [] ) );

		$term_id_with_description = self::factory()->term->create(
			[
				'description' => wp_json_encode( [ 'foo' => 'bar' ] ),
			]
		);
		$args                     = [ 'delete_term', null, $term_id_with_description ];
		$this->assertEquals( $initial_caps, AMP_Validation_Error_Taxonomy::filter_user_has_cap_for_hiding_term_list_table_checkbox( $initial_caps, [], $args ) );

		$term_id_no_description = self::factory()->term->create(
			[
				'description' => wp_json_encode( [ 'foo' => 'bar' ] ),
			]
		);
		$args                   = [ 'delete_term', null, $term_id_no_description ];
		$this->assertEquals( $initial_caps, AMP_Validation_Error_Taxonomy::filter_user_has_cap_for_hiding_term_list_table_checkbox( $initial_caps, [], $args ) );
	}

	/**
	 * Test filter_terms_clauses_for_description_search.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::filter_terms_clauses_for_description_search()
	 */
	public function test_filter_terms_clauses_for_description_search() {
		global $wpdb;

		$initial_where = '((t.name LIKE \'foo\'))';
		$clauses       = [ 'where' => $initial_where ];
		$args          = [ 'search' => 'baz' ];

		// The conditional shouldn't be true, so it shouldn't alter the 'where' clause.
		$this->assertEquals( $clauses, AMP_Validation_Error_Taxonomy::filter_terms_clauses_for_description_search( $clauses, [], $args ) );

		// The conditional should be true, so test the preg_replace() call for $clauses['where'].
		$clauses = AMP_Validation_Error_Taxonomy::filter_terms_clauses_for_description_search( $clauses, [ AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ], $args );
		$this->assertContains( '(tt.description LIKE ', $clauses['where'] );
		$this->assertContains( $wpdb->esc_like( $args['search'] ), $clauses['where'] );
	}

	/**
	 * Test add_admin_notices.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::add_admin_notices()
	 */
	public function test_add_admin_notices() {
		global $current_screen;
		set_current_screen( 'edit.php' );

		// Test that the method exits when the first conditional isn't true.
		$message = get_echo( [ 'AMP_Validation_Error_Taxonomy', 'add_admin_notices' ] );
		$this->assertEmpty( $message );

		// Test the first conditional, where the error is accepted.
		$_GET['amp_actioned']       = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACCEPT_ACTION;
		$count                      = 5;
		$_GET['amp_actioned_count'] = $count;
		$current_screen->taxonomy   = AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG;
		$message                    = get_echo( [ 'AMP_Validation_Error_Taxonomy', 'add_admin_notices' ] );
		$this->assertEquals(
			sprintf( '<div class="notice notice-success is-dismissible"><p>Removed %d instances of invalid markup. They will no longer block related URLs from being served as AMP.</p></div>', $count ),
			$message
		);

		// Test the second conditional, where the error is rejected.
		$_GET['amp_actioned'] = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_REJECT_ACTION;
		$message              = get_echo( [ 'AMP_Validation_Error_Taxonomy', 'add_admin_notices' ] );
		$this->assertEquals(
			sprintf( '<div class="notice notice-success is-dismissible"><p>Kept %d instances of invalid markup. They will continue to block related URLs from being served as AMP.</p></div>', $count ),
			$message
		);
	}

	/**
	 * Test filter_tag_row_actions.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::filter_tag_row_actions()
	 */
	public function test_filter_tag_row_actions() {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );
		global $pagenow;
		$pagenow = 'edit-tags.php';

		// Prevent an error in add_query_arg().
		$_SERVER['REQUEST_URI'] = 'https://example.com';
		AMP_Validation_Error_Taxonomy::register();
		$initial_actions = [
			'delete' => '<a href="#">Delete</a>',
		];

		// The term is for this taxonomy, so this should filter the actions.
		$term_this_taxonomy = self::factory()->term->create_and_get(
			[
				'taxonomy'    => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
				'description' => wp_json_encode( $this->get_mock_error() ),
			]
		);
		$filtered_actions   = AMP_Validation_Error_Taxonomy::filter_tag_row_actions( $initial_actions, $term_this_taxonomy );
		$reject_action      = $filtered_actions[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_REJECT_ACTION ];
		$accept_action      = $filtered_actions[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACCEPT_ACTION ];
		$this->assertContains( strval( $term_this_taxonomy->term_id ), $reject_action );
		$this->assertContains( strval( $term_this_taxonomy->term_id ), $accept_action );
		$this->assertContains( 'Keep', $reject_action );
		$this->assertContains( 'Confirm removed', $accept_action );
	}

	/**
	 * Test add_admin_menu_validation_error_item.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::add_admin_menu_validation_error_item()
	 */
	public function test_add_admin_menu_validation_error_item() {
		global $submenu;

		$submenu = [];
		AMP_Validation_Error_Taxonomy::register();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Validation_Error_Taxonomy::add_admin_menu_validation_error_item();
		$expected_submenu = [
			'Error Index',
			'manage_categories',
			'edit-tags.php?taxonomy=amp_validation_error&amp;post_type=amp_validated_url',
			'Error Index',
		];
		$amp_options      = $submenu[ AMP_Options_Manager::OPTION_NAME ];
		$this->assertEquals( $expected_submenu, end( $amp_options ) );
	}

	/**
	 * Test parse_post_php_term_query.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::parse_post_php_term_query()
	 */
	public function test_parse_post_php_term_query() {
		$wp_term_query = new WP_Term_Query();

		// If is_admin() is false, the conditional will be false and this won't add a query_var value.
		set_current_screen( 'front' );
		AMP_Validation_Error_Taxonomy::parse_post_php_term_query( $wp_term_query );
		$this->assertEmpty( $wp_term_query->query_vars );

		// This is now on the proper screen, but there is no post ID in $_GET['post'].
		set_current_screen( 'post.php' );
		$GLOBALS['pagenow'] = 'post.php';
		AMP_Validation_Error_Taxonomy::parse_post_php_term_query( $wp_term_query );
		$this->assertEmpty( $wp_term_query->query_vars );

		// Though $_GET['post'] has a post ID, it's not for the amp_validated_url post type.
		$post_id_wrong_type = self::factory()->post->create();
		$_GET['post']       = $post_id_wrong_type;
		AMP_Validation_Error_Taxonomy::parse_post_php_term_query( $wp_term_query );
		$this->assertEmpty( $wp_term_query->query_vars );

		// Now that $_GET['post'] has a post ID of the correct post type, it should be in the query var.
		$post_id_correct_post_type = self::factory()->post->create( [ 'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );
		$_GET['post']              = $post_id_correct_post_type;
		AMP_Validation_Error_Taxonomy::parse_post_php_term_query( $wp_term_query );
		$this->assertEquals( $post_id_correct_post_type, $wp_term_query->query_vars['object_ids'] );
	}

	/**
	 * Test get_reader_friendly_error_type_text.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::get_reader_friendly_error_type_text()
	 */
	public function test_get_reader_friendly_error_type_text() {
		$this->assertEquals( 'JS', AMP_Validation_Error_Taxonomy::get_reader_friendly_error_type_text( 'js_error' ) );
		$this->assertEquals( 'HTML element', AMP_Validation_Error_Taxonomy::get_reader_friendly_error_type_text( 'html_element_error' ) );
		$this->assertEquals( 'HTML attribute', AMP_Validation_Error_Taxonomy::get_reader_friendly_error_type_text( 'html_attribute_error' ) );
		$this->assertEquals( 'CSS', AMP_Validation_Error_Taxonomy::get_reader_friendly_error_type_text( 'css_error' ) );
		$this->assertEquals( 'some_other_error', AMP_Validation_Error_Taxonomy::get_reader_friendly_error_type_text( 'some_other_error' ) );
	}

	/**
	 * Test get_details_summary_label.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::get_details_summary_label()
	 */
	public function test_get_details_summary_label() {
		$validation_error = $this->get_mock_error();
		$this->assertEquals( '<code>&lt;link&gt;</code>', AMP_Validation_Error_Taxonomy::get_details_summary_label( $validation_error ) );
		$validation_error['code'] = AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR;
		$this->assertEquals( '<code>&lt;head&gt;</code>', AMP_Validation_Error_Taxonomy::get_details_summary_label( $validation_error ) );
		unset( $validation_error['node_name'] );
		$this->assertEquals( '<code>&lt;head&gt;</code>', AMP_Validation_Error_Taxonomy::get_details_summary_label( $validation_error ) );
		$validation_error['code'] = 'some_other_code';
		$this->assertEquals( '<code>&hellip;</code>', AMP_Validation_Error_Taxonomy::get_details_summary_label( $validation_error ) );
	}

	/**
	 * Test filter_manage_custom_columns.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::filter_manage_custom_columns()
	 */
	public function test_filter_manage_custom_columns() {
		$this->accept_sanitization_by_default( false );
		AMP_Validation_Error_Taxonomy::register();
		$validation_error = $this->get_mock_error();
		$initial_content  = 'example initial content';
		$term_id          = self::factory()->term->create(
			[
				'taxonomy'    => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
				'description' => wp_json_encode( $validation_error ),
			]
		);

		// Test the 'error' block in the switch.
		$GLOBALS['pagenow'] = 'post.php';
		$filtered_content   = AMP_Validation_Error_Taxonomy::filter_manage_custom_columns( $initial_content, 'error_code', $term_id );
		$this->assertStringStartsWith( $initial_content . '<button type="button" aria-label="Toggle error details"', $filtered_content );

		// Test the 'status' block in the switch for the error taxonomy page.
		$GLOBALS['pagenow'] = 'edit-tags.php';
		$filtered_content   = AMP_Validation_Error_Taxonomy::filter_manage_custom_columns( $initial_content, 'status', $term_id );
		$this->assertContains( '<span class="status-text rejected">Kept</span>', $filtered_content );

		// Test the 'status' block switch for the single error page.
		$GLOBALS['pagenow'] = 'post.php';
		$filtered_content   = AMP_Validation_Error_Taxonomy::filter_manage_custom_columns( $initial_content, 'status', $term_id );
		$this->assertContains( '<select class="amp-validation-error-status" id="amp_validation_error_term_status', $filtered_content );

		// Test the 'created_date_gmt' block in the switch.
		$date = current_time( 'mysql', true );
		update_term_meta( $term_id, 'created_date_gmt', $date );
		$filtered_content = AMP_Validation_Error_Taxonomy::filter_manage_custom_columns( $initial_content, 'created_date_gmt', $term_id );
		$this->assertContains( '<time datetime=', $filtered_content );
		$this->assertContains( '<abbr title=', $filtered_content );

		// Test the 'details' block in the switch.
		$filtered_content = AMP_Validation_Error_Taxonomy::filter_manage_custom_columns( $initial_content, 'details', $term_id );
		$this->assertContains( '<details open class="details-attributes"><summary class="details-attributes__summary"', $filtered_content );

		// Test the 'error_type' block in the switch.
		$filtered_content = AMP_Validation_Error_Taxonomy::filter_manage_custom_columns( $initial_content, 'error_type', $term_id );
		$this->assertContains( 'CSS', $filtered_content );
	}

	/**
	 * Test for add_single_post_sortable_columns()
	 *
	 * @covers AMP_Validation_Error_Taxonomy::add_single_post_sortable_columns()
	 */
	public function test_add_single_post_sortable_columns() {
		$initial_columns              = [
			'description' => 'description',
			'links'       => 'count',
		];
		$columns_expected_to_be_added = [
			'error_code' => 'amp_validation_code',
			'error_type' => 'amp_validation_error_type',
		];
		$this->assertEquals(
			array_merge( $initial_columns, $columns_expected_to_be_added ),
			AMP_Validation_Error_Taxonomy::add_single_post_sortable_columns( $initial_columns )
		);

		// In the unlikely case that the initial columns has an 'error' value, this method should overwrite it.
		$initial_columns_with_error = [
			'error_code' => 'foobar',
		];
		$this->assertEquals(
			$columns_expected_to_be_added,
			AMP_Validation_Error_Taxonomy::add_single_post_sortable_columns( $initial_columns_with_error )
		);
	}

	/**
	 * Test render_single_url_error_details.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::render_single_url_error_details()
	 */
	public function test_render_single_url_error_details() {
		$validation_error         = self::get_mock_error();
		$validation_error['code'] = AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG;
		$term                     = self::factory()->term->create_and_get( [ 'taxonomy' => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ] );
		$html                     = AMP_Validation_Error_Taxonomy::render_single_url_error_details( $validation_error, $term );
		$this->assertContains( '<dl class="detailed">', $html );
	}

	/**
	 * Test get_translated_type_name.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::get_translated_type_name()
	 */
	public function test_get_translated_type_name() {
		// When the error doesn't have a type, this should return null.
		$error_without_type = [
			'code' => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG,
		];
		$this->assertEmpty( AMP_Validation_Error_Taxonomy::get_translated_type_name( $error_without_type ) );

		// When the error has a type that's not recognized, this should also return null.
		$error_with_unrecognized_type = [
			'type' => 'foobar',
		];
		$this->assertEmpty( AMP_Validation_Error_Taxonomy::get_translated_type_name( $error_with_unrecognized_type ) );

		$translated_names = [
			AMP_Validation_Error_Taxonomy::HTML_ELEMENT_ERROR_TYPE => 'HTML Element',
			AMP_Validation_Error_Taxonomy::HTML_ATTRIBUTE_ERROR_TYPE => 'HTML Attribute',
			AMP_Validation_Error_Taxonomy::JS_ERROR_TYPE  => 'JavaScript',
			AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE => 'CSS',
		];

		foreach ( $translated_names as $slug => $name ) {
			$validation_error = [
				'type' => $slug,
			];
			$this->assertEquals( $name, AMP_Validation_Error_Taxonomy::get_translated_type_name( $validation_error ) );
		}
	}

	/**
	 * Test handle_single_url_page_bulk_and_inline_actions.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::handle_single_url_page_bulk_and_inline_actions()
	 */
	public function test_handle_single_url_page_bulk_and_inline_actions() {
		// Create a new error term.
		$initial_accepted_status = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS;
		$error_term              = self::factory()->term->create_and_get(
			[
				'taxonomy'    => AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
				'description' => wp_json_encode( $this->get_mock_error() ),
			]
		);
		wp_update_term( $error_term->term_id, AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG, [ 'term_group' => $initial_accepted_status ] );

		// Because the action is incorrect, the tested method should exit and not update the validation error term.
		$_REQUEST['action']   = 'incorrect-action';
		$_POST['delete_tags'] = [ $error_term->term_id ];
		$correct_post_type    = self::factory()->post->create( [ 'post_type' => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );
		$this->assertEquals( get_term( $error_term->term_id )->term_group, $initial_accepted_status );

		// Because the post type is wrong, the tested method should again return without updating the term.
		$_REQUEST['action']  = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACCEPT_ACTION;
		$incorrect_post_type = self::factory()->post->create();
		AMP_Validation_Error_Taxonomy::handle_single_url_page_bulk_and_inline_actions( $incorrect_post_type );
		$this->assertEquals( get_term( $error_term->term_id )->term_group, $initial_accepted_status );

		/*
		 * Now that the post type is correct, this should update the post accepted status to be 'accepted'.
		 * There should be a warning because wp_safe_redirect() should be called at the end of the tested method.
		 */
		try {
			AMP_Validation_Error_Taxonomy::handle_single_url_page_bulk_and_inline_actions( $correct_post_type );
		} catch ( Exception $exception ) {
			$e = $exception;
		}

		$this->assertContains( 'Cannot modify header information', $e->getMessage() );
		$this->assertEquals( get_term( $error_term->term_id )->term_group, AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS );

		// When the action is to 'reject' the error, this should update the status of the error to 'rejected'.
		$_REQUEST['action'] = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_REJECT_ACTION;
		try {
			AMP_Validation_Error_Taxonomy::handle_single_url_page_bulk_and_inline_actions( $correct_post_type );
		} catch ( Exception $exception ) {
			$e = $exception;
		}

		$this->assertContains( 'Cannot modify header information', $e->getMessage() );
		$this->assertEquals( get_term( $error_term->term_id )->term_group, AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS );
	}

	/**
	 * Test handle_validation_error_update.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::handle_validation_error_update()
	 */
	public function test_handle_validation_error_update() {
		$initial_redirect_to = 'https://example.com';

		// The action argument isn't either an accepted or rejected status, so the redirect shouldn't change.
		$this->assertEquals( $initial_redirect_to, AMP_Validation_Error_Taxonomy::handle_validation_error_update( $initial_redirect_to, 'unexpected-action', [] ) );

		$action = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_REJECT_ACTION;
		$this->assertEquals(
			add_query_arg(
				[
					'amp_actioned'       => $action,
					'amp_actioned_count' => 0,
				],
				$initial_redirect_to
			),
			AMP_Validation_Error_Taxonomy::handle_validation_error_update( $initial_redirect_to, $action, [] )
		);
		$this->assertNotFalse( has_filter( 'pre_term_description', 'wp_filter_kses' ) );
	}

	/**
	 * Test handle_clear_empty_terms_request.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::handle_clear_empty_terms_request()
	 */
	public function test_handle_clear_empty_terms_request() {
		add_filter(
			'wp_redirect',
			static function() {
				throw new Exception( 'redirected' );
			}
		);
		$post_id = AMP_Validated_URL_Post_Type::store_validation_errors( [ $this->get_mock_error() ], home_url( '/' ) );
		wp_delete_post( $post_id, true );
		$_REQUEST = &$_POST; // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification

		// No-op.
		AMP_Validation_Error_Taxonomy::handle_clear_empty_terms_request();
		$this->assertEquals( 1, AMP_Validation_Error_Taxonomy::get_validation_error_count() );

		// Bad nonce.
		$_POST[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_CLEAR_EMPTY_ACTION ]            = 1;
		$_POST[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_CLEAR_EMPTY_ACTION . '_nonce' ] = 'bad';
		try {
			$exception = null;
			AMP_Validation_Error_Taxonomy::handle_clear_empty_terms_request();
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'WPDieException', $exception );
		$this->assertEquals( 1, AMP_Validation_Error_Taxonomy::get_validation_error_count() );

		// Good nonce, but no permissions.
		$_REQUEST[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_CLEAR_EMPTY_ACTION . '_nonce' ] = wp_create_nonce( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_CLEAR_EMPTY_ACTION );
		try {
			$exception = null;
			AMP_Validation_Error_Taxonomy::handle_clear_empty_terms_request();
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'WPDieException', $exception );
		$this->assertEquals( 1, AMP_Validation_Error_Taxonomy::get_validation_error_count() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_REQUEST[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_CLEAR_EMPTY_ACTION . '_nonce' ] = wp_create_nonce( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_CLEAR_EMPTY_ACTION );
		AMP_Validation_Error_Taxonomy::handle_clear_empty_terms_request();
		$this->assertEquals( 0, AMP_Validation_Error_Taxonomy::get_validation_error_count() );
	}

	/**
	 * Test conditionally_change_sanitization.
	 *
	 * @covers \AMP_Validation_Error_Taxonomy::conditionally_change_sanitization()
	 */
	public function test_conditionally_change_sanitization() {
		$initial_sanitization = null;

		// The user doesn't have the correct permissions, so this should not change the sanitization.
		$this->assertEquals( $initial_sanitization, AMP_Validation_Error_Taxonomy::conditionally_change_sanitization( $initial_sanitization, [] ) );

		// The user now has the correct permissions, but the query vars aren't present, so this should not change the sanitization.
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertEquals( $initial_sanitization, AMP_Validation_Error_Taxonomy::conditionally_change_sanitization( $initial_sanitization, [] ) );

		// With the query var present to reject all errors, this should return false.
		$_GET[ AMP_Debug::AMP_FLAGS_QUERY_VAR ][ AMP_Debug::REJECT_ALL_VALIDATION_ERRORS_QUERY_VAR ] = '1';
		$this->assertFalse( AMP_Validation_Error_Taxonomy::conditionally_change_sanitization( $initial_sanitization, [] ) );
		$_GET = [];

		// Though the query var to reject excessive_css errors is present, the error's code is wrong, so this shouldn't change the sanitization.
		$_GET[ AMP_Debug::AMP_FLAGS_QUERY_VAR ][ AMP_Debug::ACCEPT_EXCESSIVE_CSS_ERROR_QUERY_VAR ] = '1';
		$error = [ 'code' => 'invalid_attribute' ];
		$this->assertEquals( $initial_sanitization, AMP_Validation_Error_Taxonomy::conditionally_change_sanitization( $initial_sanitization, $error ) );

		// Now that the error code is 'excessive_css', this should return true for the sanitization.
		$this->assertTrue( AMP_Validation_Error_Taxonomy::conditionally_change_sanitization( $initial_sanitization, [ 'code' => 'excessive_css' ] ) );
	}

	/**
	 * Gets a mock validation error for testing.
	 *
	 * @return array $error Mock validation error.
	 */
	public function get_mock_error() {
		return [
			'at_rule'         => '-ms-viewport',
			'code'            => AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_AT_RULE,
			'node_attributes' => [
				'href'  => 'https://example.com',
				'id'    => 'twentysixteen-style-css',
				'media' => 'all',
				'rel'   => 'stylesheet',
				'type'  => 'text/css',
			],
			'node_name'       => 'link',
			'parent_name'     => 'head',
			'type'            => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
		];
	}
}

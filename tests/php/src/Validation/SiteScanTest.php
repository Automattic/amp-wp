<?php

namespace AmpProject\AmpWP\Tests\Validation;

use AmpProject\AmpWP\Option;
use AMP_Options_Manager;
use AMP_Post_Meta_Box;
use AMP_Theme_Support;
use AmpProject\AmpWP\Tests\Helpers\AssertContainsCompatibility;
use AmpProject\AmpWP\Tests\Helpers\PrivateAccess;
use AmpProject\AmpWP\Tests\Helpers\ValidationRequestMocking;
use AmpProject\AmpWP\Validation\SiteScan;
use WP_Query;
use WP_UnitTestCase;

/** @coversDefaultClass SiteScan */
final class SiteScanTest extends WP_UnitTestCase {
	use PrivateAccess, AssertContainsCompatibility;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->site_scan = new SiteScan( 100 );
		add_filter( 'pre_http_request', [ ValidationRequestMocking::class, 'get_validate_response' ] );
	}

	/**
	 * Test count_urls_to_validate.
	 *
	 * @covers ::count_urls_to_validate()
	 */
	public function test_count_urls_to_validate() {
		$this->site_scan = new SiteScan();

		$number_original_urls = 4;

		$this->assertEquals( $number_original_urls, $this->site_scan->count_urls_to_validate() );

		$this->site_scan = new SiteScan( 100 );

		$category         = self::factory()->term->create( [ 'taxonomy' => 'category' ] );
		$number_new_posts = 50;
		$post_ids         = [];
		for ( $i = 0; $i < $number_new_posts; $i++ ) {
			$post_ids[] = self::factory()->post->create(
				[
					'tax_input' => [ 'category' => $category ],
				]
			);
		}

		/*
		 * Add the number of new posts, original URLs, and 1 for the $category that all of them have.
		 * And ensure that the tested method finds a URL for all of them.
		 */
		$expected_url_count = $number_new_posts + $number_original_urls + 1;
		$this->assertEquals( $expected_url_count, $this->site_scan->count_urls_to_validate() );

		$this->site_scan = new SiteScan( 100 );

		$number_of_new_terms        = 20;
		$expected_url_count        += $number_of_new_terms;
		$taxonomy                   = 'category';
		$terms_for_current_taxonomy = [];
		for ( $i = 0; $i < $number_of_new_terms; $i++ ) {
			$terms_for_current_taxonomy[] = self::factory()->term->create(
				[
					'taxonomy' => $taxonomy,
				]
			);
		}

		// Terms need to be associated with a post in order to be returned in get_terms().
		wp_set_post_terms(
			$post_ids[0],
			$terms_for_current_taxonomy,
			$taxonomy
		);

		$this->assertEquals( $expected_url_count, $this->site_scan->count_urls_to_validate() );
	}

	/**
	 * Test get_posts_that_support_amp.
	 *
	 * @covers ::get_posts_that_support_amp()
	 */
	public function test_get_posts_that_support_amp() {
		$number_of_posts = 20;
		$ids             = [];
		for ( $i = 0; $i < $number_of_posts; $i++ ) {
			$ids[] = self::factory()->post->create();
		}

		// This should count all of the newly-created posts as supporting AMP.
		$this->assertEquals( $ids, $this->call_private_method( $this->site_scan, 'get_posts_that_support_amp', [ $ids ] ) );

		// Simulate 'Enable AMP' being unchecked in the post editor, in which case get_url_count() should not count it.
		$first_id = $ids[0];
		update_post_meta(
			$first_id,
			AMP_Post_Meta_Box::STATUS_POST_META_KEY,
			AMP_Post_Meta_Box::DISABLED_STATUS
		);
		$this->assertEquals( [], $this->call_private_method( $this->site_scan, 'get_posts_that_support_amp', [ [ $first_id ] ] ) );

		update_post_meta(
			$first_id,
			AMP_Post_Meta_Box::STATUS_POST_META_KEY,
			AMP_Post_Meta_Box::ENABLED_STATUS
		);

		// When the second $force_count_all_urls argument is true, all of the newly-created posts should be part of the URL count.
		$this->site_scan->force_crawl_urls = true;
		$this->assertEquals( $ids, $this->call_private_method( $this->site_scan, 'get_posts_that_support_amp', [ $ids ] ) );
		$this->site_scan->force_crawl_urls = false;

		// In AMP-first, the IDs should include all of the newly-created posts.
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		$this->assertEquals( $ids, $this->call_private_method( $this->site_scan, 'get_posts_that_support_amp', [ $ids ] ) );

		// In Transitional Mode, the IDs should also include all of the newly-created posts.
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				AMP_Theme_Support::PAIRED_FLAG => true,
			]
		);
		$this->assertEquals( $ids, $this->call_private_method( $this->site_scan, 'get_posts_that_support_amp', [ $ids ] ) );

		/*
		 * If the WP-CLI command has an include argument, and is_singular isn't in it, no posts will have AMP enabled.
		 * For example, wp amp validate-site --include=is_tag,is_category
		 */
		$this->site_scan->include_conditionals = [ 'is_tag', 'is_category' ];
		$this->assertEquals( [], $this->call_private_method( $this->site_scan, 'get_posts_that_support_amp', [ $ids ] ) );

		/*
		 * If is_singular is in the WP-CLI argument, it should allow return these posts as being AMP-enabled.
		 * For example, wp amp validate-site include=is_singular,is_category
		 */
		$this->site_scan->include_conditionals = [ 'is_singular', 'is_category' ];
		$this->assertEmpty( array_diff( $ids, $this->call_private_method( $this->site_scan, 'get_posts_that_support_amp', [ $ids ] ) ) );
		$this->site_scan->include_conditionals = [];
	}

	/**
	 * Test get_author_page_urls.
	 *
	 * @covers AMP_CLI_Validation_Command::get_author_page_urls()
	 */
	public function test_get_author_page_urls() {
		self::factory()->user->create();
		$users             = get_users();
		$first_author      = $users[0];
		$first_author_url  = get_author_posts_url( $first_author->ID, $first_author->user_nicename );
		$second_author     = $users[1];
		$second_author_url = get_author_posts_url( $second_author->ID, $second_author->user_nicename );

		$actual_urls = $this->call_private_method( $this->site_scan, 'get_author_page_urls', [ 0, 1 ] );

		// Passing 0 as the offset argument should get the first author.
		$this->assertEquals( [ $first_author_url ], $actual_urls );

		$actual_urls = $this->call_private_method( $this->site_scan, 'get_author_page_urls', [ 1, 1 ] );

		// Passing 1 as the offset argument should get the second author.
		$this->assertEquals( [ $second_author_url ], $actual_urls );

		// If $include_conditionals is set and does not have is_author, this should not return a URL.
		$this->site_scan->include_conditionals = [ 'is_category' ];
		$this->assertEquals( [], $this->call_private_method( $this->site_scan, 'get_author_page_urls' ) );

		// If $include_conditionals is set and has is_author, this should return URLs.
		$this->site_scan->include_conditionals = [ 'is_author' ];
		$this->assertEquals(
			[ $first_author_url, $second_author_url ],
			$this->call_private_method( $this->site_scan, 'get_author_page_urls' )
		);
		$this->site_scan->include_conditionals = [];
	}

	/**
	 * Test does_taxonomy_support_amp.
	 *
	 * @covers AMP_CLI_Validation_Command::does_taxonomy_support_amp()
	 */
	public function test_does_taxonomy_support_amp() {
		$custom_taxonomy = 'foo_custom_taxonomy';
		register_taxonomy( $custom_taxonomy, 'post' );
		$taxonomies_to_test = [ $custom_taxonomy, 'category', 'post_tag' ];
		AMP_Options_Manager::update_option( Option::SUPPORTED_TEMPLATES, [ 'is_category', 'is_tag', sprintf( 'is_tax[%s]', $custom_taxonomy ) ] );

		// When these templates are not unchecked in the 'AMP Settings' UI, these should be supported.
		foreach ( $taxonomies_to_test as $taxonomy ) {
			$this->assertTrue( $this->call_private_method( $this->site_scan, 'does_taxonomy_support_amp', [ $taxonomy ] ) );
		}

		// When the user has not checked the boxes for 'Categories' and 'Tags,' this should be false.
		AMP_Options_Manager::update_option( Option::SUPPORTED_TEMPLATES, [ 'is_author' ] );
		AMP_Options_Manager::update_option( Option::ALL_TEMPLATES_SUPPORTED, false );
		foreach ( $taxonomies_to_test as $taxonomy ) {
			$this->assertFalse( $this->call_private_method( $this->site_scan, 'does_taxonomy_support_amp', [ $taxonomy ] ) );
		}

		// When $force_crawl_urls is true, all taxonomies should be supported.
		$this->site_scan->force_crawl_urls = true;
		foreach ( $taxonomies_to_test as $taxonomy ) {
			$this->assertTrue( $this->call_private_method( $this->site_scan, 'does_taxonomy_support_amp', [ $taxonomy ] ) );
		}
		$this->site_scan->force_crawl_urls = false;

		// When the user has checked the Option::ALL_TEMPLATES_SUPPORTED box, this should always be true.
		AMP_Options_Manager::update_option( Option::ALL_TEMPLATES_SUPPORTED, true );
		foreach ( $taxonomies_to_test as $taxonomy ) {
			$this->assertTrue( $this->call_private_method( $this->site_scan, 'does_taxonomy_support_amp', [ $taxonomy ] ) );
		}
		AMP_Options_Manager::update_option( Option::ALL_TEMPLATES_SUPPORTED, false );

		/*
		 * If the user passed allowed conditionals to the WP-CLI command like wp amp validate-site --include=is_category,is_tag
		 * these should be supported taxonomies.
		 */
		$this->site_scan->include_conditionals = [ 'is_category', 'is_tag' ];
		$this->assertTrue( $this->call_private_method( $this->site_scan, 'does_taxonomy_support_amp', [ 'category' ] ) );
		$this->assertTrue( $this->call_private_method( $this->site_scan, 'does_taxonomy_support_amp', [ 'tag' ] ) );
		$this->assertFalse( $this->call_private_method( $this->site_scan, 'does_taxonomy_support_amp', [ 'author' ] ) );
		$this->assertFalse( $this->call_private_method( $this->site_scan, 'does_taxonomy_support_amp', [ 'search' ] ) );
		$this->site_scan->include_conditionals = [];
	}

	/**
	 * Test is_template_supported.
	 *
	 * @covers AMP_CLI_Validation_Command::is_template_supported()
	 */
	public function test_is_template_supported() {
		$author_conditional = 'is_author';
		$search_conditional = 'is_search';

		AMP_Options_Manager::update_option( Option::SUPPORTED_TEMPLATES, [ $author_conditional ] );
		AMP_Options_Manager::update_option( Option::ALL_TEMPLATES_SUPPORTED, false );
		$this->assertTrue( $this->call_private_method( $this->site_scan, 'is_template_supported', [ $author_conditional ] ) );
		$this->assertFalse( $this->call_private_method( $this->site_scan, 'is_template_supported', [ $search_conditional ] ) );

		AMP_Options_Manager::update_option( Option::SUPPORTED_TEMPLATES, [ $search_conditional ] );
		$this->assertTrue( $this->call_private_method( $this->site_scan, 'is_template_supported', [ $search_conditional ] ) );
		$this->assertFalse( $this->call_private_method( $this->site_scan, 'is_template_supported', [ $author_conditional ] ) );
	}

	/**
	 * Test get_posts_by_type.
	 *
	 * @covers AMP_CLI_Validation_Command::get_posts_by_type()
	 */
	public function test_get_posts_by_type() {
		$number_posts_each_post_type = 20;
		$post_types                  = get_post_types( [ 'public' => true ], 'names' );

		foreach ( $post_types as $post_type ) {
			// Start the expected posts with the existing post(s).
			$query          = new WP_Query(
				[
					'fields'    => 'ids',
					'post_type' => $post_type,
				]
			);
			$expected_posts = $query->posts;

			for ( $i = 0; $i < $number_posts_each_post_type; $i++ ) {
				array_unshift(
					$expected_posts,
					self::factory()->post->create(
						[
							'post_type' => $post_type,
						]
					)
				);
			}

			$actual_posts = $this->call_private_method( $this->site_scan, 'get_posts_by_type', [ $post_type ] );
			$this->assertEquals( $expected_posts, array_values( $actual_posts ) );

			// Test with the $offset and $number arguments.
			$offset       = 0;
			$actual_posts = $this->call_private_method( $this->site_scan, 'get_posts_by_type', [ $post_type, $offset, $number_posts_each_post_type ] );
			$this->assertEquals( array_slice( $expected_posts, $offset, $number_posts_each_post_type ), $actual_posts );
		}
	}

	/**
	 * Test get_taxonomy_links.
	 *
	 * @covers AMP_CLI_Validation_Command::get_taxonomy_links()
	 */
	public function test_get_taxonomy_links() {
		$number_links_each_taxonomy = 20;
		$taxonomies                 = get_taxonomies(
			[
				'public' => true,
			]
		);

		foreach ( $taxonomies as $taxonomy ) {
			// Begin the expected links with the term links that already exist.
			$expected_links             = array_map( 'get_term_link', get_terms( [ 'taxonomy' => $taxonomy ] ) );
			$terms_for_current_taxonomy = [];
			for ( $i = 0; $i < $number_links_each_taxonomy; $i++ ) {
				$terms_for_current_taxonomy[] = self::factory()->term->create(
					[
						'taxonomy' => $taxonomy,
					]
				);
			}

			// Terms need to be associated with a post in order to be returned in get_terms().
			wp_set_post_terms(
				self::factory()->post->create(),
				$terms_for_current_taxonomy,
				$taxonomy
			);

			$expected_links  = array_merge(
				$expected_links,
				array_map( 'get_term_link', $terms_for_current_taxonomy )
			);
			$number_of_links = 100;
			$actual_links    = $this->call_private_method( $this->site_scan, 'get_taxonomy_links', [ $taxonomy, 0, $number_of_links ] );

			// The get_terms() call in get_taxonomy_links() returns an array with a first index of 1, so correct for that with array_values().
			$this->assertEquals( $expected_links, array_values( $actual_links ) );
			$this->assertLessThan( $number_of_links, count( $actual_links ) );

			$number_of_links           = 5;
			$offset                    = 10;
			$actual_links_using_offset = $this->call_private_method( $this->site_scan, 'get_taxonomy_links', [ $taxonomy, $offset, $number_of_links ] );
			$this->assertEquals( array_slice( $expected_links, $offset, $number_of_links ), array_values( $actual_links_using_offset ) );
			$this->assertEquals( $number_of_links, count( $actual_links_using_offset ) );
		}
	}

	/**
	 * Test get_search_page.
	 *
	 * @covers AMP_CLI_Validation_Command::get_search_page()
	 */
	public function test_get_search_page() {
		// Normally, this should return a string, unless the user has opted out of the search template.
		$this->assertTrue( is_string( $this->call_private_method( $this->site_scan, 'get_search_page' ) ) );

		// If $include_conditionals is set and does not have is_search, this should not return a URL.
		$this->site_scan->include_conditionals = [ 'is_author' ];
		$this->assertEquals( null, $this->call_private_method( $this->site_scan, 'get_search_page' ) );

		// If $include_conditionals has is_search, this should return a URL.
		$this->site_scan->include_conditionals = [ 'is_search' ];
		$this->assertTrue( is_string( $this->call_private_method( $this->site_scan, 'get_search_page' ) ) );
		$this->site_scan->include_conditionals = [];
	}

	/**
	 * Test get_date_page.
	 *
	 * @covers AMP_CLI_Validation_Command::get_date_page()
	 */
	public function test_get_date_page() {
		$year = gmdate( 'Y' );

		// Normally, this should return the date page, unless the user has opted out of that template.
		$this->assertStringContains( $year, $this->call_private_method( $this->site_scan, 'get_date_page' ) );

		// If $include_conditionals is set and does not have is_date, this should not return a URL.
		$this->site_scan->include_conditionals = [ 'is_search' ];
		$this->assertEquals( null, $this->call_private_method( $this->site_scan, 'get_date_page' ) );

		// If $include_conditionals has is_date, this should return a URL.
		$this->site_scan->include_conditionals = [ 'is_date' ];
		$parsed_page_url                       = wp_parse_url( $this->call_private_method( $this->site_scan, 'get_date_page' ) );
		$this->assertStringContains( $year, $parsed_page_url['query'] );
		$this->site_scan->include_conditionals = [];
	}

	/**
	 * Test validate_and_store_url.
	 *
	 * @covers AMP_CLI_Validation_Command::validate_and_store_url()
	 */
	public function test_validate_and_store_url() {
		$single_post_permalink = get_permalink( self::factory()->post->create() );
		$this->call_private_method( $this->site_scan, 'validate_and_store_url', [ $single_post_permalink, 'post' ] );
		$this->assertTrue( in_array( $single_post_permalink, ValidationRequestMocking::get_validated_urls(), true ) );

		$number_of_posts = 30;
		$post_permalinks = [];

		for ( $i = 0; $i < $number_of_posts; $i++ ) {
			$permalink         = get_permalink( self::factory()->post->create() );
			$post_permalinks[] = $permalink;
			$this->call_private_method( $this->site_scan, 'validate_and_store_url', [ $permalink, 'post' ] );
		}

		// All of the posts created should be present in the validated URLs.
		$this->assertEmpty( array_diff( $post_permalinks, ValidationRequestMocking::get_validated_urls() ) );
	}
}

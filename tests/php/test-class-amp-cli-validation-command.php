<?php
/**
 * Tests for Test_AMP_CLI_Validation_Command class.
 *
 * @package AMP
 */

use AmpProject\AmpWP\Option;
use AmpProject\AmpWP\Tests\Helpers\AssertContainsCompatibility;
use AmpProject\AmpWP\Tests\Helpers\PrivateAccess;
use AmpProject\AmpWP\Tests\Helpers\ValidationRequestMocking;

/**
 * Tests for Test_AMP_CLI_Validation_Command class.
 *
 * @since 1.0
 *
 * @coversDefaultClass AMP_CLI_Validation_Command
 */
class Test_AMP_CLI_Validation_Command extends \WP_UnitTestCase {

	use AssertContainsCompatibility;
	use PrivateAccess;

	/**
	 * Store a reference to the validation command object.
	 *
	 * @var AMP_CLI_Validation_Command
	 */
	private $validation;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		$this->validation = new AMP_CLI_Validation_Command();
		add_filter( 'pre_http_request', [ ValidationRequestMocking::class, 'get_validate_response' ] );
	}

	/**
	 * Test crawl_site.
	 *
	 * @covers ::crawl_site()
	 */
	public function test_crawl_site() {
		$number_of_posts = 20;
		$number_of_terms = 30;
		$posts           = [];
		$post_permalinks = [];
		$terms           = [];

		for ( $i = 0; $i < $number_of_posts; $i++ ) {
			$post_id           = self::factory()->post->create();
			$posts[]           = $post_id;
			$post_permalinks[] = get_permalink( $post_id );
		}
		$this->call_private_method( $this->validation, 'crawl_site' );

		// All of the posts created above should be present in $validated_urls.
		$this->assertEmpty( array_diff( $post_permalinks, ValidationRequestMocking::get_validated_urls() ) );

		$this->validation = new AMP_CLI_Validation_Command();
		for ( $i = 0; $i < $number_of_terms; $i++ ) {
			$terms[] = self::factory()->category->create();
		}

		// Terms need to be associated with a post in order to be returned in get_terms().
		wp_set_post_terms( $posts[0], $terms, 'category' );
		$this->call_private_method( $this->validation, 'crawl_site' );
		$expected_validated_urls = array_map( 'get_term_link', $terms );
		$actual_validated_urls   = ValidationRequestMocking::get_validated_urls();

		// All of the terms created above should be present in $validated_urls.
		$this->assertEmpty( array_diff( $expected_validated_urls, $actual_validated_urls ) );
		$this->assertTrue( in_array( home_url( '/' ), ValidationRequestMocking::get_validated_urls(), true ) );
	}
}

<?php
/**
 * Test AMP_Prepare_Data.
 *
 * @package AMP
 */

use AmpProject\AmpWP\QueryVar;

/**
 * Test AMP_Prepare_Data.
 *
 * @covers AMP_Prepare_Data
 */
class AMP_Prepare_Data_Test extends WP_UnitTestCase {

	/**
	 * Test __construct method.
	 *
	 * @covers \AMP_Prepare_Data::__construct()
	 */
	public function test_construct() {
		$prepare_data = new \AMP_Prepare_Data( [] );
		$this->assertTrue(
			is_a(
				$prepare_data,
				'AMP_Prepare_Data'
			)
		);
	}

	/**
	 * Test parse_args method.
	 *
	 * @covers \AMP_Prepare_Data::parse_args()
	 */
	public function test_parse_args() {
		$args = [
			'urls'     => [ 'https://google.com/' ],
			'post_ids' => [ 123, 456 ],
			'term_ids' => [ 789 ],
		];

		$prepare_data = new \AMP_Prepare_Data( $args );

		$this->assertSame(
			$args['urls'],
			$prepare_data->urls
		);
		$this->assertSame(
			$args['term_ids'],
			$prepare_data->args['term_ids']
		);
		$this->assertSame(
			$args['post_ids'],
			$prepare_data->args['post_ids']
		);

		// If valid term IDs, permalinks should be added to URLs.
		$term = wp_insert_term( 'test', 'category' );

		$expected = [
			'urls'     => [
				\AMP_Prepare_Data::normalize_url_for_storage(
					get_term_link( $term['term_id'] )
				),
			],
			'term_ids' => [
				$term['term_id'],
			],
		];

		$prepare_data = new \AMP_Prepare_Data( $expected );

		$this->assertSame(
			$expected['urls'],
			$prepare_data->urls
		);

		// If valid post IDs, permalinks should be added to URLs.
		$post_id = wp_insert_post(
			[
				'post_title'  => 'test',
				'post_status' => 'publish',
			]
		);

		$expected = [
			'urls'     => [
				\AMP_Prepare_Data::normalize_url_for_storage(
					get_permalink( $post_id )
				),
			],
			'post_ids' => [
				$post_id,
			],
		];

		$prepare_data = new \AMP_Prepare_Data( $expected );

		$this->assertSame(
			$expected['urls'],
			$prepare_data->urls
		);
	}

	/**
	 * Test normalize_url_for_storage method.
	 *
	 * @covers \AMP_Prepare_Data::normalize_url_for_storage()
	 */
	public function test_normalize_url_for_storage() {
		$url_not_normalized = add_query_arg(
			[
				QueryVar::NOAMP => '',
				'preview_id'    => 123,
			],
			'http://google.com/#anchor'
		);

		$this->assertSame(
			'https://google.com/',
			\AMP_Prepare_Data::normalize_url_for_storage(
				$url_not_normalized
			)
		);
	}
}

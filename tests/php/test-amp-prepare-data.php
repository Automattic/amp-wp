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

	/**
	 * Test get_data method.
	 *
	 * @covers \AMP_Prepare_Data::get_data()
	 */
	public function test_get_data() {
		$this->populate_validation_errors(
			home_url( '/' ),
			[ 'amp' ]
		);

		$pd = new \AMP_Prepare_Data();

		$data = $pd->get_data();

		$this->assertSame(
			\AMP_Prepare_Data::get_home_url(),
			$data['site_url']
		);
		$this->assertSame(
			$pd->get_site_info(),
			$data['site_info']
		);
		$this->assertSame(
			$pd->get_plugin_info(),
			$data['plugins']
		);
		$this->assertSame(
			$pd->get_theme_info(),
			$data['themes']
		);
		$this->assertSame(
			'bad',
			$data['errors'][0]['code']
		);
		$this->assertTrue(
			! empty( $data['errors'][0]['error_slug'] )
		);
		$this->assertSame(
			$data['error_sources'][0]['error_slug'],
			$data['errors'][0]['error_slug']
		);
		$this->assertSame(
			'amp',
			$data['error_sources'][0]['name']
		);
		$this->assertSame(
			'plugin',
			$data['error_sources'][0]['type']
		);
		$this->assertSame(
			\AMP_Prepare_Data::normalize_url_for_storage(
				home_url( '/' )
			),
			$data['urls'][0]['url']
		);
		$this->assertSame(
			1,
			count( $data['urls'][0]['errors'] )
		);
		$this->assertTrue(
			is_array( $data['error_log'] )
		);
		$this->assertTrue(
			empty( $data['error_log']['contents'] )
		);
	}

	/**
	 * Populate sample validation errors.
	 *
	 * @param string   $url               URL to populate errors for. Defaults to the home URL.
	 * @param string[] $plugin_file_slugs Plugin file slugs.
	 * @return int ID for amp_validated_url post.
	 */
	private function populate_validation_errors( $url, $plugin_file_slugs ) {
		if ( ! $url ) {
			$url = home_url( '/' );
		}

		$errors = array_map(
			static function ( $plugin_file_slug ) {
				return [
					'code'    => 'bad',
					'sources' => [
						[
							'type' => 'plugin',
							'name' => $plugin_file_slug,
						],
					],
				];
			},
			$plugin_file_slugs
		);

		$r = AMP_Validated_URL_Post_Type::store_validation_errors( $errors, $url );
		if ( is_wp_error( $r ) ) {
			throw new Exception( $r->get_error_message() );
		}
		return $r;
	}
}

<?php
/**
 * Tests for AMP_Image_Dimension_Extractor.
 *
 * @package AMP
 */

/**
 * Tests for AMP_Image_Dimension_Extractor.
 *
 * @covers AMP_Image_Dimension_Extractor
 */
class AMP_Image_Dimension_Extractor_Extract_Test extends WP_UnitTestCase {

	/**
	 * Set up.
	 */
	public function setUp() {
		parent::setUp();

		// We don't want to actually download the images; just testing the extract method.
		add_action( 'amp_extract_image_dimensions_batch_callbacks_registered', [ $this, 'disable_downloads' ] );
	}

	/**
	 * Disable downloads.
	 */
	public function disable_downloads() {
		remove_all_filters( 'amp_extract_image_dimensions_batch' );
	}

	/**
	 * Test single url returns expected dimensions and that the normalization runs as expected.
	 *
	 * @covers \AMP_Image_Dimension_Extractor::extract()
	 */
	public function test__single_url() {
		$source_url = 'https://example.com/image.png';
		$cdn_url    = 'https://cdn.example.com/image.png';
		$expected   = [ 100, 101 ];

		add_action(
			'amp_extract_image_dimensions_batch_callbacks_registered',
			static function() use ( $cdn_url ) {
				add_filter(
					'amp_extract_image_dimensions_batch',
					static function() use ( $cdn_url ) {
						return [
							$cdn_url => [
								100,
								101,
							],
						];
					}
				);
			},
			9999 // Run after the `disable_downloads`.
		);

		$ran_filter_count = 0;
		add_filter(
			'amp_normalized_dimension_extractor_image_url',
			function ( $url, $original_url ) use ( $source_url, $cdn_url, &$ran_filter_count ) {
				$this->assertSame( $source_url, $url );
				$this->assertSame( $source_url, $original_url );
				$ran_filter_count++;
				return $cdn_url;
			},
			10,
			2
		);

		$actual = AMP_Image_Dimension_Extractor::extract( $source_url );
		$this->assertSame( 1, $ran_filter_count );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test should return both urls
	 */
	public function test__should_return_both_urls() {
		$source_urls = [
			home_url( '/wp-content/uploads/2018/06/IMG_0183-300x300.jpg' ),
			site_url( '/wp-content/uploads/2018/06/IMG_0183-300x300.jpg' ),
			'/wp-content/uploads/2018/06/IMG_0183-300x300.jpg',
		];
		$expected    = [
			home_url( '/wp-content/uploads/2018/06/IMG_0183-300x300.jpg' ) => false,
			site_url( '/wp-content/uploads/2018/06/IMG_0183-300x300.jpg' ) => false,
			'/wp-content/uploads/2018/06/IMG_0183-300x300.jpg'             => false,
		];

		$actual = AMP_Image_Dimension_Extractor::extract( $source_urls );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test where processed URLs should match originals.
	 */
	public function test__should_return_original_urls() {
		$source_urls = [
			'https://example.com',
			'//example.com/no-protocol',
			'/absolute-url/no-host',
			'data:image/gif;base64,R0lGODl...', // can't normalize.
		];
		$expected    = [
			'https://example.com'              => false,
			'//example.com/no-protocol'        => false,
			'/absolute-url/no-host'            => false,
			'data:image/gif;base64,R0lGODl...' => false,
		];

		$actual = AMP_Image_Dimension_Extractor::extract( $source_urls );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data strings for testing converter.
	 *
	 * @return array
	 */
	public function get_data() {
		$home_url = home_url();

		return [
			'empty_url'         => [
				'',
				false,
			],
			'data_url'          => [
				'data:image/gif;base64,R0lGODl...',
				false,
			],
			'protocol-less_url' => [
				'//example.com/file.jpg',
				'http://example.com/file.jpg',
			],
			'path_only'         => [
				'/path/to/file.png',
				$home_url . '/path/to/file.png',
			],
			'query_only'        => [
				'?file=file.png',
				$home_url . '?file=file.png',
			],
			'path_and_query'    => [
				'/path/file.jpg?query=1',
				$home_url . '/path/file.jpg?query=1',
			],
			'normal_url'        => [
				'https://example.com/path/to/file.jpg',
				'https://example.com/path/to/file.jpg',
			],
		];
	}

	/**
	 * Test normalizing a URL
	 *
	 * @param string $source_url Source.
	 * @param string $expected_url Expected result.
	 *
	 * @dataProvider get_data
	 */
	public function test__normalize_url( $source_url, $expected_url ) {
		$result_url = AMP_Image_Dimension_Extractor::normalize_url( $source_url );

		$this->assertEquals( $expected_url, $result_url );
	}

	/**
	 * Test get_default_user_agent()
	 *
	 * @covers \AMP_Image_Dimension_Extractor::get_default_user_agent()
	 */
	public function test__amp_wp_user_agent() {
		$expected   = 'amp-wp, v' . AMP__VERSION . ', ';
		$user_agent = AMP_Image_Dimension_Extractor::get_default_user_agent();
		$user_agent = substr( $user_agent, 0, strlen( $expected ) );

		$this->assertEquals( $expected, $user_agent );
	}

	/**
	 * @covers \AMP_Image_Dimension_Extractor::extract_by_filename_or_filesystem
	 * @covers \AMP_Image_Dimension_Extractor::get_attachment_id_from_path
	 */
	public function test_extract_by_filename_or_filesystem() {

		$attachment_id = $this->factory()->attachment->create_upload_object( __DIR__ . '/data/images/wordpress-logo.png' );

		$full_image       = wp_get_attachment_image_src( $attachment_id, 'full' );
		$thumbnail_image  = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
		$external_image   = 'https://via.placeholder.com/1500/000.png/FF0';
		$external_image_2 = 'https://via.placeholder.com/1000/000.png/FF0';

		$expected = [
			$full_image[0]      => [
				'width'  => $full_image[1],
				'height' => $full_image[2],
			],
			$thumbnail_image[0] => [
				'width'  => $thumbnail_image[1],
				'height' => $thumbnail_image[2],
			],
			$external_image     => [],
			$external_image_2   => [
				'width'  => 1000,
				'height' => 1000,
			],
		];

		$input = [
			$full_image[0]      => [],
			$thumbnail_image[0] => [],
			$external_image     => [],
			$external_image_2   => [
				'width'  => 1000,
				'height' => 1000,
			],
		];

		$this->assertEmpty( AMP_Image_Dimension_Extractor::extract_by_filename_or_filesystem( [] ) );

		$output = AMP_Image_Dimension_Extractor::extract_by_filename_or_filesystem( $input );
		$this->assertEquals( $expected, $output );

		// Check after removing attachment metadata, To check fallback to filesystem.

		wp_update_attachment_metadata( $attachment_id, [] );

		$output = AMP_Image_Dimension_Extractor::extract_by_filename_or_filesystem( $input );
		$this->assertEquals( $expected, $output );
	}
}

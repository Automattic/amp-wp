<?php
/**
 * Class AMP_Vimeo_Embed_Test
 *
 * @package AMP
 */

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

/**
 * Class AMP_Vimeo_Embed_Handler_Test
 *
 * @covers AMP_Vimeo_Embed_Handler
 */
class AMP_Vimeo_Embed_Handler_Test extends WP_UnitTestCase {

	use WithoutBlockPreRendering;

	/**
	 * Get conversion data.
	 *
	 * @return array
	 */
	public function get_conversion_data() {
		return [
			'no_embed'     => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],

			'url_simple'   => [
				'https://vimeo.com/172355597' . PHP_EOL,
				'<amp-vimeo data-videoid="172355597" layout="responsive" width="600" height="338"></amp-vimeo>' . PHP_EOL,
			],

			'url_unlisted' => [
				'https://vimeo.com/172355597/abcdef0123' . PHP_EOL,
				'<amp-vimeo data-videoid="172355597" layout="responsive" width="600" height="338"></amp-vimeo>' . PHP_EOL,
			],

			'url_player'   => [
				'https://player.vimeo.com/video/172355597' . PHP_EOL,
				'<amp-vimeo data-videoid="172355597" layout="responsive" width="600" height="338"></amp-vimeo>' . PHP_EOL,
			],
		];
	}

	/**
	 * Test conversion.
	 *
	 * @dataProvider get_conversion_data
	 *
	 * @param string $source   Source.
	 * @param string $expected Expected.
	 */
	public function test__conversion( $source, $expected ) {
		$embed = new AMP_Vimeo_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$content = AMP_DOM_Utils::get_content_from_dom( $dom );

		$this->assertEquals( $expected, $content );
	}

	/**
	 * Get scripts data.
	 *
	 * @return array Scripts data.
	 */
	public function get_scripts_data() {
		return [
			'not_converted' => [
				'<p>Hello World.</p>',
				[],
			],
			'converted'     => [
				'https://vimeo.com/172355597' . PHP_EOL,
				[ 'amp-vimeo' => true ],
			],
		];
	}

	/**
	 * Test get_scripts().
	 *
	 * @dataProvider get_scripts_data
	 * @covers AMP_Vimeo_Embed_Handler::get_scripts()
	 *
	 * @param string $source   Source.
	 * @param string $expected Expected.
	 */
	public function test__get_scripts( $source, $expected ) {
		$embed = new AMP_Vimeo_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$validating_sanitizer = new AMP_Tag_And_Attribute_Sanitizer( $dom );
		$validating_sanitizer->sanitize();

		$scripts = array_merge(
			$embed->get_scripts(),
			$validating_sanitizer->get_scripts()
		);

		$this->assertEquals( $expected, $scripts );
	}
}

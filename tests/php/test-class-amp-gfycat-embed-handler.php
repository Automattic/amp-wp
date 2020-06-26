<?php
/**
 * Test Gfycat embed.
 *
 * @package AMP.
 */

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

/**
 * Class AMP_Gfycat_Embed_Handler_Test
 *
 * @covers AMP_Gfycat_Embed_Handler
 */
class AMP_Gfycat_Embed_Handler_Test extends WP_UnitTestCase {

	use WithoutBlockPreRendering {
		setUp as public prevent_block_pre_render;
	}

	/**
	 * Set up.
	 */
	public function setUp() {
		$this->prevent_block_pre_render();

		// Mock the HTTP request.
		add_filter(
			'pre_oembed_result',
			static function( $pre, $url ) {
				if ( in_array( 'external-http', $_SERVER['argv'], true ) ) {
					return $pre;
				}

				if ( false === strpos( $url, 'tautwhoppingcougar' ) ) {
					return $pre;
				}
				return '<iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" title="Melanie Raccoon riding bike-side angle (reddit)" src=\'https://gfycat.com/ifr/tautwhoppingcougar#?secret=Brq0P9wYCr\' data-secret=\'Brq0P9wYCr\' frameborder=\'0\' scrolling=\'no\' width=\'100%\' height=\'100%\'></iframe>';
			},
			10,
			2
		);
	}

	/**
	 * Get conversion data.
	 *
	 * @return array
	 */
	public function get_conversion_data() {
		return [
			'no_embed'        => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],

			'url_simple'      => [
				'https://gfycat.com/tautwhoppingcougar' . PHP_EOL,
				'<amp-gfycat data-gfyid="tautwhoppingcougar" layout="fill"></amp-gfycat>' . PHP_EOL,
			],

			'url_with_detail' => [
				'https://gfycat.com/gifs/detail/tautwhoppingcougar' . PHP_EOL,
				'<amp-gfycat data-gfyid="tautwhoppingcougar" layout="fill"></amp-gfycat>' . PHP_EOL,
			],

			'url_with_params' => [
				'https://gfycat.com/gifs/detail/tautwhoppingcougar?foo=bar' . PHP_EOL,
				'<amp-gfycat data-gfyid="tautwhoppingcougar" layout="fill"></amp-gfycat>' . PHP_EOL,
			],

		];
	}

	/**
	 * Test conversion.
	 *
	 * @param string $source Source.
	 * @param string $expected Expected.
	 * @dataProvider get_conversion_data
	 */
	public function test__conversion( $source, $expected ) {
		$embed = new AMP_Gfycat_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$content = AMP_DOM_Utils::get_content_from_dom( $dom );

		$this->assertEquals( $expected, $content );
	}

	/**
	 * Get scripts data.
	 *
	 * @return array
	 */
	public function get_scripts_data() {
		return [
			'not_converted' => [
				'<p>Hello World.</p>',
				[],
			],
			'converted'     => [
				'https://www.gfycat.com/gifs/detail/tautwhoppingcougar' . PHP_EOL,
				[ 'amp-gfycat' => true ],
			],
		];
	}

	/**
	 * Test scripts.
	 *
	 * @param string $source Source.
	 * @param string $expected Expected.
	 * @dataProvider get_scripts_data
	 */
	public function test__get_scripts( $source, $expected ) {
		$embed = new AMP_Gfycat_Embed_Handler();

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

<?php
/**
 * Test Hulu embed.
 *
 * @package AMP.
 */

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

/**
 * Class AMP_Hulu_Embed_Handler_Test
 *
 * @covers AMP_Hulu_Embed_Handler
 */
class AMP_Hulu_Embed_Handler_Test extends WP_UnitTestCase {

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
			'pre_http_request',
			static function( $pre, $r, $url ) {
				if ( self::is_external_http_test_suite() ) {
					return $pre;
				}

				if ( false === strpos( $url, '771496' ) ) {
					return $pre;
				}
				return [
					'body'     => '{"title":"Out of the Box / Run Down Race Car (Doc McStuffins)","author_name":"Disney Junior","type":"video","provider_name":"Hulu","air_date":"Fri Mar 23 00:00:00 UTC 2012","embed_url":"//www.hulu.com/embed.html?eid=_hHzwnAcj3RrXMJFDDvkuw","thumbnail_url":"http://ib.huluim.com/video/60528019?size=240x180&caller=h1o&img=i","width":500,"thumbnail_width":500,"provider_url":"//www.hulu.com/","thumbnail_height":375,"cache_age":3600,"version":"1.0","large_thumbnail_url":"http://ib.huluim.com/video/60528019?size=512x288&caller=h1o&img=i","height":289,"large_thumbnail_width":512,"html":"<iframe width=\\"500\\" height=\\"289\\" src=\\"//www.hulu.com/embed.html?eid=_hHzwnAcj3RrXMJFDDvkuw\\" frameborder=\\"0\\" scrolling=\\"no\\" webkitAllowFullScreen mozallowfullscreen allowfullscreen> </iframe>","duration":1446.25,"large_thumbnail_height":288}',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			},
			10,
			3
		);
	}

	/**
	 * Whether external-http test suite is running.
	 *
	 * @return bool Running external-http test suite.
	 */
	private static function is_external_http_test_suite() {
		return in_array( 'external-http', $_SERVER['argv'], true );
	}

	/**
	 * Get conversion data.
	 *
	 * @return array
	 */
	public function get_conversion_data() {
		return [
			'url_simple'      => [
				'https://www.hulu.com/watch/771496',
				'<amp-hulu data-eid="_hHzwnAcj3RrXMJFDDvkuw" layout="responsive" width="500" height="289"></amp-hulu>' . PHP_EOL,
			],

			'url_with_params' => [
				'https://www.hulu.com/watch/771496?foo=bar',
				'<amp-hulu data-eid="_hHzwnAcj3RrXMJFDDvkuw" layout="responsive" width="500" height="289"></amp-hulu>' . PHP_EOL,
			],

		];
	}

	/**
	 * Test conversion.
	 *
	 * @param string $url      URL.
	 * @param string $expected Expected.
	 * @dataProvider get_conversion_data
	 */
	public function test__conversion( $url, $expected ) {
		$embed = new AMP_Hulu_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $url );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		if ( self::is_external_http_test_suite() && "<p>$url</p>" === trim( $filtered_content ) ) {
			$this->markTestSkipped( 'Endpoint is down.' );
		}

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
				'https://www.hulu.com/watch/771496',
				[ 'amp-hulu' => true ],
			],
		];
	}

	/**
	 * Test scripts.
	 *
	 * @param string $url      URL.
	 * @param string $expected Expected.
	 * @dataProvider get_scripts_data
	 */
	public function test__get_scripts( $url, $expected ) {
		$embed = new AMP_Hulu_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $url );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		if ( self::is_external_http_test_suite() && "<p>$url</p>" === trim( $filtered_content ) ) {
			$this->markTestSkipped( 'Endpoint is down.' );
		}

		$validating_sanitizer = new AMP_Tag_And_Attribute_Sanitizer( $dom );
		$validating_sanitizer->sanitize();

		$scripts = array_merge(
			$embed->get_scripts(),
			$validating_sanitizer->get_scripts()
		);

		$this->assertEquals( $expected, $scripts );
	}
}

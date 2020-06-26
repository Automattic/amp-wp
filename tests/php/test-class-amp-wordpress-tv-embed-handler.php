<?php
/**
 * Tests for AMP_WordPress_TV_Embed_Handler.
 *
 * @package AMP
 * @since 1.4
 */

use AmpProject\AmpWP\Tests\AssertContainsCompatibility;
use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

/**
 * Tests for AMP_WordPress_TV_Embed_Handler.
 *
 * @package AMP
 * @covers AMP_WordPress_TV_Embed_Handler
 */
class Test_AMP_WordPress_TV_Embed_Handler extends WP_UnitTestCase {

	use AssertContainsCompatibility;
	use WithoutBlockPreRendering {
		setUp as public prevent_block_pre_render;
	}

	/**
	 * Set up.
	 */
	public function setUp() {
		$this->prevent_block_pre_render();

		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
	}

	/**
	 * Mock HTTP request.
	 *
	 * @param mixed  $preempt Whether to preempt an HTTP request's return value. Default false.
	 * @param mixed  $r       HTTP request arguments.
	 * @param string $url     The request URL.
	 * @return array Response data.
	 */
	public function mock_http_request( $preempt, $r, $url ) {
		if ( in_array( 'external-http', $_SERVER['argv'], true ) ) {
			return $preempt;
		}

		if ( false === strpos( $url, 'wordpress.tv' ) ) {
			return $preempt;
		}

		return [
			'body'          => '{"type":"video","version":"1.0","title":null,"width":500,"height":281,"html":"<iframe width=\'500\' height=\'281\' src=\'https:\/\/video.wordpress.com\/embed\/yFCmLMGL?hd=1\' frameborder=\'0\' allowfullscreen><\/iframe><script src=\'https:\/\/v0.wordpress.com\/js\/next\/videopress-iframe.js?m=1435166243\'><\/script>"}', // phpcs:ignore
			'headers'       => [],
			'response'      => [
				'code'    => 200,
				'message' => 'ok',
			],
			'cookies'       => [],
			'http_response' => null,
		];
	}

	/**
	 * Gets the test data for test_conversion().
	 *
	 * @return array The test data.
	 */
	public function get_conversion_data() {
		return [
			'no_embed'                      => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],

			'wrong_embed_url_wordpress_com' => [
				'https://wordpress.com/123',
				'<p>https://wordpress.com/123</p>' . PHP_EOL,
			],

			'correct_url'                   => [
				'https://wordpress.tv/2019/10/08/the-history-of-wordpress-in-four-minutes/',
				'<iframe width="500" height="281" src="https://video.wordpress.com/embed/yFCmLMGL?hd=1" frameborder="0" allowfullscreen layout="responsive"></iframe>' . PHP_EOL,
			],
		];
	}

	/**
	 * Test conversion.
	 *
	 * @dataProvider get_conversion_data
	 *
	 * @param string $source Source.
	 * @param string $expected The expected return value.
	 */
	public function test_conversion( $source, $expected ) {
		$embed = new AMP_WordPress_TV_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$content = AMP_DOM_Utils::get_content_from_dom( $dom );

		$this->assertEquals( $expected, $content );
	}
}

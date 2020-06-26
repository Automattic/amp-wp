<?php

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

class AMP_Issuu_Embed_Handler_Test extends WP_UnitTestCase {

	use WithoutBlockPreRendering {
		setUp as prevent_block_pre_render;
	}

	/**
	 * Set up.
	 */
	public function setUp() {
		$this->prevent_block_pre_render();

		// Mock the HTTP request.
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
	}

	/**
	 * Tear down.
	 */
	public function tearDown() {
		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );
		parent::tearDown();
	}

	/**
	 * Mock HTTP request.
	 *
	 * @param mixed  $pre Whether to preempt an HTTP request's return value. Default false.
	 * @param mixed  $r   HTTP request arguments.
	 * @param string $url The request URL.
	 * @return array Response data.
	 */
	public function mock_http_request( $pre, $r, $url ) {
		if ( in_array( 'external-http', $_SERVER['argv'], true ) ) {
			return $pre;
		}

		if ( false === strpos( $url, 'issuu.com' ) ) {
			return $pre;
		}

		$body = '{"version":"1.0","type":"rich","width":500,"height":324,"title":"A Seat at the Table Syllabus","description":"","url":"https://issuu.com/ajcwfu/docs/seatatthetablefinal","author_name":"ajcwfu","author_url":"https://issuu.com/ajcwfu","provider_name":"Issuu","provider_url":"https://issuu.com","html":"<div data-url=\"https://issuu.com/ajcwfu/docs/seatatthetablefinal\" style=\"width: 500px; height: 324px;\" class=\"issuuembed\"></div><script type=\"text/javascript\" src=\"//e.issuu.com/embed.js\" async=\"true\"></script>","thumbnail_url":"https://image.issuu.com/170209231446-0e77cd67a4a596188ee591ddd6dbdb61/jpg/page_1_thumb_large.jpg","thumbnail_width":320,"thumbnail_height":414}'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

		return [
			'body'     => $body,
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
		];
	}

	public function get_conversion_data() {
		return [
			'no_embed'  => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],

			'url_embed' => [
				'https://issuu.com/ajcwfu/docs/seatatthetablefinal' . PHP_EOL,
				'<amp-iframe width="500" height="324" src="https://issuu.com/ajcwfu/docs/seatatthetablefinal" sandbox="allow-scripts allow-same-origin"></amp-iframe>' . PHP_EOL . PHP_EOL,
			],
		];
	}

	/**
	 * @dataProvider get_conversion_data
	 */
	public function test__conversion( $source, $expected ) {
		$embed = new AMP_Issuu_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$content = AMP_DOM_Utils::get_content_from_dom( $dom );

		$this->assertEquals( $expected, $content );
	}
}

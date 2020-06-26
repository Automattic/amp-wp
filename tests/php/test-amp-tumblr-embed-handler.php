<?php

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

class AMP_Tumblr_Embed_Handler_Test extends WP_UnitTestCase {

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

		if ( false === strpos( $url, 'tumblr.com' ) ) {
			return $pre;
		}

		$body = '{"cache_age":3600,"url":"https:\/\/ifpaintingscouldtext.tumblr.com\/post\/92003045635\/grant-wood-american-gothic-1930","provider_url":"https:\/\/www.tumblr.com","provider_name":"Tumblr","author_name":"If Paintings Could Text","version":"1.0","author_url":"https:\/\/ifpaintingscouldtext.tumblr.com\/","type":"rich","html":"\u003Cdiv class=\u0022tumblr-post\u0022 data-href=\u0022https:\/\/embed.tumblr.com\/embed\/post\/2JT2XTaiTxO08wh21dqQrw\/92003045635\u0022 data-did=\u00227ce4825965cbd8bfd208f6aae43de7a528859aee\u0022  \u003E\u003Ca href=\u0022https:\/\/ifpaintingscouldtext.tumblr.com\/post\/92003045635\/grant-wood-american-gothic-1930\u0022\u003Ehttps:\/\/ifpaintingscouldtext.tumblr.com\/post\/92003045635\/grant-wood-american-gothic-1930\u003C\/a\u003E\u003C\/div\u003E\u003Cscript async src=\u0022https:\/\/assets.tumblr.com\/post.js\u0022\u003E\u003C\/script\u003E","height":null,"width":540}';

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
			'no_embed'   => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],

			'url_simple' => [
				'https://ifpaintingscouldtext.tumblr.com/post/92003045635/grant-wood-american-gothic-1930' . PHP_EOL,
				'<amp-iframe src="https://embed.tumblr.com/embed/post/2JT2XTaiTxO08wh21dqQrw/92003045635" layout="responsive" width="540" height="480" resizable="" sandbox="allow-scripts allow-popups allow-same-origin"><div overflow="" tabindex="0" role="button" aria-label="See more">See more</div><a href="https://ifpaintingscouldtext.tumblr.com/post/92003045635/grant-wood-american-gothic-1930" placeholder="">https://ifpaintingscouldtext.tumblr.com/post/92003045635/grant-wood-american-gothic-1930</a></amp-iframe>' . PHP_EOL . PHP_EOL,
			],
		];
	}

	/**
	 * @dataProvider get_conversion_data
	 */
	public function test__conversion( $source, $expected ) {
		$embed = new AMP_Tumblr_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$content = AMP_DOM_Utils::get_content_from_dom( $dom );

		$this->assertEquals( $expected, $content );
	}

	public function get_scripts_data() {
		return [
			'not_converted' => [
				'<p>Hello World.</p>',
				[],
			],
			'converted'     => [
				'https://ifpaintingscouldtext.tumblr.com/post/92003045635/grant-wood-american-gothic-1930' . PHP_EOL,
				[ 'amp-iframe' => true ],
			],
		];
	}

	/**
	 * @dataProvider get_scripts_data
	 */
	public function test__get_scripts( $source, $expected ) {
		$embed = new AMP_Tumblr_Embed_Handler();

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

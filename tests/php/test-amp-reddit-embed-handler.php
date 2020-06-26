<?php

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

class AMP_Reddit_Embed_Handler_Test extends WP_UnitTestCase {

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

		if ( false === strpos( $url, 'reddit.com' ) ) {
			return $pre;
		}

		$body = '{"provider_url": "https://www.reddit.com/", "version": "1.0", "title": "Sticky bun for you!", "provider_name": "reddit", "type": "rich", "html": "\n    \u003Cblockquote class=\"reddit-card\" \u003E\n      \u003Ca href=\"https://www.reddit.com/r/aww/comments/gnl55y/sticky_bun_for_you/?ref_source=embed\u0026amp;ref=share\"\u003ESticky bun for you!\u003C/a\u003E from\n      \u003Ca href=\"https://www.reddit.com/r/aww/\"\u003Eaww\u003C/a\u003E\n    \u003C/blockquote\u003E\n    \u003Cscript async src=\"https://embed.redditmedia.com/widgets/platform.js\" charset=\"UTF-8\"\u003E\u003C/script\u003E\n", "author_name": "rnielsen776"}';

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
				'https://www.reddit.com/r/aww/comments/gnl55y/sticky_bun_for_you/' . PHP_EOL,
				'<amp-embedly-card layout="responsive" width="100" height="100" data-url="https://www.reddit.com/r/aww/comments/gnl55y/sticky_bun_for_you/?ref_source=embed&amp;ref=share"><blockquote class="reddit-card" placeholder=""><p>' . PHP_EOL . '      <a href="https://www.reddit.com/r/aww/comments/gnl55y/sticky_bun_for_you/?ref_source=embed&amp;ref=share">Sticky bun for you!</a> from<br>' . PHP_EOL . '      <a href="https://www.reddit.com/r/aww/">aww</a>' . PHP_EOL . '    </p></blockquote></amp-embedly-card>' . PHP_EOL . PHP_EOL,
			],

			'shortcode'  => [
				'[embed]https://www.reddit.com/r/aww/comments/gnl55y/sticky_bun_for_you/[/embed]' . PHP_EOL,
				'<amp-embedly-card layout="responsive" width="100" height="100" data-url="https://www.reddit.com/r/aww/comments/gnl55y/sticky_bun_for_you/?ref_source=embed&amp;ref=share"><blockquote class="reddit-card" placeholder=""><p>' . PHP_EOL . '      <a href="https://www.reddit.com/r/aww/comments/gnl55y/sticky_bun_for_you/?ref_source=embed&amp;ref=share">Sticky bun for you!</a> from<br>' . PHP_EOL . '      <a href="https://www.reddit.com/r/aww/">aww</a>' . PHP_EOL . '    </p></blockquote></amp-embedly-card>' . PHP_EOL . PHP_EOL,
			],
		];
	}

	/**
	 * @dataProvider get_conversion_data
	 */
	public function test__conversion( $source, $expected ) {
		$embed = new AMP_Reddit_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );

		if ( "<br />\n" === substr( $filtered_content, 0, 7 ) ) {
			// Remove prepended break line tag and new line.
			$filtered_content = substr( $filtered_content, 7 );
		}

		$dom = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
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
				'https://www.reddit.com/r/aww/comments/gnl55y/sticky_bun_for_you/' . PHP_EOL,
				[ 'amp-embedly-card' => true ],
			],
		];
	}

	/**
	 * @dataProvider get_scripts_data
	 */
	public function test__get_scripts( $source, $expected ) {
		$embed = new AMP_Reddit_Embed_Handler();

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

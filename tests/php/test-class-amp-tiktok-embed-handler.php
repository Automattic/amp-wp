<?php
/**
 * Test TikTok embed.
 *
 * @package AMP.
 */

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

/**
 * Class Test_AMP_TikTok_Embed_Handler
 */
class Test_AMP_TikTok_Embed_Handler extends WP_UnitTestCase {

	use WithoutBlockPreRendering {
		setUp as public prevent_block_pre_render;
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
	 * After a test method runs, reset any state in WordPress the test method might have changed.
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

		if ( false === strpos( $url, 'tiktok.com' ) ) {
			return $pre;
		}

		$body = '{"version":"1.0","type":"video","title":"Scramble up ur name & I’ll try to guess it😍❤️ #foryoupage #petsoftiktok #aesthetic","author_url":"https://www.tiktok.com/@scout2015","author_name":"Scout & Suki","width":"100%","height":"100%","html":"<blockquote class=\"tiktok-embed\" cite=\"https://www.tiktok.com/@scout2015/video/6718335390845095173\" data-video-id=\"6718335390845095173\" style=\"max-width: 605px;min-width: 325px;\" > <section> <a target=\"_blank\" title=\"@scout2015\" href=\"https://www.tiktok.com/@scout2015\">@scout2015</a> <p>Scramble up ur name & I’ll try to guess it😍❤️ <a title=\"foryoupage\" target=\"_blank\" href=\"https://www.tiktok.com/tag/foryoupage\">#foryoupage</a> <a title=\"petsoftiktok\" target=\"_blank\" href=\"https://www.tiktok.com/tag/petsoftiktok\">#petsoftiktok</a> <a title=\"aesthetic\" target=\"_blank\" href=\"https://www.tiktok.com/tag/aesthetic\">#aesthetic</a></p> <a target=\"_blank\" title=\"♬ original sound - 𝐇𝐚𝐰𝐚𝐢𝐢𓆉\" href=\"https://www.tiktok.com/music/original-sound-6689804660171082501\">♬ original sound - 𝐇𝐚𝐰𝐚𝐢𝐢𓆉</a> </section> </blockquote> <script async src=\"https://www.tiktok.com/embed.js\"></script>","thumbnail_width":720,"thumbnail_height":1280,"thumbnail_url":"https://p16.muscdn.com/obj/tos-maliva-p-0068/06kv6rfcesljdjr45ukb0000d844090v0200000a05","provider_url":"https://www.tiktok.com","provider_name":"TikTok"}'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

		return [
			'body'     => $body,
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
		];
	}

	/**
	 * Get conversion data.
	 *
	 * @return array
	 */
	public function get_conversion_data() {
		return [
			'no_embed'   => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],

			'url_simple' => [
				'https://www.tiktok.com/@scout2015/video/6718335390845095173' . PHP_EOL,

				'<amp-embedly-card layout="responsive" height="700" width="340" data-card-controls="0" data-url="https://www.tiktok.com/@scout2015/video/6718335390845095173"><section placeholder=""> <a target="_blank" title="@scout2015" href="https://www.tiktok.com/@scout2015">@scout2015</a> ' . PHP_EOL .
				'<p>Scramble up ur name &amp; I’ll try to guess it😍❤️ <a title="foryoupage" target="_blank" href="https://www.tiktok.com/tag/foryoupage">#foryoupage</a> <a title="petsoftiktok" target="_blank" href="https://www.tiktok.com/tag/petsoftiktok">#petsoftiktok</a> <a title="aesthetic" target="_blank" href="https://www.tiktok.com/tag/aesthetic">#aesthetic</a></p>' . PHP_EOL .
				'<p> <a target="_blank" title="♬ original sound - 𝐇𝐚𝐰𝐚𝐢𝐢𓆉" href="https://www.tiktok.com/music/original-sound-6689804660171082501">♬ original sound – 𝐇𝐚𝐰𝐚𝐢𝐢𓆉</a> </p></section></amp-embedly-card>' . PHP_EOL . PHP_EOL,
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
	public function test_conversion( $source, $expected ) {
		if ( version_compare( '5.4-alpha', get_bloginfo( 'version' ), '>' ) ) {
			$this->markTestSkipped( 'The TikTok embed is only available in 5.4-alpha and above.' );
		}

		$embed = new AMP_TikTok_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$content = AMP_DOM_Utils::get_content_from_dom( $dom );

		$this->assertEquals( $expected, $content );
	}
}

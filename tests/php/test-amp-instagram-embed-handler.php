<?php

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

class AMP_Instagram_Embed_Handler_Test extends WP_UnitTestCase {

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

		if ( false === strpos( $url, '7-l0z_p4A4' ) ) {
			return $pre;
		}

		$body = '{"version": "1.0", "title": "Ice Bear requires more sprinkles.\ud83c\udf66#lastdaysofsummer #webarebears", "author_name": "cartoonnetworkofficial", "author_url": "https://www.instagram.com/cartoonnetworkofficial", "author_id": 1385592527, "media_id": "1080467317577973816_1385592527", "provider_name": "Instagram", "provider_url": "https://www.instagram.com", "type": "rich", "width": 658, "height": null, "html": "\u003cblockquote class=\"instagram-media\" data-instgrm-captioned data-instgrm-permalink=\"https://www.instagram.com/p/7-l0z_p4A4/?utm_source=ig_embed\u0026amp;utm_campaign=loading\" data-instgrm-version=\"12\" style=\" background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:658px; min-width:326px; padding:0; width:99.375%; width:-webkit-calc(100% - 2px); width:calc(100% - 2px);\"\u003e\u003cdiv style=\"padding:16px;\"\u003e \u003ca href=\"https://www.instagram.com/p/7-l0z_p4A4/?utm_source=ig_embed\u0026amp;utm_campaign=loading\" style=\" background:#FFFFFF; line-height:0; padding:0 0; text-align:center; text-decoration:none; width:100%;\" target=\"_blank\"\u003e \u003cdiv style=\" display: flex; flex-direction: row; align-items: center;\"\u003e \u003cdiv style=\"background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 40px; margin-right: 14px; width: 40px;\"\u003e\u003c/div\u003e \u003cdiv style=\"display: flex; flex-direction: column; flex-grow: 1; justify-content: center;\"\u003e \u003cdiv style=\" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 100px;\"\u003e\u003c/div\u003e \u003cdiv style=\" background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 60px;\"\u003e\u003c/div\u003e\u003c/div\u003e\u003c/div\u003e\u003cdiv style=\"padding: 19% 0;\"\u003e\u003c/div\u003e \u003cdiv style=\"display:block; height:50px; margin:0 auto 12px; width:50px;\"\u003e\u003csvg width=\"50px\" height=\"50px\" viewBox=\"0 0 60 60\" version=\"1.1\" xmlns=\"https://www.w3.org/2000/svg\" xmlns:xlink=\"https://www.w3.org/1999/xlink\"\u003e\u003cg stroke=\"none\" stroke-width=\"1\" fill=\"none\" fill-rule=\"evenodd\"\u003e\u003cg transform=\"translate(-511.000000, -20.000000)\" fill=\"#000000\"\u003e\u003cg\u003e\u003cpath d=\"M556.869,30.41 C554.814,30.41 553.148,32.076 553.148,34.131 C553.148,36.186 554.814,37.852 556.869,37.852 C558.924,37.852 560.59,36.186 560.59,34.131 C560.59,32.076 558.924,30.41 556.869,30.41 M541,60.657 C535.114,60.657 530.342,55.887 530.342,50 C530.342,44.114 535.114,39.342 541,39.342 C546.887,39.342 551.658,44.114 551.658,50 C551.658,55.887 546.887,60.657 541,60.657 M541,33.886 C532.1,33.886 524.886,41.1 524.886,50 C524.886,58.899 532.1,66.113 541,66.113 C549.9,66.113 557.115,58.899 557.115,50 C557.115,41.1 549.9,33.886 541,33.886 M565.378,62.101 C565.244,65.022 564.756,66.606 564.346,67.663 C563.803,69.06 563.154,70.057 562.106,71.106 C561.058,72.155 560.06,72.803 558.662,73.347 C557.607,73.757 556.021,74.244 553.102,74.378 C549.944,74.521 548.997,74.552 541,74.552 C533.003,74.552 532.056,74.521 528.898,74.378 C525.979,74.244 524.393,73.757 523.338,73.347 C521.94,72.803 520.942,72.155 519.894,71.106 C518.846,70.057 518.197,69.06 517.654,67.663 C517.244,66.606 516.755,65.022 516.623,62.101 C516.479,58.943 516.448,57.996 516.448,50 C516.448,42.003 516.479,41.056 516.623,37.899 C516.755,34.978 517.244,33.391 517.654,32.338 C518.197,30.938 518.846,29.942 519.894,28.894 C520.942,27.846 521.94,27.196 523.338,26.654 C524.393,26.244 525.979,25.756 528.898,25.623 C532.057,25.479 533.004,25.448 541,25.448 C548.997,25.448 549.943,25.479 553.102,25.623 C556.021,25.756 557.607,26.244 558.662,26.654 C560.06,27.196 561.058,27.846 562.106,28.894 C563.154,29.942 563.803,30.938 564.346,32.338 C564.756,33.391 565.244,34.978 565.378,37.899 C565.522,41.056 565.552,42.003 565.552,50 C565.552,57.996 565.522,58.943 565.378,62.101 M570.82,37.631 C570.674,34.438 570.167,32.258 569.425,30.349 C568.659,28.377 567.633,26.702 565.965,25.035 C564.297,23.368 562.623,22.342 560.652,21.575 C558.743,20.834 556.562,20.326 553.369,20.18 C550.169,20.033 549.148,20 541,20 C532.853,20 531.831,20.033 528.631,20.18 C525.438,20.326 523.257,20.834 521.349,21.575 C519.376,22.342 517.703,23.368 516.035,25.035 C514.368,26.702 513.342,28.377 512.574,30.349 C511.834,32.258 511.326,34.438 511.181,37.631 C511.035,40.831 511,41.851 511,50 C511,58.147 511.035,59.17 511.181,62.369 C511.326,65.562 511.834,67.743 512.574,69.651 C513.342,71.625 514.368,73.296 516.035,74.965 C517.703,76.634 519.376,77.658 521.349,78.425 C523.257,79.167 525.438,79.673 528.631,79.82 C531.831,79.965 532.853,80.001 541,80.001 C549.148,80.001 550.169,79.965 553.369,79.82 C556.562,79.673 558.743,79.167 560.652,78.425 C562.623,77.658 564.297,76.634 565.965,74.965 C567.633,73.296 568.659,71.625 569.425,69.651 C570.167,67.743 570.674,65.562 570.82,62.369 C570.966,59.17 571,58.147 571,50 C571,41.851 570.966,40.831 570.82,37.631\"\u003e\u003c/path\u003e\u003c/g\u003e\u003c/g\u003e\u003c/g\u003e\u003c/svg\u003e\u003c/div\u003e\u003cdiv style=\"padding-top: 8px;\"\u003e \u003cdiv style=\" color:#3897f0; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:550; line-height:18px;\"\u003e View this post on Instagram\u003c/div\u003e\u003c/div\u003e\u003cdiv style=\"padding: 12.5% 0;\"\u003e\u003c/div\u003e \u003cdiv style=\"display: flex; flex-direction: row; margin-bottom: 14px; align-items: center;\"\u003e\u003cdiv\u003e \u003cdiv style=\"background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(0px) translateY(7px);\"\u003e\u003c/div\u003e \u003cdiv style=\"background-color: #F4F4F4; height: 12.5px; transform: rotate(-45deg) translateX(3px) translateY(1px); width: 12.5px; flex-grow: 0; margin-right: 14px; margin-left: 2px;\"\u003e\u003c/div\u003e \u003cdiv style=\"background-color: #F4F4F4; border-radius: 50%; height: 12.5px; width: 12.5px; transform: translateX(9px) translateY(-18px);\"\u003e\u003c/div\u003e\u003c/div\u003e\u003cdiv style=\"margin-left: 8px;\"\u003e \u003cdiv style=\" background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 20px; width: 20px;\"\u003e\u003c/div\u003e \u003cdiv style=\" width: 0; height: 0; border-top: 2px solid transparent; border-left: 6px solid #f4f4f4; border-bottom: 2px solid transparent; transform: translateX(16px) translateY(-4px) rotate(30deg)\"\u003e\u003c/div\u003e\u003c/div\u003e\u003cdiv style=\"margin-left: auto;\"\u003e \u003cdiv style=\" width: 0px; border-top: 8px solid #F4F4F4; border-right: 8px solid transparent; transform: translateY(16px);\"\u003e\u003c/div\u003e \u003cdiv style=\" background-color: #F4F4F4; flex-grow: 0; height: 12px; width: 16px; transform: translateY(-4px);\"\u003e\u003c/div\u003e \u003cdiv style=\" width: 0; height: 0; border-top: 8px solid #F4F4F4; border-left: 8px solid transparent; transform: translateY(-4px) translateX(8px);\"\u003e\u003c/div\u003e\u003c/div\u003e\u003c/div\u003e\u003c/a\u003e \u003cp style=\" margin:8px 0 0 0; padding:0 4px;\"\u003e \u003ca href=\"https://www.instagram.com/p/7-l0z_p4A4/?utm_source=ig_embed\u0026amp;utm_campaign=loading\" style=\" color:#000; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px; text-decoration:none; word-wrap:break-word;\" target=\"_blank\"\u003eIce Bear requires more sprinkles.\ud83c\udf66#lastdaysofsummer #webarebears\u003c/a\u003e\u003c/p\u003e \u003cp style=\" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; line-height:17px; margin-bottom:0; margin-top:8px; overflow:hidden; padding:8px 0 7px; text-align:center; text-overflow:ellipsis; white-space:nowrap;\"\u003eA post shared by \u003ca href=\"https://www.instagram.com/cartoonnetworkofficial/?utm_source=ig_embed\u0026amp;utm_campaign=loading\" style=\" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px;\" target=\"_blank\"\u003e Cartoon Network\u003c/a\u003e (@cartoonnetworkofficial) on \u003ctime style=\" font-family:Arial,sans-serif; font-size:14px; line-height:17px;\" datetime=\"2015-09-23T15:22:43+00:00\"\u003eSep 23, 2015 at 8:22am PDT\u003c/time\u003e\u003c/p\u003e\u003c/div\u003e\u003c/blockquote\u003e\n\u003cscript async src=\"//www.instagram.com/embed.js\"\u003e\u003c/script\u003e", "thumbnail_url": "https://scontent-lga3-1.cdninstagram.com/v/t51.2885-15/sh0.08/e35/s640x640/11906204_1475523516088375_164900015_n.jpg?_nc_ht=scontent-lga3-1.cdninstagram.com\u0026_nc_cat=107\u0026_nc_ohc=eKlJu6SefhcAX_CwEfY\u0026oh=2ad53afd14a8357251b2b945f4ff28a3\u0026oe=5F1369B7", "thumbnail_width": 640, "thumbnail_height": 640}';

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
			'no_embed'                           => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],
			'simple_url'                         => [
				'https://instagram.com/p/7-l0z_p4A4/',
				'<amp-instagram data-shortcode="7-l0z_p4A4" layout="responsive" width="600" height="600" data-captioned=""></amp-instagram>' . PHP_EOL . PHP_EOL,
			],
			'simple_tv_url'                      => [
				'https://instagram.com/tv/7-l0z_p4A4/' . PHP_EOL,
				'<amp-instagram data-shortcode="7-l0z_p4A4" layout="responsive" width="600" height="600" data-captioned=""></amp-instagram>' . PHP_EOL . PHP_EOL,
			],
			'short_url'                          => [
				'https://instagr.am/p/7-l0z_p4A4' . PHP_EOL,
				'<amp-instagram data-shortcode="7-l0z_p4A4" layout="responsive" width="600" height="600" data-captioned=""></amp-instagram>' . PHP_EOL . PHP_EOL,
			],
			'short_tv_url'                       => [
				'https://instagr.am/tv/7-l0z_p4A4' . PHP_EOL,
				'<amp-instagram data-shortcode="7-l0z_p4A4" layout="responsive" width="600" height="600" data-captioned=""></amp-instagram>' . PHP_EOL . PHP_EOL,
			],

			'embed_blockquote_without_instagram' => [
				'<blockquote><p>lorem ipsum</p></blockquote>',
				'<blockquote>' . PHP_EOL . '<p>lorem ipsum</p>' . PHP_EOL . '</blockquote>' . PHP_EOL,
			],

			'blockquote_embed'                   => [
				wpautop( '<blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/p/BhsgU3jh6xE/"><div style="padding: 8px;">Lorem ipsum</div></blockquote> <script async defer src="//www.instagram.com/embed.js"></script>' ), // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine
				'<amp-instagram data-shortcode="BhsgU3jh6xE" layout="responsive" width="600" height="600"></amp-instagram>' . PHP_EOL . PHP_EOL,
			],

			'blockquote_tv_embed'                => [
				wpautop( '<blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/tv/BhsgU3jh6xE/"><div style="padding: 8px;">Lorem ipsum</div></blockquote> <script async defer src="//www.instagram.com/embed.js"></script>' ), // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine
				'<amp-instagram data-shortcode="BhsgU3jh6xE" layout="responsive" width="600" height="600"></amp-instagram>' . PHP_EOL . PHP_EOL,
			],

			'blockquote_embed_with_caption'      => [
				wpautop( '<blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/p/BhsgU3jh6xE/" data-instgrm-captioned><div style="padding: 8px;">Lorem ipsum</div></blockquote> <script async defer src="//www.instagram.com/embed.js"></script>' ), // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine
				'<amp-instagram data-shortcode="BhsgU3jh6xE" layout="responsive" width="600" height="600" data-captioned=""></amp-instagram>' . PHP_EOL . PHP_EOL,
			],
		];
	}

	/**
	 * Test conversion.
	 *
	 * @dataProvider get_conversion_data
	 * @param string $source   Source.
	 * @param string $expected Expected.
	 */
	public function test__conversion( $source, $expected ) {
		$embed = new AMP_Instagram_Embed_Handler();
		$embed->register_embed();

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
				'https://instagram.com/p/7-l0z_p4A4/' . PHP_EOL,
				[ 'amp-instagram' => true ],
			],
		];
	}

	/**
	 * Test get_scripts().
	 *
	 * @dataProvider get_scripts_data
	 * @param string $source   Source.
	 * @param array  $expected Expected.
	 */
	public function test__get_scripts( $source, $expected ) {
		$embed = new AMP_Instagram_Embed_Handler();
		$embed->register_embed();

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

<?php
/**
 * Tests for AMP_YouTube_Embed_Handler.
 *
 * @package AMP
 * @since 0.7
 */

use AmpProject\AmpWP\Tests\AssertContainsCompatibility;
use AmpProject\AmpWP\Tests\PrivateAccess;
use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

/**
 * Tests for AMP_YouTube_Embed_Handler.
 *
 * @covers AMP_YouTube_Embed_Handler
 */
class Test_AMP_YouTube_Embed_Handler extends WP_UnitTestCase {

	use AssertContainsCompatibility;
	use PrivateAccess;
	use WithoutBlockPreRendering {
		setUp as public prevent_block_pre_render;
	}

	protected $youtube_video_id = 'kfVsfOSbJY0';

	/**
	 * Response for YouTube oEmbed request.
	 *
	 * @see Test_AMP_YouTube_Embed_Handler::$youtube_video_id
	 * @var string
	 */
	protected $youtube_oembed_response = '{"height":281,"type":"video","author_name":"rebecca","thumbnail_url":"https:\/\/i.ytimg.com\/vi\/kfVsfOSbJY0\/hqdefault.jpg","provider_url":"https:\/\/www.youtube.com\/","title":"Rebecca Black - Friday","version":"1.0","width":500,"thumbnail_height":360,"html":"\u003ciframe width=\"500\" height=\"281\" src=\"https:\/\/www.youtube.com\/embed\/kfVsfOSbJY0?feature=oembed\" frameborder=\"0\" allow=\"accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen\u003e\u003c\/iframe\u003e","thumbnail_width":500,"provider_name":"YouTube","author_url":"https:\/\/www.youtube.com\/user\/rebecca"}';

	/**
	 * An instance of this embed handler.
	 *
	 * @var AMP_YouTube_Embed_Handler.
	 */
	public $handler;

	/**
	 * Set up each test.
	 */
	public function setUp() {
		$this->prevent_block_pre_render();

		$this->handler = new AMP_YouTube_Embed_Handler();

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
	 * @param mixed  $preempt Whether to preempt an HTTP request's return value. Default false.
	 * @param mixed  $r       HTTP request arguments.
	 * @param string $url     The request URL.
	 * @return array Response data.
	 */
	public function mock_http_request( $preempt, $r, $url ) {
		if ( in_array( 'external-http', $_SERVER['argv'], true ) ) {
			return $preempt;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! in_array( $host, [ 'youtu.be', 'youtube.com', 'www.youtube.com' ], true ) ) {
			return $preempt;
		}

		/**
		 * The URL http://www.youtube.com/watch?v=kfVsfOSbJY0?hl=en&fs=1&w=425&h=349 is invalid as
		 * it has multiple query vars. Checking if `?v=kfVsfOSbJY0?hl=en` is in the URL should be a
		 * sufficient check when that URL is being mocked.
		 */
		if ( false !== strpos( $url, '%3Fv%3DkfVsfOSbJY0%3Fhl%3Den' ) ) {
			return $preempt;
		}

		return [
			'body'          => $this->youtube_oembed_response,
			'headers'       => [],
			'response'      => [
				'code'    => 200,
				'message' => 'ok',
			],
			'cookies'       => [],
			'http_response' => null,
		];
	}

	public function get_conversion_data() {
		return [
			'no_embed'                         => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],

			'url_simple'                       => [
				'https://www.youtube.com/watch?v=kfVsfOSbJY0' . PHP_EOL,
				'<amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" title="Rebecca Black - Friday"><a placeholder="" href="https://www.youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover" alt="Rebecca Black - Friday"></a></amp-youtube>' . PHP_EOL,
				'<amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281"><a placeholder="" href="https://www.youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover"></a></amp-youtube>' . PHP_EOL,
			],

			'url_short'                        => [
				'https://youtu.be/kfVsfOSbJY0' . PHP_EOL,
				'<amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" title="Rebecca Black - Friday"><a placeholder="" href="https://www.youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover" alt="Rebecca Black - Friday"></a></amp-youtube>' . PHP_EOL,
				'<amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281"><a placeholder="" href="https://www.youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover"></a></amp-youtube>' . PHP_EOL,
			],

			'url_with_querystring'             => [
				'http://www.youtube.com/watch?v=kfVsfOSbJY0&hl=en&fs=1&w=425&h=349' . PHP_EOL,
				'<amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" title="Rebecca Black - Friday"><a placeholder="" href="https://www.youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover" alt="Rebecca Black - Friday"></a></amp-youtube>' . PHP_EOL,
				'<amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281"><a placeholder="" href="https://www.youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover"></a></amp-youtube>' . PHP_EOL,
			],

			// Several reports of invalid URLs that have multiple `?` in the URL.
			'url_with_querystring_and_extra_?' => [
				'http://www.youtube.com/watch?v=kfVsfOSbJY0?hl=en&fs=1&w=425&h=349' . PHP_EOL,
				'<p>http://www.youtube.com/watch?v=kfVsfOSbJY0?hl=en&amp;fs=1&amp;w=425&amp;h=349</p>' . PHP_EOL,
			],

			'embed_url'                        => [
				'https://www.youtube.com/embed/kfVsfOSbJY0' . PHP_EOL,
				'<amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" title="Rebecca Black - Friday"><a placeholder="" href="https://www.youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover" alt="Rebecca Black - Friday"></a></amp-youtube>' . PHP_EOL,
				'<amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281"><a placeholder="" href="https://www.youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover"></a></amp-youtube>' . PHP_EOL,
			],
		];
	}

	/**
	 * @dataProvider get_conversion_data
	 */
	public function test__conversion( $source, $expected, $fallback_for_expected = null ) {
		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$this->handler->sanitize_raw_embeds( $dom );

		$content = AMP_DOM_Utils::get_content_from_dom( $dom );

		if (
			version_compare( strtok( get_bloginfo( 'version' ), '-' ), '5.1', '<' )
			&& null !== $fallback_for_expected
		) {
			$this->assertEquals( $fallback_for_expected, $content );
		} else {
			$this->assertEquals( $expected, $content );
		}
	}

	/**
	 * Gets the test data for test_get_video_id_from_url().
	 *
	 * @return array The test data.
	 */
	public function get_video_id_data() {
		return [
			'basic_url'                        => [
				'https://www.youtube.com/watch?v=XOY3ZUO6P0k',
				'XOY3ZUO6P0k',
			],
			'mobile_url'                       => [
				'https://m.youtube.com/watch?v=XOY3ZUO6P0k',
				'XOY3ZUO6P0k',
			],
			'short_url'                        => [
				'https://youtu.be/XOY3ZUO6P0k',
				'XOY3ZUO6P0k',
			],
			'url_with_underscore'              => [
				'https://www.youtube.com/watch?v=CMrv_D78oxY',
				'CMrv_D78oxY',
			],
			'short_url_with_underscore'        => [
				'https://youtu.be/CMrv_D78oxY',
				'CMrv_D78oxY',
			],
			'url_with_hyphen'                  => [
				'https://www.youtube.com/watch?v=xo68-iWaKv8',
				'xo68-iWaKv8',
			],
			'url_with_hyphen_and_query_string' => [
				'https://www.youtube.com/watch?v=xo68-iWaKv8&w=800&h=400',
				'xo68-iWaKv8',
			],
			'url_with_hyphen_and_query_string_dimensions_before_id' => [
				'https://www.youtube.com/watch?w=800&h=400&v=xo68-iWaKv8',
				'xo68-iWaKv8',
			],
			'embed_url'                        => [
				'http://www.youtube.com/embed/XOY3ZUO6P0k',
				'XOY3ZUO6P0k',
			],
			'embed_url_ending_in_query_param'  => [
				'http://www.youtube.com/embed/XOY3ZUO6P0k?rel=0',
				'XOY3ZUO6P0k',
			],
			'v_segment_url'                    => [
				'http://youtube.com/v/XOY3ZUO6P0k',
				'XOY3ZUO6P0k',
			],
			'e_segment_url'                    => [
				'http://youtube.com/e/XOY3ZUO6P0k',
				'XOY3ZUO6P0k',
			],
			'vi_segment_url'                   => [
				'http://youtube.com/vi/XOY3ZUO6P0k',
				'XOY3ZUO6P0k',
			],
			'vi_query_param_url'               => [
				'http://youtube.com/?vi=XOY3ZUO6P0k',
				'XOY3ZUO6P0k',
			],
			'nocookie_url'                     => [
				'//www.youtube-nocookie.com/embed/XOY3ZUO6P0k?rel=0',
				'XOY3ZUO6P0k',
			],
			'account_url'                      => [
				'https://www.youtube.com/account',
				false,
			],
			'account_url_followed_by_segment'  => [
				'https://www.youtube.com/account/johnsmith',
				false,
			],
			'playlist_url'                     => [
				'https://www.youtube.com/playlist?list=PLCra4VPr-3frJzAd-lVYo3-34wu0Eax_u',
				false,
			],
			'false_because_no_id'              => [
				'http://youtube.com/?wrong=XOY3ZUO6P0k',
				false,
			],
		];
	}

	/**
	 * Tests get_video_id_from_url.
	 *
	 * @dataProvider get_video_id_data
	 * @covers AMP_YouTube_Embed_Handler::get_video_id_from_url()
	 *
	 * @param string       $url      The URL to test.
	 * @param string|false $expected The expected result.
	 * @throws ReflectionException If a reflection of the object is not possible.
	 */
	public function test_get_video_id_from_url( $url, $expected ) {
		$this->assertEquals(
			$expected,
			$this->call_private_method( $this->handler, 'get_video_id_from_url', [ $url ] )
		);
	}

	public function get_scripts_data() {
		return [
			'not_converted' => [
				'<p>Hello World.</p>',
				[],
			],
			'converted'     => [
				'https://www.youtube.com/watch?v=kfVsfOSbJY0' . PHP_EOL,
				[ 'amp-youtube' => true ],
			],
		];
	}

	/**
	 * @dataProvider get_scripts_data
	 */
	public function test__get_scripts( $source, $expected ) {
		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$this->handler->sanitize_raw_embeds( $dom );

		$validating_sanitizer = new AMP_Tag_And_Attribute_Sanitizer( $dom );
		$validating_sanitizer->sanitize();

		$scripts = array_merge(
			$this->handler->get_scripts(),
			$validating_sanitizer->get_scripts()
		);

		$this->assertEquals( $expected, $scripts );
	}
}

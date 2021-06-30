<?php
/**
 * Tests for AMP_YouTube_Embed_Handler.
 *
 * @package AMP
 * @since 0.7
 */

use AmpProject\AmpWP\Tests\Helpers\AssertContainsCompatibility;
use AmpProject\AmpWP\Tests\Helpers\PrivateAccess;
use AmpProject\AmpWP\Tests\Helpers\WithoutBlockPreRendering;

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

		unset( $r );

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

	/**
	 * data provider for $this->test_sanitize_raw_embeds()
	 *
	 * @return string[][]
	 */
	public function sanitize_raw_embeds_data_provider() {

		return [
			'youtube-embed' => [
				'source'   => '<iframe src="https://www.youtube.com/embed/q4xKvHANqjk?controls=0&amp;autoplay=1&amp;loop=1&amp;modestbranding=1" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen="" width="560" height="315" frameborder="0"></iframe>',
				'expected' => '<amp-youtube data-videoid="q4xKvHANqjk" layout="responsive" width="560" height="315" title="YouTube video player" data-param-controls="0" data-param-modestbranding="1" autoplay="1" loop="1"></amp-youtube>',
			],
			'with_http'     => [
				'source'   => '<iframe src="http://www.youtube.com/embed/q4xKvHANqjk?controls=0&amp;autoplay=1&amp;loop=1&amp;modestbranding=1" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen="" width="560" height="315" frameborder="0"></iframe>',
				'expected' => '<amp-youtube data-videoid="q4xKvHANqjk" layout="responsive" width="560" height="315" title="YouTube video player" data-param-controls="0" data-param-modestbranding="1" autoplay="1" loop="1"></amp-youtube>',
			],
			'short-url'     => [
				'source'   => '<iframe src="https://youtu.be/kfVsfOSbJY0?controls=0&amp;autoplay=1&amp;loop=1&amp;modestbranding=1" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen="" width="560" height="315" frameborder="0"></iframe>',
				'expected' => '<amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="560" height="315" title="YouTube video player" data-param-controls="0" data-param-modestbranding="1" autoplay="1" loop="1"></amp-youtube>',
			],
			'none-youtube'  => [
				'source'   => '<iframe src="https://mysite.com/blog/youtube-iz-da-best" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen="" width="560" height="315" frameborder="0"></iframe>',
				'expected' => '<iframe src="https://mysite.com/blog/youtube-iz-da-best" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen="" width="560" height="315" frameborder="0"></iframe>',
			],
		];
	}

	/**
	 * @dataProvider sanitize_raw_embeds_data_provider
	 *
	 * @covers       AMP_YouTube_Embed_Handler::sanitize_raw_embeds
	 */
	public function test_sanitize_raw_embeds( $source, $expected ) {

		$embed = new AMP_YouTube_Embed_Handler();
		$embed->register_embed();

		$dom = AMP_DOM_Utils::get_dom_from_content( $source );
		$embed->sanitize_raw_embeds( $dom );

		$layout_sanitizer = new AMP_Layout_Sanitizer( $dom );
		$layout_sanitizer->sanitize();

		$content = AMP_DOM_Utils::get_content_from_dom( $dom );

		$this->assertEquals( $expected, trim( $content ) );
	}

	/**
	 * Test video_override().
	 *
	 * @covers AMP_YouTube_Embed_Handler::video_override()
	 */
	public function test_video_override() {
		remove_all_filters( 'wp_video_shortcode_override' );
		$this->handler->register_embed();
		$youtube_id   = 'XOY3ZUO6P0k';
		$youtube_src  = 'https://youtu.be/' . $youtube_id;
		$attr_youtube = [
			'src' => $youtube_src,
		];

		$youtube_shortcode = $this->handler->video_override( '', $attr_youtube );
		$this->assertStringContains( '<amp-youtube', $youtube_shortcode );
		$this->assertStringContains( $youtube_id, $youtube_shortcode );

		$vimeo_id        = '64086087';
		$vimeo_src       = 'https://vimeo.com/' . $vimeo_id;
		$attr_vimeo      = [
			'src' => $vimeo_src,
		];
		$vimeo_shortcode = $this->handler->video_override( '', $attr_vimeo );
		$this->assertEquals( '', $vimeo_shortcode );

		$daily_motion_id        = 'x6bacgf';
		$daily_motion_src       = 'http://www.dailymotion.com/video/' . $daily_motion_id;
		$attr_daily_motion      = [
			'src' => $daily_motion_src,
		];
		$daily_motion_shortcode = $this->handler->video_override( '', $attr_daily_motion );
		$this->assertEquals( '', $daily_motion_shortcode );
		$no_attributes = $this->handler->video_override( '', [] );
		$this->assertEquals( '', $no_attributes );
		remove_all_filters( 'wp_video_shortcode_override' );
	}

	public function get_conversion_data() {
		return [
			'no_embed'                         => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],

			'url_simple'                       => [
				'https://www.youtube.com/watch?v=kfVsfOSbJY0' . PHP_EOL,
				'<p><amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" title="Rebecca Black - Friday"><a placeholder href="https://www.youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover" alt="Rebecca Black - Friday"></img></a></amp-youtube></p>' . PHP_EOL,
				'<p><amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281"><a placeholder href="https://www.youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover"></img></a></amp-youtube></p>' . PHP_EOL,
			],

			'url_short'                        => [
				'https://youtu.be/kfVsfOSbJY0' . PHP_EOL,
				'<p><amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" title="Rebecca Black - Friday"><a placeholder href="https://youtu.be/kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover" alt="Rebecca Black - Friday"></img></a></amp-youtube></p>' . PHP_EOL,
				'<p><amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281"><a placeholder href="https://youtu.be/kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover"></img></a></amp-youtube></p>' . PHP_EOL,
			],

			'url_with_querystring'             => [
				'http://www.youtube.com/watch?v=kfVsfOSbJY0&hl=en&fs=1&w=425&h=349' . PHP_EOL,
				'<p><amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" title="Rebecca Black - Friday" data-param-fs="1" data-param-hl="en"><a placeholder href="http://www.youtube.com/watch?v=kfVsfOSbJY0&amp;hl=en&amp;fs=1&amp;w=425&amp;h=349"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover" alt="Rebecca Black - Friday"></img></a></amp-youtube></p>' . PHP_EOL,
				'<p><amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" data-param-fs="1" data-param-hl="en"><a placeholder href="http://www.youtube.com/watch?v=kfVsfOSbJY0&amp;hl=en&amp;fs=1&amp;w=425&amp;h=349"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover"></img></a></amp-youtube></p>' . PHP_EOL,
			],

			// Several reports of invalid URLs that have multiple `?` in the URL.
			'url_with_querystring_and_extra_?' => [
				'http://www.youtube.com/watch?v=kfVsfOSbJY0?hl=en&fs=1&w=425&h=349' . PHP_EOL,
				'<p>http://www.youtube.com/watch?v=kfVsfOSbJY0?hl=en&#038;fs=1&#038;w=425&#038;h=349</p>' . PHP_EOL,
			],

			'embed_url'                        => [
				'https://www.youtube.com/embed/kfVsfOSbJY0' . PHP_EOL,
				'<p><amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" title="Rebecca Black - Friday"><a placeholder href="https://youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover" alt="Rebecca Black - Friday"></img></a></amp-youtube></p>' . PHP_EOL,
				'<p><amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281"><a placeholder href="https://youtube.com/watch?v=kfVsfOSbJY0"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover"></img></a></amp-youtube></p>' . PHP_EOL,
			],

			'with_start_time'                  => [
				'http://www.youtube.com/watch?v=kfVsfOSbJY0#t=1m10s' . PHP_EOL,
				'<p><amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" title="Rebecca Black - Friday" data-param-start="70"><a placeholder href="http://www.youtube.com/watch?v=kfVsfOSbJY0#t=1m10s"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover" alt="Rebecca Black - Friday"></img></a></amp-youtube></p>' . PHP_EOL,
				'<p><amp-youtube data-videoid="kfVsfOSbJY0" layout="responsive" width="500" height="281" data-param-start="70"><a placeholder href="http://www.youtube.com/watch?v=kfVsfOSbJY0#t=1m10s"><img src="https://i.ytimg.com/vi/kfVsfOSbJY0/hqdefault.jpg" layout="fill" object-fit="cover"></img></a></amp-youtube></p>' . PHP_EOL,
			],
		];
	}

	/**
	 * @dataProvider get_conversion_data
	 */
	public function test__conversion( $source, $expected, $fallback_for_expected = null ) {
		$this->handler->register_embed();

		// Make actual output consistent between WP 5.4 and 5.5.
		add_filter( 'wp_lazy_loading_enabled', '__return_false' );

		$filtered_content = apply_filters( 'the_content', $source );

		if (
			version_compare( strtok( get_bloginfo( 'version' ), '-' ), '5.1', '<' )
			&& null !== $fallback_for_expected
		) {
			$this->assertEquals( $fallback_for_expected, $filtered_content );
		} else {
			$this->assertEquals( $expected, $filtered_content );
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

	/**
	 * Gets the test data for test_get_start_time_from_url().
	 *
	 * @return array The test data.
	 */
	public function get_start_time_data() {

		return [
			'empty_because_no_start_data'   => [
				'http://youtube.com/?wrong=XOY3ZUO6P0k',
				0,
			],
			'data_in_query_string'          => [
				'http://www.youtube.com/watch?v=0zM3nApSvMg&start=90',
				90,
			],
			'data_in_fragment'              => [
				'http://www.youtube.com/watch?v=0zM3nApSvMg#t=0m10s',
				10,
			],
			'data_in_fragment_with_minutes' => [
				'http://www.youtube.com/watch?v=0zM3nApSvMg#t=1m10s',
				70,
			],
		];
	}

	/**
	 * Tests get_start_time_from_url.
	 *
	 * @dataProvider get_start_time_data
	 * @covers       AMP_YouTube_Embed_Handler::get_start_time_from_url
	 *
	 * @param string       $url      The URL to test.
	 * @param string|false $expected The expected result.
	 *
	 * @throws ReflectionException If a reflection of the object is not possible.
	 */
	public function test_get_start_time_from_url( $url, $expected ) {

		$this->assertEquals(
			$expected,
			$this->call_private_method( $this->handler, 'get_start_time_from_url', [ $url ] )
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
		$this->handler->register_embed();
		$source = apply_filters( 'the_content', $source );

		$validating_sanitizer = new AMP_Tag_And_Attribute_Sanitizer( AMP_DOM_Utils::get_dom_from_content( $source ) );
		$validating_sanitizer->sanitize();

		$scripts = array_merge(
			$this->handler->get_scripts(),
			$validating_sanitizer->get_scripts()
		);

		$this->assertEquals( $expected, $scripts );
	}
}

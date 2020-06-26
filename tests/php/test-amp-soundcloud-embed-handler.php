<?php
/**
 * Class AMP_SoundCloud_Embed_Test
 *
 * @package AMP
 */

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

/**
 * Class AMP_SoundCloud_Embed_Handler_Test
 *
 * @covers AMP_SoundCloud_Embed_Handler
 */
class AMP_SoundCloud_Embed_Handler_Test extends WP_UnitTestCase {

	use WithoutBlockPreRendering {
		setUp as public prevent_block_pre_render;
	}

	/**
	 * Track URL.
	 *
	 * @var string
	 */
	protected $track_url = 'https://soundcloud.com/jack-villano-villano/mozart-requiem-in-d-minor';

	/**
	 * Playlist URL.
	 *
	 * @var string
	 */
	protected $playlist_url = 'https://soundcloud.com/classical-music-playlist/sets/classical-music-essential-collection';

	/**
	 * Response for track oEmbed request.
	 *
	 * @see AMP_SoundCloud_Embed_Test::$track_url
	 * @var string
	 */
	protected $track_oembed_response = '{"version":1.0,"type":"rich","provider_name":"SoundCloud","provider_url":"http://soundcloud.com","height":400,"width":500,"title":"Mozart - Requiem in D minor Complete Full by Jack Villano Villano","description":"mass in D Minor ","thumbnail_url":"http://i1.sndcdn.com/artworks-000046826426-o7i9ki-t500x500.jpg","html":"\u003Ciframe width=\"500\" height=\"400\" scrolling=\"no\" frameborder=\"no\" src=\"https://w.soundcloud.com/player/?visual=true\u0026url=https%3A%2F%2Fapi.soundcloud.com%2Ftracks%2F90097394\u0026show_artwork=true\u0026maxwidth=500\u0026maxheight=750\u0026dnt=1\"\u003E\u003C/iframe\u003E","author_name":"Jack Villano Villano","author_url":"https://soundcloud.com/jack-villano-villano"}';

	/**
	 * Response for playlist oEmbed request.
	 *
	 * @see AMP_SoundCloud_Embed_Test::$playlist_url
	 * @var string
	 */
	protected $playlist_oembed_response = '{"version":1.0,"type":"rich","provider_name":"SoundCloud","provider_url":"http://soundcloud.com","height":450,"width":500,"title":"Classical Music - The Essential Collection by Classical Music","description":"Classical Music - The Essential Collection features 50 of the finest Classical Masterpieces ever written. Definitely not to working to! ","thumbnail_url":"http://i1.sndcdn.com/artworks-000083473866-mno23j-t500x500.jpg","html":"\u003Ciframe width=\"500\" height=\"450\" scrolling=\"no\" frameborder=\"no\" src=\"https://w.soundcloud.com/player/?visual=true\u0026url=https%3A%2F%2Fapi.soundcloud.com%2Fplaylists%2F40936190\u0026show_artwork=true\u0026maxwidth=500\u0026maxheight=750\u0026dnt=1\"\u003E\u003C/iframe\u003E","author_name":"Classical Music","author_url":"https://soundcloud.com/classical-music-playlist"}';

	/**
	 * Set up.
	 */
	public function setUp() {
		$this->prevent_block_pre_render();

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

		if ( false === strpos( $url, 'soundcloud.com' ) ) {
			return $preempt;
		}

		if ( false !== strpos( $url, 'sets' ) ) {
			$body = $this->playlist_oembed_response;
		} else {
			$body = $this->track_oembed_response;
		}

		return [
			'body'          => $body,
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
	 * Get the embed conversion data.
	 *
	 * @return array
	 */
	public function get_embed_conversion_data() {
		return [
			'no_embed'        => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],

			'track_simple'    => [
				$this->track_url . PHP_EOL,
				'<amp-soundcloud data-trackid="90097394" height="400" width="500" layout="responsive" data-visual="true"></amp-soundcloud>' . PHP_EOL,
			],

			'playlist_simple' => [
				$this->playlist_url . PHP_EOL,
				'<amp-soundcloud data-playlistid="40936190" height="450" width="500" layout="responsive" data-visual="true"></amp-soundcloud>' . PHP_EOL,
			],
		];
	}

	/**
	 * Test conversion.
	 *
	 * @dataProvider get_embed_conversion_data
	 *
	 * @param string $source   Source.
	 * @param string $expected Expected.
	 */
	public function test_embed_conversion( $source, $expected ) {
		$embed = new AMP_SoundCloud_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$content = AMP_DOM_Utils::get_content_from_dom( $dom );

		$this->assertEquals( $expected, $content );
	}

	/**
	 * Get scripts data.
	 *
	 * @return array Scripts data.
	 */
	public function get_scripts_data() {
		return [
			'not_converted'      => [
				'<p>Hello World.</p>',
				[],
			],
			'converted_track'    => [
				$this->track_url . PHP_EOL,
				[ 'amp-soundcloud' => true ],
			],
			'converted_playlist' => [
				$this->playlist_url . PHP_EOL,
				[ 'amp-soundcloud' => true ],
			],
		];
	}

	/**
	 * Test get scripts.
	 *
	 * @covers AMP_SoundCloud_Embed_Handler::get_scripts()
	 * @dataProvider get_scripts_data
	 *
	 * @param string $source   Source.
	 * @param string $expected Expected.
	 */
	public function test__get_scripts( $source, $expected ) {
		$embed = new AMP_SoundCloud_Embed_Handler();

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

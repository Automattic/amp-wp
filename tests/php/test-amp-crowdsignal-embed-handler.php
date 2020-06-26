<?php
/**
 * Class AMP_Crowdsignal_Embed_Handler_Test
 *
 * @package AMP
 */

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

/**
 * Class AMP_Crowdsignal_Embed_Handler_Test
 *
 * @covers AMP_Crowdsignal_Embed_Handler
 */
class AMP_Crowdsignal_Embed_Handler_Test extends WP_UnitTestCase {

	use WithoutBlockPreRendering;

	/**
	 * Get conversion data.
	 *
	 * @return array
	 */
	public function get_conversion_data() {
		$poll_response = [
			'type'          => 'rich',
			'version'       => '1.0',
			'provider_name' => 'Crowdsignal',
			'provider_url'  => 'https://crowdsignal.com',
			'title'         => 'Which design do you prefer?',
			'html'          => '<script type="text/javascript" charset="utf-8" src="https://secure.polldaddy.com/p/7012505.js"></script><noscript><iframe title="Which design do you prefer?" src="https://poll.fm/7012505/embed" frameborder="0" class="cs-iframe-embed"></iframe></noscript>', // phpcs:ignore
		];

		$poll_response['html'] = $this->adapt_iframe_title( $poll_response['html'] );

		$survey_response = [
			'type'          => 'rich',
			'version'       => '1.0',
			'provider_name' => 'Crowdsignal',
			'provider_url'  => 'https://crowdsignal.com',
			'html'          => "<div class=\"pd-embed\" data-settings=\"{&quot;type&quot;:&quot;iframe&quot;,&quot;auto&quot;:true,&quot;domain&quot;:&quot;rydk.survey.fm&quot;,&quot;id&quot;:&quot;test-survey&quot;}\"></div>\n<script type=\"text/javascript\">\n(function(d,c,j){if(!document.getElementById(j)){var pd=d.createElement(c),s;pd.id=j;pd.src=('https:'==document.location.protocol)?'https://polldaddy.com/survey.js':'http://i0.poll.fm/survey.js';s=document.getElementsByTagName(c)[0];s.parentNode.insertBefore(pd,s);}}(document,'script','pd-embed'));\n</script>",
		];

		$data = [
			'poll.fm'          => [
				'https://poll.fm/7012505',
				'<iframe title="Which design do you prefer?" src="https://poll.fm/7012505/embed" frameborder="0" class="cs-iframe-embed"></iframe>' . PHP_EOL,
				$poll_response,
			],

			'polldaddy_poll'   => [
				'https://polldaddy.com/poll/7012505/',
				'<iframe title="Which design do you prefer?" src="https://poll.fm/7012505/embed" frameborder="0" class="cs-iframe-embed"></iframe>' . PHP_EOL,
				$poll_response,
			],

			'polldaddy_survey' => [
				'https://rydk.polldaddy.com/s/test-survey',
				'<iframe src="https://rydk.survey.fm/test-survey?ft=1&amp;iframe=http://example.org/" layout="responsive" width="600" height="600" frameborder="0" scrolling="no" allowtransparency="true" sandbox="allow-scripts allow-same-origin"></iframe>' . PHP_EOL . PHP_EOL,
				$survey_response,
			],

			'survey.fm'        => [
				'https://rydk.survey.fm/test-survey',
				'<iframe src="https://rydk.survey.fm/test-survey?ft=1&amp;iframe=http://example.org/" layout="responsive" width="600" height="600" frameborder="0" scrolling="no" allowtransparency="true" sandbox="allow-scripts allow-same-origin"></iframe>' . PHP_EOL . PHP_EOL,
				$survey_response,
			],
		];

		return $data;
	}

	/**
	 * Test conversion.
	 *
	 * @dataProvider get_conversion_data
	 *
	 * @param string $url             Source.
	 * @param string $expected        Expected.
	 * @param string $oembed_response oEmbed response.
	 */
	public function test_conversion( $url, $expected, $oembed_response ) {
		add_filter(
			'pre_http_request',
			static function ( $pre, $r, $request_url ) use ( $oembed_response ) {
				if ( in_array( 'external-http', $_SERVER['argv'], true ) ) {
					return $pre;
				}

				if ( ! preg_match( '/crowdsignal|polldaddy/', $request_url ) ) {
					return $pre;
				}

				return [
					'body'     => wp_json_encode( $oembed_response ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				];
			},
			10,
			3
		);

		$embed = new AMP_Crowdsignal_Embed_Handler();
		$embed->register_embed();

		$filtered_content = apply_filters( 'the_content', $url );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$content  = AMP_DOM_Utils::get_content_from_dom( $dom );
		$expected = $this->adapt_iframe_title( $expected );

		$this->assertEquals( $expected, $content );
	}

	private function adapt_iframe_title( $html ) {
		// Prior to 5.1, there was no 'title' attribute on an iframe.
		if ( version_compare( get_bloginfo( 'version' ), '5.1', '<' ) ) {
			$html = preg_replace( '/(<iframe.*)(\stitle=".+?")/', '${1}', $html );
		}

		return $html;
	}
}

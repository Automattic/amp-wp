<?php

use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

class AMP_Pinterest_Embed_Handler_Test extends WP_UnitTestCase {

	use WithoutBlockPreRendering;

	public function get_conversion_data() {
		return [
			'no_embed'         => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>' . PHP_EOL,
			],
			'simple_url_https' => [
				'https://www.pinterest.com/pin/606156431067611861/' . PHP_EOL,
				'<amp-pinterest width="450" height="750" data-do="embedPin" data-url="https://www.pinterest.com/pin/606156431067611861/"></amp-pinterest>' . PHP_EOL,
			],
			'simple_url_http'  => [
				'http://www.pinterest.com/pin/606156431067611861/' . PHP_EOL,
				'<amp-pinterest width="450" height="750" data-do="embedPin" data-url="http://www.pinterest.com/pin/606156431067611861/"></amp-pinterest>' . PHP_EOL,
			],
		];
	}

	/**
	 * @dataProvider get_conversion_data
	 */
	public function test__conversion( $source, $expected ) {
		$embed = new AMP_Pinterest_Embed_Handler();
		$embed->register_embed();

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
				'https://www.pinterest.com/pin/606156431067611861/' . PHP_EOL,
				[ 'amp-pinterest' => true ],
			],
		];
	}

	/**
	 * @dataProvider get_scripts_data
	 */
	public function test__get_scripts( $source, $expected ) {
		$embed = new AMP_Pinterest_Embed_Handler();
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

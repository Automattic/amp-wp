<?php
/**
 * Tests for Keyframes class.
 *
 * @package AMP
 */

use Amp\AmpWP\Component\Keyframes;

/**
 * Tests for Keyframes class.
 *
 * @covers \Amp\AmpWP\Component\Keyframes
 */
class Test_Keyframes extends \WP_UnitTestCase {

	/**
	 * Gets the data for test_remove_eligible_keyframes().
	 *
	 * @return array[] The testing data.
	 */
	public function get_keyframes_data() {
		$default_property_whitelist = [
			'animation-timing-function',
			'offset-distance',
			'opacity',
			'transform',
			'visibility',
		];

		return [
			'eligible_keyframes_moved_to_amp_keyframes' => [
				'@keyframes visible { from { visibility: visible; } to { visibility: hidden } }',
				$default_property_whitelist,
				'',
				null,
			],
			'complex_and_eligible_keyframes'            => [
				'@keyframes offset { 0% { offset-distance: 0%; } 50% { offset-distance: 50%; opacity: 50%; } 100% { offset-distance: 100%; opacity: 80%; } }',
				$default_property_whitelist,
				'',
				null,
			],
			'multiple_keyframes_moved_to_amp_keyframes' => [
				'@keyframes scale { from { transform: scaleX(0.5); } to { transform: scaleX(1); } } @keyframes rotate { from { transform: rotateY(10deg); } to { transform: rotateY(20deg); } }',
				$default_property_whitelist,
				'',
				'@keyframes scale { from { transform: scaleX(0.5); } to { transform: scaleX(1); } }@keyframes rotate { from { transform: rotateY(10deg); } to { transform: rotateY(20deg); } }',
			],
			'non_eligible_keyframes_not_moved'          => [
				'@keyframes wider { from { width: 20px } to { width: 40px } }',
				$default_property_whitelist,
				null,
				'',
			],
			'empty_property_whitelist'                  => [
				'@keyframes visible { from { visibility: visible } to { visibility: hidden } }',
				[],
				null,
				'',
			],
			'non_keyframes_remain_in_stylesheet_while_keyframes_removed' => [
				'@media screen and ( max-width: 800px; ) { #baz{ background-color #ffffff; } } @keyframes visible { from { visibility: visible; } to { visibility: hidden; } } .foo { margin-bottom: 20px; }',
				$default_property_whitelist,
				'@media screen and ( max-width: 800px; ) { #baz{ background-color #ffffff; } }  .foo { margin-bottom: 20px; }',
				'@keyframes visible { from { visibility: visible; } to { visibility: hidden; } }',
			],
		];
	}

	/**
	 * Test remove_eligible_keyframes.
	 *
	 * @dataProvider get_keyframes_data
	 * @covers \Amp\AmpWP\Component\Keyframes::remove_eligible_keyframes()
	 *
	 * @param string $stylesheet          Stylesheet to evaluate for keyframes.
	 * @param array  $property_whitelist  Properties that are allowed in the keyframes.
	 * @param string $expected_stylesheet Expected final stylesheet.
	 * @param string $expected_keyframes  Expected keyframes that were removed from the stylesheet.
	 */
	public function test_remove_eligible_keyframes( $stylesheet, $property_whitelist, $expected_stylesheet, $expected_keyframes ) {
		if ( null === $expected_stylesheet ) {
			$expected_stylesheet = $stylesheet;
		}
		if ( null === $expected_keyframes ) {
			$expected_keyframes = $stylesheet;
		}

		$keyframes = new Keyframes( $stylesheet, $property_whitelist );
		$keyframes->remove_eligible_keyframes();

		$this->assertEquals( $expected_stylesheet, $keyframes->get_stylesheet() );
		$this->assertEquals( $expected_keyframes, $keyframes->get_removed_keyframes() );
	}
}

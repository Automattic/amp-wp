<?php
/**
 * Class Keyframes.
 *
 * @package Amp\AmpWP
 */

namespace Amp\AmpWP\Component;

use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\Settings;

/**
 * Class Keyframes
 *
 * Gets @keyframes rules that can be moved to style[amp-custom], and removes them from the stylesheet.
 *
 * @internal
 * @since 1.5.0
 */
final class Keyframes {

	/**
	 * The stylesheet.
	 *
	 * @var string
	 */
	private $stylesheet;

	/**
	 * The whitelist of allowed properties.
	 *
	 * @var string[] The allowed properties in the @keyframes rule.
	 */
	private $property_whitelist;

	/**
	 * The @keyframes rules that were removed
	 *
	 * @var string
	 */
	private $removed_keyframes = '';

	/**
	 * The index in the stylesheet to start a search for keyframes at.
	 *
	 * @var string
	 */
	private $pointer = 0;

	/**
	 * Instantiates the class.
	 *
	 * @param string   $stylesheet         The stylesheet to look for the @keyframes in.
	 * @param string[] $property_whitelist The properties that are allowed in the @keyframes rules.
	 */
	public function __construct( $stylesheet, $property_whitelist ) {
		$this->stylesheet         = $stylesheet;
		$this->property_whitelist = $property_whitelist;
	}

	/**
	 * Gets the eligible removed keyframes.
	 *
	 * @return string The removed keyframes that are eligible to be in the style[amp-keyframes].
	 */
	public function get_removed_keyframes() {
		return $this->removed_keyframes;
	}

	/**
	 * Gets the stylesheet, which may have eligible @keyframes rules removed.
	 *
	 * @return string The stylesheet.
	 */
	public function get_stylesheet() {
		return $this->stylesheet;
	}

	/**
	 * Removes @keyframes rules in a stylesheet, if they would be valid in style[amp-keyframes].
	 *
	 * To avoid exceeding the limit of the style[amp-custom],
	 * this removes and returns eligible @keyframes rules to add those to style[amp-keyframes].
	 * But the style[amp-keyframes] has fewer allowed style properties, so this only removes @keyframes that would be valid.
	 */
	public function remove_eligible_keyframes() {
		while ( preg_match( '/@keyframes[^{]+({)/', substr( $this->stylesheet, $this->pointer ), $matches, PREG_OFFSET_CAPTURE ) ) {
			$keyframes_position     = $matches[0][1];
			$first_bracket_position = $matches[1][1];
			$this->possibly_remove_keyframes_at_index( $keyframes_position, $first_bracket_position );
		}

	}

	/**
	 * Gets @keyframes rules in a stylesheet, if they exist.
	 *
	 * @todo: This should be reworked to parse the stylesheet with Sabberworm, as @keyframes can be nested in @media queries.
	 * @param int $keyframes_index       The position of the @keyframe in the stylesheet.
	 * @param int $opening_bracket_index The position of the opening { after @keyframes.
	 */
	private function possibly_remove_keyframes_at_index( $keyframes_index, $opening_bracket_index ) {
		$bracket_count     = 0;
		$stylesheet_length = strlen( $this->stylesheet );
		for ( $closing_bracket_index = $opening_bracket_index; $closing_bracket_index < $stylesheet_length; $closing_bracket_index++ ) {
			$character = substr( $this->stylesheet, $closing_bracket_index, 1 );
			if ( '{' === $character ) {
				$bracket_count++;
			} elseif ( '}' === $character ) {
				$bracket_count--;
			}

			if ( 0 === $bracket_count ) {
				break;
			}
		}

		$length    = 1 + $closing_bracket_index - $keyframes_index;
		$keyframes = substr( $this->stylesheet, $keyframes_index, $length );

		// Conditionally remove the @keyframes rule from the stylesheet.
		if ( $this->is_valid( $keyframes ) ) {
			$this->removed_keyframes .= $keyframes;
			$this->stylesheet         = trim( substr_replace( $this->stylesheet, '', $keyframes_index, $length ) );
		} else {
			// Since this @keyframes wasn't valid, move the pointer so that the next search doesn't examine this again.
			$this->pointer = $closing_bracket_index;
		}
	}

	/**
	 * Gets whether a stylesheet part with @keyframes{ ... } would be valid if moved to the style[amp-keyframes].
	 *
	 * @param string $stylesheet_part The part of the stylesheet with @keyframes { ... }.
	 * @return bool Whether it passes validation to be in the style[amp-keyframes].
	 */
	private function is_valid( $stylesheet_part ) {
		$parser_settings = Settings::create();
		$css_parser      = new Parser( $stylesheet_part, $parser_settings );
		$css_document    = $css_parser->parse();

		foreach ( $css_document->getContents() as $css_item ) {
			foreach ( $css_item->getContents() as $rules ) {
				$properties = $rules->getRules();
				if ( ! $this->are_properties_valid( $properties ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Gets whether all of the properties are in the whitelist.
	 *
	 * @param Rule[] $properties The CSS properties.
	 * @return bool Whether all of the properties are in the whitelist.
	 */
	private function are_properties_valid( $properties ) {
		foreach ( $properties as $property ) {
			$property_name_without_vendor = preg_replace( '/^-\w+-/', '', $property->getRule() );
			if ( ! in_array( $property_name_without_vendor, $this->property_whitelist, true ) ) {
				return false;
			}
		}

		return true;
	}
}

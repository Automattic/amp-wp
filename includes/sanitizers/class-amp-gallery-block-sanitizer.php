<?php
/**
 * Class AMP_Gallery_Block_Sanitizer.
 *
 * @package AMP
 */

use AmpProject\AmpWP\Embed\HandlesGalleryEmbed;
use AmpProject\Tag;

/**
 * Class AMP_Gallery_Block_Sanitizer
 *
 * Modifies gallery block to match the block's AMP-specific configuration.
 *
 * @internal
 */
class AMP_Gallery_Block_Sanitizer extends AMP_Base_Sanitizer {

	use HandlesGalleryEmbed;

	/**
	 * Tag.
	 *
	 * @since 1.0
	 *
	 * @var string Ul tag to identify wrapper around gallery block.
	 */
	public static $tag = 'ul';

	/**
	 * Expected class of the wrapper around the gallery block.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	public static $class = 'wp-block-gallery';

	/**
	 * Array of flags used to control sanitization.
	 *
	 * @var array {
	 *      @type int  $content_max_width Max width of content.
	 *      @type bool $carousel_required Whether carousels are required. This is used when amp theme support is not present, for back-compat.
	 * }
	 */
	protected $args;

	/**
	 * Default args.
	 *
	 * @var array
	 */
	protected $DEFAULT_ARGS = [
		'carousel_required' => false,
	];

	/**
	 * Sanitize the gallery block contained by <ul> element where necessary.
	 *
	 * @since 0.2
	 */
	public function sanitize() {
		$class_query = 'contains( concat( " ", normalize-space( @class ), " " ), " wp-block-gallery " )';
		$expr        = sprintf(
			'//ul[ %s ]',
			implode(
				' or ',
				[
					sprintf( '( parent::figure[ %s ] )', $class_query ),
					$class_query,
				]
			)
		);
		$nodes       = $this->dom->xpath->query( $expr );

		foreach ( $nodes as $node ) {
			/** @var DOMElement $node */

			// In WordPress 5.3, the Gallery block's <ul> is wrapped in a <figure class="wp-block-gallery">, so look for that node also.
			$gallery_node = isset( $node->parentNode ) && AMP_DOM_Utils::has_class( $node->parentNode, self::$class ) ? $node->parentNode : $node;
			$attributes   = AMP_DOM_Utils::get_node_attributes_as_assoc_array( $gallery_node );

			$is_amp_lightbox = isset( $attributes['data-amp-lightbox'] ) && rest_sanitize_boolean( $attributes['data-amp-lightbox'] );

			if ( isset( $attributes['data-amp-carousel'] ) ) {
				$is_amp_carousel = rest_sanitize_boolean( $attributes['data-amp-carousel'] );
			} else {
				// The carousel_required argument is set to true when the theme does not support AMP. However, it is no
				// no longer strictly required. Rather, carousels are just enabled by default.
				$is_amp_carousel = ! empty( $this->args['carousel_required'] );
			}

			// Ensure data-amp-carousel=true attribute is present for proper styling of block.
			if ( $is_amp_carousel ) {
				$gallery_node->setAttribute( 'data-amp-carousel', 'true' );
			}

			$img_elements = $this->dom->xpath->query( './/amp-img | .//img[ not( parent::noscript ) ]', $node );

			$this->process_gallery_embed( $is_amp_carousel, $is_amp_lightbox, $node, $img_elements );
		}
	}

	/**
	 * Get the caption element for the specified image element.
	 *
	 * @param DOMElement $img_element Image element.
	 * @return DOMElement|null The caption element, or `null` if the image has none.
	 */
	protected function get_caption_element( DOMElement $img_element ) {
		$figcaption_element = null;

		if ( isset( $img_element->nextSibling->nodeName ) && Tag::FIGCAPTION === $img_element->nextSibling->nodeName ) {
			$figcaption_element = $img_element->nextSibling;
		}

		// If 'Link To' is selected, the image will be wrapped in an <a>, so search for the sibling of the <a>.
		if (
			! $figcaption_element
			&& isset( $img_element->parentNode->nextSibling->nodeName )
			&& Tag::FIGCAPTION === $img_element->parentNode->nextSibling->nodeName
		) {
			$figcaption_element = $img_element->parentNode->nextSibling;
		}

		if ( $figcaption_element instanceof DOMElement && 0 === $figcaption_element->childNodes->length ) {
			return null;
		}

		return $figcaption_element;
	}
}

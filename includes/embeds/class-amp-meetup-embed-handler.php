<?php
/**
 * Class AMP_Meetup_Embed_Handler
 *
 * @package AMP
 * @since 0.7
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Meetup_Embed_Handler
 */
class AMP_Meetup_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Determine if the node is indeed a raw embed.
	 *
	 * @param DOMElement $node DOM element.
	 * @return bool True if it is a raw embed, false otherwise.
	 */
	protected function is_raw_embed( DOMElement $node ) {
		$width  = filter_var( $node->getAttribute( 'width' ), FILTER_SANITIZE_NUMBER_INT );
		$height = filter_var( $node->getAttribute( 'height' ), FILTER_SANITIZE_NUMBER_INT );

		return 50 !== $width && 50 !== $height;
	}

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		return $dom->xpath->query( '//div[ @id="meetup_oembed" ]//div[ @class="photo" ]/img' );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		// Supply the width/height so that we don't have to make requests to look them up later.
		$node->setAttribute( 'width', 50 );
		$node->setAttribute( 'height', 50 );
	}
}

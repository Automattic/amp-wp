<?php
/**
 * Class AMP_TikTok_Embed_Handler
 *
 * @package AMP
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_TikTok_Embed_Handler
 */
class AMP_TikTok_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Default AMP tag to be used when sanitizing embeds.
	 *
	 * @var string
	 */
	protected $amp_tag = 'amp-embedly-card';

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		return $dom->xpath->query( '//blockquote[ contains( @class, "tiktok-embed" ) ]' );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$dom       = $node->ownerDocument;
		$video_url = $node->getAttribute( 'cite' );

		// If there is no video ID, stop here as its needed for the `data-url` parameter.
		if ( empty( $video_url ) ) {
			return;
		}

		$this->remove_script_sibling( $node, 'tiktok.com/embed.js' );

		$amp_node = AMP_DOM_Utils::create_node(
			Document::fromNode( $dom ),
			$this->amp_tag,
			[
				'layout'             => 'responsive',
				'height'             => 700,
				'width'              => 340,
				'data-card-controls' => 0,
				'data-url'           => $video_url,
			]
		);

		// Find existing <section> node to use as the placeholder.
		foreach ( iterator_to_array( $node->childNodes ) as $child ) {
			if ( ! ( $child instanceof DOMElement ) ) {
				continue;
			}

			// Append the placeholder if it was found.
			if ( 'section' === $child->nodeName ) {
				/**
				 * Placeholder to append to the AMP component.
				 *
				 * @var DOMElement $placeholder_node
				 */
				$placeholder_node = $node->removeChild( $child );
				$placeholder_node->setAttribute( 'placeholder', '' );
				$amp_node->appendChild( $placeholder_node );
				break;
			}
		}

		$node->parentNode->replaceChild( $amp_node, $node );
	}
}

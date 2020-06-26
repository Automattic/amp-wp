<?php
/**
 * Class AMP_Reddit_Embed_Handler
 *
 * @package AMP
 * @since 0.7
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Reddit_Embed_Handler
 */
class AMP_Reddit_Embed_Handler extends AMP_Base_Embed_Handler {

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
		/*
		 * The blockquote being queried has two anchor elements. The first one is what we need since it has the URL of the
		 * Reddit page being embedded.
		 */
		return $dom->xpath->query( '//blockquote[ @class = "reddit-card" and //a[1][ starts-with( @href, "https://www.reddit.com" ) ] ]' );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$anchor_node = $this->get_anchor_node( $node );
		if ( null === $anchor_node ) {
			return;
		}

		$reddit_url = $anchor_node->getAttribute( 'href' );
		if ( empty( $reddit_url ) ) {
			return;
		}

		$attributes = [
			'layout'   => 'responsive',
			'width'    => 100,
			'height'   => 100,
			'data-url' => $anchor_node->getAttribute( 'href' ),
		];

		$amp_node = AMP_DOM_Utils::create_node(
			Document::fromNode( $node ),
			$this->amp_tag,
			$attributes
		);

		// Add the raw embed as the placeholder.
		$placeholder_node = $node->cloneNode( true );
		$placeholder_node->setAttribute( 'placeholder', '' );
		$amp_node->appendChild( $placeholder_node );

		$this->remove_script_sibling( $node, 'embed.redditmedia.com/widgets/platform.js' );
		$node->parentNode->replaceChild( $amp_node, $node );
	}

	/**
	 * Get the first child anchor element from the blockquote element.
	 *
	 * @param DOMElement $blockquote_node Blockquote element.
	 * @return DOMElement|null Anchor element if found, otherwise null.
	 */
	private function get_anchor_node( DOMElement $blockquote_node ) {
		$first_child_element = $this->get_first_child_element( $blockquote_node );

		if ( null === $first_child_element ) {
			return null;
		}

		$anchor_node = null;

		if ( 'a' === $first_child_element->nodeName ) {
			// Anchor tag was not wrapped by `wpautop`.
			$anchor_node = $first_child_element;
		} elseif ( 'p' === $first_child_element->nodeName ) {
			// If the anchor tag is wrapped by `wpautop`, it should be the first child of the `p` tag.
			$first_child_element = $this->get_first_child_element( $first_child_element );

			if ( null !== $first_child_element && 'a' === $first_child_element->nodeName ) {
				$anchor_node = $first_child_element;
			}
		}

		return $anchor_node;
	}

	/**
	 * Get first child element for specified element.
	 *
	 * @param DOMElement $node Element.
	 * @return DOMElement|null First element if found, otherwise null.
	 */
	private function get_first_child_element( DOMElement $node ) {
		foreach ( $node->childNodes as $child_node ) {
			if ( $child_node instanceof DOMElement ) {
				return $child_node;
			}
		}
		return null;
	}
}


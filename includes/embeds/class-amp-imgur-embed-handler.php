<?php
/**
 * Class AMP_Imgur_Embed_Handler
 *
 * @package AMP
 * @since 1.0
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Imgur_Embed_Handler
 */
class AMP_Imgur_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Default width.
	 *
	 * @var int
	 */
	protected $DEFAULT_WIDTH = 540;

	/**
	 * Default height.
	 *
	 * @var int
	 */
	protected $DEFAULT_HEIGHT = 500;

	/**
	 * Default AMP tag to be used when sanitizing embeds.
	 *
	 * @var string
	 */
	protected $amp_tag = 'amp-imgur';

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		return $dom->xpath->query( '//blockquote[ @class = "imgur-embed-pub" ]' );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$imgur_id = $node->getAttribute( 'data-id' );

		if ( empty( $imgur_id ) ) {
			return;
		}

		$amp_node = AMP_DOM_Utils::create_node(
			Document::fromNode( $node ),
			$this->amp_tag,
			[
				'data-imgur-id' => $imgur_id,
				'layout'        => 'responsive',
				'width'         => $this->args['width'],
				'height'        => $this->args['height'],
			]
		);

		$this->remove_script_sibling( $node, 's.imgur.com/min/embed.js' );

		$node->parentNode->replaceChild( $amp_node, $node );
	}
}

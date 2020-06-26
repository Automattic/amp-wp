<?php
/**
 * Class AMP_Hulu_Embed_Handler
 *
 * @package AMP
 * @since 1.0
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Hulu_Embed_Handler
 */
class AMP_Hulu_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Default height.
	 *
	 * @var int
	 */
	protected $DEFAULT_HEIGHT = 600;

	/**
	 * Default AMP tag to be used when sanitizing embeds.
	 *
	 * @var string
	 */
	protected $amp_tag = 'amp-hulu';

	/**
	 * Base URL used for identifying embeds.
	 *
	 * @var string
	 */
	protected $base_embed_url = 'www.hulu.com/embed.html';

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		return $dom->xpath->query( sprintf( '//iframe[ contains( @src, "%s" ) ]', $this->base_embed_url ) );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$iframe_src = $node->getAttribute( 'src' );

		parse_str( wp_parse_url( $iframe_src, PHP_URL_QUERY ), $query );
		if ( empty( $query['eid'] ) ) {
			return;
		}

		$attributes = [
			'data-eid' => $query['eid'],
			'layout'   => 'responsive',
			'width'    => $this->DEFAULT_WIDTH,
			'height'   => $this->DEFAULT_HEIGHT,
		];

		if ( $node->hasAttribute( 'width' ) ) {
			$attributes['width'] = $node->getAttribute( 'width' );
		}

		if ( $node->hasAttribute( 'height' ) ) {
			$attributes['height'] = $node->getAttribute( 'height' );
		}

		$amp_node = AMP_DOM_Utils::create_node(
			Document::fromNode( $node ),
			$this->amp_tag,
			$attributes
		);

		$this->unwrap_p_element( $node );

		$node->parentNode->replaceChild( $amp_node, $node );
	}
}

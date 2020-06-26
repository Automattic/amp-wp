<?php
/**
 * Class AMP_WordPress_TV_Embed_Handler
 *
 * @package AMP
 * @since 1.4
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_WordPress_TV_Embed_Handler
 *
 * @since 1.4
 */
class AMP_WordPress_TV_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Base URL used for identifying embeds.
	 *
	 * @var string
	 */
	protected $base_embed_url = 'https://video.wordpress.com/embed/';

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		return $dom->xpath->query( sprintf( '//iframe[ starts-with( @src, "%s" ) ]', $this->base_embed_url ) );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$node->setAttribute( 'layout', 'responsive' );

		$this->remove_script_sibling( $node, 'v0.wordpress.com/js/next/videopress-iframe.js' );
		$this->unwrap_p_element( $node );
	}
}

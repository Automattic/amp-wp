<?php
/**
 * Class AMP_Scribd_Embed_Handler
 *
 * @package AMP
 * @since 1.4
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Scribd_Embed_Handler
 */
class AMP_Scribd_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Base URL used for identifying embeds.
	 *
	 * @var string
	 */
	protected $base_embed_url = 'https://www.scribd.com/embeds/';

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
		$required_sandbox_permissions = 'allow-popups allow-scripts';
		$node->setAttribute(
			'sandbox',
			$node->getAttribute( 'sandbox' ) . ' ' . $required_sandbox_permissions
		);
		$node->setAttribute( 'layout', 'responsive' );

		$this->remove_script_sibling( $node, null, 'scribd.com/javascripts/embed_code/inject.j' );
		$this->unwrap_p_element( $node );

		// The iframe sanitizer will further sanitize and convert this into an amp-iframe.
	}
}

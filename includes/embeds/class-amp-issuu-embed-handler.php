<?php
/**
 * Class AMP_Issuu_Embed_Handler
 *
 * @package AMP
 * @since 0.7
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Issuu_Embed_Handler
 */
class AMP_Issuu_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		return $dom->xpath->query( '//div[ @class="issuuembed" and @data-url ]' );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$url = $node->getAttribute( 'data-url' );

		$attributes = [
			'width'   => $this->args['width'],
			'height'  => $this->args['height'],
			'src'     => $url,
			'sandbox' => 'allow-scripts allow-same-origin',
		];

		if ( $node->hasAttribute( 'style' ) ) {
			$style = $node->getAttribute( 'style' );

			if ( preg_match( '/width\s*:\s*(\d+)px/', $style, $matches ) ) {
				$attributes['width'] = $matches[1];
			}

			if ( preg_match( '/height\s*:\s*(\d+)px/', $style, $matches ) ) {
				$attributes['height'] = $matches[1];
			}
		}

		$amp_node = AMP_DOM_Utils::create_node(
			Document::fromNode( $node ),
			$this->amp_tag,
			$attributes
		);

		$this->unwrap_p_element( $node );
		$this->remove_script_sibling( $node, '//e.issuu.com/embed.js' );

		$node->parentNode->replaceChild( $amp_node, $node );
	}
}


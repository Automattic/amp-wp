<?php
/**
 * Class AMP_Tumblr_Embed_Handler
 *
 * @package AMP
 * @since 0.7
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Tumblr_Embed_Handler
 */
class AMP_Tumblr_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Default width.
	 *
	 * Tumblr embeds for web have a fixed width of 540px.
	 * See <https://tumblr.zendesk.com/hc/en-us/articles/226261028-Embed-pro-tips>.
	 *
	 * @var int
	 */
	protected $DEFAULT_WIDTH = 540;

	/**
	 * Base URL used for identifying embeds.
	 *
	 * @var string
	 */
	protected $base_embed_url = 'https://embed.tumblr.com/embed/post/';

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		return $dom->xpath->query( sprintf( '//div[ @class = "tumblr-post" and starts-with( @data-href, "%s" ) ]', $this->base_embed_url ) );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$dom        = Document::fromNode( $node );
		$iframe_src = $node->getAttribute( 'data-href' );

		$attributes = [
			'src'       => $iframe_src,
			'layout'    => 'responsive',
			'width'     => $this->args['width'],
			'height'    => $this->args['height'],
			'resizable' => '',
			'sandbox'   => 'allow-scripts allow-popups allow-same-origin',
		];

		$amp_node = AMP_DOM_Utils::create_node(
			$dom,
			$this->amp_tag,
			$attributes
		);

		// Add an overflow node to allow the amp-iframe to resize.
		$overflow_node = AMP_DOM_Utils::create_node(
			$dom,
			'div',
			[
				'overflow'   => '',
				'tabindex'   => 0,
				'role'       => 'button',
				'aria-label' => esc_attr__( 'See more', 'amp' ),
			]
		);

		$overflow_node->textContent = esc_html__( 'See more', 'amp' );
		$amp_node->appendChild( $overflow_node );

		// Append the original link as a placeholder node.
		if ( $node->firstChild instanceof DOMElement && 'a' === $node->firstChild->nodeName ) {
			$placeholder_node = $node->firstChild;
			$placeholder_node->setAttribute( 'placeholder', '' );
			$amp_node->appendChild( $placeholder_node );
		}

		$this->remove_script_sibling( $node, 'assets.tumblr.com/post.js' );

		$node->parentNode->replaceChild( $amp_node, $node );
	}
}


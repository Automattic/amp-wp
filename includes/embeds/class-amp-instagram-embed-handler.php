<?php
/**
 * Class AMP_Instagram_Embed_Handler
 *
 * @package AMP
 */

use AmpProject\AmpWP\Embed\Registerable;
use AmpProject\Dom\Document;

/**
 * Class AMP_Instagram_Embed_Handler
 *
 * Much of this class is borrowed from Jetpack embeds
 */
class AMP_Instagram_Embed_Handler extends AMP_Base_Embed_Handler implements Registerable {

	const URL_PATTERN = '#https?:\/\/(www\.)?instagr(\.am|am\.com)\/(p|tv)\/([A-Za-z0-9-_]+)#i';

	/**
	 * Default width.
	 *
	 * @var int
	 */
	protected $DEFAULT_WIDTH = 600;

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
	protected $amp_tag = 'amp-instagram';

	/**
	 * Register the embed.
	 *
	 * @return void
	 */
	public function register_embed() {
		if ( version_compare( get_bloginfo( 'version' ), '5.1', '>=' ) ) {
			return;
		}

		// The oEmbed provider for Instagram does not accommodate Instagram TV URLs on WP < 5.1. Modifying the provider format
		// here will allow for the oEmbed HTML to be fetched, and can then sanitized later below.
		wp_oembed_remove_provider( '#https?://(www\.)?instagr(\.am|am\.com)/p/.*#i' );
		wp_oembed_add_provider( self::URL_PATTERN, 'https://api.instagram.com/oembed', true );
	}

	/**
	 * Unregister the embed.
	 *
	 * @return void
	 */
	public function unregister_embed() {
	}

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		return $dom->xpath->query( '//blockquote[ @data-instgrm-permalink ]' );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$instagram_id = $this->get_instagram_id_from_url( $node->getAttribute( 'data-instgrm-permalink' ) );

		$node_args = [
			'data-shortcode' => $instagram_id,
			'layout'         => 'responsive',
			'width'          => $this->DEFAULT_WIDTH,
			'height'         => $this->DEFAULT_HEIGHT,
		];

		if ( true === $node->hasAttribute( 'data-instgrm-captioned' ) ) {
			$node_args['data-captioned'] = '';
		}

		$new_node = AMP_DOM_Utils::create_node( Document::fromNode( $node ), $this->amp_tag, $node_args );

		$this->remove_script_sibling( $node, 'instagram.com/embed.js' );

		$node->parentNode->replaceChild( $new_node, $node );
	}

	/**
	 * Get Instagram ID from URL.
	 *
	 * @param string $url URL.
	 * @return string|false The ID parsed from the URL or false if not found.
	 */
	private function get_instagram_id_from_url( $url ) {
		$found = preg_match( self::URL_PATTERN, $url, $matches );

		if ( ! $found ) {
			return false;
		}

		return end( $matches );
	}
}

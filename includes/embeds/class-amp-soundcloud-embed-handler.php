<?php
/**
 * Class AMP_SoundCloud_Embed_Handler
 *
 * @package AMP
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_SoundCloud_Embed_Handler
 *
 * @since 0.5
 */
class AMP_SoundCloud_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Default height.
	 *
	 * @var int
	 */
	protected $DEFAULT_HEIGHT = 200;

	/**
	 * Base URL used for identifying embeds.
	 *
	 * @var string
	 */
	protected $base_embed_url = 'https://w.soundcloud.com/player/';

	/**
	 * Default AMP tag to be used when sanitizing embeds.
	 *
	 * @var string
	 */
	protected $amp_tag = 'amp-soundcloud';

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
		$iframe_src = $node->getAttribute( 'src' );

		parse_str( wp_parse_url( $iframe_src, PHP_URL_QUERY ), $query );
		if ( empty( $query['url'] ) ) {
			return;
		}

		$embed_id = $this->parse_embed_id_from_url( $query['url'] );
		if ( null === $embed_id ) {
			return;
		}

		$attributes = [];

		if ( isset( $embed_id['track_id'] ) ) {
			$attributes['data-trackid'] = $embed_id['track_id'];
		} elseif ( isset( $embed_id['playlist_id'] ) ) {
			$attributes['data-playlistid'] = $embed_id['playlist_id'];
		}

		$attributes['height'] = $node->hasAttribute( 'height' )
			? $node->getAttribute( 'height' )
			: $this->args['height'];

		if ( $node->hasAttribute( 'width' ) ) {
			$attributes['width']  = $node->getAttribute( 'width' );
			$attributes['layout'] = 'responsive';
		} else {
			$attributes['layout'] = 'fixed-height';
		}

		if ( isset( $query['visual'] ) ) {
			$attributes['data-visual'] = rest_sanitize_boolean( $query['visual'] ) ? 'true' : 'false';
		}

		$amp_node = AMP_DOM_Utils::create_node(
			Document::fromNode( $node ),
			$this->amp_tag,
			$attributes
		);

		$this->unwrap_p_element( $node );

		$node->parentNode->replaceChild( $amp_node, $node );
	}

	/**
	 * Get embed ID from Soundcloud iframe src.
	 *
	 * @param string $url URL.
	 * @return array|null Array with key being the embed type and value being the embed ID, or null if embed type and ID could not be found.
	 */
	private function parse_embed_id_from_url( $url ) {
		if ( preg_match( '#/(?P<type>tracks|playlists)/(?P<id>\d+)#', $url, $matches ) ) {
			if ( 'tracks' === $matches['type'] ) {
				return [ 'track_id' => $matches['id'] ];
			}
			if ( 'playlists' === $matches['type'] ) {
				return [ 'playlist_id' => $matches['id'] ];
			}
		}

		return null;
	}
}

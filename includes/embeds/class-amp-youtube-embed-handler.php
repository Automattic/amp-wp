<?php
/**
 * Class AMP_YouTube_Embed_Handler
 *
 * @package AMP
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_YouTube_Embed_Handler
 *
 * Much of this class is borrowed from Jetpack embeds.
 */
class AMP_YouTube_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Ratio for calculating the default height from the content width.
	 *
	 * @param float
	 */
	const RATIO = 0.5625;

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
	protected $DEFAULT_HEIGHT = 338;

	/**
	 * Default AMP tag to be used when sanitizing embeds.
	 *
	 * @var string
	 */
	protected $amp_tag = 'amp-youtube';

	/**
	 * List of domains that are applicable for this embed.
	 *
	 * @var string[]
	 */
	protected $applicable_domains = [ 'youtu.be', 'youtube.com', 'youtube-nocookie.com' ];

	/**
	 * AMP_YouTube_Embed_Handler constructor.
	 *
	 * @param array $args Height, width and maximum width for embed.
	 */
	public function __construct( $args = [] ) {
		parent::__construct( $args );

		if ( isset( $this->args['content_max_width'] ) ) {
			// Set default width/height; these will be overridden by whatever YouTube specifies.
			$max_width            = $this->args['content_max_width'];
			$this->args['width']  = $max_width;
			$this->args['height'] = round( $max_width * self::RATIO );
		}
	}

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		$query_segments = array_map(
			static function ( $domain ) {
				return sprintf( 'contains( @src, "%s" )', $domain );
			},
			$this->applicable_domains
		);
		$query          = implode( ' or ', $query_segments );
		return $dom->xpath->query( sprintf( '//iframe[ %s ]', $query ) );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$iframe_src = $node->getAttribute( 'src' );
		$video_id   = $this->get_video_id_from_url( $iframe_src );

		$attributes = [
			'data-videoid' => $video_id,
			'layout'       => 'responsive',
			'width'        => $this->args['width'],
			'height'       => $this->args['height'],
		];

		if ( ! empty( $node->getAttribute( 'title' ) ) ) {
			$attributes['title'] = $node->getAttribute( 'title' );
		}

		if ( ! empty( $node->getAttribute( 'width' ) ) ) {
			$attributes['width'] = $node->getAttribute( 'width' );
		}

		if ( ! empty( $node->getAttribute( 'height' ) ) ) {
			$attributes['height'] = $node->getAttribute( 'height' );
		}

		$amp_node = AMP_DOM_Utils::create_node(
			Document::fromNode( $node ),
			$this->amp_tag,
			$attributes
		);

		$this->append_placeholder( $amp_node, $video_id, isset( $attributes['title'] ) ? $attributes['title'] : null );

		$this->unwrap_p_element( $node );

		$node->parentNode->replaceChild( $amp_node, $node );
	}

	/**
	 * Append placeholder as a child of the AMP component.
	 *
	 * @param DOMElement $node AMP component.
	 * @param string     $video_id Video ID.
	 * @param string     $video_title Video title.
	 */
	private function append_placeholder( DOMElement $node, $video_id, $video_title ) {
		$img_attributes = [
			'src'        => esc_url_raw( sprintf( 'https://i.ytimg.com/vi/%s/hqdefault.jpg', $video_id ) ),
			'layout'     => 'fill',
			'object-fit' => 'cover',
		];
		if ( $video_title ) {
			$img_attributes['alt'] = $video_title;
		}

		$img_node = AMP_DOM_Utils::create_node(
			Document::fromNode( $node->ownerDocument ),
			'img',
			$img_attributes
		);

		$placeholder_node = AMP_DOM_Utils::create_node(
			Document::fromNode( $node ),
			'a',
			[
				'placeholder' => '',
				'href'        => esc_url_raw( sprintf( 'https://www.youtube.com/watch?v=%s', $video_id ) ),
			]
		);

		$placeholder_node->appendChild( $img_node );

		$node->appendChild( $placeholder_node );
	}

	/**
	 * Determine the video ID from the URL.
	 *
	 * @param string $url URL.
	 * @return string|false Video ID, or false if none could be retrieved.
	 */
	private function get_video_id_from_url( $url ) {
		$parsed_url = wp_parse_url( $url );

		if ( ! isset( $parsed_url['host'] ) ) {
			return false;
		}

		$domain = implode( '.', array_slice( explode( '.', $parsed_url['host'] ), -2 ) );
		if ( ! in_array( $domain, $this->applicable_domains, true ) ) {
			return false;
		}

		if ( ! isset( $parsed_url['path'] ) ) {
			return false;
		}

		$segments = explode( '/', trim( $parsed_url['path'], '/' ) );

		$query_vars = [];
		if ( isset( $parsed_url['query'] ) ) {
			wp_parse_str( $parsed_url['query'], $query_vars );

			// Handle video ID in v query param, e.g. <https://www.youtube.com/watch?v=XOY3ZUO6P0k>.
			// Support is also included for other query params which don't appear to be supported by YouTube anymore.
			if ( isset( $query_vars['v'] ) ) {
				return $query_vars['v'];
			}
			if ( isset( $query_vars['vi'] ) ) {
				return $query_vars['vi'];
			}
		}

		if ( empty( $segments[0] ) ) {
			return false;
		}

		// For shortened URLs like <http://youtu.be/XOY3ZUO6P0k>, the slug is the first path segment.
		if ( 'youtu.be' === $parsed_url['host'] ) {
			return $segments[0];
		}

		// For non-shortened URLs, the video ID is in the second path segment. For example:
		// * https://www.youtube.com/watch/XOY3ZUO6P0k
		// * https://www.youtube.com/embed/XOY3ZUO6P0k
		// Other top-level segments indicate non-video URLs. There are examples of URLs having segments including
		// 'v', 'vi', and 'e' but these do not work anymore. In any case, they are added here for completeness.
		if ( ! empty( $segments[1] ) && in_array( $segments[0], [ 'embed', 'watch', 'v', 'vi', 'e' ], true ) ) {
			return $segments[1];
		}

		return false;
	}
}

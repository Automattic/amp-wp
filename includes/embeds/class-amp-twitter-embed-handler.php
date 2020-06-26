<?php
/**
 * Class AMP_Twitter_Embed_Handler
 *
 * @package AMP
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Twitter_Embed_Handler
 *
 *  Much of this class is borrowed from Jetpack embeds
 */
class AMP_Twitter_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * URL pattern for a Tweet URL.
	 *
	 * @since 0.2
	 * @var string
	 */
	const URL_PATTERN = '#https?:\/\/twitter\.com(?:\/\#\!\/|\/)(?P<username>[a-zA-Z0-9_]{1,20})\/status(?:es)?\/(?P<tweet>\d+)#i';

	/**
	 * URL pattern for a Twitter timeline.
	 *
	 * @since 1.0
	 * @var string
	 */
	const URL_PATTERN_MOMENT = '#https?:\/\/twitter\.com\/i\/moments\/(?P<id>\d+)#i';

	/**
	 * URL pattern for a Twitter timeline.
	 *
	 * @since 1.0
	 * @var string
	 */
	const URL_PATTERN_TIMELINE = '#https?:\/\/twitter\.com(?:\/\#\!\/|\/)(?P<username>[a-zA-Z0-9_]{1,20})(?:$|\/(?P<type>likes|lists|timelines)(\/(?P<id>[a-zA-Z0-9_-]+))?|.+?)#i';

	/**
	 * Default AMP tag to be used when sanitizing embeds.
	 *
	 * @var string
	 */
	protected $amp_tag = 'amp-twitter';

	/**
	 * Sanitize all embeds on the page to be AMP compatible.
	 *
	 * @param Document $dom DOM.
	 */
	public function sanitize_raw_embeds( Document $dom ) {
		$this->sanitize_tweet_embeds( $dom );
		$this->sanitize_timeline_embeds( $dom );
		$this->sanitize_moment_embeds( $dom );
	}

	/**
	 * Sanitize tweets.
	 *
	 * @param Document $dom Document.
	 */
	private function sanitize_tweet_embeds( Document $dom ) {
		$nodes = $dom->xpath->query( '//blockquote[ @class = "twitter-tweet" ]' );
		$this->sanitize_raw_embeds_with_type( 'tweet', $nodes );
	}

	/**
	 * Sanitize timelines.
	 *
	 * @param Document $dom Document.
	 */
	private function sanitize_timeline_embeds( Document $dom ) {
		$nodes = $dom->xpath->query( '//a[ @class = "twitter-timeline" ]' );
		$this->sanitize_raw_embeds_with_type( 'timeline', $nodes );
	}

	/**
	 * Sanitize moments.
	 *
	 * @param Document $dom Document.
	 */
	private function sanitize_moment_embeds( Document $dom ) {
		$nodes = $dom->xpath->query( '//a[ @class = "twitter-moment" ]' );
		$this->sanitize_raw_embeds_with_type( 'moment', $nodes );
	}

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList|null A list of DOMElement nodes, or null if not implemented.
	 */
	protected function get_raw_embed_nodes( Document $dom ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return null;
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	}

	/**
	 * Sanitize list of raw embeds based on the embed type.
	 *
	 * @param string      $embed_type The type of Twitter embed.
	 * @param DOMNodeList $nodes List of DOMElement nodes.
	 */
	protected function sanitize_raw_embeds_with_type( $embed_type, DOMNodeList $nodes ) {
		foreach ( $nodes as $node ) {
			/**
			 * Node.
			 *
			 * @var DOMElement $node
			 */

			if ( ! $this->is_raw_embed( $node ) ) {
				continue;
			}

			$attributes = [
				'width'  => $this->DEFAULT_WIDTH,
				'height' => $this->DEFAULT_HEIGHT,
				'layout' => 'responsive',
			];

			if ( $node->hasAttribute( 'data-width' ) ) {
				$attributes['width'] = $node->getAttribute( 'data-width' );
				$node->removeAttribute( 'data-width' );
			}

			if ( $node->hasAttribute( 'data-height' ) ) {
				$attributes['height'] = $node->getAttribute( 'data-height' );
				$node->removeAttribute( 'data-height' );
			}

			switch ( $embed_type ) {
				case 'tweet':
					$tweet_id = $this->get_tweet_id( $node );
					if ( ! $tweet_id ) {
						return;
					}

					$attributes['data-tweetid'] = $tweet_id;
					break;
				case 'timeline':
					$timeline_attrs = $this->get_timeline_attributes( $node->getAttribute( 'href' ) );
					if ( ! $timeline_attrs ) {
						return;
					}

					if (
						( ! isset( $timeline_attrs['type'] ) && isset( $timeline_attrs['username'] ) ) ||
						( isset( $timeline_attrs['type'] ) && in_array( $timeline_attrs['type'], [ 'likes', 'lists' ], true ) )
					) {
						$attributes['data-timeline-source-type'] = 'url';
						$attributes['data-timeline-url']         = $node->getAttribute( 'href' );
					}

					break;
				case 'moment':
					$moment_id = $this->get_moment_id( $node->getAttribute( 'href' ) );
					if ( ! $moment_id ) {
						return;
					}

					$attributes['data-momentid'] = $moment_id;
					break;
				default:
					return;
			}

			if ( $node->hasAttributes() ) {
				foreach ( $node->attributes as $attr ) {
					// Strip `data-` attributes and apply them to the AMP component.
					if ( false !== strpos( $attr->nodeName, 'data-', 0 ) ) {
						$attributes[ $attr->nodeName ] = $attr->nodeValue;
						$node->removeAttribute( $attr->nodeName );
					}
				}
			}

			$new_node = AMP_DOM_Utils::create_node(
				Document::fromNode( $node ),
				'amp-twitter',
				$attributes
			);

			/**
			 * Placeholder element to append to the new node.
			 *
			 * @var DOMElement $placeholder
			 */
			$placeholder = $node->cloneNode( true );
			$placeholder->setAttribute( 'placeholder', '' );

			$new_node->appendChild( $placeholder );

			$this->remove_br_sibling_elements( $node );
			$this->remove_script_sibling( $node, 'platform.twitter.com/widgets.js' );
			// We can unwrap the <p> tag once the accompanied <script> is removed.
			$this->unwrap_p_element( $node );

			$node->parentNode->replaceChild( $new_node, $node );
		}
	}

	/**
	 * Extracts Tweet id.
	 *
	 * @param DOMElement $node The DOMNode to adjust and replace.
	 * @return string|null Tweet ID, or null if not found.
	 */
	private function get_tweet_id( DOMElement $node ) {
		$anchors = $node->getElementsByTagName( 'a' );

		/**
		 * Anchor.
		 *
		 * @var DOMElement $anchor
		 */
		foreach ( $anchors as $anchor ) {
			$found = preg_match( self::URL_PATTERN, $anchor->getAttribute( 'href' ), $matches );
			if ( $found ) {
				return $matches['tweet'];
			}
		}

		return null;
	}

	/**
	 * Parse Twitter timeline attributes from a URL.
	 *
	 * @param string $url URL.
	 * @return array|null Timeline attributes, or null if the attributes were not found.
	 */
	private function get_timeline_attributes( $url ) {
		$found = preg_match( self::URL_PATTERN_TIMELINE, $url, $matches );
		if ( $found ) {
			return $matches;
		}

		return null;
	}

	/**
	 * Parse Twitter moment ID from a URL.
	 *
	 * @param string $url URL.
	 * @return array|null Timeline attributes, or null if the moment ID was not found.
	 */
	private function get_moment_id( $url ) {
		$found = preg_match( self::URL_PATTERN_MOMENT, $url, $matches );
		if ( $found ) {
			return $matches['id'];
		}

		return null;
	}

	/**
	 * Remove all <br> sibling elements for specified node.
	 *
	 * @param DOMElement $node The node to remove <br> siblings from.
	 */
	private function remove_br_sibling_elements( DOMElement $node ) {
		$next_element_sibling = $node->nextSibling;

		while ( $next_element_sibling && $next_element_sibling instanceof DOMElement && 'br' === $next_element_sibling->nodeName ) {
			$next_element_sibling->parentNode->removeChild( $next_element_sibling );
			$next_element_sibling = $node->nextSibling;
		}
	}
}

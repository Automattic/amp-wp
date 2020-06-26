<?php
/**
 * Class AMP_Facebook_Embed_Handler
 *
 * @package AMP
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Facebook_Embed_Handler
 */
class AMP_Facebook_Embed_Handler extends AMP_Base_Embed_Handler {
	/**
	 * Default height.
	 *
	 * @var int
	 */
	protected $DEFAULT_HEIGHT = 400;

	/**
	 * Default AMP tag to be used when sanitizing embeds.
	 *
	 * @var string
	 */
	protected $amp_tag = 'amp-facebook';

	/**
	 * Sanitize all embeds on the page to be AMP compatible.
	 *
	 * @param Document $dom DOM.
	 */
	public function sanitize_raw_embeds( Document $dom ) {
		$nodes = $this->get_raw_embed_nodes( $dom );

		if ( 0 < $nodes->length ) {
			foreach ( $nodes as $node ) {
				if ( ! $this->is_raw_embed( $node ) ) {
					continue;
				}
				$this->sanitize_raw_embed( $node );
			}

			$this->remove_fb_root_nodes( $dom );
		}
	}

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		return $dom->getElementsByTagName( 'div' );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$embed_type = $this->get_embed_type( $node );

		if ( null === $embed_type ) {
			return;
		}

		$attributes = [
			'data-amp-layout' => 'responsive',
			'width'           => $node->hasAttribute( 'data-width' ) ? $node->getAttribute( 'data-width' ) : $this->DEFAULT_WIDTH,
			'height'          => $node->hasAttribute( 'data-height' ) ? $node->getAttribute( 'data-height' ) : $this->DEFAULT_HEIGHT,
		];

		$node->removeAttribute( 'data-width' );
		$node->removeAttribute( 'data-height' );

		foreach ( $node->attributes as $attribute ) {
			if ( 'data-' === substr( $attribute->nodeName, 0, 5 ) ) {
				$attributes[ $attribute->nodeName ] = $attribute->nodeValue;
			}
		}

		if ( 'page' === $embed_type ) {
			$amp_tag = 'amp-facebook-page';
		} elseif ( 'like' === $embed_type ) {
			$amp_tag = 'amp-facebook-like';
		} elseif ( 'comments' === $embed_type ) {
			$amp_tag = 'amp-facebook-comments';
		} else {
			$amp_tag                     = $this->amp_tag;
			$attributes['data-embed-as'] = $embed_type;
		}

		$amp_facebook_node = AMP_DOM_Utils::create_node(
			Document::fromNode( $node ),
			$amp_tag,
			$attributes
		);

		$fallback = null;
		foreach ( $node->childNodes as $child_node ) {
			if ( $child_node instanceof DOMElement && false !== strpos( $child_node->getAttribute( 'class' ), 'fb-xfbml-parse-ignore' ) ) {
				$fallback = $child_node;
				$child_node->parentNode->removeChild( $child_node );
				$fallback->setAttribute( 'fallback', '' );
				break;
			}
		}

		$node->parentNode->replaceChild( $amp_facebook_node, $node );
		if ( $fallback ) {
			$amp_facebook_node->appendChild( $fallback );
		}

		$this->did_convert_elements = true;
	}

	/**
	 * Remove instances of the `fb-root` div element and its accompanied Facebook Connect JS script since they would be irrelevant.
	 *
	 * @param Document $dom DOM.
	 */
	private function remove_fb_root_nodes( Document $dom ) {
		$fb_root_query = $dom->xpath->query( '//div[ @id = "fb-root" ]' );

		if ( 0 < $fb_root_query->length ) {
			// Remove instances of <div id="fb-root">.
			foreach ( $fb_root_query as $fb_root ) {
				$fb_root->parentNode->removeChild( $fb_root );
			}

			// Remove instances of the accompanied script.
			$script_query = $dom->xpath->query( '//script[ starts-with( @src, "https://connect.facebook.net" ) and contains( @src, "sdk.js" ) ]' );

			foreach ( $script_query as $script ) {
				/** @var DOMElement $parent_node */
				$parent_node = $script->parentNode;
				$parent_node->removeChild( $script );

				// Remove parent node if it is an empty <p> tag.
				if ( 'p' === $parent_node->nodeName && null === $parent_node->firstChild ) {
					$parent_node->parentNode->removeChild( $parent_node );
				}
			}
		}
	}

	/**
	 * Get embed type.
	 *
	 * @param DOMElement $node The DOMNode to adjust and replace.
	 * @return string|null Embed type or null if not detected.
	 */
	private function get_embed_type( DOMElement $node ) {
		$class_attr = $node->getAttribute( 'class' );
		if ( null === $class_attr || ! $node->hasAttribute( 'data-href' ) ) {
			return null;
		}

		if ( false !== strpos( $class_attr, 'fb-post' ) ) {
			return 'post';
		}

		if ( false !== strpos( $class_attr, 'fb-video' ) ) {
			return 'video';
		}

		if ( false !== strpos( $class_attr, 'fb-page' ) ) {
			return 'page';
		}

		if ( false !== strpos( $class_attr, 'fb-like' ) ) {
			return 'like';
		}

		if ( false !== strpos( $class_attr, 'fb-comments' ) ) {
			return 'comments';
		}

		if ( false !== strpos( $class_attr, 'fb-comment-embed' ) ) {
			return 'comment';
		}

		return null;
	}
}

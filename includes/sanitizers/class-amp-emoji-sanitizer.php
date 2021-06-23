<?php
/**
 * Class AMP_Emoji_Sanitizer
 *
 * @since 2.1.0
 * @package AMP
 */

/**
 * Class AMP_Emoji_Sanitizer
 *
 * @since 2.1.0
 * @internal
 */
class AMP_Emoji_Sanitizer extends AMP_Base_Sanitizer {

	/**
	 * Default args.
	 *
	 * @var array
	 */
	protected $DEFAULT_ARGS = [
		'use_svg'     => true,
		'inline_svg'  => true,
		'max_fetches' => 12, // WordPress pages at 90th percentile have a maximum of 12 emoji. See https://github.com/ampproject/amp-wp/pull/5498#issuecomment-706840922.
	];

	/**
	 * Args.
	 *
	 * @var array {
	 *      @type bool $use_svg     Whether SVG emoji should be used.
	 *      @type bool $inline_svg  Whether SVG emoji should be inlined.
	 *      @type int  $max_fetches Maximum number of fetches for SVG from s.w.org.
	 * }
	 */
	protected $args;

	/**
	 * Number of SVG images fetched.
	 *
	 * @var int
	 */
	private $fetched_count = 0;

	/**
	 * Twemoji version.
	 *
	 * @todo Replace this with the current version in WordPress core.
	 *
	 * @var string
	 */
	private $twemoji_version = '';

	/**
	 * CDN URL.
	 *
	 * @todo Replace this with what is in the current version of WordPress core.
	 *
	 * @var string
	 */
	private $twemoji_cdn_url = '';

	/**
	 * Emoji lookup.
	 *
	 * Mapping of UTF-8 emoji characters to their Twemoji image basenames (without file extension).
	 *
	 * @var array
	 */
	private $emoji_lookup = [];

	/**
	 * Regular expression to match a UTF-8 emoji character.
	 *
	 * @todo At build time, replace with pre-computed regex.
	 *
	 * @var string
	 */
	private $emoji_regex = '';

	/**
	 * SVG defs element that contains the symbols for Twemoji on the page.
	 *
	 * @var DOMElement
	 */
	private $twemoji_svg_defs_element;

	/**
	 * Record of which Twemoji have had symbols added.
	 *
	 * @var array
	 */
	private $added_defs_symbols = [];

	/**
	 * Replace UTF-8 emoji with Twemoji SVG.
	 *
	 * @see wp_staticize_emoji()
	 */
	public function sanitize() {
		if (
			empty( $this->twemoji_version )
			||
			empty( $this->twemoji_cdn_url )
			||
			empty( $this->emoji_lookup )
			||
			empty( $this->emoji_regex )
		) {
			if ( ! $this->gather_emoji_data() ) {
				return;
			}
		}

		$query = $this->dom->xpath->query( './/text()', $this->dom->body );
		foreach ( $query as $text_node ) {
			/** @var DOMText $text_node */

			if ( trim( $text_node->nodeValue ) === '' ) {
				continue;
			}

			// Ignore processing of specific tags.
			if ( in_array( $text_node->parentNode->nodeName, [ 'code', 'pre', 'style', 'script', 'textarea' ], true ) ) {
				continue;
			}

			$parts = preg_split( "/({$this->emoji_regex})/u", $text_node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE );
			if ( 1 === count( $parts ) ) {
				continue;
			}

			$fragment = $this->dom->createDocumentFragment();

			foreach ( $parts as $part ) {
				if ( '' === $part ) {
					continue;
				}

				if ( isset( $this->emoji_lookup[ $part ] ) ) {
					$fragment->appendChild( $this->create_emoji_image( $part, $this->emoji_lookup[ $part ] ) );
				} else {
					$fragment->appendChild( $this->dom->createTextNode( $part ) );
				}
			}
			$text_node->parentNode->replaceChild( $fragment, $text_node );
		}
	}

	/**
	 * Create emoji fragment.
	 *
	 * @param string $emojum   Emoji character.
	 * @param string $basename Emoji basename minus file extension.
	 *
	 * @return DOMElement SVG or IMG element.
	 */
	private function create_emoji_image( $emojum, $basename ) {
		if ( $this->args['use_svg'] ) {
			$img_url = $this->twemoji_cdn_url . 'svg/' . $basename . '.svg';

			if ( $this->args['inline_svg'] ) {
				$svg_element = $this->get_svg_element( $emojum, $basename, $img_url );
				if ( $svg_element instanceof DOMElement ) {
					return $svg_element;
				}
			}
		} else {
			$img_url = $this->twemoji_cdn_url . '72x72/' . $basename . '.png';
		}

		// Fallback to when the SVG cannot be inlined.
		$img_element = $this->dom->createElement( 'img' );
		$img_element->setAttribute( 'class', 'emoji' );
		$img_element->setAttribute( 'alt', $emojum );
		$img_element->setAttribute( 'draggable', 'false' );
		$img_element->setAttribute( 'src', $img_url );
		$img_element->setAttribute( 'width', '72' );
		$img_element->setAttribute( 'height', '72' );

		return $img_element;
	}

	/**
	 * Get Twemoji SVG defs element.
	 *
	 * @return DOMElement Defs element.
	 */
	private function get_twemoji_svg_defs_element() {
		if ( ! isset( $this->twemoji_svg_defs_element ) ) {
			$svg_element = $this->dom->createElement( 'svg' );
			$svg_element->setAttribute( 'id', 'amp-twemoji' );
			$svg_element->setAttribute( 'hidden', '' );
			$this->twemoji_svg_defs_element = $this->dom->createElement( 'defs' );
			$svg_element->appendChild( $this->twemoji_svg_defs_element );
			$this->dom->body->insertBefore( $svg_element, $this->dom->body->firstChild ); // Add to beginning of body to ensure symbols can be used immediately.
		}
		return $this->twemoji_svg_defs_element;
	}

	/**
	 * Get SVG document.
	 *
	 * @param string $emojum   Emoji character.
	 * @param string $basename Emoji basename minus file extension.
	 * @param string $svg_url  SVG URL.
	 * @return DOMElement|WP_Error SVG element string or WP_Error on failure.
	 */
	private function get_svg_element( $emojum, $basename, $svg_url ) {
		$added = $this->ensure_twemoji_symbol_added( $emojum, $basename, $svg_url );
		if ( $added instanceof WP_Error ) {
			return $added;
		}

		$svg_element = $this->dom->createElement( 'svg' );
		$svg_element->setAttribute( 'class', 'emoji' );
		$svg_element->setAttribute( 'role', 'img' );
		$use_element = $this->dom->createElement( 'use' );
		$use_element->setAttribute( 'href', "#twemoji-{$basename}" );
		$svg_element->appendChild( $use_element );

		return $svg_element;
	}

	/**
	 * Ensure SVG symbol is added for Twemoji.
	 *
	 * @param string $emojum   Emoji character.
	 * @param string $basename Emoji basename minus file extension.
	 * @param string $svg_url  SVG URL.
	 * @return true|WP_Error SVG element string or WP_Error on failure.
	 */
	private function ensure_twemoji_symbol_added( $emojum, $basename, $svg_url ) {
		if ( isset( $this->added_defs_symbols[ $emojum ] ) ) {
			return true;
		}

		$transient_key = "amp-emoji-svg-{$this->twemoji_version}-{$basename}";
		$svg_doc       = get_transient( $transient_key );

		if ( false === $svg_doc ) {
			if ( $this->fetched_count >= $this->args['max_fetches'] ) {
				return new WP_Error( 'exceeded_max_fetches' );
			}
			$this->fetched_count++;

			$response = wp_remote_get( $svg_url );
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$error = new WP_Error( wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_response_message( $response ) );
				set_transient(
					$transient_key,
					$error,
					DAY_IN_SECONDS
				);
				return $error;
			}

			$svg_doc = wp_remote_retrieve_body( $response );
			set_transient(
				$transient_key,
				$svg_doc,
				MONTH_IN_SECONDS
			);
		} elseif ( $svg_doc instanceof WP_Error ) {
			return $svg_doc;
		}

		$svg_dom = new DOMDocument();
		if ( ! @$svg_dom->loadHTML( $svg_doc ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'parse_error' );
		}

		$svg_element = $svg_dom->getElementsByTagName( 'svg' )->item( 0 );
		if ( ! $svg_element instanceof DOMElement ) {
			return new WP_Error( 'no_svg_element' );
		}

		/** @var DOMElement $svg_element */
		$svg_element = $this->dom->importNode( $svg_element, true );

		// Add the SVG to a symbol which we'll reuse for each instance of the emoji on the page.
		$symbol = $this->dom->createElement( 'symbol' );
		foreach ( $svg_element->attributes as $attribute ) {
			/** @var DOMAttr $attribute */
			$symbol->setAttribute( $attribute->nodeName, $attribute->nodeValue );
		}
		while ( $svg_element->firstChild ) {
			$symbol->appendChild( $svg_element->removeChild( $svg_element->firstChild ) );
		}
		$symbol->setAttribute( 'id', 'twemoji-' . $basename );
		$symbol->setAttribute( 'data-src', $svg_url );

		$title = $this->dom->createElement( 'title' );
		$title->appendChild( $this->dom->createTextNode( $emojum ) );
		$symbol->insertBefore( $title, $symbol->firstChild );

		$this->get_twemoji_svg_defs_element()->appendChild( $symbol );
		$this->added_defs_symbols[ $emojum ] = true;

		return true;
	}

	/**
	 * Build emoji data.
	 *
	 * This is used during development only.
	 *
	 * @return bool Whether data was gathered successfully.
	 */
	private function gather_emoji_data() {
		// Obtain version.
		$staticized = wp_staticize_emoji( '🙂' );
		if ( preg_match( '#(?P<cdn_url>https?://s\.w\.org/images/core/emoji/(?P<version>\d+(?:\.\d+)*)/)#', $staticized, $matches ) ) {
			$this->twemoji_version = $matches['version'];
			$this->twemoji_cdn_url = $matches['cdn_url'];
		} else {
			return false;
		}

		// Obtain emoji lookup and emojum regex.
		$this->emoji_lookup = [];
		$this->emoji_regex  = '';
		foreach ( _wp_emoji_list() as $emoji_entity ) {
			$basename = rtrim(
				str_replace(
					[ '&', '#', 'x', ';' ],
					[ '', '', '', '-' ],
					$emoji_entity
				),
				'-'
			);

			$emojum = html_entity_decode( $emoji_entity, ENT_NOQUOTES, 'utf-8' );

			$this->emoji_lookup[ $emojum ] = $basename;

			if ( $this->emoji_regex ) {
				$this->emoji_regex .= '|';
			}
			$this->emoji_regex .= preg_quote( $emojum, '/' );
		}

		return true;
	}
}

<?php
/**
 * Class AMP_Twitter_Embed_Handler
 *
 * @package AMP
 */

/**
 * Class AMP_Twitter_Embed_Handler
 *
 *  Much of this class is borrowed from Jetpack embeds
 */
class AMP_Twitter_Embed_Handler extends AMP_Base_Embed_Handler {
	const URL_PATTERN = '#http(s|):\/\/twitter\.com(\/\#\!\/|\/)([a-zA-Z0-9_]{1,20})\/status(es)*\/(\d+)#i';

	/**
	 * Register embed.
	 */
	public function register_embed() {
		add_shortcode( 'tweet', array( $this, 'shortcode' ) );
		wp_embed_register_handler( 'amp-twitter', self::URL_PATTERN, array( $this, 'oembed' ), -1 );
	}

	/**
	 * Unregister embed.
	 */
	public function unregister_embed() {
		remove_shortcode( 'tweet' );
		wp_embed_unregister_handler( 'amp-twitter', -1 );
	}

	/**
	 * Gets AMP-compliant markup for the Twitter shortcode.
	 *
	 * @param array $attr The Twitter attributes.
	 * @return string Twitter shortcode markup.
	 */
	public function shortcode( $attr ) {
		$attr = wp_parse_args( $attr, array(
			'tweet' => false,
		) );

		if ( empty( $attr['tweet'] ) && ! empty( $attr[0] ) ) {
			$attr['tweet'] = $attr[0];
		}

		$id = false;
		if ( is_numeric( $attr['tweet'] ) ) {
			$id = $attr['tweet'];
		} else {
			preg_match( self::URL_PATTERN, $attr['tweet'], $matches );
			if ( isset( $matches[5] ) && is_numeric( $matches[5] ) ) {
				$id = $matches[5];
			}

			if ( empty( $id ) ) {
				return '';
			}
		}

		$this->did_convert_elements = true;

		return AMP_HTML_Utils::build_tag(
			'amp-twitter',
			array(
				'data-tweetid' => $id,
				'layout'       => 'responsive',
				'width'        => $this->args['width'],
				'height'       => $this->args['height'],
			)
		);
	}

	/**
	 * Render oEmbed.
	 *
	 * @see \WP_Embed::shortcode()
	 *
	 * @param array  $matches URL pattern matches.
	 * @param array  $attr    Shortcode attribues.
	 * @param string $url     URL.
	 * @param string $rawattr Unmodified shortcode attributes.
	 * @return string Rendered oEmbed.
	 */
	public function oembed( $matches, $attr, $url, $rawattr ) {
		$id = false;

		if ( isset( $matches[5] ) && is_numeric( $matches[5] ) ) {
			$id = $matches[5];
		}

		if ( ! $id ) {
			return '';
		}

		return $this->shortcode( array( 'tweet' => $id ) );
	}
}

<?php
/**
 * Class AMP_Pinterest_Embed_Handler
 *
 * @package AMP
 */

use AmpProject\AmpWP\Embed\Registerable;
use AmpProject\Dom\Document;

/**
 * Class AMP_Pinterest_Embed_Handler
 */
class AMP_Pinterest_Embed_Handler extends AMP_Base_Embed_Handler implements Registerable {

	const URL_PATTERN = '#https?://(www\.)?pinterest\.com/pin/.*#i';

	/**
	 * Default width.
	 *
	 * @var int
	 */
	protected $DEFAULT_WIDTH = 450;

	/**
	 * Default height.
	 *
	 * @var int
	 */
	protected $DEFAULT_HEIGHT = 750;

	/**
	 * Default AMP tag to be used when sanitizing embeds.
	 *
	 * @var string
	 */
	protected $amp_tag = 'amp-pinterest';

	/**
	 * Registers embed.
	 */
	public function register_embed() {
		wp_embed_register_handler(
			$this->amp_tag,
			self::URL_PATTERN,
			[ $this, 'oembed' ],
			-1
		);
	}

	/**
	 * Unregisters embed.
	 */
	public function unregister_embed() {
		wp_embed_unregister_handler( $this->amp_tag, -1 );
	}

	/**
	 * WordPress OEmbed rendering callback.
	 *
	 * @param array  $matches URL pattern matches.
	 * @param array  $attr    Matched attributes.
	 * @param string $url     Matched URL.
	 * @return string HTML markup for rendered embed.
	 */
	public function oembed( $matches, $attr, $url ) {
		return $this->render( [ 'url' => $url ] );
	}

	/**
	 * Gets the rendered embed markup.
	 *
	 * @param array $args Embed rendering arguments.
	 * @return string HTML markup for rendered embed.
	 */
	public function render( $args ) {
		$args = wp_parse_args(
			$args,
			[
				'url' => false,
			]
		);

		if ( empty( $args['url'] ) ) {
			return '';
		}

		return AMP_HTML_Utils::build_tag(
			$this->amp_tag,
			[
				'width'    => $this->args['width'],
				'height'   => $this->args['height'],
				'data-do'  => 'embedPin',
				'data-url' => $args['url'],
			]
		);
	}

	/**
	 * Get all raw embeds from the DOM.
	 *
	 * @param Document $dom Document.
	 * @return DOMNodeList A list of DOMElement nodes.
	 */
	protected function get_raw_embed_nodes( Document $dom ) {
		return $dom->xpath->query( sprintf( '//%s', $this->amp_tag ) );
	}

	/**
	 * Make embed AMP compatible.
	 *
	 * @param DOMElement $node DOM element.
	 */
	protected function sanitize_raw_embed( DOMElement $node ) {
		$this->unwrap_p_element( $node );
	}
}

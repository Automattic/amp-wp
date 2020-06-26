<?php
/**
 * Class AMP_Embed_Sanitizer
 *
 * @package AMP
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Embed_Sanitizer
 *
 * Calls sanitize_raw_embeds method on embed handlers.
 */
class AMP_Embed_Sanitizer extends AMP_Base_Sanitizer {

	/**
	 * Embed handlers.
	 *
	 * @var AMP_Base_Embed_Handler[] AMP_Base_Embed_Handler[]
	 */
	private $embed_handlers = [];

	/**
	 * AMP_Embed_Sanitizer constructor.
	 *
	 * @param Document $dom  DOM.
	 * @param array    $args Args.
	 */
	public function __construct( $dom, $args = [] ) {
		parent::__construct( $dom, $args );

		if ( ! empty( $this->args['embed_handlers'] ) ) {
			$this->embed_handlers = $this->args['embed_handlers'];
		}
	}

	/**
	 * Allow each embed handler sanitize their respective raw embeds.
	 */
	public function sanitize() {

		foreach ( $this->embed_handlers as $embed_handler ) {
			$embed_handler->sanitize_raw_embeds( $this->dom );
		}
	}
}

<?php
/**
 * Class AMP_Gallery_Embed_Handler
 *
 * @package AMP
 */

use AmpProject\AmpWP\Embed\HandlesGalleryEmbed;
use AmpProject\Dom\Document;
use AmpProject\Tag;

/**
 * Class AMP_Gallery_Embed_Handler
 *
 * @since 0.2
 * @internal
 */
class AMP_Gallery_Embed_Handler extends AMP_Base_Embed_Handler {

	use HandlesGalleryEmbed;

	/**
	 * Register embed.
	 */
	public function register_embed() {
		add_filter( 'post_gallery', [ $this, 'generate_gallery_markup' ], 10, 2 );
		add_action( 'wp_print_styles', [ $this, 'print_styles' ] );
	}

	/**
	 * Override the output of gallery_shortcode().
	 *
	 * @param string $html  Markup to filter.
	 * @param array  $attrs Shortcode attributes.
	 * @return string Markup for the gallery.
	 */
	public function generate_gallery_markup( $html, $attrs ) {
		static $recursing = false;
		if ( ! $recursing ) {
			$recursing = true;
			$html      = $this->filter_post_gallery_markup( $html, $attrs );
			$recursing = false;
		}
		return $html;
	}

	/**
	 * Filter the output of gallery_shortcode().
	 *
	 * @param string $html  Markup to filter.
	 * @param array  $attrs Shortcode attributes.
	 * @return string Markup for the gallery.
	 */
	protected function filter_post_gallery_markup( $html, $attrs ) {
		// Use <amp-carousel> for the gallery if requested via amp-carousel shortcode attribute, or use by default if in legacy Reader mode.
		// In AMP_Gallery_Block_Sanitizer, this is referred to as carousel_required.
		$is_carousel = isset( $attrs['amp-carousel'] )
			? rest_sanitize_boolean( $attrs['amp-carousel'] )
			: amp_is_legacy();

		$is_lightbox = isset( $attrs['amp-lightbox'] ) && rest_sanitize_boolean( $attrs['amp-lightbox'] );

		if ( ! $is_carousel && ! $is_lightbox ) {
			return $html;
		}

		if ( $is_carousel ) {
			$gallery_size = isset( $attrs['size'] ) ? $attrs['size'] : null;

			if ( $gallery_size && 'thumbnail' === $gallery_size ) {
				/*
				 * If the 'gallery' shortcode has a `size` attribute of `thumbnail`, prevent outputting an <amp-carousel>.
				 * That will often get thumbnail images around 150 x 150,
				 * while the <amp-carousel> usually has a width of 600 and a height of 480.
				 * That often means very low-resolution images.
				 * So fall back to the normal 'gallery' shortcode callback, gallery_shortcode().
				 */
				return $html;
			}

			if ( ! $gallery_size ) {
				// Default to `large` if no `size` attribute is specified.
				$attrs['size'] = 'large';
			}
		}

		if ( $is_lightbox ) {
			// Prevent wrapping the images in anchor tags if a lightbox is specified. If that is done the link will get
			// preference over the lightbox when the image is clicked.
			$attrs['link'] = 'none';
		}

		// Use `data` attributes to indicate which options are configured for the embed. These indications are later
		// processed during sanitization of the embed in `::sanitize_raw_embeds`.
		$filter_gallery_style = static function ( $style ) use ( $is_carousel, $is_lightbox ) {
			$data_attrs = [];

			if ( $is_lightbox ) {
				$data_attrs[] = 'data-amp-lightbox="true"';
			}

			if ( $is_carousel ) {
				$data_attrs[] = 'data-amp-carousel="true"';
			}

			return preg_replace(
				'/(?<=<div\b)/',
				' ' . implode( ' ', $data_attrs ),
				$style,
				1
			);
		};

		add_filter( 'gallery_style', $filter_gallery_style );
		$gallery_html = gallery_shortcode( $attrs );
		remove_filter( 'gallery_style', $filter_gallery_style );

		return $gallery_html;
	}

	/**
	 * Unregister embed.
	 */
	public function unregister_embed() {
		remove_filter( 'post_gallery', [ $this, 'generate_gallery_markup' ] );
		remove_action( 'wp_print_styles', [ $this, 'print_styles' ] );
	}

	/**
	 * Sanitizes gallery raw embeds to become an amp-carousel and/or amp-image-lightbox, depending on configuration options.
	 *
	 * @param Document $dom DOM.
	 */
	public function sanitize_raw_embeds( Document $dom ) {
		$nodes = $dom->xpath->query( '//div[ contains( concat( " ", normalize-space( @class ), " " ), " gallery " ) and ( @data-amp-carousel or @data-amp-lightbox ) ]' );

		/** @var DOMElement $node */
		foreach ( $nodes as $node ) {
			$is_carousel  = $node->hasAttribute( 'data-amp-carousel' ) && rest_sanitize_boolean( $node->getAttribute( 'data-amp-carousel' ) );
			$is_lightbox  = $node->hasAttribute( 'data-amp-lightbox' ) && rest_sanitize_boolean( $node->getAttribute( 'data-amp-lightbox' ) );
			$img_elements = $node->getElementsByTagName( 'img' );

			$this->process_gallery_embed( $is_carousel, $is_lightbox, $node, $img_elements );
		}
	}

	/**
	 * Get the caption element for the specified image element.
	 *
	 * @param DOMElement $img_element Image element.
	 * @return DOMElement|null The caption element, otherwise `null` if it could not be found.
	 */
	protected function get_caption_element( DOMElement $img_element ) {
		$parent_element = $this->get_parent_container_for_image( $img_element );

		if ( ! $parent_element instanceof DOMElement ) {
			return null;
		}

		// The caption should be next immediate element located next to the parent container of the image.
		$caption_element = $parent_element->nextSibling;

		while (
			$caption_element
			&& ( ! $caption_element instanceof DOMElement || ! AMP_DOM_Utils::has_class( $caption_element, 'wp-caption-text' ) )
		) {
			$caption_element = $caption_element->nextSibling;
		}

		if ( $caption_element instanceof DOMElement && Tag::FIGCAPTION !== $caption_element->nodeName ) {
			// Transform the caption element into a `figcaption`. This not only allows the `amp-lightbox` to correctly
			// detect and display the caption, but it is also semantically correct as the parent element will be a `figure`.
			$figcaption_element = AMP_DOM_Utils::create_node( Document::fromNode( $caption_element ), Tag::FIGCAPTION, [] );

			while ( $caption_element->firstChild ) {
				$figcaption_element->appendChild( $caption_element->firstChild );
			}

			$caption_element = $figcaption_element;
		}

		return $caption_element instanceof DOMElement ? $caption_element : null;
	}

	/**
	 * Get the parent container for the specified image element.
	 *
	 * @param DOMElement $image_element Image element.
	 * @return DOMElement|null The parent container, otherwise `null` if it could not be found.
	 */
	protected function get_parent_container_for_image( DOMElement $image_element ) {
		$parent_element = $image_element->parentNode;

		while (
			$parent_element
			&& ( ! $parent_element instanceof DOMElement || ! AMP_DOM_Utils::has_class( $parent_element, 'gallery-icon' ) )
		) {
			$parent_element = $parent_element->parentNode;
		}

		return $parent_element;
	}

	/**
	 * Prints the Gallery block styling.
	 *
	 * It would be better to print this in AMP_Gallery_Block_Sanitizer,
	 * but by the time that runs, it's too late.
	 * This rule is copied exactly from block-library/style.css, but the selector here has amp-img >.
	 * The image sanitizer normally converts the <img> from that original stylesheet <amp-img>,
	 * but that doesn't have the same effect as applying it to the <img>.
	 */
	public function print_styles() {
		if ( amp_is_bento_enabled() ) {
			return;
		}
		?>
		<style>
			.wp-block-gallery.is-cropped .blocks-gallery-item amp-img > img {
				object-fit: cover;
			}
		</style>
		<?php
	}
}

<?php
/**
 * Class AMP_Gallery_Embed_Handler
 *
 * @package AMP
 */

use AmpProject\AmpWP\Embed\Registerable;
use AmpProject\Dom\Document;
use AmpProject\AmpWP\Dom\ElementList;
use AmpProject\AmpWP\Component\Carousel;

/**
 * Class AMP_Gallery_Embed_Handler
 *
 * @since 0.2
 */
class AMP_Gallery_Embed_Handler extends AMP_Base_Embed_Handler implements Registerable {

	/**
	 * Register embed.
	 */
	public function register_embed() {
		add_filter( 'post_gallery', [ $this, 'maybe_override_gallery' ], 10, 2 );
		add_action( 'wp_print_styles', [ $this, 'print_styles' ] );
	}

	/**
	 * Unregister embed.
	 */
	public function unregister_embed() {}

	/**
	 * Shortcode handler.
	 *
	 * @param array $attr Shortcode attributes.
	 * @return string Rendered gallery.
	 */
	public function shortcode( $attr ) {
		$post = get_post();

		if ( ! empty( $attr['ids'] ) ) {
			// 'ids' is explicitly ordered, unless you specify otherwise.
			if ( empty( $attr['orderby'] ) ) {
				$attr['orderby'] = 'post__in';
			}
			$attr['include'] = $attr['ids'];
		}

		$atts = shortcode_atts(
			[
				'order'   => 'ASC',
				'orderby' => 'menu_order ID',
				'id'      => $post ? $post->ID : 0,
				'include' => '',
				'exclude' => '',
				'size'    => [ $this->args['width'], $this->args['height'] ],
				'link'    => 'none',
			],
			$attr,
			'gallery'
		);

		if ( ! empty( $attr['amp-lightbox'] ) ) {
			$atts['lightbox'] = filter_var( $attr['amp-lightbox'], FILTER_VALIDATE_BOOLEAN );
		}

		$id = (int) $atts['id'];

		if ( ! empty( $atts['include'] ) ) {
			$attachments = get_posts(
				[
					'include'        => $atts['include'],
					'post_status'    => 'inherit',
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'order'          => $atts['order'],
					'orderby'        => $atts['orderby'],
					'fields'         => 'ids',
				]
			);
		} elseif ( ! empty( $atts['exclude'] ) ) {
			$attachments = get_children(
				[
					'post_parent'    => $id,
					'exclude'        => $atts['exclude'],
					'post_status'    => 'inherit',
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'order'          => $atts['order'],
					'orderby'        => $atts['orderby'],
					'fields'         => 'ids',
				]
			);
		} else {
			$attachments = get_children(
				[
					'post_parent'    => $id,
					'post_status'    => 'inherit',
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'order'          => $atts['order'],
					'orderby'        => $atts['orderby'],
					'fields'         => 'ids',
				]
			);
		}

		if ( empty( $attachments ) ) {
			return '';
		}

		$urls = [];
		foreach ( $attachments as $attachment_id ) {
			list( $url, $width, $height ) = wp_get_attachment_image_src( $attachment_id, $atts['size'], true );

			if ( ! $url ) {
				continue;
			}

			$href = null;
			if ( empty( $atts['lightbox'] ) && ! empty( $atts['link'] ) ) {
				if ( 'file' === $atts['link'] ) {
					$href = $url;
				} elseif ( 'post' === $atts['link'] ) {
					$href = get_attachment_link( $attachment_id );
				}
			}

			$urls[] = [
				'href'   => $href,
				'url'    => $url,
				'srcset' => wp_get_attachment_image_srcset( $attachment_id, $atts['size'] ),
				'width'  => $width,
				'height' => $height,
				'alt'    => trim( wp_strip_all_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ), // Logic from wp_get_attachment_image().
				'id'     => $attachment_id,
			];
		}

		$args = [
			'images'   => $urls,
			'lightbox' => ! empty( $atts['lightbox'] ),
		];

		return $this->render( $args );
	}

	/**
	 * Override the output of gallery_shortcode() if amp-carousel is not false.
	 *
	 * The 'Gallery' widget also uses this function.
	 * This ensures that it outputs an <amp-carousel>.
	 *
	 * @param string $html Markup to filter, possibly ''.
	 * @param array  $attributes Shortcode attributes.
	 * @return string $html Markup for the gallery.
	 */
	public function maybe_override_gallery( $html, $attributes ) {
		$is_lightbox = isset( $attributes['amp-lightbox'] ) && true === filter_var( $attributes['amp-lightbox'], FILTER_VALIDATE_BOOLEAN );
		if ( isset( $attributes['amp-carousel'] ) && false === filter_var( $attributes['amp-carousel'], FILTER_VALIDATE_BOOLEAN ) ) {
			if ( true === $is_lightbox ) {
				$add_lightbox_attribute = static function ( $attr ) {
					$attr['lightbox'] = '';
					return $attr;
				};

				$set_link_attribute = static function ( $attributes ) {
					$attributes['link'] = 'none';
					return $attributes;
				};

				remove_filter( 'post_gallery', [ $this, 'maybe_override_gallery' ], 10 );
				add_filter( 'wp_get_attachment_image_attributes', $add_lightbox_attribute );
				add_filter( 'shortcode_atts_gallery', $set_link_attribute, PHP_INT_MAX );

				$html = gallery_shortcode( $attributes );

				remove_filter( 'wp_get_attachment_image_attributes', $add_lightbox_attribute );
				add_filter( 'post_gallery', [ $this, 'maybe_override_gallery' ], 10, 2 );
				remove_filter( 'shortcode_atts_gallery', $set_link_attribute, PHP_INT_MAX );
			}

			return $html;
		}

		if ( isset( $attributes['size'] ) && 'thumbnail' === $attributes['size'] ) {
			/*
			 * If the 'gallery' shortcode has a 'size' attribute of 'thumbnail', prevent outputting an <amp-carousel>.
			 * That will often get thumbnail images around 150 x 150,
			 * while the <amp-carousel> usually has a width of 600 and a height of 480.
			 * That often means very low-resolution images.
			 * So fall back to the normal 'gallery' shortcode callback, gallery_shortcode().
			 */
			return '';
		}

		return $this->shortcode( $attributes );
	}

	/**
	 * Render.
	 *
	 * @param array $args Args.
	 * @return string Rendered.
	 */
	public function render( $args ) {
		$dom                        = new Document();
		$this->did_convert_elements = true;

		$args = wp_parse_args(
			$args,
			[
				'images' => false,
			]
		);

		if ( empty( $args['images'] ) ) {
			return '';
		}

		$images = new ElementList();
		foreach ( $args['images'] as $props ) {
			$image_attrs = [
				'src'    => $props['url'],
				'width'  => $props['width'],
				'height' => $props['height'],
				'layout' => 'responsive',
				'alt'    => $props['alt'],
			];
			if ( ! empty( $props['srcset'] ) ) {
				$image_attrs['srcset'] = $props['srcset'];
			}

			if ( ! empty( $args['lightbox'] ) ) {
				$image_attrs['lightbox'] = '';
			}
			$image = AMP_DOM_Utils::create_node(
				$dom,
				'img',
				$image_attrs
			);

			if ( ! empty( $props['href'] ) ) {
				$previous_image = $image;
				$image          = AMP_DOM_Utils::create_node(
					$dom,
					'a',
					[
						'href' => $props['href'],
					]
				);
				$image->appendChild( $previous_image );
			}

			$caption = isset( $props['id'] ) ? wp_get_attachment_caption( $props['id'] ) : '';
			$images  = $images->add( $image, $caption );
		}

		$amp_carousel  = new Carousel( $dom, $images );
		$carousel_node = $amp_carousel->get_dom_element();

		// Prevent an error in get_content_from_dom_node() when it calls $node->parentNode->insertBefore().
		$dom->appendChild( $carousel_node );

		return $dom->saveHTML( $carousel_node );
	}

	/**
	 * Prints the Gallery block styling.
	 *
	 * It would be better to print this in AMP_Gallery_Block_Sanitizer,
	 * but by the time that runs, it's too late.
	 * This rule is copied exactly from block-library/style.css, but the selector here has amp-img >.
	 * The image sanitizer normally converts the <img> from that original stylesheet <amp-img>,
	 * but that doesn't have the same effect as applying it to the <img>.
	 *
	 * @return void
	 */
	public function print_styles() {
		?>
		<style>
			.wp-block-gallery.is-cropped .blocks-gallery-item amp-img > img {
				object-fit: cover;
			}
		</style>
		<?php
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
}

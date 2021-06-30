<?php
/**
 * Class AMP_YouTube_Embed_Handler
 *
 * @package AMP
 */

use AmpProject\Dom\Document;
use AmpProject\Dom\Element;

/**
 * Class AMP_YouTube_Embed_Handler
 *
 * Much of this class is borrowed from Jetpack embeds.
 *
 * @internal
 */
class AMP_YouTube_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * URL pattern to match YouTube videos.
	 *
	 * Only handling single videos. Playlists are handled elsewhere.
	 *
	 * @deprecated No longer used.
	 * @internal
	 * @var string
	 */
	const URL_PATTERN = '#https?://(?:www\.)?(?:youtube.com/(?:v/|e/|embed/|watch[/\#?])|youtu\.be/).*#i';

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
	 * Register embed.
	 */
	public function register_embed() {
		add_filter( 'embed_oembed_html', [ $this, 'filter_embed_oembed_html' ], 10, 2 );
		add_filter( 'wp_video_shortcode_override', [ $this, 'video_override' ], 10, 2 );
	}

	/**
	 * Unregister embed.
	 */
	public function unregister_embed() {
		remove_filter( 'embed_oembed_html', [ $this, 'filter_embed_oembed_html' ], 10 );
	}

	/**
	 * Sanitize HTML that are not added via Gutenberg.
	 *
	 * @param Document $dom  Document.
	 *
	 * @return void
	 */
	public function sanitize_raw_embeds( Document $dom ) {

		$nodes = $dom->xpath->query( '//iframe[ contains( @src, "youtu" ) ]' );

		/** @var Element $node */
		foreach ( $nodes as $node ) {

			$html             = $dom->saveHTML( $node );
			$url              = $node->getAttribute( 'src' );
			$amp_youtube_node = $this->process_embed( $dom, $html, $url );

			if ( ! empty( $amp_youtube_node ) ) {
				$node->parentNode->replaceChild( $amp_youtube_node, $node );
			}
		}

	}

	/**
	 * To AMP youtube component from DOM Document.
	 *
	 * @param Document $dom  Document DOM.
	 * @param string   $html HTML markup of youtube iframe.
	 * @param string   $url  Youtube URL.
	 *
	 * @return DOMElement|false DOMElement on success, Otherwise false.
	 */
	public function process_embed( Document $dom, $html, $url ) {

		$id = $this->get_video_id_from_url( $url );

		if ( ! $id ) {
			return false;
		}

		$args = $this->parse_props( $html, $url, $id );
		if ( empty( $args ) ) {
			return false;
		}

		$args['video_id'] = $id;

		$attributes = $this->prepare_attributes( $args, $url );

		if ( empty( $attributes['data-videoid'] ) ) {
			return AMP_DOM_Utils::create_node(
				$dom,
				'a',
				[
					'href'  => esc_url_raw( $url ),
					'class' => 'amp-wp-embed-fallback',
				]
			);
		}

		return AMP_DOM_Utils::create_node(
			$dom,
			'amp-youtube',
			$attributes
		);
	}

	/**
	 * Filter oEmbed HTML for YouTube to convert to AMP.
	 *
	 * @param string $cache Cache for oEmbed.
	 * @param string $url   Embed URL.
	 * @return string Embed.
	 */
	public function filter_embed_oembed_html( $cache, $url ) {
		$id = $this->get_video_id_from_url( $url );
		if ( ! $id ) {
			return $cache;
		}

		$props = $this->parse_props( $cache, $url, $id );
		if ( empty( $props ) ) {
			return $cache;
		}

		$props['video_id'] = $id;
		return $this->render( $props, $url );
	}

	/**
	 * Parse AMP component from iframe.
	 *
	 * @param string $html     HTML.
	 * @param string $url      Embed URL, for fallback purposes.
	 * @param string $video_id YouTube video ID.
	 * @return array|null Props for rendering the component, or null if unable to parse.
	 */
	private function parse_props( $html, $url, $video_id ) {
		$props = $this->match_element_attributes( $html, 'iframe', [ 'title', 'height', 'width' ] );
		if ( ! isset( $props ) ) {
			return null;
		}

		$img_attributes = [
			'src'        => esc_url_raw( sprintf( 'https://i.ytimg.com/vi/%s/hqdefault.jpg', $video_id ) ),
			'layout'     => 'fill',
			'object-fit' => 'cover',
		];
		if ( ! empty( $props['title'] ) ) {
			$img_attributes['alt'] = $props['title'];
		}
		$img = AMP_HTML_Utils::build_tag( 'img', $img_attributes );

		$props['placeholder'] = AMP_HTML_Utils::build_tag(
			'a',
			[
				'placeholder' => '',
				'href'        => esc_url_raw( $url ),
			],
			$img
		);

		// Find start time of video.
		$start_time = $this->get_start_time_from_url( $url );
		if ( ! empty( $start_time ) && 0 < intval( $start_time ) ) {
			$props['start'] = intval( $start_time );
		}

		return $props;
	}

	/**
	 * Prepare attributes for amp-youtube component.
	 *
	 * @param array  $args amp-youtube component arguments.
	 * @param string $url  Youtube URL.
	 *
	 * @return array prepared arguments for amp-youtube component.
	 */
	public function prepare_attributes( $args, $url ) {
		$args = wp_parse_args(
			$args,
			[
				'video_id'    => false,
				'layout'      => 'responsive',
				'width'       => $this->args['width'],
				'height'      => $this->args['height'],
				'placeholder' => '',
				'start'       => 0,
			]
		);

		$attributes = array_merge(
			[ 'data-videoid' => $args['video_id'] ],
			wp_array_slice_assoc( $args, [ 'layout', 'width', 'height' ] )
		);
		if ( ! empty( $args['title'] ) ) {
			$attributes['title'] = $args['title'];
		}

		$allowed_data_params = [
			'cc_lang_pref',
			'cc_load_policy',
			'color',
			'controls',
			'disablekb',
			'enablejsapi',
			'end',
			'fs',
			'hl',
			'iv_load_policy',
			'list',
			'listType',
			'modestbranding',
			'origin',
			'playlist',
			'playsinline',
			'rel',
			'widget_referrer',
		];

		$query_vars  = [];
		$query_param = wp_parse_url( $url, PHP_URL_QUERY );
		wp_parse_str( $query_param, $query_vars );

		foreach ( $allowed_data_params as $allowed_data_param ) {
			if ( isset( $query_vars[ $allowed_data_param ] ) ) {
				$attributes[ "data-param-$allowed_data_param" ] = $query_vars[ $allowed_data_param ];
			}
		}

		foreach ( [ 'autoplay', 'loop' ] as $param ) {
			if ( isset( $query_vars[ $param ] ) ) {
				$attributes[ $param ] = $query_vars[ $param ];
			}
		}

		if ( ! empty( $args['start'] ) && 0 < (int) $args['start'] ) {
			$attributes['data-param-start'] = (int) $args['start'];
		}

		return $attributes;
	}

	/**
	 * Render embed.
	 *
	 * @param array  $args Args.
	 * @param string $url  URL.
	 * @return string Rendered.
	 */
	public function render( $args, $url ) {

		$attributes = $this->prepare_attributes( $args, $url );

		if ( empty( $attributes['data-videoid'] ) ) {
			return AMP_HTML_Utils::build_tag(
				'a',
				[
					'href'  => esc_url_raw( $url ),
					'class' => 'amp-wp-embed-fallback',
				],
				esc_html( $url )
			);
		}

		$this->did_convert_elements = true;

		$args['placeholder'] = ( ! empty( $args['placeholder'] ) ) ? $args['placeholder'] : '';

		return AMP_HTML_Utils::build_tag( 'amp-youtube', $attributes, $args['placeholder'] );
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
		if ( ! in_array( $domain, [ 'youtu.be', 'youtube.com', 'youtube-nocookie.com' ], true ) ) {
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
			} elseif ( isset( $query_vars['vi'] ) ) {
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

	/**
	 * To get start time of youtube video in second.
	 *
	 * @param string $url Youtube URL.
	 *
	 * @return int Start time in second.
	 */
	private function get_start_time_from_url( $url ) {

		if ( empty( $url ) ) {
			return 0;
		}

		$start_time = 0;
		$parsed_url = wp_parse_url( $url );

		if ( ! empty( $parsed_url['query'] ) ) {
			wp_parse_str( $parsed_url['query'], $query_vars );

			if ( ! empty( $query_vars['start'] ) && 0 < (int) $query_vars['start'] ) {
				$start_time = (int) $query_vars['start'];
			}
		}

		if ( empty( $start_time ) && ! empty( $parsed_url['fragment'] ) ) {
			$regex = '/^t=(?<minutes>[0-9])+m(?<seconds>[0-9]+)s$/iU';

			preg_match( $regex, $parsed_url['fragment'], $matches );

			if ( ! empty( $matches ) ) {
				$matches    = wp_parse_args(
					$matches,
					[
						'minutes' => 0,
						'seconds' => 0,
					]
				);
				$start_time = ( (int) $matches['seconds'] + ( (int) $matches['minutes'] * 60 ) );
			}
		}

		return $start_time;
	}

	/**
	 * Override the output of YouTube videos.
	 *
	 * This overrides the value in wp_video_shortcode().
	 * The pattern matching is copied from WP_Widget_Media_Video::render().
	 *
	 * @param string $html Empty variable to be replaced with shortcode markup.
	 * @param array  $attr The shortcode attributes.
	 * @return string|null $markup The markup to output.
	 */
	public function video_override( $html, $attr ) {
		if ( ! isset( $attr['src'] ) ) {
			return $html;
		}
		$video_id = $this->get_video_id_from_url( $attr['src'] );
		if ( ! $video_id ) {
			return $html;
		}

		return $this->render( compact( 'video_id' ), $attr['src'] );
	}
}

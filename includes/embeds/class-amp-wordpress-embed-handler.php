<?php
/**
 * Class AMP_WordPress_Embed_Handler
 *
 * @package AMP
 */

/**
 * Class AMP_WordPress_Embed_Handler
 *
 * @since 1.3.1
 */
class AMP_WordPress_Embed_Handler extends AMP_Base_Embed_Handler {

	/**
	 * Default height.
	 *
	 * Note that 200px is the minimum that WordPress allows for a post embed. This minimum height is enforced by
	 * WordPress in the wp.receiveEmbedMessage() function, and the <amp-wordpress-embed> also enforces that same
	 * minimum height. It is important for the minimum height to be initially used because if the actual post embed
	 * window is _less_ than the initial, then no overflow button will be presented to resize the iframe to be
	 * _smaller_. So this ensures that the iframe will only ever overflow to grow in height.
	 *
	 * @var int
	 */
	protected $DEFAULT_HEIGHT = 200;

	/**
	 * Register embed.
	 */
	public function register_embed() {
		add_filter( 'embed_oembed_html', [ $this, 'filter_embed_oembed_html' ], 100, 2 );
	}

	/**
	 * Unregister embed.
	 */
	public function unregister_embed() {
		remove_filter( 'embed_oembed_html', [ $this, 'filter_embed_oembed_html' ], 100 );
	}

	/**
	 * Filter oEmbed HTML for WordPress to convert to AMP.
	 *
	 * @param string $cache Cache for oEmbed.
	 * @param string $url   Embed URL.
	 * @return string Embed.
	 */
	public function filter_embed_oembed_html( $cache, $url ) {
		/*
		 * Example WordPress embed HTML that would be present in $cache.
		 *
		 * <blockquote class="wp-embedded-content" data-secret="xUuZHketRt">
		 *     <a href="https://make.wordpress.org/core/2015/10/28/new-embeds-feature-in-wordpress-4-4/">New Embeds Feature in WordPress 4.4</a>
		 * </blockquote>
		 * <iframe
		 *      title="&#8220;New Embeds Feature in WordPress 4.4&#8221; &#8212; Make WordPress Core"
		 *      class="wp-embedded-content"
		 *      sandbox="allow-scripts"
		 *      security="restricted"
		 *      style="position: absolute; clip: rect(1px, 1px, 1px, 1px);"
		 *      src="https://make.wordpress.org/core/2015/10/28/new-embeds-feature-in-wordpress-4-4/embed/#?secret=xUuZHketRt"
		 *      data-secret="xUuZHketRt"
		 *      width="600"
		 *      height="338"
		 *      frameborder="0"
		 *      marginwidth="0"
		 *      marginheight="0"
		 *      scrolling="no">
		 * </iframe>
		 */
		if ( ! preg_match( '#<blockquote class="wp-embedded-content" data-secret="\w+">(.+?)</blockquote>#s', $cache, $matches ) ) {
			return $cache;
		}
		$placeholder = sprintf( '<blockquote class="wp-embedded-content" placeholder>%s</blockquote>', $matches[1] );

		$attributes = [
			'height' => $this->args['height'],
			'title'  => '',
		];
		if ( preg_match( '#<iframe[^>]*?title="(?P<title>[^"]+?)"#s', $cache, $matches ) ) {
			$attributes['title'] = $matches['title'];
		}

		return sprintf(
			'<amp-wordpress-embed layout="fixed-height" height="%d" title="%s" data-url="%s">%s<button overflow>%s</button></amp-wordpress-embed>',
			esc_attr( $attributes['height'] ),
			esc_attr( $attributes['title'] ),
			esc_url( $url ),
			$placeholder,
			esc_html__( 'Expand', 'amp' )
		);
	}
}

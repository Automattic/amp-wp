<?php
/**
 * Class AMP_Facebook_Embed_Test
 *
 * @package AMP
 */

use AmpProject\AmpWP\Tests\MarkupComparison;
use AmpProject\AmpWP\Tests\WithoutBlockPreRendering;

/**
 * Test AMP_Facebook_Embed_Handler_Test
 *
 * @covers AMP_Facebook_Embed_Handler
 */
class AMP_Facebook_Embed_Handler_Test extends WP_UnitTestCase {

	use MarkupComparison;
	use WithoutBlockPreRendering {
		setUp as public prevent_block_pre_render;
	}

	/**
	 * Set up.
	 */
	public function setUp() {
		$this->prevent_block_pre_render();

		// Mock the HTTP request.
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
	}

	/**
	 * Mock HTTP request.
	 *
	 * @param mixed  $preempt Whether to preempt an HTTP request's return value. Default false.
	 * @param mixed  $r       HTTP request arguments.
	 * @param string $url     The request URL.
	 * @return array Response data.
	 */
	public function mock_http_request( $preempt, $r, $url ) {
		if ( in_array( 'external-http', $_SERVER['argv'], true ) ) {
			return $preempt;
		}

		if ( false !== strpos( $url, '10102593740125791' ) ) {
			$body = '{"author_name":"Mark","author_url":"https://www.facebook.com/zuck","provider_url":"https://www.facebook.com","provider_name":"Facebook","success":true,"height":null,"html":"\u003Cdiv id=\"fb-root\">\u003C/div>\n\u003Cscript async=\"1\" defer=\"1\" crossorigin=\"anonymous\" src=\"https://connect.facebook.net/en_US/sdk.js#xfbml=1&amp;version=v6.0\">\u003C/script>\u003Cdiv class=\"fb-post\" data-href=\"https://www.facebook.com/zuck/posts/10102593740125791\" data-width=\"500\">\u003Cblockquote cite=\"https://www.facebook.com/zuck/posts/10102593740125791\" class=\"fb-xfbml-parse-ignore\">\u003Cp>February 4 is Facebook\u2019s 12th birthday!\n\nOur anniversary has a lot of meaning to me as an opportunity to reflect on how...\u003C/p>Posted by \u003Ca href=\"https://www.facebook.com/zuck\">Mark Zuckerberg\u003C/a> on&nbsp;\u003Ca href=\"https://www.facebook.com/zuck/posts/10102593740125791\">Tuesday, January 12, 2016\u003C/a>\u003C/blockquote>\u003C/div>","type":"rich","version":"1.0","url":"https://www.facebook.com/zuck/posts/10102593740125791","width":500}';
		} elseif ( false !== strpos( $url, '10151176218703920' ) ) {
			$body = '{"author_name":"Facebook Engineering","author_url":"https://www.facebook.com/Engineering/","provider_url":"https://www.facebook.com","provider_name":"Facebook","success":true,"height":null,"html":"\u003Cdiv id=\"fb-root\">\u003C/div>\n\u003Cscript async=\"1\" defer=\"1\" crossorigin=\"anonymous\" src=\"https://connect.facebook.net/en_US/sdk.js#xfbml=1&amp;version=v6.0\">\u003C/script>\u003Cdiv class=\"fb-post\" data-href=\"https://www.facebook.com/notes/facebook-engineering/under-the-hood-the-javascript-sdk-truly-asynchronous-loading/10151176218703920/\" data-width=\"500\">\u003Cblockquote cite=\"https://www.facebook.com/notes/facebook-engineering/under-the-hood-the-javascript-sdk-truly-asynchronous-loading/10151176218703920/\" class=\"fb-xfbml-parse-ignore\">Posted by \u003Ca href=\"https://www.facebook.com/Engineering/\">Facebook Engineering\u003C/a> on&nbsp;\u003Ca href=\"https://www.facebook.com/notes/facebook-engineering/under-the-hood-the-javascript-sdk-truly-asynchronous-loading/10151176218703920/\">Saturday, December 8, 2012\u003C/a>\u003C/blockquote>\u003C/div>","type":"rich","version":"1.0","url":"https://www.facebook.com/notes/facebook-engineering/under-the-hood-the-javascript-sdk-truly-asynchronous-loading/10151176218703920/","width":500}';
		} elseif ( false !== strpos( $url, '10102533316889441' ) ) {
			$body = '{"author_name":"Mark","author_url":"https://www.facebook.com/zuck","provider_url":"https://www.facebook.com","provider_name":"Facebook","success":true,"height":null,"html":"\u003Cdiv id=\"fb-root\">\u003C/div>\n\u003Cscript async=\"1\" defer=\"1\" crossorigin=\"anonymous\" src=\"https://connect.facebook.net/en_US/sdk.js#xfbml=1&amp;version=v6.0\">\u003C/script>\u003Cdiv class=\"fb-post\" data-href=\"https://www.facebook.com/photo.php?fbid=10102533316889441&amp;set=a.529237706231.2034669.4&amp;type=3&amp;theater\" data-width=\"500\">\u003Cblockquote cite=\"https://www.facebook.com/photo.php?fbid=10102533316889441&amp;set=a.529237706231&amp;type=3\" class=\"fb-xfbml-parse-ignore\">\u003Cp>Meanwhile, Beast turned to the dark side.\u003C/p>Posted by \u003Ca href=\"https://www.facebook.com/zuck\">Mark Zuckerberg\u003C/a> on&nbsp;\u003Ca href=\"https://www.facebook.com/photo.php?fbid=10102533316889441&amp;set=a.529237706231&amp;type=3\">Friday, December 18, 2015\u003C/a>\u003C/blockquote>\u003C/div>","type":"rich","version":"1.0","url":"https://www.facebook.com/photo.php?fbid=10102533316889441&set=a.529237706231.2034669.4&type=3&theater","width":500}';
		} elseif ( false !== strpos( $url, '10102509264909801' ) ) {
			$body = '{"author_name":"Mark","author_url":"https://www.facebook.com/zuck","provider_url":"https://www.facebook.com","provider_name":"Facebook","success":true,"height":280,"html":"\u003Cdiv id=\"fb-root\">\u003C/div>\n\u003Cscript async=\"1\" defer=\"1\" crossorigin=\"anonymous\" src=\"https://connect.facebook.net/en_US/sdk.js#xfbml=1&amp;version=v6.0\">\u003C/script>\u003Cdiv class=\"fb-video\" data-href=\"https://www.facebook.com/zuck/videos/10102509264909801/\" data-width=\"500\">\u003Cblockquote cite=\"https://www.facebook.com/zuck/videos/10102509264909801/\" class=\"fb-xfbml-parse-ignore\">\u003Ca href=\"https://www.facebook.com/zuck/videos/10102509264909801/\">\u003C/a>\u003Cp>I want to share a few more thoughts on the Chan Zuckerberg Initiative before I just start posting photos of me and Max for a while :)\n\nI hope one idea comes through: that we as a society should make investments now to ensure this world is much better for the next generation.\n\nI don&#039;t think we do enough of this right now. \n\nSure, there are many areas where investment now will solve problems for today and also improve the world for the future. We do muster the will to solve some of those.\n\nBut for the problems that will truly take decades of investment before we see any major return, we are dramatically underinvested.\n\nOne example is basic science research to cure disease. Another is developing clean energy to protect the world for the future. Another is the slow and steady improvement to modernize schools. Others are systematic issues around poverty and justice. There is a long list of these opportunities.\n\nThe role of philanthropy is to invest in important areas that companies and governments aren&#039;t funding -- either because they may not be profitable for companies or because they are too long term for people to want to invest now.\n\nIn the case of disease, basic research often needs to be funded before biotech or pharma companies can create drugs to help people. If we invest more in science, we can make faster progress towards curing disease.\n\nOur investment in the Chan Zuckerberg Initiative is small compared to what the world can invest in solving these great challenges. My hope is that our work inspires more people to invest in these longer term issues. If we can do that, then we can all really make a difference together.\u003C/p>Posted by \u003Ca href=\"https://www.facebook.com/zuck\">Mark Zuckerberg\u003C/a> on Friday, December 4, 2015\u003C/blockquote>\u003C/div>","type":"video","version":"1.0","url":"https://www.facebook.com/zuck/videos/10102509264909801/","width":500}';
		} elseif ( false !== strpos( $url, '10154009990506729' ) ) {
			$body = '{"author_name":"Facebook App","author_url":"https://www.facebook.com/facebookapp/","provider_url":"https://www.facebook.com","provider_name":"Facebook","success":true,"height":null,"html":"\u003Cdiv id=\"fb-root\">\u003C/div>\n\u003Cscript async=\"1\" defer=\"1\" crossorigin=\"anonymous\" src=\"https://connect.facebook.net/en_US/sdk.js#xfbml=1&amp;version=v6.0\">\u003C/script>\u003Cdiv class=\"fb-post\" data-href=\"https://www.facebook.com/20531316728/posts/10154009990506729/\" data-width=\"552\">\u003Cblockquote cite=\"https://www.facebook.com/20531316728/posts/10154009990506729/\" class=\"fb-xfbml-parse-ignore\">Posted by \u003Ca href=\"https://www.facebook.com/facebookapp/\">Facebook App\u003C/a> on&nbsp;\u003Ca href=\"https://www.facebook.com/20531316728/posts/10154009990506729/\">Thursday, August 27, 2015\u003C/a>\u003C/blockquote>\u003C/div>","type":"rich","version":"1.0","url":"https://www.facebook.com/20531316728/posts/10154009990506729/","width":552}';
		} else {
			return $preempt;
		}

		return [
			'body'          => $body,
			'headers'       => [],
			'response'      => [
				'code'    => 200,
				'message' => 'ok',
			],
			'cookies'       => [],
			'http_response' => null,
		];
	}

	/**
	 * Get scripts data.
	 *
	 * @return array Scripts.
	 */
	public function get_scripts_data() {
		return [
			'not_converted' => [
				'<p>Hello World.</p>',
				[],
			],
			'converted'     => [
				'https://www.facebook.com/zuck/posts/10102593740125791' . PHP_EOL,
				[ 'amp-facebook' => true ],
			],
		];
	}

	/**
	 * Test get_scripts().
	 *
	 * @dataProvider get_scripts_data
	 * @param string $source Source.
	 * @param array  $expected Expected scripts.
	 */
	public function test__get_scripts( $source, $expected ) {
		$embed = new AMP_Facebook_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$validating_sanitizer = new AMP_Tag_And_Attribute_Sanitizer( $dom );
		$validating_sanitizer->sanitize();

		$scripts = array_merge(
			$embed->get_scripts(),
			$validating_sanitizer->get_scripts()
		);

		$this->assertEquals( $expected, $scripts );
	}

	/**
	 * Data for test__raw_embed_sanitizer.
	 *
	 * @return array
	 */
	public function get_raw_embed_dataset() {
		return [
			'no_embed_blockquote'           => [
				'<p>Hello world.</p>',
				'<p>Hello world.</p>',
			],

			'div_without_facebook_embed'    => [
				'<div>lorem ipsum</div>',
				'<div>lorem ipsum</div>',
			],

			'simple_url_https'              => [
				'https://www.facebook.com/zuck/posts/10102593740125791' . PHP_EOL,
				'
					<amp-facebook width="500" height="400" data-href="https://www.facebook.com/zuck/posts/10102593740125791" data-embed-as="post" layout="responsive">
						<blockquote cite="https://www.facebook.com/zuck/posts/10102593740125791" class="fb-xfbml-parse-ignore" fallback=""><!--blockquote_contents--></blockquote>
					</amp-facebook>
				' . PHP_EOL,
			],

			'notes_url'                     => [
				'https://www.facebook.com/notes/facebook-engineering/under-the-hood-the-javascript-sdk-truly-asynchronous-loading/10151176218703920/' . PHP_EOL,
				'
					<amp-facebook width="500" height="400" data-href="https://www.facebook.com/notes/facebook-engineering/under-the-hood-the-javascript-sdk-truly-asynchronous-loading/10151176218703920/" data-embed-as="post" layout="responsive">
						<blockquote cite="https://www.facebook.com/notes/facebook-engineering/under-the-hood-the-javascript-sdk-truly-asynchronous-loading/10151176218703920/" class="fb-xfbml-parse-ignore" fallback=""><!--blockquote_contents--></blockquote>
					</amp-facebook>
				' . PHP_EOL,
			],

			'photo_url'                     => [
				'https://www.facebook.com/photo.php?fbid=10102533316889441&set=a.529237706231.2034669.4&type=3&theater' . PHP_EOL,
				'
					<amp-facebook width="500" height="400" data-href="https://www.facebook.com/photo.php?fbid=10102533316889441&amp;set=a.529237706231.2034669.4&amp;type=3&amp;theater" data-embed-as="post" layout="responsive">
						<blockquote cite="https://www.facebook.com/photo.php?fbid=10102533316889441&amp;set=a.529237706231&amp;type=3" class="fb-xfbml-parse-ignore" fallback=""><!--blockquote_contents--></blockquote>
					</amp-facebook>
				' . PHP_EOL,
			],

			'video_url'                     => [
				'https://www.facebook.com/zuck/videos/10102509264909801/' . PHP_EOL,
				'
					<amp-facebook width="500" height="400" data-href="https://www.facebook.com/zuck/videos/10102509264909801/" data-embed-as="video" layout="responsive">
						<blockquote cite="https://www.facebook.com/zuck/videos/10102509264909801/" class="fb-xfbml-parse-ignore" fallback=""><!--blockquote_contents--></blockquote>
					</amp-facebook>
				' . PHP_EOL,
			],

			'post_embed'                    => [
				'<div class="fb-post" data-href="https://www.facebook.com/notes/facebook-engineering/under-the-hood-the-javascript-sdk-truly-asynchronous-loading/10151176218703920/"></div>',
				'<amp-facebook width="600" height="400" data-href="https://www.facebook.com/notes/facebook-engineering/under-the-hood-the-javascript-sdk-truly-asynchronous-loading/10151176218703920/" data-embed-as="post" layout="responsive"></amp-facebook>',
			],

			'post_with_fallbacks'           => [
				'
					<div class="fb-post" data-href="https://www.facebook.com/20531316728/posts/10154009990506729/" data-width="500" data-show-text="true">
						<blockquote cite="https://developers.facebook.com/20531316728/posts/10154009990506729/" class="fb-xfbml-parse-ignore"><!--blockquote_contents--></blockquote>
					</div>
				',
				'
					<amp-facebook width="500" height="400" data-href="https://www.facebook.com/20531316728/posts/10154009990506729/"  data-show-text="true" data-embed-as="post" layout="responsive">
						<blockquote cite="https://developers.facebook.com/20531316728/posts/10154009990506729/" class="fb-xfbml-parse-ignore" fallback=""><!--blockquote_contents--></blockquote>
					</amp-facebook>
				',
			],

			'video_embed'                   => [
				'<div class="fb-video" data-href="https://www.facebook.com/amanda.orr.56/videos/10212156330049017/" data-show-text="false"></div>',
				'<amp-facebook width="600" height="400" data-href="https://www.facebook.com/amanda.orr.56/videos/10212156330049017/" data-show-text="false" data-embed-as="video" layout="responsive"></amp-facebook>',
			],

			'page_embed'                    => [
				'
					<div class="fb-page" data-href="https://www.facebook.com/xwp.co/" data-width="340" data-height="432" data-hide-cover="true" data-show-facepile="true" data-show-posts="false">
						<div class="fb-xfbml-parse-ignore">
							<blockquote cite="https://www.facebook.com/xwp.co/"><!--blockquote_contents--></blockquote>
						</div>
					</div>
				',
				'
					<amp-facebook-page width="340" height="432" data-href="https://www.facebook.com/xwp.co/" data-hide-cover="true" data-show-facepile="true" data-show-posts="false" layout="responsive">
						<div class="fb-xfbml-parse-ignore" fallback="">
							<blockquote cite="https://www.facebook.com/xwp.co/"><!--blockquote_contents--></blockquote>
						</div>
					</amp-facebook-page>
				',
			],

			'like'                          => [
				'
					<div class="fb-like" data-href="https://developers.facebook.com/docs/plugins/" data-width="400" data-layout="standard" data-action="like" data-size="small" data-show-faces="true" data-share="true"></div>
				',
				'
					<amp-facebook-like width="400" height="400" data-href="https://developers.facebook.com/docs/plugins/" data-layout="standard" data-action="like" data-size="small" data-show-faces="true" data-share="true" layout="responsive">
					</amp-facebook-like>
				',
			],

			'comments'                      => [
				'
					<div class="fb-comments" data-href="https://developers.facebook.com/docs/plugins/comments#configurator" data-numposts="5"></div>
				',
				'<amp-facebook-comments width="600" height="400" data-href="https://developers.facebook.com/docs/plugins/comments#configurator" data-numposts="5" layout="responsive"></amp-facebook-comments>',
			],

			'comments_full_width'           => [
				'
					<div class="fb-comments" data-href="https://developers.facebook.com/docs/plugins/comments#configurator" data-width="100%" data-numposts="5"></div>
				',
				'<amp-facebook-comments width="auto" height="400" data-href="https://developers.facebook.com/docs/plugins/comments#configurator" data-numposts="5" layout="fixed-height"></amp-facebook-comments>',
			],

			'comments_full_width_2'         => [
				'
					<div class="fb-comments" data-href="https://developers.facebook.com/docs/plugins/comments#configurator" data-height="123" data-width="100%" data-numposts="5"></div>
				',
				'<amp-facebook-comments width="auto" height="123" data-href="https://developers.facebook.com/docs/plugins/comments#configurator" data-numposts="5" layout="fixed-height"></amp-facebook-comments>',
			],

			'comment_embed'                 => [
				'
					<div class="fb-comment-embed" data-href="https://www.facebook.com/zuck/posts/10102735452532991?comment_id=1070233703036185" data-width="500"></div>
				',
				'<amp-facebook width="500" height="400" data-href="https://www.facebook.com/zuck/posts/10102735452532991?comment_id=1070233703036185" data-embed-as="comment" layout="responsive"></amp-facebook>',
			],

			'remove_fb_root'                => [
				'<div id="fb-root"></div>' . str_repeat( '<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.2"></script>', 5 ), // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
				'',
			],

			'remove_multiple_fb_root'       => [
				str_repeat( '<div id="fb-root"></div>', 5 ) . str_repeat( '<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.2"></script>', 5 ), // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
				'',
			],

			'remove_empty_p_tag'            => [
				'<p><script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.2"></script></p><div id="fb-root"></div>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
				'',
			],

			'keep_p_tag_if_it_has_children' => [
				'<p><span id="foo-bar"></span><script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.2"></script></p><div id="fb-root"></div>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
				'<p><span id="foo-bar"></span></p>',
			],
		];
	}

	/**
	 * Test raw_embed_sanitizer.
	 *
	 * @param string $source  Content.
	 * @param string $expected Expected content.
	 * @dataProvider get_raw_embed_dataset
	 * @covers AMP_Facebook_Embed_Handler::sanitize_raw_embeds()
	 */
	public function test__raw_embed_sanitizer( $source, $expected ) {
		$embed = new AMP_Facebook_Embed_Handler();

		$filtered_content = apply_filters( 'the_content', $source );
		$dom              = AMP_DOM_Utils::get_dom_from_content( $filtered_content );
		$embed->sanitize_raw_embeds( $dom );

		$layout_sanitizer = new AMP_Layout_Sanitizer( $dom );
		$layout_sanitizer->sanitize();

		$content = AMP_DOM_Utils::get_content_from_dom( $dom );

		// Normalize blockquote contents to account for editing of published posts or variability of localized datetime strings.
		$content = preg_replace( '#(<blockquote.*?>).+?(</blockquote>)#s', '$1<!--blockquote_contents-->$2', $content );

		$this->assertEqualMarkup( $expected, $content );
	}
}

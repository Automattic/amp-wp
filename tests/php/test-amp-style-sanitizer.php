<?php
/**
 * Test AMP_Style_Sanitizer.
 *
 * @package AMP
 */

// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned

use Amp\AmpWP\Dom\Document;
use Amp\AmpWP\Tests\PrivateAccess;

/**
 * Test AMP_Style_Sanitizer.
 */
class AMP_Style_Sanitizer_Test extends WP_UnitTestCase {

	use PrivateAccess;

	/**
	 * Set up.
	 */
	public function setUp() {
		parent::setUp();
		global $wp_styles, $wp_scripts;
		$wp_styles  = null;
		$wp_scripts = null;
		delete_option( AMP_Options_Manager::OPTION_NAME ); // Make sure default reader mode option does not override theme support being added.
	}

	/**
	 * Tear down.
	 */
	public function tearDown() {
		parent::tearDown();
		global $wp_styles, $wp_scripts;
		$wp_styles  = null;
		$wp_scripts = null;
	}

	/**
	 * Get data for tests.
	 *
	 * @return array
	 */
	public function get_body_style_attribute_data() {
		return [
			'empty' => [
				'',
				'',
				[],
			],

			'span_one_style' => [
				'<span style="color: #00ff00;">This is green.</span>',
				'<span data-amp-original-style="color: #00ff00;" class="amp-wp-bb01159">This is green.</span>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-bb01159{color:#0f0}',
				],
			],

			'span_one_style_bad_format' => [
				'<span style="color  :   #00ff00">This is green.</span>',
				'<span data-amp-original-style="color  :   #00ff00" class="amp-wp-0837823">This is green.</span>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-0837823{color:#0f0}',
				],
			],

			'span_two_styles_reversed' => [
				'<span style="color: #00ff00; background-color: #000; ">This is green.</span>',
				'<span data-amp-original-style="color: #00ff00; background-color: #000; " class="amp-wp-c71affe">This is green.</span>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-c71affe{color:#0f0;background-color:#000}',
				],
			],

			'span_display_none' => [
				'<span style="display: none;">Kses-banned properties are allowed since Kses will have already applied if user does not have unfiltered_html.</span>',
				'<span data-amp-original-style="display: none;" class="amp-wp-224b51a">Kses-banned properties are allowed since Kses will have already applied if user does not have unfiltered_html.</span>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-224b51a{display:none}',
				],
			],

			'!important_is_ok' => [
				'<span style="padding:1px; margin: 2px !important; outline: 3px;">!important is converted.</span>',
				'<span data-amp-original-style="padding:1px; margin: 2px !important; outline: 3px;" class="amp-wp-6a75598">!important is converted.</span>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-6a75598{padding:1px;outline:3px}:root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-6a75598{margin:2px}',
				],
			],

			'!important_with_spaces_also_converted' => [
				'<span style="color: red  !  important;">!important is converted.</span>',
				'<span data-amp-original-style="color: red  !  important;" class="amp-wp-952600b">!important is converted.</span>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-952600b{color:red}',
				],
			],

			'!important_multiple_is_converted' => [
				'<span style="color: red !important; background: blue!important;">!important is converted.</span>',
				'<span data-amp-original-style="color: red !important; background: blue!important;" class="amp-wp-1e2bfaa">!important is converted.</span>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-1e2bfaa{color:red;background:blue}',
				],
			],

			'!important_takes_precedence_over_inline' => [
				'<header id="header" style="display: none;"><h1>This is the header.</h1></header><style>#header { display: block !important;width: 100%;background: #fff; }',
				'<header id="header" data-amp-original-style="display: none;" class="amp-wp-224b51a"><h1>This is the header.</h1></header>',
				[
					'#header{width:100%;background:#fff}:root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) #header{display:block}',
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-224b51a{display:none}',
				],
			],

			'two_nodes' => [
				'<span style="color: #00ff00;"><span style="color: #ff0000;">This is red.</span></span>',
				'<span data-amp-original-style="color: #00ff00;" class="amp-wp-bb01159"><span data-amp-original-style="color: #ff0000;" class="amp-wp-cc68ddc">This is red.</span></span>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-bb01159{color:#0f0}',
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-cc68ddc{color:#f00}',
				],
			],

			'existing_class_attribute' => [
				'<figure class="alignleft" style="background: #000"></figure>',
				'<figure class="alignleft amp-wp-2864855" data-amp-original-style="background: #000"></figure>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-2864855{background:#000}',
				],
			],

			'inline_style_element_with_multiple_rules_containing_selectors_is_removed' => [
				'<style>div > span { font-weight:bold !important; font-style: italic; } @media screen and ( max-width: 640px ) { div > span { font-weight:normal !important; font-style: normal; } }</style><div><span>bold!</span></div>',
				'<div><span>bold!</span></div>',
				[
					'div > span{font-style:italic}:root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) div > span{font-weight:bold}@media screen and ( max-width: 640px ){div > span{font-style:normal}:root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) div > span{font-weight:normal}}',
				],
			],

			'illegal_unsafe_properties' => [
				'<style>button { behavior: url(hilite.htc) /* IE only */; font-weight:bold; -moz-binding: url(http://www.example.org/xbl/htmlBindings.xml#checkbox); /*XBL*/ }</style><style> @media screen { button { behavior: url(hilite.htc) /* IE only */; font-weight:bold; -moz-binding: url(http://www.example.org/xbl/htmlBindings.xml#checkbox); /*XBL*/ } }</style><button>Click</button>',
				'<button>Click</button>',
				[
					'button{font-weight:bold}',
					'@media screen{button{font-weight:bold}}',
				],
				array_fill( 0, 4, AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_PROPERTY_NOLIST ),
			],

			'illegal_at_rule_in_style_attribute' => [
				'<span style="color:brown; @media screen { color:green }">invalid @-rule omitted.</span>',
				'<span data-amp-original-style="color:brown; @media screen { color:green }" class="amp-wp-481af57">invalid @-rule omitted.</span>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-481af57{color:brown}',
				],
				[],
			],

			'illegal_at_rules_removed' => [
				'<style>@charset "utf-8"; @namespace svg url(http://www.w3.org/2000/svg); @page { margin: 1cm; } @viewport { width: device-width; } @counter-style thumbs { system: cyclic; symbols: "\1F44D"; suffix: " "; } body { color: black; }</style>',
				'',
				[
					'@page{margin:1cm}body{color:black}',
				],
				[ AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_AT_RULE, AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_AT_RULE, AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_AT_RULE ],
			],

			'allowed_at_rules_retained' => [
				'<style>@media screen and ( max-width: 640px ) { body { font-size: small; } } @font-face { font-family: "Open Sans"; src: url("/fonts/OpenSans-Regular-webfont.woff2") format("woff2"); } @supports (display: grid) { div { display: grid; } } @-moz-keyframes appear { from { opacity: 0.0; } to { opacity: 1.0; } }</style><div></div>',
				'<div></div>',
				[
					'@media screen and ( max-width: 640px ){body{font-size:small}}@font-face{font-family:"Open Sans";src:url("/fonts/OpenSans-Regular-webfont.woff2") format("woff2")}@supports (display: grid){div{display:grid}}@-moz-keyframes appear{from{opacity:0}to{opacity:1}}',
				],
			],

			'keyframes_eligible_for_style_amp_keyframes_removed' => [
				'<style>@keyframes offset { 0% { offset-distance: 0% } 50% { offset-distance: 50% } 100% { offset-distance: 100% } }</style><div></div>',
				'<div></div>',
				[ '' ],
			],

			'keyframes_not_eligible_for_style_amp_keyframes_not_removed' => [
				'<style>@keyframes border { from { border-bottom: 0 } to { border-bottom: 20px } }</style><div></div>',
				'<div></div>',
				[
					'@keyframes border{from{border-bottom:0}to{border-bottom:20px}}',
				],
			],

			'selector_specificity' => [
				'<style>#child {color:red !important} #parent #child {color:pink !important} .foo { color:blue !important; } #me .foo { color: green !important; }</style><div id="parent"><span id="child" class="foo bar baz">one</span><span style="color: yellow;">two</span><span style="color: purple !important;">three</span></div><div id="me"><span class="foo"></span></div>',
				'<div id="parent"><span id="child" class="foo bar baz">one</span><span data-amp-original-style="color: yellow;" class="amp-wp-64b4fd4">two</span><span data-amp-original-style="color: purple !important;" class="amp-wp-ab79d9e">three</span></div><div id="me"><span class="foo"></span></div>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) #child{color:red}:root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) #parent #child{color:pink}:root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) .foo{color:blue}:root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) #me .foo{color:green}',
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-64b4fd4{color:yellow}',
					':root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-ab79d9e{color:purple}',
				],
			],

			'grid_lines'                                  => [
				'<style>.wrapper {display: grid;grid-template-columns: [main-start] 1fr [content-start] 1fr [content-end] 1fr [main-end];grid-template-rows: [main-start] 100px [content-start] 100px [content-end] 100px [main-end];}</style><div class="wrapper"></div>',
				'<div class="wrapper"></div>',
				[
					'.wrapper{display:grid;grid-template-columns:[main-start] 1fr [content-start] 1fr [content-end] 1fr [main-end];grid-template-rows:[main-start] 100px [content-start] 100px [content-end] 100px [main-end]}',
				],
			],

			'col_with_width_attribute' => [
				'<table><colgroup><col width="253"/></colgroup></table>',
				'<table><colgroup><col data-amp-original-style="width: 253px" class="amp-wp-cbcb5c2"></colgroup></table>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-cbcb5c2{width:253px}',
				],
			],

			'col_with_percent_width_attribute' => [
				'<table><colgroup><col width="50%"/></colgroup></table>',
				'<table><colgroup><col data-amp-original-style="width: 50%" class="amp-wp-cd7753e"></colgroup></table>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-cd7753e{width:50%}',
				],
			],

			'col_with_star_width_attribute' => [
				'<table><colgroup><col width="0*"/></colgroup></table>',
				'<table><colgroup><col width="0*"></colgroup></table>',
				[],
			],

			'col_with_width_attribute_and_existing_style' => [
				'<table><colgroup><col width="50" style="background-color: red; width: 60px"/></colgroup></table>',
				'<table><colgroup><col data-amp-original-style="width: 50px;background-color: red; width: 60px" class="amp-wp-c8aa9e9"></colgroup></table>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-c8aa9e9{width:50px;width:60px;background-color:red}',
				],
			],

			'multi_selector_in_not_pseudo_class'         => [
				'<style>.widget:not(.widget_text,.jetpack_widget_social_icons[title="a,b"]) ul { color:red; }</style><div class="widget"><ul></ul></div>',
				'<div class="widget"><ul></ul></div>',
				[
					'.widget:not(.widget_text,.jetpack_widget_social_icons[title="a,b"]) ul{color:red}',
				],
			],

			'selector_with_escaped_char_class_name'      => [
				'<style>.lg\:w-full { width: 100%; }</style><div class="bg-black w-16 lg:w-full hover:bg-blue"></div>',
				'<div class="bg-black w-16 lg:w-full hover:bg-blue"></div>',
				[
					'.lg\:w-full{width:100%}',
				],
			],
			'semicolon_outside_of_rule_in_media_query'      => [
				'<style>@media (max-width: 450px) { .sidebar { padding: 0; }; } .sidebar { margin: 0 auto; }</style><div class="sidebar"></div>',
				'<div class="sidebar"></div>',
				[
					'@media (max-width: 450px){.sidebar{padding:0}}.sidebar{margin:0 auto}',
				],
			],
		];
	}

	/**
	 * Test sanitizer for style attributes that appear in the body.
	 *
	 * @dataProvider get_body_style_attribute_data
	 * @param string $source               Source.
	 * @param string $expected_content     Expected content.
	 * @param string $expected_stylesheets Expected stylesheets.
	 * @param array  $expected_errors      Expected error codes.
	 */
	public function test_body_style_attribute_sanitizer( $source, $expected_content, $expected_stylesheets, $expected_errors = [] ) {
		$dom = AMP_DOM_Utils::get_dom_from_content( $source );

		$error_codes = [];
		$args        = [
			'validation_error_callback' => static function( $error ) use ( &$error_codes ) {
				$error_codes[] = $error['code'];
			},
		];

		$sanitizer = new AMP_Style_Sanitizer( $dom, $args );
		$sanitizer->sanitize();

		// Test content.
		$content = AMP_DOM_Utils::get_content_from_dom( $dom );
		$content = preg_replace( '/(?<=>)\s+(?=<)/', '', $content );
		$this->assertEquals( $expected_content, $content );

		// Test stylesheet.
		$this->assertEquals( $expected_stylesheets, array_values( $sanitizer->get_stylesheets() ) );

		$this->assertEquals( $expected_errors, $error_codes );
	}

	/**
	 * Get link and style test data.
	 *
	 * @return array
	 */
	public function get_link_and_style_test_data() {
		return [
			'multiple_amp_custom_and_other_styles' => [
				'<html amp><head><meta charset="utf-8"><style amp-custom>b {color:red !important}</style><style amp-custom>i {color:blue}</style><style type="text/css">u {color:green; text-decoration: underline !important}</style></head><body><style>s {color:yellow} /* So !important! */</style><b>1</b><i>i</i><u>u</u><s>s</s></body></html>',
				[
					':root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) b{color:red}',
					'i{color:blue}',
					'u{color:green}:root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) u{text-decoration:underline}',
					's{color:yellow}',
				],
				[],
			],
			'style_elements_with_link_elements' => [
				sprintf(
					'<html amp><head><meta charset="utf-8"><style type="text/css">strong.before-dashicon {color:green}</style><link rel="stylesheet" href="%s"><style type="text/css">strong.after-dashicon {color:green}</style></head><body><style>s {color:yellow !important}</style><s class="before-dashicon"></s><strong class="dashicons-dashboard"></strong><strong class="after-dashicon"></strong></body></html>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
					includes_url( 'css/dashicons.css' )
				),
				[
					'strong.before-dashicon',
					'.dashicons-dashboard:before',
					'strong.after-dashicon',
					':root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) s{color:yellow}',
				],
				[],
			],
			'style_with_no_head' => [
				'<html amp><body>Not good!<style>body{color:red}</style></body></html>',
				[
					'body{color:red}',
				],
				[],
			],
			'style_with_not_selectors' => [
				'<html amp><head><meta charset="utf-8"><style amp-custom>body.bar > p:not(.baz) { color:red; } body.foo:not(.bar) > p { color:blue; } body.foo:not(.bar) p:not(.baz) { color:green; } body.foo p { color:yellow; }</style></head><body class="foo"><p>Hello</p></body></html>',
				[
					'body.foo:not(.bar) > p{color:blue}body.foo:not(.bar) p:not(.baz){color:green}body.foo p{color:yellow}',
				],
				[],
			],
			'style_with_attribute_selectors' => [
				'<html amp><head><meta charset="utf-8"><style amp-custom>.social-navigation a[href*="example.com"] { color:red; } .social-navigation a.examplecom { color:blue; }</style></head><body class="foo"><nav class="social-navigation"><a href="https://example.com/">Example</a></nav></body></html>',
				[
					'.social-navigation a[href*="example.com"]{color:red}',
				],
				[],
			],
			'style_on_root_element' => [
				'<html amp style="color:red;"><head><meta charset="utf-8"><style amp-custom>html { background-color: blue !important; }</style></head><body>Hi</body></html>',
				[
					'html:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_){background-color:blue}',
					':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-10b06ba{color:red}',
				],
				[],
			],
			'styles_with_dynamic_elements' => [
				implode(
					'',
					[
						'<html amp><head><meta charset="utf-8">',
						'<style amp-custom>b.foo, form [submit-success] b, div[submit-failure] b, form.unused b { color: green }</style>',
						'<style amp-custom>.dead-list li .highlighted, amp-live-list li .highlighted { background: yellow }</style>',
						'<style amp-custom>article.missing amp-live-list li .highlighted { background: yellow }</style>',
						'<style amp-custom>body amp-list .portland { color:blue; }</style>',
						'<style amp-custom>amp-script .loaded { color:brown; }</style>',
						'</head><body>',
						'<form method="post" action-xhr="https://example.com/subscribe" target="_top"><div submit-success><template type="amp-mustache"><b>Thanks</b>, {{name}}}</template></div></form>',
						'<amp-live-list id="my-live-list" data-poll-interval="15000" data-max-items-per-page="20"><button update on="tap:my-live-list.update">You have updates!</button><ul items><li id="live-list-2-item-2" data-sort-time="1464281932879">Hello</li></ul></amp-live-list>',
						'<amp-list width="auto" height="100" layout="fixed-height" src="https://ampproject-b5f4c.firebaseapp.com/examples/data/amp-list-urls.json"> <template type="amp-mustache"> <div class="url-entry"> <a href="{{url}}" class="{{class}}">{{title}}</a> </div> </template> </amp-list>',
						'<amp-script src="https://example.com/populate/server/time">The current server time is <time class="loading">loading...</time>.</amp-script>',
						'</body></html>',
					]
				),
				[
					'form [submit-success] b{color:green}', // The [submit-failure] selector is removed because there is no div[submit-failure].
					'amp-live-list li .highlighted{background:yellow}',
					'body amp-list .portland{color:blue}',
					'amp-script .loaded{color:brown}',
				],
				[],
			],
			'styles_with_calc_functions' => [
				implode(
					'',
					[
						'<html amp><head>',
						'<style amp-custom>body { color: red; width: calc( 1px + calc( 2vh / 3 ) - 2px * 5 ); outline: solid 1px blue; }</style>',
						'<style amp-custom>.alignwide{ max-width: -webkit-calc(50% + 22.5rem); border: solid 1px red; }</style>',
						'<style amp-custom>.alignwide{ height: calc(10% + ( 1px ); color: red; content: ")"}</style>', // Test unbalanced parentheses.
						'</head><body><div class="alignwide"></div></body></html>',
					]
				),
				[
					'body{color:red;width:calc(1px + calc(2vh / 3) - 2px * 5);outline:solid 1px blue}',
					'.alignwide{max-width:-webkit-calc(50% + 22.5rem);border:solid 1px red}',
					'.alignwide{color:red;content:")"}',
				],
				[],
			],
			'style_with_media_element' => [
				'<html amp><head><meta charset="utf-8"><style media="print">.print { display:none; }</style></head><body><button class="print" on="tap:AMP.print()"></button></body></html>',
				[
					'@media print{.print{display:none}}',
				],
				[],
			],
			'selectors_with_ie_hacks_removed' => [
				'<html amp><head><meta charset="utf-8"><style>* html span { color:red; background: blue !important; } span { text-decoration:underline; } *+html span { border: solid green; }</style></head><body><span>Test</span></body></html>',
				[
					'span{text-decoration:underline}',
				],
				[],
			],
			'unamerican_lang_attribute_selectors_removed' => [ // USA is used for convenience here. No political statement intended.
				'<html lang="en-US" amp><head><meta charset="utf-8"><style>html[lang=en-US] {color:red} html[lang="en-US"] {color:white} html[lang^=en] {color:blue} html[lang="en-CA"] {color:red}  html[lang^=ar] { color:green; } html[lang="es-MX"] { color:green; }</style></head><body><span>Test</span></body></html>',
				[
					'html[lang=en-US]{color:red}html[lang="en-US"]{color:white}html[lang^=en]{color:blue}',
				],
				[],
			],
			'unamerican_lang_selector_selectors_removed' => [ // USA is used for convenience here. No political statement intended.
				'
					<html amp><head><meta charset="utf-8">
					<style>
						html:lang(en-US) { color:red; }
						body span:lang(en-US) { color:red; }
						html:lang(en) {color:white; }
						.test:lang(en-us, en-CA) {color:white; }
						html span.test:lang(en-US) { color: blue;}
						html:lang("en-US") span.test { color: blue;}
						html:lang(en-CA) {color:red; }
						html:lang(ar) { color:green; }
						html:lang(es-MX) { color:green; }
						</style>
					</head><body><span class="test">Test</span></body></html>
				',
				[
					'html:lang(en-US){color:red}body span:lang(en-US){color:red}html:lang(en){color:white}.test:lang(en-us, en-CA){color:white}html span.test:lang(en-US){color:blue}html:lang("en-US") span.test{color:blue}',
				],
				[],
			],
			'external_link_without_css_file_extension' => [
				'<html amp><head><meta charset="utf-8"><link rel="stylesheet" href="https://example.com/_static/??-eJx9kN1SAyEMhV9Iip3aOl44Pgs"></head><body><span>externally-styled</span></body></html>', // phpcs:ignore
				[
					'span:before{content:"Returned from: https://example.com/_static/??-eJx9kN1SAyEMhV9Iip3aOl44Pgs"}',
				],
				[],
			],
			'charset_ruleset_removed_without_warning'  => [
				'<html amp><body><style>@charset "utf-8"; body { color:limegreen; }</style></body></html>',
				[
					'body{color:limegreen}',
				],
				[],
			],
			'dynamic_classes_preserved_always' => [
				'
					<html amp><head>
					<style> .amp-viewer { color: blue; } </style>
					<style> .amp-mode-touch { color: blanchedalmond; } </style>
					<style> .amp-mode-mouse { color: bisque; } </style>
					<style> .amp-mode-keyboard-active { color: burlywood; } </style>
					<style> .amp-referrer-www-google-com { color: red; } </style>
					<style> .amp-active { color: green } </style>
					<style> .amp-carousel-slide { outline: solid 1px red; } </style>
					<style> .amp-date-picker-selecting { outline: solid 2px red; } </style>
					<style> .amp-form-submit-success { color: green; } </style>
					<style> .amp-access-laterpay-container { color: purple} </style>
					<style> .amp-image-lightbox-caption { color: brown} </style>
					<style> .amp-live-list-item-new { color: lime} </style>
					<style> .amp-sidebar-toolbar-target-hidden { color: lavender} </style>
					<style> .amp-sticky-ad-close-button { color: aliceblue} </style>
					<style> .amp-docked-video-shadow { color: azure} </style>
					<style> .amp-geo-pending { color: saddlebrown; } </style>
					<style> .amp-geo-no-group { color: ghostwhite; } </style>
					<style> .amp-geo-group-foo { color: peru; } </style>
					<style> .amp-iso-country-us { color: oldlace; } </style>
					<style> .non-existent { color: black; } </style>
					<style> .amp-video-eq { display: none; } </style>
					</head><body><p>Hello!</p></body></html>
				',
				[
					'.amp-viewer{color:blue}',
					'.amp-mode-touch{color:blanchedalmond}',
					'.amp-mode-mouse{color:bisque}',
					'.amp-mode-keyboard-active{color:burlywood}',
					'.amp-referrer-www-google-com{color:red}',
				],
				[],
			],
			'dynamic_classes_and_attributes_preserved_conditionally' => [
				'
					<html amp><head>
					<style> .amp-viewer { color: blue; } </style>
					<style> .amp-referrer-www-google-com { color: red; } </style>
					<style> .amp-active { color: green } </style>
					<style> .amp-carousel-slide { outline: solid 1px red; } </style>
					<style> .amp-date-picker-selecting { outline: solid 2px red; } </style>
					<style> .amp-form-submit-success { color: green; } </style>
					<style> .amp-access-laterpay-container { color: purple} </style>
					<style> .amp-image-lightbox-caption { color: brown} </style>
					<style> .amp-live-list-item-new { color: lime} #my-live-list [data-tombstone] { display: block; }</style>
					<style> .amp-sidebar-toolbar-target-hidden { color: lavender} #sidebar1[open] { outline: solid 1px red; }</style>
					<style> .amp-sticky-ad-close-button { color: aliceblue} </style>
					<style> .amp-docked-video-shadow { color: azure} </style>
					<style> .amp-geo-pending { color: saddlebrown; } </style>
					<style> .amp-geo-no-group { color: ghostwhite; } </style>
					<style> .amp-geo-group-foo { color: peru; } </style>
					<style> .amp-iso-country-us { color: oldlace; } </style>
					<style> .amp-video-eq { display: none; } </style>
					<style> #accord section[expanded] { outline: solid 1px blue; } </style>
					<style> .non-existent { color: black; } </style>
					</head>
					<body>
						<amp-user-notification  layout="nodisplay"  id="amp-user-notification1"  data-show-if-href="https://foo.com/api/show-api?timestamp=TIMESTAMP"  data-dismiss-href="https://foo.com/api/dismissed">  This  site  uses  cookies  to  personalize  content.  <a  href="">Learn  more.</a>  <button  on="tap:amp-user-notification1.dismiss">I  accept</button>  </amp-user-notification>
						<amp-carousel type="slides" width="450" height="300" controls loop autoplay delay="3000" data-next-button-aria-label="Go to next slide" data-previous-button-aria-label="Go to previous slide"> <amp-img src="images/image1.jpg" width="450" height="300"></amp-img> <amp-img src="images/image2.jpg" width="450" height="300"></amp-img> <amp-img src="images/image3.jpg" width="450" height="300"></amp-img></amp-carousel>
						<amp-date-picker layout="fixed-height" height="360"></amp-date-picker>
						<form action="https://example.com/" target="_top" method="get"><input name="search" type="search" required></form>
						<section amp-access="NOT error AND NOT access" amp-access-hide><div id="amp-access-laterpay-dialog" class="amp-access-laterpay"></div></section>
						<amp-image-lightbox id="lightbox1" layout="nodisplay"></amp-image-lightbox>
						<amp-live-list id="my-live-list" data-poll-interval="15000" data-max-items-per-page="20"> <div update class="outer-container"> <div class="inner-container"> <button class="btn" on="tap:my-live-list.update">Click me!</button> </div> </div> <div items></div> </amp-live-list>
						<amp-sidebar id="sidebar1" layout="nodisplay" side="right"><nav toolbar="(max-width: 767px)" toolbar-target="target-element"><ul><li></li></ul></nav></amp-sidebar>
						<amp-sticky-ad layout="nodisplay"><amp-ad width="320" height="50" type="doubleclick" data-slot="/35096353/amptesting/formats/sticky"></amp-ad></amp-sticky-ad>
						<amp-video dock width="720" height="305" layout="responsive" src="https://yourhost.com/videos/myvideo.mp4" poster="https://yourhost.com/posters/poster.png" artwork="https://yourhost.com/artworks/artwork.png" title="Awesome video" artist="Awesome artist" album="Amazing album"></amp-video>
						<amp-geo layout="nodisplay"><script type="application/json">{"ISOCountryGroups": {"foo":["us"]}}</script></amp-geo>
						<amp-accordion id="accord" disable-session-states><section><h2>Section 1</h2><p>Content in section 1.</p></section><section><h2>Section 2</h2><div>Content in section 2.</div></section></amp-accordion>
					</body>
					</html>
				',
				[
					'.amp-viewer{color:blue}',
					'.amp-referrer-www-google-com{color:red}',
					'.amp-active{color:green}',
					'.amp-carousel-slide{outline:solid 1px red}',
					'.amp-date-picker-selecting{outline:solid 2px red}',
					'.amp-form-submit-success{color:green}',
					'.amp-access-laterpay-container{color:purple}',
					'.amp-image-lightbox-caption{color:brown}',
					'.amp-live-list-item-new{color:lime}#my-live-list [data-tombstone]{display:block}',
					'.amp-sidebar-toolbar-target-hidden{color:lavender}#sidebar1[open]{outline:solid 1px red}',
					'.amp-sticky-ad-close-button{color:aliceblue}',
					'.amp-docked-video-shadow{color:azure}',
					'.amp-geo-pending{color:saddlebrown}',
					'.amp-geo-no-group{color:ghostwhite}',
					'.amp-geo-group-foo{color:peru}',
					'.amp-iso-country-us{color:oldlace}',
					'.amp-video-eq{display:none}',
					'#accord section[expanded]{outline:solid 1px blue}',
				],
				[],
			],
			'test_with_dev_mode' => [
				'<html amp data-ampdevmode=""><body data-ampdevmode="" style="background:red !important"><link rel="stylesheet" href="https://example.com/foo.css" data-ampdevmode=""><style data-ampdevmode="">body{color:red !important}</style></body></html>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				[],
				[],
			],
		];
	}

	/**
	 * Test style elements and link elements.
	 *
	 * @dataProvider get_link_and_style_test_data
	 * @param string $source               Source.
	 * @param array  $expected_stylesheets Expected stylesheets.
	 * @param array  $expected_errors      Expected error codes.
	 */
	public function test_link_and_style_elements( $source, $expected_stylesheets, $expected_errors = [] ) {
		add_filter(
			'locale',
			static function() {
				return 'en_US';
			}
		);
		add_filter(
			'pre_http_request',
			static function( $preempt, $request, $url ) {
				$preempt = [
					'response' => [
						'code' => 200,
					],
					'headers'  => [
						'content-type' => 'text/css',
					],
					'body'     => sprintf( 'span:before { content: "Returned from: %s"; }', $url ),
				];
				return $preempt;
			},
			10,
			3
		);
		$dom = Document::from_html( $source );

		$error_codes = [];
		$args        = [
			'use_document_element'      => true,
			'validation_error_callback' => static function( $error ) use ( &$error_codes ) {
				$error_codes[] = $error['code'];
			},
		];

		$sanitizer = new AMP_Style_Sanitizer( $dom, $args );
		$sanitizer->sanitize();

		$whitelist_sanitizer = new AMP_Tag_And_Attribute_Sanitizer( $dom, $args );
		$whitelist_sanitizer->sanitize();

		$sanitized_html     = $dom->saveHTML( $dom->documentElement );
		$actual_stylesheets = array_values( array_filter( $sanitizer->get_stylesheets() ) );
		$this->assertEquals( $expected_errors, $error_codes );
		$this->assertCount( count( $expected_stylesheets ), $actual_stylesheets );
		foreach ( $expected_stylesheets as $i => $expected_stylesheet ) {
			if ( empty( $expected_stylesheet ) ) {
				$this->assertEmpty( $actual_stylesheets[ $i ] );
				continue;
			}

			if ( false === strpos( $expected_stylesheet, '{' ) ) {
				$this->assertContains( $expected_stylesheet, $actual_stylesheets[ $i ] );
			} else {
				$this->assertEquals( $expected_stylesheet, $actual_stylesheets[ $i ] );
			}
			$this->assertContains( $expected_stylesheet, $sanitized_html );
		}

		if ( $actual_stylesheets ) {
			$this->assertContains( "\n\n/*# sourceURL=amp-custom.css */", $sanitized_html );
		}
	}

	/**
	 * Data for testing AMP selector conversion.
	 *
	 * @return array
	 */
	public function get_amp_selector_data() {
		return [
			'img' => [
				sprintf( '<div><img class="logo" src="%s" width="200" height="100"></div>', admin_url( 'images/wordpress-logo.png' ) ),
				'div img.logo{border:solid 1px red}',
				'div amp-img.logo{border:solid 1px red}', // Note amp-anim is still tree-shaken because it doesn't occur in the DOM.
			],
			'img-missing-class' => [
				sprintf( '<div><img class="logo" src="%s" width="200" height="100"></div>', admin_url( 'images/wordpress-logo.png' ) ),
				'div img.missing{border:solid 1px red}',
				'', // Tree-shaken because missing class doesn't occur in the DOM.
			],
			'img-and-anim' => [
				sprintf( '<div><img class="logo" src="%s" width="200" height="100"><img class="spinner" src="%s" width="200" height="100"></div>', admin_url( 'images/wordpress-logo.png' ), admin_url( 'images/spinner-2x.gif' ) ),
				'div img{border:solid 1px red}',
				'div amp-img,div amp-anim{border:solid 1px red}',
			],
			'amp-img-and-amp-anim' => [
				sprintf( '<amp-img class="logo amp-wp-enforced-sizes" src="%s" width="200" height="100" layout="intrinsic"></amp-img><amp-anim class="spinner amp-wp-enforced-sizes" src="%s" width="200" height="100" layout="intrinsic"></amp-anim>', admin_url( 'images/wordpress-logo.png' ), admin_url( 'images/spinner-2x.gif' ) ),
				'amp-img.amp-wp-enforced-sizes[layout="intrinsic"] > img,amp-anim.amp-wp-enforced-sizes[layout="intrinsic"] > img{object-fit:contain}',
				'amp-img.amp-wp-enforced-sizes[layout="intrinsic"] > img,amp-anim.amp-wp-enforced-sizes[layout="intrinsic"] > img{object-fit:contain}',
			],
			'admin-bar-style-selectors' => [
				'<div id="wpadminbar"><a href="https://example.com/"><amp-img src="https://example.com/foo.png" width="100" height="100"></amp-img><amp-anim src="https://example.com/foo.gif" width="100" height="100"></amp-anim></a></div>',
				'#wpadminbar a, #wpadminbar a:hover, #wpadminbar a img, #wpadminbar a img:hover { border: none; text-decoration: none; background: none;}',
				'#wpadminbar a,#wpadminbar a:hover,#wpadminbar a amp-img,#wpadminbar a amp-anim,#wpadminbar a amp-img:hover,#wpadminbar a amp-anim:hover{border:none;text-decoration:none;background:none}',
			],
			'img_with_amp_img' => [
				'<amp-img></amp-img>',
				'amp-img img{background-color:red}',
				'amp-img img{background-color:red}',
			],
			'img-cover' => [
				sprintf( '<div><amp-img class="logo" src="%s" width="200" height="100"></amp-img></div>', admin_url( 'images/wordpress-logo.png' ) ),
				'div amp-img.logo img{object-fit:cover}',
				'div amp-img.logo img{object-fit:cover}',
			],
			'img-tree-shaking' => [
				sprintf( '<article><img class="logo" src="%s" width="200" height="100"></article>', admin_url( 'images/wordpress-logo.png' ) ),
				'div img.logo{border:solid 1px red}',
				'', // The selector is removed because there is no div element.
			],
			'attribute_selectors' => [
				'<div id="content" tabindex="-1"></div><button type=button>Hello</button><a href="#">Top</a><span></span>',
				'[type="button"], [type="reset"], [type^="submit"] {color:red} a[href^=http]:after, a[href^="#"]:after { color:blue } span[hidden] {display:none}#content[tabindex="-1"]:focus{ outline: solid 1px red; }',
				'[type="button"],[type="reset"],[type^="submit"]{color:red}a[href^=http]:after,a[href^="#"]:after{color:blue}span[hidden]{display:none}#content[tabindex="-1"]:focus{outline:solid 1px red}', // Any selector mentioning [type] or [href] will persist since value is not used for tree shaking.
			],
			'playbuzz' => [
				'<p>hello</p><div class="pb_feed" data-item="226dd4c0-ef13-4fee-850b-7be32bf6d121"></div>',
				'p + div.pb_feed{border:solid 1px blue}',
				'p + amp-playbuzz.pb_feed{border:solid 1px blue}',
			],
			'video' => [
				'<article><video src="http://example.com" height="100" width="200"></video></article>',
				'article>video{border:solid 1px green}',
				'article>amp-video{border:solid 1px green}',
			],
			'form' => [
				sprintf( '<div id="search"><form method="get" action="https://example.com"><label id="s">Search</label><input type="search" name="s" id="s"></form></div>' ),
				'#search form label{display:block}',
				'#search form label{display:block}',
			],
			'video_with_amp_video' => [
				'<amp-video class="video"></amp-video>',
				'amp-video.video video{border:solid 1px green}',
				'amp-video.video video{border:solid 1px green}',
			],
			'iframe' => [
				'<p><b>purple</b><iframe src="http://example.com" height="100" width="200"></iframe></p>',
				'p>*:not(iframe){color:purple}',
				'p>*:not(amp-iframe){color:purple}',
			],
			'audio' => [
				'<audio src="http://example.com/foo.mp3" height="100" width="200"></audio>',
				'audio{border:solid 1px yellow}',
				'amp-audio{border:solid 1px yellow}',
			],
			'keyframes_eligible_for_style_amp_keyframes_removed' => [
				'<div>test</div>',
				'span {color:red;} @keyframes foo { from: { opacity:0; } 50% {opacity:0.5} 75%,80% { opacity:0.6 } to { opacity:1 }  }',
				'',
			],
			'keyframes_not_eligible_for_style_amp_custom_not_removed' => [
				'<div>test</div>',
				'span {color:red;} @keyframes margin { from: { margin-top:0 } 50% { margin-top:10px } 75%,80% { margin-top:16px } to { margin-top:20px } }',
				'@keyframes margin{from:{margin-top:0}50%{margin-top:10px}75%,80%{margin-top:16px}to{margin-top:20px}}',
			],
			'type_class_names' => [
				'<audio src="https://example.org/foo.mp3" width="100" height="100" class="audio iframe video img form">',
				'.video{color:blue;} audio.audio{color:purple;} .iframe{color:black;} .img{color:purple;} .form:not(form){color:green;}',
				'.video{color:blue}amp-audio.audio{color:purple}.iframe{color:black}.img{color:purple}.form:not(form){color:green}',
			],
			'focus_within_classes' => [
				'<nav class="main-navigation focused"><ul><li><a href="https://example.com/">Example</a><ul><li><a href="https://example.org">Another example</a></li></ul></li></ul></nav>',
				'.main-navigation ul ul li:hover > ul, .main-navigation ul ul li.focus > ul { left: 100%; right: auto; } nav.focused { outline:solid 1px red; }',
				'.main-navigation ul ul li:hover > ul,.main-navigation ul ul li:focus-within > ul{left:100%;right:auto}nav.focused{outline:solid 1px red}',
			],
			'style_attribute_selector' => [
				'<figure class="wp-block-pullquote" style="border-color:#ce3a0d">',
				'.wp-block-pullquote:not(.is-style-solid-color)[style*="border-color"] { border: 2px solid; }',
				'.wp-block-pullquote:not(.is-style-solid-color)[data-amp-original-style*="border-color"]{border:2px solid}',
			],
		];
	}

	/**
	 * Test AMP selector conversion.
	 *
	 * @covers AMP_Style_Sanitizer::ampify_ruleset_selectors()
	 *
	 * @dataProvider get_amp_selector_data
	 * @param string $markup Markup.
	 * @param string $input  Input stylesheet.
	 * @param string $output Output stylesheet.
	 */
	public function test_amp_selector_conversion( $markup, $input, $output ) {
		$html = "<html amp><head><meta charset=utf-8><style amp-custom>$input</style></head><body>$markup</body></html>";
		$dom  = Document::from_html( $html );

		$sanitizer_classes = amp_get_content_sanitizers();
		$sanitized         = AMP_Content_Sanitizer::sanitize_document(
			$dom,
			$sanitizer_classes,
			[
				'use_document_element' => true,
			]
		);

		$stylesheets = array_values( $sanitized['stylesheets'] );
		$this->assertEquals( $output, $stylesheets[0] );
	}

	/**
	 * Provide data for attribute selector test.
	 *
	 * @return array Data.
	 */
	public function get_attribute_selector_data() {
		return [
			'type_attribute' => [
				'<input type="color">',
				// All selectors remain because only the existence of the attribute is examined.
				[
					'[type="button"]' => true,
					'[type*="reset"]' => true,
					'[type^="submit"]' => true,
					'[type$="button"]' => true,
				],
			],
			'tabindex_attribute' => [
				'<span tabindex="-1"></span>',
				// The div[tabindex] is removed because there is no div. The span[tabindex^=2] remains because value is not considered.
				[
					'div[tabindex]' => false,
					'span[tabindex]' => true,
					'span[tabindex=-1]' => true,
					'span[tabindex^=2]' => true,
				],
			],
			'href_attribute' => [
				'<a href="foo">Foo</a>',
				[
					'a[href^=http]:after' => true,
					'a[href^="#"]:after' => true,
				],
			],
			'hidden_attribute' => [
				'<span>not hidden</span>',
				// Only div[hidden] should be removed because there is no div element; the other [hidden] selectors remain because it can be dynamically added.
				[
					'span[hidden]' => true,
					'[hidden]' => true,
					'div[hidden]' => false,
					'span:not([hidden])' => true,
				],
			],
			'selected_readonly_disabled_multiple_autofocus_required' => [
				'<input><select><option></option></select>',
				[
					'[autofocus]' => true,
					'[checked]'   => true,
					'[disabled]'  => true,
					'[multiple]'  => true,
					'[readonly]'  => true,
					'[required]'  => true,
					'[selected]'  => true,
				],
			],
			'open_attribute' => [
				'<details><summary>More</summary>Details</details>',
				[
					'[open]'             => true,
					'amp-lightbox[open]' => false,
					'details[open]'      => true,
				],
			],
			'media_attributes' => [
				'<amp-video width="720" height="305" layout="responsive" src="https://yourhost.com/videos/myvideo.mp4" poster="https://yourhost.com/posters/poster.png" artwork="https://yourhost.com/artworks/artwork.png" title="Awesome video" artist="Awesome artist" album="Amazing album"></amp-video>',
				[
					'[loop]'     => true,
					'[controls]' => true,
				],
			],
			'escaped_char_class_name' => [
				'<div class="bg-black w-16 lg:w-full hover:bg-blue @@@"></div>',
				[
					'.lg'               => false,
					'.hover'            => false,
					'.hover\:bg-blue'   => true,
					'.lg\:w-full'       => true,
					'.lg\:w-full:hover' => true,
					'.lg\:w-medium'     => false,
					'.\@\@\@'           => true,
					'.\@\@\@\@'         => false,
				],
			],
			'toggle_class' => [
				implode(
					'',
					[
						'<div id=\"foo\"></div>',
						'<button on="tap:foo . toggleClass ( class = \'expanded\' )">Yes</button>',
						'<button on="tap:foo.toggleClass(class=\'clicked\')">Yes</button>',
						'<button on="tap:foo.toggleClass(class=&quot;tapped&quot;)">Yes</button>',
						'<button on="tap:foo.toggleClass(class=pressed);tap:foo.toggleClass(class = im-pressed)">Yes</button>',
						'<button on="tap:foo.toggleClass(class = \'touch:ed\' )">Yes</button>',
						'<button on="tap:AMP.setState({toggleClass:\'stateful\'})">No</button>',
					]
				),
				[
					'.expanded'   => true,
					'.clicked'    => true,
					'.tapped'     => true,
					'.pressed'    => true,
					'.im-pressed' => true,
					'.touch\:ed'  => true,
					'.exploded'   => false,
					'.stateful'   => false,
				],
			],
		];
	}

	/**
	 * Test attribute selector tree shaking.
	 *
	 * @dataProvider get_attribute_selector_data
	 *
	 * @param string $markup      Source HTML markup.
	 * @param array  $selectors   Mapping of selectors to whether they are expected.
	 */
	public function test_attribute_selector( $markup, $selectors ) {
		$style = implode(
			'',
			array_map(
				static function ( $selector ) {
					return sprintf( '%s{ color: red; }', $selector );
				},
				array_keys( $selectors )
			)
		);

		$html = "<html amp><head><meta charset=utf-8><style amp-custom>$style</style></head><body>$markup</body></html>";
		$dom  = Document::from_html( $html );

		$sanitizer_classes = amp_get_content_sanitizers();

		$sanitized = AMP_Content_Sanitizer::sanitize_document(
			$dom,
			$sanitizer_classes,
			[
				'use_document_element' => true,
			]
		);

		$stylesheets = array_values( $sanitized['stylesheets'] );

		$actual_selectors   = array_values( array_filter( preg_split( '/{.+?}/s', $stylesheets[0] ) ) );
		$expected_selectors = array_keys( array_filter( $selectors ) );
		$this->assertEqualSets( $expected_selectors, $actual_selectors );
	}

	/**
	 * Data for testing CSS hack removal.
	 *
	 * @return array
	 */
	public function get_amp_css_hacks_data() {
		return [
			[
				'.selector { !property: value; }',
			],
			[
				'.selector { $property: value; }',
			],
			[
				'.selector { &property: value; }',
			],
			[
				'.selector { *property: value; }',
			],
			[
				'.selector { )property: value; }',
			],
			[
				'.selector { =property: value; }',
			],
			[
				'.selector { %property: value; }',
			],
			[
				'.selector { +property: value; }',
			],
			[
				'.selector { @property: value; }',
			],
			[
				'.selector { ,property: value; }',
			],
			[
				'.selector { .property: value; }',
			],
			[
				'.selector { /property: value; }',
			],
			[
				'.selector { `property: value; }',
			],
			[
				'.selector { ]property: value; }',
			],
			[
				'.selector { #property: value; }',
			],
			[
				'.selector { ~property: value; }',
			],
			[
				'.selector { ?property: value; }',
			],
			[
				'.selector { :property: value; }',
			],
			[
				'.selector { |property: value; }',
			],
			[
				'_::selection, .selector:not([attr*=\'\']) {}',
			],
			[
				':root .selector {}',
			],
			[
				'body:last-child .selector {}',
			],
			[
				'body:nth-of-type(1) .selector {}',
			],
			[
				'body:first-of-type .selector {}',
			],
			[
				'.selector:not([attr*=\'\']) {}',
			],
			[
				'.selector:not(*:root) {}',
			],
			[
				'.selector:not(*:root) {}',
			],
			[
				'body:empty .selector {}',
			],
			[
				'body:last-child .selector, x:-moz-any-link {}',
			],
			[
				'body:last-child .selector, x:-moz-any-link, x:default {}',
			],
			[
				'body:not(:-moz-handler-blocked) .selector {}',
			],
			[
				'_::-moz-progress-bar, body:last-child .selector {}',
			],
			[
				'_::-moz-range-track, body:last-child .selector {}',
			],
			[
				'_:-moz-tree-row(hover), .selector {}',
			],
			[
				'_::selection, .selector:not([attr*=\'\']) {}',
			],
			[
				'* html .selector  {}',
			],
			[
				'.unused-class.selector {}',
			],
			[
				'html > body .selector {}',
			],
			[
				'.selector, {}',
			],
			[
				'*:first-child+html .selector {}',
			],
			[
				'.selector, x:-IE7 {}',
			],
			[
				'*+html .selector {}',
			],
			[
				'body*.selector {}',
			],
			[
				'.selector\ {}',
			],
			[
				'html > /**/ body .selector {}',
			],
			[
				'head ~ /**/ body .selector {}',
			],
			[
				'_::selection, .selector:not([attr*=\'\']) {}',
			],
			[
				':root .selector {}',
			],
			[
				'body:last-child .selector {}',
			],
			[
				'body:nth-of-type(1) .selector {}',
			],
			[
				'body:first-of-type .selector {}',
			],
			[
				'.selector:not([attr*=\'\']) {}',
			],
			[
				'body { -0-transition: all .3s ease-in-out; }',
			],
			[
				'body { 4-o-transition: all .3s ease-in-out; }',
			],
		];
	}

	/**
	 * Test removal of IE and Other Browser CSS Hacks
	 *
	 * @dataProvider get_amp_css_hacks_data
	 * @param string $input  Hack input CSS rule.
	 */
	public function test_browser_css_hacks( $input ) {
		$html = "<html amp><head><meta charset=utf-8><style amp-custom>$input</style></head><body></body></html>";
		$dom  = Document::from_html( $html );

		$error_codes = [];
		$sanitizer   = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element'      => true,
				'validation_error_callback' => static function( $error ) use ( &$error_codes ) {
					$error_codes[] = $error['code'];
				},
			]
		);
		$sanitizer->sanitize();
		$actual_stylesheets = array_values( $sanitizer->get_stylesheets() );
		$this->assertEmpty( $actual_stylesheets[0] );
	}

	/**
	 * Test handling of stylesheets with @font-face that have data: url source.
	 *
	 * Also confirm that class-based tree-shaking is working.
	 *
	 * @covers AMP_Style_Sanitizer::process_font_face_at_rule()
	 */
	public function test_font_data_url_handling() {
		$html  = '<html amp><head><meta charset="utf-8">';
		$html .= '<link rel="stylesheet" href="' . esc_url( includes_url( 'css/dashicons.css' ) ) . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$html .= '</head><body><span class="b dashicons dashicons-admin-appearance"></span></body></html>';

		// Test with tree-shaking.
		$dom         = Document::from_html( $html );
		$error_codes = [];
		$sanitizer   = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element'      => true,
				'validation_error_callback' => static function( $error ) use ( &$error_codes ) {
					$error_codes[] = $error['code'];
				},
			]
		);
		$sanitizer->sanitize();
		$this->assertEquals( [], $error_codes );
		$actual_stylesheets = array_values( $sanitizer->get_stylesheets() );
		$this->assertCount( 1, $actual_stylesheets );
		$this->assertContains( 'dashicons.woff") format("woff")', $actual_stylesheets[0] );
		$this->assertNotContains( 'data:application/font-woff;', $actual_stylesheets[0] );
		$this->assertContains( '.dashicons{', $actual_stylesheets[0] );
		$this->assertContains( '.dashicons-admin-appearance:before{', $actual_stylesheets[0] );
		$this->assertNotContains( '.dashicons-format-chat:before', $actual_stylesheets[0] );
	}

	/**
	 * Test handling of stylesheets with @font-face that have data: url source.
	 *
	 * Also confirm that class-based tree-shaking is working.
	 *
	 * @link https://github.com/ampproject/amp-wp/pull/2079
	 *
	 * @covers AMP_Style_Sanitizer::process_font_face_at_rule()
	 */
	public function test_font_data_url_handling_without_file_sources() {
		$theme = new WP_Theme( 'twentynineteen', ABSPATH . 'wp-content/themes' );
		if ( $theme->errors() ) {
			$this->markTestSkipped( 'Twenty Nineteen is not installed.' );
		}

		$html  = '<html amp><head><meta charset="utf-8">';
		$html .= sprintf( '<link rel="stylesheet" href="%s">', esc_url( $theme->get_stylesheet_directory_uri() . '/style.css' ) ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$html .= '<style>@font-face { font-family: "Genericons"; src: url("data:application/x-font-woff;charset=utf-8;base64,d09GRgABAAA") format("woff"); }</style>';
		$html .= '<style>@font-face { font-family: "Custom"; src: url("data:application/x-font-woff;charset=utf-8;base64,d09GRgABAAA") format("woff"); }</style>';
		$html .= '</head><body></body></html>';

		$dom         = Document::from_html( $html );
		$error_codes = [];
		$sanitizer   = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element' => true,
			]
		);
		$sanitizer->sanitize();
		$this->assertEquals( [], $error_codes );
		$actual_stylesheets = array_values( $sanitizer->get_stylesheets() );
		$this->assertCount( 3, $actual_stylesheets );

		// Check font included in theme.
		$this->assertContains( '@font-face{font-family:"NonBreakingSpaceOverride";', $actual_stylesheets[0] );
		$this->assertContains( 'format("woff2")', $actual_stylesheets[0] );
		$this->assertContains( 'format("woff")', $actual_stylesheets[0] );
		$this->assertNotContains( 'data:', $actual_stylesheets[0] );
		$this->assertContains( 'fonts/NonBreakingSpaceOverride.woff2', $actual_stylesheets[0] );
		$this->assertContains( 'fonts/NonBreakingSpaceOverride.woff', $actual_stylesheets[0] );
		$this->assertContains( 'font-display:swap', $actual_stylesheets[0] );

		// Check font not included in theme, but included in plugin.
		$this->assertContains( '@font-face{font-family:"Genericons";', $actual_stylesheets[1] );
		$this->assertContains( 'format("woff")', $actual_stylesheets[1] );
		$this->assertNotContains( 'data:', $actual_stylesheets[1] );
		$this->assertContains( 'assets/fonts/genericons.woff', $actual_stylesheets[1] );
		$this->assertContains( 'font-display:swap', $actual_stylesheets[1] );

		// Check font not included anywhere, so must remain inline.
		$this->assertContains( '@font-face{font-family:"Custom";', $actual_stylesheets[2] );
		$this->assertContains( 'url("data:application/x-font-woff;charset=utf-8;base64,d09GRgABAAA")', $actual_stylesheets[2] );
		$this->assertContains( 'format("woff")', $actual_stylesheets[2] );
		$this->assertNotContains( 'font-display:swap', $actual_stylesheets[2] );
	}

	/**
	 * Test that auto-removal (tree shaking) does not remove rules for classes mentioned in class and [class] attributes.
	 *
	 * @covers AMP_Style_Sanitizer::get_used_class_names()
	 * @covers AMP_Style_Sanitizer::finalize_stylesheet_group()
	 */
	public function test_class_amp_bind_preservation() {
		ob_start();
		?>
		<html amp>
			<head>
				<meta charset="utf-8">
				<style>.sidebar1 { display:none }</style>
				<style>.sidebar1.expanded { display:block }</style>
				<style>.sidebar2{ visibility:hidden }</style>
				<style>.sidebar2.visible { display:block }</style>
				<style>.nothing { visibility:hidden; }</style>
			</head>
			<body>
				<amp-state id="mySidebar">
					<script type="application/json">
						{
							"expanded": false
						}
					</script>
				</amp-state>
				<aside class="sidebar1" [class]="! mySidebar.expanded ? '' : 'expanded'">...</aside>
				<aside class="sidebar2" [class]='mySidebar.expanded ? "visible" : ""'>...</aside>
			</body>
		</html>
		<?php
		$dom = Document::from_html( ob_get_clean() );

		$error_codes = [];
		$sanitizer   = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element'      => true,
				'validation_error_callback' => static function( $error ) use ( &$error_codes ) {
					$error_codes[] = $error['code'];
				},
			]
		);
		$sanitizer->sanitize();
		$this->assertEquals( [], $error_codes );
		$actual_stylesheets = array_values( $sanitizer->get_stylesheets() );
		$this->assertEquals( '.sidebar1{display:none}', $actual_stylesheets[0] );
		$this->assertEquals( '.sidebar1.expanded{display:block}', $actual_stylesheets[1] );
		$this->assertEquals( '.sidebar2{visibility:hidden}', $actual_stylesheets[2] );
		$this->assertEquals( '.sidebar2.visible{display:block}', $actual_stylesheets[3] );
		$this->assertEmpty( $actual_stylesheets[4] );
	}

	/**
	 * Test that auto-removal is performed and that excessive CSS will be removed entirely.
	 *
	 * @covers AMP_Style_Sanitizer::finalize_stylesheet_group()
	 */
	public function test_large_custom_css_and_rule_removal() {
		$custom_max_size = null;
		foreach ( AMP_Allowed_Tags_Generated::get_allowed_tag( 'style' ) as $spec_rule ) {
			if ( isset( $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) && 'style amp-custom' === $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) {
				$custom_max_size = $spec_rule[ AMP_Rule_Spec::CDATA ]['max_bytes'];
				break;
			}
		}
		$this->assertNotNull( $custom_max_size );

		$html  = '<html amp><head><meta charset="utf-8">';
		$html .= '<style>.' . str_repeat( 'a', $custom_max_size - 50 ) . '{ color:red } .b{ color:blue; }</style>';
		$html .= '<style>.b[data-value="' . str_repeat( 'c', $custom_max_size ) . '"] { color:green }</style>';
		$html .= '<style>#nonexists { color:black; } #exists { color:white; }</style>';
		$html .= '<style>div { color:black; } span { color:white; } </style>';
		$html .= '<style>@font-face {font-family: "Open Sans";src: url("/fonts/OpenSans-Regular-webfont.woff2") format("woff2");}</style>';
		$html .= '<style>@media only screen and (min-width: 1280px) { .not-exists-selector { margin: 0 auto; } } .b { background: lightblue; }</style>';
		$html .= '
			<style>
			@media screen and (max-width: 1000px) {
				@supports (display: grid) {
					.b::before {
						content: "@media screen and (max-width: 1000px) {";
					}
					.b::after {
						content: "}";
					}
				}
			}
			@media print { @media print { @media print { #nonexists { color:red; } @media presentation {} #verynotexists { color:blue; } } } }
			@media print { @media print { @media print { #nonexists { color:red; } @media presentation {} .b { color:blue; } @media print {} } } }
			@media screen and (min-width: 750px) and (max-width: 999px) {
				.b::before {
					content: "@media screen and (max-width: 1000px) {}";
					content: \'@media screen and (max-width: 1000px) {}\';
				}
			}
			@media screen {}
			</style>
		';
		$html .= '</head><body><span class="b" data-value="">...</span><span id="exists"></span></body></html>';
		$dom   = Document::from_html( $html );

		$error_codes = [];
		$sanitizer   = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element'      => true,
				'validation_error_callback' => static function( $error ) use ( &$error_codes ) {
					$error_codes[] = $error['code'];
				},
			]
		);
		$sanitizer->sanitize();

		$this->assertEquals(
			[
				'.b{color:blue}',
				'#exists{color:white}',
				'span{color:white}',
				'@font-face{font-family:"Open Sans";src:url("/fonts/OpenSans-Regular-webfont.woff2") format("woff2")}',
				'.b{background:lightblue}',
				'@media screen and (max-width: 1000px){@supports (display: grid){.b::before{content:"@media screen and (max-width: 1000px) {"}.b::after{content:"}"}}}@media print{@media print{@media print{.b{color:blue}}}}@media screen and (min-width: 750px) and (max-width: 999px){.b::before{content:"@media screen and (max-width: 1000px) {}";content:"@media screen and (max-width: 1000px) {}"}}',
			],
			array_values( $sanitizer->get_stylesheets() )
		);

		$this->assertEquals(
			[ AMP_Style_Sanitizer::STYLESHEET_TOO_LONG ],
			$error_codes
		);
	}

	/**
	 * Test handling of stylesheets with relative background-image URLs.
	 *
	 * @covers AMP_Style_Sanitizer::real_path_urls()
	 */
	public function test_relative_background_url_handling() {
		$html = '<html amp><head><meta charset="utf-8"><link rel="stylesheet" href="' . esc_url( admin_url( 'css/common.css' ) ) . '"></head><body><span class="spinner"></span></body></html>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$dom  = Document::from_html( $html );

		$sanitizer = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element' => true,
			]
		);
		$sanitizer->sanitize();
		$dom->saveHTML( $dom->documentElement );
		$actual_stylesheets = array_values( $sanitizer->get_stylesheets() );
		$this->assertCount( 1, $actual_stylesheets );
		$stylesheet = $actual_stylesheets[0];

		$this->assertNotContains( '../images/spinner', $stylesheet );
		$this->assertContains( sprintf( '.spinner{background-image:url("%s")', admin_url( 'images/spinner-2x.gif' ) ), $stylesheet );
	}

	/**
	 * Return stylesheets that are to be fetched over HTTP.
	 */
	public function get_http_stylesheets() {
		return [
			'external_file' => [
				'https://stylesheets.example.com/style.css',
				'text/css',
				'html{background-color:lightblue}',
				[],
			],
			'external_file_schemeless' => [
				'//stylesheets.example.com/style.css',
				'text/css',
				'html{background-color:lightblue}',
				[],
			],
			'dynamic_file' => [
				set_url_scheme( add_query_arg( 'action', 'kirki-styles', home_url() ), 'http' ),
				'text/css',
				'body{color:red}',
				[],
			],
			'local_css_file_outside_normal_dirs' => [
				home_url( '/style.css' ),
				'text/css',
				'body{color:green}',
				[],
			],
			'not_css_file' => [
				home_url( '/this.is.not.css' ),
				'image/jpeg',
				'JPEG...',
				[ AMP_Style_Sanitizer::STYLESHEET_FETCH_ERROR ],
			],
		];
	}

	/**
	 * Test handling external stylesheet.
	 *
	 * @dataProvider get_http_stylesheets
	 * @covers AMP_Style_Sanitizer::process_link_element()
	 *
	 * @param string $href                 Request URL.
	 * @param string $content_type         Content type.
	 * @param string $response_body        Response body.
	 * @param array  $expected_error_codes Error codes when getting the stylesheet.
	 */
	public function test_external_stylesheet_handling( $href, $content_type, $response_body, $expected_error_codes ) {
		$request_count = 0;
		add_filter(
			'pre_http_request',
			function( $preempt, $request, $url ) use ( $href, &$request_count, $content_type, $response_body ) {
				$this->assertRegExp( '#^https?://#', $url );
				if ( set_url_scheme( $url, 'https' ) === set_url_scheme( $href, 'https' ) ) {
					$request_count++;
					$preempt = [
						'response' => [
							'code'    => 200,
						],
						'headers' => [
							'content-type' => $content_type,
						],
						'body' => $response_body,
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$sanitize_and_get_stylesheets = static function() use ( $href ) {
			$html = sprintf( '<html amp><head><meta charset="utf-8"><link rel="stylesheet" href="%s"></head><body></body></html>', esc_url( $href ) ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
			$dom  = Document::from_html( $html );

			$found_error_codes = [];

			$sanitizer = new AMP_Style_Sanitizer(
				$dom,
				[
					'use_document_element'      => true,
					'validation_error_callback' => static function( $error ) use ( &$found_error_codes ) {
						$found_error_codes[] = $error['code'];
					},
				]
			);
			$sanitizer->sanitize();
			$dom->saveHTML( $dom->documentElement );
			return [ $found_error_codes, array_values( $sanitizer->get_stylesheets() ) ];
		};

		$this->assertEquals( 0, $request_count );

		list( $found_error_codes, $actual_stylesheets ) = $sanitize_and_get_stylesheets();
		$this->assertEquals( 1, $request_count, 'Expected HTTP request.' );

		if ( empty( $expected_error_codes ) ) {
			$this->assertCount( 1, $actual_stylesheets ); // @todo Change
			$this->assertEquals( $response_body, $actual_stylesheets[0] );
		} else {
			$this->assertEquals( $expected_error_codes, $found_error_codes );
			$this->assertCount( 0, $actual_stylesheets );
		}

		$sanitize_and_get_stylesheets();
		$this->assertEquals( 1, $request_count, 'Expected HTTP request to be cached.' );
	}

	/**
	 * Get amp-keyframe styles.
	 *
	 * @return array
	 */
	public function get_keyframe_data() {
		$keyframes_max_size = null;
		foreach ( AMP_Allowed_Tags_Generated::get_allowed_tag( 'style' ) as $spec_rule ) {
			if ( isset( $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) && 'style[amp-keyframes]' === $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) {
				$keyframes_max_size = $spec_rule[ AMP_Rule_Spec::CDATA ]['max_bytes'];
				break;
			}
		}
		$this->assertNotNull( $keyframes_max_size );

		return [
			'style_amp_keyframes'              => [
				'<style amp-keyframes>@keyframes anim1 { from { opacity:0.0 } to { opacity:0.5 } } @media (min-width: 600px) {@keyframes anim1 { from { opacity:0.5 } to { opacity:1.0 } } }</style>',
				'<style amp-keyframes="">@keyframes anim1{from{opacity:0}to{opacity:.5}}@media (min-width: 600px){@keyframes anim1{from{opacity:.5}to{opacity:1}}}</style>',
				[],
			],

			'style_amp_keyframes_max_overflow' => [
				'<style amp-keyframes>@keyframes anim1 {} @media (min-width: 600px) {@keyframes ' . str_repeat( 'a', $keyframes_max_size + 1 ) . ' {} }</style>',
				'',
				[ AMP_Style_Sanitizer::STYLESHEET_TOO_LONG ],
			],

			'style_amp_keyframes_last_child'   => [
				'<b>before</b> <style amp-keyframes>@keyframes anim1 { from { opacity:1; } to { opacity:0.5; } }</style> between <style amp-keyframes>@keyframes anim2 { from { opacity:0.25; } to { opacity:0.75; } }</style> as <b>after</b>',
				'<b>before</b>  between  as <b>after</b><style amp-keyframes="">@keyframes anim1{from{opacity:1}to{opacity:.5}}@keyframes anim2{from{opacity:.25}to{opacity:.75}}</style>',
				[],
			],

			'blacklisted_and_whitelisted_keyframe_properties' => [
				'<style amp-keyframes>@keyframes anim1 { 50% { width: 50%; animation-timing-function: ease; opacity: 0.5; height:10%; offset-distance: 50%; visibility: visible; transform: rotate(0.5turn); -webkit-transform: rotate(0.5turn); color:red; } }</style>',
				'<style amp-keyframes="">@keyframes anim1{50%{animation-timing-function:ease;opacity:.5;offset-distance:50%;visibility:visible;transform:rotate(.5 turn);-webkit-transform:rotate(.5 turn)}}</style>',
				array_fill( 0, 3, AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_PROPERTY ),
			],

			'style_amp_keyframes_with_disallowed_rules' => [
				'<style amp-keyframes>body { color:red; opacity:1; } @keyframes anim1 { 50% { opacity:0.5 !important; } } @font-face { font-family: "Open Sans"; src: url("/fonts/OpenSans-Regular-webfont.woff2") format("woff2"); }</style>',
				'<style amp-keyframes="">@keyframes anim1{50%{opacity:.5}}</style>',
				[ AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_DECLARATION, AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_IMPORTANT, AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_AT_RULE ],
			],
		];
	}

	/**
	 * Test amp-keyframe styles.
	 *
	 * @dataProvider get_keyframe_data
	 * @param string $source   Markup to process.
	 * @param string $expected The markup to expect.
	 * @param array  $expected_errors      Expected error codes.
	 */
	public function test_keyframe_sanitizer( $source, $expected = null, $expected_errors = [] ) {
		$expected    = isset( $expected ) ? $expected : $source;
		$expected    = preg_replace( '#(?=</style>)#', "\n\n/*# sourceURL=amp-keyframes.css */", $expected );
		$dom         = AMP_DOM_Utils::get_dom_from_content( $source );
		$error_codes = [];
		$sanitizer   = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element' => true,
				'validation_error_callback' => static function( $error ) use ( &$error_codes ) {
					$error_codes[] = $error['code'];
				},
			]
		);
		$sanitizer->sanitize();
		$content = AMP_DOM_Utils::get_content_from_dom( $dom );
		$content = preg_replace( '#\s+(?=@keyframes)#', '', $content );
		$content = preg_replace( '#\s+(?=</style>)#', '', $content );
		$content = preg_replace( '/(?<=>)\s+(?=<)/', '', $content );
		$this->assertEquals( $expected, $content );

		$this->assertEquals( $expected_errors, $error_codes );
	}

	/**
	 * Get stylesheet URLs.
	 *
	 * @returns array Stylesheet URL data.
	 */
	public function get_stylesheet_urls() {

		// Make sure core-bundled themes are registered.
		if ( WP_CONTENT_DIR !== ABSPATH . 'wp-content/themes' ) {
			register_theme_directory( ABSPATH . 'wp-content/themes' );
		}

		$theme = new WP_Theme( 'twentyseventeen', ABSPATH . 'wp-content/themes' );

		return [
			'url_without_path' => [
				'https://example.com',
				null,
				AMP_Style_Sanitizer::STYLESHEET_URL_SYNTAX_ERROR,
			],
			'url_not_string' => [
				false,
				null,
				AMP_Style_Sanitizer::STYLESHEET_URL_SYNTAX_ERROR,
			],
			'theme_stylesheet_without_host' => [
				'/wp-content/themes/twentyseventeen/style.css',
				$theme->get_stylesheet_directory() . '/style.css',
			],
			'theme_stylesheet_with_host' => [
				$theme->get_stylesheet_directory_uri() . '/style.css',
				$theme->get_stylesheet_directory() . '/style.css',
			],
			'theme_stylesheet_with_relative_paths' => [
				$theme->get_stylesheet_directory_uri() . '/foo/./bar/baz/../../../style.css',
				$theme->get_stylesheet_directory() . '/style.css',
			],
			'theme_stylesheet_with_trailing_dot' => [
				$theme->get_stylesheet_directory_uri() . '/foo./bar.css',
				null,
				AMP_Style_Sanitizer::STYLESHEET_FILE_PATH_NOT_FOUND,
			],
			'dashicons_without_host' => [
				'/wp-includes/css/dashicons.css',
				ABSPATH . WPINC . '/css/dashicons.css',
			],
			'dashicons_with_host' => [
				includes_url( 'css/dashicons.css' ),
				ABSPATH . WPINC . '/css/dashicons.css',
			],
			'admin_without_host' => [
				'/wp-admin/css/common.css',
				ABSPATH . 'wp-admin/css/common.css',
			],
			'admin_with_host' => [
				admin_url( 'css/common.css' ),
				ABSPATH . 'wp-admin/css/common.css',
			],
			'admin_with_host_https' => [
				set_url_scheme( admin_url( 'css/common.css' ), 'https' ),
				ABSPATH . 'wp-admin/css/common.css',
			],
			'admin_with_host_http' => [
				set_url_scheme( admin_url( 'css/common.css' ), 'http' ),
				ABSPATH . 'wp-admin/css/common.css',
			],
			'admin_with_no_host_scheme' => [
				preg_replace( '#^\w+:(?=//)#', '', admin_url( 'css/common.css' ) ),
				ABSPATH . 'wp-admin/css/common.css',
			],
			'amp_disallowed_file_extension' => [
				content_url( 'themes/twentyseventeen/index.php' ),
				null,
				AMP_Style_Sanitizer::STYLESHEET_DISALLOWED_FILE_EXT,
			],
			'amp_file_path_not_found' => [
				content_url( 'themes/twentyseventeen/404.css' ),
				null,
				AMP_Style_Sanitizer::STYLESHEET_FILE_PATH_NOT_FOUND,
			],
			'amp_file_path_illegal_linux' => [
				content_url( '../../../../../../../../../../../../../../../bad.css' ),
				null,
				AMP_Style_Sanitizer::STYLESHEET_INVALID_RELATIVE_PATH,
			],
			'amp_file_path_illegal_windows' => [
				content_url( '..\..\..\..\..\..\..\..\..\..\..\..\..\..\..\bad.css' ),
				null,
				AMP_Style_Sanitizer::STYLESHEET_FILE_PATH_NOT_ALLOWED,
			],
			'amp_file_path_illegal_location' => [
				site_url( 'outside/root.css' ),
				null,
				AMP_Style_Sanitizer::STYLESHEET_FILE_PATH_NOT_ALLOWED,
			],
			'amp_external_file' => [
				'//s.w.org/wp-includes/css/dashicons.css',
				false,
				AMP_Style_Sanitizer::STYLESHEET_EXTERNAL_FILE_URL,
			],
		];
	}

	/**
	 * Tests get_validated_url_file_path.
	 *
	 * @dataProvider get_stylesheet_urls
	 * @covers AMP_Style_Sanitizer::get_validated_url_file_path()
	 *
	 * @param string      $source     Source URL.
	 * @param string|null $expected   Expected path or null if error.
	 * @param string      $error_code Error code. Optional.
	 */
	public function test_get_validated_url_file_path( $source, $expected, $error_code = null ) {
		$dom = Document::from_html( '<html></html>' );

		$sanitizer = new AMP_Style_Sanitizer( $dom );
		$actual    = $sanitizer->get_validated_url_file_path( $source, [ 'css' ] );
		if ( isset( $error_code ) ) {
			$this->assertInstanceOf( 'WP_Error', $actual );
			$this->assertEquals( $error_code, $actual->get_error_code() );
		} else {
			$this->assertInternalType( 'string', $actual );
			$this->assertEquals( $expected, $actual );
		}
	}

	/**
	 * Get data for CSS rules with url() values.
	 *
	 * @returns array Data.
	 */
	public function get_style_rules_with_url_values() {
		return [
			'url_with_spaces' => [
				'html { background-image:url(url with spaces.png); }',
				'html{background-image:url("urlwithspaces.png")}',
			],
			'data_url_with_spaces' => [
				'html { background: url(data:image/png; base64, ivborw0kggoaaaansuheugaaacwaaaascamaaaapwqozaaaabgdbtueaalgpc/xhbqaaaafzukdcak7ohokaaaamuexurczmzpf399fx1+bm5mzy9amaaadisurbvdjlvzxbesmgces5/p8/t9furvcrmu73jwlzosgsiizurcjo/ad+eqjjb4hv8bft+idpqocx1wjosbfhh2xssxeiyn3uli/6mnree07uiwjev8ueowds88ly97kqytlijkktuybbruayvh5wohixmpi5we58ek028czwyuqdlkpg1bkb4nnm+veanfhqn1k4+gpt6ugqcvu2h2ovuif/gwufyy8owepdyzsa3avcqpvovvzzz2vtnn2wu8qzvjddeto90gsy9mvlqtgysy231mxry6i2ggqjrty0l8fxcxfcbbhwrsyyaaaaaelftksuqmcc); }',
				'html{background:url("data:image/png;base64,ivborw0kggoaaaansuheugaaacwaaaascamaaaapwqozaaaabgdbtueaalgpc/xhbqaaaafzukdcak7ohokaaaamuexurczmzpf399fx1+bm5mzy9amaaadisurbvdjlvzxbesmgces5/p8/t9furvcrmu73jwlzosgsiizurcjo/ad+eqjjb4hv8bft+idpqocx1wjosbfhh2xssxeiyn3uli/6mnree07uiwjev8ueowds88ly97kqytlijkktuybbruayvh5wohixmpi5we58ek028czwyuqdlkpg1bkb4nnm+veanfhqn1k4+gpt6ugqcvu2h2ovuif/gwufyy8owepdyzsa3avcqpvovvzzz2vtnn2wu8qzvjddeto90gsy9mvlqtgysy231mxry6i2ggqjrty0l8fxcxfcbbhwrsyyaaaaaelftksuqmcc")}',
			],
			'svg_url_with_spaces_in_single_quotes' => [
				'html { mask-image: url(\'data:image/svg+xml;utf8,\\00003Csvg viewBox=\"0 0 100 100\" xmlns=\"http://www.w3.org/2000/svg\"\\00003E\\00003Ccircle cx=\"50\" cy=\"50\" r=\"50\"/\\00003E\\00003C/svg\\00003E\' ); }',
				'html{mask-image:url("data:image/svg+xml;utf8,<svg viewBox=\"0 0 100 100\" xmlns=\"http://www.w3.org/2000/svg\"><circle cx=\"50\" cy=\"50\" r=\"50\"/></svg>")}',
			],
			'svg_url_with_spaces_in_double_quotes' => [
				'html { mask-image: url( "data:image/svg+xml;utf8,\\00003Csvg viewBox=\'0 0 100 100\' xmlns=\'http://www.w3.org/2000/svg\'\\00003E\\00003Ccircle cx=\'50\' cy=\'50\' r=\'50\'/\\00003E\\00003C/svg\\00003E" ); }',
				"html{mask-image:url(\"data:image/svg+xml;utf8,<svg viewBox=\'0 0 100 100\' xmlns=\'http://www.w3.org/2000/svg\'><circle cx=\'50\' cy=\'50\' r=\'50\'/></svg>\")}",
			],
			'svg_url_with_encoded_spaces_in_quotes' => [
				'html { mask-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2243%22%20height%3D%2244%22%20viewBox%3D%220%200%2043%2044%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%3E%3Cdefs%3E%3Cpath%20d%3D%22M42.5%2018H22v8.5h11.8C32.7%2031.9%2028.1%2035%2022%2035c-7.2%200-13-5.8-13-13S14.8%209%2022%209c3.1%200%205.9%201.1%208.1%202.9l6.4-6.4C32.6%202.1%2027.6%200%2022%200%209.8%200%200%209.8%200%2022s9.8%2022%2022%2022c11%200%2021-8%2021-22%200-1.3-.2-2.7-.5-4z%22%20id%3D%22a%22%2F%3E%3C%2Fdefs%3E%3Cuse%20fill%3D%22%23FFF%22%20xlink%3Ahref%3D%22%23a%22%20fill-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E"); }',
				'html{mask-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2243%22%20height%3D%2244%22%20viewBox%3D%220%200%2043%2044%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%3E%3Cdefs%3E%3Cpath%20d%3D%22M42.5%2018H22v8.5h11.8C32.7%2031.9%2028.1%2035%2022%2035c-7.2%200-13-5.8-13-13S14.8%209%2022%209c3.1%200%205.9%201.1%208.1%202.9l6.4-6.4C32.6%202.1%2027.6%200%2022%200%209.8%200%200%209.8%200%2022s9.8%2022%2022%2022c11%200%2021-8%2021-22%200-1.3-.2-2.7-.5-4z%22%20id%3D%22a%22%2F%3E%3C%2Fdefs%3E%3Cuse%20fill%3D%22%23FFF%22%20xlink%3Ahref%3D%22%23a%22%20fill-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E")}',
			],
			'svg_url_with_encoded_spaces_in_no_quotes' => [
				'html { mask-image: url(data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2243%22%20height%3D%2244%22%20viewBox%3D%220%200%2043%2044%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%3E%3Cdefs%3E%3Cpath%20d%3D%22M42.5%2018H22v8.5h11.8C32.7%2031.9%2028.1%2035%2022%2035c-7.2%200-13-5.8-13-13S14.8%209%2022%209c3.1%200%205.9%201.1%208.1%202.9l6.4-6.4C32.6%202.1%2027.6%200%2022%200%209.8%200%200%209.8%200%2022s9.8%2022%2022%2022c11%200%2021-8%2021-22%200-1.3-.2-2.7-.5-4z%22%20id%3D%22a%22%2F%3E%3C%2Fdefs%3E%3Cuse%20fill%3D%22%23FFF%22%20xlink%3Ahref%3D%22%23a%22%20fill-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E); }',
				'html{mask-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2243%22%20height%3D%2244%22%20viewBox%3D%220%200%2043%2044%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%3E%3Cdefs%3E%3Cpath%20d%3D%22M42.5%2018H22v8.5h11.8C32.7%2031.9%2028.1%2035%2022%2035c-7.2%200-13-5.8-13-13S14.8%209%2022%209c3.1%200%205.9%201.1%208.1%202.9l6.4-6.4C32.6%202.1%2027.6%200%2022%200%209.8%200%200%209.8%200%2022s9.8%2022%2022%2022c11%200%2021-8%2021-22%200-1.3-.2-2.7-.5-4z%22%20id%3D%22a%22%2F%3E%3C%2Fdefs%3E%3Cuse%20fill%3D%22%23FFF%22%20xlink%3Ahref%3D%22%23a%22%20fill-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E")}',
			],
		];
	}

	/**
	 * Test handling of stylesheets with spaces in the background-image URLs.
	 *
	 * @dataProvider get_style_rules_with_url_values
	 * @covers AMP_Style_Sanitizer::remove_spaces_from_url_values()
	 *
	 * @param string      $source     Source URL string.
	 * @param string|null $expected   Expected normalized URL string.
	 */
	public function test_remove_spaces_from_url_values( $source, $expected ) {
		$html  = '<html><head><style>';
		$html .= $source;
		$html .= '</style></head</html>';

		$dom = Document::from_html( $html );

		$sanitizer = new AMP_Style_Sanitizer( $dom );
		$sanitizer->sanitize();

		$stylesheets = array_values( $sanitizer->get_stylesheets() );

		$this->assertContains( $expected, $stylesheets[0] );
	}

	/**
	 * Get font url test data.
	 *
	 * @return array Data.
	 */
	public function get_font_urls() {
		return [
			'tangerine'   => [
				'https://fonts.googleapis.com/css?family=Tangerine',
				[],
			],
			'tangerine2'  => [
				'//fonts.googleapis.com/css?family=Tangerine',
				[],
			],
			'tangerine3'  => [
				'http://fonts.googleapis.com/css?family=Tangerine',
				[],
			],
			'typekit'     => [
				'https://use.typekit.net/abc.css',
				[],
			],
			'fontscom'    => [
				'https://fast.fonts.net/abc.css',
				[],
			],
			'fontawesome' => [
				'https://maxcdn.bootstrapcdn.com/font-awesome/123/css/font-awesome.min.css',
				[],
			],
		];
	}

	/**
	 * Tests that font URLs get validated.
	 *
	 * @covers ::amp_filter_font_style_loader_tag_with_crossorigin_anonymous()
	 * @dataProvider get_font_urls
	 * @param string $url         Font URL.
	 * @param array  $error_codes Error codes.
	 */
	public function test_font_urls( $url, $error_codes ) {
		$tag = sprintf( '<link rel="stylesheet" href="%s">', esc_url( $url ) ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$tag = amp_filter_font_style_loader_tag_with_crossorigin_anonymous( $tag, 'font', $url );

		$dom = Document::from_html( sprintf( '<html><head>%s</head></html>', $tag ) );

		$validation_errors = [];

		$sanitizer = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element'      => true,
				'validation_error_callback' => static function( $error ) use ( &$validation_errors ) {
					$validation_errors[] = $error;
				},
			]
		);
		$sanitizer->sanitize();

		$this->assertEqualSets( $error_codes, wp_list_pluck( $validation_errors, 'code' ) );

		$link = $dom->getElementsByTagName( 'link' )->item( 0 );
		if ( empty( $error_codes ) ) {
			$this->assertInstanceOf( 'DOMElement', $link );
			$this->assertEquals(
				preg_replace( '#^(http:)?(?=//)#', 'https:', $url ),
				$link->getAttribute( 'href' )
			);
			$this->assertEquals( 'anonymous', $link->getAttribute( 'crossorigin' ) );
		} else {
			$this->assertEmpty( $link );
		}
	}

	/**
	 * Test addition of crossorigin attribute to external stylesheet links.
	 *
	 * @covers AMP_Style_Sanitizer::process_link_element()
	 * @covers ::amp_filter_font_style_loader_tag_with_crossorigin_anonymous()
	 */
	public function test_cors_enabled_stylesheet_url() {

		// Test supplying crossorigin attribute.
		$url       = 'https://fonts.googleapis.com/css?family=Tangerine';
		$link      = amp_filter_font_style_loader_tag_with_crossorigin_anonymous( "<link rel='stylesheet' href='$url'>", 'font', $url ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$document  = Document::from_html( "<html><head>$link</head></html>" );
		$sanitizer = new AMP_Style_Sanitizer( $document, [ 'use_document_element' => true ] );
		$sanitizer->sanitize();
		$link = $document->getElementsByTagName( 'link' )->item( 0 );
		$this->assertInstanceOf( 'DOMElement', $link );
		$this->assertEquals( 'anonymous', $link->getAttribute( 'crossorigin' ) );

		// Test that existing crossorigin attribute is not overridden.
		$link      = amp_filter_font_style_loader_tag_with_crossorigin_anonymous( "<link crossorigin='use-credentials' rel='stylesheet' href='$url'>", 'font', $url ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$document  = Document::from_html( "<html><head>$link</head></html>" ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$sanitizer = new AMP_Style_Sanitizer( $document, [ 'use_document_element' => true ] );
		$sanitizer->sanitize();
		$link = $document->getElementsByTagName( 'link' )->item( 0 );
		$this->assertInstanceOf( 'DOMElement', $link );
		$this->assertEquals( 'use-credentials', $link->getAttribute( 'crossorigin' ) );
	}

	/**
	 * Data source for test_css_import.
	 *
	 * @return array
	 */
	public function get_import_test_data() {
		return [
			'local_css_files' => [
				[
					plugins_url( 'tests/php/data/css/foo/../login.css', AMP__FILE__ ),
					plugins_url( 'tests/php/data/css/buttons.css', AMP__FILE__ ),
				],
				'<style>div::after{content:"After login"}</style><div><input type="checkbox"><button class="wp-core-ui button"></button></div>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				0, // Zero HTTP requests.
				null, // No preempting of request, as no external requests.
				static function ( WP_UnitTestCase $test, $stylesheet ) {
					$expected_order = [
						preg_quote( 'input[type="checkbox"]:disabled', '/' ),
						preg_quote( '.wp-core-ui .button', '/' ),
						preg_quote( 'div::after{content:"After login"}', '/' ),
					];
					$test->assertRegExp(
						'/.*' . implode( '.*', $expected_order ) . '/s',
						$stylesheet
					);
				},
			],

			'local_css_with_import_failure_rejecting' => [
				[
					admin_url( 'css/local-does-not-exist.css' ),
					'https://bogus.example.com/remote-does-not-exist.css',
					plugins_url( 'tests/php/data/css/foo/../login.css', AMP__FILE__ ),
					'https://bogus.example.com/remote-also-does-not-exist.css',
				],
				'<style>div::after{content:"End"}</style><style>@import url("https://bogus.example.com/remote-finally-does-not-exist.css");</style><body class="locale-he-il"><div class="login message"></div><table class="form-table"><td></td></table></body>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				4, // All four result in HTTP requests, even the local one because it doesn't exist on the filesystem.
				static function ( $requested_url ) {
					if ( false !== strpos( $requested_url, 'does-not-exist' ) ) {
						return new WP_Error( 'does_not_exist' );
					}
					return null;
				},
				static function ( WP_UnitTestCase $test, $stylesheet ) {
					$expected_order = [
						'local-does-not-exist.css',
						'remote-does-not-exist.css',
						'remote-also-does-not-exist.css',
						'remote-finally-does-not-exist.css',
						'.form-table td', // From imported forms.css.
						'body.locale-he-il', // From imported l10n.css.
						'.login .message', // From login.css.
						'div::after{content:"End"}',
					];

					$previous = -1;
					foreach ( $expected_order as $i => $expected ) {
						$test->assertContains( $expected, $stylesheet, "Did not see $expected at position $i." );
						$position = strpos( $stylesheet, $expected );
						$test->assertGreaterThan( $previous, $position, "Expected $expected to be after previous (at position $i)." );
						$previous = $position;
					}
				},
				[
					'auto_reject' => true,
				],
			],

			'local_css_with_import_failure_accepting' => [
				[
					admin_url( 'css/local-does-not-exist.css' ),
					'https://bogus.example.com/remote-does-not-exist.css',
					plugins_url( 'tests/php/data/css/foo/../login.css', AMP__FILE__ ),
					'https://bogus.example.com/remote-also-does-not-exist.css',
				],
				'<style>div::after{content:"End"}</style><style>@import url("https://bogus.example.com/remote-finally-does-not-exist.css");</style><body class="locale-he-il"><div class="login message"></div><table class="form-table"><td></td></table></body>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				4, // All four result in HTTP requests, even the local one because it doesn't exist on the filesystem.
				static function ( $requested_url ) {
					if ( false !== strpos( $requested_url, 'does-not-exist' ) ) {
						return new WP_Error( 'does_not_exist' );
					}
					return null;
				},
				static function ( WP_UnitTestCase $test, $stylesheet ) {
					$expected_absent = [
						'local-does-not-exist.css',
						'remote-does-not-exist.css',
						'remote-also-does-not-exist.css',
						'remote-finally-does-not-exist.css',
					];
					foreach ( $expected_absent as $expected ) {
						$test->assertNotContains( $expected, $stylesheet, "Expected to not see $expected." );
					}

					$expected_order = [
						'.form-table td', // From imported forms.css.
						'body.locale-he-il', // From imported l10n.css.
						'.login .message', // From login.css.
						'div::after{content:"End"}',
					];

					$previous = -1;
					foreach ( $expected_order as $i => $expected ) {
						$test->assertContains( $expected, $stylesheet, "Did not see $expected at position $i." );
						$position = strpos( $stylesheet, $expected );
						$test->assertGreaterThan( $previous, $position, "Expected $expected to be after previous (at position $i)." );
						$previous = $position;
					}
				},
				[
					'auto_reject' => false,
				],
			],

			'dynamic_stylesheet_with_relative_import' => [
				includes_url( '/dynamic/import-buttons.php' ),
				'<style>div::after{content:"After import-buttons"}</style><body class="wp-core-ui"><div><button class="button"></button></div></body>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				1,
				static function( $requested_url ) {
					if ( false !== strpos( $requested_url, 'import-buttons.php' ) ) {
						return '@import url( "../css/./foo/../buttons.css" );body{color:#123456}';
					}
					return null;
				},
				static function ( WP_UnitTestCase $test, $stylesheet ) {
					$test->assertRegExp(
						'/.*' . preg_quote( '.wp-core-ui .button', '/' ) . '.*' . preg_quote( 'body{color:#123456}', '/' ) . '.*' . preg_quote( 'div::after{content:"After import-buttons"}', '/' ) . '/s',
						$stylesheet
					);
				},
			],

			'dynamic_stylesheet_with_absolute_import' => [
				includes_url( '/dynamic/import-buttons.php' ),
				'<style>div::after{content:"After import-buttons2"}</style><body class="wp-core-ui"><div><button class="button"></button></div></body>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				1,
				static function( $requested_url ) {
					if ( false !== strpos( $requested_url, 'import-buttons.php' ) ) {
						return sprintf( '@import "%s";body{color:#123456}', plugins_url( 'tests/php/data/css/buttons.css', AMP__FILE__ ) );
					}
					return null;
				},
				static function ( WP_UnitTestCase $test, $stylesheet ) {
					$test->assertRegExp(
						'/.*' . preg_quote( '.wp-core-ui .button', '/' ) . '.*' . preg_quote( 'body{color:#123456}', '/' ) . '.*' . preg_quote( 'div::after{content:"After import-buttons2"}', '/' ) . '/s',
						$stylesheet
					);
				},
			],

			'dynamic_stylesheet_with_nested_dynamic_stylesheet' => [
				includes_url( '/dynamic/import-buttons.php' ),
				'<style>div::after{content:"After import-buttons2"}</style><body><div></div></body>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				2,
				static function( $requested_url ) {
					$self_call_url = includes_url( '/dynamic/nested.php' );
					if ( false !== strpos( $requested_url, 'import-buttons.php' ) ) {
						return sprintf( '@import "%s";body{color:#123456}', $self_call_url );
					} elseif ( wp_parse_url( $self_call_url, PHP_URL_PATH ) === wp_parse_url( $requested_url, PHP_URL_PATH ) ) {
						return 'div::before{ content:"HELLO NESTED"; }';
					}
					return null;
				},
				static function ( WP_UnitTestCase $test, $stylesheet ) {
					$test->assertRegExp(
						'/.*' . preg_quote( 'div::before{content:"HELLO NESTED"}', '/' ) . '.*' . preg_quote( 'body{color:#123456}', '/' ) . '.*' . preg_quote( 'div::after{content:"After import-buttons2"}', '/' ) . '/s',
						$stylesheet
					);
				},
			],
		];
	}

	/**
	 * Test CSS imports.
	 *
	 * @dataProvider get_import_test_data
	 * @covers AMP_Style_Sanitizer::splice_imported_stylesheet()
	 *
	 * @param array|string $stylesheet_urls             Stylesheet URLs.
	 * @param string       $style_element               HTML markup for the stylesheet URL.
	 * @param int          $expected_http_request_count Expected number of HTTP requests.
	 * @param callable     $mock_response               Function that returns the mocked CSS data.
	 * @param callable     $assert                      Function that runs the assertions.
	 * @param array        $options                     Additional options.
	 */
	public function test_css_import( $stylesheet_urls, $style_element, $expected_http_request_count, $mock_response, $assert, $options = [] ) {
		$stylesheet_urls = (array) $stylesheet_urls;

		$markup  = '<html><head>';
		$imports = implode(
			'',
			array_map(
				static function ( $stylesheet_url ) {
					return sprintf( '@import url("%s");', $stylesheet_url );
				},
				$stylesheet_urls
			)
		);
		$markup .= preg_replace( ':(?<=<style>):', $imports, $style_element );
		$markup .= '</head><body>hello</body></html>';

		$http_request_count = 0;

		add_filter(
			'pre_http_request',
			static function( $preempt, $request, $url ) use ( $mock_response, $stylesheet_urls, &$http_request_count ) {
				$http_request_count++;
				if ( $mock_response ) {
					$body = $mock_response( $url, $stylesheet_urls );
					if ( null !== $body ) {
						$preempt = [
							'response' => [
								'code'    => is_wp_error( $body ) ? 404 : 200,
								'message' => is_wp_error( $body ) ? 'Not Found' : 'OK',
							],
							'headers'  => [ 'content-type' => 'text/css' ],
							'body'     => is_wp_error( $body ) ? '' : $body,
						];
					}
				}
				return $preempt;
			},
			10,
			3
		);

		$dom = Document::from_html( $markup );

		if ( ! empty( $options['auto_reject'] ) ) {
			add_filter( 'amp_validation_error_sanitized', '__return_false' );
		}

		$sanitizer = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element'      => true,
				'validation_error_callback' => 'AMP_Validation_Manager::add_validation_error',
			]
		);
		$sanitizer->sanitize();

		$stylesheet = $dom->getElementsByTagName( 'style' )->item( 0 )->textContent;

		$assert( $this, $stylesheet, $dom );
		$this->assertEquals( $expected_http_request_count, $http_request_count );
	}

	/**
	 * Test that @import'ing a font URL gets converted into a link.
	 *
	 * @expectedIncorrectUsage wp_enqueue_style
	 * @covers AMP_Style_Sanitizer::splice_imported_stylesheet()
	 */
	public function test_css_import_font() {
		$stylesheet_url = 'http://fonts.googleapis.com/css?family=Merriweather:300|PT+Serif:400i|Open+Sans:800|Zilla+Slab:300,400,500|Montserrat:800|Muli:400&subset=cyrillic-ext,latin-ext,cyrillic,greek,greek-ext,vietnamese';

		$markup  = '<html><head>';
		$markup .= sprintf( '<style>@import "%s"; body{color:red}</style>', $stylesheet_url );
		$markup .= '</head><body>hello</body></html>';

		$dom       = Document::from_html( $markup );
		$sanitizer = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element' => true,
			]
		);
		$sanitizer->sanitize();
		$stylesheets = array_values( $sanitizer->get_stylesheets() );

		$this->assertCount( 1, $stylesheets );
		$this->assertEquals( 'body{color:red}', $stylesheets[0] );
		$link = $dom->xpath->query( '//link[ @rel = "stylesheet" ]' )->item( 0 );
		$this->assertInstanceOf( 'DOMElement', $link );
		$this->assertEquals( set_url_scheme( $stylesheet_url, 'https' ), $link->getAttribute( 'href' ) );
	}

	/**
	 * Test CSS with Unicode characters.
	 *
	 * @covers \AMP_DOM_Utils::get_content_from_dom_node()
	 */
	public function test_unicode_stylesheet() {
		wp();
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		ob_start();
		?>
		<!DOCTYPE html>
		<html amp>
			<head>
				<meta charset="utf-8">
				<?php wp_print_styles( [ 'dashicons' ] ); ?>
				<style>span::after { content:"⚡️"; }</style>
			</head>
			<body>
				<span class="dashicons dashicons-admin-customizer"></span>
			</body>
		</html>
		<?php
		$original_html  = trim( ob_get_clean() );
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html );

		$this->assertContains( ".dashicons-admin-customizer:before{content:\"\xEF\x95\x80\"}", $sanitized_html );
		$this->assertContains( 'span::after{content:"⚡️"}', $sanitized_html );
	}

	/**
	 * Test style element with old-school XHTML CDATA.
	 *
	 * @covers \AMP_Style_Sanitizer::parse_stylesheet()
	 */
	public function test_style_element_cdata() {
		$html  = '<!DOCTYPE html><html amp><head><meta charset="utf-8">';
		$html .= '<style><![CDATA[ body { color:red } ]]></style>';
		$html .= '<style>/*<![CDATA[*/ body { color:green } /*]]>*/</style>';
		$html .= '<style><!--/*--><![CDATA[/*><!--*/ body { color:blue } /*]]>*/--></style>';
		$html .= '</head><body><p>Hello World</p></body></html>';

		$dom       = Document::from_html( $html );
		$sanitizer = new AMP_Style_Sanitizer(
			$dom,
			[
				'use_document_element' => true,
			]
		);

		$sanitizer->sanitize();

		$style = $dom->xpath->query( '//style[ @amp-custom ]' )->item( 0 );
		$this->assertInstanceOf( 'DOMElement', $style );

		$expected = "body{color:red}body{color:green}body{color:blue}\n\n/*# sourceURL=amp-custom.css */";
		$this->assertEquals( $expected, $style->nodeValue );
	}

	/**
	 * Test that a font stylesheet is moved to the head.
	 *
	 * @covers \AMP_Style_Sanitizer::sanitize()
	 */
	public function test_body_font_stylesheet_moved_to_head() {
		$html = '<!DOCTYPE html><html amp><head><meta charset="utf-8"></head><body><link rel="stylesheet" id="the-font" href="https://fonts.googleapis.com/css?family=Merriweather%3A400%2C700" type="text/css" media="all"></body></html>'; // phpcs:ignore
		$dom  = Document::from_html( $html );

		$link = $dom->getElementById( 'the-font' );
		$this->assertInstanceOf( 'DOMElement', $link );
		$this->assertEquals( 'body', $link->parentNode->nodeName );

		$sanitizer_args = [ 'use_document_element' => true ];

		$sanitizer = new AMP_Style_Sanitizer( $dom, $sanitizer_args );
		$sanitizer->sanitize();

		$sanitizer = new AMP_Tag_And_Attribute_Sanitizer( $dom, $sanitizer_args );
		$sanitizer->sanitize();

		$this->assertInstanceOf( 'DOMElement', $link->parentNode );
		$this->assertEquals( 'head', $link->parentNode->nodeName );
	}

	/**
	 * Get prioritization test data.
	 *
	 * @todo Refactor to use custom theme or existing theme instead of requiring Twenty Ten.
	 *
	 * @return array
	 */
	public function get_prioritization_data() {
		add_filter(
			'theme_root',
			static function () {
				return ABSPATH . 'wp-content/themes';
			}
		);
		add_filter(
			'theme_root_uri',
			static function () {
				return site_url( 'wp-content/themes' );
			}
		);

		$render_template = static function () {
			ob_start();
			?>
			<!DOCTYPE html><html><head><meta charset="utf-8"><?php wp_head(); ?></head><body <?php body_class(); ?>><?php wp_footer(); ?></body></html>
			<?php
			return ob_get_clean();
		};

		return [
			'admin_bar_included' => [
				function () use ( $render_template ) {
					$this->go_to( home_url() );
					show_admin_bar( true );
					_wp_admin_bar_init();
					switch_theme( 'twentyten' );
					require_once get_template_directory() . '/functions.php';
					add_action(
						'wp_head',
						static function() {
							printf( '<style media=print id="early-print-style">html:after { content:"earlyprintstyle %s"; }</style>', esc_html( str_repeat( 'a', 75000 - 10 ) ) );
						},
						-1000
					);
					add_action( 'wp_enqueue_scripts', 'twentyten_scripts_styles' );
					AMP_Theme_Support::add_hooks();
					wp_add_inline_style( 'admin-bar', '.admin-bar-inline-style{ color:red }' );

					add_action(
						'wp_footer',
						function() {
							?>
							<div class="is-style-outline"><button class="wp-block-button__link"></button></div>
							<div class="wp-block-foo"><figcaption></figcaption></div>
							<img src="https://example.com/example.jpg" width="100" height="200">
							<?php
						}
					);

					return $render_template();
				},
				function( $original_dom, $original_source, $amphtml_dom, $amphtml_source ) {
					/**
					 * Vars.
					 *
					 * @var Document $original_dom
					 * @var string   $original_source
					 * @var Document $amphtml_dom
					 * @var string   $amphtml_source
					 */
					$this->assertInstanceOf( 'DOMElement', $original_dom->getElementById( 'wpadminbar' ), 'Expected admin bar element to be present originally.' );
					$this->assertInstanceOf( 'DOMElement', $original_dom->getElementById( 'admin-bar-css' ), 'Expected admin bar CSS to be present originally.' );
					$this->assertContains( 'admin-bar', $original_dom->body->getAttribute( 'class' ) );
					$this->assertContains( 'earlyprintstyle', $original_source, 'Expected early print style to not be present.' );

					$this->assertContains( '.is-style-outline .wp-block-button__link', $amphtml_source, 'Expected block-library/style.css' );
					$this->assertContains( '[class^="wp-block-"]:not(.wp-block-gallery) figcaption', $amphtml_source, 'Expected twentyten/blocks.css' );
					$this->assertContains( 'amp-img.amp-wp-enforced-sizes', $amphtml_source, 'Expected amp-default.css' );
					$this->assertContains( 'ab-empty-item', $amphtml_source, 'Expected admin-bar.css to still be present.' );
					$this->assertNotContains( 'earlyprintstyle', $amphtml_source, 'Expected early print style to not be present.' );
					$this->assertContains( 'admin-bar', $amphtml_dom->body->getAttribute( 'class' ) );
					$this->assertInstanceOf( 'DOMElement', $amphtml_dom->getElementById( 'wpadminbar' ) );
				},
			],
			// @todo Add other scenarios in the future.
		];
	}

	/**
	 * Test stylesheet prioritization.
	 *
	 * @dataProvider get_prioritization_data
	 * @covers \AMP_Style_Sanitizer::finalize_stylesheet_group()
	 * @covers \AMP_Style_Sanitizer::get_stylesheet_priority()
	 *
	 * @param callable $html_generator Generator of HTML.
	 * @param callable $assert         Function which runs assertions.
	 */
	public function test_prioritized_stylesheets( $html_generator, $assert ) {
		if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
			$this->markTestSkipped( 'Requires WordPress 5.0.' );
		}
		global $wp_theme_directories; // Note that get_theme_roots() does not work, for some reason.
		$theme_exists = false;
		foreach ( $wp_theme_directories as $theme_root ) {
			$theme_exists = wp_get_theme( 'twentyten', $theme_root )->exists();
			if ( $theme_exists ) {
				break;
			}
		}
		if ( ! $theme_exists ) {
			$this->markTestSkipped( 'Requires Twenty Ten to be installed.' );
		}

		add_theme_support( 'amp' );
		$this->go_to( home_url() );
		$html = $html_generator();

		$original_dom = Document::from_html( $html );
		$amphtml_dom  = clone( $original_dom );

		$error_codes = [];
		$args        = [
			'use_document_element'      => true,
			'validation_error_callback' => static function( $error ) use ( &$error_codes ) {
				$error_codes[] = $error['code'];
			},
		];

		$sanitizer = new AMP_Img_Sanitizer( $amphtml_dom, $args );
		$sanitizer->sanitize();

		$sanitizer = new AMP_Style_Sanitizer( $amphtml_dom, $args );
		$sanitizer->sanitize();

		$whitelist_sanitizer = new AMP_Tag_And_Attribute_Sanitizer( $amphtml_dom, $args );
		$whitelist_sanitizer->sanitize();

		$assert( $original_dom, $html, $amphtml_dom, $amphtml_dom->saveHTML(), $sanitizer->get_stylesheets() );
	}

	/**
	 * Provide data to test_get_stylesheet_priority().
	 *
	 * @return array[] Array of test data.
	 */
	public function get_stylesheet_priority_data() {
		return [
			'Non-AMP handle' => [ [ 'link', [ 'id' => 'mediaelement-css' ] ], 1000 ],
			'Admin bar handle' => [ [ 'link', [ 'id' => 'admin-bar-css' ] ], 200 ],
			'Dashicons handle' => [ [ 'link', [ 'id' => 'dashicons-css' ] ], 90 ],
			'Parent theme styles' => [ [ 'link', [ 'href' => get_theme_root_uri() . '/parent-theme/style.css' ] ], 1 ],
			'Child theme styles' => [ [ 'link', [ 'href' => get_theme_root_uri() . '/child-theme/style.css' ] ], 10 ],
			'Core frontend handle' => [ [ 'link', [ 'id' => 'wp-block-library-css' ] ], 20 ],
			'Plugin asset' => [ [ 'link', [ 'href' => plugins_url() . '/some-plugin/style.css' ] ], 30 ],
			'Query monitor plugin asset' => [ [ 'link', [ 'href' => plugins_url() . '/query-monitor/style.css' ] ], 150 ],
			'Other styles from wp-includes' => [ [ 'link', [ 'href' => includes_url() ] ], 40 ],
			'All other links' => [ [ 'link', [ 'id' => 'something-else' ] ], 50 ],
			'Parent theme inline styles' => [ [ 'style', [ 'id' => 'parent-theme-inline-css' ] ], 2 ],
			'Child theme inline styles' => [ [ 'style', [ 'id' => 'child-theme-inline-css' ] ], 12 ],
			'Admin bar inline styles' => [ [ 'style', [ 'id' => 'admin-bar-inline-css' ] ], 200 ],
			'Customizer inline styles' => [ [ 'style', [ 'id' => 'wp-custom-css' ] ], 60 ],
			'Other inline styles' => [ [ 'style', [ 'id' => 'something-else' ] ], 70 ],
			'Non-AMP handle for print' => [
				[
					'link',
					[
						'id' => 'mediaelement-css',
						'media' => 'print',
					],
				],
				1100,
			],
			'Admin bar handle for print' => [
				[
					'link',
					[
						'id' => 'admin-bar-css',
						'media' => 'print',
					],
				],
				300,
			],
			'Dashicons handle for print' => [
				[
					'link',
					[
						'id' => 'dashicons-css',
						'media' => 'print',
					],
				],
				190,
			],
			'Parent theme styles for print' => [
				[
					'link',
					[
						'href' => get_theme_root_uri() . '/parent-theme/style.css',
						'media' => 'print',
					],
				],
				101,
			],
			'Child theme styles for print' => [
				[
					'link',
					[
						'href' => get_theme_root_uri() . '/child-theme/style.css',
						'media' => 'print',
					],
				],
				110,
			],
			'Core frontend handle for print' => [
				[
					'link',
					[
						'id' => 'wp-block-library-css',
						'media' => 'print',
					],
				],
				120,
			],
			'Plugin asset for print' => [
				[
					'link',
					[
						'href' => plugins_url() . '/some-plugin/style.css',
						'media' => 'print',
					],
				],
				130,
			],
			'Query monitor plugin asset for print' => [
				[
					'link',
					[
						'href' => plugins_url() . '/query-monitor/style.css',
						'media' => 'print',
					],
				],
				250,
			],
			'Other styles from wp-includes for print' => [
				[
					'link',
					[
						'href' => includes_url(),
						'media' => 'print',
					],
				],
				140,
			],
			'All other links for print' => [
				[
					'link',
					[
						'id' => 'something-else',
						'media' => 'print',
					],
				],
				150,
			],
			'Parent theme inline styles for print' => [
				[
					'style',
					[
						'id' => 'parent-theme-inline-css',
						'media' => 'print',
					],
				],
				102,
			],
			'Child theme inline styles for print' => [
				[
					'style',
					[
						'id' => 'child-theme-inline-css',
						'media' => 'print',
					],
				],
				112,
			],
			'Admin bar inline styles for print' => [
				[
					'style',
					[
						'id' => 'admin-bar-inline-css',
						'media' => 'print',
					],
				],
				300,
			],
			'Customizer inline styles for print' => [
				[
					'style',
					[
						'id' => 'wp-custom-css',
						'media' => 'print',
					],
				],
				160,
			],
			'Other inline styles for print' => [
				[
					'style',
					[
						'id' => 'something-else',
						'media' => 'print',
					],
				],
				170,
			],
			'Style attribute' => [ [ null, [ 'something' ] ], 70 ],
		];
	}

	/**
	 * Test retrieval of stylesheet priorities.
	 *
	 * @covers       \AMP_Style_Sanitizer::get_stylesheet_priority()
	 *
	 * @dataProvider get_stylesheet_priority_data
	 *
	 * @param array $node_data Node to check the priority of.
	 * @param int   $expected  Expected priority.
	 */
	public function test_get_stylesheet_priority( $node_data, $expected ) {
		global $wp_styles;
		$wp_styles = new WP_Styles();

		$parent_theme_filter = static function () {
			return 'parent-theme';
		};
		$child_theme_filter  = static function () {
			return 'child-theme';
		};

		$dom = new Document();

		if ( isset( $node_data[0] ) ) {
			$node = AMP_DOM_Utils::create_node( $dom, $node_data[0], $node_data[1] );
		} else {
			$node = $dom->createAttribute( $node_data[1][0] );
		}

		$sanitizer = new AMP_Style_Sanitizer( $dom );

		add_filter( 'template', $parent_theme_filter );
		add_filter( 'stylesheet', $child_theme_filter );
		$wp_styles->add( 'parent-theme', get_template_directory_uri() . '/style.css' );
		$wp_styles->add( 'child-theme', get_stylesheet_directory_uri() . '/style.css' );
		$this->assertEquals(
			$expected,
			$this->call_private_method( $sanitizer, 'get_stylesheet_priority', [ $node ] ),
			'Node data: ' . wp_json_encode( $node_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
		);
		remove_filter( 'stylesheet', $child_theme_filter );
		remove_filter( 'template', $parent_theme_filter );
	}

	/**
	 * Test get_css_parser_validation_error_codes.
	 *
	 * @covers AMP_Style_Sanitizer::get_css_parser_validation_error_codes()
	 */
	public function test_get_css_parser_validation_error_codes() {
		$expected = [
			AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_AT_RULE,
			AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_DECLARATION,
			AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_IMPORTANT,
			AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_PROPERTY,
			AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_PROPERTY_NOLIST,
			AMP_Style_Sanitizer::CSS_SYNTAX_PARSE_ERROR,
			AMP_Style_Sanitizer::STYLESHEET_FETCH_ERROR,
			AMP_Style_Sanitizer::STYLESHEET_TOO_LONG,
		];

		$this->assertEquals( $expected, AMP_Style_Sanitizer::get_css_parser_validation_error_codes() );
	}

	/**
	 * Test get_styles.
	 *
	 * @covers AMP_Style_Sanitizer::get_styles()
	 */
	public function test_get_styles() {
		$sanitizer = new AMP_Style_Sanitizer( new Document() );
		$this->assertEquals( [], $sanitizer->get_styles() );
	}
}

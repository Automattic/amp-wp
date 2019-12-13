<?php
/**
 * Class AMP_Style_Sanitizer
 *
 * @package AMP
 */

use \Sabberworm\CSS\RuleSet\DeclarationBlock;
use \Sabberworm\CSS\CSSList\CSSList;
use \Sabberworm\CSS\Property\Selector;
use \Sabberworm\CSS\RuleSet\RuleSet;
use \Sabberworm\CSS\Property\AtRule;
use \Sabberworm\CSS\Rule\Rule;
use \Sabberworm\CSS\CSSList\KeyFrame;
use \Sabberworm\CSS\RuleSet\AtRuleSet;
use \Sabberworm\CSS\Property\Import;
use \Sabberworm\CSS\CSSList\AtRuleBlockList;
use \Sabberworm\CSS\Value\RuleValueList;
use \Sabberworm\CSS\Value\URL;
use \Sabberworm\CSS\CSSList\Document;

/**
 * Class AMP_Style_Sanitizer
 *
 * Collects inline styles and outputs them in the amp-custom stylesheet.
 */
class AMP_Style_Sanitizer extends AMP_Base_Sanitizer {

	const STYLE_AMP_CUSTOM_SPEC_NAME    = 'style amp-custom';
	const STYLE_AMP_KEYFRAMES_SPEC_NAME = 'style[amp-keyframes]';

	const STYLE_AMP_CUSTOM_GROUP_INDEX    = 0;
	const STYLE_AMP_KEYFRAMES_GROUP_INDEX = 1;

	const CSS_SYNTAX_INVALID_AT_RULE         = 'CSS_SYNTAX_INVALID_AT_RULE';
	const CSS_SYNTAX_INVALID_DECLARATION     = 'CSS_SYNTAX_INVALID_DECLARATION';
	const CSS_SYNTAX_INVALID_PROPERTY        = 'CSS_SYNTAX_INVALID_PROPERTY';
	const CSS_SYNTAX_INVALID_PROPERTY_NOLIST = 'CSS_SYNTAX_INVALID_PROPERTY_NOLIST';
	const CSS_SYNTAX_INVALID_IMPORTANT       = 'CSS_SYNTAX_INVALID_IMPORTANT';
	const CSS_SYNTAX_PARSE_ERROR             = 'CSS_SYNTAX_PARSE_ERROR';
	const STYLESHEET_TOO_LONG                = 'STYLESHEET_TOO_LONG';
	const STYLESHEET_INVALID_FILE_URL        = 'STYLESHEET_INVALID_FILE_URL';

	// These are internal to the sanitizer and not exposed as validation error codes.
	const STYLESHEET_DISALLOWED_FILE_EXT   = 'STYLESHEET_DISALLOWED_FILE_EXT';
	const STYLESHEET_EXTERNAL_FILE_URL     = 'STYLESHEET_EXTERNAL_FILE_URL';
	const STYLESHEET_FILE_PATH_NOT_FOUND   = 'STYLESHEET_FILE_PATH_NOT_FOUND';
	const STYLESHEET_FILE_PATH_NOT_ALLOWED = 'STYLESHEET_FILE_PATH_NOT_ALLOWED';
	const STYLESHEET_URL_SYNTAX_ERROR      = 'STYLESHEET_URL_SYNTAX_ERROR';
	const STYLESHEET_INVALID_RELATIVE_PATH = 'STYLESHEET_INVALID_RELATIVE_PATH';

	/**
	 * Inline style selector's specificity multiplier, i.e. used to generate the number of ':not(#_)' placeholders.
	 *
	 * @var int
	 */
	const INLINE_SPECIFICITY_MULTIPLIER = 5; // @todo The correctness of using "5" should be validated.

	/**
	 * Array index for tag names extracted from a selector.
	 *
	 * @private
	 * @since 1.1
	 * @see \AMP_Style_Sanitizer::prepare_stylesheet()
	 */
	const SELECTOR_EXTRACTED_TAGS = 0;

	/**
	 * Array index for class names extracted from a selector.
	 *
	 * @private
	 * @since 1.1
	 * @see \AMP_Style_Sanitizer::prepare_stylesheet()
	 */
	const SELECTOR_EXTRACTED_CLASSES = 1;

	/**
	 * Array index for IDs extracted from a selector.
	 *
	 * @private
	 * @since 1.1
	 * @see \AMP_Style_Sanitizer::prepare_stylesheet()
	 */
	const SELECTOR_EXTRACTED_IDS = 2;

	/**
	 * Array index for attributes extracted from a selector.
	 *
	 * @private
	 * @since 1.1
	 * @see \AMP_Style_Sanitizer::prepare_stylesheet()
	 */
	const SELECTOR_EXTRACTED_ATTRIBUTES = 3;

	/**
	 * Array of flags used to control sanitization.
	 *
	 * @var array {
	 *      @type string[] $dynamic_element_selectors  Selectors for elements (or their ancestors) which contain dynamic content; selectors containing these will not be filtered.
	 *      @type bool     $use_document_element       Whether the root of the document should be used rather than the body.
	 *      @type bool     $require_https_src          Require HTTPS URLs.
	 *      @type bool     $allow_dirty_styles         Allow dirty styles. This short-circuits the sanitize logic; it is used primarily in Customizer preview.
	 *      @type callable $validation_error_callback  Function to call when a validation error is encountered.
	 *      @type bool     $should_locate_sources      Whether to locate the sources when reporting validation errors.
	 *      @type string   $parsed_cache_variant       Additional value by which to vary parsed cache.
	 *      @type string   $include_manifest_comment   Whether to show the manifest HTML comment in the response before the style[amp-custom] element. Can be 'always', 'never', or 'when_excessive'.
	 *      @type string[] $focus_within_classes       Class names in selectors that should be replaced with :focus-within pseudo classes.
	 *      @type string[] $low_priority_plugins       Plugin slugs of the plugins to deprioritize when hitting the CSS limit.
	 * }
	 */
	protected $args;

	/**
	 * Default args.
	 *
	 * @var array
	 */
	protected $DEFAULT_ARGS = [
		'dynamic_element_selectors' => [
			'amp-list',
			'amp-live-list',
			'[submit-error]',
			'[submit-success]',
			'amp-script',
		],
		'should_locate_sources'     => false,
		'parsed_cache_variant'      => null,
		'include_manifest_comment'  => 'always',
		'focus_within_classes'      => [ 'focus' ],
		'low_priority_plugins'      => [ 'query-monitor' ],
	];

	/**
	 * List of stylesheet parts prior to selector/rule removal (tree shaking).
	 *
	 * Keys are MD5 hashes of stylesheets.
	 *
	 * @since 1.0
	 * @var array[] {
	 *     @type array              $stylesheet Array of stylesheet chunked, with declaration blocks being represented as arrays.
	 *     @type DOMElement|DOMAttr $node       Origin for styles.
	 *     @type array              $sources    Sources for the node.
	 *     @type bool               $keyframes  Whether an amp-keyframes.
	 * }
	 */
	private $pending_stylesheets = [];

	/**
	 * Spec for style[amp-custom] cdata.
	 *
	 * @since 1.0
	 * @var array
	 */
	private $style_custom_cdata_spec;

	/**
	 * The style[amp-custom] element.
	 *
	 * @var DOMElement
	 */
	private $amp_custom_style_element;

	/**
	 * Spec for style[amp-keyframes] cdata.
	 *
	 * @since 1.0
	 * @var array
	 */
	private $style_keyframes_cdata_spec;

	/**
	 * Regex for allowed font stylesheet URL.
	 *
	 * @var string
	 */
	private $allowed_font_src_regex;

	/**
	 * Base URL for styles.
	 *
	 * Full URL with trailing slash.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * URL of the content directory.
	 *
	 * @var string
	 */
	private $content_url;

	/**
	 * Class names used in document.
	 *
	 * This list includes all class names that AMP can dynamically add.
	 *
	 * @link https://www.ampproject.org/docs/reference/components/amp-dynamic-css-classes
	 * @since 1.0
	 * @var array
	 */
	private $used_class_names;

	/**
	 * Regular expression pattern to match focus class names in selectors.
	 *
	 * The computed pattern is cached to prevent re-constructing for each processed selector.
	 *
	 * @var string|null
	 */
	private $focus_class_name_selector_pattern;

	/**
	 * Attributes used in the document.
	 *
	 * This is initially populated with boolean attributes which can be mutated by AMP at runtime,
	 * since they can by dynamically added at any time.
	 *
	 * @todo With the exception of 'hidden' (which can be on any element), the values here could be removed in favor of
	 *       checking to see if any of the related elements exist in the page in `\AMP_Style_Sanitizer::has_used_attributes()`.
	 *       Nevertheless, selectors mentioning these attributes are very numerous, so tree-shaking improvements will be marginal.
	 *
	 * @see \AMP_Style_Sanitizer::has_used_attributes()
	 *
	 * @since 1.1
	 * @var array
	 */
	private $used_attributes = [
		'autofocus' => true,
		'checked'   => true,
		'controls'  => true,
		'disabled'  => true,
		'hidden'    => true,
		'loop'      => true,
		'multiple'  => true,
		'readonly'  => true,
		'required'  => true,
		'selected'  => true,
	];

	/**
	 * Tag names used in document.
	 *
	 * @since 1.0
	 * @var array
	 */
	private $used_tag_names;

	/**
	 * XPath.
	 *
	 * @since 1.0
	 * @var DOMXPath
	 */
	private $xpath;

	/**
	 * Amount of time that was spent parsing CSS.
	 *
	 * @since 1.0
	 * @var float
	 */
	private $parse_css_duration = 0.0;

	/**
	 * THe HEAD element.
	 *
	 * @var DOMElement
	 */
	private $head;

	/**
	 * Current node being processed.
	 *
	 * @var DOMElement|DOMAttr
	 */
	private $current_node;

	/**
	 * Current sources for a given node.
	 *
	 * @var array
	 */
	private $current_sources;

	/**
	 * Log of the stylesheet URLs that have been imported to guard against infinite loops.
	 *
	 * @var array
	 */
	private $processed_imported_stylesheet_urls = [];

	/**
	 * List of font stylesheets that were @import'ed which should have been <link>'ed to instead.
	 *
	 * These font URLs will be cached with the parsed CSS and then converted into stylesheet links.
	 *
	 * @var array
	 */
	private $imported_font_urls = [];

	/**
	 * Mapping of HTML element selectors to AMP selector elements.
	 *
	 * @var array
	 */
	private $selector_mappings = [];

	/**
	 * Elements in extensions which use the video-manager, and thus the video-autoplay.css.
	 *
	 * @var array
	 */
	private $video_autoplay_elements = [
		'amp-3q-player',
		'amp-brid-player',
		'amp-brightcove',
		'amp-dailymotion',
		'amp-delight-player',
		'amp-gfycat',
		'amp-ima-video',
		'amp-mowplayer',
		'amp-nexxtv-player',
		'amp-ooyala-player',
		'amp-powr-player',
		'amp-story-auto-ads',
		'amp-video',
		'amp-video-iframe',
		'amp-vimeo',
		'amp-viqeo-player',
		'amp-wistia-player',
		'amp-youtube',
	];

	/**
	 * Get error codes that can be raised during parsing of CSS.
	 *
	 * This is used to determine which validation errors should be taken into account
	 * when determining which validation errors should vary the parse cache.
	 *
	 * @return array
	 */
	public static function get_css_parser_validation_error_codes() {
		return [
			self::CSS_SYNTAX_INVALID_AT_RULE,
			self::CSS_SYNTAX_INVALID_DECLARATION,
			self::CSS_SYNTAX_INVALID_IMPORTANT,
			self::CSS_SYNTAX_INVALID_PROPERTY,
			self::CSS_SYNTAX_INVALID_PROPERTY_NOLIST,
			self::CSS_SYNTAX_PARSE_ERROR,
			self::STYLESHEET_INVALID_FILE_URL,
			self::STYLESHEET_TOO_LONG,
		];
	}

	/**
	 * Determine whether the version of PHP-CSS-Parser loaded has all required features for tree shaking and CSS processing.
	 *
	 * @since 1.0.2
	 *
	 * @return bool Returns true if the plugin's forked version of PHP-CSS-Parser is loaded by Composer.
	 */
	public static function has_required_php_css_parser() {
		$has_required_methods = (
			method_exists( 'Sabberworm\CSS\CSSList\Document', 'splice' )
			&&
			method_exists( 'Sabberworm\CSS\CSSList\Document', 'replace' )
		);
		if ( ! $has_required_methods ) {
			return false;
		}

		$reflection = new ReflectionClass( 'Sabberworm\CSS\OutputFormat' );

		$has_output_format_extensions = (
			$reflection->hasProperty( 'sBeforeAtRuleBlock' )
			&&
			$reflection->hasProperty( 'sAfterAtRuleBlock' )
			&&
			$reflection->hasProperty( 'sBeforeDeclarationBlock' )
			&&
			$reflection->hasProperty( 'sAfterDeclarationBlockSelectors' )
			&&
			$reflection->hasProperty( 'sAfterDeclarationBlock' )
		);
		if ( ! $has_output_format_extensions ) {
			return false;
		}

		return true;
	}

	/**
	 * AMP_Base_Sanitizer constructor.
	 *
	 * @since 0.7
	 *
	 * @param DOMDocument $dom  Represents the HTML document to sanitize.
	 * @param array       $args Args.
	 */
	public function __construct( DOMDocument $dom, array $args = [] ) {
		parent::__construct( $dom, $args );

		foreach ( AMP_Allowed_Tags_Generated::get_allowed_tag( 'style' ) as $spec_rule ) {
			if ( ! isset( $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) ) {
				continue;
			}
			if ( self::STYLE_AMP_KEYFRAMES_SPEC_NAME === $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) {
				$this->style_keyframes_cdata_spec = $spec_rule[ AMP_Rule_Spec::CDATA ];
			} elseif ( self::STYLE_AMP_CUSTOM_SPEC_NAME === $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) {
				$this->style_custom_cdata_spec = $spec_rule[ AMP_Rule_Spec::CDATA ];
			}
		}

		$spec_name = 'link rel=stylesheet for fonts'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		foreach ( AMP_Allowed_Tags_Generated::get_allowed_tag( 'link' ) as $spec_rule ) {
			if ( isset( $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) && $spec_name === $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) {
				$this->allowed_font_src_regex = '@^(' . $spec_rule[ AMP_Rule_Spec::ATTR_SPEC_LIST ]['href']['value_regex'] . ')$@';
				break;
			}
		}

		$guessurl = site_url();
		if ( ! $guessurl ) {
			$guessurl = wp_guess_url();
		}
		$this->base_url    = untrailingslashit( $guessurl );
		$this->content_url = WP_CONTENT_URL;
		$this->xpath       = new DOMXPath( $dom );
	}

	/**
	 * Get list of CSS styles in HTML content of DOMDocument ($this->dom).
	 *
	 * @since 0.4
	 * @deprecated As of 1.0, use get_stylesheets().
	 *
	 * @return array[] Mapping CSS selectors to array of properties, or mapping of keys starting with 'stylesheet:' with value being the stylesheet.
	 */
	public function get_styles() {
		return [];
	}

	/**
	 * Get stylesheets for amp-custom.
	 *
	 * @since 0.7
	 * @return array Values are the CSS stylesheets.
	 */
	public function get_stylesheets() {
		return wp_list_pluck(
			array_filter(
				$this->pending_stylesheets,
				static function( $pending_stylesheet ) {
					return $pending_stylesheet['included'] && self::STYLE_AMP_CUSTOM_GROUP_INDEX === $pending_stylesheet['group'];
				}
			),
			'stylesheet'
		);
	}

	/**
	 * Get list of all the class names used in the document, including those used in [class] attributes.
	 *
	 * @since 1.0
	 * @return array Used class names.
	 */
	private function get_used_class_names() {
		if ( isset( $this->used_class_names ) ) {
			return $this->used_class_names;
		}

		$dynamic_class_names = [

			/*
			 * See <https://www.ampproject.org/docs/reference/components/amp-dynamic-css-classes>.
			 * Note that amp-referrer-* class names are handled in has_used_class_name() below.
			 */
			'amp-viewer',

			// Classes added based on input mode. See <https://github.com/ampproject/amphtml/blob/master/spec/amp-css-classes.md#input-mode-classes>.
			'amp-mode-touch',
			'amp-mode-mouse',
			'amp-mode-keyboard-active',
		];

		$classes = ' ';
		foreach ( $this->xpath->query( '//*/@class' ) as $class_attribute ) {
			$classes .= ' ' . $class_attribute->nodeValue;
		}

		// Find all [class] attributes and capture the contents of any single- or double-quoted strings.
		foreach ( $this->xpath->query( '//*/@' . AMP_DOM_Utils::AMP_BIND_DATA_ATTR_PREFIX . 'class' ) as $bound_class_attribute ) {
			if ( preg_match_all( '/([\'"])([^\1]*?)\1/', $bound_class_attribute->nodeValue, $matches ) ) {
				$classes .= ' ' . implode( ' ', $matches[2] );
			}
		}

		$class_names = array_merge(
			$dynamic_class_names,
			array_unique( array_filter( preg_split( '/\s+/', trim( $classes ) ) ) )
		);

		// Find all instances of the toggleClass() action to prevent the class name from being tree-shaken.
		foreach ( $this->xpath->query( '//*/@on[ contains( ., "toggleClass" ) ]' ) as $on_attribute ) {
			if ( preg_match_all( '/\.\s*toggleClass\s*\(\s*class\s*=\s*(([\'"])([^\1]*?)\2|[a-zA-Z0-9_\-]+)/', $on_attribute->nodeValue, $matches ) ) {
				$class_names = array_merge(
					$class_names,
					array_map(
						static function ( $match ) {
							return trim( $match, '"\'' );
						},
						$matches[1]
					)
				);
			}
		}

		$this->used_class_names = array_fill_keys( $class_names, true );
		return $this->used_class_names;
	}

	/**
	 * Determine if all the supplied class names are used.
	 *
	 * @since 1.1
	 *
	 * @param string[] $class_names Class names.
	 * @return bool All used.
	 */
	private function has_used_class_name( $class_names ) {
		if ( empty( $this->used_class_names ) ) {
			$this->get_used_class_names();
		}

		foreach ( $class_names as $class_name ) {
			// Bail early with a common case scenario.
			if ( isset( $this->used_class_names[ $class_name ] ) ) {
				continue;
			}

			// Check exact matches first, as they are faster.
			switch ( $class_name ) {
				/*
				 * Common class names used for amp-user-notification and amp-live-list.
				 * See <https://www.ampproject.org/docs/reference/components/amp-user-notification#styling>.
				 * See <https://www.ampproject.org/docs/reference/components/amp-live-list#styling>.
				 */
				case 'amp-active':
				case 'amp-hidden':
					if ( ! $this->has_used_tag_names( [ 'amp-live-list', 'amp-user-notification' ] ) ) {
						return false;
					}
					continue 2;
				// Class names for amp-image-lightbox, see <https://www.ampproject.org/docs/reference/components/amp-image-lightbox#styling>.
				case 'amp-image-lightbox-caption':
					if ( ! $this->has_used_tag_names( [ 'amp-image-lightbox' ] ) ) {
						return false;
					}
					continue 2;
				// Class names for amp-form, see <https://www.ampproject.org/docs/reference/components/amp-form#classes-and-css-hooks>.
				case 'user-valid':
				case 'user-invalid':
					if ( ! $this->has_used_tag_names( [ 'form' ] ) ) {
						return false;
					}
					continue 2;
			}

			// Only do AMP element-specific checks on an AMP components with the corresponding prefix.
			if ( 'amp-' === substr( $class_name, 0, 4 ) ) {

				// Class names for amp-geo, see <https://www.ampproject.org/docs/reference/components/amp-geo#generated-css-classes>.
				if ( 'amp-geo-' === substr( $class_name, 0, 8 ) ) {
					if ( ! $this->has_used_tag_names( [ 'amp-geo' ] ) ) {
						return false;
					}
					continue;
				}

				// Class names for amp-form, see <https://www.ampproject.org/docs/reference/components/amp-form#classes-and-css-hooks>.
				if ( 'amp-form-' === substr( $class_name, 0, 9 ) ) {
					if ( ! $this->has_used_tag_names( [ 'form' ] ) ) {
						return false;
					}
					continue;
				}

				// Class names for extensions which use the video-manager, and thus video-autoplay.css.
				if ( 'amp-video-' === substr( $class_name, 0, 10 ) ) {
					foreach ( $this->video_autoplay_elements as $video_autoplay_element ) {
						if ( $this->has_used_tag_names( [ $video_autoplay_element ] ) ) {
							continue 2;
						}
					}
					return false;
				}

				switch ( substr( $class_name, 0, 11 ) ) {
					/*
					 * Class names for amp-access and amp-access-laterpay.
					 * See <https://www.ampproject.org/docs/reference/components/amp-access>.
					 * See <https://www.ampproject.org/docs/reference/components/amp-access-laterpay#styling>
					 */
					case 'amp-access-':
						if ( ! $this->has_used_attributes( [ 'amp-access' ] ) ) {
							return false;
						}
						continue 2;
					// Class names for amp-video-docking, see <https://github.com/ampproject/amphtml/blob/master/extensions/amp-video-docking/amp-video-docking.md#styling>.
					case 'amp-docked-':
						if ( ! $this->has_used_attributes( [ 'dock' ] ) ) {
							return false;
						}
						continue 2;
				}

				// Class names for amp-sidebar, see <https://www.ampproject.org/docs/reference/components/amp-sidebar#styling-toolbar>.
				if ( 'amp-sidebar-' === substr( $class_name, 0, 12 ) ) {
					if ( ! $this->has_used_tag_names( [ 'amp-sidebar' ] ) ) {
						return false;
					}
					continue;
				}

				switch ( substr( $class_name, 0, 13 ) ) {
					// Class names for amp-dynamic-css-classes, see <https://www.ampproject.org/docs/reference/components/amp-dynamic-css-classes>.
					case 'amp-referrer-':
						continue 2;
					// Class names for amp-carousel, see <https://www.ampproject.org/docs/reference/components/amp-carousel#styling>.
					case 'amp-carousel-':
						if ( ! $this->has_used_tag_names( [ 'amp-carousel' ] ) ) {
							return false;
						}
						continue 2;
				}

				switch ( substr( $class_name, 0, 14 ) ) {
					// Class names for amp-sticky-ad, see <https://www.ampproject.org/docs/reference/components/amp-sticky-ad#styling>.
					case 'amp-sticky-ad-':
						if ( ! $this->has_used_tag_names( [ 'amp-sticky-ad' ] ) ) {
							return false;
						}
						continue 2;
					// Class names for amp-live-list, see <https://www.ampproject.org/docs/reference/components/amp-live-list#styling>.
					case 'amp-live-list-':
						if ( ! $this->has_used_tag_names( [ 'amp-live-list' ] ) ) {
							return false;
						}
						continue 2;
				}

				switch ( substr( $class_name, 0, 16 ) ) {
					// Class names for amp-date-picker, see <https://www.ampproject.org/docs/reference/components/amp-date-picker>.
					case 'amp-date-picker-':
						if ( ! $this->has_used_tag_names( [ 'amp-date-picker' ] ) ) {
							return false;
						}
						continue 2;
					// Class names for amp-geo, see <https://www.ampproject.org/docs/reference/components/amp-geo#generated-css-classes>.
					case 'amp-iso-country-':
						if ( ! $this->has_used_tag_names( [ 'amp-geo' ] ) ) {
							return false;
						}
						continue 2;
				}
			}

			if ( ! isset( $this->used_class_names[ $class_name ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get list of all the tag names used in the document.
	 *
	 * @since 1.0
	 * @return array Used tag names.
	 */
	private function get_used_tag_names() {
		if ( ! isset( $this->used_tag_names ) ) {
			$this->used_tag_names = [];
			foreach ( $this->dom->getElementsByTagName( '*' ) as $el ) {
				$this->used_tag_names[ $el->tagName ] = true;
			}
		}
		return $this->used_tag_names;
	}

	/**
	 * Determine if all the supplied tag names are used.
	 *
	 * @since 1.1
	 *
	 * @param string[] $tag_names Tag names.
	 * @return bool All used.
	 */
	private function has_used_tag_names( $tag_names ) {
		if ( empty( $this->used_tag_names ) ) {
			$this->get_used_tag_names();
		}
		foreach ( $tag_names as $tag_name ) {
			if ( ! isset( $this->used_tag_names[ $tag_name ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check whether the attributes exist.
	 *
	 * @since 1.1
	 * @todo Make $attribute_names into $attributes as an associative array and implement lookups of specific values. Since attribute values can vary (e.g. with amp-bind), this may not be feasible.
	 *
	 * @param string[] $attribute_names Attribute names.
	 * @return bool Whether all supplied attributes are used.
	 */
	private function has_used_attributes( $attribute_names ) {
		foreach ( $attribute_names as $attribute_name ) {
			if ( ! isset( $this->used_attributes[ $attribute_name ] ) ) {
				$expression = sprintf( '(//@%s)[1]', $attribute_name );

				$this->used_attributes[ $attribute_name ] = ( 0 !== $this->xpath->query( $expression )->length );
			}

			// Attributes for amp-accordion, see <https://amp.dev/documentation/components/amp-accordion/#styling>.
			if ( 'expanded' === $attribute_name ) {
				if ( ! $this->has_used_tag_names( [ 'amp-accordion' ] ) ) {
					return false;
				}
				continue;
			}

			// Attributes for amp-sidebar, see <https://amp.dev/documentation/components/amp-sidebar/#styling>.
			if ( 'open' === $attribute_name ) {
				// The 'open' attribute is also used by the HTML5 <details> attribute.
				if ( ! $this->has_used_tag_names( [ 'amp-sidebar' ] ) && ! $this->has_used_tag_names( [ 'details' ] ) ) {
					return false;
				}
				continue;
			}

			// Attributes for amp-live-list, see <https://amp.dev/documentation/components/amp-live-list/#styling>.
			if ( 'data-tombstone' === $attribute_name ) {
				if ( ! $this->has_used_tag_names( [ 'amp-live-list' ] ) ) {
					return false;
				}
				continue;
			}

			if ( ! $this->used_attributes[ $attribute_name ] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Run logic before any sanitizers are run.
	 *
	 * After the sanitizers are instantiated but before calling sanitize on each of them, this
	 * method is called with list of all the instantiated sanitizers.
	 *
	 * @param AMP_Base_Sanitizer[] $sanitizers Sanitizers.
	 */
	public function init( $sanitizers ) {
		parent::init( $sanitizers );

		foreach ( $sanitizers as $sanitizer ) {
			foreach ( $sanitizer->get_selector_conversion_mapping() as $html_selectors => $amp_selectors ) {
				if ( ! isset( $this->selector_mappings[ $html_selectors ] ) ) {
					$this->selector_mappings[ $html_selectors ] = $amp_selectors;
				} else {
					$this->selector_mappings[ $html_selectors ] = array_unique(
						array_merge( $this->selector_mappings[ $html_selectors ], $amp_selectors )
					);
				}

				// Prevent selectors like `amp-img img` getting deleted since `img` does not occur in the DOM.
				$this->args['dynamic_element_selectors'] = array_merge(
					$this->args['dynamic_element_selectors'],
					$this->selector_mappings[ $html_selectors ]
				);
			}
		}
	}

	/**
	 * Sanitize CSS styles within the HTML contained in this instance's DOMDocument.
	 *
	 * @since 0.4
	 */
	public function sanitize() {
		$elements = [];

		// @todo Instead of short-circuiting, this actually needs to turn off tree-shaking.
		// Do nothing if inline styles are allowed. Note, a better alternative to this is AMP dev mode.
		if ( ! empty( $this->args['allow_dirty_styles'] ) ) {
			return;
		}

		$this->focus_class_name_selector_pattern = (
			! empty( $this->args['focus_within_classes'] ) ?
				self::get_class_name_selector_pattern( $this->args['focus_within_classes'] ) :
				null
		);

		$this->head = $this->dom->getElementsByTagName( 'head' )->item( 0 );
		if ( ! $this->head ) {
			$this->head = $this->dom->createElement( 'head' );
			$this->dom->documentElement->insertBefore( $this->head, $this->dom->documentElement->firstChild );
		}

		$this->parse_css_duration = 0.0;

		/*
		 * Note that xpath is used to query the DOM so that the link and style elements will be
		 * in document order. DOMNode::compareDocumentPosition() is not yet implemented.
		 */
		$xpath = $this->xpath;

		$dev_mode_predicate = '';
		if ( $this->is_document_in_dev_mode() ) {
			$dev_mode_predicate = sprintf( ' and not ( @%s )', AMP_Rule_Spec::DEV_MODE_ATTRIBUTE );
		}

		$lower_case = 'translate( %s, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz" )'; // In XPath 2.0 this is lower-case().
		$predicates = [
			sprintf( '( self::style and not( @amp-boilerplate ) and ( not( @type ) or %s = "text/css" ) %s )', sprintf( $lower_case, '@type' ), $dev_mode_predicate ),
			sprintf( '( self::link and @href and %s = "stylesheet" %s )', sprintf( $lower_case, '@rel' ), $dev_mode_predicate ),
		];

		foreach ( $xpath->query( '//*[ ' . implode( ' or ', $predicates ) . ' ]' ) as $element ) {
			$elements[] = $element;
		}

		// If 'width' attribute is present for 'col' tag, convert to proper CSS rule.
		foreach ( $this->dom->getElementsByTagName( 'col' ) as $col ) {
			/**
			 * Col element.
			 *
			 * @var DOMElement $col
			 */
			$width_attr = $col->getAttribute( 'width' );
			if ( ! empty( $width_attr ) && ( false === strpos( $width_attr, '*' ) ) ) {
				$width_style = 'width: ' . $width_attr;
				if ( is_numeric( $width_attr ) ) {
					$width_style .= 'px';
				}
				if ( $col->hasAttribute( 'style' ) ) {
					$col->setAttribute( 'style', $width_style . ';' . $col->getAttribute( 'style' ) );
				} else {
					$col->setAttribute( 'style', $width_style );
				}
				$col->removeAttribute( 'width' );
			}
		}

		/**
		 * Element.
		 *
		 * @var DOMElement $element
		 */
		foreach ( $elements as $element ) {
			$node_name = strtolower( $element->nodeName );
			if ( 'style' === $node_name ) {
				$this->process_style_element( $element );
			} elseif ( 'link' === $node_name ) {
				$this->process_link_element( $element );

				// If the element is still in the document, it is a font stylesheet; make sure it gets moved to the head as required.
				if ( $element->parentNode && 'head' !== $element->parentNode->nodeName ) {
					$this->head->appendChild( $element->parentNode->removeChild( $element ) );
				}
			}
		}

		$elements = [];
		foreach ( $xpath->query( "//*[ @style $dev_mode_predicate ]" ) as $element ) {
			$elements[] = $element;
		}
		foreach ( $elements as $element ) {
			$this->collect_inline_styles( $element );
		}

		$this->finalize_styles();

		$this->did_convert_elements = true;

		if ( $this->parse_css_duration > 0.0 ) {
			AMP_HTTP::send_server_timing( 'amp_parse_css', $this->parse_css_duration, 'AMP Parse CSS' );
		}
	}

	/**
	 * Get the priority of the stylesheet associated with the given element.
	 *
	 * As with hooks, lower priorities mean they should be included first.
	 * The higher the priority value, the more likely it will be that the
	 * stylesheet will be among those excluded due to STYLESHEET_TOO_LONG when
	 * concatenated CSS reaches 50KB.
	 *
	 * @todo This will eventually need to be abstracted to not be CMS-specific, allowing for the prioritization scheme to be defined by configuration.
	 *
	 * @param DOMNode|DOMElement|DOMAttr $node Node.
	 * @return int Priority.
	 */
	private function get_stylesheet_priority( DOMNode $node ) {
		$print_priority_base = 100;
		$admin_bar_priority  = 200;

		$remove_url_scheme = static function( $url ) {
			return preg_replace( '/^https?:/', '', $url );
		};

		if ( $node instanceof DOMElement && 'link' === $node->nodeName ) {
			$element_id      = (string) $node->getAttribute( 'id' );
			$schemeless_href = $remove_url_scheme( $node->getAttribute( 'href' ) );

			$plugin = null;
			if ( preg_match(
				sprintf(
					'#^(?:%s|%s)(?<plugin>[^/]+)#i',
					preg_quote( $remove_url_scheme( trailingslashit( WP_PLUGIN_URL ) ), '#' ),
					preg_quote( $remove_url_scheme( trailingslashit( WPMU_PLUGIN_URL ) ), '#' )
				),
				$schemeless_href,
				$matches
			) ) {
				$plugin = $matches['plugin'];
			}

			$style_handle = null;
			if ( preg_match( '/^(.+)-css$/', $element_id, $matches ) ) {
				$style_handle = $matches[1];
			}

			$core_frontend_handles = [
				'wp-block-library',
				'wp-block-library-theme',
			];
			$non_amp_handles       = [
				'mediaelement',
				'wp-mediaelement',
				'thickbox',
			];

			if ( in_array( $style_handle, $non_amp_handles, true ) ) {
				// Styles are for non-AMP JS only so not be used in AMP at all.
				$priority = 1000;
			} elseif ( 'admin-bar' === $style_handle ) {
				// Admin bar has lowest priority. If it gets excluded, then the entire admin bar should be removed.
				$priority = $admin_bar_priority;
			} elseif ( 'dashicons' === $style_handle ) {
				// Dashicons could be used by the theme, but low priority compared to other styles.
				$priority = 90;
			} elseif ( false !== strpos( $schemeless_href, $remove_url_scheme( trailingslashit( get_template_directory_uri() ) ) ) ) {
				// Highest priority are parent theme styles.
				$priority = 1;
			} elseif ( false !== strpos( $schemeless_href, $remove_url_scheme( trailingslashit( get_stylesheet_directory_uri() ) ) ) ) {
				// Penultimate highest priority are child theme styles.
				$priority = 10;
			} elseif ( in_array( $style_handle, $core_frontend_handles, true ) ) {
				// Styles from wp-includes which are enqueued for themes are next highest priority.
				$priority = 20;
			} elseif ( $plugin ) {
				// Styles from plugins are next-highest priority, unless they are in the list of low-priority plugins.
				$priority = in_array( $plugin, $this->args['low_priority_plugins'], true ) ? 150 : 30;
			} elseif ( 0 === strpos( $schemeless_href, $remove_url_scheme( includes_url() ) ) ) {
				// Other styles from wp-includes come next.
				$priority = 40;
			} else {
				// Everything else, perhaps wp-admin styles or stylesheets from remote servers.
				$priority = 50;
			}

			if ( 'print' === $node->getAttribute( 'media' ) ) {
				$priority += $print_priority_base;
			}
		} elseif ( $node instanceof DOMElement && 'style' === $node->nodeName && $node->hasAttribute( 'id' ) ) {
			$id                  = $node->getAttribute( 'id' );
			$is_theme_inline_css = preg_match( '/^(?<handle>.+)-inline-css$/', $id, $matches ) && wp_style_is( $matches['handle'], 'registered' );
			if ( $is_theme_inline_css && 0 === strpos( wp_styles()->registered[ $matches['handle'] ]->src, get_template_directory_uri() ) ) {
				// Parent theme inline style.
				$priority = 2;
			} elseif ( $is_theme_inline_css && get_stylesheet() !== get_template() && 0 === strpos( wp_styles()->registered[ $matches['handle'] ]->src, get_stylesheet_directory_uri() ) ) {
				// Child theme inline style.
				$priority = 12;
			} elseif ( 'admin-bar-inline-css' === $id ) {
				$priority = $admin_bar_priority;
			} elseif ( 'wp-custom-css' === $id ) {
				// Additional CSS from Customizer.
				$priority = 60;
			} else {
				// Other style elements, including from Recent Comments widget.
				$priority = 70;
			}

			if ( 'print' === $node->getAttribute( 'media' ) ) {
				$priority += $print_priority_base;
			}
		} else {
			// Style attribute.
			$priority = 70;
		}

		return $priority;
	}

	/**
	 * Eliminate relative segments (../ and ./) from a path.
	 *
	 * @param string $path Path with relative segments. This is not a URL, so no host and no query string.
	 * @return string|WP_Error Unrelativized path or WP_Error if there is too much relativity.
	 */
	private function unrelativize_path( $path ) {
		// Eliminate current directory relative paths, like <foo/./bar/./baz.css> => <foo/bar/baz.css>.
		do {
			$path = preg_replace(
				'#/\./#',
				'/',
				$path,
				-1,
				$count
			);
		} while ( 0 !== $count );

		// Collapse relative paths, like <foo/bar/../../baz.css> => <baz.css>.
		do {
			$path = preg_replace(
				'#(?<=/)(?!\.\./)[^/]+/\.\./#',
				'',
				$path,
				1,
				$count
			);
		} while ( 0 !== $count );

		if ( preg_match( '#(^|/)\.+/#', $path ) ) {
			return new WP_Error( self::STYLESHEET_INVALID_RELATIVE_PATH );
		}

		return $path;
	}

	/**
	 * Construct a URL from a parsed one.
	 *
	 * @param array $parsed_url Parsed URL.
	 * @return string Reconstructed URL.
	 */
	private function reconstruct_url( $parsed_url ) {
		$url = '';
		if ( ! empty( $parsed_url['host'] ) ) {
			if ( ! empty( $parsed_url['scheme'] ) ) {
				$url .= $parsed_url['scheme'] . ':';
			}
			$url .= '//';
			$url .= $parsed_url['host'];

			if ( ! empty( $parsed_url['port'] ) ) {
				$url .= ':' . $parsed_url['port'];
			}
		}
		if ( ! empty( $parsed_url['path'] ) ) {
			$url .= $parsed_url['path'];
		}
		if ( ! empty( $parsed_url['query'] ) ) {
			$url .= '?' . $parsed_url['query'];
		}
		if ( ! empty( $parsed_url['fragment'] ) ) {
			$url .= '#' . $parsed_url['fragment'];
		}
		return $url;
	}

	/**
	 * Generate a URL's fully-qualified file path.
	 *
	 * @since 0.7
	 * @see WP_Styles::_css_href()
	 *
	 * @param string   $url The file URL.
	 * @param string[] $allowed_extensions Allowed file extensions for local files.
	 * @return string|WP_Error Style's absolute validated filesystem path, or WP_Error when error.
	 */
	public function get_validated_url_file_path( $url, $allowed_extensions = [] ) {
		if ( ! is_string( $url ) ) {
			return new WP_Error( self::STYLESHEET_URL_SYNTAX_ERROR );
		}

		$needs_base_url = (
			! preg_match( '|^(https?:)?//|', $url )
			&&
			! ( $this->content_url && 0 === strpos( $url, $this->content_url ) )
		);
		if ( $needs_base_url ) {
			$url = $this->base_url . '/' . ltrim( $url, '/' );
		}

		$parsed_url = wp_parse_url( $url );
		if ( empty( $parsed_url['host'] ) ) {
			return new WP_Error( self::STYLESHEET_URL_SYNTAX_ERROR );
		}
		if ( empty( $parsed_url['path'] ) ) {
			return new WP_Error( self::STYLESHEET_URL_SYNTAX_ERROR );
		}

		$path = $this->unrelativize_path( $parsed_url['path'] );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		$parsed_url['path'] = $path;

		$remove_url_scheme = static function( $schemed_url ) {
			return preg_replace( '#^\w+:(?=//)#', '', $schemed_url );
		};

		unset( $parsed_url['scheme'], $parsed_url['query'], $parsed_url['fragment'] );
		$url = $this->reconstruct_url( $parsed_url );

		$includes_url = $remove_url_scheme( includes_url( '/' ) );
		$content_url  = $remove_url_scheme( content_url( '/' ) );
		$admin_url    = $remove_url_scheme( get_admin_url( null, '/' ) );
		$site_url     = $remove_url_scheme( site_url( '/' ) );

		$allowed_hosts = [
			wp_parse_url( $includes_url, PHP_URL_HOST ),
			wp_parse_url( $content_url, PHP_URL_HOST ),
			wp_parse_url( $admin_url, PHP_URL_HOST ),
		];

		// Validate file extensions.
		if ( ! empty( $allowed_extensions ) ) {
			$pattern = sprintf( '/\.(%s)$/i', implode( '|', $allowed_extensions ) );
			if ( ! preg_match( $pattern, $url ) ) {
				/* translators: %s: the file URL. */
				return new WP_Error( self::STYLESHEET_DISALLOWED_FILE_EXT );
			}
		}

		if ( ! in_array( $parsed_url['host'], $allowed_hosts, true ) ) {
			/* translators: %s: the file URL */
			return new WP_Error( self::STYLESHEET_EXTERNAL_FILE_URL );
		}

		$base_path  = null;
		$file_path  = null;
		$wp_content = 'wp-content';
		if ( 0 === strpos( $url, $content_url ) ) {
			$base_path = WP_CONTENT_DIR;
			$file_path = substr( $url, strlen( $content_url ) - 1 );
		} elseif ( 0 === strpos( $url, $includes_url ) ) {
			$base_path = ABSPATH . WPINC;
			$file_path = substr( $url, strlen( $includes_url ) - 1 );
		} elseif ( 0 === strpos( $url, $admin_url ) ) {
			$base_path = ABSPATH . 'wp-admin';
			$file_path = substr( $url, strlen( $admin_url ) - 1 );
		} elseif ( 0 === strpos( $url, $site_url . trailingslashit( $wp_content ) ) ) {
			// Account for loading content from original wp-content directory not WP_CONTENT_DIR which can happen via register_theme_directory().
			$base_path = ABSPATH . $wp_content;
			$file_path = substr( $url, strlen( $site_url ) + strlen( $wp_content ) );
		}

		if ( ! $file_path || false !== strpos( $file_path, '../' ) || false !== strpos( $file_path, '..\\' ) ) {
			return new WP_Error( self::STYLESHEET_FILE_PATH_NOT_ALLOWED );
		}
		if ( ! file_exists( $base_path . $file_path ) ) {
			return new WP_Error( self::STYLESHEET_FILE_PATH_NOT_FOUND );
		}

		return $base_path . $file_path;
	}

	/**
	 * Set the current node (and its sources when required).
	 *
	 * @since 1.0
	 * @param DOMElement|DOMAttr|null $node Current node, or null to reset.
	 */
	private function set_current_node( $node ) {
		if ( $this->current_node === $node ) {
			return;
		}

		$this->current_node = $node;
		if ( empty( $node ) ) {
			$this->current_sources = null;
		} elseif ( ! empty( $this->args['should_locate_sources'] ) ) {
			$this->current_sources = AMP_Validation_Manager::locate_sources( $node );
		}
	}

	/**
	 * Process style element.
	 *
	 * @param DOMElement $element Style element.
	 */
	private function process_style_element( DOMElement $element ) {
		$this->set_current_node( $element ); // And sources when needing to be located.

		// @todo Any @keyframes rules could be removed from amp-custom and instead added to amp-keyframes.
		$is_keyframes = $element->hasAttribute( 'amp-keyframes' );
		$stylesheet   = trim( $element->textContent );
		$cdata_spec   = $is_keyframes ? $this->style_keyframes_cdata_spec : $this->style_custom_cdata_spec;

		// Honor the style's media attribute.
		$media = $element->getAttribute( 'media' );
		if ( $media && 'all' !== $media ) {
			$stylesheet = sprintf( '@media %s { %s }', $media, $stylesheet );
		}

		$processed = $this->process_stylesheet(
			$stylesheet,
			[
				'allowed_at_rules'   => $cdata_spec['css_spec']['allowed_at_rules'],
				'property_whitelist' => $cdata_spec['css_spec']['declaration'],
				'validate_keyframes' => $cdata_spec['css_spec']['validate_keyframes'],
				'spec_name'          => $is_keyframes ? self::STYLE_AMP_KEYFRAMES_SPEC_NAME : self::STYLE_AMP_CUSTOM_SPEC_NAME,
			]
		);

		$this->pending_stylesheets[] = array_merge(
			[
				'group'    => $is_keyframes ? self::STYLE_AMP_KEYFRAMES_GROUP_INDEX : self::STYLE_AMP_CUSTOM_GROUP_INDEX,
				'node'     => $element,
				'sources'  => $this->current_sources,
				'priority' => $this->get_stylesheet_priority( $element ),
			],
			wp_array_slice_assoc( $processed, [ 'stylesheet', 'imported_font_urls' ] )
		);

		if ( $element->hasAttribute( 'amp-custom' ) && ! $this->amp_custom_style_element ) {
			$this->amp_custom_style_element = $element;
		} else {

			// Remove from DOM since we'll be adding it to amp-custom.
			$element->parentNode->removeChild( $element );
		}

		$this->set_current_node( null );
	}

	/**
	 * Process link element.
	 *
	 * @param DOMElement $element Link element.
	 */
	private function process_link_element( DOMElement $element ) {
		$href = $element->getAttribute( 'href' );

		// Allow font URLs, including protocol-less URLs and recognized URLs that use HTTP instead of HTTPS.
		$normalized_url = preg_replace( '#^(http:)?(?=//)#', 'https:', $href );
		if ( $this->allowed_font_src_regex && preg_match( $this->allowed_font_src_regex, $normalized_url ) ) {
			if ( $href !== $normalized_url ) {
				$element->setAttribute( 'href', $normalized_url );
			}

			/*
			 * Make sure rel=preconnect link is present for Google Fonts stylesheet.
			 * Note that core themes normally do this already, per <https://core.trac.wordpress.org/ticket/37171>.
			 * But not always, per <https://core.trac.wordpress.org/ticket/44668>.
			 * This also ensures that other themes will get the preconnect link when
			 * they don't implement the resource hint.
			 */
			$needs_preconnect_link = (
				'https://fonts.googleapis.com/' === substr( $normalized_url, 0, 29 )
				&&
				0 === $this->xpath->query( '//link[ @rel = "preconnect" and @crossorigin and starts-with( @href, "https://fonts.gstatic.com" ) ]', $this->head )->length
			);
			if ( $needs_preconnect_link ) {
				$link = AMP_DOM_Utils::create_node(
					$this->dom,
					'link',
					[
						'rel'         => 'preconnect',
						'href'        => 'https://fonts.gstatic.com/',
						'crossorigin' => '',
					]
				);
				$this->head->insertBefore( $link ); // Note that \AMP_Theme_Support::ensure_required_markup() will put this in the optimal order.
			}
			return;
		}

		$stylesheet = $this->get_stylesheet_from_url( $href );
		if ( $stylesheet instanceof WP_Error ) {
			$this->remove_invalid_child(
				$element,
				[
					// @todo Also include details about the error.
					'code' => self::STYLESHEET_INVALID_FILE_URL,
					'type' => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
					'url'  => $normalized_url,
				]
			);
			return;
		}

		// Honor the link's media attribute.
		$media = $element->getAttribute( 'media' );
		if ( $media && 'all' !== $media ) {
			$stylesheet = sprintf( '@media %s { %s }', $media, $stylesheet );
		}

		$this->set_current_node( $element ); // And sources when needing to be located.

		$processed = $this->process_stylesheet(
			$stylesheet,
			[
				'allowed_at_rules'   => $this->style_custom_cdata_spec['css_spec']['allowed_at_rules'],
				'property_whitelist' => $this->style_custom_cdata_spec['css_spec']['declaration'],
				'stylesheet_url'     => $href,
				'spec_name'          => self::STYLE_AMP_CUSTOM_SPEC_NAME,
			]
		);

		$this->pending_stylesheets[] = array_merge(
			[
				'group'    => self::STYLE_AMP_CUSTOM_GROUP_INDEX,
				'node'     => $element,
				'sources'  => $this->current_sources, // Needed because node is removed below.
				'priority' => $this->get_stylesheet_priority( $element ),
			],
			wp_array_slice_assoc( $processed, [ 'stylesheet', 'imported_font_urls' ] )
		);

		// Remove now that styles have been processed.
		$element->parentNode->removeChild( $element );

		$this->set_current_node( null );
	}

	/**
	 * Get stylesheet from URL.
	 *
	 * @since 1.5.0
	 *
	 * @param string $stylesheet_url Stylesheet URL.
	 * @return string|WP_Error Stylesheet string on success, or WP_Error on failure.
	 */
	private function get_stylesheet_from_url( $stylesheet_url ) {
		$stylesheet    = false;
		$css_file_path = $this->get_validated_url_file_path( $stylesheet_url, [ 'css', 'less', 'scss', 'sass' ] );
		if ( ! is_wp_error( $css_file_path ) ) {
			$stylesheet = file_get_contents( $css_file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
		}
		if ( is_string( $stylesheet ) ) {
			return $stylesheet;
		}

		// Fall back to doing an HTTP request for the stylesheet is not accessible directly from the filesystem.
		return $this->fetch_external_stylesheet( $stylesheet_url );
	}

	/**
	 * Fetch external stylesheet.
	 *
	 * @todo Use Cache-Control max-age for transient.
	 *
	 * @param string $url External stylesheet URL.
	 * @return string|WP_Error Stylesheet contents or WP_Error.
	 */
	private function fetch_external_stylesheet( $url ) {
		$cache_key = md5( $url );
		$contents  = get_transient( $cache_key );
		if ( false === $contents ) {
			$r    = wp_remote_get( $url );
			$code = wp_remote_retrieve_response_code( $r );
			if ( $code < 200 || $code >= 300 ) {
				$message = wp_remote_retrieve_response_message( $r );
				if ( ! $code ) {
					$code = 'http_error';
				} else {
					$code = "http_{$code}";
				}
				if ( ! $message ) {
					/* translators: %s: the fetched URL */
					$message = sprintf( __( 'Failed to fetch: %s', 'amp' ), $url );
				}
				$contents = new WP_Error( $code, $message );
			} elseif ( ! preg_match( '#^text/css#', wp_remote_retrieve_header( $r, 'content-type' ) ) ) {
				$contents = new WP_Error(
					'no_css_content_type',
					__( 'Response did not contain the expected text/css content type.', 'amp' )
				);
			} else {
				$contents = wp_remote_retrieve_body( $r );
			}
			set_transient( $cache_key, $contents, MONTH_IN_SECONDS );
		}
		return $contents;
	}

	/**
	 * Process stylesheet.
	 *
	 * Sanitized invalid CSS properties and rules, removes rules which do not
	 * apply to the current document, and compresses the CSS to remove whitespace and comments.
	 *
	 * @since 1.0
	 *
	 * @param string $stylesheet Stylesheet.
	 * @param array  $options {
	 *     Options.
	 *
	 *     @type string[] $property_whitelist          Exclusively-allowed properties.
	 *     @type string[] $property_blacklist          Disallowed properties.
	 *     @type string   $stylesheet_url              Original URL for stylesheet when originating via link or @import.
	 *     @type array    $allowed_at_rules            Allowed @-rules.
	 *     @type bool     $validate_keyframes          Whether keyframes should be validated.
	 *     @type string   $spec_name                   Spec name.
	 * }
	 * @return array {
	 *    Processed stylesheet.
	 *
	 *    @type array $stylesheet         Stylesheet parts, where arrays are tuples for declaration blocks.
	 *    @type array $validation_results Validation results, array containing arrays with error and sanitized keys.
	 *    @type array $imported_font_urls Imported font stylesheet URLs.
	 *    @type int   $priority           The priority of the stylesheet.
	 * }
	 */
	private function process_stylesheet( $stylesheet, $options = [] ) {
		$parsed      = null;
		$cache_key   = null;
		$cache_group = 'amp-parsed-stylesheet-v22'; // This should be bumped whenever the PHP-CSS-Parser is updated or parsed format is updated.

		$cache_impacting_options = array_merge(
			wp_array_slice_assoc(
				$options,
				[ 'property_whitelist', 'property_blacklist', 'stylesheet_url', 'allowed_at_rules' ]
			),
			wp_array_slice_assoc(
				$this->args,
				[ 'should_locate_sources', 'parsed_cache_variant', 'dynamic_element_selectors' ]
			),
			[
				'language' => get_bloginfo( 'language' ), // Used to tree-shake html[lang] selectors.
			]
		);

		$cache_key = md5( $stylesheet . wp_json_encode( $cache_impacting_options ) );

		if ( wp_using_ext_object_cache() ) {
			$parsed = wp_cache_get( $cache_key, $cache_group );
		} else {
			$parsed = get_transient( $cache_group . '-' . $cache_key );
		}

		/*
		 * Make sure that the parsed stylesheet was cached with current sanitizations.
		 * The should_sanitize_validation_error method prevents duplicates from being reported.
		 */
		if ( ! empty( $parsed['validation_results'] ) ) {
			foreach ( $parsed['validation_results'] as $validation_result ) {
				$sanitized = $this->should_sanitize_validation_error( $validation_result['error'] );
				if ( $sanitized !== $validation_result['sanitized'] ) {
					$parsed = null; // Change to sanitization of validation error detected, so cache cannot be used.
					break;
				}
			}
		}

		if ( ! $parsed || ! isset( $parsed['stylesheet'] ) || ! is_array( $parsed['stylesheet'] ) ) {
			$parsed = $this->prepare_stylesheet( $stylesheet, $options );

			/*
			 * When an object cache is not available, we cache with an expiration to prevent the options table from
			 * getting filled infinitely. On the other hand, if an external object cache is available then we don't
			 * set an expiration because it should implement LRU cache expulsion policy.
			 */
			if ( wp_using_ext_object_cache() ) {
				wp_cache_set( $cache_key, $parsed, $cache_group );
			} else {
				// The expiration is to ensure transient doesn't stick around forever since no LRU flushing like with external object cache.
				set_transient( $cache_group . '-' . $cache_key, $parsed, MONTH_IN_SECONDS );
			}
		}

		return $parsed;
	}

	/**
	 * Parse imported stylesheet.
	 *
	 * @param Import  $item     Import object.
	 * @param CSSList $css_list CSS List.
	 * @param array   $options {
	 *     Options.
	 *
	 *     @type string $stylesheet_url Original URL for stylesheet when originating via link or @import.
	 * }
	 * @return array Validation results.
	 */
	private function parse_import_stylesheet( Import $item, CSSList $css_list, $options ) {
		$results      = [];
		$at_rule_args = $item->atRuleArgs();
		$location     = array_shift( $at_rule_args );
		$media_query  = array_shift( $at_rule_args );

		if ( isset( $options['stylesheet_url'] ) ) {
			$this->real_path_urls( [ $location ], $options['stylesheet_url'] );
		}

		$import_stylesheet_url = $location->getURL()->getString();

		// Prevent importing something that has already been imported, and avoid infinite recursion.
		if ( isset( $this->processed_imported_stylesheet_urls[ $import_stylesheet_url ] ) ) {
			$css_list->remove( $item );
			return [];
		}
		$this->processed_imported_stylesheet_urls[ $import_stylesheet_url ] = true;

		// Prevent importing font stylesheets from allowed font CDNs. These will get added to the document as links instead.
		$https_import_stylesheet_url = preg_replace( '#^(http:)?(?=//)#', 'https:', $import_stylesheet_url );
		if ( $this->allowed_font_src_regex && preg_match( $this->allowed_font_src_regex, $https_import_stylesheet_url ) ) {
			$this->imported_font_urls[] = $https_import_stylesheet_url;
			$css_list->remove( $item );
			_doing_it_wrong(
				'wp_enqueue_style',
				esc_html(
					sprintf(
						/* translators: 1: @import. 2: wp_enqueue_style(). 3: font CDN URL. */
						__( 'It is not a best practice to use %1$s to load font CDN stylesheets. Please use %2$s to enqueue %3$s as its own separate script.', 'amp' ),
						'@import',
						'wp_enqueue_style()',
						$import_stylesheet_url
					)
				),
				'1.0'
			);
			return [];
		}

		$stylesheet = $this->get_stylesheet_from_url( $import_stylesheet_url );
		if ( $stylesheet instanceof WP_Error ) {
			$error     = [
				// @todo Also include details about the error.
				'code' => self::STYLESHEET_INVALID_FILE_URL,
				'type' => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
				'url'  => $import_stylesheet_url,
			];
			$sanitized = $this->should_sanitize_validation_error( $error );
			if ( $sanitized ) {
				$css_list->remove( $item );
			}
			$results[] = compact( 'error', 'sanitized' );
			return $results;
		}

		if ( $media_query ) {
			$stylesheet = sprintf( '@media %s { %s }', $media_query, $stylesheet );
		}

		$options['stylesheet_url'] = $import_stylesheet_url;

		$parsed_stylesheet = $this->parse_stylesheet( $stylesheet, $options );

		$results = array_merge(
			$results,
			$parsed_stylesheet['validation_results']
		);

		if ( ! empty( $parsed_stylesheet['css_document'] ) && method_exists( $css_list, 'replace' ) ) {
			/**
			 * CSS Doc.
			 *
			 * @var Document $css_document
			 */
			$css_document = $parsed_stylesheet['css_document'];

			// Work around bug in \Sabberworm\CSS\CSSList\CSSList::replace() when array keys are not 0-based.
			$css_list->setContents( array_values( $css_list->getContents() ) );

			$css_list->replace( $item, $css_document->getContents() );
		} else {
			$css_list->remove( $item );
		}

		return $results;
	}

	/**
	 * Parse stylesheet.
	 *
	 * @since 1.0
	 *
	 * @param string $stylesheet_string Stylesheet.
	 * @param array  $options           Options. See definition in \AMP_Style_Sanitizer::process_stylesheet().
	 * @return array {
	 *    Parsed stylesheet.
	 *
	 *    @type Document $css_document       CSS Document.
	 *    @type array    $validation_results Validation results, array containing arrays with error and sanitized keys.
	 *    @type string   $stylesheet_url     Stylesheet URL, if available.
	 *    @type string   $spec_name          Spec name.
	 * }
	 */
	private function parse_stylesheet( $stylesheet_string, $options ) {
		$validation_results = [];
		$css_document       = null;

		$this->imported_font_urls = [];
		try {
			// Remove spaces from data URLs, which cause errors and PHP-CSS-Parser can't handle them.
			$stylesheet_string = $this->remove_spaces_from_url_values( $stylesheet_string );

			$parser_settings = Sabberworm\CSS\Settings::create();
			$css_parser      = new Sabberworm\CSS\Parser( $stylesheet_string, $parser_settings );
			$css_document    = $css_parser->parse(); // @todo If 'utf-8' is not $css_parser->getCharset() then issue warning?

			if ( ! empty( $options['stylesheet_url'] ) ) {
				$this->real_path_urls(
					array_filter(
						$css_document->getAllValues(),
						static function ( $value ) {
							return $value instanceof URL;
						}
					),
					$options['stylesheet_url']
				);
			}

			$validation_results = array_merge(
				$validation_results,
				$this->process_css_list( $css_document, $options )
			);
		} catch ( Exception $exception ) {
			$error = [
				'code'      => self::CSS_SYNTAX_PARSE_ERROR,
				'message'   => $exception->getMessage(),
				'type'      => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
				'spec_name' => $options['spec_name'],
			];

			/*
			 * This is not a recoverable error, so sanitized here is just used to give user control
			 * over whether to proceed with serving this exception-raising stylesheet in AMP.
			 */
			$sanitized = $this->should_sanitize_validation_error( $error );

			$validation_results[] = compact( 'error', 'sanitized' );
		}
		return array_merge(
			compact( 'validation_results', 'css_document' ),
			[
				'imported_font_urls' => $this->imported_font_urls,
			]
		);
	}

	/**
	 * Prepare stylesheet.
	 *
	 * @since 1.0
	 *
	 * @param string $stylesheet_string Stylesheet.
	 * @param array  $options           Options. See definition in \AMP_Style_Sanitizer::process_stylesheet().
	 * @return array {
	 *    Prepared stylesheet.
	 *
	 *    @type array $stylesheet         Stylesheet parts, where arrays are tuples for declaration blocks.
	 *    @type array $validation_results Validation results, array containing arrays with error and sanitized keys.
	 *    @type array $imported_font_urls Imported font stylesheet URLs.
	 * }
	 */
	private function prepare_stylesheet( $stylesheet_string, $options = [] ) {
		$start_time = microtime( true );

		$options = array_merge(
			[
				'allowed_at_rules'   => [],
				'property_blacklist' => [
					// See <https://www.ampproject.org/docs/design/responsive/style_pages#disallowed-styles>.
					'behavior',
					'-moz-binding',
				],
				'property_whitelist' => [],
				'validate_keyframes' => false,
				'stylesheet_url'     => null,
				'spec_name'          => null,
			],
			$options
		);

		// Strip the dreaded UTF-8 byte order mark (BOM, \uFEFF). This should ideally get handled by PHP-CSS-Parser <https://github.com/sabberworm/PHP-CSS-Parser/issues/150>.
		$stylesheet_string = preg_replace( '/^\xEF\xBB\xBF/', '', $stylesheet_string );

		// Strip obsolete CDATA sections and HTML comments which were used for old school XHTML.
		$stylesheet_string = preg_replace( '#^\s*<!--#', '', $stylesheet_string );
		$stylesheet_string = preg_replace( '#^\s*<!\[CDATA\[#', '', $stylesheet_string );
		$stylesheet_string = preg_replace( '#\]\]>\s*$#', '', $stylesheet_string );
		$stylesheet_string = preg_replace( '#-->\s*$#', '', $stylesheet_string );

		$stylesheet         = [];
		$parsed_stylesheet  = $this->parse_stylesheet( $stylesheet_string, $options );
		$validation_results = $parsed_stylesheet['validation_results'];
		if ( ! empty( $parsed_stylesheet['css_document'] ) ) {
			$css_document = $parsed_stylesheet['css_document'];

			$output_format = Sabberworm\CSS\OutputFormat::createCompact();
			$output_format->setSemicolonAfterLastRule( false );

			$before_declaration_block          = '/*AMP_WP_BEFORE_DECLARATION_BLOCK*/';
			$between_selectors                 = '/*AMP_WP_BETWEEN_SELECTORS*/';
			$after_declaration_block_selectors = '/*AMP_WP_BEFORE_DECLARATION_SELECTORS*/';
			$after_declaration_block           = '/*AMP_WP_AFTER_DECLARATION*/';
			$before_at_rule                    = '/*AMP_WP_BEFORE_AT_RULE*/';
			$after_at_rule                     = '/*AMP_WP_AFTER_AT_RULE*/';

			// Add comments to stylesheet if PHP-CSS-Parser has the required extensions for tree shaking.
			if ( self::has_required_php_css_parser() ) {
				$output_format->set( 'BeforeDeclarationBlock', $before_declaration_block );
				$output_format->set( 'SpaceBeforeSelectorSeparator', $between_selectors );
				$output_format->set( 'AfterDeclarationBlockSelectors', $after_declaration_block_selectors );
				$output_format->set( 'AfterDeclarationBlock', $after_declaration_block );
				$output_format->set( 'BeforeAtRuleBlock', $before_at_rule );
				$output_format->set( 'AfterAtRuleBlock', $after_at_rule );
			}

			$stylesheet_string = $css_document->render( $output_format );

			$pattern  = '#';
			$pattern .= preg_quote( $before_at_rule, '#' );
			$pattern .= '|';
			$pattern .= preg_quote( $after_at_rule, '#' );
			$pattern .= '|';
			$pattern .= '(' . preg_quote( $before_declaration_block, '#' ) . ')';
			$pattern .= '(.+?)';
			$pattern .= preg_quote( $after_declaration_block_selectors, '#' );
			$pattern .= '(.+?)';
			$pattern .= preg_quote( $after_declaration_block, '#' );
			$pattern .= '#s';

			$dynamic_selector_pattern = null;
			if ( ! empty( $this->args['dynamic_element_selectors'] ) ) {
				$dynamic_selector_pattern = implode(
					'|',
					array_map(
						static function( $selector ) {
							return preg_quote( $selector, '#' );
						},
						$this->args['dynamic_element_selectors']
					)
				);
			}

			$split_stylesheet = preg_split( $pattern, $stylesheet_string, -1, PREG_SPLIT_DELIM_CAPTURE );
			$length           = count( $split_stylesheet );
			for ( $i = 0; $i < $length; $i++ ) {
				if ( $before_declaration_block === $split_stylesheet[ $i ] ) {

					// Skip keyframe-selector, which is can be: from | to | <percentage>.
					if ( preg_match( '/^((from|to)\b|-?\d+(\.\d+)?%)/i', $split_stylesheet[ $i + 1 ] ) ) {
						$stylesheet[] = str_replace( $between_selectors, '', $split_stylesheet[ ++$i ] ) . $split_stylesheet[ ++$i ];
						continue;
					}

					$selectors   = explode( $between_selectors . ',', $split_stylesheet[ ++$i ] );
					$declaration = $split_stylesheet[ ++$i ];

					// @todo The following logic could be made much more robust if PHP-CSS-Parser did parsing of selectors. See <https://github.com/sabberworm/PHP-CSS-Parser/pull/138#issuecomment-418193262> and <https://github.com/ampproject/amp-wp/issues/2102>.
					$selectors_parsed = [];
					foreach ( $selectors as $selector ) {
						$selectors_parsed[ $selector ] = [];

						// Remove :not() and pseudo selectors to eliminate false negatives, such as with `body:not(.title-tagline-hidden) .site-branding-text` (but not after escape character).
						$reduced_selector = preg_replace( '/(?<!\\\\)::?[a-zA-Z0-9_-]+(\(.+?\))?/', '', $selector );

						// Ignore any selector terms that occur under a dynamic selector.
						if ( $dynamic_selector_pattern ) {
							$reduced_selector = preg_replace( '#((?:' . $dynamic_selector_pattern . ')(?:\.[a-z0-9_-]+)*)[^a-z0-9_-].*#si', '$1', $reduced_selector . ' ' );
						}

						/*
						 * Gather attribute names while removing attribute selectors to eliminate false negative,
						 * such as with `.social-navigation a[href*="example.com"]:before`.
						 */
						$reduced_selector = preg_replace_callback(
							'/\[([A-Za-z0-9_:-]+)(\W?=[^\]]+)?\]/',
							static function( $matches ) use ( $selector, &$selectors_parsed ) {
								$selectors_parsed[ $selector ][ self::SELECTOR_EXTRACTED_ATTRIBUTES ][] = $matches[1];
								return '';
							},
							$reduced_selector
						);

						// Extract class names.
						$reduced_selector = preg_replace_callback(
							'/\.((?:[a-zA-Z0-9_-]+|\\\\.)+)/', // The `\\\\.` will allow any char via escaping, like the colon in `.lg\:w-full`.
							static function( $matches ) use ( $selector, &$selectors_parsed ) {
								$selectors_parsed[ $selector ][ self::SELECTOR_EXTRACTED_CLASSES ][] = stripslashes( $matches[1] );
								return '';
							},
							$reduced_selector
						);

						// Extract IDs.
						$reduced_selector = preg_replace_callback(
							'/#([a-zA-Z0-9_-]+)/',
							static function( $matches ) use ( $selector, &$selectors_parsed ) {
								$selectors_parsed[ $selector ][ self::SELECTOR_EXTRACTED_IDS ][] = $matches[1];
								return '';
							},
							$reduced_selector
						);

						// Extract tag names.
						$reduced_selector = preg_replace_callback(
							'/[a-zA-Z0-9_-]+/',
							static function( $matches ) use ( $selector, &$selectors_parsed ) {
								$selectors_parsed[ $selector ][ self::SELECTOR_EXTRACTED_TAGS ][] = $matches[0];
								return '';
							},
							$reduced_selector
						);

						// At this point, $reduced_selector should contain just the remnants of the selector, primarily combinators.
						unset( $reduced_selector );
					}

					$stylesheet[] = [
						$selectors_parsed,
						$declaration,
					];
				} else {
					$stylesheet[] = $split_stylesheet[ $i ];
				}
			}
		}

		$this->parse_css_duration += ( microtime( true ) - $start_time );

		return array_merge(
			compact( 'stylesheet', 'validation_results' ),
			[
				'imported_font_urls' => $parsed_stylesheet['imported_font_urls'],
			]
		);
	}

	/**
	 * Previous return values from calls to should_sanitize_validation_error().
	 *
	 * This is used to prevent duplicates from being reported when the sanitization status
	 * changes for a validation error in a previously-cached stylesheet.
	 *
	 * @see AMP_Style_Sanitizer::should_sanitize_validation_error()
	 * @var array
	 */
	protected $previous_should_sanitize_validation_error_results = [];

	/**
	 * Check whether or not sanitization should occur in response to validation error.
	 *
	 * Supply sources to the error and the current node to data.
	 *
	 * @since 1.0
	 *
	 * @param array $validation_error Validation error.
	 * @param array $data Data including the node.
	 * @return bool Whether to sanitize.
	 */
	public function should_sanitize_validation_error( $validation_error, $data = [] ) {
		if ( ! isset( $data['node'] ) ) {
			$data['node'] = $this->current_node;
		}
		if ( ! isset( $validation_error['sources'] ) ) {
			$validation_error['sources'] = $this->current_sources;
		}

		/*
		 * This is used to prevent duplicates from being reported when the sanitization status
		 * changes for a validation error in a previously-cached stylesheet.
		 */
		$args = compact( 'validation_error', 'data' );
		foreach ( $this->previous_should_sanitize_validation_error_results as $result ) {
			if ( $result['args'] === $args ) {
				return $result['sanitized'];
			}
		}

		$sanitized = parent::should_sanitize_validation_error( $validation_error, $data );

		$this->previous_should_sanitize_validation_error_results[] = compact( 'args', 'sanitized' );
		return $sanitized;
	}

	/**
	 * Remove spaces from CSS URL values which PHP-CSS-Parser doesn't handle.
	 *
	 * @since 1.0
	 *
	 * @param string $css CSS.
	 * @return string CSS with spaces removed from URLs.
	 */
	private function remove_spaces_from_url_values( $css ) {
		return preg_replace_callback(
			// Match CSS url() values that don't have quoted string values.
			'/\burl\(\s*(?=\w)(?P<url>[^}]*?\s*)\)/',
			static function( $matches ) {
				return preg_replace( '/\s+/', '', $matches[0] );
			},
			$css
		);
	}

	/**
	 * Process CSS list.
	 *
	 * @since 1.0
	 *
	 * @param CSSList $css_list CSS List.
	 * @param array   $options Options.
	 * @return array Validation errors.
	 */
	private function process_css_list( CSSList $css_list, $options ) {
		$results = [];

		foreach ( $css_list->getContents() as $css_item ) {
			$sanitized = false;
			if ( $css_item instanceof DeclarationBlock && empty( $options['validate_keyframes'] ) ) {
				$results = array_merge(
					$results,
					$this->process_css_declaration_block( $css_item, $css_list, $options )
				);
			} elseif ( $css_item instanceof AtRuleBlockList ) {
				if ( ! in_array( $css_item->atRuleName(), $options['allowed_at_rules'], true ) ) {
					$error     = [
						'code'      => self::CSS_SYNTAX_INVALID_AT_RULE,
						'at_rule'   => $css_item->atRuleName(),
						'type'      => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
						'spec_name' => $options['spec_name'],
					];
					$sanitized = $this->should_sanitize_validation_error( $error );
					$results[] = compact( 'error', 'sanitized' );
				}
				if ( ! $sanitized ) {
					$results = array_merge(
						$results,
						$this->process_css_list( $css_item, $options )
					);
				}
			} elseif ( $css_item instanceof Import ) {
				$results = array_merge(
					$results,
					$this->parse_import_stylesheet( $css_item, $css_list, $options )
				);
			} elseif ( $css_item instanceof AtRuleSet ) {
				if ( ! in_array( $css_item->atRuleName(), $options['allowed_at_rules'], true ) ) {
					$error     = [
						'code'      => self::CSS_SYNTAX_INVALID_AT_RULE,
						'at_rule'   => $css_item->atRuleName(),
						'type'      => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
						'spec_name' => $options['spec_name'],
					];
					$sanitized = $this->should_sanitize_validation_error( $error );
					$results[] = compact( 'error', 'sanitized' );
				}

				if ( ! $sanitized ) {
					$results = array_merge(
						$results,
						$this->process_css_declaration_block( $css_item, $css_list, $options )
					);
				}
			} elseif ( $css_item instanceof KeyFrame ) {
				if ( ! in_array( 'keyframes', $options['allowed_at_rules'], true ) ) {
					$error     = [
						'code'      => self::CSS_SYNTAX_INVALID_AT_RULE,
						'at_rule'   => $css_item->atRuleName(),
						'type'      => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
						'spec_name' => $options['spec_name'],
					];
					$sanitized = $this->should_sanitize_validation_error( $error );
					$results[] = compact( 'error', 'sanitized' );
				}

				if ( ! $sanitized ) {
					$results = array_merge(
						$results,
						$this->process_css_keyframes( $css_item, $options )
					);
				}
			} elseif ( $css_item instanceof AtRule ) {
				if ( 'charset' === $css_item->atRuleName() ) {
					/*
					 * The @charset at-rule is not allowed in style elements, so it is not allowed in AMP.
					 * If the @charset is defined, then it really should have already been acknowledged
					 * by PHP-CSS-Parser when the CSS was parsed in the first place, so at this point
					 * it is irrelevant and can be removed.
					 */
					$sanitized = true;
				} else {
					$error     = [
						'code'      => self::CSS_SYNTAX_INVALID_AT_RULE,
						'at_rule'   => $css_item->atRuleName(),
						'type'      => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
						'spec_name' => $options['spec_name'],
					];
					$sanitized = $this->should_sanitize_validation_error( $error );
					$results[] = compact( 'error', 'sanitized' );
				}
			} else {
				$error     = [
					'code'      => self::CSS_SYNTAX_INVALID_DECLARATION,
					'item'      => get_class( $css_item ),
					'type'      => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
					'spec_name' => $options['spec_name'],
				];
				$sanitized = $this->should_sanitize_validation_error( $error );
				$results[] = compact( 'error', 'sanitized' );
			}

			if ( $sanitized ) {
				$css_list->remove( $css_item );
			}
		}
		return $results;
	}

	/**
	 * Convert URLs in to non-relative real-paths.
	 *
	 * @param URL[]  $urls           URLs.
	 * @param string $stylesheet_url Stylesheet URL.
	 */
	private function real_path_urls( $urls, $stylesheet_url ) {
		$base_url = preg_replace( ':[^/]+(\?.*)?(#.*)?$:', '', $stylesheet_url );
		if ( empty( $base_url ) ) {
			return;
		}

		foreach ( $urls as $url ) {
			// URLs cannot have spaces in them, so strip them (especially when spaces get erroneously injected in data: URLs).
			$url_string = $url->getURL()->getString();

			// For data: URLs, all that is needed is to remove spaces so set and continue.
			if ( 'data:' === substr( $url_string, 0, 5 ) ) {
				continue;
			}

			// If the URL is already absolute, continue since there there is nothing left to do.
			$parsed_url = wp_parse_url( $url_string );
			if ( ! empty( $parsed_url['host'] ) || empty( $parsed_url['path'] ) || '/' === substr( $parsed_url['path'], 0, 1 ) ) {
				continue;
			}

			$parsed_url = wp_parse_url( $base_url . $url->getURL()->getString() );

			// Resolve any relative parent directory paths.
			$path = $this->unrelativize_path( $parsed_url['path'] );
			if ( is_wp_error( $path ) ) {
				continue;
			}
			$parsed_url['path'] = $path;

			$real_url = $this->reconstruct_url( $parsed_url );

			$url->getURL()->setString( $real_url );
		}
	}

	/**
	 * Process CSS rule set.
	 *
	 * @since 1.0
	 * @link https://www.ampproject.org/docs/design/responsive/style_pages#disallowed-styles
	 * @link https://www.ampproject.org/docs/design/responsive/style_pages#restricted-styles
	 *
	 * @param RuleSet $ruleset  Ruleset.
	 * @param CSSList $css_list CSS List.
	 * @param array   $options  Options.
	 *
	 * @return array Validation results.
	 */
	private function process_css_declaration_block( RuleSet $ruleset, CSSList $css_list, $options ) {
		$results = [];

		if ( $ruleset instanceof DeclarationBlock ) {
			$this->ampify_ruleset_selectors( $ruleset );
			if ( 0 === count( $ruleset->getSelectors() ) ) {
				$css_list->remove( $ruleset );
				return $results;
			}
		}

		// Remove disallowed properties.
		if ( ! empty( $options['property_whitelist'] ) ) {
			$properties = $ruleset->getRules();
			foreach ( $properties as $property ) {
				$vendorless_property_name = preg_replace( '/^-\w+-/', '', $property->getRule() );
				if ( ! in_array( $vendorless_property_name, $options['property_whitelist'], true ) ) {
					$error     = [
						'code'           => self::CSS_SYNTAX_INVALID_PROPERTY,
						'property_name'  => $property->getRule(),
						'property_value' => $property->getValue(),
						'type'           => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
						'spec_name'      => $options['spec_name'],
					];
					$sanitized = $this->should_sanitize_validation_error( $error );
					if ( $sanitized ) {
						$ruleset->removeRule( $property->getRule() );
					}
					$results[] = compact( 'error', 'sanitized' );
				}
			}
		} else {
			foreach ( $options['property_blacklist'] as $illegal_property_name ) {
				$properties = $ruleset->getRules( $illegal_property_name );
				foreach ( $properties as $property ) {
					$error     = [
						'code'           => self::CSS_SYNTAX_INVALID_PROPERTY_NOLIST,
						'property_name'  => $property->getRule(),
						'property_value' => (string) $property->getValue(),
						'type'           => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
						'spec_name'      => $options['spec_name'],
					];
					$sanitized = $this->should_sanitize_validation_error( $error );
					if ( $sanitized ) {
						$ruleset->removeRule( $property->getRule() );
					}
					$results[] = compact( 'error', 'sanitized' );
				}
			}
		}

		if ( $ruleset instanceof AtRuleSet && 'font-face' === $ruleset->atRuleName() ) {
			$this->process_font_face_at_rule( $ruleset, $options );
		}

		$results = array_merge(
			$results,
			$this->transform_important_qualifiers( $ruleset, $css_list, $options )
		);

		// Remove the ruleset if it is now empty.
		if ( 0 === count( $ruleset->getRules() ) ) {
			$css_list->remove( $ruleset );
		}
		// @todo Delete rules with selectors for -amphtml- class and i-amphtml- tags.
		return $results;
	}

	/**
	 * Process @font-face by making src URLs non-relative and converting data: URLs into file URLs (with educated guessing).
	 *
	 * @since 1.0
	 *
	 * @param AtRuleSet $ruleset Ruleset for @font-face.
	 * @param array     $options {
	 *     Options.
	 *
	 *     @type string $stylesheet_url Stylesheet URL, if available.
	 * }
	 */
	private function process_font_face_at_rule( AtRuleSet $ruleset, $options ) {
		$src_properties = $ruleset->getRules( 'src' );
		if ( empty( $src_properties ) ) {
			return;
		}

		// Obtain the font-family name to guess the filename.
		$font_family   = null;
		$font_basename = null;
		$properties    = $ruleset->getRules( 'font-family' );
		if ( isset( $properties[0] ) ) {
			$font_family = trim( $properties[0]->getValue(), '"\'' );

			// Remove all non-word characters from the font family to serve as the filename.
			$font_basename = preg_replace( '/[^A-Za-z0-9_\-]/', '', $font_family ); // Same as sanitize_key() minus case changes.
		}

		// Obtain the stylesheet base URL from which to guess font file locations.
		$stylesheet_base_url = null;
		if ( ! empty( $options['stylesheet_url'] ) ) {
			$stylesheet_base_url = preg_replace(
				':[^/]+(\?.*)?(#.*)?$:',
				'',
				$options['stylesheet_url']
			);
			$stylesheet_base_url = trailingslashit( $stylesheet_base_url );
		}

		// Attempt to transform data: URLs in src properties to be external file URLs.
		$converted_count = 0;
		foreach ( $src_properties as $src_property ) {
			$value = $src_property->getValue();
			if ( ! ( $value instanceof RuleValueList ) ) {
				continue;
			}

			/*
			 * The CSS Parser parses a src such as:
			 *
			 *    url(data:application/font-woff;...) format('woff'),
			 *    url('Genericons.ttf') format('truetype'),
			 *    url('Genericons.svg#genericonsregular') format('svg')
			 *
			 * As a list of components consisting of:
			 *
			 *    URL,
			 *    RuleValueList( CSSFunction, URL ),
			 *    RuleValueList( CSSFunction, URL ),
			 *    CSSFunction
			 *
			 * Clearly the components here are not logically grouped. So the first step is to fix the order.
			 */
			$sources = [];
			foreach ( $value->getListComponents() as $component ) {
				if ( $component instanceof RuleValueList ) {
					$subcomponents = $component->getListComponents();
					$subcomponent  = array_shift( $subcomponents );
					if ( $subcomponent ) {
						if ( empty( $sources ) ) {
							$sources[] = [ $subcomponent ];
						} else {
							$sources[ count( $sources ) - 1 ][] = $subcomponent;
						}
					}
					foreach ( $subcomponents as $subcomponent ) {
						$sources[] = [ $subcomponent ];
					}
				} elseif ( empty( $sources ) ) {
					$sources[] = [ $component ];
				} else {
					$sources[ count( $sources ) - 1 ][] = $component;
				}
			}

			/**
			 * Source URL lists.
			 *
			 * @var string[] $source_file_urls
			 * @var URL[]    $source_data_url_objects
			 */
			$source_file_urls        = [];
			$source_data_url_objects = [];
			foreach ( $sources as $i => $source ) {
				if ( $source[0] instanceof URL ) {
					$value = $source[0]->getURL()->getString();
					if ( 'data:' === substr( $value, 0, 5 ) ) {
						$source_data_url_objects[ $i ] = $source[0];
					} else {
						$source_file_urls[ $i ] = $value;
					}
				}
			}

			// Convert data: URLs into regular URLs, assuming there will be a file present (e.g. woff fonts in core themes).
			foreach ( $source_data_url_objects as $i => $data_url ) {
				$mime_type = strtok( substr( $data_url->getURL()->getString(), 5 ), ';' );
				if ( ! $mime_type ) {
					continue;
				}
				$extension = preg_replace( ':.+/(.+-)?:', '', $mime_type );

				$guessed_urls = [];

				// Guess URLs based on any other font sources that are not using data: URLs (e.g. truetype fallback for inline woff2).
				foreach ( $source_file_urls as $source_file_url ) {
					$guessed_url = preg_replace(
						':(?<=\.)\w+(\?.*)?(#.*)?$:', // Match the file extension in the URL.
						$extension,
						$source_file_url,
						1,
						$count
					);
					if ( 1 === $count ) {
						$guessed_urls[] = $guessed_url;
					}
				}

				/*
				 * Guess some font file URLs based on the font name in a fonts directory based on precedence of Twenty Nineteen.
				 * For example, the NonBreakingSpaceOverride woff2 font file is located at fonts/NonBreakingSpaceOverride.woff2.
				 */
				if ( $stylesheet_base_url && $font_basename ) {
					$guessed_urls[] = $stylesheet_base_url . sprintf( 'fonts/%s.%s', $font_basename, $extension );
					$guessed_urls[] = $stylesheet_base_url . sprintf( 'fonts/%s.%s', strtolower( $font_basename ), $extension );
				}

				// Find the font file that exists, and then replace the data: URL with the external URL for the font.
				foreach ( $guessed_urls as $guessed_url ) {
					$path = $this->get_validated_url_file_path( $guessed_url, [ 'woff', 'woff2', 'ttf', 'otf', 'svg' ] );
					if ( ! is_wp_error( $path ) ) {
						$data_url->getURL()->setString( $guessed_url );
						$converted_count++;
						continue 2;
					}
				}

				// As fallback, look for fonts bundled with the AMP plugin.
				$font_filename = sprintf( '%s.%s', strtolower( $font_basename ), $extension );
				$bundled_fonts = [
					'nonbreakingspaceoverride.woff',
					'nonbreakingspaceoverride.woff2',
					'genericons.woff',
				];
				if ( in_array( $font_filename, $bundled_fonts, true ) ) {
					$data_url->getURL()->setString( plugin_dir_url( AMP__FILE__ ) . "assets/fonts/$font_filename" );
					$converted_count++;
				}
			} // End foreach $source_data_url_objects.
		} // End foreach $src_properties.

		/*
		 * If a data: URL has been replaced with an external file URL, then we add a font-display:swap to the @font-face
		 * rule if one isn't already present. This prevents FO
		 *
		 *  If no font-display is already present, add font-display:swap since the font is now being loaded externally.
		 */
		if ( $converted_count && 0 === count( $ruleset->getRules( 'font-display' ) ) ) {
			$font_display_rule = new Rule( 'font-display' );
			$font_display_rule->setValue( 'swap' );
			$ruleset->addRule( $font_display_rule );
		}
	}

	/**
	 * Process CSS keyframes.
	 *
	 * @since 1.0
	 * @link https://www.ampproject.org/docs/design/responsive/style_pages#restricted-styles.
	 * @link https://github.com/ampproject/amphtml/blob/b685a0780a7f59313666225478b2b79b463bcd0b/validator/validator-main.protoascii#L1002-L1043
	 * @todo Tree shaking could be extended to keyframes, to omit a keyframe if it is not referenced by any rule.
	 *
	 * @param KeyFrame $css_list Ruleset.
	 * @param array    $options  Options.
	 * @return array Validation results.
	 */
	private function process_css_keyframes( KeyFrame $css_list, $options ) {
		$results = [];
		if ( ! empty( $options['property_whitelist'] ) ) {
			foreach ( $css_list->getContents() as $rules ) {
				if ( ! ( $rules instanceof DeclarationBlock ) ) {
					$error     = [
						'code'      => self::CSS_SYNTAX_INVALID_DECLARATION,
						'item'      => get_class( $rules ),
						'type'      => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
						'spec_name' => $options['spec_name'],
					];
					$sanitized = $this->should_sanitize_validation_error( $error );
					if ( $sanitized ) {
						$css_list->remove( $rules );
					}
					$results[] = compact( 'error', 'sanitized' );
					continue;
				}

				$results = array_merge(
					$results,
					$this->transform_important_qualifiers( $rules, $css_list, $options )
				);

				$properties = $rules->getRules();
				foreach ( $properties as $property ) {
					$vendorless_property_name = preg_replace( '/^-\w+-/', '', $property->getRule() );
					if ( ! in_array( $vendorless_property_name, $options['property_whitelist'], true ) ) {
						$error     = [
							'code'           => self::CSS_SYNTAX_INVALID_PROPERTY,
							'property_name'  => $property->getRule(),
							'property_value' => (string) $property->getValue(),
							'type'           => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
							'spec_name'      => $options['spec_name'],
						];
						$sanitized = $this->should_sanitize_validation_error( $error );
						if ( $sanitized ) {
							$rules->removeRule( $property->getRule() );
						}
						$results[] = compact( 'error', 'sanitized' );
					}
				}
			}
		}
		return $results;
	}

	/**
	 * Replace !important qualifiers with more specific rules.
	 *
	 * @since 1.0
	 * @see https://www.npmjs.com/package/replace-important
	 * @see https://www.ampproject.org/docs/fundamentals/spec#important
	 *
	 * @param RuleSet|DeclarationBlock $ruleset  Rule set.
	 * @param CSSList                  $css_list CSS List.
	 * @param array                    $options  Options.
	 * @return array Validation results.
	 */
	private function transform_important_qualifiers( RuleSet $ruleset, CSSList $css_list, $options ) {
		$results = [];

		// An !important only makes sense for rulesets that have selectors.
		$allow_transformation = (
			$ruleset instanceof DeclarationBlock
			&&
			! ( $css_list instanceof KeyFrame )
		);

		$properties = $ruleset->getRules();
		$importants = [];
		foreach ( $properties as $property ) {
			if ( $property->getIsImportant() ) {
				if ( $allow_transformation ) {
					$importants[] = $property;
					$property->setIsImportant( false );
					$ruleset->removeRule( $property->getRule() );
				} else {
					$error     = [
						'code'           => self::CSS_SYNTAX_INVALID_IMPORTANT,
						'type'           => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
						'property_name'  => $property->getRule(),
						'property_value' => $property->getValue(),
						'spec_name'      => $options['spec_name'],
					];
					$sanitized = $this->should_sanitize_validation_error( $error );
					if ( $sanitized ) {
						$property->setIsImportant( false );
					}
					$results[] = compact( 'error', 'sanitized' );
				}
			}
		}
		if ( ! $allow_transformation || empty( $importants ) ) {
			return $results;
		}

		$important_ruleset = clone $ruleset;
		$important_ruleset->setSelectors(
			array_map(
				/**
				 * Modify selectors to be more specific to roughly match the effect of !important.
				 *
				 * @link https://github.com/ampproject/ampstart/blob/4c21d69afdd07b4c60cd190937bda09901955829/tools/replace-important/lib/index.js#L88-L109
				 *
				 * @param Selector $old_selector Original selector.
				 * @return Selector The new more-specific selector.
				 */
				static function( Selector $old_selector ) {
					// Calculate the specificity multiplier for the placeholder.
					$specificity_multiplier = AMP_Style_Sanitizer::INLINE_SPECIFICITY_MULTIPLIER + 1 + floor( $old_selector->getSpecificity() / 100 );
					if ( $old_selector->getSpecificity() % 100 > 0 ) {
						$specificity_multiplier++;
					}
					if ( $old_selector->getSpecificity() % 10 > 0 ) {
						$specificity_multiplier++;
					}
					$selector_mod = str_repeat( ':not(#_)', $specificity_multiplier ); // Here "_" is just a short single-char ID.

					$new_selector = $old_selector->getSelector();

					// Amend the selector mod to the first element in selector if it is already the root; otherwise add new root ancestor.
					if ( preg_match( '/^\s*(html|:root)\b/i', $new_selector, $matches ) ) {
						$new_selector = substr( $new_selector, 0, strlen( $matches[0] ) ) . $selector_mod . substr( $new_selector, strlen( $matches[0] ) );
					} else {
						$new_selector = sprintf( ':root%s %s', $selector_mod, $new_selector );
					}
					return new Selector( $new_selector );
				},
				$ruleset->getSelectors()
			)
		);
		$important_ruleset->setRules( $importants );

		$i = array_search( $ruleset, $css_list->getContents(), true );
		if ( false !== $i && method_exists( $css_list, 'splice' ) ) {
			$css_list->splice( $i + 1, 0, [ $important_ruleset ] );
		} else {
			$css_list->append( $important_ruleset );
		}

		return $results;
	}

	/**
	 * Collect and store all CSS style attributes.
	 *
	 * Collects the CSS styles from within the HTML contained in this instance's DOMDocument.
	 *
	 * @see Retrieve array of styles using $this->get_styles() after calling this method.
	 *
	 * @since 0.4
	 * @since 0.7 Modified to use element passed by XPath query.
	 *
	 * @note Uses recursion to traverse down the tree of DOMDocument nodes.
	 *
	 * @param DOMElement $element Node.
	 */
	private function collect_inline_styles( $element ) {
		$style_attribute = $element->getAttributeNode( 'style' );
		if ( ! $style_attribute || ! trim( $style_attribute->nodeValue ) ) {
			return;
		}

		$class = 'amp-wp-' . substr( md5( $style_attribute->nodeValue ), 0, 7 );
		$root  = ':root' . str_repeat( ':not(#_)', self::INLINE_SPECIFICITY_MULTIPLIER );
		$rule  = sprintf( '%s .%s { %s }', $root, $class, $style_attribute->nodeValue );

		$this->set_current_node( $element ); // And sources when needing to be located.

		$processed = $this->process_stylesheet(
			$rule,
			[
				'allowed_at_rules'   => [],
				'property_whitelist' => $this->style_custom_cdata_spec['css_spec']['declaration'],
				'spec_name'          => self::STYLE_AMP_CUSTOM_SPEC_NAME,
			]
		);

		$element->removeAttribute( 'style' );

		if ( $processed['stylesheet'] ) {
			$this->pending_stylesheets[] = [
				'group'      => self::STYLE_AMP_CUSTOM_GROUP_INDEX,
				'stylesheet' => $processed['stylesheet'],
				'node'       => $element,
				'sources'    => $this->current_sources,
				'priority'   => $this->get_stylesheet_priority( $style_attribute ),
			];

			if ( $element->hasAttribute( 'class' ) ) {
				$element->setAttribute( 'class', $element->getAttribute( 'class' ) . ' ' . $class );
			} else {
				$element->setAttribute( 'class', $class );
			}
		}

		$this->set_current_node( null );
	}

	/**
	 * Finalize stylesheets for style[amp-custom] and style[amp-keyframes] elements.
	 *
	 * Concatenate all pending stylesheets, remove unused rules, and add to AMP style elements in document.
	 * Combine all amp-keyframe styles and add them to the end of the body.
	 *
	 * @since 1.0
	 * @see https://www.ampproject.org/docs/fundamentals/spec#keyframes-stylesheet
	 */
	private function finalize_styles() {
		$stylesheet_groups = [
			self::STYLE_AMP_CUSTOM_GROUP_INDEX    => [
				'source_map_comment'  => "\n\n/*# sourceURL=amp-custom.css */",
				'cdata_spec'          => $this->style_custom_cdata_spec,
				'pending_stylesheets' => [],
				'included_count'      => 0,
				'import_front_matter' => '', // Extra @import statements that are prepended when fetch fails and validation error is rejected.
			],
			self::STYLE_AMP_KEYFRAMES_GROUP_INDEX => [
				'source_map_comment'  => "\n\n/*# sourceURL=amp-keyframes.css */",
				'cdata_spec'          => $this->style_keyframes_cdata_spec,
				'pending_stylesheets' => [],
				'included_count'      => 0,
				'import_front_matter' => '',
			],
		];

		$imported_font_urls = [];

		// Divide pending stylesheet between custom and keyframes, and calculate size of each (before tree shaking).
		foreach ( $this->pending_stylesheets as $i => $pending_stylesheet ) {
			foreach ( $pending_stylesheet['stylesheet'] as $j => $part ) {
				if ( is_string( $part ) && 0 === strpos( $part, '@import' ) ) {
					$stylesheet_groups[ $pending_stylesheet['group'] ]['import_front_matter'] .= $part;
					unset( $this->pending_stylesheets[ $i ]['stylesheet'][ $j ] );
				}
			}

			if ( ! empty( $pending_stylesheet['imported_font_urls'] ) ) {
				$imported_font_urls = array_merge( $imported_font_urls, $pending_stylesheet['imported_font_urls'] );
			}
		}

		// Process the pending stylesheets.
		foreach ( array_keys( $stylesheet_groups ) as $group ) {
			$stylesheet_groups[ $group ]['included_count'] = $this->finalize_stylesheet_group( $group, $stylesheet_groups[ $group ] );
		}

		// If we're not working with the document element (e.g. for legacy post templates) then there is nothing left to do.
		if ( empty( $this->args['use_document_element'] ) ) {
			return; // @todo This would no longer be true with <https://github.com/ampproject/amp-wp/issues/2202>.
		}

		// Add style[amp-custom] to document.
		if ( $stylesheet_groups[ self::STYLE_AMP_CUSTOM_GROUP_INDEX ]['included_count'] > 0 ) {

			// Ensure style[amp-custom] is present in the document.
			if ( ! $this->amp_custom_style_element ) {
				$this->amp_custom_style_element = $this->dom->createElement( 'style' );
				$this->amp_custom_style_element->setAttribute( 'amp-custom', '' );
				$this->head->appendChild( $this->amp_custom_style_element );
			}

			/*
			 * On AMP-first themes when there are new/rejected validation errors present, a parsed stylesheet may include
			 * @import rules. These must be moved to the beginning to be honored.
			 */
			$css = $stylesheet_groups[ self::STYLE_AMP_CUSTOM_GROUP_INDEX ]['import_front_matter'];

			$css .= implode( '', $this->get_stylesheets() );
			$css .= $stylesheet_groups[ self::STYLE_AMP_CUSTOM_GROUP_INDEX ]['source_map_comment'];

			/*
			 * Let the style[amp-custom] be populated with the concatenated CSS.
			 * !important: Updating the contents of this style element by setting textContent is not
			 * reliable across PHP/libxml versions, so this is why the children are removed and the
			 * text node is then explicitly added containing the CSS.
			 */
			while ( $this->amp_custom_style_element->firstChild ) {
				$this->amp_custom_style_element->removeChild( $this->amp_custom_style_element->firstChild );
			}
			$this->amp_custom_style_element->appendChild( $this->dom->createTextNode( $css ) );

			$included_size          = 0;
			$included_original_size = 0;
			$excluded_size          = 0;
			$excluded_original_size = 0;
			$included_sources       = [];
			$excluded_sources       = [];
			foreach ( $this->pending_stylesheets as $j => $pending_stylesheet ) {
				if ( self::STYLE_AMP_CUSTOM_GROUP_INDEX !== $pending_stylesheet['group'] || ! ( $pending_stylesheet['node'] instanceof DOMElement ) || ! empty( $pending_stylesheet['duplicate'] ) ) {
					continue;
				}
				$message = sprintf( '[%3d] % 6d B', $pending_stylesheet['priority'], $pending_stylesheet['size'] );
				if ( $pending_stylesheet['size'] && $pending_stylesheet['size'] !== $pending_stylesheet['original_size'] ) {
					$message .= sprintf( ' (%2d%%)', $pending_stylesheet['size'] / $pending_stylesheet['original_size'] * 100 );
				} else {
					$message .= '      ';
				}
				$message .= ': ';
				$message .= $pending_stylesheet['node']->nodeName;
				if ( $pending_stylesheet['node']->getAttribute( 'id' ) ) {
					$message .= '#' . $pending_stylesheet['node']->getAttribute( 'id' );
				}
				if ( $pending_stylesheet['node']->getAttribute( 'class' ) ) {
					$message .= '.' . $pending_stylesheet['node']->getAttribute( 'class' );
				}
				foreach ( $pending_stylesheet['node']->attributes as $attribute ) {
					if ( 'id' !== $attribute->nodeName && 'class' !== $attribute->nodeName ) {
						$message .= sprintf( '[%s=%s]', $attribute->nodeName, $attribute->nodeValue );
					}
				}

				if ( $pending_stylesheet['included'] ) {
					$included_sources[]      = $message;
					$included_size          += $pending_stylesheet['size'];
					$included_original_size += $pending_stylesheet['original_size'];
				} else {
					$excluded_sources[]      = $message;
					$excluded_size          += $pending_stylesheet['size'];
					$excluded_original_size += $pending_stylesheet['original_size'];
				}
			}

			$include_manifest_comment = (
				'always' === $this->args['include_manifest_comment']
				||
				( $excluded_size > 0 && 'when_excessive' === $this->args['include_manifest_comment'] )
			);

			$comment = '';
			if ( $include_manifest_comment && ! empty( $included_sources ) && $included_original_size > 0 ) {
				$comment .= sprintf(
					/* translators: %s: style[amp-custom] */
					esc_html__( 'The %s element is populated with:', 'amp' ),
					'style[amp-custom]'
				) . "\n" . implode( "\n", $included_sources ) . "\n";
				if ( self::has_required_php_css_parser() && ! AMP_Debug::has_flag( AMP_Debug::DISABLE_TREE_SHAKING_QUERY_VAR ) ) {
					$comment .= sprintf(
						/* translators: 1: number of included bytes. 2: percentage of total CSS actually included after tree shaking. 3: total included size. */
						esc_html__( 'Total included size: %1$s bytes (%2$d%% of %3$s total after tree shaking)', 'amp' ),
						number_format_i18n( $included_size ),
						$included_size / $included_original_size * 100,
						number_format_i18n( $included_original_size )
					) . "\n";
				} else {
					$comment .= sprintf(
						/* translators: %s: number of included bytes. */
						esc_html__( 'Total included size: %s bytes', 'amp' ),
						number_format_i18n( $included_size ),
						$included_size / $included_original_size * 100,
						number_format_i18n( $included_original_size )
					) . "\n";
				}
			}
			if ( $include_manifest_comment && ! empty( $excluded_sources ) && $excluded_original_size > 0 ) {
				if ( $comment ) {
					$comment .= "\n";
				}
				$comment .= sprintf(
					/* translators: %s: style[amp-custom] */
					esc_html__( 'The following stylesheets are too large to be included in %s:', 'amp' ),
					'style[amp-custom]'
				) . "\n" . implode( "\n", $excluded_sources ) . "\n";

				if ( self::has_required_php_css_parser() ) {
					$comment .= sprintf(
						/* translators: 1: number of excluded bytes. 2: percentage of total CSS actually excluded even after tree shaking. 3: total excluded size. */
						esc_html__( 'Total excluded size: %1$s bytes (%2$d%% of %3$s total after tree shaking)', 'amp' ),
						number_format_i18n( $excluded_size ),
						$excluded_size / $excluded_original_size * 100,
						number_format_i18n( $excluded_original_size )
					) . "\n";
				} else {
					$comment .= sprintf(
						/* translators: %s: number of excluded bytes. */
						esc_html__( 'Total excluded size: %s bytes', 'amp' ),
						number_format_i18n( $excluded_size )
					) . "\n";
				}

				$total_size          = $included_size + $excluded_size;
				$total_original_size = $included_original_size + $excluded_original_size;
				if ( $total_size !== $total_original_size ) {
					$comment .= "\n";
					$comment .= sprintf(
						/* translators: 1: total combined bytes. 2: is percentage of CSS after tree shaking. 3: is total before tree shaking. */
						esc_html__( 'Total combined size: %1$s bytes (%2$d%% of %3$s total after tree shaking)', 'amp' ),
						number_format_i18n( $total_size ),
						( $total_size / $total_original_size ) * 100,
						number_format_i18n( $total_original_size )
					) . "\n";
				}
			}

			if ( $include_manifest_comment && ! self::has_required_php_css_parser() ) {
				$comment .= "\n" . esc_html__( 'Warning! AMP CSS processing is limited because a conflicting version of PHP-CSS-Parser has been loaded by another plugin or theme. Tree shaking is not available.', 'amp' ) . "\n";
			}

			if ( $comment ) {
				$this->amp_custom_style_element->parentNode->insertBefore(
					$this->dom->createComment( "\n$comment" ),
					$this->amp_custom_style_element
				);
			}
		}

		/*
		 * Add font stylesheets from CDNs which were extracted from @import rules.
		 * We can't add crossorigin=anonymous to these since such a CORS request would not be made in the non-AMP version,
		 * and so if the service worker cached the opaque response on the non-AMP version then it wouldn't be usable in
		 * the AMP version if it was requested with CORS.
		 */
		foreach ( array_unique( $imported_font_urls ) as $imported_font_url ) {
			$link = $this->dom->createElement( 'link' );
			$link->setAttribute( 'rel', 'stylesheet' );
			$link->setAttribute( 'href', $imported_font_url );
			$this->head->appendChild( $link );
		}

		// Add style[amp-keyframes] to document.
		if ( $stylesheet_groups[ self::STYLE_AMP_KEYFRAMES_GROUP_INDEX ]['included_count'] > 0 ) {
			$body = $this->dom->getElementsByTagName( 'body' )->item( 0 );
			if ( ! $body ) {
				$body = $this->dom->createElement( 'body' );
				$this->dom->documentElement->appendChild( $body );
			} else {
				$css = $stylesheet_groups[ self::STYLE_AMP_KEYFRAMES_GROUP_INDEX ]['import_front_matter'];

				$css .= implode(
					'',
					wp_list_pluck(
						array_filter(
							$this->pending_stylesheets,
							static function( $pending_stylesheet ) {
								return $pending_stylesheet['included'] && self::STYLE_AMP_KEYFRAMES_GROUP_INDEX === $pending_stylesheet['group'];
							}
						),
						'stylesheet'
					)
				);
				$css .= $stylesheet_groups[ self::STYLE_AMP_KEYFRAMES_GROUP_INDEX ]['source_map_comment'];

				$style_element = $this->dom->createElement( 'style' );
				$style_element->setAttribute( 'amp-keyframes', '' );
				$style_element->appendChild( $this->dom->createTextNode( $css ) );
				$body->appendChild( $style_element );
			}
		}

		$this->remove_admin_bar_if_css_excluded();
	}

	/**
	 * Remove admin bar if its CSS was excluded.
	 *
	 * @since 1.2
	 */
	private function remove_admin_bar_if_css_excluded() {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		$admin_bar_id = 'wpadminbar';
		$admin_bar    = $this->dom->getElementById( $admin_bar_id );
		if ( ! $admin_bar || ! $admin_bar->parentNode ) {
			return;
		}

		$included = true;
		foreach ( $this->pending_stylesheets as &$pending_stylesheet ) {
			$is_admin_bar_css = (
				self::STYLE_AMP_CUSTOM_GROUP_INDEX === $pending_stylesheet['group']
				&&
				$pending_stylesheet['node'] instanceof DOMElement
				&&
				'admin-bar-css' === $pending_stylesheet['node']->getAttribute( 'id' )
			);
			if ( $is_admin_bar_css ) {
				$included = $pending_stylesheet['included'];
				break;
			}
		}

		unset( $pending_stylesheet );

		if ( ! $included ) {
			// Remove admin-bar class from body element.
			// @todo It would be nice if any style rules which refer to .admin-bar could also be removed, but this would mean retroactively going back over the CSS again and re-shaking it.
			$body = $this->dom->getElementsByTagName( 'body' )->item( 0 );
			if ( $body instanceof DOMElement && $body->hasAttribute( 'class' ) ) {
				$body->setAttribute(
					'class',
					preg_replace( '/(^|\s)admin-bar(\s|$)/', ' ', $body->getAttribute( 'class' ) )
				);
			}

			// Remove admin bar element.
			$comment_text = sprintf(
				/* translators: %s: CSS selector for admin bar element  */
				__( 'Admin bar (%s) was removed to preserve AMP validity due to excessive CSS.', 'amp' ),
				'#' . $admin_bar_id
			);
			$admin_bar->parentNode->replaceChild(
				$this->dom->createComment( ' ' . $comment_text . ' ' ),
				$admin_bar
			);
		}
	}

	/**
	 * Convert CSS selectors and remove obsolete selector hacks for IE.
	 *
	 * @param DeclarationBlock $ruleset Ruleset.
	 */
	private function ampify_ruleset_selectors( $ruleset ) {
		$selectors             = [];
		$has_changed_selectors = false;
		$language              = strtolower( get_bloginfo( 'language' ) );
		foreach ( $ruleset->getSelectors() as $old_selector ) {
			$selector = $old_selector->getSelector();

			// Automatically tree-shake IE6/IE7 hacks for selectors with `* html` and `*+html`.
			if ( preg_match( '/^\*\s*\+?\s*html/', $selector ) ) {
				$has_changed_selectors = true;
				continue;
			}

			// Automatically remove selectors with html[lang] that are for another language (and thus are irrelevant). This is safe because amp-bind'ed [lang] is not allowed.
			$is_other_language_root = (
				preg_match( '/^html\[lang(?P<starts_with>\^)?=([\'"]?)(?P<lang>.+?)\2\]/', strtolower( $selector ), $matches )
				&&
				(
					empty( $matches['starts_with'] )
					?
					$language !== $matches['lang']
					:
					substr( $language, 0, strlen( $matches['lang'] ) ) !== $matches['lang']
				)
			);
			if ( $is_other_language_root ) {
				$has_changed_selectors = true;
				continue;
			}

			// Remove selectors with :lang() for another language (and thus irrelevant).
			if ( preg_match( '/:lang\((?P<languages>.+?)\)/', $selector, $matches ) ) {
				$has_matching_language = 0;
				$selector_languages    = array_map(
					static function ( $selector_language ) {
						return trim( $selector_language, '"\'' );
					},
					preg_split( '/\s*,\s*/', strtolower( trim( $matches['languages'] ) ) )
				);
				foreach ( $selector_languages as $selector_language ) {
					/*
					 * The following logic accounts for the following conditions, where all but the last is a match:
					 *
					 * N: en && fr
					 * Y: en && en
					 * Y: en && en-US
					 * Y: en-US && en
					 * N: en-US && en-UK
					 */
					if (
						substr( $language, 0, strlen( $selector_language ) ) === $selector_language
						||
						substr( $selector_language, 0, strlen( $language ) ) === $language
					) {
						$has_matching_language = true;
						break;
					}
				}
				if ( ! $has_matching_language ) {
					$has_changed_selectors = true;
					continue;
				}
			}

			// An element (type) either starts a selector or is preceded by combinator, comma, opening paren, or closing brace.
			$before_type_selector_pattern = '(?<=^|\(|\s|>|\+|~|,|})';
			$after_type_selector_pattern  = '(?=$|[^a-zA-Z0-9_-])';

			// Replace focus selectors with :focus-within.
			if ( $this->focus_class_name_selector_pattern ) {
				$count    = 0;
				$selector = preg_replace(
					$this->focus_class_name_selector_pattern,
					':focus-within',
					$selector,
					-1,
					$count
				);
				if ( $count > 0 ) {
					$has_changed_selectors = true;
				}
			}

			/*
			 * Loop over each selector mappings. A single HTML tag can map to multiple AMP tags (e.g. img could be amp-img or amp-anim).
			 * The $selector_mappings array contains ~6 items, so rest easy your O(n^3) eyes when seeing triple nested loops!
			 */
			$edited_selectors = [ $selector ];
			foreach ( $this->selector_mappings as $html_tag => $amp_tags ) {

				// Create pattern for determining whether a mapped HTML element is present in this selector.
				$html_pattern = '/' . $before_type_selector_pattern . preg_quote( $html_tag, '/' ) . $after_type_selector_pattern . '/i';

				/*
				 * Iterate over each selector and perform the tag mapping replacements.
				 * Note that $edited_selectors array contains only item in the normal case.
				 * Note also that the size of $edited_selectors can grow while iterating, hence disabling sniffs.
				 */
				for ( $i = 0; $i < count( $edited_selectors ); $i++ ) { // phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed, Squiz.PHP.DisallowSizeFunctionsInLoops.Found

					// Skip doing any replacement if the AMP tag is already present, as this indicates the selector was written for AMP already.
					$amp_tag_pattern = '/' . $before_type_selector_pattern . implode( '|', $amp_tags ) . $after_type_selector_pattern . '/i';
					if ( preg_match( $amp_tag_pattern, $edited_selectors[ $i ], $matches ) && in_array( $matches[0], $amp_tags, true ) ) {
						continue;
					}

					// Replace the HTML tag with the first first mapped AMP tag.
					$edited_selector = preg_replace( $html_pattern, $amp_tags[0], $edited_selectors[ $i ], -1, $count );

					// If the HTML tag was not found, then short-circuit.
					if ( 0 === $count ) {
						continue;
					}

					$edited_selectors_from_selector = [ $edited_selector ];

					// Replace the HTML tag with the the remaining mapped AMP tags.
					foreach ( array_slice( $amp_tags, 1 ) as $amp_tag ) { // Note: This array contains only a couple items.
						$edited_selectors_from_selector[] = preg_replace( $html_pattern, $amp_tag, $edited_selectors[ $i ] );
					}

					// Replace the current edited selector with all the new edited selectors resulting from the mapping replacement.
					array_splice( $edited_selectors, $i, 1, $edited_selectors_from_selector );
					$has_changed_selectors = true;
				}
			}

			$selectors = array_merge( $selectors, $edited_selectors );
		}

		if ( $has_changed_selectors ) {
			$ruleset->setSelectors( $selectors );
		}
	}

	/**
	 * Given a list of class names, create a regular expression pattern to match them in a selector.
	 *
	 * @since 1.4
	 *
	 * @param string[] $class_names Class names.
	 * @return string Regular expression pattern.
	 */
	private static function get_class_name_selector_pattern( $class_names ) {
		$class_pattern = implode(
			'|',
			array_map(
				static function ( $class_name ) {
					return preg_quote( $class_name, '/' );
				},
				(array) $class_names
			)
		);
		return "/\.({$class_pattern})(?=$|[^a-zA-Z0-9_-])/";
	}

	/**
	 * Finalize a stylesheet group (amp-custom or amp-keyframes).
	 *
	 * @since 1.2
	 *
	 * @param int   $group        Group name (either self::STYLE_AMP_CUSTOM_GROUP_INDEX or self::STYLE_AMP_KEYFRAMES_GROUP_INDEX ).
	 * @param array $group_config Group config.
	 * @return int Number of included stylesheets in group.
	 */
	private function finalize_stylesheet_group( $group, $group_config ) {
		$included_count = 0;
		$max_bytes      = $group_config['cdata_spec']['max_bytes'] - strlen( $group_config['source_map_comment'] );

		$previously_seen_stylesheet_index = [];
		foreach ( $this->pending_stylesheets as $pending_stylesheet_index => &$pending_stylesheet ) {
			if ( $group !== $pending_stylesheet['group'] ) {
				continue;
			}

			$stylesheet_parts = [];
			$original_size    = 0;
			foreach ( $pending_stylesheet['stylesheet'] as $stylesheet_part ) {
				if ( is_string( $stylesheet_part ) ) {
					$stylesheet_parts[] = $stylesheet_part;
					$original_size     += strlen( $stylesheet_part );
					continue;
				}

				list( $selectors_parsed, $declaration_block ) = $stylesheet_part;

				$selectors = [];
				foreach ( $selectors_parsed as $selector => $parsed_selector ) {
					$should_include = (
						// If all class names are used in the doc.
						(
							empty( $parsed_selector[ self::SELECTOR_EXTRACTED_CLASSES ] )
							||
							$this->has_used_class_name( $parsed_selector[ self::SELECTOR_EXTRACTED_CLASSES ] )
						)
						&&
						// If all IDs are used in the doc.
						(
							empty( $parsed_selector[ self::SELECTOR_EXTRACTED_IDS ] )
							||
							0 === count(
								array_filter(
									$parsed_selector[ self::SELECTOR_EXTRACTED_IDS ],
									function( $id ) {
										return ! $this->dom->getElementById( $id );
									}
								)
							)
						)
						&&
						// If tag names are present in the doc.
						(
							empty( $parsed_selector[ self::SELECTOR_EXTRACTED_TAGS ] )
							||
							$this->has_used_tag_names( $parsed_selector[ self::SELECTOR_EXTRACTED_TAGS ] )
						)
						&&
						// If all attribute names are used in the doc.
						(
							empty( $parsed_selector[ self::SELECTOR_EXTRACTED_ATTRIBUTES ] )
							||
							$this->has_used_attributes( $parsed_selector[ self::SELECTOR_EXTRACTED_ATTRIBUTES ] )
						)
					);
					if ( $should_include || AMP_Debug::has_flag( AMP_Debug::DISABLE_TREE_SHAKING_QUERY_VAR ) ) {
						$selectors[] = $selector;
					}
				}
				$stylesheet_part = implode( ',', $selectors ) . $declaration_block;
				$original_size  += strlen( $stylesheet_part );
				if ( ! empty( $selectors ) ) {
					$stylesheet_parts[] = $stylesheet_part;
				}
			}

			// Strip empty at-rules after tree shaking.
			$stylesheet_part_count = count( $stylesheet_parts );
			for ( $i = 0; $i < $stylesheet_part_count; $i++ ) {
				$stylesheet_part = $stylesheet_parts[ $i ];
				if ( '@' !== substr( $stylesheet_part, 0, 1 ) ) {
					continue;
				}

				// Delete empty at-rules.
				if ( '{}' === substr( $stylesheet_part, -2 ) ) {
					$stylesheet_part_count--;
					array_splice( $stylesheet_parts, $i, 1 );
					$i--;
					continue;
				}

				// Delete at-rules that were emptied due to tree-shaking.
				if ( '{' === substr( $stylesheet_part, -1 ) ) {
					$open_braces = 1;
					for ( $j = $i + 1; $j < $stylesheet_part_count; $j++ ) {
						$stylesheet_part = $stylesheet_parts[ $j ];
						$is_at_rule      = '@' === substr( $stylesheet_part, 0, 1 );
						if ( empty( $stylesheet_part ) ) {
							continue; // There was a shaken rule.
						}

						if ( $is_at_rule && '{}' === substr( $stylesheet_part, -2 ) ) {
							continue; // The rule opens is empty from the start.
						}

						if ( $is_at_rule && '{' === substr( $stylesheet_part, -1 ) ) {
							$open_braces++;
						} elseif ( '}' === $stylesheet_part ) {
							$open_braces--;
						} else {
							break;
						}

						// Splice out the parts that are empty.
						if ( 0 === $open_braces ) {
							array_splice( $stylesheet_parts, $i, $j - $i + 1 );
							$stylesheet_part_count = count( $stylesheet_parts );
							$i--;
							continue 2;
						}
					}
				}
			}

			$pending_stylesheet['stylesheet']    = implode( '', $stylesheet_parts );
			$pending_stylesheet['original_size'] = $original_size;
			$pending_stylesheet['included']      = null; // To be determined below.
			$pending_stylesheet['size']          = strlen( $pending_stylesheet['stylesheet'] );
			$pending_stylesheet['hash']          = md5( $pending_stylesheet['stylesheet'] );

			// If this stylesheet is a duplicate of something that came before, mark the previous as not included automatically.
			if ( isset( $previously_seen_stylesheet_index[ $pending_stylesheet['hash'] ] ) ) {
				$this->pending_stylesheets[ $previously_seen_stylesheet_index[ $pending_stylesheet['hash'] ] ]['included']  = false;
				$this->pending_stylesheets[ $previously_seen_stylesheet_index[ $pending_stylesheet['hash'] ] ]['duplicate'] = true;
			}
			$previously_seen_stylesheet_index[ $pending_stylesheet['hash'] ] = $pending_stylesheet_index;
			unset( $stylesheet_parts );
		}

		unset( $pending_stylesheet );

		// Determine which stylesheets are included based on their priorities.
		$pending_stylesheet_indices = array_keys( $this->pending_stylesheets );
		usort(
			$pending_stylesheet_indices,
			function ( $a, $b ) {
				return $this->pending_stylesheets[ $a ]['priority'] - $this->pending_stylesheets[ $b ]['priority'];
			}
		);

		$current_concatenated_size = 0;
		foreach ( $pending_stylesheet_indices as $i ) {
			if ( $group !== $this->pending_stylesheets[ $i ]['group'] ) {
				continue;
			}

			// Skip duplicates.
			if ( false === $this->pending_stylesheets[ $i ]['included'] ) {
				continue;
			}

			// Report validation error if size is now too big.
			if ( $current_concatenated_size + $this->pending_stylesheets[ $i ]['size'] > $max_bytes && ! AMP_Debug::has_flag( AMP_Debug::DISABLE_TREE_SHAKING_QUERY_VAR ) ) {
				$validation_error = [
					'code'      => self::STYLESHEET_TOO_LONG,
					'type'      => AMP_Validation_Error_Taxonomy::CSS_ERROR_TYPE,
					'spec_name' => self::STYLE_AMP_KEYFRAMES_GROUP_INDEX === $group ? self::STYLE_AMP_KEYFRAMES_SPEC_NAME : self::STYLE_AMP_CUSTOM_SPEC_NAME,
				];
				if ( isset( $this->pending_stylesheets[ $i ]['sources'] ) ) {
					$validation_error['sources'] = $this->pending_stylesheets[ $i ]['sources'];
				}

				if ( $this->should_sanitize_validation_error( $validation_error, wp_array_slice_assoc( $this->pending_stylesheets[ $i ], [ 'node' ] ) ) ) {
					$this->pending_stylesheets[ $i ]['included'] = false;
					continue; // Skip to the next stylesheet.
				}
			}

			if ( ! isset( $this->pending_stylesheets[ $i ]['included'] ) ) {
				$this->pending_stylesheets[ $i ]['included'] = true;
				$included_count++;
				$current_concatenated_size += $this->pending_stylesheets[ $i ]['size'];
			}
		}

		return $included_count;
	}
}

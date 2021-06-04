<?php
/**
 * Class AMP_Theme_Support
 *
 * @package AMP
 */

use AmpProject\Amp;
use AmpProject\AmpWP\Dom\Options;
use AmpProject\AmpWP\ExtraThemeAndPluginHeaders;
use AmpProject\AmpWP\Optimizer\OptimizerService;
use AmpProject\AmpWP\Option;
use AmpProject\AmpWP\QueryVar;
use AmpProject\AmpWP\ConfigurationArgument;
use AmpProject\AmpWP\Services;
use AmpProject\Attribute;
use AmpProject\Dom\Document;
use AmpProject\Extension;
use AmpProject\Optimizer;
use AmpProject\RequestDestination;
use AmpProject\Tag;
use AmpProject\AmpWP\Optimizer\Transformer\AmpSchemaOrgMetadata;
use AmpProject\AmpWP\Optimizer\Transformer\AmpSchemaOrgMetadataConfiguration;

/**
 * Class AMP_Theme_Support
 *
 * Callbacks for adding AMP-related things when theme support is added.
 *
 * @internal
 */
class AMP_Theme_Support {

	/**
	 * Theme support slug.
	 *
	 * @var string
	 */
	const SLUG = 'amp';

	/**
	 * Slug identifying standard website mode.
	 *
	 * @since 1.2
	 * @var string
	 */
	const STANDARD_MODE_SLUG = 'standard';

	/**
	 * Slug identifying transitional website mode.
	 *
	 * @since 1.2
	 * @var string
	 */
	const TRANSITIONAL_MODE_SLUG = 'transitional';

	/**
	 * Slug identifying reader website mode.
	 *
	 * @since 1.2
	 * @var string
	 */
	const READER_MODE_SLUG = 'reader';

	/**
	 * Flag used in args passed to add_theme_support('amp') to indicate transitional mode supported.
	 *
	 * @since 1.2
	 * @var string
	 */
	const PAIRED_FLAG = 'paired';

	/**
	 * The directory name in a theme where Reader Mode templates can be.
	 *
	 * For example, this could be at your-theme-name/amp.
	 *
	 * @var string
	 */
	const READER_MODE_TEMPLATE_DIRECTORY = 'amp';

	/**
	 * Sanitizers, with keys as class names and values as arguments.
	 *
	 * @var array[]
	 */
	protected static $sanitizer_classes = [];

	/**
	 * Embed handlers.
	 *
	 * @var AMP_Base_Embed_Handler[]
	 */
	protected static $embed_handlers = [];

	/**
	 * Template types.
	 *
	 * @var array
	 */
	protected static $template_types = [
		'paged', // Deprecated.
		'index',
		'404',
		'archive',
		'author',
		'category',
		'tag',
		'taxonomy',
		'date',
		'home',
		'front_page',
		'page',
		'search',
		'single',
		'embed',
		'singular',
		'attachment',
	];

	/**
	 * Whether output buffering has started.
	 *
	 * @since 0.7
	 * @var bool
	 */
	protected static $is_output_buffering = false;

	/**
	 * Schema.org metadata
	 *
	 * @var array
	 */
	protected static $metadata;

	/**
	 * Initialize.
	 *
	 * @since 0.7
	 */
	public static function init() {
		/**
		 * Starts the server-timing measurement for the entire output buffer capture.
		 *
		 * @since 2.0
		 * @internal
		 *
		 * @param string      $event_name        Name of the event to record.
		 * @param string|null $event_description Optional. Description of the event
		 *                                       to record. Defaults to null.
		 * @param string[]    $properties        Optional. Additional properties to add
		 *                                       to the logged record.
		 * @param bool        $verbose_only      Optional. Whether to only show the
		 *                                       event in verbose mode. Defaults to
		 *                                       false.
		 */
		do_action( 'amp_server_timing_start', 'amp_output_buffer' );

		// Ensure extra theme support for core themes is in place.
		AMP_Core_Theme_Sanitizer::extend_theme_support();

		/*
		 * Note that wp action is use instead of template_redirect because some themes/plugins output
		 * the response at this action and then short-circuit with exit. So this is why the the preceding
		 * action to template_redirect--the wp action--is used instead.
		 */
		if ( ! is_admin() ) {
			add_action( 'wp', [ __CLASS__, 'finish_init' ], PHP_INT_MAX );
		}
	}

	/**
	 * Determine whether theme support was added via admin option.
	 *
	 * @since 1.0
	 * @see AMP_Theme_Support::read_theme_support()
	 * @see AMP_Theme_Support::get_support_mode()
	 * @codeCoverageIgnore
	 * @deprecated Support is always determined by the option.
	 *
	 * @return bool Support added via option.
	 */
	public static function is_support_added_via_option() {
		_deprecated_function( __METHOD__, '2.0.0' );
		return true;
	}

	/**
	 * Get the theme support mode added via admin option.
	 *
	 * @return null|string Support added via option, with null meaning Reader, and otherwise being 'standard' or 'transitional'.
	 * @see AMP_Theme_Support::read_theme_support()
	 * @see AMP_Theme_Support::TRANSITIONAL_MODE_SLUG
	 * @see AMP_Theme_Support::STANDARD_MODE_SLUG
	 *
	 * @since 1.2
	 * @deprecated
	 * @codeCoverageIgnore
	 */
	public static function get_support_mode_added_via_option() {
		_deprecated_function( __METHOD__, '2.0.0', 'AMP_Options_Manager::get_option' );
		$value = AMP_Options_Manager::get_option( Option::THEME_SUPPORT );
		if ( self::READER_MODE_SLUG === $value ) {
			$value = null;
		}
		return $value;
	}

	/**
	 * Get the theme support mode added via theme.
	 *
	 * @return null|string Support added via theme, with null meaning Reader, and otherwise being 'standard' or 'transitional'.
	 * @see AMP_Theme_Support::read_theme_support()
	 * @see AMP_Theme_Support::TRANSITIONAL_MODE_SLUG
	 * @see AMP_Theme_Support::STANDARD_MODE_SLUG
	 *
	 * @since 1.2
	 * @deprecated
	 * @codeCoverageIgnore
	 */
	public static function get_support_mode_added_via_theme() {
		_deprecated_function( __METHOD__, '2.0.0', 'current_theme_supports' );
		$theme_support_args = self::get_theme_support_args();
		if ( ! $theme_support_args ) {
			return null;
		}
		return empty( $theme_support_args[ self::PAIRED_FLAG ] ) ? self::STANDARD_MODE_SLUG : self::TRANSITIONAL_MODE_SLUG;
	}

	/**
	 * Get theme support mode.
	 *
	 * @return string Theme support mode.
	 * @see AMP_Theme_Support::read_theme_support()
	 * @see AMP_Theme_Support::TRANSITIONAL_MODE_SLUG
	 * @see AMP_Theme_Support::STANDARD_MODE_SLUG
	 *
	 * @since 1.2
	 * @deprecated
	 * @codeCoverageIgnore
	 */
	public static function get_support_mode() {
		_deprecated_function( __METHOD__, '2.0.0', 'AMP_Options_Manager::get_option' );
		return AMP_Options_Manager::get_option( Option::THEME_SUPPORT );
	}

	/**
	 * Check theme support args or add theme support if option is set in the admin.
	 *
	 * In older versions of the plugin, the DB option was only considered if the theme does not already explicitly support AMP.
	 * This is no longer the case. The DB option is the only value that is considered.
	 *
	 * @see AMP_Post_Type_Support::add_post_type_support() For where post type support is added, since it is irrespective of theme support.
	 * @deprecated
	 * @codeCoverageIgnore
	 */
	public static function read_theme_support() {
		_deprecated_function( __METHOD__, '2.0.0' );
	}

	/**
	 * Get the theme support args.
	 *
	 * This avoids having to repeatedly call `get_theme_support()`, check the args, shift an item off the array, and so on.
	 * Note that if the theme's `style.css` has the `AMP` header with a value that when converted to a boolean evaluates to `true`, then this function will return the same
	 * as if the theme had done `add_theme_support('amp')`.
	 *
	 * @since 1.0
	 *
	 * @return array|false Theme support args, or false if theme support is not present.
	 */
	public static function get_theme_support_args() {
		if ( ! current_theme_supports( self::SLUG ) ) {
			$theme_header = wp_get_theme()->get( ExtraThemeAndPluginHeaders::AMP_HEADER );
			if ( rest_sanitize_boolean( $theme_header ) && ExtraThemeAndPluginHeaders::AMP_HEADER_LEGACY !== $theme_header ) {
				return [
					self::PAIRED_FLAG => true,
				];
			}
			return false;
		}
		$support = get_theme_support( self::SLUG );
		if ( isset( $support[0] ) && is_array( $support[0] ) ) {
			$args = $support[0];
		} else {
			$args = [];
		}
		if ( ! isset( $args[ self::PAIRED_FLAG ] ) ) {
			// Formerly when paired was not supplied it defaulted to be false. However, the reality is that
			// the vast majority of themes should be built to work in AMP and non-AMP because AMP can be
			// disabled for any URL just by disabling AMP for the post.
			$args[ self::PAIRED_FLAG ] = true;
		}
		return $args;
	}

	/**
	 * Gets whether the parent or child theme supports Reader Mode.
	 *
	 * True if the theme does not call add_theme_support( 'amp' ) at all,
	 * and it has an amp/ directory for templates.
	 *
	 * @return bool Whether the theme supports Reader Mode.
	 */
	public static function supports_reader_mode() {
		return (
			! current_theme_supports( self::SLUG )
			&&
			(
				is_dir( trailingslashit( get_template_directory() ) . self::READER_MODE_TEMPLATE_DIRECTORY )
				||
				is_dir( trailingslashit( get_stylesheet_directory() ) . self::READER_MODE_TEMPLATE_DIRECTORY )
			)
		);
	}

	/**
	 * Finish initialization once query vars are set.
	 *
	 * @since 0.7
	 */
	public static function finish_init() {
		if ( ! amp_is_request() ) {
			return;
		}

		if ( amp_is_legacy() ) {
			// Make sure there is no confusion when serving the legacy Reader template that the normal theme hooks should not be used.
			remove_theme_support( self::SLUG );

			add_filter(
				'template_include',
				static function() {
					return AMP__DIR__ . '/includes/templates/reader-template-loader.php';
				},
				PHP_INT_MAX
			);
		} else {
			$theme_support = self::get_theme_support_args();
			if ( false === $theme_support ) {
				// Make sure that 'amp' theme support is present for plugins can use `current_theme_supports('amp')` as
				// a signal for whether to use standard template hooks instead of legacy Reader AMP post template hooks.
				add_theme_support( self::SLUG );
			} elseif ( ! empty( $theme_support['template_dir'] ) ) {
				self::add_amp_template_filters();
			}
		}

		self::add_hooks();
		self::$sanitizer_classes = amp_get_content_sanitizers();
		self::$sanitizer_classes = AMP_Validation_Manager::filter_sanitizer_args( self::$sanitizer_classes );
		self::$embed_handlers    = self::register_content_embed_handlers();
		self::$sanitizer_classes['AMP_Embed_Sanitizer']['embed_handlers'] = self::$embed_handlers;

		foreach ( self::$sanitizer_classes as $sanitizer_class => $args ) {
			if ( method_exists( $sanitizer_class, 'add_buffering_hooks' ) ) {
				call_user_func( [ $sanitizer_class, 'add_buffering_hooks' ], $args );
			}
		}
	}

	/**
	 * Determines whether transitional mode is available.
	 *
	 * When 'amp' theme support has not been added or canonical mode is enabled, then this returns false.
	 *
	 * @since 0.7
	 * @deprecated No longer used. Consider instead `! amp_is_canonical() && amp_is_available()`.
	 * @todo There are ecosystem plugins which are still using this method. See <https://wpdirectory.net/search/01EPD9M5CKWHJ7NMQ1WE0YGPJ5>.
	 *
	 * @see amp_is_canonical()
	 * @see amp_is_available()
	 * @return bool Whether available.
	 */
	public static function is_paired_available() {
		if ( amp_is_legacy() ) {
			return false;
		}

		if ( amp_is_canonical() ) {
			return false;
		}

		$availability = self::get_template_availability();
		return $availability['supported'];
	}

	/**
	 * Determine whether the user is in the Customizer preview iframe.
	 *
	 * @since 0.7
	 *
	 * @return bool Whether in Customizer preview iframe.
	 */
	public static function is_customize_preview_iframe() {
		global $wp_customize;
		return is_customize_preview() && $wp_customize->get_messenger_channel();
	}

	/**
	 * Register filters for loading AMP-specific templates.
	 */
	public static function add_amp_template_filters() {
		foreach ( self::$template_types as $template_type ) {
			// See get_query_template().
			$template_type = preg_replace( '|[^a-z0-9-]+|', '', $template_type );

			add_filter( "{$template_type}_template_hierarchy", [ __CLASS__, 'filter_amp_template_hierarchy' ] );
		}
	}

	/**
	 * Determine template availability of AMP for the given query.
	 *
	 * This is not intended to return whether AMP is available for a _specific_ post. For that, use `amp_is_post_supported()`.
	 *
	 * @since 1.0
	 * @global WP_Query $wp_query
	 * @see amp_is_post_supported()
	 *
	 * @param WP_Query|WP_Post|null $query Query or queried post. If null then the global query will be used.
	 * @return array {
	 *     Template availability.
	 *
	 *     @type bool        $supported Whether the template is supported in AMP.
	 *     @type string|null $template  The ID of the matched template (conditional), such as 'is_singular', or null if nothing was matched.
	 *     @type string[]    $errors    List of the errors or reasons for why the template is not available.
	 * }
	 */
	public static function get_template_availability( $query = null ) {
		global $wp_query;
		if ( ! $query ) {
			$query = $wp_query;
		} elseif ( $query instanceof WP_Post ) {
			$post  = $query;
			$query = new WP_Query();
			if ( 'page' === $post->post_type ) {
				$query->set( 'page_id', $post->ID );
			} else {
				$query->set( 'p', $post->ID );
			}
			$query->queried_object    = $post;
			$query->queried_object_id = $post->ID;
			$query->parse_query_vars();
		}

		$default_response = [
			'errors'    => [],
			'supported' => false,
			'immutable' => false, // Obsolete.
			'template'  => null,
		];

		if ( amp_is_legacy() ) {
			return array_merge(
				$default_response,
				[ 'errors' => [ 'legacy_reader_mode' ] ]
			);
		}

		if ( ! ( $query instanceof WP_Query ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'No WP_Query available.', 'amp' ), '1.0' );
			return array_merge(
				$default_response,
				[ 'errors' => [ 'no_query_available' ] ]
			);
		}

		$all_templates_supported = AMP_Options_Manager::get_option( Option::ALL_TEMPLATES_SUPPORTED );

		// Make sure global $wp_query is set in case of conditionals that unfortunately look at global scope.
		$prev_query = $wp_query;
		$wp_query   = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$matching_templates    = [];
		$supportable_templates = self::get_supportable_templates();
		foreach ( $supportable_templates as $id => $supportable_template ) {
			if ( empty( $supportable_template['callback'] ) ) {
				$callback = $id;
			} else {
				$callback = $supportable_template['callback'];
			}

			// If the callback is a method on the query, then call the method on the query itself.
			if ( is_string( $callback ) && 'is_' === substr( $callback, 0, 3 ) && method_exists( $query, $callback ) ) {
				$is_match = call_user_func( [ $query, $callback ] );
			} elseif ( is_callable( $callback ) ) {
				$is_match = $callback( $query );
			} else {
				/* translators: %s: the supportable template ID. */
				_doing_it_wrong( __FUNCTION__, esc_html( sprintf( __( 'Supportable template "%s" does not have a callable callback.', 'amp' ), $id ) ), '1.0' );
				$is_match = false;
			}

			if ( $is_match ) {
				$matching_templates[ $id ] = [
					'template'  => $id,
					'supported' => ! empty( $supportable_template['supported'] ),
				];
			}
		}

		// Restore previous $wp_query (if any).
		$wp_query = $prev_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Make sure children override their parents.
		$matching_template_ids = array_keys( $matching_templates );
		foreach ( array_diff( array_keys( $supportable_templates ), $matching_template_ids ) as $template_id ) {
			unset( $supportable_templates[ $template_id ] );
		}
		foreach ( $matching_template_ids as $id ) {
			$has_children = false;
			foreach ( $supportable_templates as $other_id => $supportable_template ) {
				if ( $other_id === $id ) {
					continue;
				}
				if ( isset( $supportable_template['parent'] ) && $id === $supportable_template['parent'] ) {
					$has_children = true;
					break;
				}
			}

			// Delete all matching parent templates since the child will override them.
			if ( ! $has_children ) {
				$supportable_template = $supportable_templates[ $id ];
				while ( ! empty( $supportable_template['parent'] ) ) {
					$parent = $supportable_template['parent'];

					/*
					 * If the parent is not amongst the supportable templates, then something is off in terms of hierarchy.
					 * Either the matching is off-track, or the template is badly configured.
					 */
					if ( ! array_key_exists( $parent, $supportable_templates ) ) {
						_doing_it_wrong(
							__METHOD__,
							esc_html(
								sprintf(
									/* translators: %s: amp_supportable_templates */
									__( 'An expected parent was not found. Did you filter %s to not honor the template hierarchy?', 'amp' ),
									'amp_supportable_templates'
								)
							),
							'1.4'
						);
						break;
					}

					$supportable_template = $supportable_templates[ $parent ];

					// Let the child supported status override the parent's supported status.
					unset( $matching_templates[ $parent ] );
				}
			}
		}

		// If there is more than 1 matching template, the is_home() condition is the default so discard it if there are other matching templates.
		if ( count( $matching_templates ) > 1 && isset( $matching_templates['is_home'] ) ) {
			unset( $matching_templates['is_home'] );
		}

		/*
		 * When there is still more than one matching template, account for ambiguous cases, informed by the order in template-loader.php.
		 * See <https://github.com/WordPress/wordpress-develop/blob/5.1.0/src/wp-includes/template-loader.php#L49-L68>.
		 */
		if ( count( $matching_templates ) > 1 ) {
			$template_conditional_priority_order = [
				'is_embed',
				'is_404',
				'is_search',
				'is_front_page',
				'is_home',
				'is_post_type_archive',
				'is_tax',
				'is_attachment',
				'is_single',
				'is_page',
				'is_singular',
				'is_category',
				'is_tag',
				'is_author',
				'is_date',
				'is_archive',
			];

			// Obtain the template conditionals for each matching template ID (e.g. 'is_post_type_archive[product]' => 'is_post_type_archive').
			$template_conditional_id_mapping = [];
			foreach ( array_keys( $matching_templates ) as $template_id ) {
				$template_conditional_id_mapping[ strtok( $template_id, '[' ) ] = $template_id;
			}

			// If there are any custom supportable templates, only consider them since they would override the conditional logic in core.
			$custom_template_conditions = array_diff(
				array_keys( $template_conditional_id_mapping ),
				$template_conditional_priority_order
			);
			if ( ! empty( $custom_template_conditions ) ) {
				$matching_templates = wp_array_slice_assoc(
					$matching_templates,
					array_values( wp_array_slice_assoc( $template_conditional_id_mapping, $custom_template_conditions ) )
				);
			} else {
				/*
				 * Otherwise, iterate over the template conditionals in the order they occur in the if/elseif/else conditional chain.
				 * to then populate $matching_templates with just this one entry.
				 */
				foreach ( $template_conditional_priority_order as $template_conditional ) {
					if ( isset( $template_conditional_id_mapping[ $template_conditional ] ) ) {
						$template_id        = $template_conditional_id_mapping[ $template_conditional ];
						$matching_templates = [
							$template_id => $matching_templates[ $template_id ],
						];
						break;
					}
				}
			}
		}

		/*
		 * If there are more than one matching templates, then something is probably not right.
		 * Template conditions need to be set up properly to prevent this from happening.
		 */
		if ( count( $matching_templates ) > 1 ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html(
					sprintf(
						/* translators: %s: amp_supportable_templates */
						__( 'Did not expect there to be more than one matching template. Did you filter %s to not honor the template hierarchy?', 'amp' ),
						'amp_supportable_templates'
					)
				),
				'1.0'
			);
		}

		$matching_template = array_shift( $matching_templates );

		// If there aren't any matching templates left that are supported, then we consider it to not be available.
		if ( ! $matching_template ) {
			if ( $all_templates_supported ) {
				return array_merge(
					$default_response,
					[
						'supported' => true,
					]
				);
			}

			return array_merge(
				$default_response,
				[ 'errors' => [ 'no_matching_template' ] ]
			);
		}
		$matching_template = array_merge( $default_response, $matching_template );

		// If there aren't any matching templates left that are supported, then we consider it to not be available.
		if ( empty( $matching_template['supported'] ) ) {
			$matching_template['errors'][] = 'template_unsupported';
		}

		// For singular queries, amp_is_post_supported() is given the final say.
		if ( $query->is_singular() || $query->is_posts_page ) {
			/**
			 * Queried object.
			 *
			 * @var WP_Post $queried_object
			 */
			$queried_object = $query->get_queried_object();
			if ( $queried_object instanceof WP_Post ) {
				$support_errors = AMP_Post_Type_Support::get_support_errors( $queried_object );
				if ( ! empty( $support_errors ) ) {
					$matching_template['errors']    = array_merge( $matching_template['errors'], $support_errors );
					$matching_template['supported'] = false;
				}
			}
		}

		return $matching_template;
	}

	/**
	 * Get the templates which can be supported.
	 *
	 * @return array Supportable templates.
	 */
	public static function get_supportable_templates() {
		$templates = [
			'is_singular' => [
				'label' => __( 'Singular', 'amp' ),
			],
		];
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$templates['is_front_page'] = [
				'label'  => __( 'Homepage', 'amp' ),
				'parent' => 'is_singular',
			];
			if ( AMP_Post_Meta_Box::DISABLED_STATUS === get_post_meta( get_option( 'page_on_front' ), AMP_Post_Meta_Box::STATUS_POST_META_KEY, true ) ) {
				/* translators: %s: the URL to the edit post screen. */
				$templates['is_front_page']['description'] = sprintf( __( 'Currently disabled at the <a href="%s">page level</a>.', 'amp' ), esc_url( get_edit_post_link( get_option( 'page_on_front' ) ) ) );
			}

			// In other words, same as is_posts_page, *but* it not is_singular.
			$templates['is_home'] = [
				'label' => __( 'Blog', 'amp' ),
			];
			if ( AMP_Post_Meta_Box::DISABLED_STATUS === get_post_meta( get_option( 'page_for_posts' ), AMP_Post_Meta_Box::STATUS_POST_META_KEY, true ) ) {
				/* translators: %s: the URL to the edit post screen. */
				$templates['is_home']['description'] = sprintf( __( 'Currently disabled at the <a href="%s">page level</a>.', 'amp' ), esc_url( get_edit_post_link( get_option( 'page_for_posts' ) ) ) );
			}
		} else {
			$templates['is_home'] = [
				'label' => __( 'Homepage', 'amp' ),
			];
		}

		$templates = array_merge(
			$templates,
			[
				'is_archive' => [
					'label' => __( 'Archives', 'amp' ),
				],
				'is_author'  => [
					'label'  => __( 'Author', 'amp' ),
					'parent' => 'is_archive',
				],
				'is_date'    => [
					'label'  => __( 'Date', 'amp' ),
					'parent' => 'is_archive',
				],
				'is_search'  => [
					'label' => __( 'Search', 'amp' ),
				],
				'is_404'     => [
					'label' => __( 'Not Found (404)', 'amp' ),
				],
			]
		);

		if ( taxonomy_exists( 'category' ) ) {
			$templates['is_category'] = [
				'label'  => get_taxonomy( 'category' )->labels->name,
				'parent' => 'is_archive',
			];
		}
		if ( taxonomy_exists( 'post_tag' ) ) {
			$templates['is_tag'] = [
				'label'  => get_taxonomy( 'post_tag' )->labels->name,
				'parent' => 'is_archive',
			];
		}

		$taxonomy_args = [
			'_builtin' => false,
			'public'   => true,
		];
		foreach ( get_taxonomies( $taxonomy_args, 'objects' ) as $taxonomy ) {
			$templates[ sprintf( 'is_tax[%s]', $taxonomy->name ) ] = [
				'label'    => $taxonomy->labels->name,
				'parent'   => 'is_archive',
				'callback' => static function ( WP_Query $query ) use ( $taxonomy ) {
					return $query->is_tax( $taxonomy->name );
				},
			];
		}

		$post_type_args = [
			'has_archive' => true,
			'public'      => true,
		];
		foreach ( get_post_types( $post_type_args, 'objects' ) as $post_type ) {
			$templates[ sprintf( 'is_post_type_archive[%s]', $post_type->name ) ] = [
				'label'    => $post_type->labels->archives,
				'parent'   => 'is_archive',
				'callback' => static function ( WP_Query $query ) use ( $post_type ) {
					return $query->is_post_type_archive( $post_type->name );
				},
			];
		}

		/**
		 * Filters list of supportable templates.
		 *
		 * Each array item should have a key that corresponds to a template conditional function.
		 * If the key is such a function, then the key is used to evaluate whether the given template
		 * entry is a match. Otherwise, a supportable template item can include a callback value which
		 * is used instead. Each item needs a 'label' value. Additionally, if the supportable template
		 * is a subset of another condition (e.g. is_singular > is_single) then this relationship needs
		 * to be indicated via the 'parent' value.
		 *
		 * @since 1.0
		 *
		 * @param array $templates Supportable templates.
		 */
		$templates = apply_filters( 'amp_supportable_templates', $templates );

		$supported_templates = AMP_Options_Manager::get_option( Option::SUPPORTED_TEMPLATES );
		$are_all_supported   = AMP_Options_Manager::get_option( Option::ALL_TEMPLATES_SUPPORTED );

		$did_filter_supply_supported = false;
		$did_filter_supply_immutable = false;
		foreach ( $templates as $id => &$template ) {
			if ( isset( $template['supported'] ) ) {
				$did_filter_supply_supported = true;
			}
			if ( isset( $template['immutable'] ) ) {
				$did_filter_supply_immutable = true;
			}

			$template['supported']      = $are_all_supported || in_array( $id, $supported_templates, true );
			$template['user_supported'] = $template['supported']; // Obsolete.
			$template['immutable']      = false; // Obsolete.
		}

		if ( $did_filter_supply_supported ) {
			_doing_it_wrong(
				'add_filter',
				esc_html__( 'The AMP plugin no longer allows `amp_supportable_templates` filters to specify a template as being `supported`. This is now managed only in AMP Settings.', 'amp' ),
				'2.0.0'
			);
		}
		if ( $did_filter_supply_immutable ) {
			_doing_it_wrong(
				'add_filter',
				esc_html__( 'The AMP plugin no longer allows `amp_supportable_templates` filters to specify a template\'s support as being `immutable`. This is now managed only in AMP Settings.', 'amp' ),
				'2.0.0'
			);
		}

		return $templates;
	}

	/**
	 * Register hooks.
	 */
	public static function add_hooks() {

		// Remove core actions which are invalid AMP.
		remove_action( 'wp_head', 'wp_post_preview_js', 1 );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );

		// Replace JS-based emoji with PHP-based, if the JS-based emoji replacement was not already removed.
		if ( has_action( 'wp_head', 'print_emoji_detection_script' ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_footer', 'gutenberg_the_skip_link' ); // Temporary workaround for <https://github.com/ampproject/amp-wp/issues/6115>.
			remove_action( 'wp_footer', 'the_block_template_skip_link' ); // Temporary workaround for <https://github.com/ampproject/amp-wp/issues/6115>.
			add_action( 'wp_print_styles', [ __CLASS__, 'print_emoji_styles' ] );
			add_filter( 'the_title', 'wp_staticize_emoji' );
			add_filter( 'the_excerpt', 'wp_staticize_emoji' );
			add_filter( 'the_content', 'wp_staticize_emoji' );
			add_filter( 'comment_text', 'wp_staticize_emoji' );
			add_filter( 'widget_text', 'wp_staticize_emoji' );
		}

		// @todo The wp_mediaelement_fallback() should still run to be injected inside of the audio/video generated by wp_audio_shortcode()/wp_video_shortcode() respectively.
		// Prevent MediaElement.js scripts/styles from being enqueued.
		add_filter(
			'wp_video_shortcode_library',
			static function() {
				return 'amp';
			}
		);
		add_filter(
			'wp_audio_shortcode_library',
			static function() {
				return 'amp';
			}
		);

		// Don't show loading indicator on custom logo since it makes most sense for larger images.
		add_filter(
			'get_custom_logo',
			static function( $html ) {
				return preg_replace( '/(?<=<img\s)/', ' data-amp-noloading="" ', $html );
			},
			1
		);

		add_action( 'admin_bar_init', [ __CLASS__, 'init_admin_bar' ] );
		add_action( 'wp_head', 'amp_add_generator_metadata', 20 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ], 0 ); // Enqueue before theme's styles.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'dequeue_customize_preview_scripts' ], 1000 );
		add_filter( 'customize_partial_render', [ __CLASS__, 'filter_customize_partial_render' ] );
		if ( is_customize_preview() ) {
			add_filter( 'style_loader_tag', [ __CLASS__, 'filter_customize_preview_style_loader_tag' ], 10, 2 );
		}

		add_action( 'wp_footer', 'amp_print_analytics' );

		/*
		 * Start output buffering at very low priority for sake of plugins and themes that use template_redirect
		 * instead of template_include.
		 */
		$priority = defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~PHP_INT_MAX; // phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
		add_action( 'template_redirect', [ __CLASS__, 'start_output_buffering' ], $priority );

		// Commenting hooks.
		add_filter( 'comment_form_defaults', [ __CLASS__, 'filter_comment_form_defaults' ], PHP_INT_MAX );
		add_filter( 'comment_reply_link', [ __CLASS__, 'filter_comment_reply_link' ], 10, 4 );
		add_filter( 'cancel_comment_reply_link', [ __CLASS__, 'filter_cancel_comment_reply_link' ], 10, 3 );
		remove_action( 'comment_form', 'wp_comment_form_unfiltered_html_nonce' );
		add_filter( 'wp_kses_allowed_html', [ __CLASS__, 'include_layout_in_wp_kses_allowed_html' ], 10 );
		add_filter( 'get_header_image_tag', [ __CLASS__, 'amend_header_image_with_video_header' ], PHP_INT_MAX );
		add_action(
			'wp_print_footer_scripts',
			static function() {
				wp_dequeue_script( 'wp-custom-header' );
			},
			0
		);
		add_action(
			'wp_enqueue_scripts',
			static function() {
				wp_dequeue_script( 'comment-reply' ); // Handled largely by AMP_Comments_Sanitizer and *reply* methods in this class.
			}
		);

		if ( amp_is_bento_enabled() ) {
			add_action(
				'wp_head',
				static function () {
					?>
					<script data-ampdevmode>
						(self.AMP = self.AMP || []).push(function (AMP) {
							AMP.toggleExperiment('bento', true);
						});
					</script>
					<?php
				}
			);
		}
	}

	/**
	 * Register/override widgets.
	 *
	 * @deprecated As of 2.0 the AMP_Core_Block_Handler will sanitize the core widgets instead.
	 * @global WP_Widget_Factory
	 * @return void
	 */
	public static function register_widgets() {
		_deprecated_function( __METHOD__, '2.0.0' );
		global $wp_widget_factory;
		foreach ( $wp_widget_factory->widgets as $registered_widget ) {
			$registered_widget_class_name = get_class( $registered_widget );
			if ( ! preg_match( '/^WP_Widget_(.+)$/', $registered_widget_class_name, $matches ) ) {
				continue;
			}
			$amp_class_name = 'AMP_Widget_' . $matches[1];
			if ( ! class_exists( $amp_class_name ) || is_a( $amp_class_name, $registered_widget_class_name ) ) {
				continue;
			}

			unregister_widget( $registered_widget_class_name );
			register_widget( $amp_class_name );
		}
	}

	/**
	 * Register content embed handlers.
	 *
	 * This was copied from `AMP_Content::register_embed_handlers()` due to being a private method
	 * and due to `AMP_Content` not being well suited for use in AMP canonical.
	 *
	 * @see AMP_Content::register_embed_handlers()
	 * @global int $content_width
	 * @return AMP_Base_Embed_Handler[] Handlers.
	 */
	public static function register_content_embed_handlers() {
		global $content_width;

		$embed_handlers = [];
		foreach ( amp_get_content_embed_handlers() as $embed_handler_class => $args ) {

			/**
			 * Embed handler.
			 *
			 * @type AMP_Base_Embed_Handler $embed_handler
			 */
			$embed_handler = new $embed_handler_class(
				array_merge(
					[
						'content_max_width' => ! empty( $content_width ) ? $content_width : AMP_Post_Template::CONTENT_MAX_WIDTH, // Back-compat.
					],
					$args
				)
			);

			if ( ! $embed_handler instanceof AMP_Base_Embed_Handler ) {
				_doing_it_wrong(
					__METHOD__,
					esc_html(
						sprintf(
							/* translators: 1: embed handler. 2: AMP_Embed_Handler */
							__( 'Embed Handler (%1$s) must extend `%2$s`', 'amp' ),
							esc_html( $embed_handler_class ),
							'AMP_Embed_Handler'
						)
					),
					'0.1'
				);
				continue;
			}

			$embed_handler->register_embed();
			$embed_handlers[] = $embed_handler;
		}

		return $embed_handlers;
	}

	/**
	 * Add the comments template placeholder marker
	 *
	 * @codeCoverageIgnore
	 * @deprecated 1.1.0 This functionality was moved to AMP_Comments_Sanitizer
	 *
	 * @param array $args the args for the comments list.
	 * @return array Args to return.
	 */
	public static function set_comments_walker( $args ) {
		_deprecated_function( __METHOD__, '1.1' );
		$amp_walker     = new AMP_Comment_Walker();
		$args['walker'] = $amp_walker;
		return $args;
	}

	/**
	 * Prepends template hierarchy with template_dir for AMP transitional mode templates.
	 *
	 * @param array $templates Template hierarchy.
	 * @return array Templates.
	 */
	public static function filter_amp_template_hierarchy( $templates ) {
		$args = self::get_theme_support_args();
		if ( isset( $args['template_dir'] ) ) {
			$amp_templates = [];
			foreach ( $templates as $template ) {
				$amp_templates[] = $args['template_dir'] . '/' . $template; // Let template_dir have precedence.
				$amp_templates[] = $template;
			}
			$templates = $amp_templates;
		}
		return $templates;
	}

	/**
	 * Get the canonical/self (non-paired) URL for current request.
	 *
	 * For paired AMP sites, this is not the actual "canonical" URL but rather just the non-amphtml version of the current
	 * paired URL. For AMP-first sites, this returns just the current self URL. If desiring to have an actual semantically
	 * canonical URL, then a plugin should be added which adds the desired link to the page. As it stands, this method
	 * is only used to add canonical links when a page lacks it in the first place.
	 *
	 * @link https://www.ampproject.org/docs/reference/spec#canon.
	 *
	 * @return string Canonical/self URL.
	 */
	public static function get_current_canonical_url() {
		$current_url = amp_get_current_url();
		if ( ! amp_is_canonical() ) {
			$current_url = amp_remove_paired_endpoint( $current_url );
		}
		return $current_url;
	}

	/**
	 * Get the ID for the amp-state.
	 *
	 * @since 0.7
	 *
	 * @param int $post_id Post ID.
	 * @return string ID for amp-state.
	 */
	public static function get_comment_form_state_id( $post_id ) {
		return sprintf( 'commentform_post_%d', $post_id );
	}

	/**
	 * Filter comment form args to an element with [text] AMP binding wrap the title reply.
	 *
	 * @since 0.7
	 * @see comment_form()
	 *
	 * @param array $default_args Comment form arg defaults.
	 * @return array Filtered comment form args.
	 */
	public static function filter_comment_form_defaults( $default_args ) {

		// Obtain the actual args provided to the comment_form() function since it is not available in the filter.
		$args      = [];
		$backtrace = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Due to limitation in WordPress core.
		foreach ( $backtrace as $call ) {
			if ( 'comment_form' === $call['function'] ) {
				$args = isset( $call['args'][0] ) ? $call['args'][0] : [];
				break;
			}
		}

		// Abort if the comment_form() was called with arguments which we cannot override the defaults for.
		// @todo This and the debug_backtrace() call above would be unnecessary if WordPress had a comment_form_args filter.
		$overridden_keys = [ 'cancel_reply_before', 'title_reply', 'title_reply_before', 'title_reply_to' ];
		foreach ( $overridden_keys as $key ) {
			if ( array_key_exists( $key, $args ) && array_key_exists( $key, $default_args ) && $default_args[ $key ] !== $args[ $key ] ) {
				return $default_args;
			}
		}

		$state_id     = self::get_comment_form_state_id( get_the_ID() );
		$text_binding = sprintf(
			'%s.replyToName ? %s : %s',
			$state_id,
			str_replace(
				'%s',
				sprintf( '" + %s.replyToName + "', $state_id ),
				wp_json_encode( $default_args['title_reply_to'], JSON_UNESCAPED_UNICODE )
			),
			wp_json_encode( $default_args['title_reply'], JSON_UNESCAPED_UNICODE )
		);

		$default_args['title_reply_before'] .= sprintf(
			'<span [text]="%s">',
			esc_attr( $text_binding )
		);
		$default_args['cancel_reply_before'] = '</span>' . $default_args['cancel_reply_before'];
		return $default_args;
	}

	/**
	 * Modify the comment reply link for AMP.
	 *
	 * @since 0.7
	 * @see get_comment_reply_link()
	 *
	 * @param string     $link    The HTML markup for the comment reply link.
	 * @param array      $args    An array of arguments overriding the defaults.
	 * @param WP_Comment $comment The object of the comment being replied.
	 * @return string Comment reply link.
	 */
	public static function filter_comment_reply_link( $link, $args, $comment ) {

		// Continue to show default link to wp-login when user is not logged-in.
		if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
			return $args['before'] . $link . $args['after'];
		}

		$state_id  = self::get_comment_form_state_id( get_the_ID() );
		$tap_state = [
			$state_id => [
				'replyToName' => $comment->comment_author,
				'values'      => [
					'comment_parent' => (string) $comment->comment_ID,
				],
			],
		];

		// @todo Figure out how to support add_below. Instead of moving the form, what about letting the form get a fixed position?
		$link = sprintf(
			'<a rel="nofollow" class="comment-reply-link" href="%s" on="%s" aria-label="%s">%s</a>',
			esc_attr( '#' . $args['respond_id'] ),
			esc_attr( sprintf( 'tap:AMP.setState( %s )', wp_json_encode( $tap_state, JSON_UNESCAPED_UNICODE ) ) ),
			esc_attr( sprintf( $args['reply_to_text'], $comment->comment_author ) ),
			$args['reply_text']
		);
		return $args['before'] . $link . $args['after'];
	}

	/**
	 * Filters the cancel comment reply link HTML.
	 *
	 * @since 0.7
	 * @see get_cancel_comment_reply_link()
	 *
	 * @param string $formatted_link The HTML-formatted cancel comment reply link.
	 * @param string $link           Cancel comment reply link URL.
	 * @param string $text           Cancel comment reply link text.
	 * @return string Cancel reply link.
	 */
	public static function filter_cancel_comment_reply_link( $formatted_link, $link, $text ) {
		if ( empty( $text ) ) {
			$text = __( 'Click here to cancel reply.', 'default' );
		}

		$state_id  = self::get_comment_form_state_id( get_the_ID() );
		$tap_state = [
			$state_id => [
				'replyToName' => '',
				'values'      => [
					'comment_parent' => '0',
				],
			],
		];

		$respond_id = 'respond'; // Hard-coded in comment_form() and default value in get_comment_reply_link().
		return sprintf(
			'<a id="cancel-comment-reply-link" href="%s" %s [hidden]="%s" on="%s">%s</a>',
			esc_url( remove_query_arg( 'replytocom' ) . '#' . $respond_id ),
			isset( $_GET['replytocom'] ) ? '' : ' hidden', // phpcs:ignore
			esc_attr( sprintf( '%s.values.comment_parent == "0"', self::get_comment_form_state_id( get_the_ID() ) ) ),
			esc_attr( sprintf( 'tap:AMP.setState( %s )', wp_json_encode( $tap_state, JSON_UNESCAPED_UNICODE ) ) ),
			esc_html( $text )
		);
	}

	/**
	 * Configure the admin bar for AMP.
	 *
	 * @since 1.0
	 */
	public static function init_admin_bar() {
		add_filter( 'style_loader_tag', [ __CLASS__, 'filter_admin_bar_style_loader_tag' ], 10, 2 );
		add_filter( 'script_loader_tag', [ __CLASS__, 'filter_admin_bar_script_loader_tag' ], 10, 2 );

		// Inject the data-ampdevmode attribute into the admin bar bump style. See \WP_Admin_Bar::initialize().
		if ( current_theme_supports( 'admin-bar' ) ) {
			$admin_bar_args  = get_theme_support( 'admin-bar' );
			$header_callback = $admin_bar_args[0]['callback'];
		} else {
			$header_callback = '_admin_bar_bump_cb';
		}
		remove_action( 'wp_head', $header_callback );
		if ( '__return_false' !== $header_callback ) {
			ob_start();
			$header_callback();
			$style = ob_get_clean();
			$data  = trim( preg_replace( '#<style[^>]*>(.*)</style>#is', '$1', $style ) ); // See wp_add_inline_style().

			// Override AMP's position:relative on the body for the sake of the AMP viewer, which is not relevant an an Admin Bar context.
			if ( amp_is_dev_mode() ) {
				$data .= 'html:not(#_) > body { position:unset !important; }';
			}

			wp_add_inline_style( 'admin-bar', $data );
		}

		// Emulate customize support script in PHP, to assume Customizer.
		add_action(
			'admin_bar_menu',
			static function() {
				remove_action( 'wp_before_admin_bar_render', 'wp_customize_support_script' );
			},
			41
		);
		add_filter(
			'body_class',
			static function( $body_classes ) {
				return array_merge(
					array_diff(
						$body_classes,
						[ 'no-customize-support' ]
					),
					[ 'customize-support' ]
				);
			}
		);
	}

	/**
	 * Recursively determine if a given dependency depends on another.
	 *
	 * @since 1.3
	 *
	 * @param WP_Dependencies $dependencies      Dependencies.
	 * @param string          $current_handle    Current handle.
	 * @param string          $dependency_handle Dependency handle.
	 * @return bool Whether the current handle is a dependency of the dependency handle.
	 */
	protected static function has_dependency( WP_Dependencies $dependencies, $current_handle, $dependency_handle ) {
		if ( $current_handle === $dependency_handle ) {
			return true;
		}
		if ( ! isset( $dependencies->registered[ $current_handle ] ) ) {
			return false;
		}
		foreach ( $dependencies->registered[ $current_handle ]->deps as $handle ) {
			if ( self::has_dependency( $dependencies, $handle, $dependency_handle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a handle is exclusively a dependency of another handle.
	 *
	 * For example, check if dashicons is being added exclusively because it is a dependency of admin-bar, as opposed
	 * to being added because it was directly enqueued by a theme or a dependency of some other style.
	 *
	 * @since 1.4.2
	 *
	 * @param WP_Dependencies $dependencies      Dependencies.
	 * @param string          $dependency_handle Dependency handle.
	 * @param string          $dependent_handle  Dependent handle.
	 * @return bool Whether the $handle is exclusively a handle of the $exclusive_dependency handle.
	 */
	protected static function is_exclusively_dependent( WP_Dependencies $dependencies, $dependency_handle, $dependent_handle ) {

		// If a dependency handle is the same as the dependent handle, then this self-referential relationship is exclusive.
		if ( $dependency_handle === $dependent_handle ) {
			return true;
		}

		// Short-circuit if there is no dependency relationship up front.
		if ( ! self::has_dependency( $dependencies, $dependent_handle, $dependency_handle ) ) {
			return false;
		}

		// Check whether any enqueued handle depends on the dependency.
		foreach ( $dependencies->queue as $queued_handle ) {
			// Skip considering the dependent handle.
			if ( $dependent_handle === $queued_handle ) {
				continue;
			}

			// If the dependency handle was directly enqueued, then it is not exclusively dependent.
			if ( $dependency_handle === $queued_handle ) {
				return false;
			}

			// Otherwise, if the dependency handle is depended on by the queued handle while at the same time the queued
			// handle _does_ have a dependency on the supplied dependent handle, then the dependency handle is not
			// exclusively dependent on the dependent handle.
			if (
				self::has_dependency( $dependencies, $queued_handle, $dependency_handle )
				&&
				! self::has_dependency( $dependencies, $queued_handle, $dependent_handle )
			) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Add data-ampdevmode attribute to any enqueued style that depends on the admin-bar.
	 *
	 * @since 1.3
	 *
	 * @param string $tag    The link tag for the enqueued style.
	 * @param string $handle The style's registered handle.
	 * @return string Tag.
	 */
	public static function filter_admin_bar_style_loader_tag( $tag, $handle ) {
		if (
			is_array( wp_styles()->registered['admin-bar']->deps ) && in_array( $handle, wp_styles()->registered['admin-bar']->deps, true ) ?
				self::is_exclusively_dependent( wp_styles(), $handle, 'admin-bar' ) :
				self::has_dependency( wp_styles(), $handle, 'admin-bar' )
		) {
			$tag = preg_replace( '/(?<=<link)(?=\s|>)/i', ' ' . AMP_Rule_Spec::DEV_MODE_ATTRIBUTE, $tag );
		}
		return $tag;
	}

	/**
	 * Add data-ampdevmode attribute to any enqueued style that depends on the `customizer-preview` handle.
	 *
	 * @since 2.0
	 *
	 * @param string $tag    The link tag for the enqueued style.
	 * @param string $handle The style's registered handle.
	 * @return string Tag.
	 */
	public static function filter_customize_preview_style_loader_tag( $tag, $handle ) {
		$customize_preview = 'customize-preview';
		if (
			is_array( wp_styles()->registered[ $customize_preview ]->deps ) && in_array( $handle, wp_styles()->registered[ $customize_preview ]->deps, true )
				? self::is_exclusively_dependent( wp_styles(), $handle, $customize_preview )
				: self::has_dependency( wp_styles(), $handle, $customize_preview )
		) {
			$tag = preg_replace( '/(?<=<link)(?=\s|>)/i', ' ' . AMP_Rule_Spec::DEV_MODE_ATTRIBUTE, $tag );
		}

		return $tag;
	}

	/**
	 * Add data-ampdevmode attribute to any enqueued script that depends on the admin-bar.
	 *
	 * @since 1.3
	 *
	 * @param string $tag    The `<script>` tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 * @return string Tag.
	 */
	public static function filter_admin_bar_script_loader_tag( $tag, $handle ) {
		if (
			is_array( wp_scripts()->registered['admin-bar']->deps ) && in_array( $handle, wp_scripts()->registered['admin-bar']->deps, true ) ?
				self::is_exclusively_dependent( wp_scripts(), $handle, 'admin-bar' ) :
				self::has_dependency( wp_scripts(), $handle, 'admin-bar' )
		) {
			$tag = preg_replace( '/(?<=<script)(?=\s|>)/i', ' ' . AMP_Rule_Spec::DEV_MODE_ATTRIBUTE, $tag );
		}
		return $tag;
	}

	/**
	 * Ensure the markup exists as required by AMP and elements are in the optimal loading order.
	 *
	 * Ensure meta[charset], meta[name=viewport], and link[rel=canonical] exist, as the validating sanitizer
	 * may have removed an illegal meta[http-equiv] or meta[name=viewport]. For a singular post, core only outputs a
	 * canonical URL by default. Adds the preload links.
	 *
	 * @since 0.7
	 * @link https://www.ampproject.org/docs/reference/spec#required-markup
	 * @link https://amp.dev/documentation/guides-and-tutorials/optimize-and-measure/optimize_amp/
	 * @todo All of this might be better placed inside of a sanitizer.
	 *
	 * @param Document $dom            Document.
	 * @param string[] $script_handles AMP script handles for components identified during output buffering.
	 */
	public static function ensure_required_markup( Document $dom, $script_handles = [] ) {
		// Gather all links.
		$links = [
			Attribute::REL_PRECONNECT => [
				// Include preconnect link for AMP CDN for browsers that don't support preload.
				AMP_DOM_Utils::create_node(
					$dom,
					Tag::LINK,
					[
						Attribute::REL  => Attribute::REL_PRECONNECT,
						Attribute::HREF => 'https://cdn.ampproject.org',
					]
				),
			],
		];

		$link_elements = $dom->head->getElementsByTagName( Tag::LINK );

		/**
		 * Link element.
		 *
		 * @var DOMElement $link
		 */
		foreach ( $link_elements as $link ) {
			if ( $link->hasAttribute( Attribute::REL ) ) {
				$links[ $link->getAttribute( Attribute::REL ) ][] = $link;
			}
		}

		// Ensure rel=canonical link.
		$rel_canonical = null;
		if ( empty( $links['canonical'] ) ) {
			$rel_canonical = AMP_DOM_Utils::create_node(
				$dom,
				Tag::LINK,
				[
					Attribute::REL  => Attribute::REL_CANONICAL,
					Attribute::HREF => self::get_current_canonical_url(),
				]
			);
			$dom->head->appendChild( $rel_canonical );
		}

		// Store the last meta tag as the previous node to append to.
		$meta_tags     = $dom->head->getElementsByTagName( Tag::META );
		$previous_node = $meta_tags->length > 0 ? $meta_tags->item( $meta_tags->length - 1 ) : $dom->head->firstChild;

		// Handle the title.
		$title = $dom->head->getElementsByTagName( Tag::TITLE )->item( 0 );
		if ( $title ) {
			$title->parentNode->removeChild( $title ); // So we can move it.
			$dom->head->insertBefore( $title, $previous_node->nextSibling );
			$previous_node = $title;
		}

		// Obtain the existing AMP scripts.
		$amp_scripts     = [];
		$ordered_scripts = [];
		$head_scripts    = [];
		$runtime_src     = wp_scripts()->registered[ Amp::RUNTIME ]->src;

		/**
		 * Script element.
		 *
		 * @var DOMElement $script
		 */
		foreach ( $dom->head->getElementsByTagName( Tag::SCRIPT ) as $script ) { // Note that prepare_response() already moved body scripts to head.
			$head_scripts[] = $script;
		}
		foreach ( $head_scripts as $script ) {
			$src = $script->getAttribute( Attribute::SRC );
			if ( ! $src || 'https://cdn.ampproject.org/' !== substr( $src, 0, 27 ) ) {
				continue;
			}
			if ( $runtime_src === $src ) {
				$amp_scripts[ Amp::RUNTIME ] = $script;
			} elseif ( $script->hasAttribute( Attribute::CUSTOM_ELEMENT ) ) {
				$amp_scripts[ $script->getAttribute( Attribute::CUSTOM_ELEMENT ) ] = $script;
			} elseif ( $script->hasAttribute( Attribute::CUSTOM_TEMPLATE ) ) {
				$amp_scripts[ $script->getAttribute( Attribute::CUSTOM_TEMPLATE ) ] = $script;
			} else {
				continue;
			}
			$script->parentNode->removeChild( $script ); // So we can move it.
		}

		// Create scripts for any components discovered from output buffering that are missing.
		foreach ( array_diff( $script_handles, array_keys( $amp_scripts ) ) as $missing_script_handle ) {
			if ( ! wp_script_is( $missing_script_handle, 'registered' ) ) {
				continue;
			}
			$attrs = [
				Attribute::SRC   => wp_scripts()->registered[ $missing_script_handle ]->src,
				Attribute::ASYNC => '',
			];
			if ( Extension::MUSTACHE === $missing_script_handle ) {
				$attrs[ Attribute::CUSTOM_TEMPLATE ] = $missing_script_handle;
			} else {
				$attrs[ Attribute::CUSTOM_ELEMENT ] = $missing_script_handle;
			}

			$amp_scripts[ $missing_script_handle ] = AMP_DOM_Utils::create_node( $dom, Tag::SCRIPT, $attrs );
		}

		// Remove scripts that had already been added but couldn't be detected from output buffering.
		$extension_specs            = AMP_Allowed_Tags_Generated::get_extension_specs();
		$superfluous_script_handles = array_diff(
			array_keys( $amp_scripts ),
			array_merge( $script_handles, [ Amp::RUNTIME ] )
		);
		foreach ( $superfluous_script_handles as $superfluous_script_handle ) {
			if ( ! empty( $extension_specs[ $superfluous_script_handle ]['requires_usage'] ) ) {
				unset( $amp_scripts[ $superfluous_script_handle ] );
			}
		}

		/*
		 * "3. If your page includes render-delaying extensions (e.g., amp-experiment, amp-dynamic-css-classes, amp-story),
		 * preload those extensions as they're required by the AMP runtime for rendering the page."
		 * @TODO: Move into RewriteAmpUrls transformer, as that will support self-hosting as well.
		 */
		$prioritized_preloads = [];
		if ( ! isset( $links[ Attribute::REL_PRELOAD ] ) ) {
			$links[ Attribute::REL_PRELOAD ] = [];
		}

		$amp_script_handles = array_keys( $amp_scripts );
		foreach ( array_intersect( Amp::RENDER_DELAYING_EXTENSIONS, $amp_script_handles ) as $script_handle ) {
			if ( ! in_array( $script_handle, Amp::RENDER_DELAYING_EXTENSIONS, true ) ) {
				continue;
			}
			$prioritized_preloads[] = AMP_DOM_Utils::create_node(
				$dom,
				Tag::LINK,
				[
					Attribute::REL  => Attribute::REL_PRELOAD,
					Attribute::AS_  => RequestDestination::SCRIPT,
					Attribute::HREF => $amp_scripts[ $script_handle ]->getAttribute( Attribute::SRC ),
				]
			);
		}
		$links[ Attribute::REL_PRELOAD ] = array_merge( $prioritized_preloads, $links[ Attribute::REL_PRELOAD ] );

		// Add stylesheets for Bento components.
		if ( amp_is_bento_enabled() ) {
			foreach ( array_keys( $amp_scripts ) as $amp_script ) {
				if ( ! wp_style_is( $amp_script, 'registered' ) ) {
					continue;
				}
				$links[ Attribute::REL_STYLESHEET ][] = AMP_DOM_Utils::create_node(
					$dom,
					Tag::LINK,
					[
						Attribute::REL    => Attribute::REL_STYLESHEET,
						Attribute::TYPE   => 'text/css',
						Attribute::HREF   => wp_styles()->registered[ $amp_script ]->src,
						'data-ampdevmode' => '',
					]
				);
			}
		}

		/*
		 * "4. Use preconnect to speedup the connection to other origin where the full resource URL is not known ahead of time,
		 * for example, when using Google Fonts."
		 *
		 * Note that \AMP_Style_Sanitizer::process_link_element() will ensure preconnect links for Google Fonts are present.
		 */
		$link_relations = [ Attribute::REL_PRECONNECT, Attribute::REL_DNS_PREFETCH, Attribute::REL_PRELOAD, Attribute::REL_PRERENDER, Attribute::REL_PREFETCH, Attribute::REL_STYLESHEET ];
		foreach ( $link_relations as $rel ) {
			if ( ! isset( $links[ $rel ] ) ) {
				continue;
			}
			foreach ( $links[ $rel ] as $link ) {
				if ( $link->parentNode ) {
					$link->parentNode->removeChild( $link ); // So we can move it.
				}
				$dom->head->insertBefore( $link, $previous_node->nextSibling );
				$previous_node = $link;
			}
		}

		// "5. Load the AMP runtime."
		if ( isset( $amp_scripts[ Amp::RUNTIME ] ) ) {
			$ordered_scripts[ Amp::RUNTIME ] = $amp_scripts[ Amp::RUNTIME ];
			unset( $amp_scripts[ Amp::RUNTIME ] );
		} else {
			$script = $dom->createElement( Tag::SCRIPT );
			$script->setAttribute( Attribute::ASYNC, '' );
			$script->setAttribute( Attribute::SRC, $runtime_src );
			$ordered_scripts[ Amp::RUNTIME ] = $script;
		}

		/*
		 * "6. Specify the <script> tags for render-delaying extensions (e.g., amp-experiment amp-dynamic-css-classes and amp-story"
		 *
		 * {@link https://amp.dev/documentation/guides-and-tutorials/optimize-and-measure/optimize_amp/ AMP Hosting Guide}
		 */
		foreach ( Amp::RENDER_DELAYING_EXTENSIONS as $extension ) {
			if ( isset( $amp_scripts[ $extension ] ) ) {
				$ordered_scripts[ $extension ] = $amp_scripts[ $extension ];
				unset( $amp_scripts[ $extension ] );
			}
		}

		/*
		 * "7. Specify the <script> tags for remaining extensions (e.g., amp-bind ...). These extensions are not render-delaying
		 * and therefore should not be preloaded as they might take away important bandwidth for the initial render."
		 */
		ksort( $amp_scripts );
		$ordered_scripts = array_merge( $ordered_scripts, $amp_scripts );
		foreach ( $ordered_scripts as $ordered_script ) {
			$dom->head->insertBefore( $ordered_script, $previous_node->nextSibling );
			$previous_node = $ordered_script;
		}

		unset( $previous_node );
	}

	/**
	 * Dequeue Customizer assets which are not necessary outside the preview iframe.
	 *
	 * Prevent enqueueing customize-preview styles if not in customizer preview iframe.
	 * These are only needed for when there is live editing of content, such as selective refresh.
	 *
	 * @since 0.7
	 */
	public static function dequeue_customize_preview_scripts() {

		// Dequeue styles unnecessary unless in customizer preview iframe when editing (such as for edit shortcuts).
		if ( ! self::is_customize_preview_iframe() ) {
			wp_dequeue_style( 'customize-preview' );
			foreach ( wp_styles()->registered as $handle => $dependency ) {
				if ( in_array( 'customize-preview', $dependency->deps, true ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
	}

	/**
	 * Start output buffering.
	 *
	 * @since 0.7
	 * @see AMP_Theme_Support::finish_output_buffering()
	 */
	public static function start_output_buffering() {
		/*
		 * Disable the New Relic Browser agent on AMP responses.
		 * This prevents the New Relic from causing invalid AMP responses due the NREUM script it injects after the meta charset:
		 * https://docs.newrelic.com/docs/browser/new-relic-browser/troubleshooting/google-amp-validator-fails-due-3rd-party-script
		 * Sites with New Relic will need to specially configure New Relic for AMP:
		 * https://docs.newrelic.com/docs/browser/new-relic-browser/installation/monitor-amp-pages-new-relic-browser
		 */
		if ( function_exists( 'newrelic_disable_autorum' ) ) {
			newrelic_disable_autorum();
		}

		// Obtain Schema.org metadata so that it will be available.
		self::$metadata = (array) amp_get_schemaorg_metadata();

		ob_start( [ __CLASS__, 'finish_output_buffering' ] );
		self::$is_output_buffering = true;
	}

	/**
	 * Determine whether output buffering has started.
	 *
	 * @since 0.7
	 * @see AMP_Theme_Support::start_output_buffering()
	 * @see AMP_Theme_Support::finish_output_buffering()
	 *
	 * @return bool Whether output buffering has started.
	 */
	public static function is_output_buffering() {
		return self::$is_output_buffering;
	}

	/**
	 * Finish output buffering.
	 *
	 * @since 0.7
	 * @see AMP_Theme_Support::start_output_buffering()
	 *
	 * @param string $response Buffered Response.
	 * @return string Processed Response.
	 */
	public static function finish_output_buffering( $response ) {
		self::$is_output_buffering = false;

		try {
			$response = self::prepare_response( $response );
		} catch ( Exception $exception ) {
			$title   = __( 'Failed to prepare AMP page', 'amp' );
			$message = __( 'A PHP error occurred while trying to prepare the AMP response. This may not be caused by the AMP plugin but by some other active plugin or the current theme. You will need to review the error details to determine the source of the error.', 'amp' );

			$error_page = Services::get( 'dev_tools.error_page' );

			$error_page
				->with_title( $title )
				->with_message( $message )
				->with_exception( $exception )
				->with_response_code( 500 );

			// Add link to non-AMP version if not canonical.
			if ( ! amp_is_canonical() ) {
				$non_amp_url = amp_remove_paired_endpoint( amp_get_current_url() );

				// Prevent user from being redirected back to AMP version.
				if ( true === AMP_Options_Manager::get_option( Option::MOBILE_REDIRECT ) ) {
					$non_amp_url = add_query_arg( QueryVar::NOAMP, QueryVar::NOAMP_MOBILE, $non_amp_url );
				}

				$error_page->with_back_link(
					$non_amp_url,
					__( 'Go to non-AMP version', 'amp' )
				);
			}

			$response = $error_page->render();
		}

		/**
		 * Fires when server timings should be sent.
		 *
		 * This is immediately before the processed output buffer is sent to the client.
		 *
		 * @since 2.0
		 * @internal
		 */
		do_action( 'amp_server_timing_send' );
		return $response;
	}

	/**
	 * Filter rendered partial to convert to AMP.
	 *
	 * @see WP_Customize_Partial::render()
	 *
	 * @param string|mixed $partial Rendered partial.
	 * @return string|mixed Filtered partial.
	 * @global int $content_width
	 */
	public static function filter_customize_partial_render( $partial ) {
		global $content_width;
		if ( is_string( $partial ) && preg_match( '/<\w/', $partial ) ) {
			$dom  = AMP_DOM_Utils::get_dom_from_content( $partial );
			$args = [
				'content_max_width'    => ! empty( $content_width ) ? $content_width : AMP_Post_Template::CONTENT_MAX_WIDTH, // Back-compat.
				'use_document_element' => true,
			];
			AMP_Content_Sanitizer::sanitize_document( $dom, self::$sanitizer_classes, $args ); // @todo Include script assets in response?

			// Move any amp-custom to include in the partial response.
			$amp_custom_style = $dom->xpath->query( '//style[ @amp-custom ]' )->item( 0 );
			if ( $amp_custom_style instanceof DOMElement ) {
				$amp_custom_style->removeAttribute( Attribute::AMP_CUSTOM );
				$amp_custom_style->setAttribute( 'amp-custom-partial', '' );
				$amp_custom_style->textContent = str_replace(
					'/*# sourceURL=amp-custom.css */',
					'/*# sourceURL=amp-custom-partial.css */',
					$amp_custom_style->textContent
				);
				$dom->body->appendChild( $amp_custom_style ); // @todo This could cause layout problems. It may be preferable to move to the head.
			}

			$partial = AMP_DOM_Utils::get_content_from_dom( $dom );
		}
		return $partial;
	}

	/**
	 * Process response to ensure AMP validity.
	 *
	 * @since 0.7
	 *
	 * @param string $response HTML document response. By default it expects a complete document.
	 * @param array  $args     Args to send to the preprocessor/sanitizer/optimizer.
	 * @return string AMP document response.
	 * @global int $content_width
	 */
	public static function prepare_response( $response, $args = [] ) {
		global $content_width;
		$last_error = error_get_last();

		if ( isset( $args['validation_error_callback'] ) ) {
			_doing_it_wrong( __METHOD__, 'Do not supply validation_error_callback arg.', '1.0' );
			unset( $args['validation_error_callback'] );
		}

		$status_code = http_response_code();

		/*
		 * Send a JSON response when the site is failing to handle AMP form submissions with a JSON response as required
		 * or an AMP-Redirect-To response header was not sent. This is a common scenario for plugins that handle form
		 * submissions and show the success page via the POST request's response body instead of invoking wp_redirect(),
		 * in which case AMP_HTTP::intercept_post_request_redirect() will automatically send the AMP-Redirect-To header.
		 * If the POST response is an HTML document then the form submission will appear to not have worked since there
		 * is no success or failure message shown. By catching the case where HTML is sent in the response, we can
		 * automatically send a generic success message when a 200 status is returned or a failure message when a 400+
		 * response code is sent.
		 */
		$is_form_submission = (
			isset( AMP_HTTP::$purged_amp_query_vars[ AMP_HTTP::ACTION_XHR_CONVERTED_QUERY_VAR ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&&
			isset( $_SERVER['REQUEST_METHOD'] )
			&&
			'POST' === $_SERVER['REQUEST_METHOD']
		);
		if ( $is_form_submission && null === json_decode( $response ) && json_last_error() && ( is_bool( $status_code ) || ( $status_code >= 200 && $status_code < 300 ) || $status_code >= 400 ) ) {
			if ( is_bool( $status_code ) ) {
				$status_code = 200; // Not a web server environment.
			}
			if ( ! headers_sent() ) {
				header( 'Content-Type: application/json; charset=utf-8' );
			}
			return wp_json_encode(
				[
					'status_code' => $status_code,
					'status_text' => get_status_header_desc( $status_code ),
				]
			);
		}

		// Abort if response type is not HTML.
		if ( Attribute::TYPE_HTML !== substr( AMP_HTTP::get_response_content_type(), 0, 9 ) ) {
			return $response;
		}

		// Abort if an expected template action didn't fire or if the HTML tag does not have the AMP attribute.
		if ( ! (
			did_action( 'wp_head' )
			||
			did_action( 'wp_footer' )
			||
			did_action( 'amp_post_template_head' )
			||
			did_action( 'amp_post_template_footer' )
			||
			preg_match(
				sprintf(
					'#^(?:<!.*?>|\s+)*+<html(?=\s)[^>]*?\s(%1$s|%2$s|%3$s)(\s|=|>)#is',
					preg_quote( Attribute::AMP, '#' ),
					preg_quote( Attribute::AMP_EMOJI, '#' ),
					preg_quote( Attribute::AMP_EMOJI_ALT, '#' )
				),
				$response
			)
		) ) {
			// Detect whether redirect happened and prevent failing a validation request when that happens,
			// since \AMP_Validation_Manager::validate_url() follows redirects.
			$sent_location_header = false;
			foreach ( headers_list() as $sent_header ) {
				if ( preg_match( '#^location:#i', $sent_header ) ) {
					$sent_location_header = true;
					break;
				}
			}
			$did_redirect = $status_code >= 300 && $status_code < 400 && $sent_location_header;

			if ( AMP_Validation_Manager::$is_validate_request && ! $did_redirect ) {
				if ( ! headers_sent() ) {
					status_header( 400 );
					header( 'Content-Type: application/json; charset=utf-8' );
				}
				return wp_json_encode(
					[
						'code'    => 'RENDERED_PAGE_NOT_AMP',
						'message' => __( 'The requested URL did not result in an AMP page being rendered.', 'amp' ),
					]
				);
			}

			return $response;
		}

		// Enforce UTF-8 encoding as it is a requirement for AMP.
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
		}

		$args = array_merge(
			[
				'content_max_width'    => ! empty( $content_width ) ? $content_width : AMP_Post_Template::CONTENT_MAX_WIDTH, // Back-compat.
				'use_document_element' => true,
				'user_can_validate'    => AMP_Validation_Manager::has_cap(),
			],
			$args
		);

		/**
		 * Stops the server-timing measurement for the entire output buffer capture.
		 *
		 * @since 2.0
		 * @internal
		 *
		 * @param string $event_name Name of the event to stop.
		 */
		do_action( 'amp_server_timing_stop', 'amp_output_buffer' );

		/**
		 * Starts the server-timing measurement for the dom parsing subsystem.
		 *
		 * @since 2.0
		 * @internal
		 *
		 * @param string      $event_name        Name of the event to record.
		 * @param string|null $event_description Optional. Description of the event
		 *                                       to record. Defaults to null.
		 * @param string[]    $properties        Optional. Additional properties to add
		 *                                       to the logged record.
		 * @param bool        $verbose_only      Optional. Whether to only show the
		 *                                       event in verbose mode. Defaults to
		 *                                       false.
		 */
		do_action( 'amp_server_timing_start', 'amp_dom_parse', '', [], true );

		$dom = Document::fromHtml( $response, Options::DEFAULTS );

		if ( AMP_Validation_Manager::$is_validate_request ) {
			AMP_Validation_Manager::remove_illegal_source_stack_comments( $dom );
		}

		/**
		 * Stops the server-timing measurement for the dom parsing subsystem.
		 *
		 * @since 2.0
		 * @internal
		 *
		 * @param string $event_name Name of the event to stop.
		 */
		do_action( 'amp_server_timing_stop', 'amp_dom_parse' );

		/**
		 * Starts the server-timing measurement for the AMP Sanitizer subsystem.
		 *
		 * @since 2.0
		 * @internal
		 *
		 * @param string      $event_name        Name of the event to record.
		 * @param string|null $event_description Optional. Description of the event
		 *                                       to record. Defaults to null.
		 * @param string[]    $properties        Optional. Additional properties to add
		 *                                       to the logged record.
		 * @param bool        $verbose_only      Optional. Whether to only show the
		 *                                       event in verbose mode. Defaults to
		 *                                       false.
		 */
		do_action( 'amp_server_timing_start', 'amp_sanitizer' );

		// Make sure scripts from the body get moved to the head.
		foreach ( $dom->xpath->query( '//body//script[ @custom-element or @custom-template or @src = "https://cdn.ampproject.org/v0.js" ]' ) as $script ) {
			$dom->head->appendChild( $script->parentNode->removeChild( $script ) );
		}

		// Ensure the mandatory amp attribute is present on the html element.
		if ( ! $dom->documentElement->hasAttribute( Attribute::AMP )
			&& ! $dom->documentElement->hasAttribute( Attribute::AMP_EMOJI )
			&& ! $dom->documentElement->hasAttribute( Attribute::AMP_EMOJI_ALT ) ) {
			$dom->documentElement->setAttribute( Attribute::AMP, '' );
		}

		$sanitization_results = AMP_Content_Sanitizer::sanitize_document( $dom, self::$sanitizer_classes, $args );

		/**
		 * Stops the server-timing measurement for the AMP Sanitizer subsystem.
		 *
		 * @since 2.0
		 * @internal
		 *
		 * @param string $event_name Name of the event to stop.
		 */
		do_action( 'amp_server_timing_stop', 'amp_sanitizer' );

		// Respond early with results if performing a validate request.
		if ( AMP_Validation_Manager::$is_validate_request ) {
			status_header( 200 );
			header( 'Content-Type: application/json; charset=utf-8' );
			$data = [
				'http_status_code' => $status_code,
				'php_fatal_error'  => false,
			];
			if ( $last_error && in_array( $last_error['type'], [ E_ERROR, E_RECOVERABLE_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_PARSE ], true ) ) {
				$data['php_fatal_error'] = $last_error;
			}
			$data = array_merge( $data, AMP_Validation_Manager::get_validate_response_data( $sanitization_results ) );
			return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}

		/**
		 * Starts the server-timing measurement for the AMP DOM serialization.
		 *
		 * @since 2.0
		 * @internal
		 *
		 * @param string      $event_name        Name of the event to record.
		 * @param string|null $event_description Optional. Description of the event
		 *                                       to record. Defaults to null.
		 * @param string[]    $properties        Optional. Additional properties to add
		 *                                       to the logged record.
		 * @param bool        $verbose_only      Optional. Whether to only show the
		 *                                       event in verbose mode. Defaults to
		 *                                       false.
		 */
		do_action( 'amp_server_timing_start', 'amp_dom_serialize', '', [], true );

		// Gather all component scripts that are used in the document and then render any not already printed.
		$amp_scripts = $sanitization_results['scripts'];
		foreach ( self::$embed_handlers as $embed_handler ) {
			$amp_scripts = array_merge(
				$amp_scripts,
				$embed_handler->get_scripts()
			);
		}
		foreach ( $amp_scripts as $handle => $src ) {
			/*
			 * Make sure the src is up-to-date. This allows for embed handlers to override the
			 * default extension version by defining a different URL.
			 */
			if ( is_string( $src ) && wp_script_is( $handle, 'registered' ) ) {
				wp_scripts()->registered[ $handle ]->src = $src;
			}
		}

		self::ensure_required_markup( $dom, array_keys( $amp_scripts ) );

		$enable_optimizer = array_key_exists( ConfigurationArgument::ENABLE_OPTIMIZER, $args )
			? $args[ ConfigurationArgument::ENABLE_OPTIMIZER ]
			: true;

		/**
		 * Filter whether the generated HTML output should be run through the AMP Optimizer or not.
		 *
		 * @since 1.5.0
		 *
		 * @param bool $enable_optimizer Whether the generated HTML output should be run through the AMP Optimizer or not.
		 */
		$enable_optimizer = apply_filters( 'amp_enable_optimizer', $enable_optimizer );

		if ( $enable_optimizer ) {
			/**
			 * Starts the server-timing measurement for the AMP Optimizer subsystem.
			 *
			 * @since 2.0
			 * @internal
			 *
			 * @param string      $event_name        Name of the event to record.
			 * @param string|null $event_description Optional. Description of the event
			 *                                       to record. Defaults to null.
			 * @param string[]    $properties        Optional. Additional properties to add
			 *                                       to the logged record.
			 * @param bool        $verbose_only      Optional. Whether to only show the
			 *                                       event in verbose mode. Defaults to
			 *                                       false.
			 */
			do_action( 'amp_server_timing_start', 'amp_optimizer' );

			$errors = new Optimizer\ErrorCollection();
			self::get_optimizer( $args )->optimizeDom( $dom, $errors );

			if ( count( $errors ) > 0 ) {
				$error_messages = array_map(
					static function( Optimizer\Error $error ) {
						return ' - ' . $error->getCode() . ': ' . $error->getMessage();
					},
					iterator_to_array( $errors )
				);
				$dom->head->appendChild(
					$dom->createComment( "\n" . __( 'AMP optimization could not be completed due to the following:', 'amp' ) . "\n" . implode( "\n", $error_messages ) . "\n" )
				);
				// @todo Include errors elsewhere than HTML comment?
			}

			/**
			 * Stops the server-timing measurement for the AMP Optimizer subsystem.
			 *
			 * @since 2.0
			 * @internal
			 *
			 * @param string $event_name Name of the event to stop.
			 */
			do_action( 'amp_server_timing_stop', 'amp_optimizer' );
		}

		$can_serve = AMP_Validation_Manager::finalize_validation( $dom );

		// Redirect to the non-AMP version if not on an AMP-first site.
		if ( ! $can_serve && ! amp_is_canonical() ) {
			$non_amp_url = amp_remove_paired_endpoint( amp_get_current_url() );

			// Redirect to include query var to preventing AMP from even being considered available.
			$non_amp_url = add_query_arg( QueryVar::NOAMP, QueryVar::NOAMP_AVAILABLE, $non_amp_url );

			wp_safe_redirect( $non_amp_url, 302 ); // phpcs:ignore WordPressVIPMinimum.Security.ExitAfterRedirect.NoExit -- This is in an output buffer callback handler.
			return esc_html__( 'Redirecting since AMP version not available.', 'amp' );
		}

		$response = $dom->saveHTML();

		/**
		 * Stops the server-timing measurement for the AMP DOM serialization.
		 *
		 * @since 2.0
		 * @internal
		 *
		 * @param string $event_name Name of the event to stop.
		 */
		do_action( 'amp_server_timing_stop', 'amp_dom_serialize' );

		return $response;
	}

	/**
	 * Optimizer instance to use.
	 *
	 * @param array $args Associative array of arguments to pass into the transformation engine.
	 * @return OptimizerService Optimizer transformation engine to use.
	 */
	private static function get_optimizer( $args ) {
		add_filter(
			'amp_enable_ssr',
			static function () use ( $args ) {
				// SSR currently does not work with Bento.
				if ( amp_is_bento_enabled() ) {
					return false;
				}

				return array_key_exists( ConfigurationArgument::ENABLE_SSR, $args )
					? $args[ ConfigurationArgument::ENABLE_SSR ]
					: true;
			},
			defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~PHP_INT_MAX // phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
		);

		// Supply the Schema.org metadata, previously obtained just before output buffering began, to the AmpSchemaOrgMetadataConfiguration.
		add_filter(
			'amp_optimizer_config',
			function ( $config ) {
				if ( is_array( self::$metadata ) ) {
					$config[ AmpSchemaOrgMetadata::class ][ AmpSchemaOrgMetadataConfiguration::METADATA ] = self::$metadata;
				}
				return $config;
			},
			defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~PHP_INT_MAX // phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
		);

		return Services::get( 'injector' )->make( OptimizerService::class );
	}

	/**
	 * Adds 'data-amp-layout' to the allowed <img> attributes for wp_kses().
	 *
	 * @since 0.7
	 *
	 * @param array $context Allowed tags and their allowed attributes.
	 * @return array $context Filtered allowed tags and attributes.
	 */
	public static function include_layout_in_wp_kses_allowed_html( $context ) {
		if ( ! empty( $context[ Tag::IMG ][ Attribute::WIDTH ] ) && ! empty( $context[ Tag::IMG ][ Attribute::HEIGHT ] ) ) {
			$context[ Tag::IMG ]['data-amp-layout'] = true;
		}

		return $context;
	}

	/**
	 * Enqueue AMP assets if this is an AMP endpoint.
	 *
	 * @since 0.7
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		// Enqueue default styles expected by sanitizer.
		wp_enqueue_style( 'amp-default' );
	}

	/**
	 * Print the important emoji-related styles.
	 *
	 * @see print_emoji_styles()
	 * @staticvar bool $printed
	 */
	public static function print_emoji_styles() {
		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;
		?>
		<style type="text/css">
			img.wp-smiley,
			img.emoji {
				display: inline-block !important; /* Patched from core, which had display:inline */
				border: none !important;
				box-shadow: none !important;
				height: 1em !important;
				width: 1em !important;
				margin: 0 .07em !important;
				vertical-align: -0.1em !important;
				background: none !important;
				padding: 0 !important;
			}
		</style>
		<?php
	}

	/**
	 * Conditionally replace the header image markup with a header video or image.
	 *
	 * This is JS-driven in Core themes like Twenty Sixteen and Twenty Seventeen.
	 * So in order for the header video to display, this replaces the markup of the header image.
	 *
	 * @since 1.0
	 * @link https://github.com/WordPress/wordpress-develop/blob/d002fde80e5e3a083e5f950313163f566561517f/src/wp-includes/js/wp-custom-header.js#L54
	 * @link https://github.com/WordPress/wordpress-develop/blob/d002fde80e5e3a083e5f950313163f566561517f/src/wp-includes/js/wp-custom-header.js#L78
	 *
	 * @param string $image_markup The image markup to filter.
	 * @return string $html Filtered markup.
	 */
	public static function amend_header_image_with_video_header( $image_markup ) {

		// If there is no video, just pass the image through.
		if ( ! has_header_video() || ! is_header_video_active() ) {
			return $image_markup;
		}

		$video_settings   = get_header_video_settings();
		$parsed_url       = wp_parse_url( $video_settings['videoUrl'] );
		$query            = isset( $parsed_url['query'] ) ? wp_parse_args( $parsed_url['query'] ) : [];
		$video_attributes = [
			Attribute::MEDIA    => '(min-width: ' . $video_settings['minWidth'] . 'px)',
			Attribute::WIDTH    => $video_settings[ Attribute::WIDTH ],
			Attribute::HEIGHT   => $video_settings[ Attribute::HEIGHT ],
			Attribute::LAYOUT   => 'responsive',
			Attribute::AUTOPLAY => '',
			Attribute::LOOP     => '',
			Attribute::ID       => 'wp-custom-header-video',
		];

		$youtube_id = null;
		if ( isset( $parsed_url['host'] ) && preg_match( '/(^|\.)(youtube\.com|youtu\.be)$/', $parsed_url['host'] ) ) {
			if ( 'youtu.be' === $parsed_url['host'] && ! empty( $parsed_url['path'] ) ) {
				$youtube_id = trim( $parsed_url['path'], '/' );
			} elseif ( isset( $query['v'] ) ) {
				$youtube_id = $query['v'];
			}
		}

		// If the video URL is for YouTube, return an <amp-youtube> element.
		if ( ! empty( $youtube_id ) ) {
			$video_markup = AMP_HTML_Utils::build_tag(
				Extension::YOUTUBE,
				array_merge(
					$video_attributes,
					[
						'data-videoid'              => $youtube_id,

						// For documentation on the params, see <https://developers.google.com/youtube/player_parameters>.
						'data-param-rel'            => '0', // Don't show related videos.
						'data-param-showinfo'       => '0', // Don't show video title at the top.
						'data-param-controls'       => '0', // Don't show video controls.
						'data-param-iv_load_policy' => '3', // Suppress annotations.
						'data-param-modestbranding' => '1', // Show modest branding.
						'data-param-playsinline'    => '1', // Prevent fullscreen playback on iOS.
						'data-param-disablekb'      => '1', // Disable keyboard conttrols.
						'data-param-fs'             => '0', // Suppress full screen button.
					]
				)
			);

			// Hide equalizer video animation.
			$video_markup .= '<style>#wp-custom-header-video .amp-video-eq { display:none; }</style>';
		} else {
			$video_markup = AMP_HTML_Utils::build_tag(
				Extension::VIDEO,
				array_merge(
					$video_attributes,
					[
						Attribute::SRC => $video_settings['videoUrl'],
					]
				)
			);
		}

		return $image_markup . $video_markup;
	}
}

<?php
/**
 * Class AMP_Theme_Support
 *
 * @package AMP
 */

/**
 * Class AMP_Theme_Support
 *
 * Callbacks for adding AMP-related things when theme support is added.
 */
class AMP_Theme_Support {

	/**
	 * Theme support slug.
	 *
	 * @var string
	 */
	const SLUG = 'amp';

	/**
	 * Response cache group name.
	 *
	 * @var string
	 */
	const RESPONSE_CACHE_GROUP = 'amp-response';

	/**
	 * Post-processor cache effectiveness group name.
	 *
	 * @var string
	 */
	const POST_PROCESSOR_CACHE_EFFECTIVENESS_GROUP = 'post_processor_cache_effectiveness_group';

	/**
	 * Post-processor cache effectiveness key name.
	 *
	 * @var string
	 */
	const POST_PROCESSOR_CACHE_EFFECTIVENESS_KEY = 'post_processor_cache_effectiveness';

	/**
	 * Cache miss threshold for determining when to disable post-processor cache.
	 *
	 * @var int
	 */
	const CACHE_MISS_THRESHOLD = 20;

	/**
	 * Cache miss URL option name.
	 *
	 * @var string
	 */
	const CACHE_MISS_URL_OPTION = 'amp_cache_miss_url';

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

	const PAIRED_BROWSING_QUERY_VAR = 'amp-paired-browsing';

	/**
	 * Sanitizer classes.
	 *
	 * @var array
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
	 * Start time when init was called.
	 *
	 * @since 1.0
	 * @var float
	 */
	public static $init_start_time;

	/**
	 * Whether output buffering has started.
	 *
	 * @since 0.7
	 * @var bool
	 */
	protected static $is_output_buffering = false;

	/**
	 * Theme support mode that was added via option.
	 *
	 * This should be either null (reader), 'standard', or 'transitional'.
	 *
	 * @since 1.0
	 * @var null|string
	 */
	protected static $support_added_via_option;

	/**
	 * Theme support mode which was added via the theme.
	 *
	 * This should be either null (reader), 'standard', or 'transitional'.
	 *
	 * @var null|string
	 */
	protected static $support_added_via_theme;

	/**
	 * Initialize.
	 *
	 * @since 0.7
	 */
	public static function init() {
		self::read_theme_support();

		self::$init_start_time = microtime( true );

		if ( AMP_Options_Manager::is_website_experience_enabled() ) {
			if ( self::READER_MODE_SLUG !== self::get_support_mode() ) {
				// Ensure extra theme support for core themes is in place.
				AMP_Core_Theme_Sanitizer::extend_theme_support();
			}

			add_action( 'widgets_init', [ __CLASS__, 'register_widgets' ] );

			/*
			 * Note that wp action is use instead of template_redirect because some themes/plugins output
			 * the response at this action and then short-circuit with exit. So this is why the the preceding
			 * action to template_redirect--the wp action--is used instead.
			 */
			add_action( 'wp', [ __CLASS__, 'finish_init' ], PHP_INT_MAX );
		} elseif ( AMP_Options_Manager::is_stories_experience_enabled() ) {
			add_action(
				'wp',
				static function () {
					if ( is_singular( AMP_Story_Post_Type::POST_TYPE_SLUG ) ) {
						self::finish_init();
					}
				},
				PHP_INT_MAX
			);
		}
	}

	/**
	 * Determine whether theme support was added via admin option.
	 *
	 * @since 1.0
	 * @see AMP_Theme_Support::read_theme_support()
	 * @see AMP_Theme_Support::get_support_mode()
	 * @deprecated Use AMP_Theme_Support::get_support_mode_added_via_option().
	 *
	 * @return bool Support added via option.
	 */
	public static function is_support_added_via_option() {
		_deprecated_function( __METHOD__, '1.2', 'AMP_Theme_Support::get_support_mode_added_via_option' );
		return null !== self::$support_added_via_option;
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
	 */
	public static function get_support_mode_added_via_option() {
		return self::$support_added_via_option;
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
	 */
	public static function get_support_mode_added_via_theme() {
		return self::$support_added_via_theme;
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
	 */
	public static function get_support_mode() {
		$theme_support = self::get_support_mode_added_via_option();
		if ( ! $theme_support ) {
			$theme_support = self::get_support_mode_added_via_theme();
		}
		if ( ! $theme_support ) {
			$theme_support = self::READER_MODE_SLUG;
		}
		return $theme_support;
	}

	/**
	 * Check theme support args or add theme support if option is set in the admin.
	 *
	 * The DB option is only considered if the theme does not already explicitly support AMP.
	 *
	 * @see AMP_Theme_Support::get_support_mode_added_via_theme()
	 * @see AMP_Theme_Support::get_support_mode_added_via_option()
	 * @see AMP_Post_Type_Support::add_post_type_support() For where post type support is added, since it is irrespective of theme support.
	 */
	public static function read_theme_support() {
		self::$support_added_via_theme  = null;
		self::$support_added_via_option = null;

		$theme_support_option = AMP_Options_Manager::get_option( 'theme_support' );
		if ( current_theme_supports( self::SLUG ) ) {
			$args = self::get_theme_support_args();

			// Validate theme support usage.
			$keys = [ 'template_dir', 'comments_live_list', self::PAIRED_FLAG, 'templates_supported', 'available_callback', 'service_worker', 'nav_menu_toggle', 'nav_menu_dropdown' ];

			if ( count( array_diff( array_keys( $args ), $keys ) ) !== 0 ) {
				_doing_it_wrong(
					'add_theme_support',
					esc_html(
						sprintf(  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
							/* translators: 1: comma-separated list of expected keys, 2: comma-separated list of actual keys */
							__( 'Expected AMP theme support to keys (%1$s) but saw (%2$s)', 'amp' ),
							implode( ', ', $keys ),
							implode( ', ', array_keys( $args ) )
						)
					),
					'1.0'
				);
			}

			if ( isset( $args['available_callback'] ) ) {
				_doing_it_wrong(
					'add_theme_support',
					sprintf(
						/* translators: 1: available_callback. 2: supported_templates */
						esc_html__( 'The %1$s is deprecated when adding amp theme support in favor of declaratively setting the %2$s.', 'amp' ),
						'available_callback',
						'supported_templates'
					),
					'1.0'
				);
			}

			// See amp_is_canonical().
			$is_paired = isset( $args[ self::PAIRED_FLAG ] ) ? $args[ self::PAIRED_FLAG ] : ! empty( $args['template_dir'] );

			self::$support_added_via_theme  = $is_paired ? self::TRANSITIONAL_MODE_SLUG : self::STANDARD_MODE_SLUG;
			self::$support_added_via_option = $theme_support_option;

			// Make sure the user option can override what the theme has specified.
			if ( $is_paired && self::STANDARD_MODE_SLUG === $theme_support_option ) {
				$args[ self::PAIRED_FLAG ] = false;
				add_theme_support( self::SLUG, $args );
			} elseif ( ! $is_paired && self::TRANSITIONAL_MODE_SLUG === $theme_support_option ) {
				$args[ self::PAIRED_FLAG ] = true;
				add_theme_support( self::SLUG, $args );
			} elseif ( self::READER_MODE_SLUG === $theme_support_option ) {
				remove_theme_support( self::SLUG );
			}
		} elseif ( self::READER_MODE_SLUG !== $theme_support_option ) {
			$is_paired = ( self::TRANSITIONAL_MODE_SLUG === $theme_support_option );
			add_theme_support(
				self::SLUG,
				[
					self::PAIRED_FLAG => $is_paired,
				]
			);
			self::$support_added_via_option = $is_paired ? self::TRANSITIONAL_MODE_SLUG : self::STANDARD_MODE_SLUG;
		} elseif ( AMP_Validation_Manager::is_theme_support_forced() ) {
			self::$support_added_via_option = self::STANDARD_MODE_SLUG;
			add_theme_support( self::SLUG );
		}
	}

	/**
	 * Get the theme support args.
	 *
	 * This avoids having to repeatedly call `get_theme_support()`, check the args, shift an item off the array, and so on.
	 *
	 * @since 1.0
	 *
	 * @return array|false Theme support args, or false if theme support is not present.
	 */
	public static function get_theme_support_args() {
		if ( ! current_theme_supports( self::SLUG ) ) {
			return false;
		}
		$support = get_theme_support( self::SLUG );
		if ( true === $support ) {
			return [
				self::PAIRED_FLAG => false,
			];
		}
		if ( ! isset( $support[0] ) || ! is_array( $support[0] ) ) {
			return [];
		}
		return $support[0];
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
			! self::get_support_mode_added_via_theme()
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
		if ( self::is_paired_available() ) {
			self::setup_paired_browsing_client();
			add_action( 'template_redirect', [ __CLASS__, 'sanitize_url_for_paired_browsing' ] );
			add_filter( 'template_include', [ __CLASS__, 'serve_paired_browsing_experience' ] );
		}

		if ( ! is_amp_endpoint() ) {
			/*
			 * Redirect to AMP-less variable if AMP is not available for this URL and yet the query var is present.
			 * Temporary redirect is used for admin users because implied transitional mode and template support can be
			 * enabled by user ay any time, so they will be able to make AMP available for this URL and see the change
			 * without wrestling with the redirect cache.
			 */
			if ( isset( $_GET[ amp_get_slug() ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				self::redirect_non_amp_url( current_user_can( 'manage_options' ) ? 302 : 301, true );
			}

			amp_add_frontend_actions();
			return;
		}

		self::ensure_proper_amp_location();

		$is_reader_mode = self::READER_MODE_SLUG === self::get_support_mode();
		$theme_support  = self::get_theme_support_args();
		if ( ! empty( $theme_support['template_dir'] ) ) {
			self::add_amp_template_filters();
		} elseif ( $is_reader_mode ) {
			add_filter(
				'template_include',
				static function() {
					return AMP__DIR__ . '/includes/templates/reader-template-loader.php';
				},
				PHP_INT_MAX
			);
		}

		self::add_hooks();
		self::$sanitizer_classes = amp_get_content_sanitizers();
		if ( ! $is_reader_mode ) {
			self::$sanitizer_classes = AMP_Validation_Manager::filter_sanitizer_args( self::$sanitizer_classes );
		}
		self::$embed_handlers = self::register_content_embed_handlers();
		self::$sanitizer_classes['AMP_Embed_Sanitizer']['embed_handlers'] = self::$embed_handlers;

		foreach ( self::$sanitizer_classes as $sanitizer_class => $args ) {
			if ( method_exists( $sanitizer_class, 'add_buffering_hooks' ) ) {
				call_user_func( [ $sanitizer_class, 'add_buffering_hooks' ], $args );
			}
		}
	}

	/**
	 * Ensure that the current AMP location is correct.
	 *
	 * @since 1.0
	 *
	 * @param bool $exit Whether to exit after redirecting.
	 * @return bool Whether redirection was done. Naturally this is irrelevant if $exit is true.
	 */
	public static function ensure_proper_amp_location( $exit = true ) {
		$has_query_var = false !== get_query_var( amp_get_slug(), false ); // May come from URL param or endpoint slug.
		$has_url_param = isset( $_GET[ amp_get_slug() ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( amp_is_canonical() || is_singular( AMP_Story_Post_Type::POST_TYPE_SLUG ) ) {
			/*
			 * When AMP-first/canonical, then when there is an /amp/ endpoint or ?amp URL param,
			 * then a redirect needs to be done to the URL without any AMP indicator in the URL.
			 * Permanent redirect is used for unauthenticated users since switching between modes
			 * should happen infrequently. For admin users, this is kept temporary to allow them
			 * to not be hampered by browser remembering permanent redirects and preventing test.
			 */
			if ( $has_query_var || $has_url_param ) {
				return self::redirect_non_amp_url( current_user_can( 'manage_options' ) ? 302 : 301, $exit );
			}
		} elseif ( self::READER_MODE_SLUG === self::get_support_mode() && is_singular() ) {
			// Prevent infinite URL space under /amp/ endpoint.
			global $wp;
			$path_args = [];
			wp_parse_str( $wp->matched_query, $path_args );
			if ( isset( $path_args[ amp_get_slug() ] ) && '' !== $path_args[ amp_get_slug() ] ) {
				wp_safe_redirect( amp_get_permalink( get_queried_object_id() ), 301 );
				if ( $exit ) {
					exit;
				}
				return true;
			}
		} elseif ( $has_query_var && ! $has_url_param ) {
			/*
			 * When in AMP transitional mode *with* theme support, then the proper AMP URL has the 'amp' URL param
			 * and not the /amp/ endpoint. The URL param is now the exclusive way to mark AMP in transitional mode
			 * when amp theme support present. This is important for plugins to be able to reliably call
			 * is_amp_endpoint() before the parse_query action.
			 */
			$old_url = amp_get_current_url();
			$new_url = add_query_arg( amp_get_slug(), '', amp_remove_endpoint( $old_url ) );
			if ( $old_url !== $new_url ) {
				// A temporary redirect is used for admin users to allow them to see changes between reader mode and transitional modes.
				wp_safe_redirect( $new_url, current_user_can( 'manage_options' ) ? 302 : 301 );
				// @codeCoverageIgnoreStart
				if ( $exit ) {
					exit;
				}
				return true;
				// @codeCoverageIgnoreEnd
			}
		}
		return false;
	}

	/**
	 * Redirect to non-AMP version of the current URL, such as because AMP is canonical or there are unaccepted validation errors.
	 *
	 * If the current URL is already AMP-less then do nothing.
	 *
	 * @since 0.7
	 * @since 1.0 Added $exit param.
	 * @since 1.0 Renamed from redirect_canonical_amp().
	 *
	 * @param int  $status Status code (301 or 302).
	 * @param bool $exit   Whether to exit after redirecting.
	 * @return bool Whether redirection was done. Naturally this is irrelevant if $exit is true.
	 */
	public static function redirect_non_amp_url( $status = 302, $exit = true ) {
		$current_url = amp_get_current_url();
		$non_amp_url = remove_query_arg( AMP_Debug::AMP_FLAGS_QUERY_VAR, amp_remove_endpoint( $current_url ) );
		if ( $non_amp_url === $current_url ) {
			return false;
		}

		wp_safe_redirect( $non_amp_url, $status );
		// @codeCoverageIgnoreStart
		if ( $exit ) {
			exit;
		}
		return true;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Determines whether transitional mode is available.
	 *
	 * When 'amp' theme support has not been added or canonical mode is enabled, then this returns false.
	 *
	 * @since 0.7
	 *
	 * @see amp_is_canonical()
	 * @return bool Whether available.
	 */
	public static function is_paired_available() {
		if ( ! current_theme_supports( self::SLUG ) ) {
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
	 * This is not intended to return whether AMP is available for a _specific_ post. For that, use `post_supports_amp()`.
	 *
	 * @since 1.0
	 * @global WP_Query $wp_query
	 * @see post_supports_amp()
	 *
	 * @param WP_Query|WP_Post|null $query Query or queried post. If null then the global query will be used.
	 * @return array {
	 *     Template availability.
	 *
	 *     @type bool        $supported Whether the template is supported in AMP.
	 *     @type bool|null   $immutable Whether the supported status is known to be unchangeable.
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
			'immutable' => null,
			'template'  => null,
		];

		if ( ! ( $query instanceof WP_Query ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'No WP_Query available.', 'amp' ), '1.0' );
			return array_merge(
				$default_response,
				[ 'errors' => [ 'no_query_available' ] ]
			);
		}

		$theme_support_args = self::get_theme_support_args();
		if ( false === $theme_support_args ) {
			return array_merge(
				$default_response,
				[ 'errors' => [ 'no_theme_support' ] ]
			);
		}

		// Support available_callback from 0.7, though it is deprecated.
		if ( isset( $theme_support_args['available_callback'] ) && is_callable( $theme_support_args['available_callback'] ) ) {
			/**
			 * Queried object.
			 *
			 * @var WP_Post $queried_object
			 */
			$queried_object = $query->get_queried_object();
			if ( ( is_singular() || $query->is_posts_page ) && ! post_supports_amp( $queried_object ) ) {
				return array_merge(
					$default_response,
					[
						'errors'    => [ 'no-post-support' ],
						'supported' => false,
						'immutable' => true,
					]
				);
			}

			$response = array_merge(
				$default_response,
				[
					'supported' => call_user_func( $theme_support_args['available_callback'] ),
					'immutable' => true,
				]
			);
			if ( ! $response['supported'] ) {
				$response['errors'][] = 'available_callback';
			}
			return $response;
		}

		$all_templates_supported_by_theme_support = false;
		if ( isset( $theme_support_args['templates_supported'] ) ) {
			$all_templates_supported_by_theme_support = 'all' === $theme_support_args['templates_supported'];
		}
		$all_templates_supported = (
			$all_templates_supported_by_theme_support || AMP_Options_Manager::get_option( 'all_templates_supported' )
		);

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
					'immutable' => ! empty( $supportable_template['immutable'] ),
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

		// For singular queries, post_supports_amp() is given the final say.
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
				'label'       => __( 'Singular', 'amp' ),
				'description' => __( 'Required for the above content types.', 'amp' ),
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
		 * A theme or plugin can force a given template to be supported or not by preemptively
		 * setting the 'supported' flag for a given template. Otherwise, if the flag is undefined
		 * then the user will be able to toggle it themselves in the admin. Each array item should
		 * have a key that corresponds to a template conditional function. If the key is such a
		 * function, then the key is used to evaluate whether the given template entry is a match.
		 * Otherwise, a supportable template item can include a callback value which is used instead.
		 * Each item needs a 'label' value. Additionally, if the supportable template is a subset of
		 * another condition (e.g. is_singular > is_single) then this relationship needs to be
		 * indicated via the 'parent' value.
		 *
		 * @since 1.0
		 *
		 * @param array $templates Supportable templates.
		 */
		$templates = apply_filters( 'amp_supportable_templates', $templates );

		$theme_support_args        = self::get_theme_support_args();
		$theme_supported_templates = [];
		if ( isset( $theme_support_args['templates_supported'] ) ) {
			$theme_supported_templates = $theme_support_args['templates_supported'];
		}

		$supported_templates = AMP_Options_Manager::get_option( 'supported_templates' );
		foreach ( $templates as $id => &$template ) {

			// Capture user-elected support from options. This allows us to preserve the original user selection through programmatic overrides.
			$template['user_supported'] = in_array( $id, $supported_templates, true );

			// Consider supported templates from theme support args.
			if ( ! isset( $template['supported'] ) ) {
				if ( 'all' === $theme_supported_templates ) {
					$template['supported'] = true;
				} elseif ( is_array( $theme_supported_templates ) && isset( $theme_supported_templates[ $id ] ) ) {
					$template['supported'] = $theme_supported_templates[ $id ];
				}
			}

			// Make supported state immutable if it was programmatically set.
			$template['immutable'] = isset( $template['supported'] );

			// Set supported state from user preference.
			if ( ! $template['immutable'] ) {
				$template['supported'] = AMP_Options_Manager::get_option( 'all_templates_supported' ) || $template['user_supported'];
			}
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

		add_action( 'wp_footer', 'amp_print_analytics' );

		/*
		 * Start output buffering at very low priority for sake of plugins and themes that use template_redirect
		 * instead of template_include.
		 */
		$priority = defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~PHP_INT_MAX; // phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
		add_action( 'template_redirect', [ __CLASS__, 'start_output_buffering' ], $priority );

		// Commenting hooks.
		add_filter( 'comment_form_defaults', [ __CLASS__, 'filter_comment_form_defaults' ] );
		add_filter( 'comment_reply_link', [ __CLASS__, 'filter_comment_reply_link' ], 10, 4 );
		add_filter( 'cancel_comment_reply_link', [ __CLASS__, 'filter_cancel_comment_reply_link' ], 10, 3 );
		add_action( 'comment_form', [ __CLASS__, 'amend_comment_form' ], 100 );
		remove_action( 'comment_form', 'wp_comment_form_unfiltered_html_nonce' );
		add_filter( 'wp_kses_allowed_html', [ __CLASS__, 'whitelist_layout_in_wp_kses_allowed_html' ], 10 );
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

		// @todo Add character conversion.
	}

	/**
	 * Register/override widgets.
	 *
	 * @global WP_Widget_Factory
	 * @return void
	 */
	public static function register_widgets() {
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
	 * Amend the comment form with the redirect_to field to persist the AMP page after submission.
	 */
	public static function amend_comment_form() {
		?>
		<?php if ( is_singular() && ! amp_is_canonical() ) : ?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( amp_get_permalink( get_the_ID() ) ); ?>">
		<?php endif; ?>
		<?php
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
	 * Get canonical URL for current request.
	 *
	 * @see rel_canonical()
	 * @global WP $wp
	 * @global WP_Rewrite $wp_rewrite
	 * @link https://www.ampproject.org/docs/reference/spec#canon.
	 * @link https://core.trac.wordpress.org/ticket/18660
	 *
	 * @return string Canonical non-AMP URL.
	 */
	public static function get_current_canonical_url() {
		global $wp, $wp_rewrite;

		$url = null;
		if ( is_singular() ) {
			$url = wp_get_canonical_url();
		}

		// For non-singular queries, make use of the request URI and public query vars to determine canonical URL.
		if ( empty( $url ) ) {
			$added_query_vars = $wp->query_vars;
			if ( ! $wp_rewrite->permalink_structure || empty( $wp->request ) ) {
				$url = home_url( '/' );
			} else {
				$url = home_url( user_trailingslashit( $wp->request ) );
				parse_str( $wp->matched_query, $matched_query_vars );
				foreach ( $wp->query_vars as $key => $value ) {

					// Remove query vars that were matched in the rewrite rules for the request.
					if ( isset( $matched_query_vars[ $key ] ) ) {
						unset( $added_query_vars[ $key ] );
					}
				}
			}
		}

		if ( ! empty( $added_query_vars ) ) {
			$url = add_query_arg( $added_query_vars, $url );
		}

		return amp_remove_endpoint( $url );
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
	 * @param array $args Comment form args.
	 * @return array Filtered comment form args.
	 */
	public static function filter_comment_form_defaults( $args ) {
		$state_id = self::get_comment_form_state_id( get_the_ID() );

		$text_binding = sprintf(
			'%s.replyToName ? %s : %s',
			$state_id,
			str_replace(
				'%s',
				sprintf( '" + %s.replyToName + "', $state_id ),
				wp_json_encode( $args['title_reply_to'], JSON_UNESCAPED_UNICODE )
			),
			wp_json_encode( $args['title_reply'], JSON_UNESCAPED_UNICODE )
		);

		$args['title_reply_before'] .= sprintf(
			'<span [text]="%s">',
			esc_attr( $text_binding )
		);
		$args['cancel_reply_before'] = '</span>' . $args['cancel_reply_before'];
		return $args;
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
	 * Add data-ampdevmode attribute to any enqueued style that depends on the admin-bar.
	 *
	 * @since 1.3
	 *
	 * @param string $tag    The link tag for the enqueued style.
	 * @param string $handle The style's registered handle.
	 * @return string Tag.
	 */
	public static function filter_admin_bar_style_loader_tag( $tag, $handle ) {
		if ( 'dashicons' === $handle ) {
			// Conditionally include Dashicons in dev mode only if was included because it is a dependency of admin-bar.
			$needs_dev_mode = true;
			foreach ( wp_styles()->queue as $queued_handle ) {
				if (
					// If a theme or plugin directly enqueued dashicons, then it is not added via admin-bar dependency and it is not part of dev mode.
					'dashicons' === $queued_handle
					||
					// If a stylesheet has dashicons as a dependency without also having admin-bar as a dependency, then no dev mode.
					(
						self::has_dependency( wp_styles(), $queued_handle, 'dashicons' )
						&&
						! self::has_dependency( wp_styles(), $queued_handle, 'admin-bar' )
					)
				) {
					$needs_dev_mode = false;
					break;
				}
			}
		} else {
			$needs_dev_mode = self::has_dependency( wp_styles(), $handle, 'admin-bar' );
		}

		if ( $needs_dev_mode ) {
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
		if ( self::has_dependency( wp_scripts(), $handle, 'admin-bar' ) ) {
			$tag = preg_replace( '/(?<=<script)(?=\s|>)/i', ' ' . AMP_Rule_Spec::DEV_MODE_ATTRIBUTE, $tag );
		}
		return $tag;
	}

	/**
	 * Ensure the markup exists as required by AMP and elements are in the optimal loading order.
	 *
	 * Ensure meta[charset], meta[name=viewport], and link[rel=canonical] exist, as the whitelist sanitizer
	 * may have removed an illegal meta[http-equiv] or meta[name=viewport]. For a singular post, core only outputs a
	 * canonical URL by default. Adds the preload links.
	 *
	 * @since 0.7
	 * @link https://www.ampproject.org/docs/reference/spec#required-markup
	 * @link https://amp.dev/documentation/guides-and-tutorials/optimize-and-measure/optimize_amp/
	 * @todo All of this might be better placed inside of a sanitizer.
	 *
	 * @param DOMDocument $dom            Document.
	 * @param string[]    $script_handles AMP script handles for components identified during output buffering.
	 */
	public static function ensure_required_markup( DOMDocument $dom, $script_handles = [] ) {
		/**
		 * Elements.
		 *
		 * @var DOMElement $meta
		 * @var DOMElement $script
		 * @var DOMElement $link
		 * @var DOMElement $style
		 * @var DOMElement $noscript
		 */

		$xpath = new DOMXPath( $dom );

		// Make sure the HEAD element is in the doc.
		$head = $dom->getElementsByTagName( 'head' )->item( 0 );
		if ( ! $head ) {
			$head = $dom->createElement( 'head' );
			$dom->documentElement->insertBefore( $head, $dom->documentElement->firstChild );
		}

		// Ensure there is a schema.org script in the document.
		// @todo Consider applying the amp_schemaorg_metadata filter on the contents when a script is already present.
		$schema_org_meta_script = $xpath->query( '//script[ @type = "application/ld+json" ][ contains( ./text(), "schema.org" ) ]' )->item( 0 );
		if ( ! $schema_org_meta_script ) {
			$script = $dom->createElement( 'script' );
			$script->setAttribute( 'type', 'application/ld+json' );
			$script->appendChild( $dom->createTextNode( wp_json_encode( amp_get_schemaorg_metadata(), JSON_UNESCAPED_UNICODE ) ) );
			$head->appendChild( $script );
		}

		// Gather all links.
		$links         = [
			'preconnect' => [
				// Include preconnect link for AMP CDN for browsers that don't support preload.
				AMP_DOM_Utils::create_node(
					$dom,
					'link',
					[
						'rel'  => 'preconnect',
						'href' => 'https://cdn.ampproject.org',
					]
				),
			],
		];
		$link_elements = $head->getElementsByTagName( 'link' );
		foreach ( $link_elements as $link ) {
			if ( $link->hasAttribute( 'rel' ) ) {
				$links[ $link->getAttribute( 'rel' ) ][] = $link;
			}
		}

		// Ensure rel=canonical link.
		$rel_canonical = null;
		if ( empty( $links['canonical'] ) ) {
			$rel_canonical = AMP_DOM_Utils::create_node(
				$dom,
				'link',
				[
					'rel'  => 'canonical',
					'href' => self::get_current_canonical_url(),
				]
			);
			$head->appendChild( $rel_canonical );
		}

		/*
		 * Ensure meta charset and meta viewport are present.
		 *
		 * "AMP is already quite restrictive about which markup is allowed in the <head> section. However,
		 * there are a few basic optimizations that you can apply. The key is to structure the <head> section
		 * in a way so that all render-blocking scripts and custom fonts load as fast as possible."
		 *
		 * "1. The first tag should be the meta charset tag, followed by any remaining meta tags."
		 *
		 * {@link https://amp.dev/documentation/guides-and-tutorials/optimize-and-measure/optimize_amp/ Optimize the AMP Runtime loading}
		 */
		$meta_charset         = null;
		$meta_viewport        = null;
		$meta_amp_script_srcs = [];
		$meta_elements        = [];
		foreach ( $head->getElementsByTagName( 'meta' ) as $meta ) {
			if ( $meta->hasAttribute( 'charset' ) ) { // There will not be a meta[http-equiv] because the sanitizer removed it.
				$meta_charset = $meta;
			} elseif ( 'viewport' === $meta->getAttribute( 'name' ) ) {
				$meta_viewport = $meta;
			} elseif ( 'amp-script-src' === $meta->getAttribute( 'name' ) ) {
				$meta_amp_script_srcs[] = $meta;
			} else {
				$meta_elements[] = $meta;
			}
		}

		// Handle meta charset.
		if ( ! $meta_charset ) {
			// Warning: This probably means the character encoding needs to be converted.
			$meta_charset = AMP_DOM_Utils::create_node(
				$dom,
				'meta',
				[
					'charset' => 'utf-8',
				]
			);
		} else {
			$head->removeChild( $meta_charset ); // So we can move it.
		}
		$head->insertBefore( $meta_charset, $head->firstChild );

		// Handle meta viewport.
		if ( ! $meta_viewport ) {
			$meta_viewport = AMP_DOM_Utils::create_node(
				$dom,
				'meta',
				[
					'name'    => 'viewport',
					'content' => 'width=device-width',
				]
			);
		} else {
			$head->removeChild( $meta_viewport ); // So we can move it.
		}
		$head->insertBefore( $meta_viewport, $meta_charset->nextSibling );

		// Handle meta amp-script-src elements.
		$first_meta_amp_script_src = array_shift( $meta_amp_script_srcs );
		if ( $first_meta_amp_script_src ) {
			$meta_elements[] = $first_meta_amp_script_src;

			// Merge (and remove) any subsequent meta amp-script-src elements.
			if ( ! empty( $meta_amp_script_srcs ) ) {
				$content_values = [ $first_meta_amp_script_src->getAttribute( 'content' ) ];
				foreach ( $meta_amp_script_srcs as $meta_amp_script_src ) {
					$meta_amp_script_src->parentNode->removeChild( $meta_amp_script_src );
					$content_values[] = $meta_amp_script_src->getAttribute( 'content' );
				}
				$first_meta_amp_script_src->setAttribute( 'content', implode( ' ', $content_values ) );
				unset( $meta_amp_script_src, $content_values );
			}
		}
		unset( $meta_amp_script_srcs, $first_meta_amp_script_src );

		// Insert all the the meta elements next in the head.
		$previous_node = $meta_viewport;
		foreach ( $meta_elements as $meta_element ) {
			$meta_element->parentNode->removeChild( $meta_element );
			$head->insertBefore( $meta_element, $previous_node->nextSibling );
			$previous_node = $meta_element;
		}

		// Handle the title.
		$title = $head->getElementsByTagName( 'title' )->item( 0 );
		if ( $title ) {
			$title->parentNode->removeChild( $title ); // So we can move it.
			$head->insertBefore( $title, $previous_node->nextSibling );
			$previous_node = $title;
		}

		// @see https://github.com/ampproject/amphtml/blob/2fd30ca984bceac05905bd5b17f9e0010629d719/src/render-delaying-services.js#L39-L43 AMPHTML Render Delaying Services SERVICES definition.
		$render_delaying_extensions = [
			'amp-experiment',
			'amp-dynamic-css-classes',
			'amp-story',
		];

		// Obtain the existing AMP scripts.
		$amp_scripts     = [];
		$ordered_scripts = [];
		$head_scripts    = [];
		$runtime_src     = wp_scripts()->registered['amp-runtime']->src;
		foreach ( $head->getElementsByTagName( 'script' ) as $script ) { // Note that prepare_response() already moved body scripts to head.
			$head_scripts[] = $script;
		}
		foreach ( $head_scripts as $script ) {
			$src = $script->getAttribute( 'src' );
			if ( ! $src || 'https://cdn.ampproject.org/' !== substr( $src, 0, 27 ) ) {
				continue;
			}
			if ( $runtime_src === $src ) {
				$amp_scripts['amp-runtime'] = $script;
			} elseif ( $script->hasAttribute( 'custom-element' ) ) {
				$amp_scripts[ $script->getAttribute( 'custom-element' ) ] = $script;
			} elseif ( $script->hasAttribute( 'custom-template' ) ) {
				$amp_scripts[ $script->getAttribute( 'custom-template' ) ] = $script;
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
				'src'   => wp_scripts()->registered[ $missing_script_handle ]->src,
				'async' => '',
			];
			if ( 'amp-mustache' === $missing_script_handle ) {
				$attrs['custom-template'] = $missing_script_handle;
			} else {
				$attrs['custom-element'] = $missing_script_handle;
			}

			$amp_scripts[ $missing_script_handle ] = AMP_DOM_Utils::create_node( $dom, 'script', $attrs );
		}

		// Remove scripts that had already been added but couldn't be detected from output buffering.
		$extension_specs            = AMP_Allowed_Tags_Generated::get_extension_specs();
		$superfluous_script_handles = array_diff(
			array_keys( $amp_scripts ),
			array_merge( $script_handles, [ 'amp-runtime' ] )
		);
		foreach ( $superfluous_script_handles as $superfluous_script_handle ) {
			if ( ! empty( $extension_specs[ $superfluous_script_handle ]['requires_usage'] ) ) {
				unset( $amp_scripts[ $superfluous_script_handle ] );
			}
		}

		/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		 *
		 * "2. Next, preload the AMP runtime v0.js <script> tag with <link as=script href=https://cdn.ampproject.org/v0.js rel=preload>.
		 * The AMP runtime should start downloading as soon as possible because the AMP boilerplate hides the document via body { visibility:hidden }
		 * until the AMP runtime has loaded. Preloading the AMP runtime tells the browser to download the script with a higher priority."
		 * {@link https://amp.dev/documentation/guides-and-tutorials/optimize-and-measure/optimize_amp/ Optimize the AMP Runtime loading}
		 */
		$prioritized_preloads = [];
		if ( ! isset( $links['preload'] ) ) {
			$links['preload'] = [];
		}

		$prioritized_preloads[] = AMP_DOM_Utils::create_node(
			$dom,
			'link',
			[
				'rel'  => 'preload',
				'as'   => 'script',
				'href' => $runtime_src,
			]
		);

		/*
		 * "3. If your page includes render-delaying extensions (e.g., amp-experiment, amp-dynamic-css-classes, amp-story),
		 * preload those extensions as they're required by the AMP runtime for rendering the page."
		 */
		$amp_script_handles = array_keys( $amp_scripts );
		foreach ( array_intersect( $render_delaying_extensions, $amp_script_handles ) as $script_handle ) {
			if ( ! in_array( $script_handle, $render_delaying_extensions, true ) ) {
				continue;
			}
			$prioritized_preloads[] = AMP_DOM_Utils::create_node(
				$dom,
				'link',
				[
					'rel'  => 'preload',
					'as'   => 'script',
					'href' => $amp_scripts[ $script_handle ]->getAttribute( 'src' ),
				]
			);
		}
		$links['preload'] = array_merge( $prioritized_preloads, $links['preload'] );

		/*
		 * "4. Use preconnect to speedup the connection to other origin where the full resource URL is not known ahead of time,
		 * for example, when using Google Fonts."
		 *
		 * Note that \AMP_Style_Sanitizer::process_link_element() will ensure preconnect links for Google Fonts are present.
		 */
		$link_relations = [ 'preconnect', 'dns-prefetch', 'preload', 'prerender', 'prefetch' ];
		foreach ( $link_relations as $rel ) {
			if ( ! isset( $links[ $rel ] ) ) {
				continue;
			}
			foreach ( $links[ $rel ] as $link ) {
				if ( $link->parentNode ) {
					$link->parentNode->removeChild( $link ); // So we can move it.
				}
				$head->insertBefore( $link, $previous_node->nextSibling );
				$previous_node = $link;
			}
		}

		// "5. Load the AMP runtime."
		if ( isset( $amp_scripts['amp-runtime'] ) ) {
			$ordered_scripts['amp-runtime'] = $amp_scripts['amp-runtime'];
			unset( $amp_scripts['amp-runtime'] );
		} else {
			$script = $dom->createElement( 'script' );
			$script->setAttribute( 'async', '' );
			$script->setAttribute( 'src', $runtime_src );
			$ordered_scripts['amp-runtime'] = $script;
		}

		/*
		 * "6. Specify the <script> tags for render-delaying extensions (e.g., amp-experiment amp-dynamic-css-classes and amp-story"
		 *
		 * {@link https://amp.dev/documentation/guides-and-tutorials/optimize-and-measure/optimize_amp/ AMP Hosting Guide}
		 */
		foreach ( $render_delaying_extensions as $extension ) {
			if ( isset( $amp_scripts[ $extension ] ) ) {
				$ordered_scripts[ $extension ] = $amp_scripts[ $extension ];
				unset( $amp_scripts[ $extension ] );
			}
		}

		/*
		 * "7. Specify the <script> tags for remaining extensions (e.g., amp-bind ...). These extensions are not render-delaying
		 * and therefore should not be preloaded as they might take away important bandwidth for the initial render."
		 */
		$ordered_scripts = array_merge( $ordered_scripts, $amp_scripts );
		foreach ( $ordered_scripts as $ordered_script ) {
			$head->insertBefore( $ordered_script, $previous_node->nextSibling );
			$previous_node = $ordered_script;
		}

		/*
		 * "8. Specify any custom styles by using the <style amp-custom> tag."
		 */
		$style = $xpath->query( './style[ @amp-custom ]', $head )->item( 0 );
		if ( $style ) {
			// Ensure the CSS manifest comment remains before style[amp-custom].
			if ( $style->previousSibling instanceof DOMComment ) {
				$comment = $style->previousSibling;
				$comment->parentNode->removeChild( $comment );
				$head->insertBefore( $comment, $previous_node->nextSibling );
				$previous_node = $comment;
			}

			$style->parentNode->removeChild( $style );
			$head->insertBefore( $style, $previous_node->nextSibling );
			$previous_node = $style;
		}

		/*
		 * "9. Add any other tags allowed in the <head> section. In particular, any external fonts should go last since
		 * they block rendering."
		 */

		/*
		 * "10. Finally, specify the AMP boilerplate code. By putting the boilerplate code last, it prevents custom styles
		 * from accidentally overriding the boilerplate css rules."
		 */
		$style = $xpath->query( './style[ @amp-boilerplate ]', $head )->item( 0 );
		if ( ! $style ) {
			$style = $dom->createElement( 'style' );
			$style->setAttribute( 'amp-boilerplate', '' );
			$style->appendChild( $dom->createTextNode( amp_get_boilerplate_stylesheets()[0] ) );
		} else {
			$style->parentNode->removeChild( $style ); // So we can move it.
		}
		$head->appendChild( $style );

		$noscript = $xpath->query( './noscript[ style[ @amp-boilerplate ] ]', $head )->item( 0 );
		if ( ! $noscript ) {
			$noscript = $dom->createElement( 'noscript' );
			$style    = $dom->createElement( 'style' );
			$style->setAttribute( 'amp-boilerplate', '' );
			$style->appendChild( $dom->createTextNode( amp_get_boilerplate_stylesheets()[1] ) );
			$noscript->appendChild( $style );
		} else {
			$noscript->parentNode->removeChild( $noscript ); // So we can move it.
		}
		$head->appendChild( $noscript );

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

		if ( AMP_Debug::has_flag( AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR ) ) {
			return;
		}

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
		return self::prepare_response( $response );
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
				'use_document_element' => false,
				'allow_dirty_styles'   => true,
				'allow_dirty_scripts'  => false,
			];
			AMP_Content_Sanitizer::sanitize_document( $dom, self::$sanitizer_classes, $args ); // @todo Include script assets in response?
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
	 * @param array  $args     Args to send to the preprocessor/sanitizer.
	 * @return string AMP document response.
	 * @global int $content_width
	 */
	public static function prepare_response( $response, $args = [] ) {
		global $content_width;
		$prepare_response_start = microtime( true );

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
			return wp_json_encode(
				[
					'status_code' => $status_code,
					'status_text' => get_status_header_desc( $status_code ),
				]
			);
		}

		/*
		 * Abort if the response was not HTML. To be post-processed as an AMP page, the output-buffered document must
		 * have the HTML mime type and it must start with <html> followed by <head> tag (with whitespace, doctype, and comments optionally interspersed).
		 */
		if ( 'text/html' !== substr( AMP_HTTP::get_response_content_type(), 0, 9 ) || ! preg_match( '#^(?:<!.*?>|\s+)*<html.*?>(?:<!.*?>|\s+)*<head\b(.*?)>#is', $response ) ) {
			return $response;
		}

		/**
		 * Filters whether response (post-processor) caching is enabled.
		 *
		 * When enabled and when an external object cache is present, the output of the post-processor phase is stored in
		 * in the object cache. When another request is made that generates the same HTML output, the previously-cached
		 * post-processor output will then be served immediately and bypass needlessly re-running the sanitizers.
		 * This does not apply when:
		 *
		 * - AMP validation is being performed.
		 * - The response is in the Customizer preview.
		 * - Response caching is disabled due to a high-rate of cache misses.
		 *
		 * @param bool $enable_response_caching Whether response caching is enabled.
		 */
		$enable_response_caching = apply_filters( 'amp_response_caching_enabled', ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ! empty( $args['enable_response_caching'] ) );
		$enable_response_caching = (
			$enable_response_caching
			&&
			! AMP_Validation_Manager::should_validate_response()
			&&
			! is_customize_preview()
			&&
			! AMP_Debug::has_flag( AMP_Debug::DISABLE_RESPONSE_CACHE_QUERY_VAR )
		);

		// When response caching is enabled, determine if it should be turned off for cache misses.
		$caches_for_url = null;
		if ( $enable_response_caching ) {
			list( $disable_response_caching, $caches_for_url ) = self::check_for_cache_misses();
			$enable_response_caching                           = ! $disable_response_caching;
		}

		// @todo Both allow_dirty_styles and allow_dirty_scripts should eventually use AMP dev mode instead.
		$args = array_merge(
			[
				'content_max_width'    => ! empty( $content_width ) ? $content_width : AMP_Post_Template::CONTENT_MAX_WIDTH, // Back-compat.
				'use_document_element' => true,
				'allow_dirty_styles'   => self::is_customize_preview_iframe(), // Dirty styles only needed when editing (e.g. for edit shortcuts).
				'allow_dirty_scripts'  => is_customize_preview(), // Scripts are always needed to inject changeset UUID.
				'user_can_validate'    => AMP_Validation_Manager::has_cap(),
			],
			$args,
			compact( 'enable_response_caching' )
		);

		$current_url = amp_get_current_url();
		$non_amp_url = amp_remove_endpoint( $current_url );

		/*
		 * Set response cache hash, the data values dictates whether a new hash key should be generated or not.
		 * This is also used as the ETag.
		 */
		$response_cache_key = md5(
			wp_json_encode(
				[
					$args,
					$response,
					self::$sanitizer_classes,
					self::$embed_handlers,
					AMP__VERSION,
				]
			)
		);

		/*
		 * Per rfc7232:
		 * "The server generating a 304 response MUST generate any of the
		 * following header fields that would have been sent in a 200 (OK)
		 * response to the same request: Cache-Control, Content-Location, Date,
		 * ETag, Expires, and Vary." The only one of these headers which would
		 * not have been set yet during the WordPress template generation is
		 * the ETag. The AMP plugin sends a Vary header at amp_init.
		 */
		AMP_HTTP::send_header( 'ETag', '"' . $response_cache_key . '"' );

		/*
		 * Handle responses that are cached by the browser, returning 304 response if the response cache key
		 * matches any ETags mentioned in If-None-Match request header. Note that if the client request indicates a
		 * weak validator (prefixed by W/) then this will be ignored. The MD5 strings will be extracted from the
		 * If-None-Match request header and if any of them match the $response_cache_key then a 304 Not Modified
		 * response is returned.
		 *
		 * Such 304 Not Modified responses are only enabled when using a stable release. This is not enabled for
		 * non-stable releases (like 1.2-beta2) because the plugin would be under active development and such caching
		 * would make it more difficult to see changes applied to the sanitizers. (A browser's cache would have to be
		 * disabled or the developer would have to always do hard reloads.)
		 */
		$has_matching_etag = (
			false === strpos( AMP__VERSION, '-' )
			&&
			isset( $_SERVER['HTTP_IF_NONE_MATCH'] )
			&&
			preg_match_all( '#\b[0-9a-f]{32}\b#', wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ), $etag_match_candidates )
			&&
			in_array( $response_cache_key, $etag_match_candidates[0], true )
		);
		if ( $has_matching_etag ) {
			status_header( 304 );
			return '';
		}

		// Return cache if enabled and found.
		$cache_response = null;
		if ( true === $args['enable_response_caching'] ) {
			$response_cache = wp_cache_get( $response_cache_key, self::RESPONSE_CACHE_GROUP );

			// Make sure that all of the validation errors should be sanitized in the same way; if not, then the cached body should be discarded.
			$blocking_error_count = 0;
			if ( isset( $response_cache['validation_results'] ) ) {
				foreach ( $response_cache['validation_results'] as $validation_result ) {
					if ( ! $validation_result['sanitized'] ) {
						$blocking_error_count++;
					}
					$should_sanitize = AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $validation_result['error'] );
					if ( $should_sanitize !== $validation_result['sanitized'] ) {
						unset( $response_cache['body'] );
						break;
					}
				}
			}

			// Short-circuit response with cached body.
			if ( isset( $response_cache['body'] ) ) {

				// Re-send the headers that were sent before when the response was first cached.
				if ( isset( $response_cache['headers'] ) ) {
					foreach ( $response_cache['headers'] as $header ) {
						if ( in_array( $header, AMP_HTTP::$headers_sent, true ) ) {
							continue; // Skip sending headers that were already sent prior to post-processing.
						}
						AMP_HTTP::send_header( $header['name'], $header['value'], wp_array_slice_assoc( $header, [ 'replace', 'status_code' ] ) );
					}
				}

				AMP_HTTP::send_server_timing( 'amp_processor_cache_hit', -$prepare_response_start );

				// Redirect to non-AMP version.
				if (
					! amp_is_canonical()
					&&
					! is_singular( AMP_Story_Post_Type::POST_TYPE_SLUG )
					&&
					$blocking_error_count > 0
					&&
					! AMP_Debug::has_flag( AMP_Debug::PREVENT_REDIRECT_TO_NON_AMP_QUERY_VAR )
				) {
					if ( AMP_Validation_Manager::has_cap() ) {
						$non_amp_url = add_query_arg( AMP_Validation_Manager::VALIDATION_ERRORS_QUERY_VAR, $blocking_error_count, $non_amp_url );
					}

					/*
					 * Temporary redirect because AMP page may return with blocking validation errors when auto-accepting sanitization
					 * is not enabled. A 302 will allow the errors to be fixed without needing to bust any redirect caches.
					 */
					wp_safe_redirect( $non_amp_url, 302 );
				}
				return $response_cache['body'];
			}

			$cache_response = static function( $body, $validation_results ) use ( $response_cache_key, $caches_for_url ) {
				$caches_for_url[] = $response_cache_key;
				wp_cache_set(
					AMP_Theme_Support::POST_PROCESSOR_CACHE_EFFECTIVENESS_KEY,
					$caches_for_url,
					AMP_Theme_Support::POST_PROCESSOR_CACHE_EFFECTIVENESS_GROUP,
					600 // 10 minute cache.
				);

				return wp_cache_set(
					$response_cache_key,
					[
						'headers'            => AMP_HTTP::$headers_sent,
						'body'               => $body,
						'validation_results' => $validation_results,
					],
					AMP_Theme_Support::RESPONSE_CACHE_GROUP,
					MONTH_IN_SECONDS
				);
			};
		}

		AMP_HTTP::send_server_timing( 'amp_output_buffer', -self::$init_start_time, 'AMP Output Buffer' );

		$dom_parse_start = microtime( true );

		/*
		 * Make sure that <meta charset> is present in output prior to parsing.
		 * Note that the meta charset is supposed to appear within the first 1024 bytes.
		 * See <https://www.w3.org/International/questions/qa-html-encoding-declarations>.
		 */
		if ( ! preg_match( '#<meta[^>]+charset=#i', substr( $response, 0, 1024 ) ) ) {
			$meta_charset = sprintf( '<meta charset="%s">', esc_attr( get_bloginfo( 'charset' ) ) );

			$response = preg_replace(
				'/(<head\b.*?>)/is',
				'$1' . $meta_charset,
				$response,
				1,
				$count
			);
		}

		$dom   = AMP_DOM_Utils::get_dom( $response );
		$xpath = new DOMXPath( $dom );
		$head  = $dom->getElementsByTagName( 'head' )->item( 0 );

		// Move anything after </html>, such as Query Monitor output added at shutdown, to be moved before </body>.
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( $body ) {
			while ( $dom->documentElement->nextSibling ) {
				// Trailing elements after </html> will get wrapped in additional <html> elements.
				if ( 'html' === $dom->documentElement->nextSibling->nodeName ) {
					while ( $dom->documentElement->nextSibling->firstChild ) {
						$body->appendChild( $dom->documentElement->nextSibling->firstChild );
					}
					$dom->removeChild( $dom->documentElement->nextSibling );
				} else {
					$body->appendChild( $dom->documentElement->nextSibling );
				}
			}
		}

		AMP_HTTP::send_server_timing( 'amp_dom_parse', -$dom_parse_start, 'AMP DOM Parse' );

		// Make sure scripts from the body get moved to the head.
		if ( isset( $head ) ) {
			foreach ( $xpath->query( '//body//script[ @custom-element or @custom-template or @src = "https://cdn.ampproject.org/v0.js" ]' ) as $script ) {
				$head->appendChild( $script->parentNode->removeChild( $script ) );
			}
		}

		// Ensure the mandatory amp attribute is present on the html element.
		if ( ! $dom->documentElement->hasAttribute( 'amp' ) && ! $dom->documentElement->hasAttribute( '⚡️' ) ) {
			$dom->documentElement->setAttribute( 'amp', '' );
		}

		$assets = AMP_Content_Sanitizer::sanitize_document( $dom, self::$sanitizer_classes, $args );

		// Determine what the validation errors are.
		$blocking_error_count = 0;
		$validation_results   = [];
		foreach ( AMP_Validation_Manager::$validation_results as $validation_result ) {
			if ( ! $validation_result['sanitized'] ) {
				$blocking_error_count++;
			}
			unset( $validation_result['error']['sources'] );
			$validation_results[] = $validation_result;
		}

		$dom_serialize_start = microtime( true );

		// Gather all component scripts that are used in the document and then render any not already printed.
		$amp_scripts = $assets['scripts'];
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

		if ( $blocking_error_count > 0 && ! AMP_Validation_Manager::should_validate_response() ) {
			/*
			 * In AMP-first, strip html@amp attribute to prevent GSC from complaining about a validation error
			 * already surfaced inside of WordPress. This is intended to not serve dirty AMP, but rather a
			 * non-AMP document (intentionally not valid AMP) that contains the AMP runtime and AMP components.
			 */
			if ( amp_is_canonical() || is_singular( AMP_Story_Post_Type::POST_TYPE_SLUG ) ) {
				$dom->documentElement->removeAttribute( 'amp' );
				$dom->documentElement->removeAttribute( '⚡️' );

				/*
				 * Make sure that document.write() is disabled to prevent dynamically-added content (such as added
				 * via amp-live-list) from wiping out the page by introducing any scripts that call this function.
				 */
				if ( $head ) {
					$script = $dom->createElement( 'script' );
					$script->appendChild( $dom->createTextNode( 'document.addEventListener( "DOMContentLoaded", function() { document.write = function( text ) { throw new Error( "[AMP-WP] Prevented document.write() call with: "  + text ); }; } );' ) );
					$head->appendChild( $script );
				}
			} elseif ( ! self::is_customize_preview_iframe() && ! AMP_Debug::has_flag( AMP_Debug::PREVENT_REDIRECT_TO_NON_AMP_QUERY_VAR ) ) {
				$response = esc_html__( 'Redirecting to non-AMP version.', 'amp' );

				if ( $cache_response ) {
					$cache_response( $response, $validation_results );
				}

				// Indicate the number of validation errors detected at runtime in a query var on the non-AMP page for display in the admin bar.
				if ( AMP_Validation_Manager::has_cap() ) {
					$non_amp_url = add_query_arg( AMP_Validation_Manager::VALIDATION_ERRORS_QUERY_VAR, $blocking_error_count, $non_amp_url );
				}

				// Remove debugging query args.
				$non_amp_url = remove_query_arg( AMP_Debug::AMP_FLAGS_QUERY_VAR, $non_amp_url );

				/*
				 * Temporary redirect because AMP page may return with blocking validation errors when auto-accepting sanitization
				 * is not enabled. A 302 will allow the errors to be fixed without needing to bust any redirect caches.
				 */
				wp_safe_redirect( $non_amp_url, 302 );
				return $response;
			}
		}

		// @todo If 'utf-8' is not the blog charset, then we'll need to do some character encoding conversation or "entityification".
		if ( 'utf-8' !== strtolower( get_bloginfo( 'charset' ) ) ) {
			/* translators: %s: the charset of the current site. */
			trigger_error( esc_html( sprintf( __( 'The database has the %s encoding when it needs to be utf-8 to work with AMP.', 'amp' ), get_bloginfo( 'charset' ) ) ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		}

		AMP_Validation_Manager::finalize_validation(
			$dom,
			[
				'remove_source_comments' => ! isset( $_GET['amp_preserve_source_comments'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			]
		);

		$response  = "<!DOCTYPE html>\n";
		$response .= AMP_DOM_Utils::get_content_from_dom_node( $dom, $dom->documentElement );

		AMP_HTTP::send_server_timing( 'amp_dom_serialize', -$dom_serialize_start, 'AMP DOM Serialize' );

		// Cache response if enabled.
		if ( $cache_response ) {
			$cache_response( $response, $validation_results );
		}

		return $response;
	}

	/**
	 * Check for cache misses. When found, store in an option to retain the URL.
	 *
	 * @since 1.0
	 *
	 * @return array {
	 *     State.
	 *
	 *     @type bool       Flag indicating if the threshold has been exceeded.
	 *     @type string[]   Collection of URLs.
	 * }
	 */
	private static function check_for_cache_misses() {
		// If the cache miss threshold is exceeded, return true.
		if ( self::exceeded_cache_miss_threshold() ) {
			return [ true, null ];
		}

		// Get the cache miss URLs.
		$cache_miss_urls = wp_cache_get( self::POST_PROCESSOR_CACHE_EFFECTIVENESS_KEY, self::POST_PROCESSOR_CACHE_EFFECTIVENESS_GROUP );
		$cache_miss_urls = is_array( $cache_miss_urls ) ? $cache_miss_urls : [];

		$exceeded_threshold = (
			! empty( $cache_miss_urls )
			&&
			count( $cache_miss_urls ) >= self::CACHE_MISS_THRESHOLD
		);

		if ( ! $exceeded_threshold ) {
			return [ $exceeded_threshold, $cache_miss_urls ];
		}

		// When the threshold is exceeded, store the URL for cache miss and turn off response caching.
		update_option( self::CACHE_MISS_URL_OPTION, amp_get_current_url() );
		AMP_Options_Manager::update_option( 'enable_response_caching', false );
		return [ true, null ];
	}

	/**
	 * Reset the cache miss URL option.
	 *
	 * @since 1.0
	 */
	public static function reset_cache_miss_url_option() {
		if ( get_option( self::CACHE_MISS_URL_OPTION ) ) {
			delete_option( self::CACHE_MISS_URL_OPTION );
		}
	}

	/**
	 * Checks if cache miss threshold has been exceeded.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public static function exceeded_cache_miss_threshold() {
		$url = get_option( self::CACHE_MISS_URL_OPTION, false );
		return ! empty( $url );
	}

	/**
	 * Adds 'data-amp-layout' to the allowed <img> attributes for wp_kses().
	 *
	 * @since 0.7
	 *
	 * @param array $context Allowed tags and their allowed attributes.
	 * @return array $context Filtered allowed tags and attributes.
	 */
	public static function whitelist_layout_in_wp_kses_allowed_html( $context ) {
		if ( ! empty( $context['img']['width'] ) && ! empty( $context['img']['height'] ) ) {
			$context['img']['data-amp-layout'] = true;
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
		wp_enqueue_style( 'amp-default', amp_get_asset_url( 'css/amp-default.css' ), [], AMP__VERSION );
		wp_styles()->add_data( 'amp-default', 'rtl', 'replace' );
	}

	/**
	 * Setup pages to have the paired browsing client script so that the app can interact with it.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public static function setup_paired_browsing_client() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::PAIRED_BROWSING_QUERY_VAR ] ) ) {
			return;
		}

		// Paired browsing requires a custom script which in turn requires dev mode.
		if ( ! amp_is_dev_mode() ) {
			return;
		}

		$asset_file   = AMP__DIR__ . '/assets/js/amp-paired-browsing-client.asset.php';
		$asset        = require $asset_file;
		$dependencies = $asset['dependencies'];
		$version      = $asset['version'];

		wp_enqueue_script(
			'amp-paired-browsing-client',
			amp_get_asset_url( '/js/amp-paired-browsing-client.js' ),
			$dependencies,
			$version,
			true
		);

		// Whitelist enqueued script for AMP dev mdoe so that it is not removed.
		// @todo Revisit with <https://github.com/google/site-kit-wp/pull/505#discussion_r348683617>.
		add_filter(
			'script_loader_tag',
			static function( $tag, $handle ) {
				if ( self::has_dependency( wp_scripts(), 'amp-paired-browsing-client', $handle ) ) {
					$attrs = [ AMP_Rule_Spec::DEV_MODE_ATTRIBUTE, 'async' ];
					$tag   = preg_replace( '/(?<=<script)(?=\s|>)/i', ' ' . implode( ' ', $attrs ), $tag );
				}
				return $tag;
			},
			10,
			2
		);
	}

	/**
	 * Get paired browsing URL for a given URL.
	 *
	 * @since 1.5.0
	 *
	 * @param string $url URL.
	 * @return string Paired browsing URL.
	 */
	public static function get_paired_browsing_url( $url = null ) {
		if ( ! $url ) {
			$url = wp_unslash( $_SERVER['REQUEST_URI'] );
		}
		$url = remove_query_arg(
			[ amp_get_slug(), AMP_Validated_URL_Post_Type::VALIDATE_ACTION, AMP_Validation_Manager::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR ],
			$url
		);
		$url = add_query_arg( self::PAIRED_BROWSING_QUERY_VAR, '1', $url );
		return $url;
	}

	/**
	 * Remove any unnecessary query vars that could hamper the paired browsing experience.
	 *
	 * @since 1.5.0
	 */
	public static function sanitize_url_for_paired_browsing() {
		if ( isset( $_GET[ self::PAIRED_BROWSING_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$original_url = wp_unslash( $_SERVER['REQUEST_URI'] );
			$updated_url  = self::get_paired_browsing_url( $original_url );
			if ( $updated_url !== $original_url ) {
				wp_safe_redirect( $updated_url );
				exit;
			}
		}
	}

	/**
	 * Serve paired browsing experience if it is being requested.
	 *
	 * Includes a custom template that acts as an interface to facilitate a side-by-side comparison of a
	 * non-AMP page and its AMP version to review any discrepancies.
	 *
	 * @since 1.5.0
	 *
	 * @param string $template Path of the template to include.
	 * @return string Custom template if in paired browsing mode, else the supplied template.
	 */
	public static function serve_paired_browsing_experience( $template ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::PAIRED_BROWSING_QUERY_VAR ] ) ) {
			return $template;
		}

		if ( ! amp_is_dev_mode() ) {
			wp_die(
				esc_html__( 'Paired browsing is only available when AMP dev mode is enabled (e.g. when logged-in and admin bar is showing).', 'amp' ),
				esc_html__( 'AMP Paired Browsing Unavailable', 'amp' ),
				[ 'response' => 403 ]
			);
		}

		wp_enqueue_style(
			'amp-paired-browsing-app',
			amp_get_asset_url( '/css/amp-paired-browsing-app-compiled.css' ),
			[ 'dashicons' ],
			AMP__VERSION
		);

		wp_styles()->add_data( 'amp-paired-browsing-app', 'rtl', 'replace' );

		$asset_file   = AMP__DIR__ . '/assets/js/amp-paired-browsing-app.asset.php';
		$asset        = require $asset_file;
		$dependencies = $asset['dependencies'];
		$version      = $asset['version'];

		wp_enqueue_script(
			'amp-paired-browsing-app',
			amp_get_asset_url( '/js/amp-paired-browsing-app.js' ),
			$dependencies,
			$version,
			true
		);

		wp_localize_script(
			'amp-paired-browsing-app',
			'app',
			[
				'ampSlug'                     => amp_get_slug(),
				'ampPairedBrowsingQueryVar'   => self::PAIRED_BROWSING_QUERY_VAR,
				'ampValidationErrorsQueryVar' => AMP_Validation_Manager::VALIDATION_ERRORS_QUERY_VAR,
				'documentTitlePrefix'         => __( 'AMP Paired Browsing:', 'amp' ),
			]
		);

		return AMP__DIR__ . '/includes/templates/amp-paired-browsing.php';
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
			'media'    => '(min-width: ' . $video_settings['minWidth'] . 'px)',
			'width'    => $video_settings['width'],
			'height'   => $video_settings['height'],
			'layout'   => 'responsive',
			'autoplay' => '',
			'loop'     => '',
			'id'       => 'wp-custom-header-video',
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
				'amp-youtube',
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
				'amp-video',
				array_merge(
					$video_attributes,
					[
						'src' => $video_settings['videoUrl'],
					]
				)
			);
		}

		return $image_markup . $video_markup;
	}
}

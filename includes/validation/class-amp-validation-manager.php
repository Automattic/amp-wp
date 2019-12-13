<?php
/**
 * Class AMP_Validation_Manager
 *
 * @package AMP
 */

/**
 * Class AMP_Validation_Manager
 *
 * @since 0.7
 */
class AMP_Validation_Manager {

	/**
	 * Query var that triggers validation.
	 *
	 * @var string
	 */
	const VALIDATE_QUERY_VAR = 'amp_validate';

	/**
	 * Query var for containing the number of validation errors on the frontend after redirection when invalid.
	 *
	 * @var string
	 */
	const VALIDATION_ERRORS_QUERY_VAR = 'amp_validation_errors';

	/**
	 * Query var for passing status preview/update for validation error.
	 *
	 * @var string
	 */
	const VALIDATION_ERROR_TERM_STATUS_QUERY_VAR = 'amp_validation_error_term_status';

	/**
	 * Query var for cache-busting.
	 *
	 * @var string
	 */
	const CACHE_BUST_QUERY_VAR = 'amp_cache_bust';

	/**
	 * Transient key to store validation errors when activating a plugin.
	 *
	 * @var string
	 */
	const PLUGIN_ACTIVATION_VALIDATION_ERRORS_TRANSIENT_KEY = 'amp_plugin_activation_validation_errors';

	/**
	 * The name of the REST API field with the AMP validation results.
	 *
	 * @var string
	 */
	const VALIDITY_REST_FIELD_NAME = 'amp_validity';

	/**
	 * The errors encountered when validating.
	 *
	 * @var array[][] {
	 *     @type array  $error     Error code.
	 *     @type bool   $sanitized Whether sanitized.
	 *     @type string $slug      Hash of the error.
	 * }
	 */
	public static $validation_results = [];

	/**
	 * Sources that enqueue each script.
	 *
	 * @var array
	 */
	public static $enqueued_script_sources = [];

	/**
	 * Sources that enqueue each style.
	 *
	 * @var array
	 */
	public static $enqueued_style_sources = [];

	/**
	 * Post IDs for posts that have been updated which need to be re-validated.
	 *
	 * Keys are post IDs and values are whether the post has been re-validated.
	 *
	 * @var bool[]
	 */
	public static $posts_pending_frontend_validation = [];

	/**
	 * Current sources gathered for a given hook currently being run.
	 *
	 * @see AMP_Validation_Manager::wrap_hook_callbacks()
	 * @see AMP_Validation_Manager::decorate_filter_source()
	 * @var array[]
	 */
	protected static $current_hook_source_stack = [];

	/**
	 * Index for where block appears in a post's content.
	 *
	 * @var int
	 */
	protected static $block_content_index = 0;

	/**
	 * Hook source stack.
	 *
	 * This has to be public for the sake of PHP 5.3.
	 *
	 * @since 0.7
	 * @var array[]
	 */
	public static $hook_source_stack = [];

	/**
	 * Whether validation error sources should be located.
	 *
	 * @var bool
	 */
	public static $should_locate_sources = false;

	/**
	 * Overrides for validation errors.
	 *
	 * @var array
	 */
	public static $validation_error_status_overrides = [];

	/**
	 * Whether the admin bar item was added for AMP.
	 *
	 * @var bool
	 */
	protected static $amp_admin_bar_item_added = false;

	/**
	 * Cached template directory to prevent infinite recursion.
	 *
	 * @see get_template_directory()
	 * @var string
	 */
	protected static $template_directory;

	/**
	 * Cached template slug to prevent infinite recursion.
	 *
	 * @see get_template()
	 * @var string
	 */
	protected static $template_slug;

	/**
	 * Cached stylesheet directory to prevent infinite recursion.
	 *
	 * @see get_stylesheet_directory()
	 * @var string
	 */
	protected static $stylesheet_directory;

	/**
	 * Cached stylesheet slug to prevent infinite recursion.
	 *
	 * @see get_stylesheet()
	 * @var string
	 */
	protected static $stylesheet_slug;

	/**
	 * Add the actions.
	 *
	 * @param array $args {
	 *     Args.
	 *
	 *     @type bool $should_locate_sources Whether to locate sources.
	 * }
	 * @return void
	 */
	public static function init( $args = [] ) {
		$args = array_merge(
			[
				'should_locate_sources' => self::should_validate_response(),
			],
			$args
		);

		self::$should_locate_sources = $args['should_locate_sources'] && ! AMP_Debug::has_flag( AMP_Debug::PREVENT_REDIRECT_TO_NON_AMP_QUERY_VAR );

		AMP_Validated_URL_Post_Type::register();
		AMP_Validation_Error_Taxonomy::register();

		// Short-circuit if AMP is not supported as only the post types should be available.
		if ( ! current_theme_supports( AMP_Theme_Support::SLUG ) && ! AMP_Options_Manager::is_stories_experience_enabled() ) {
			return;
		}

		add_action( 'save_post', [ __CLASS__, 'handle_save_post_prompting_validation' ] );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_validation' ] );
		add_action( 'edit_form_top', [ __CLASS__, 'print_edit_form_validation_status' ], 10, 2 );
		add_action( 'rest_api_init', [ __CLASS__, 'add_rest_api_fields' ] );

		// Add actions for checking theme support is present to determine plugin compatibility and show validation links in the admin bar.
		if ( AMP_Options_Manager::is_website_experience_enabled() && current_theme_supports( AMP_Theme_Support::SLUG ) ) {
			// Actions and filters involved in validation.
			add_action(
				'activate_plugin',
				function() {
					if ( ! has_action( 'shutdown', [ __CLASS__, 'validate_after_plugin_activation' ] ) ) {
						add_action( 'shutdown', [ __CLASS__, 'validate_after_plugin_activation' ] ); // Shutdown so all plugins will have been activated.
					}
				}
			);

			// Prevent query vars from persisting after redirect.
			add_filter(
				'removable_query_args',
				function( $query_vars ) {
					$query_vars[] = AMP_Validation_Manager::VALIDATION_ERRORS_QUERY_VAR;
					return $query_vars;
				}
			);

			add_action( 'all_admin_notices', [ __CLASS__, 'print_plugin_notice' ] );

			add_action( 'admin_bar_menu', [ __CLASS__, 'add_admin_bar_menu_items' ], 101 );
		}

		if ( self::$should_locate_sources ) {
			self::add_validation_error_sourcing();
		}
	}

	/**
	 * Determine if a post supports AMP validation.
	 *
	 * @since 1.2
	 *
	 * @param WP_Post|int $post Post.
	 * @return bool Whether post supports AMP validation.
	 */
	public static function post_supports_validation( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}

		$supports_validation = (
			// Skip if the post type is not viewable on the frontend, since we need a permalink to validate.
			is_post_type_viewable( $post->post_type )
			&&
			! wp_is_post_autosave( $post )
			&&
			! wp_is_post_revision( $post )
			&&
			'auto-draft' !== $post->post_status
			&&
			'trash' !== $post->post_status
		);
		if ( ! $supports_validation ) {
			return false;
		}

		// Story post type always supports validation.
		if ( AMP_Story_Post_Type::POST_TYPE_SLUG === $post->post_type ) {
			return AMP_Options_Manager::is_stories_experience_enabled();
		}

		// Prevent doing post validation in Reader mode or if the Website experience is not enabled.
		if ( ! current_theme_supports( AMP_Theme_Support::SLUG ) || ! AMP_Options_Manager::is_website_experience_enabled() ) {
			return false;
		}

		return post_supports_amp( $post );
	}

	/**
	 * Determine whether AMP theme support is forced via the amp_validate query param.
	 *
	 * @since 1.0
	 *
	 * @return bool Whether theme support forced.
	 */
	public static function is_theme_support_forced() {
		return (
			isset( $_GET[ self::VALIDATE_QUERY_VAR ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&&
			( self::has_cap() || self::get_amp_validate_nonce() === $_GET[ self::VALIDATE_QUERY_VAR ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	/**
	 * Return whether sanitization is initially accepted (by default) for newly encountered validation errors.
	 *
	 * To reject all new validation errors by default, a filter can be used like so:
	 *
	 *     add_filter( 'amp_validation_error_default_sanitized', '__return_false' );
	 *
	 * Whether or not a validation error is then actually sanitized is the ultimately determined by the
	 * `amp_validation_error_sanitized` filter.
	 *
	 * @since 1.0
	 * @see AMP_Validation_Error_Taxonomy::is_validation_error_sanitized()
	 * @see AMP_Validation_Error_Taxonomy::get_validation_error_sanitization()
	 *
	 * @param array $error Optional. Validation error. Will query the general status if no error provided.
	 * @return bool Whether sanitization is forcibly accepted.
	 */
	public static function is_sanitization_auto_accepted( $error = null ) {

		if ( $error && amp_is_canonical() ) {
			// Excessive CSS on AMP-first sites must not be removed by default since removing CSS can severely break a site.
			$accepted = AMP_Style_Sanitizer::STYLESHEET_TOO_LONG !== $error['code'];
		} else {
			$accepted = true;
		}

		/**
		 * Filters whether sanitization is accepted for a newly-encountered validation error .
		 *
		 * This only applies to validation errors that have not been encountered before. To override the sanitization
		 * status of existing validation errors, use the `amp_validation_error_sanitized` filter.
		 *
		 * @since 1.4
		 * @see AMP_Validation_Error_Taxonomy::get_validation_error_sanitization()
		 *
		 * @param bool       $accepted Default accepted.
		 * @param array|null $error    Validation error. May be null when asking if accepting sanitization is enabled by default.
		 */
		return apply_filters( 'amp_validation_error_default_sanitized', $accepted, $error );
	}

	/**
	 * Add menu items to admin bar for AMP.
	 *
	 * When on a non-AMP response (transitional mode), then the admin bar item should include:
	 * - Icon: LINK SYMBOL when AMP not known to be invalid and sanitization is not forced, or CROSS MARK when AMP is known to be valid.
	 * - Parent admin item and first submenu item: link to AMP version.
	 * - Second submenu item: link to validate the URL.
	 *
	 * When on transitional AMP response:
	 * - Icon: CHECK MARK if no unaccepted validation errors on page, or WARNING SIGN if there are unaccepted validation errors which are being forcibly sanitized.
	 *         Otherwise, if there are unsanitized validation errors then a redirect to the non-AMP version will be done.
	 * - Parent admin item and first submenu item: link to non-AMP version.
	 * - Second submenu item: link to validate the URL.
	 *
	 * When on AMP-first response:
	 * - Icon: CHECK MARK if no unaccepted validation errors on page, or WARNING SIGN if there are unaccepted validation errors.
	 * - Parent admin and first submenu item: link to validate the URL.
	 *
	 * @see AMP_Validation_Manager::finalize_validation() Where the emoji is updated.
	 * @see amp_add_admin_bar_view_link() Where an admin bar item may have been added already for Reader/Transitional modes.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar.
	 */
	public static function add_admin_bar_menu_items( $wp_admin_bar ) {
		if ( is_admin() || ! self::has_cap() ) {
			self::$amp_admin_bar_item_added = false;
			return;
		}

		$availability = AMP_Theme_Support::get_template_availability();
		if ( ! $availability['supported'] ) {
			self::$amp_admin_bar_item_added = false;
			return;
		}

		$amp_validated_url_post = null;

		$current_url = amp_get_current_url();
		$non_amp_url = amp_remove_endpoint( $current_url );

		$amp_url = remove_query_arg(
			array_merge(
				wp_removable_query_args(),
				[
					self::VALIDATE_QUERY_VAR,
					'amp_preserve_source_comments',
				]
			),
			$current_url
		);
		if ( ! amp_is_canonical() ) {
			$amp_url = add_query_arg( amp_get_slug(), '', $amp_url );
		}

		$error_count = -1;

		/*
		 * If not an AMP response, then obtain the count of validation errors from either the query param supplied after redirecting from AMP
		 * to non-AMP due to validation errors (see AMP_Theme_Support::prepare_response()), or if there is an amp_validated_url post that already
		 * is populated with the last-known validation errors. Otherwise, if it *is* an AMP response then the error count is obtained after
		 * when the response is being prepared by AMP_Validation_Manager::finalize_validation().
		 */
		if ( ! is_amp_endpoint() ) {
			if ( isset( $_GET[ self::VALIDATION_ERRORS_QUERY_VAR ] ) && is_numeric( $_GET[ self::VALIDATION_ERRORS_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$error_count = (int) $_GET[ self::VALIDATION_ERRORS_QUERY_VAR ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			if ( $error_count < 0 ) {
				$amp_validated_url_post = AMP_Validated_URL_Post_Type::get_invalid_url_post( $amp_url );
				if ( $amp_validated_url_post ) {
					$error_count = 0;
					foreach ( AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( $amp_validated_url_post ) as $error ) {
						if ( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS === $error['term_status'] || AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS === $error['term_status'] ) {
							$error_count++;
						}
					}
				}
			}
		}

		$user_can_revalidate = $amp_validated_url_post ? current_user_can( 'edit_post', $amp_validated_url_post->ID ) : current_user_can( 'manage_options' );
		if ( ! $user_can_revalidate ) {
			return;
		}

		// @todo The amp_validated_url post should probably only be accessible to users who can manage_options, or limit access to a post if the user has the cap to edit the queried object?
		$validate_url = AMP_Validated_URL_Post_Type::get_recheck_url( $amp_validated_url_post ?: $amp_url );

		// Construct the parent admin bar item.
		if ( is_amp_endpoint() ) {
			$icon = '&#x2705;'; // WHITE HEAVY CHECK MARK. This will get overridden in AMP_Validation_Manager::finalize_validation() if there are unaccepted errors.
			$href = $validate_url;
		} elseif ( $error_count > 0 ) {
			$icon = '&#x274C;'; // CROSS MARK.
			$href = $validate_url;
		} else {
			$icon = '&#x1F517;'; // LINK SYMBOL.
			$href = $amp_url;
		}
		$parent = [
			'id'    => 'amp',
			'title' => sprintf(
				'<span id="amp-admin-bar-item-status-icon">%s</span> %s',
				$icon,
				esc_html__( 'AMP', 'amp' )
			),
			'href'  => esc_url( $href ),
		];

		// Construct admin bar item for validation.
		$validate_item = [
			'parent' => 'amp',
			'id'     => 'amp-validity',
			'href'   => esc_url( $validate_url ),
		];
		if ( $error_count <= 0 ) {
			$validate_item['title'] = esc_html__( 'Validate', 'amp' );
		} else {
			$validate_item['title'] = esc_html(
				sprintf(
					/* translators: %s is count of validation errors */
					_n(
						'Review %s validation issue',
						'Review %s validation issues',
						$error_count,
						'amp'
					),
					number_format_i18n( $error_count )
				)
			);
		}

		// Construct admin bar item to link to AMP version or non-AMP version.
		$link_item = [
			'parent' => 'amp',
			'id'     => 'amp-view',
			'title'  => esc_html( is_amp_endpoint() ? __( 'View non-AMP version', 'amp' ) : __( 'View AMP version', 'amp' ) ),
			'href'   => esc_url( is_amp_endpoint() ? $non_amp_url : $amp_url ),
		];

		// Add admin bar item to switch between AMP and non-AMP if parent node is also an AMP link.
		$is_single_version_available = (
			amp_is_canonical()
			||
			( ! is_amp_endpoint() && $error_count > 0 )
		);

		// Add top-level menu item. Note that this will correctly merge/amend any existing AMP nav menu item added in amp_add_admin_bar_view_link().
		$wp_admin_bar->add_node( $parent );

		/*
		 * Add submenu items based on whether viewing AMP or not, and whether or not AMP is available.
		 * When
		 */
		if ( $is_single_version_available ) {
			$wp_admin_bar->add_node( $validate_item );
			self::add_debugging_option_nodes( $wp_admin_bar, $amp_url );
		} elseif ( ! is_amp_endpoint() ) {
			$wp_admin_bar->add_node( $link_item );
			$wp_admin_bar->add_node( $validate_item );
			self::add_debugging_option_nodes( $wp_admin_bar, $amp_url );
		} else {
			$wp_admin_bar->add_node( $validate_item );
			$wp_admin_bar->add_node( $link_item );
			self::add_debugging_option_nodes( $wp_admin_bar, $amp_url );
		}

		if ( AMP_Theme_Support::is_paired_available() && $error_count <= 0 && amp_is_dev_mode() ) {
			// Construct admin bar item to link to paired browsing experience.
			$paired_browsing_item = [
				'parent' => 'amp',
				'id'     => 'amp-paired-browsing',
				'title'  => esc_html__( 'Paired browsing', 'amp' ),
				'href'   => AMP_Theme_Support::get_paired_browsing_url(),
			];

			$wp_admin_bar->add_node( $paired_browsing_item );
		}

		// Scrub the query var from the URL.
		if ( ! is_amp_endpoint() && isset( $_GET[ self::VALIDATION_ERRORS_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_action(
				'wp_before_admin_bar_render',
				function() {
					?>
				<script>
				(function( queryVar ) {
					var urlParser = document.createElement( 'a' );
					urlParser.href = location.href;
					urlParser.search = urlParser.search.substr( 1 ).split( /&/ ).filter( function( part ) {
						return 0 !== part.indexOf( queryVar + '=' );
					} );
					if ( urlParser.href !== location.href ) {
						history.replaceState( {}, '', urlParser.href );
					}
				})( <?php echo wp_json_encode( AMP_Validation_Manager::VALIDATION_ERRORS_QUERY_VAR ); ?> );
				</script>
					<?php
				}
			);
		}

		self::$amp_admin_bar_item_added = true;
	}

	/**
	 * Adds the debugging option links to the admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar to add the nodes to.
	 * @param string       $amp_url The URL of the AMP endpoint.
	 */
	public static function add_debugging_option_nodes( &$wp_admin_bar, $amp_url ) {
		$parent_node_id = 'amp-debug';
		$wp_admin_bar->add_node(
			[
				'parent' => 'amp',
				'id'     => $parent_node_id,
				'title'  => esc_html__( 'Debugging options', 'amp' ),
			]
		);

		foreach ( AMP_Debug::get_all_query_vars() as $query_var => $title ) {
			$wp_admin_bar->add_node(
				[
					'parent' => $parent_node_id,
					'id'     => $query_var,
					'title'  => self::get_debugging_option_title( $title, $query_var ),
					'href'   => add_query_arg( AMP_Debug::AMP_FLAGS_QUERY_VAR, [ $query_var => 'true' ], $amp_url ),
				]
			);
		}
	}

	/**
	 * Gets the debugging option title.
	 *
	 * If the current URL has the debugging option query var present, the title starts with a 'check mark' emoji.
	 * For example, if the URL has the 'Prevent redirect' query var present,
	 * the title in the Admin Bar will be '☑ Prevent redirect'.
	 *
	 * @param string $title_text The title of the option, like 'Prevent redirect'.
	 * @param string $query_var The query var of the option.
	 * @return string The debugging option title, possible with a 'check mark' emoji at the beginning.
	 */
	public static function get_debugging_option_title( $title_text, $query_var ) {
		$checkbox = AMP_Debug::has_flag( $query_var ) ? '☑' : '☐';
		return sprintf( '<span class="amp-debug-option" style="font-family:Roboto,Oxygen-Sans,sans-serif;font-size:17px">%s</span> %s', $checkbox, esc_html( $title_text ) );
	}

	/**
	 * Add hooks for doing determining sources for validation errors during preprocessing/sanitizing.
	 */
	public static function add_validation_error_sourcing() {

		// Capture overrides validation error status overrides from query var.
		$can_override_validation_error_statuses = (
			isset( $_REQUEST[ self::VALIDATE_QUERY_VAR ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&&
			self::get_amp_validate_nonce() === $_REQUEST[ self::VALIDATE_QUERY_VAR ] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&&
			isset( $_REQUEST[ self::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&&
			is_array( $_REQUEST[ self::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		if ( $can_override_validation_error_statuses ) {
			/*
			 * This can't just easily add an amp_validation_error_sanitized filter because the the filter_sanitizer_args() method
			 * currently needs to obtain the list of overrides to create a parsed_cache_variant.
			 */
			foreach ( $_REQUEST[ self::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR ] as $slug => $status ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$slug   = sanitize_key( $slug );
				$status = (int) $status;
				self::$validation_error_status_overrides[ $slug ] = $status;
				ksort( self::$validation_error_status_overrides );
			}
		}

		self::$template_directory   = wp_normalize_path( get_template_directory() );
		self::$template_slug        = get_template();
		self::$stylesheet_directory = wp_normalize_path( get_stylesheet_directory() );
		self::$stylesheet_slug      = get_stylesheet();

		add_action( 'wp', [ __CLASS__, 'wrap_widget_callbacks' ] );

		add_action( 'all', [ __CLASS__, 'wrap_hook_callbacks' ] );
		$wrapped_filters = [ 'the_content', 'the_excerpt' ];
		foreach ( $wrapped_filters as $wrapped_filter ) {
			add_filter( $wrapped_filter, [ __CLASS__, 'decorate_filter_source' ], PHP_INT_MAX );
		}

		add_filter( 'do_shortcode_tag', [ __CLASS__, 'decorate_shortcode_source' ], PHP_INT_MAX, 2 );
		add_filter( 'embed_oembed_html', [ __CLASS__, 'decorate_embed_source' ], PHP_INT_MAX, 3 );

		$do_blocks_priority  = has_filter( 'the_content', 'do_blocks' );
		$is_gutenberg_active = (
			false !== $do_blocks_priority
			&&
			class_exists( 'WP_Block_Type_Registry' )
		);
		if ( $is_gutenberg_active ) {
			add_filter( 'the_content', [ __CLASS__, 'add_block_source_comments' ], $do_blocks_priority - 1 );
		}
	}

	/**
	 * Handle save_post action to queue re-validation of the post on the frontend.
	 *
	 * This is intended to only apply to post edits made in the classic editor.
	 *
	 * @see AMP_Validation_Manager::get_amp_validity_rest_field() The method responsible for validation post changes via Gutenberg.
	 * @see AMP_Validation_Manager::validate_queued_posts_on_frontend()
	 *
	 * @param int $post_id Post ID.
	 */
	public static function handle_save_post_prompting_validation( $post_id ) {
		global $pagenow;
		$post = get_post( $post_id );

		$is_classic_editor_post_save = (
			isset( $_SERVER['REQUEST_METHOD'] )
			&&
			'POST' === $_SERVER['REQUEST_METHOD']
			&&
			'post.php' === $pagenow
			&&
			isset( $_POST['post_ID'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			&&
			(int) $_POST['post_ID'] === (int) $post_id // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		$should_validate_post = (
			$is_classic_editor_post_save
			&&
			self::post_supports_validation( $post )
			&&
			! isset( self::$posts_pending_frontend_validation[ $post_id ] )
		);
		if ( $should_validate_post ) {
			self::$posts_pending_frontend_validation[ $post_id ] = true;

			// The reason for shutdown is to ensure that all postmeta changes have been saved, including whether AMP is enabled.
			if ( ! has_action( 'shutdown', [ __CLASS__, 'validate_queued_posts_on_frontend' ] ) ) {
				add_action( 'shutdown', [ __CLASS__, 'validate_queued_posts_on_frontend' ] );
			}
		}
	}

	/**
	 * Validate the posts pending frontend validation.
	 *
	 * @see AMP_Validation_Manager::handle_save_post_prompting_validation()
	 *
	 * @return array Mapping of post ID to the result of validating or storing the validation result.
	 */
	public static function validate_queued_posts_on_frontend() {
		$posts = array_filter(
			array_map( 'get_post', array_keys( array_filter( self::$posts_pending_frontend_validation ) ) ),
			function( $post ) {
				return self::post_supports_validation( $post );
			}
		);

		$validation_posts = [];

		/*
		 * It is unlikely that there will be more than one post in the array.
		 * For the bulk recheck action, see AMP_Validated_URL_Post_Type::handle_bulk_action().
		 */
		foreach ( $posts as $post ) {
			$url = amp_get_permalink( $post->ID );
			if ( ! $url ) {
				$validation_posts[ $post->ID ] = new WP_Error( 'no_amp_permalink' );
				continue;
			}

			// Prevent re-validating.
			self::$posts_pending_frontend_validation[ $post->ID ] = false;

			$validity = self::validate_url( $url );
			if ( is_wp_error( $validity ) ) {
				$validation_posts[ $post->ID ] = $validity;
			} else {
				$invalid_url_post_id = (int) get_post_meta( $post->ID, '_amp_validated_url_post_id', true );

				$validation_posts[ $post->ID ] = AMP_Validated_URL_Post_Type::store_validation_errors(
					wp_list_pluck( $validity['results'], 'error' ),
					$validity['url'],
					array_merge(
						[
							'invalid_url_post' => $invalid_url_post_id,
						],
						wp_array_slice_assoc( $validity, [ 'queried_object' ] )
					)
				);

				// Remember the amp_validated_url post so that when the slug changes the old amp_validated_url post can be updated.
				if ( ! is_wp_error( $validation_posts[ $post->ID ] ) && $invalid_url_post_id !== $validation_posts[ $post->ID ] ) {
					update_post_meta( $post->ID, '_amp_validated_url_post_id', $validation_posts[ $post->ID ] );
				}
			}
		}

		return $validation_posts;
	}

	/**
	 * Adds fields to the REST API responses, in order to display validation errors.
	 *
	 * @return void
	 */
	public static function add_rest_api_fields() {
		if ( ! current_theme_supports( AMP_Theme_Support::SLUG ) ) {
			$object_types = [ AMP_Story_Post_Type::POST_TYPE_SLUG ]; // Eventually validation should be done in Reader mode as well, but for now, limit to stories.
		} elseif ( amp_is_canonical() ) {
			$object_types = get_post_types_by_support( 'editor' ); // @todo Shouldn't this actually only be those with 'amp' support, or if if all_templates_supported?
		} else {
			$object_types = array_intersect(
				get_post_types_by_support( 'amp' ),
				get_post_types(
					[
						'show_in_rest' => true,
					]
				)
			);
		}

		register_rest_field(
			$object_types,
			self::VALIDITY_REST_FIELD_NAME,
			[
				'get_callback' => [ __CLASS__, 'get_amp_validity_rest_field' ],
				'schema'       => [
					'description' => __( 'AMP validity status', 'amp' ),
					'type'        => 'object',
				],
			]
		);
	}

	/**
	 * Adds a field to the REST API responses to display the validation status.
	 *
	 * First, get existing errors for the post.
	 * If there are none, validate the post and return any errors.
	 *
	 * @param array           $post_data  Data for the post.
	 * @param string          $field_name The name of the field to add.
	 * @param WP_REST_Request $request    The name of the field to add.
	 * @return array|null $validation_data Validation data if it's available, or null.
	 */
	public static function get_amp_validity_rest_field( $post_data, $field_name, $request ) {
		if ( ! current_user_can( 'edit_post', $post_data['id'] ) || ! self::has_cap() || ! self::post_supports_validation( $post_data['id'] ) ) {
			return null;
		}
		$post = get_post( $post_data['id'] );

		$validation_status_post = null;
		if ( in_array( $request->get_method(), [ 'PUT', 'POST' ], true ) ) {
			if ( ! isset( self::$posts_pending_frontend_validation[ $post->ID ] ) ) {
				self::$posts_pending_frontend_validation[ $post->ID ] = true;
			}
			$results = self::validate_queued_posts_on_frontend();
			if ( isset( $results[ $post->ID ] ) && is_int( $results[ $post->ID ] ) ) {
				$validation_status_post = get_post( $results[ $post->ID ] );
			}
		}

		if ( empty( $validation_status_post ) ) {
			$validation_status_post = AMP_Validated_URL_Post_Type::get_invalid_url_post( amp_get_permalink( $post->ID ) );
		}

		$field = [
			'results'     => [],
			'review_link' => null,
		];

		if ( $validation_status_post ) {
			$field['review_link'] = get_edit_post_link( $validation_status_post->ID, 'raw' );
			foreach ( AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( $validation_status_post ) as $result ) {
				$field['results'][] = [
					'sanitized'   => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS === $result['status'],
					'error'       => $result['data'],
					'status'      => $result['status'],
					'term_status' => $result['term_status'],
					'forced'      => $result['forced'],
				];
			}
		}

		return $field;
	}

	/**
	 * Whether the user has the required capability.
	 *
	 * Checks for permissions before validating.
	 *
	 * @return boolean $has_cap Whether the current user has the capability.
	 */
	public static function has_cap() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Add validation error.
	 *
	 * @param array $error Error info, especially code.
	 * @param array $data Additional data, including the node.
	 *
	 * @return bool Whether the validation error should result in sanitization.
	 */
	public static function add_validation_error( array $error, array $data = [] ) {
		$node    = null;
		$sources = null;

		if ( isset( $data['node'] ) && $data['node'] instanceof DOMNode ) {
			$node = $data['node'];
		}

		if ( self::$should_locate_sources ) {
			if ( ! empty( $error['sources'] ) ) {
				$sources = $error['sources'];
			} elseif ( $node ) {
				$sources = self::locate_sources( $node );
			}
		}
		unset( $error['sources'] );

		if ( ! isset( $error['code'] ) ) {
			$error['code'] = 'unknown';
		}

		/**
		 * Filters the validation error array.
		 *
		 * This allows plugins to add amend additional properties which can help with
		 * more accurately identifying a validation error beyond the name of the parent
		 * node and the element's attributes. The $sources are also omitted because
		 * these are only available during an explicit validation request and so they
		 * are not suitable for plugins to vary sanitization by. If looking to force a
		 * validation error to be ignored, use the 'amp_validation_error_sanitized'
		 * filter instead of attempting to return an empty value with this filter (as
		 * that is not supported).
		 *
		 * @since 1.0
		 *
		 * @param array $error Validation error to be printed.
		 * @param array $context   {
		 *     Context data for validation error sanitization.
		 *
		 *     @type DOMNode $node Node for which the validation error is being reported. May be null.
		 * }
		 */
		$error = apply_filters( 'amp_validation_error', $error, compact( 'node' ) );

		$sanitization = AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $error );
		$sanitized    = (
			AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS === $sanitization['status']
			||
			AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS === $sanitization['status']
		);

		/*
		 * Ignore validation errors which are forcibly sanitized by filter. This includes errors accepted via
		 * AMP_Validation_Error_Taxonomy::accept_validation_errors(), such as the acceptable_errors in core themes.
		 * This was introduced in <https://github.com/ampproject/amp-wp/pull/1413> to prevent forcibly-sanitized
		 * validation errors from being reported, to avoid noise and wasted storage. It was inadvertently
		 * reverted in de7b04b but then restored as part of <https://github.com/ampproject/amp-wp/pull/1413>.
		 */
		if ( $sanitized && 'with_filter' === $sanitization['forced'] ) {
			return true;
		}

		// Add sources back into the $error for referencing later. @todo It may be cleaner to store sources separately to avoid having to re-remove later during storage.
		$error = array_merge( $error, compact( 'sources' ) );

		self::$validation_results[] = compact( 'error', 'sanitized' );
		return $sanitized;
	}

	/**
	 * Reset the stored removed nodes and attributes.
	 *
	 * After testing if the markup is valid,
	 * these static values will remain.
	 * So reset them in case another test is needed.
	 *
	 * @return void
	 */
	public static function reset_validation_results() {
		self::$validation_results      = [];
		self::$enqueued_style_sources  = [];
		self::$enqueued_script_sources = [];
	}

	/**
	 * Checks the AMP validity of the post content.
	 *
	 * If it's not valid AMP, it displays an error message above the 'Classic' editor.
	 *
	 * This is essentially a PHP implementation of ampBlockValidation.handleValidationErrorsStateChange() in JS.
	 *
	 * @param WP_Post $post The updated post.
	 * @return void
	 */
	public static function print_edit_form_validation_status( $post ) {
		if ( ! self::post_supports_validation( $post ) || ! self::has_cap() ) {
			return;
		}

		$invalid_url_post = AMP_Validated_URL_Post_Type::get_invalid_url_post( get_permalink( $post->ID ) );
		if ( ! $invalid_url_post ) {
			return;
		}

		// Show all validation errors which have not been explicitly acknowledged as accepted.
		$validation_errors  = [];
		$has_rejected_error = false;
		foreach ( AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( $invalid_url_post ) as $error ) {
			$needs_moderation = (
				AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS === $error['status'] || // @todo Show differently since moderated?
				AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS === $error['status'] ||
				AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS === $error['status']
			);
			if ( $needs_moderation ) {
				$validation_errors[] = $error['data'];
			}

			if (
				AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS === $error['status']
				||
				AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS === $error['status']
			) {
				$has_rejected_error = true;
			}
		}

		// No validation errors so abort.
		if ( empty( $validation_errors ) ) {
			return;
		}

		echo '<div class="notice notice-warning">';
		echo '<p>';
		// @todo Check if the error actually occurs in the_content, and if not, consider omitting the warning if the user does not have privileges to manage_options.
		esc_html_e( 'There is content which fails AMP validation.', 'amp' );
		echo ' ';

		// Auto-acceptance is enabled by default but can be overridden by the the `amp_validation_error_default_sanitized` filter.
		if ( ! $has_rejected_error ) {
			esc_html_e( 'Nevertheless, the invalid markup has been automatically removed.', 'amp' );
		} else {
			/*
			 * Even if invalid markup is removed by default, if there are non-accepted errors in non-Standard mode, it will redirect to a non-AMP page.
			 * For example, the errors could have been stored as 'New Kept' when auto-accept was false, and now auto-accept is true.
			 * In that case, this will block serving AMP.
			 * This could also apply if this is in 'Standard' mode and the user has rejected a validation error.
			 */
			esc_html_e( 'You will have to remove the invalid markup (or allow the plugin to remove it) to serve AMP.', 'amp' );
		}

		echo sprintf(
			' <a href="%s" target="_blank">%s</a>',
			esc_url( get_edit_post_link( $invalid_url_post ) ),
			esc_html__( 'Review issues', 'amp' )
		);
		echo '</p>';

		$results      = AMP_Validation_Error_Taxonomy::summarize_validation_errors( array_unique( $validation_errors, SORT_REGULAR ) );
		$removed_sets = [];
		if ( ! empty( $results[ AMP_Validation_Error_Taxonomy::REMOVED_ELEMENTS ] ) && is_array( $results[ AMP_Validation_Error_Taxonomy::REMOVED_ELEMENTS ] ) ) {
			$removed_sets[] = [
				'label' => __( 'Invalid elements:', 'amp' ),
				'names' => array_map( 'sanitize_key', $results[ AMP_Validation_Error_Taxonomy::REMOVED_ELEMENTS ] ),
			];
		}
		if ( ! empty( $results[ AMP_Validation_Error_Taxonomy::REMOVED_ATTRIBUTES ] ) && is_array( $results[ AMP_Validation_Error_Taxonomy::REMOVED_ATTRIBUTES ] ) ) {
			$removed_sets[] = [
				'label' => __( 'Invalid attributes:', 'amp' ),
				'names' => array_map( 'sanitize_key', $results[ AMP_Validation_Error_Taxonomy::REMOVED_ATTRIBUTES ] ),
			];
		}
		// @todo There are other kinds of errors other than REMOVED_ELEMENTS and REMOVED_ATTRIBUTES.
		foreach ( $removed_sets as $removed_set ) {
			printf( '<p>%s ', esc_html( $removed_set['label'] ) );
			$items = [];
			foreach ( $removed_set['names'] as $name => $count ) {
				if ( 1 === (int) $count ) {
					$items[] = sprintf( '<code>%s</code>', esc_html( $name ) );
				} else {
					$items[] = sprintf( '<code>%s</code> (%d)', esc_html( $name ), $count );
				}
			}
			echo implode( ', ', $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Get source start comment.
	 *
	 * @param array $source   Source data.
	 * @param bool  $is_start Whether the comment is the start or end.
	 * @return string HTML Comment.
	 */
	public static function get_source_comment( array $source, $is_start = true ) {
		unset( $source['reflection'] );
		return sprintf(
			'<!--%samp-source-stack %s-->',
			$is_start ? '' : '/',
			str_replace( '--', '', wp_json_encode( $source ) )
		);
	}

	/**
	 * Parse source comment.
	 *
	 * @param DOMComment $comment Comment.
	 * @return array|null Parsed source or null if not a source comment.
	 */
	public static function parse_source_comment( DOMComment $comment ) {
		if ( ! preg_match( '#^\s*(?P<closing>/)?amp-source-stack\s+(?P<args>{.+})\s*$#s', $comment->nodeValue, $matches ) ) {
			return null;
		}

		$source  = json_decode( $matches['args'], true );
		$closing = ! empty( $matches['closing'] );

		return compact( 'source', 'closing' );
	}

	/**
	 * Walk back tree to find the open sources.
	 *
	 * @todo This method and others for sourcing could be moved to a separate class.
	 *
	 * @param DOMNode $node Node to look for.
	 * @return array[][] {
	 *       The data of the removed sources (theme, plugin, or mu-plugin).
	 *
	 *       @type string $name The name of the source.
	 *       @type string $type The type of the source.
	 * }
	 */
	public static function locate_sources( DOMNode $node ) {
		$xpath    = new DOMXPath( $node->ownerDocument );
		$comments = $xpath->query( 'preceding::comment()[ starts-with( ., "amp-source-stack" ) or starts-with( ., "/amp-source-stack" ) ]', $node );
		$sources  = [];
		$matches  = [];

		foreach ( $comments as $comment ) {
			$parsed_comment = self::parse_source_comment( $comment );
			if ( ! $parsed_comment ) {
				continue;
			}
			if ( $parsed_comment['closing'] ) {
				array_pop( $sources );
			} else {
				$sources[] = $parsed_comment['source'];
			}
		}

		$is_enqueued_link = (
			$node instanceof DOMElement
			&&
			'link' === $node->nodeName
			&&
			preg_match( '/(?P<handle>.+)-css$/', (string) $node->getAttribute( 'id' ), $matches )
			&&
			isset( self::$enqueued_style_sources[ $matches['handle'] ] )
		);
		if ( $is_enqueued_link ) {
			$sources = array_merge(
				self::$enqueued_style_sources[ $matches['handle'] ],
				$sources
			);
		}

		/**
		 * Script dependency.
		 *
		 * @var _WP_Dependency $script_dependency
		 */
		if ( $node instanceof DOMElement && 'script' === $node->nodeName ) {
			$enqueued_script_handles = array_intersect( wp_scripts()->done, array_keys( self::$enqueued_script_sources ) );

			if ( $node->hasAttribute( 'src' ) ) {

				// External script.
				$src = $node->getAttribute( 'src' );
				foreach ( $enqueued_script_handles as $enqueued_script_handle ) {
					$script_dependency  = wp_scripts()->registered[ $enqueued_script_handle ];
					$is_matching_script = (
						$script_dependency
						&&
						$script_dependency->src
						&&
						// Script attribute is haystack because includes protocol and may include query args (like ver).
						false !== strpos( $src, preg_replace( '#^https?:(?=//)#', '', $script_dependency->src ) )
					);
					if ( $is_matching_script ) {
						$sources = array_merge(
							self::$enqueued_script_sources[ $enqueued_script_handle ],
							$sources
						);
						break;
					}
				}
			} elseif ( $node->firstChild ) {

				// Inline script.
				$text = $node->textContent;
				foreach ( $enqueued_script_handles as $enqueued_script_handle ) {
					$inline_scripts = array_filter(
						array_merge(
							(array) wp_scripts()->get_data( $enqueued_script_handle, 'data' ),
							(array) wp_scripts()->get_data( $enqueued_script_handle, 'before' ),
							(array) wp_scripts()->get_data( $enqueued_script_handle, 'after' )
						)
					);
					foreach ( $inline_scripts as $inline_script ) {
						/*
						 * Check to see if the inline script is inside (or the same) as the script in the document.
						 * Note that WordPress takes the registered inline script and will output it with newlines
						 * padding it, and sometimes with the script wrapped by CDATA blocks.
						 */
						if ( false !== strpos( $text, trim( $inline_script ) ) ) {
							$sources = array_merge(
								self::$enqueued_script_sources[ $enqueued_script_handle ],
								$sources
							);
							break;
						}
					}
				}
			}
		}

		return $sources;
	}

	/**
	 * Remove source comments.
	 *
	 * @param DOMDocument $dom Document.
	 */
	public static function remove_source_comments( $dom ) {
		$xpath    = new DOMXPath( $dom );
		$comments = [];
		foreach ( $xpath->query( '//comment()[ starts-with( ., "amp-source-stack" ) or starts-with( ., "/amp-source-stack" ) ]' ) as $comment ) {
			if ( self::parse_source_comment( $comment ) ) {
				$comments[] = $comment;
			}
		}
		foreach ( $comments as $comment ) {
			$comment->parentNode->removeChild( $comment );
		}
	}

	/**
	 * Add block source comments.
	 *
	 * @param string $content Content prior to blocks being processed.
	 * @return string Content with source comments added.
	 */
	public static function add_block_source_comments( $content ) {
		self::$block_content_index = 0;

		$start_block_pattern = implode(
			'',
			[
				'#<!--\s+',
				'(?P<closing>/)?',
				'wp:(?P<name>\S+)',
				'(?:\s+(?P<attributes>\{.*?\}))?',
				'\s+(?P<self_closing>\/)?',
				'-->#s',
			]
		);

		return preg_replace_callback(
			$start_block_pattern,
			[ __CLASS__, 'handle_block_source_comment_replacement' ],
			$content
		);
	}

	/**
	 * Handle block source comment replacement.
	 *
	 * @see \AMP_Validation_Manager::add_block_source_comments()
	 *
	 * @param array $matches Matches.
	 *
	 * @return string Replaced.
	 */
	protected static function handle_block_source_comment_replacement( $matches ) {
		$replaced = $matches[0];

		// Obtain source information for block.
		$source = [
			'block_name' => $matches['name'],
			'post_id'    => get_the_ID(),
		];

		if ( empty( $matches['closing'] ) ) {
			$source['block_content_index'] = self::$block_content_index;
			self::$block_content_index++;
		}

		// Make implicit core namespace explicit.
		$is_implicit_core_namespace = ( false === strpos( $source['block_name'], '/' ) );
		$source['block_name']       = $is_implicit_core_namespace ? 'core/' . $source['block_name'] : $source['block_name'];

		if ( ! empty( $matches['attributes'] ) ) {
			$source['block_attrs'] = json_decode( $matches['attributes'] );
		}
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $source['block_name'] );
		if ( $block_type && $block_type->is_dynamic() ) {
			$callback_source = self::get_source( $block_type->render_callback );
			if ( $callback_source ) {
				$source = array_merge(
					$source,
					$callback_source
				);
			}
		}

		if ( ! empty( $matches['closing'] ) ) {
			$replaced .= self::get_source_comment( $source, false );
		} else {
			$replaced = self::get_source_comment( $source, true ) . $replaced;
			if ( ! empty( $matches['self_closing'] ) ) {
				unset( $source['block_content_index'] );
				$replaced .= self::get_source_comment( $source, false );
			}
		}
		return $replaced;
	}

	/**
	 * Wrap callbacks for registered widgets to keep track of queued assets and the source for anything printed for validation.
	 *
	 * @global array $wp_filter
	 * @return void
	 */
	public static function wrap_widget_callbacks() {
		global $wp_registered_widgets;
		foreach ( $wp_registered_widgets as $widget_id => &$registered_widget ) {
			$source = self::get_source( $registered_widget['callback'] );
			if ( ! $source ) {
				continue;
			}
			$source['widget_id'] = $widget_id;
			unset( $source['reflection'] ); // Omit from stored source.

			$function      = $registered_widget['callback'];
			$accepted_args = 2; // For the $instance and $args arguments.
			$callback      = compact( 'function', 'accepted_args', 'source' );

			$registered_widget['callback'] = self::wrapped_callback( $callback );
		}
	}

	/**
	 * Wrap filter/action callback functions for a given hook.
	 *
	 * Wrapped callback functions are reset to their original functions after invocation.
	 * This runs at the 'all' action. The shutdown hook is excluded.
	 *
	 * @global WP_Hook[] $wp_filter
	 * @param string $hook Hook name for action or filter.
	 * @return void
	 */
	public static function wrap_hook_callbacks( $hook ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) || 'shutdown' === $hook ) {
			return;
		}

		self::$current_hook_source_stack[ $hook ] = [];
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => &$callbacks ) {
			foreach ( $callbacks as &$callback ) {
				$source = self::get_source( $callback['function'] );
				if ( ! $source ) {
					continue;
				}

				// Skip considering ourselves.
				if ( 'AMP_Validation_Manager::add_block_source_comments' === $source['function'] ) {
					continue;
				}

				/**
				 * Reflection.
				 *
				 * @var ReflectionFunctionAbstract $reflection
				 */
				$reflection = $source['reflection'];
				unset( $source['reflection'] ); // Omit from stored source.

				// Add hook to stack for decorate_filter_source to read from.
				self::$current_hook_source_stack[ $hook ][] = $source;

				/*
				 * A current limitation with wrapping callbacks is that the wrapped function cannot have
				 * any parameters passed by reference. Without this the result is:
				 *
				 * > PHP Warning:  Parameter 1 to wp_default_styles() expected to be a reference, value given.
				 */
				if ( self::has_parameters_passed_by_reference( $reflection ) ) {
					continue;
				}

				$source['hook']     = $hook;
				$source['priority'] = $priority;
				$original_function  = $callback['function'];
				$wrapped_callback   = self::wrapped_callback(
					array_merge(
						$callback,
						compact( 'priority', 'source' )
					)
				);

				$callback['function'] = function() use ( &$callback, $wrapped_callback, $original_function ) {
					$callback['function'] = $original_function; // Restore original.
					return call_user_func_array( $wrapped_callback, func_get_args() );
				};
			}
		}
	}

	/**
	 * Determine whether the given reflection method/function has params passed by reference.
	 *
	 * @since 0.7
	 * @param ReflectionFunction|ReflectionMethod $reflection Reflection.
	 * @return bool Whether there are parameters passed by reference.
	 */
	protected static function has_parameters_passed_by_reference( $reflection ) {
		foreach ( $reflection->getParameters() as $parameter ) {
			if ( $parameter->isPassedByReference() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Filters the output created by a shortcode callback.
	 *
	 * @since 0.7
	 *
	 * @param string $output Shortcode output.
	 * @param string $tag    Shortcode name.
	 * @return string Output.
	 * @global array $shortcode_tags
	 */
	public static function decorate_shortcode_source( $output, $tag ) {
		global $shortcode_tags;
		if ( ! isset( $shortcode_tags[ $tag ] ) ) {
			return $output;
		}
		$source = self::get_source( $shortcode_tags[ $tag ] );
		if ( empty( $source ) ) {
			return $output;
		}
		$source['shortcode'] = $tag;

		$output = implode(
			'',
			[
				self::get_source_comment( $source, true ),
				$output,
				self::get_source_comment( $source, false ),
			]
		);
		return $output;
	}

	/**
	 * Filters the output created by embeds.
	 *
	 * @since 1.0
	 *
	 * @param string $output Embed output.
	 * @param string $url    URL.
	 * @param array  $attr   Attributes.
	 * @return string Output.
	 */
	public static function decorate_embed_source( $output, $url, $attr ) {
		$source = [
			'embed' => $url,
			'attr'  => $attr,
		];
		return implode(
			'',
			[
				self::get_source_comment( $source, true ),
				trim( $output ),
				self::get_source_comment( $source, false ),
			]
		);
	}

	/**
	 * Wraps output of a filter to add source stack comments.
	 *
	 * @todo Duplicate with AMP_Validation_Manager::wrap_buffer_with_source_comments()?
	 * @param string $value Value.
	 * @return string Value wrapped in source comments.
	 */
	public static function decorate_filter_source( $value ) {

		// Abort if the output is not a string and it doesn't contain any HTML tags.
		if ( ! is_string( $value ) || ! preg_match( '/<.+?>/s', $value ) ) {
			return $value;
		}

		$post   = get_post();
		$source = [
			'hook'   => current_filter(),
			'filter' => true,
		];
		if ( $post ) {
			$source['post_id']   = $post->ID;
			$source['post_type'] = $post->post_type;
		}
		if ( isset( self::$current_hook_source_stack[ current_filter() ] ) ) {
			$sources = self::$current_hook_source_stack[ current_filter() ];
			array_pop( $sources ); // Remove self.
			$source['sources'] = $sources;
		}
		return implode(
			'',
			[
				self::get_source_comment( $source, true ),
				$value,
				self::get_source_comment( $source, false ),
			]
		);
	}

	/**
	 * Gets the plugin or theme of the callback, if one exists.
	 *
	 * @param string|array|callable $callback The callback for which to get the plugin.
	 * @return array|null {
	 *     The source data.
	 *
	 *     @type string $type Source type (core, plugin, mu-plugin, or theme).
	 *     @type string $name Source name.
	 *     @type string $function Normalized function name.
	 *     @type ReflectionMethod|ReflectionFunction $reflection Reflection.
	 * }
	 */
	public static function get_source( $callback ) {
		$reflection = null;
		try {
			if ( is_string( $callback ) && is_callable( $callback ) ) {
				// The $callback is a function or static method.
				$exploded_callback = explode( '::', $callback, 2 );
				if ( 2 === count( $exploded_callback ) ) {
					$reflection = new ReflectionMethod( $exploded_callback[0], $exploded_callback[1] );
				} else {
					$reflection = new ReflectionFunction( $callback );
				}
			} elseif ( is_array( $callback ) && isset( $callback[0], $callback[1] ) && method_exists( $callback[0], $callback[1] ) ) {
				$reflection = new ReflectionMethod( $callback[0], $callback[1] );

				// Handle the special case of the class being a widget, in which case the display_callback method should
				// actually map to the underling widget method. It is the display_callback in the end that is wrapped.
				if ( 'WP_Widget' === $reflection->getDeclaringClass()->getName() && 'display_callback' === $reflection->getName() ) {
					$reflection = new ReflectionMethod( $callback[0], 'widget' );
				}
			} elseif ( is_object( $callback ) && ( 'Closure' === get_class( $callback ) ) ) {
				$reflection = new ReflectionFunction( $callback );
			}
		} catch ( Exception $e ) {
			return null;
		}

		// The reflection is needed later for AMP_Validation_Manager::has_parameters_passed_by_reference().
		if ( ! $reflection ) {
			return null;
		}

		$source = compact( 'reflection' );

		// Identify the type, name, and relative file path.
		$file         = wp_normalize_path( $reflection->getFileName() );
		$slug_pattern = '(?<slug>[^/]+)';
		if ( preg_match( ':' . preg_quote( trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ), ':' ) . $slug_pattern . '(/(?P<file>.*$))?:s', $file, $matches ) ) {
			$source['type'] = 'plugin';
			$source['name'] = $matches['slug'];
			$source['file'] = isset( $matches['file'] ) ? $matches['file'] : $matches['slug'];
		} elseif ( ! empty( self::$template_directory ) && preg_match( ':' . preg_quote( trailingslashit( self::$template_directory ), ':' ) . '(?P<file>.*$):s', $file, $matches ) ) {
			$source['type'] = 'theme';
			$source['name'] = self::$template_slug;
			$source['file'] = $matches['file'];
		} elseif ( ! empty( self::$stylesheet_directory ) && preg_match( ':' . preg_quote( trailingslashit( self::$stylesheet_directory ), ':' ) . '(?P<file>.*$):s', $file, $matches ) ) {
			$source['type'] = 'theme';
			$source['name'] = self::$stylesheet_slug;
			$source['file'] = $matches['file'];
		} elseif ( preg_match( ':' . preg_quote( trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ), ':' ) . $slug_pattern . '(/(?P<file>.*$))?:s', $file, $matches ) ) {
			$source['type'] = 'mu-plugin';
			$source['name'] = $matches['slug'];
			$source['file'] = isset( $matches['file'] ) ? $matches['file'] : $matches['slug'];
		} elseif ( preg_match( ':' . preg_quote( trailingslashit( wp_normalize_path( ABSPATH ) ), ':' ) . '(?P<slug>wp-admin|wp-includes)/(?P<file>.*$):s', $file, $matches ) ) {
			$source['type'] = 'core';
			$source['name'] = $matches['slug'];
			$source['file'] = $matches['file'];
		}

		// If a file was identified, then also supply the line number.
		if ( isset( $source['file'] ) ) {
			$source['line'] = $reflection->getStartLine();
		}

		if ( $reflection instanceof ReflectionMethod ) {
			$source['function'] = $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName();
		} else {
			$source['function'] = $reflection->getName();
		}

		return $source;
	}

	/**
	 * Check whether or not output buffering is currently possible.
	 *
	 * This is to guard against a fatal error: "ob_start(): Cannot use output buffering in output buffering display handlers".
	 *
	 * @return bool Whether output buffering is allowed.
	 */
	public static function can_output_buffer() {

		// Output buffering for validation can only be done while overall output buffering is being done for the response.
		if ( ! AMP_Theme_Support::is_output_buffering() ) {
			return false;
		}

		// Abort when in shutdown since output has finished, when we're likely in the overall output buffering display handler.
		if ( did_action( 'shutdown' ) ) {
			return false;
		}

		// Check if any functions in call stack are output buffering display handlers.
		$called_functions = [];
		if ( defined( 'DEBUG_BACKTRACE_IGNORE_ARGS' ) ) {
			$arg = DEBUG_BACKTRACE_IGNORE_ARGS; // phpcs:ignore PHPCompatibility.Constants.NewConstants.debug_backtrace_ignore_argsFound
		} else {
			$arg = false;
		}
		$backtrace = debug_backtrace( $arg ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Only way to find out if we are in a buffering display handler.
		foreach ( $backtrace as $call_stack ) {
			if ( '{closure}' === $call_stack['function'] ) {
				$called_functions[] = 'Closure::__invoke';
			} elseif ( isset( $call_stack['class'] ) ) {
				$called_functions[] = sprintf( '%s::%s', $call_stack['class'], $call_stack['function'] );
			} else {
				$called_functions[] = $call_stack['function'];
			}
		}
		return 0 === count( array_intersect( ob_list_handlers(), $called_functions ) );
	}

	/**
	 * Wraps a callback in comments if it outputs markup.
	 *
	 * If the sanitizer removes markup,
	 * this indicates which plugin it was from.
	 * The call_user_func_array() logic is mainly copied from WP_Hook:apply_filters().
	 *
	 * @param array $callback {
	 *     The callback data.
	 *
	 *     @type callable $function
	 *     @type int      $accepted_args
	 *     @type array    $source
	 * }
	 * @return AMP_Validation_Callback_Wrapper $wrapped_callback The callback, wrapped in comments.
	 */
	public static function wrapped_callback( $callback ) {
		return new AMP_Validation_Callback_Wrapper( $callback );
	}

	/**
	 * Wrap output buffer with source comments.
	 *
	 * A key reason for why this is a method and not a closure is so that
	 * the can_output_buffer method will be able to identify it by name.
	 *
	 * @since 0.7
	 * @todo Is duplicate of \AMP_Validation_Manager::decorate_filter_source()?
	 *
	 * @param string $output Output buffer.
	 * @return string Output buffer conditionally wrapped with source comments.
	 */
	public static function wrap_buffer_with_source_comments( $output ) {
		if ( empty( self::$hook_source_stack ) ) {
			return $output;
		}

		$source = self::$hook_source_stack[ count( self::$hook_source_stack ) - 1 ];

		// Wrap output that contains HTML tags (as opposed to actions that trigger in HTML attributes).
		if ( ! empty( $output ) && preg_match( '/<.+?>/s', $output ) ) {
			$output = implode(
				'',
				[
					self::get_source_comment( $source, true ),
					$output,
					self::get_source_comment( $source, false ),
				]
			);
		}
		return $output;
	}

	/**
	 * Get nonce for performing amp_validate request.
	 *
	 * The returned nonce is irrespective of the authenticated user.
	 *
	 * @return string Nonce.
	 */
	public static function get_amp_validate_nonce() {
		return substr( wp_hash( self::VALIDATE_QUERY_VAR . wp_nonce_tick(), 'nonce' ), -12, 10 );
	}

	/**
	 * Whether to validate the front end response.
	 *
	 * @return boolean Whether to validate.
	 */
	public static function should_validate_response() {
		if ( ! isset( $_GET[ self::VALIDATE_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( self::has_cap() ) {
			return true;
		}
		$validate_key = wp_unslash( $_GET[ self::VALIDATE_QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::get_amp_validate_nonce() === $validate_key;
	}

	/**
	 * Finalize validation.
	 *
	 * @see AMP_Validation_Manager::add_admin_bar_menu_items()
	 *
	 * @param DOMDocument $dom Document.
	 * @param array       $args {
	 *     Args.
	 *
	 *     @type bool $remove_source_comments           Whether source comments should be removed. Defaults to true.
	 *     @type bool $append_validation_status_comment Whether the validation errors should be appended as an HTML comment. Defaults to true.
	 * }
	 */
	public static function finalize_validation( DOMDocument $dom, $args = [] ) {
		$args = array_merge(
			[
				'remove_source_comments'           => true,
				'append_validation_status_comment' => true,
			],
			$args
		);

		/*
		 * Override AMP status in admin bar set in \AMP_Validation_Manager::add_admin_bar_menu_items()
		 * when there are validation errors which have not been explicitly accepted.
		 */
		if ( is_admin_bar_showing() && self::$amp_admin_bar_item_added ) {
			$error_count = 0;
			foreach ( self::$validation_results as $validation_result ) {
				$validation_status = AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $validation_result['error'] );

				$is_unaccepted = 'with_preview' === $validation_status['forced'] ?
					AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS !== $validation_status['status']
					:
					AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS !== $validation_status['term_status'];
				if ( $is_unaccepted ) {
					$error_count++;
				}
			}

			if ( $error_count > 0 ) {
				$validate_item = $dom->getElementById( 'wp-admin-bar-amp-validity' );
				if ( $validate_item ) {
					$link = $validate_item->getElementsByTagName( 'a' )->item( 0 );
					if ( $link ) {
						$link->textContent = sprintf(
							/* translators: %s is count of validation errors */
							_n(
								'Review %s validation issue',
								'Review %s validation issues',
								$error_count,
								'amp'
							),
							number_format_i18n( $error_count )
						);
					}
				}

				$admin_bar_icon = $dom->getElementById( 'amp-admin-bar-item-status-icon' );
				if ( $admin_bar_icon ) {
					$admin_bar_icon->textContent = "\xE2\x9A\xA0\xEF\xB8\x8F"; // WARNING SIGN: U+26A0, U+FE0F.
				}
			}
		}

		if ( self::should_validate_response() ) {
			if ( $args['remove_source_comments'] ) {
				self::remove_source_comments( $dom );
			}

			if ( $args['append_validation_status_comment'] ) {
				$data = [
					'results' => self::$validation_results,
				];
				if ( get_queried_object() ) {
					$data['queried_object'] = [];
					if ( get_queried_object_id() ) {
						$data['queried_object']['id'] = get_queried_object_id();
					}
					if ( get_queried_object() instanceof WP_Post ) {
						$data['queried_object']['type'] = 'post';
					} elseif ( get_queried_object() instanceof WP_Term ) {
						$data['queried_object']['type'] = 'term';
					} elseif ( get_queried_object() instanceof WP_User ) {
						$data['queried_object']['type'] = 'user';
					} elseif ( get_queried_object() instanceof WP_Post_Type ) {
						$data['queried_object']['type'] = 'post_type';
					}
				}

				$encoded = wp_json_encode( $data, 128 /* JSON_PRETTY_PRINT */ );
				$encoded = str_replace( '--', '\u002d\u002d', $encoded ); // Prevent "--" in strings from breaking out of HTML comments.
				$comment = $dom->createComment( 'AMP_VALIDATION:' . $encoded . "\n" );
				$dom->documentElement->appendChild( $comment );
			}
		}
	}

	/**
	 * Adds the validation callback if front-end validation is needed.
	 *
	 * @param array $sanitizers The AMP sanitizers.
	 * @return array $sanitizers The filtered AMP sanitizers.
	 */
	public static function filter_sanitizer_args( $sanitizers ) {
		foreach ( $sanitizers as $sanitizer => &$args ) {
			$args['validation_error_callback'] = __CLASS__ . '::add_validation_error';
		}

		if ( isset( $sanitizers['AMP_Style_Sanitizer'] ) ) {
			$sanitizers['AMP_Style_Sanitizer']['should_locate_sources'] = self::$should_locate_sources;

			$css_validation_errors = [];
			foreach ( self::$validation_error_status_overrides as $slug => $status ) {
				$term = AMP_Validation_Error_Taxonomy::get_term( $slug );
				if ( ! $term ) {
					continue;
				}
				$validation_error = json_decode( $term->description, true );

				$is_css_validation_error = (
					is_array( $validation_error )
					&&
					isset( $validation_error['code'] )
					&&
					in_array( $validation_error['code'], AMP_Style_Sanitizer::get_css_parser_validation_error_codes(), true )
				);
				if ( $is_css_validation_error ) {
					$css_validation_errors[ $slug ] = $status;
				}
			}
			if ( ! empty( $css_validation_errors ) ) {
				$sanitizers['AMP_Style_Sanitizer']['parsed_cache_variant'] = md5( wp_json_encode( $css_validation_errors ) );
			}
		}

		return $sanitizers;
	}

	/**
	 * Validates the latest published post.
	 *
	 * @return array|WP_Error The validation errors, or WP_Error.
	 */
	public static function validate_after_plugin_activation() {
		$url = amp_admin_get_preview_permalink();
		if ( ! $url ) {
			return new WP_Error( 'no_published_post_url_available' );
		}
		$validity = self::validate_url( $url );
		if ( is_wp_error( $validity ) ) {
			return $validity;
		}
		$validation_errors = wp_list_pluck( $validity['results'], 'error' );
		if ( is_array( $validity ) && count( $validation_errors ) > 0 ) { // @todo This should only warn when there are unaccepted validation errors.
			AMP_Validated_URL_Post_Type::store_validation_errors(
				$validation_errors,
				$validity['url'],
				wp_array_slice_assoc( $validity, [ 'queried_object_id', 'queried_object_type' ] )
			);
			set_transient( self::PLUGIN_ACTIVATION_VALIDATION_ERRORS_TRANSIENT_KEY, $validation_errors, 60 );
		} else {
			delete_transient( self::PLUGIN_ACTIVATION_VALIDATION_ERRORS_TRANSIENT_KEY );
		}
		return $validation_errors;
	}

	/**
	 * Validates a given URL.
	 *
	 * The validation errors will be stored in the validation status custom post type,
	 * as well as in a transient.
	 *
	 * @param string $url The URL to validate. This need not include the amp query var.
	 * @return WP_Error|array {
	 *     Response.
	 *
	 *     @type array  $results             Validation results, where each nested array contains an error key and sanitized key.
	 *     @type string $url                 Final URL that was checked or redirected to.
	 *     @type int    $queried_object_id   Queried object ID.
	 *     @type string $queried_object_type Queried object type.
	 * }
	 */
	public static function validate_url( $url ) {

		$added_query_vars = [
			self::VALIDATE_QUERY_VAR   => self::get_amp_validate_nonce(),
			self::CACHE_BUST_QUERY_VAR => wp_rand(),
		];
		$validation_url   = add_query_arg( $added_query_vars, $url );

		$r = null;

		/** This filter is documented in wp-includes/class-http.php */
		$allowed_redirects = apply_filters( 'http_request_redirection_count', 5 );
		for ( $redirect_count = 0; $redirect_count < $allowed_redirects; $redirect_count++ ) {
			$r = wp_remote_get(
				$validation_url,
				[
					'cookies'     => wp_unslash( $_COOKIE ), // Pass along cookies so private pages and drafts can be accessed.
					'timeout'     => 15, // Increase from default of 5 to give extra time for the plugin to identify the sources for any given validation errors; also, response caching is disabled when validating.
					'sslverify'   => false,
					'redirection' => 0, // Because we're in a loop for redirection.
					'headers'     => [
						'Cache-Control' => 'no-cache',
					],
				]
			);

			// If the response is not a redirect, then break since $r is all we need.
			$response_code   = wp_remote_retrieve_response_code( $r );
			$location_header = wp_remote_retrieve_header( $r, 'Location' );
			$is_redirect     = (
				$response_code
				&&
				$response_code > 300 && $response_code < 400
				&&
				$location_header
			);
			if ( ! $is_redirect ) {
				break;
			}

			// Ensure absolute URL.
			if ( '/' === substr( $location_header, 0, 1 ) ) {
				$location_header = preg_replace( '#(^https?://[^/]+)/.*#', '$1', home_url( '/' ) ) . $location_header;
			}

			// Block redirecting to a different host.
			$location_header = wp_validate_redirect( $location_header );
			if ( ! $location_header ) {
				break;
			}

			$validation_url = $location_header;
		}

		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( wp_remote_retrieve_response_code( $r ) >= 300 ) {
			return new WP_Error(
				wp_remote_retrieve_response_code( $r ),
				wp_remote_retrieve_response_message( $r )
			);
		}

		$url = remove_query_arg(
			array_keys( $added_query_vars ),
			$validation_url
		);

		$response = wp_remote_retrieve_body( $r );
		if ( trim( $response ) === '' ) {
			return new WP_Error( 'white_screen_of_death' );
		}
		if ( ! preg_match( '#</body>.*?<!--\s*AMP_VALIDATION\s*:\s*(\{.*?\})\s*-->#s', $response, $matches ) ) {
			return new WP_Error( 'response_comment_absent' );
		}
		$validation = json_decode( $matches[1], true );
		if ( json_last_error() || ! isset( $validation['results'] ) || ! is_array( $validation['results'] ) ) {
			return new WP_Error( 'malformed_json_validation_errors' );
		}

		return array_merge(
			$validation,
			compact( 'url' )
		);
	}

	/**
	 * Serialize validation error messages.
	 *
	 * In order to safely pass validation error messages through redirects with query parameters, they must be serialized
	 * with a HMAC for security. The messages contain markup so the HMAC prevents tampering.
	 *
	 * @since 1.4.2
	 * @see AMP_Validation_Manager::unserialize_validation_error_messages()
	 *
	 * @param string[] $messages Messages.
	 * @return string Serialized.
	 */
	public static function serialize_validation_error_messages( $messages ) {
		$encoded_messages = base64_encode( wp_json_encode( array_unique( $messages ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return wp_hash( $encoded_messages ) . ':' . $encoded_messages;
	}

	/**
	 * Unserialize validation error messages.
	 *
	 * @since 1.4.2
	 * @see AMP_Validation_Manager::serialize_validation_error_messages()
	 *
	 * @param string $serialized Serialized messages.
	 * @return string[]|null
	 */
	public static function unserialize_validation_error_messages( $serialized ) {
		$parts = explode( ':', $serialized, 2 );
		if ( count( $parts ) !== 2 || wp_hash( $parts[1] ) !== $parts[0] ) {
			return null;
		}
		return json_decode( base64_decode( $parts[1] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Get error message for a validate URL failure.
	 *
	 * @param string $error_code    Error code.
	 * @param string $error_message Error message, typically technical such as from HTTP status text or cURL error message.
	 * @return string Error message with HTML markup.
	 */
	public static function get_validate_url_error_message( $error_code, $error_message = '' ) {
		$check_error_log = sprintf(
			/* translators: %s is link to Debugging in WordPress */
			' ' . __( 'Please check your server PHP error logs; to do this you may need to <a href="%s" target="_blank">enable</a> <code>WP_DEBUG_LOG</code>.', 'amp' ),
			esc_url( 'https://wordpress.org/support/article/debugging-in-wordpress/' )
		);

		if ( $error_message ) {
			$error_message = ' ' . rtrim( $error_message, '.' ) . '.';
		}

		$support_forum_message = ' ' . sprintf(
			/* translators: %1$s: Link to support forum. %2$s: Link to new topic form in support forum. */
			__( 'If you are stuck, please search the <a href="%1$s">support forum</a> for possible related topics, or otherwise start a <a href="%2$s">new support topic</a> including the error message, the URL to your site, and your active theme/plugins.', 'amp' ),
			esc_url( 'https://wordpress.org/support/plugin/amp/' ),
			esc_url( 'https://wordpress.org/support/plugin/amp/#new-topic-0' )
		);

		$site_health_message = '';
		if ( version_compare( get_bloginfo( 'version' ), '5.2', '>=' ) ) {
			$site_health_message .= ' ' . sprintf(
				/* translators: %s is link to Site Health */
				__( 'Please check your <a href="%s">Site Health</a> to verify it can perform loopback requests.', 'amp' ),
				esc_url( admin_url( 'site-health.php' ) )
			);
			$support_forum_message .= ' ' . sprintf(
				/* translators: %s is the URL to Site Health Info. */
				__( 'Please include your <a href="%s">Site Health Info</a>.', 'amp' ),
				esc_url( admin_url( 'site-health.php?tab=debug' ) )
			);
		}

		switch ( $error_code ) {
			case 'http_request_failed':
				return __( 'Failed to fetch URL to validate.', 'amp' ) . $error_message . $site_health_message . $support_forum_message;
			case 'white_screen_of_death':
				return __( 'Unable to validate URL. Encountered a white screen of death likely due to a PHP fatal error.', 'amp' ) . $error_message . $check_error_log . $support_forum_message;
			case '404':
				return __( 'The fetched URL was not found. It may have been deleted. If so, you can trash this.', 'amp' ) . $error_message . $support_forum_message;
			case '500':
				return __( 'An internal server error occurred when fetching the URL for validation.', 'amp' ) . $error_message . $check_error_log . $support_forum_message;
			case 'response_comment_absent':
				return sprintf(
					/* translators: %1$s: AMP_VALIDATION, %2$s: </body> */
					__( 'URL validation failed to due to the absence of the expected JSON-containing %1$s HTML comment after %2$s. This is often due to a PHP fatal error occurring.', 'amp' ),
					'<code>AMP_VALIDATION</code>',
					'<code>&lt;/body&gt;</code>'
				) . $error_message . $check_error_log . $support_forum_message;
			case 'malformed_json_validation_errors':
				return sprintf(
					/* translators: %1$s: AMP_VALIDATION, %2$s: </body> */
					__( 'URL validation failed to due to unexpected JSON in the %1$s HTML comment after %2$s.', 'amp' ),
					'<code>AMP_VALIDATION</code>',
					'<code>&lt;/body&gt;</code>'
				) . $error_message . $support_forum_message;
			default:
				/* translators: %s is error code */
				return sprintf( __( 'URL validation failed. Error code: %s.', 'amp' ), $error_code ) . $error_message . $support_forum_message;
		}
	}

	/**
	 * On activating a plugin, display a notice if a plugin causes an AMP validation error.
	 *
	 * @return void
	 */
	public static function print_plugin_notice() {
		global $pagenow;
		if ( ( 'plugins.php' === $pagenow ) && ( ! empty( $_GET['activate'] ) || ! empty( $_GET['activate-multi'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$validation_errors = get_transient( self::PLUGIN_ACTIVATION_VALIDATION_ERRORS_TRANSIENT_KEY );
			if ( empty( $validation_errors ) || ! is_array( $validation_errors ) ) {
				return;
			}
			delete_transient( self::PLUGIN_ACTIVATION_VALIDATION_ERRORS_TRANSIENT_KEY );
			$errors          = AMP_Validation_Error_Taxonomy::summarize_validation_errors( $validation_errors );
			$invalid_plugins = isset( $errors[ AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT ]['plugin'] ) ? array_unique( $errors[ AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT ]['plugin'] ) : null;
			if ( isset( $invalid_plugins ) ) {
				$reported_plugins = [];
				foreach ( $invalid_plugins as $plugin_slug ) {
					$plugin_data        = AMP_Validation_Error_Taxonomy::get_plugin_from_slug( $plugin_slug );
					$plugin_name        = is_array( $plugin_data ) ? $plugin_data['data']['Name'] : $plugin_slug;
					$reported_plugins[] = $plugin_name;
				}

				$more_details_link = sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						add_query_arg(
							'post_type',
							AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
							admin_url( 'edit.php' )
						)
					),
					__( 'More details', 'amp' )
				);

				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s %s</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">%s</span></button></div>',
					esc_html(
						sprintf(
							/* translators: %s is comma-separated list of one or more plugins */
							_n(
								'Warning: The following plugin may be incompatible with AMP: %s.',
								'Warning: The following plugins may be incompatible with AMP: %s.',
								count( $invalid_plugins ),
								'amp'
							),
							implode( ', ', $reported_plugins ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						)
					),
					$more_details_link, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_html__( 'Dismiss this notice.', 'amp' )
				);
			}
		}
	}

	/**
	 * Enqueues the block validation script.
	 *
	 * @return void
	 */
	public static function enqueue_block_validation() {
		/*
		 * The AMP_Validation_Manager::post_supports_validation() method is not being used here because
		 * a post's status for validation checking can change during the life of the editor, such as when
		 * the user toggles AMP back on after having turned it off, and then gets the validation
		 * warnings appearing due to the amp-block-validation having been enqueued already.
		 */
		$should_enqueue_block_validation = (
			self::has_cap()
			&&
			(
				( AMP_Options_Manager::is_website_experience_enabled() && current_theme_supports( AMP_Theme_Support::SLUG ) )
				||
				( AMP_Options_Manager::is_stories_experience_enabled() && AMP_Story_Post_Type::POST_TYPE_SLUG === get_post_type() )
			)
		);
		if ( ! $should_enqueue_block_validation ) {
			return;
		}

		$slug = 'amp-block-validation';

		$asset_file   = AMP__DIR__ . '/assets/js/' . $slug . '.asset.php';
		$asset        = require $asset_file;
		$dependencies = $asset['dependencies'];
		$version      = $asset['version'];

		wp_enqueue_script(
			$slug,
			amp_get_asset_url( "js/{$slug}.js" ),
			$dependencies,
			$version,
			true
		);

		wp_enqueue_style(
			$slug,
			amp_get_asset_url( "css/{$slug}-compiled.css" ),
			false,
			AMP__VERSION
		);

		wp_styles()->add_data( $slug, 'rtl', 'replace' );

		$status_and_errors = AMP_Post_Meta_Box::get_status_and_errors( get_post() );
		$enabled_status    = $status_and_errors['status'];

		$data = [
			'isSanitizationAutoAccepted' => self::is_sanitization_auto_accepted(),
			'possibleStatuses'           => [ AMP_Post_Meta_Box::ENABLED_STATUS, AMP_Post_Meta_Box::DISABLED_STATUS ],
			'defaultStatus'              => $enabled_status,
		];

		wp_localize_script(
			$slug,
			'ampBlockValidation',
			$data
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $slug, 'amp' );
		} elseif ( function_exists( 'wp_get_jed_locale_data' ) || function_exists( 'gutenberg_get_jed_locale_data' ) ) {
			$locale_data  = function_exists( 'wp_get_jed_locale_data' ) ? wp_get_jed_locale_data( 'amp' ) : gutenberg_get_jed_locale_data( 'amp' );
			$translations = wp_json_encode( $locale_data );

			wp_add_inline_script(
				$slug,
				'wp.i18n.setLocaleData( ' . $translations . ', "amp" );',
				'after'
			);
		}
	}
}

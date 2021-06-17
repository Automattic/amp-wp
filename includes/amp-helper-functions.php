<?php
/**
 * AMP Helper Functions
 *
 * @package AMP
 */

use AmpProject\AmpWP\Admin\ReaderThemes;
use AmpProject\AmpWP\AmpSlugCustomizationWatcher;
use AmpProject\AmpWP\AmpWpPluginFactory;
use AmpProject\AmpWP\Exception\InvalidService;
use AmpProject\AmpWP\Icon;
use AmpProject\AmpWP\Option;
use AmpProject\AmpWP\QueryVar;
use AmpProject\AmpWP\Services;

/**
 * Determine whether AMP is enabled on the current site.
 *
 * @since 2.1.1
 * @internal
 *
 * @return bool Whether enabled.
 */
function amp_is_enabled() {
	/**
	 * Filters whether AMP is enabled on the current site.
	 *
	 * Useful if the plugin is network activated and you want to turn it off on select sites.
	 *
	 * @since 0.2
	 * @since 2.0 Filter now runs earlier at plugins_loaded (with earliest priority) rather than at the after_setup_theme action.
	 *
	 * @param bool $enabled Whether the AMP plugin's functionality should be enabled.
	 */
	return (bool) apply_filters( 'amp_is_enabled', true );
}

/**
 * Handle activation of plugin.
 *
 * @since 0.2
 * @internal
 *
 * @param bool $network_wide Whether the activation was done network-wide.
 */
function amp_activate( $network_wide = false ) {
	if ( amp_is_enabled() ) {
		AmpWpPluginFactory::create()->activate( $network_wide );
	}
}

/**
 * Handle deactivation of plugin.
 *
 * @since 0.2
 * @internal
 *
 * @param bool $network_wide Whether the activation was done network-wide.
 */
function amp_deactivate( $network_wide = false ) {
	if ( amp_is_enabled() ) {
		AmpWpPluginFactory::create()->deactivate( $network_wide );
	}
}

/**
 * Bootstrap plugin.
 *
 * @since 1.5
 * @internal
 */
function amp_bootstrap_plugin() {
	if ( ! amp_is_enabled() ) {
		return;
	}

	AmpWpPluginFactory::create()->register();

	// The amp_bootstrap_plugin() function is called at the plugins_loaded action with the earliest priority. This is
	// the earliest we can run this since that is when pluggable.php has been required and wp_hash() is available.
	AMP_Validation_Manager::init_validate_request();

	/*
	 * Register AMP scripts regardless of whether AMP is enabled or it is the AMP endpoint
	 * for the sake of being able to use AMP components on non-AMP documents ("dirty AMP").
	 */
	add_action( 'wp_default_scripts', 'amp_register_default_scripts' );

	add_action( 'wp_default_styles', 'amp_register_default_styles' );

	// Ensure async and custom-element/custom-template attributes are present on script tags.
	add_filter( 'script_loader_tag', 'amp_filter_script_loader_tag', PHP_INT_MAX, 2 );

	// Ensure crossorigin=anonymous is added to font links.
	add_filter( 'style_loader_tag', 'amp_filter_font_style_loader_tag_with_crossorigin_anonymous', 10, 4 );

	add_action( 'after_setup_theme', 'amp_after_setup_theme', 5 );

	add_action( 'plugins_loaded', '_amp_bootstrap_customizer', 9 ); // Should be hooked before priority 10 on 'plugins_loaded' to properly unhook core panels.
}

/**
 * Init AMP.
 *
 * @since 0.1
 * @internal
 */
function amp_init() {

	/**
	 * Triggers on init when AMP plugin is active.
	 *
	 * @since 0.3
	 */
	do_action( 'amp_init' );

	add_filter( 'allowed_redirect_hosts', [ 'AMP_HTTP', 'filter_allowed_redirect_hosts' ] );
	AMP_HTTP::purge_amp_query_vars();
	AMP_HTTP::send_cors_headers();
	AMP_HTTP::handle_xhr_request();
	AMP_Theme_Support::init();
	AMP_Validation_Manager::init();
	AMP_Service_Worker::init();
	add_action( 'admin_init', 'AMP_Options_Manager::init' );
	add_action( 'admin_init', 'AMP_Options_Manager::register_settings' );
	add_action( 'rest_api_init', 'AMP_Options_Manager::register_settings' );
	add_action( 'wp_loaded', 'amp_bootstrap_admin' );

	add_action( 'admin_bar_menu', 'amp_add_admin_bar_view_link', 100 );

	add_action(
		'admin_bar_init',
		function () {
			$handle = 'amp-icons';
			if ( ! is_admin() && wp_style_is( $handle, 'registered' ) ) {
				wp_styles()->registered[ $handle ]->deps[] = 'admin-bar'; // Ensure included in dev mode.
				wp_enqueue_style( $handle );
			}
		}
	);

	add_action( 'wp_loaded', 'amp_editor_core_blocks' );
	add_filter( 'request', 'amp_force_query_var_value' );

	/*
	 * Broadcast plugin updates.
	 * Note that AMP_Options_Manager::get_option( Option::VERSION, '0.0' ) cannot be used because
	 * version was new option added, and in that case default would never be used for a site
	 * upgrading from a version prior to 1.0. So this is why get_option() is currently used.
	 */
	$options     = get_option( AMP_Options_Manager::OPTION_NAME, [] );
	$old_version = isset( $options[ Option::VERSION ] ) ? $options[ Option::VERSION ] : '0.0';

	if ( AMP__VERSION !== $old_version && is_admin() && current_user_can( 'manage_options' ) ) {
		// This waits to happen until the very end of admin_init to ensure that amp theme support and amp post type
		// support have all been added, and that the settings have been registered.
		add_action(
			'admin_init',
			static function () use ( $old_version ) {
				/**
				 * Triggers when after amp_init when the plugin version has updated.
				 *
				 * @param string $old_version Old version.
				 */
				do_action( 'amp_plugin_update', $old_version );
				AMP_Options_Manager::update_option( Option::VERSION, AMP__VERSION );
			},
			PHP_INT_MAX
		);
	}

	add_action(
		'rest_api_init',
		static function() {
			$reader_themes = new ReaderThemes();

			$reader_theme_controller = new AMP_Reader_Theme_REST_Controller( $reader_themes );
			$reader_theme_controller->register_routes();
		}
	);

	/*
	 * Hide admin bar if the window is inside the setup wizard iframe.
	 *
	 * Detects whether the current window is in an iframe with the specified `name` attribute. The iframe is created
	 * by Preview component located in <assets/src/setup/pages/save/index.js>.
	 */
	add_action(
		'wp_print_scripts',
		function() {
			if ( ! amp_is_dev_mode() || ! is_admin_bar_showing() ) {
				return;
			}
			?>
			<script data-ampdevmode>
				( () => {
					if ( 'amp-wizard-completion-preview' !== window.name ) {
						return;
					}

					/** @type {HTMLStyleElement} */
					const style = document.createElement( 'style' );
					style.setAttribute( 'type', 'text/css' );
					style.appendChild( document.createTextNode( 'html { margin-top: 0 !important; } #wpadminbar { display: none !important; }' ) );
					document.head.appendChild( style );

					document.addEventListener( 'DOMContentLoaded', function() {
						const adminBar = document.getElementById( 'wpadminbar' );
						if ( adminBar ) {
							document.body.classList.remove( 'admin-bar' );
							adminBar.remove();
						}
					});
				} )();
			</script>
			<?php
		}
	);
}

/**
 * Set up AMP.
 *
 * This function must be invoked through the 'after_setup_theme' action to allow
 * the AMP setting to declare the post types support earlier than plugins/theme.
 *
 * @since 0.6
 * @internal
 */
function amp_after_setup_theme() {
	// Ensure AMP_QUERY_VAR is set since some plugins still try reading it instead of using amp_get_slug().
	if ( ! defined( 'AMP_QUERY_VAR' ) ) {
		define( 'AMP_QUERY_VAR', amp_get_slug() );
	}

	/** This filter is documented in includes/amp-helper-functions.php */
	if ( false === apply_filters( 'amp_is_enabled', true ) ) {
		_doing_it_wrong(
			'add_filter',
			esc_html(
				sprintf(
					/* translators: 1: amp_is_enabled filter name, 2: plugins_loaded action */
					__( 'Filter for "%1$s" added too late. To disable AMP, this filter must be added before the "%2$s" action.', 'amp' ),
					'amp_is_enabled',
					'plugins_loaded'
				)
			),
			'2.0'
		);
	}

	add_action( 'init', 'amp_init', 0 ); // Must be 0 because widgets_init happens at init priority 1.
}

/**
 * Make sure the `amp` query var has an explicit value.
 *
 * This avoids issues when filtering the deprecated `query_string` hook.
 *
 * @since 0.3.3
 * @internal
 *
 * @param array $query_vars Query vars.
 * @return array Query vars.
 */
function amp_force_query_var_value( $query_vars ) {
	if ( isset( $query_vars[ amp_get_slug() ] ) && '' === $query_vars[ amp_get_slug() ] ) {
		$query_vars[ amp_get_slug() ] = 1;
	}
	return $query_vars;
}

/**
 * Whether this is in 'canonical mode'.
 *
 * Themes can register support for this with `add_theme_support( AMP_Theme_Support::SLUG )`:
 *
 * ```php
 * add_theme_support( AMP_Theme_Support::SLUG );
 * ```
 *
 * This will serve templates in AMP-first, allowing you to use AMP components in your theme templates.
 * If you want to make available in transitional mode, where templates are served in AMP or non-AMP documents, do:
 *
 * ```php
 * add_theme_support( AMP_Theme_Support::SLUG, array(
 *     'paired' => true,
 * ) );
 * ```
 *
 * Transitional mode is also implied if you define a `template_dir`:
 *
 * ```php
 * add_theme_support( AMP_Theme_Support::SLUG, array(
 *     'template_dir' => 'amp',
 * ) );
 * ```
 *
 * If you want to have AMP-specific templates in addition to serving AMP-first, do:
 *
 * ```php
 * add_theme_support( AMP_Theme_Support::SLUG, array(
 *     'paired'       => false,
 *     'template_dir' => 'amp',
 * ) );
 * ```
 *
 * @see AMP_Theme_Support::read_theme_support()
 * @return boolean Whether this is in AMP 'canonical' mode, that is whether it is AMP-first and there is not a separate (paired) AMP URL.
 */
function amp_is_canonical() {
	return AMP_Theme_Support::STANDARD_MODE_SLUG === AMP_Options_Manager::get_option( Option::THEME_SUPPORT );
}

/**
 * Determines whether the legacy AMP post templates are being used.
 *
 * @since 2.0
 * @return bool
 */
function amp_is_legacy() {
	if ( AMP_Theme_Support::READER_MODE_SLUG !== AMP_Options_Manager::get_option( Option::THEME_SUPPORT ) ) {
		return false;
	}

	$reader_theme = AMP_Options_Manager::get_option( Option::READER_THEME );
	if ( ReaderThemes::DEFAULT_READER_THEME === $reader_theme ) {
		return true;
	}

	return ! wp_get_theme( $reader_theme )->exists();
}

/**
 * Determine whether AMP is available for the current URL.
 *
 * @since 2.0
 *
 * @return bool Whether there is an AMP version for the provided URL.
 * @global string $pagenow
 * @global WP_Query $wp_query
 */
function amp_is_available() {
	global $pagenow, $wp_query;

	// Short-circuit for cron, CLI, admin requests or requests to non-frontend pages.
	if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || is_admin() || in_array( $pagenow, [ 'wp-login.php', 'wp-signup.php', 'wp-activate.php', 'repair.php' ], true ) ) {
		return false;
	}

	$warn = static function () {
		static $already_warned_sources = [];

		try {
			$likely_culprit_detector = Services::get( 'dev_tools.likely_culprit_detector' );
			$closest_source          = $likely_culprit_detector->analyze_backtrace();
		} catch ( InvalidService $e ) {
			$closest_source = [
				'type' => 'exception',
				'name' => 'invalid_service',
			];
		}

		$closest_source_identifier = $closest_source['type'] . ':' . $closest_source['name'];
		if ( in_array( $closest_source_identifier, $already_warned_sources, true ) ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: amp_is_available() function, 2: amp_is_request() function, 3: is_amp_endpoint() function */
			__( '%1$s (or %2$s, formerly %3$s) was called too early and so it will not work properly.', 'amp' ),
			'`amp_is_available()`',
			'`amp_is_request()`',
			'`is_amp_endpoint()`'
		);

		$current_hook = current_action();
		if ( $current_hook ) {
			/* translators: placeholder is the current hook */
			$message .= ' ' . sprintf(
				'WordPress is currently doing the %s hook.',
				'`' . $current_hook . '`'
			);
		} else {
			$message .= ' ' . __( 'WordPress is not currently doing any hook.', 'amp' );
		}

		$message .= ' ' . sprintf(
			/* translators: 1: the wp action, 2: the WP_Query class, 3: the amp_skip_post() function */
			__( 'Calling this function before the %1$s action means it will not have access to %2$s and the queried object to determine if it is an AMP response, thus neither the %3$s filter nor the AMP enabled toggle will be considered.', 'amp' ),
			'`wp`',
			'`WP_Query`',
			'`amp_skip_post()`'
		);

		if ( ! empty( $closest_source['type'] ) && ! empty( $closest_source['name'] ) ) {
			$translated_string = false;

			switch ( $closest_source['type'] ) {
				case 'plugin':
					/* translators: placeholder is the slug of the plugin */
					$translated_string = __( 'It appears the plugin with slug %s is responsible; please contact the author.', 'amp' );
					break;
				case 'mu-plugin':
					/* translators: placeholder is the slug of the must-use plugin */
					$translated_string = __( 'It appears the must-use plugin with slug %s is responsible; please contact the author.', 'amp' );
					break;
				case 'theme':
					/* translators: placeholder is the slug of the theme */
					$translated_string = __( 'It appears the theme with slug %s is responsible; please contact the author.', 'amp' );
					break;
				case 'exception':
					$translated_string = __( 'The function was called too early (before the plugins_loaded action) to determine the plugin source.', 'amp' );
					break;
			}

			if ( $translated_string ) {
				$message .= ' ' . sprintf( $translated_string, '`' . $closest_source['name'] . '`' );
			}
		}

		_doing_it_wrong( 'amp_is_available', esc_html( $message ), '2.0.0' );
		$already_warned_sources[] = $closest_source_identifier;
	};

	// Make sure the parse_request action has triggered before trying to read from the REST_REQUEST constant, which is set during rest_api_loaded().
	if ( ! did_action( 'parse_request' ) ) {
		$warn();
	} elseif ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	// Make sure that the parse_query action has triggered, as this is required to initially populate the global WP_Query.
	if ( ! ( $wp_query instanceof WP_Query || did_action( 'parse_query' ) ) ) {
		$warn();
	}

	// Always return false when requesting the service worker.
	// Note this is no longer strictly required because AMP_Theme_Support::prepare_response() will abort for non-HTML responses.
	// But it is still good to do so because it avoids needlessly output-buffering the response.
	if ( class_exists( 'WP_Service_Workers' ) && $wp_query instanceof WP_Query && defined( 'WP_Service_Workers::QUERY_VAR' ) && $wp_query->get( WP_Service_Workers::QUERY_VAR ) ) {
		return false;
	}

	// Short-circuit queries that can never have AMP responses (e.g. post embeds and feeds).
	// Note that these conditionals only require the parse_query action to have been run. They don't depend on the wp action having been fired.
	if (
		$wp_query instanceof WP_Query
		&&
		(
			$wp_query->is_embed()
			||
			$wp_query->is_feed()
			||
			$wp_query->is_comment_feed()
			||
			$wp_query->is_trackback()
			||
			$wp_query->is_robots()
			||
			( method_exists( $wp_query, 'is_favicon' ) && $wp_query->is_favicon() )
		)
	) {
		return false;
	}

	// Ensure that all templates can be accessed in AMP when a Reader theme is selected.
	$has_reader_theme = (
		AMP_Theme_Support::READER_MODE_SLUG === AMP_Options_Manager::get_option( Option::THEME_SUPPORT )
		&&
		ReaderThemes::DEFAULT_READER_THEME !== AMP_Options_Manager::get_option( Option::READER_THEME )
	);
	if ( $has_reader_theme && is_customize_preview() ) {
		return true;
	}

	$is_legacy = amp_is_legacy();

	// If the query has not been initialized, we can only assume AMP is available if theme support is present and all templates are supported.
	if ( ! $wp_query instanceof WP_Query || ! did_action( 'wp' ) ) {
		$warn();
		return ! $is_legacy && AMP_Options_Manager::get_option( Option::ALL_TEMPLATES_SUPPORTED );
	}

	// If redirected to this page because AMP is not available due to validation errors, prevent AMP from being available (if not AMP-first).
	if (
		( ! amp_is_canonical() || AMP_Validation_Manager::has_cap() )
		&&
		( isset( $_GET[ QueryVar::NOAMP ] ) && QueryVar::NOAMP_AVAILABLE === $_GET[ QueryVar::NOAMP ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	) {
		return false;
	}

	$queried_object = get_queried_object();
	if ( ! $is_legacy ) {
		// Abort if in Transitional mode and AMP is not available for the URL.
		$availability = AMP_Theme_Support::get_template_availability( $wp_query );

		if ( ! $availability['supported'] ) {
			return false;
		}

		// If not in an AMP-first mode, check if there are any validation errors with kept invalid markup for this URL.
		// And if so, and if the user cannot do validation (since they can always get fresh validation results), then
		// AMP is not available.
		if ( ! amp_is_canonical() && ! AMP_Validation_Manager::has_cap() ) {
			$validation_errors = AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors(
				amp_get_current_url(),
				[ 'ignore_accepted' => true ]
			);
			if ( count( $validation_errors ) > 0 ) {
				return false;
			}
		}
	} elseif ( ! (
		$queried_object instanceof WP_Post &&
		$wp_query instanceof WP_Query &&
		( $wp_query->is_singular() || $wp_query->is_posts_page ) &&
		amp_is_post_supported( $queried_object ) )
	) {
		// Abort if in legacy Reader mode and the post doesn't support AMP.
		return false;
	}

	return true;
}

/**
 * Bootstraps the AMP customizer.
 *
 * Uses the priority of 12 for the 'after_setup_theme' action.
 * Many themes run `add_theme_support()` on the 'after_setup_theme' hook, at the default priority of 10.
 * And that function's documentation suggests adding it to that action.
 * So this enables themes to `add_theme_support( AMP_Theme_Support::SLUG )`.
 * And `amp_init_customizer()` will be able to recognize theme support by calling `amp_is_canonical()`.
 *
 * @since 0.4
 * @internal
 */
function _amp_bootstrap_customizer() {
	add_action( 'after_setup_theme', 'amp_init_customizer', 12 );
}

/**
 * Get the slug used in AMP for the query var, endpoint, and post type support.
 *
 * The return value can be overridden by previously defining a AMP_QUERY_VAR
 * constant or by adding a 'amp_query_var' filter, but *warning* this ability
 * may be deprecated in the future. Normally the slug should be just 'amp'.
 *
 * @since 0.7
 * @since 2.1 Added $ignore_late_defined_slug argument.
 *
 * @param bool $ignore_late_defined_slug Whether to ignore the late defined slug.
 * @return string Slug used for query var, endpoint, and post type support.
 */
function amp_get_slug( $ignore_late_defined_slug = false ) {

	// When a slug was defined late according to AmpSlugCustomizationWatcher, the slug will be stored in the
	// LATE_DEFINED_SLUG option by the PairedRouting service so that it can be used early. This is only needed until
	// the after_setup_theme action fires, because at that time the late-defined slug will have been established.
	if ( ! $ignore_late_defined_slug && ! did_action( AmpSlugCustomizationWatcher::LATE_DETERMINATION_ACTION ) ) {
		$slug = AMP_Options_Manager::get_option( Option::LATE_DEFINED_SLUG );
		if ( ! empty( $slug ) && is_string( $slug ) ) {
			return $slug;
		}
	}

	/**
	 * Filter the AMP query variable.
	 *
	 * Warning: This filter may become deprecated.
	 *
	 * @since 0.3.2
	 *
	 * @param string $query_var The AMP query variable.
	 */
	return apply_filters( 'amp_query_var', defined( 'AMP_QUERY_VAR' ) ? AMP_QUERY_VAR : QueryVar::AMP );
}

/**
 * Get the URL for the current request.
 *
 * This is essentially the REQUEST_URI prefixed by the scheme and host for the home URL.
 * This is needed in particular due to subdirectory installs.
 *
 * @since 1.0
 * @internal
 *
 * @return string Current URL.
 */
function amp_get_current_url() {
	$parsed_url = wp_parse_url( home_url() );

	if ( ! is_array( $parsed_url ) ) {
		$parsed_url = [];
	}

	if ( empty( $parsed_url['scheme'] ) ) {
		$parsed_url['scheme'] = is_ssl() ? 'https' : 'http';
	}
	if ( ! isset( $parsed_url['host'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$parsed_url['host'] = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : 'localhost';
	}

	$current_url = $parsed_url['scheme'] . '://';
	if ( isset( $parsed_url['user'] ) ) {
		$current_url .= $parsed_url['user'];
		if ( isset( $parsed_url['pass'] ) ) {
			$current_url .= ':' . $parsed_url['pass'];
		}
		$current_url .= '@';
	}
	$current_url .= $parsed_url['host'];
	if ( isset( $parsed_url['port'] ) ) {
		$current_url .= ':' . $parsed_url['port'];
	}
	$current_url .= '/';

	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$current_url .= ltrim( wp_unslash( $_SERVER['REQUEST_URI'] ), '/' );
	}
	return esc_url_raw( $current_url );
}

/**
 * Retrieves the full AMP-specific permalink for the given post ID.
 *
 * On a site in Standard mode, this is the same as `get_permalink()`.
 *
 * @since 0.1
 *
 * @param int $post_id Post ID.
 * @return string AMP permalink.
 */
function amp_get_permalink( $post_id ) {
	if ( amp_is_canonical() ) {
		return get_permalink( $post_id );
	}
	return amp_add_paired_endpoint( get_permalink( $post_id ) );
}

/**
 * Remove the AMP endpoint (and query var) from a given URL.
 *
 * @since 0.7
 * @since 2.1 Deprecated.
 * @deprecated Use amp_remove_paired_endpoint() instead.
 *
 * @param string $url URL.
 * @return string URL with AMP stripped.
 */
function amp_remove_endpoint( $url ) {
	return amp_remove_paired_endpoint( $url );
}

/**
 * Add amphtml link.
 *
 * If there are known validation errors for the current URL then do not output anything.
 *
 * @since 1.0
 */
function amp_add_amphtml_link() {
	if (
		amp_is_canonical()
		||
		/**
		 * Filters whether to show the amphtml link on the frontend.
		 *
		 * This is deprecated since the name was wrong and the use case is not clear. To remove this from being printed,
		 * instead of using the filter you can rather do:
		 *
		 *     add_action( 'template_redirect', static function () {
		 *         remove_action( 'wp_head', 'amp_add_amphtml_link' );
		 *     } );
		 *
		 * @since 0.2
		 * @deprecated Remove amp_add_amphtml_link() call on wp_head action instead.
		 */
		false === apply_filters_deprecated(
			'amp_frontend_show_canonical',
			[ true ],
			'2.0',
			'',
			sprintf(
				/* translators: 1: amphtml, 2: amp_add_amphtml_link(), 3: wp_head, 4: template_redirect */
				esc_html__( 'Removal of %1$s link should be done by removing %2$s from the %3$s action at %4$s.', 'amp' ),
				'amphtml',
				__FUNCTION__ . '()',
				'wp_head',
				'template_redirect'
			)
		)
	) {
		return;
	}

	if ( ! amp_is_available() ) {
		printf( '<!-- %s -->', esc_html__( 'There is no amphtml version available for this URL.', 'amp' ) );
		return;
	}

	$amp_url = amp_add_paired_endpoint( amp_get_current_url() );
	if ( $amp_url ) {
		$amp_url = remove_query_arg( QueryVar::NOAMP, $amp_url );
		printf( '<link rel="amphtml" href="%s">', esc_url( $amp_url ) );
	}
}

/**
 * Determine whether a given post supports AMP.
 *
 * @since 2.0 Formerly known as post_supports_amp().
 * @see AMP_Post_Type_Support::get_support_errors()
 *
 * @param WP_Post|int $post Post.
 * @return bool Whether the post supports AMP.
 */
function amp_is_post_supported( $post ) {
	return 0 === count( AMP_Post_Type_Support::get_support_errors( $post ) );
}

/**
 * Determine whether a given post supports AMP.
 *
 * @since 0.1
 * @since 0.6 Returns false when post has meta to disable AMP.
 * @since 2.0 Renamed to AMP-prefixed version, amp_is_post_supported().
 * @deprecated Use amp_is_post_supported() instead.
 *
 * @param WP_Post $post Post.
 * @return bool Whether the post supports AMP.
 */
function post_supports_amp( $post ) {
	return amp_is_post_supported( $post );
}

/**
 * Determine whether the current request is for an AMP page.
 *
 * This function cannot be called before the parse_query action because it needs to be able
 * to determine the queried object is able to be served as AMP. If 'amp' theme support is not
 * present, this function returns true just if the query var is present. If theme support is
 * present, then it returns true in transitional mode if an AMP template is available and the query
 * var is present, or else in standard mode if just the template is available.
 *
 * @since 2.0 Formerly known as is_amp_endpoint().
 *
 * @return bool Whether it is the AMP endpoint.
 * @global WP_Query $wp_query
 */
function amp_is_request() {
	global $wp_query;

	$is_amp_url = (
		amp_is_canonical()
		||
		amp_has_paired_endpoint()
	);

	// If AMP is not available, then it's definitely not an AMP endpoint.
	if ( ! amp_is_available() ) {
		// But, if WP_Query was not available yet, then we will just assume the query is supported since at this point we do
		// know either that the site is in Standard mode or the URL was requested with the AMP query var. This can still
		// produce an undesired result when a Standard mode site has a post that opts out of AMP, but this issue will
		// have been flagged via _doing_it_wrong() in amp_is_available() above.
		if ( ! did_action( 'wp' ) || ! $wp_query instanceof WP_Query ) {
			return $is_amp_url && AMP_Options_Manager::get_option( Option::ALL_TEMPLATES_SUPPORTED );
		}

		return false;
	}

	return $is_amp_url;
}

/**
 * Determine whether the current response being served as AMP.
 *
 * This function cannot be called before the parse_query action because it needs to be able
 * to determine the queried object is able to be served as AMP. If 'amp' theme support is not
 * present, this function returns true just if the query var is present. If theme support is
 * present, then it returns true in transitional mode if an AMP template is available and the query
 * var is present, or else in standard mode if just the template is available.
 *
 * @since 0.1
 * @since 2.0 Renamed to AMP-prefixed version, amp_is_request().
 * @deprecated Use amp_is_request() instead.
 *
 * @return bool Whether it is the AMP endpoint.
 */
function is_amp_endpoint() {
	return amp_is_request();
}

/**
 * Get AMP asset URL.
 *
 * @since 0.1
 * @internal
 *
 * @param string $file Relative path to file in assets directory.
 * @return string URL.
 */
function amp_get_asset_url( $file ) {
	return plugins_url( sprintf( 'assets/%s', $file ), AMP__FILE__ );
}

/**
 * Get AMP boilerplate code.
 *
 * @since 0.7
 * @internal
 * @link https://www.ampproject.org/docs/reference/spec#boilerplate
 *
 * @return string Boilerplate code.
 */
function amp_get_boilerplate_code() {
	$stylesheets = amp_get_boilerplate_stylesheets();
	return sprintf( '<style amp-boilerplate>%s</style><noscript><style amp-boilerplate>%s</style></noscript>', $stylesheets[0], $stylesheets[1] );
}

/**
 * Get AMP boilerplate stylesheets.
 *
 * @since 1.3
 * @internal
 * @link https://www.ampproject.org/docs/reference/spec#boilerplate
 *
 * @return string[] Stylesheets, where first is contained in style[amp-boilerplate] and the second in noscript>style[amp-boilerplate].
 */
function amp_get_boilerplate_stylesheets() {
	return [
		'body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}',
		'body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}',
	];
}

/**
 * Add generator metadata.
 *
 * @since 0.6
 * @since 1.0 Add template mode.
 * @since 2.0 Add reader theme.
 * @internal
 */
function amp_add_generator_metadata() {
	$template_mode = AMP_Options_Manager::get_option( Option::THEME_SUPPORT );
	$reader_theme  = AMP_Options_Manager::get_option( Option::READER_THEME );

	// Account for case where the active theme has been switched to be the same as the reader theme.
	// In this case, the behavior of the plugin is the same as transitional mode.
	if (
		AMP_Theme_Support::READER_MODE_SLUG === $template_mode
		&&
		get_stylesheet() === $reader_theme
		&&
		! Services::get( 'reader_theme_loader' )->is_enabled()
	) {
		$template_mode = AMP_Theme_Support::TRANSITIONAL_MODE_SLUG;
	}

	$content  = sprintf( 'AMP Plugin v%s', AMP__VERSION );
	$content .= sprintf( '; mode=%s', $template_mode );
	if ( AMP_Theme_Support::READER_MODE_SLUG === $template_mode ) {
		$content .= sprintf( '; theme=%s', $reader_theme );
	}

	printf( '<meta name="generator" content="%s">', esc_attr( $content ) );
}

/**
 * Register default scripts for AMP components.
 *
 * @internal
 *
 * @param WP_Scripts $wp_scripts Scripts.
 */
function amp_register_default_scripts( $wp_scripts ) {
	// AMP Runtime.
	$handle = 'amp-runtime';
	$wp_scripts->add(
		$handle,
		'https://cdn.ampproject.org/v0.js',
		[],
		null
	);
	$wp_scripts->add_data(
		$handle,
		'amp_script_attributes',
		[
			'async' => true,
		]
	);

	// Shadow AMP API.
	$handle = 'amp-shadow';
	$wp_scripts->add(
		$handle,
		'https://cdn.ampproject.org/shadow-v0.js',
		[],
		null
	);
	$wp_scripts->add_data(
		$handle,
		'amp_script_attributes',
		[
			'async' => true,
		]
	);

	// Register all AMP components as defined in the spec.
	$extension_specs = AMP_Allowed_Tags_Generated::get_extension_specs();
	if ( isset( $extension_specs['amp-carousel'] ) && '0.1' === $extension_specs['amp-carousel']['latest'] ) {
		/*
		 * The latestVersion of amp-carousel is 0.1 in https://github.com/ampproject/amphtml/blob/main/build-system/compile/bundles.config.extensions.json
		 * But we have been using 0.2 since https://github.com/ampproject/amp-wp/pull/3115
		 * Therefore, we override the latest version to also be 0.2 since it has been shown to be stable.
		 */
		$extension_specs['amp-carousel']['latest'] = '0.2';
	}

	foreach ( $extension_specs as $extension_name => $extension_spec ) {
		$src = sprintf(
			'https://cdn.ampproject.org/v0/%s-%s.js',
			$extension_name,
			$extension_spec['latest']
		);

		$wp_scripts->add(
			$extension_name,
			$src,
			[ 'amp-runtime' ],
			null
		);
	}
}

/**
 * Register default styles.
 *
 * @since 2.0
 * @internal
 *
 * @param WP_Styles $styles Styles.
 */
function amp_register_default_styles( WP_Styles $styles ) {
	$styles->add(
		'amp-default',
		amp_get_asset_url( 'css/amp-default.css' ),
		[],
		AMP__VERSION
	);
	$styles->add_data( 'amp-default', 'rtl', 'replace' );

	$styles->add(
		'amp-icons',
		amp_get_asset_url( 'css/amp-icons.css' ),
		[ 'dashicons' ],
		AMP__VERSION
	);
	$styles->add_data( 'amp-icons', 'rtl', 'replace' );
}

/**
 * Generate HTML for AMP scripts that have not yet been printed.
 *
 * This is adapted from `wp_scripts()->do_items()`, but it runs only the bare minimum required to output
 * the missing scripts, without allowing other filters to apply which may cause an invalid AMP response.
 * The HTML for the scripts is returned instead of being printed.
 *
 * @since 0.7.2
 * @see WP_Scripts::do_items()
 * @see AMP_Base_Embed_Handler::get_scripts()
 * @see AMP_Base_Sanitizer::get_scripts()
 * @internal
 *
 * @param array $scripts Script handles mapped to URLs or true.
 * @return string HTML for scripts tags that have not yet been done.
 */
function amp_render_scripts( $scripts ) {
	$script_tags = '';

	/*
	 * Make sure the src is up to date. This allows for embed handlers to override the
	 * default extension version by defining a different URL.
	 */
	foreach ( $scripts as $handle => $src ) {
		if ( is_string( $src ) && wp_script_is( $handle, 'registered' ) ) {
			wp_scripts()->registered[ $handle ]->src = $src;
		}
	}

	foreach ( array_diff( array_keys( $scripts ), wp_scripts()->done ) as $handle ) {
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			continue;
		}

		$script_dep   = wp_scripts()->registered[ $handle ];
		$script_tags .= amp_filter_script_loader_tag(
			sprintf(
				"<script type='text/javascript' src='%s'></script>\n", // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
				esc_url( $script_dep->src )
			),
			$handle
		);

		wp_scripts()->done[] = $handle;
	}
	return $script_tags;
}

/**
 * Add AMP script attributes to enqueued scripts.
 *
 * @link https://core.trac.wordpress.org/ticket/12009
 * @since 0.7
 * @internal
 *
 * @param string $tag    The script tag.
 * @param string $handle The script handle.
 * @return string Script loader tag.
 */
function amp_filter_script_loader_tag( $tag, $handle ) {
	$prefix = 'https://cdn.ampproject.org/';
	$src    = wp_scripts()->registered[ $handle ]->src;
	if ( 0 !== strpos( $src, $prefix ) ) {
		return $tag;
	}

	/*
	 * All scripts from AMP CDN should be loaded async.
	 * See <https://www.ampproject.org/docs/integration/pwa-amp/amp-in-pwa#include-"shadow-amp"-in-your-progressive-web-app>.
	 */
	$attributes = [
		'async' => true,
	];

	// Add custom-template and custom-element attributes. All component scripts look like https://cdn.ampproject.org/v0/:name-:version.js.
	if ( 'v0' === strtok( substr( $src, strlen( $prefix ) ), '/' ) ) {
		/*
		 * Per the spec, "Most extensions are custom-elements." In fact, there is only one custom template. So we hard-code it here.
		 *
		 * This could also be derived by looking at the extension_type in the extension_spec.
		 *
		 * @link https://github.com/ampproject/amphtml/blob/cd685d4e62153557519553ffa2183aedf8c93d62/validator/validator.proto#L326-L328
		 * @link https://github.com/ampproject/amphtml/blob/cd685d4e62153557519553ffa2183aedf8c93d62/extensions/amp-mustache/validator-amp-mustache.protoascii#L27
		 */
		if ( 'amp-mustache' === $handle ) {
			$attributes['custom-template'] = $handle;
		} else {
			$attributes['custom-element'] = $handle;
		}
	}

	// Add each attribute (if it hasn't already been added).
	foreach ( $attributes as $key => $value ) {
		if ( ! preg_match( ":\s$key(=|>|\s):", $tag ) ) {
			if ( true === $value ) {
				$attribute_string = sprintf( ' %s', esc_attr( $key ) );
			} else {
				$attribute_string = sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
			}
			$tag = preg_replace(
				':(?=></script>):',
				$attribute_string,
				$tag,
				1
			);
		}
	}

	return $tag;
}

/**
 * Explicitly opt-in to CORS mode by adding the crossorigin attribute to font stylesheet links.
 *
 * This explicitly triggers a CORS request, and gets back a non-opaque response, ensuring that a service
 * worker caching the external stylesheet will not inflate the storage quota. This must be done in AMP
 * and non-AMP alike because in transitional mode the service worker could cache the font stylesheets in a
 * non-AMP document without CORS (crossorigin="anonymous") in which case the service worker could then
 * fail to serve the cached font resources in an AMP document with the warning:
 *
 * > The FetchEvent resulted in a network error response: an "opaque" response was used for a request whose type is not no-cors
 *
 * @since 1.0
 * @link https://developers.google.com/web/tools/workbox/guides/storage-quota#beware_of_opaque_responses
 * @link https://developers.google.com/web/tools/workbox/guides/handle-third-party-requests#cross-origin_requests_and_opaque_responses
 * @todo This should be proposed for WordPress core.
 * @internal
 *
 * @param string $tag    Link tag HTML.
 * @param string $handle Dependency handle.
 * @param string $href   Link URL.
 * @return string Link tag HTML.
 */
function amp_filter_font_style_loader_tag_with_crossorigin_anonymous( $tag, $handle, $href ) {
	static $allowed_font_src_regex = null;
	if ( ! $allowed_font_src_regex ) {
		$spec_name = 'link rel=stylesheet for fonts'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		foreach ( AMP_Allowed_Tags_Generated::get_allowed_tag( 'link' ) as $spec_rule ) {
			if ( isset( $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) && $spec_name === $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) {
				$allowed_font_src_regex = '@^(' . $spec_rule[ AMP_Rule_Spec::ATTR_SPEC_LIST ]['href']['value_regex'] . ')$@';
				break;
			}
		}
	}

	$href = preg_replace( '#^(http:)?(?=//)#', 'https:', $href );

	if ( preg_match( $allowed_font_src_regex, $href ) && false === strpos( $tag, 'crossorigin=' ) ) {
		$tag = preg_replace( '/(?<=<link\s)/', 'crossorigin="anonymous" ', $tag );
	}

	return $tag;
}

/**
 * Retrieve analytics data added in backend.
 *
 * @since 0.7
 * @internal
 *
 * @param array $analytics Analytics entries.
 * @return array Analytics.
 */
function amp_get_analytics( $analytics = [] ) {
	$analytics_entries = AMP_Options_Manager::get_option( Option::ANALYTICS, [] );

	/**
	 * Add amp-analytics tags.
	 *
	 * This filter allows you to easily insert any amp-analytics tags without needing much heavy lifting.
	 * This filter should be used to alter entries for transitional mode.
	 *
	 * @since 0.7
	 *
	 * @param array $analytics_entries An associative array of the analytics entries we want to output. Each array entry must have a unique key, and the value should be an array with the following keys: `type`, `attributes`, `config_data`. See readme for more details.
	 */
	$analytics_entries = apply_filters( 'amp_analytics_entries', $analytics_entries );

	if ( ! $analytics_entries ) {
		return $analytics;
	}

	foreach ( $analytics_entries as $entry_id => $entry ) {
		if ( ! isset( $entry['attributes'] ) ) {
			$entry['attributes'] = [];
		}
		if ( ! isset( $entry['config_data'] ) && isset( $entry['config'] ) && is_string( $entry['config'] ) ) {
			$entry['config_data'] = json_decode( $entry['config'] );
		}
		$analytics[ $entry_id ] = $entry;
	}

	return $analytics;
}

/**
 * Print analytics data.
 *
 * @since 0.7
 * @internal
 *
 * @param array|string $analytics Analytics entries, or empty string when called via wp_footer action.
 */
function amp_print_analytics( $analytics ) {
	if ( '' === $analytics ) {
		$analytics = [];
	}

	$analytics_entries = amp_get_analytics( $analytics );

	/**
	 * Triggers before analytics entries are printed as amp-analytics tags.
	 *
	 * This is useful for printing additional `amp-analytics` tags to the page without having to refactor any existing
	 * markup generation logic to use the data structure mutated by the `amp_analytics_entries` filter. For such cases,
	 * this action should be used for printing `amp-analytics` tags as opposed to using the `wp_footer` and
	 * `amp_post_template_footer` actions.
	 *
	 * @since 1.3
	 * @param array $analytics_entries Analytics entries, already potentially modified by the amp_analytics_entries filter.
	 */
	do_action( 'amp_print_analytics', $analytics_entries );

	if ( empty( $analytics_entries ) ) {
		return;
	}

	// Can enter multiple configs within backend.
	foreach ( $analytics_entries as $id => $analytics_entry ) {
		if ( ! isset( $analytics_entry['attributes'], $analytics_entry['config_data'] ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
					/* translators: 1: the analytics entry ID. 2: type. 3: attributes. 4: config_data. 5: comma-separated list of the actual entry keys. */
					esc_html__( 'Analytics entry for %1$s is missing one of the following keys: `%2$s` or `%3$s` (array keys: %4$s)', 'amp' ),
					esc_html( $id ),
					'attributes',
					'config_data',
					esc_html( implode( ', ', array_keys( $analytics_entry ) ) )
				),
				'0.3.2'
			);
			continue;
		}
		$script_element = AMP_HTML_Utils::build_tag(
			'script',
			[
				'type' => 'application/json',
			],
			wp_json_encode( $analytics_entry['config_data'] )
		);

		$amp_analytics_attr = array_merge(
			compact( 'id' ),
			$analytics_entry['attributes']
		);

		if ( ! empty( $analytics_entry['type'] ) ) {
			$amp_analytics_attr['type'] = $analytics_entry['type'];
		}

		echo AMP_HTML_Utils::build_tag( 'amp-analytics', $amp_analytics_attr, $script_element ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Get content embed handlers.
 *
 * @since 0.7
 * @internal
 *
 * @param WP_Post $post Post that the content belongs to. Deprecated when theme supports AMP, as embeds may apply
 *                      to non-post data (e.g. Text widget).
 * @return array Embed handlers.
 */
function amp_get_content_embed_handlers( $post = null ) {
	if ( ! amp_is_legacy() && $post ) {
		_deprecated_argument(
			__FUNCTION__,
			'0.7',
			sprintf(
				/* translators: %s: $post */
				esc_html__( 'The %s argument is deprecated when theme supports AMP.', 'amp' ),
				'$post'
			)
		);
		$post = null;
	}

	/**
	 * Filters the content embed handlers.
	 *
	 * @since 0.2
	 * @since 0.7 Deprecated $post parameter.
	 *
	 * @param array   $handlers Handlers.
	 * @param WP_Post $post     Post. Deprecated. It will be null when `amp_is_canonical()`.
	 */
	return apply_filters(
		'amp_content_embed_handlers',
		[
			'AMP_Core_Block_Handler'         => [],
			'AMP_Twitter_Embed_Handler'      => [],
			'AMP_YouTube_Embed_Handler'      => [],
			'AMP_Crowdsignal_Embed_Handler'  => [],
			'AMP_DailyMotion_Embed_Handler'  => [],
			'AMP_Vimeo_Embed_Handler'        => [],
			'AMP_SoundCloud_Embed_Handler'   => [],
			'AMP_Instagram_Embed_Handler'    => [],
			'AMP_Issuu_Embed_Handler'        => [],
			'AMP_Meetup_Embed_Handler'       => [],
			'AMP_Facebook_Embed_Handler'     => [],
			'AMP_Pinterest_Embed_Handler'    => [],
			'AMP_Playlist_Embed_Handler'     => [],
			'AMP_Reddit_Embed_Handler'       => [],
			'AMP_TikTok_Embed_Handler'       => [],
			'AMP_Tumblr_Embed_Handler'       => [],
			'AMP_Gallery_Embed_Handler'      => [],
			'AMP_Gfycat_Embed_Handler'       => [],
			'AMP_Imgur_Embed_Handler'        => [],
			'AMP_Scribd_Embed_Handler'       => [],
			'AMP_WordPress_TV_Embed_Handler' => [],
		],
		$post
	);
}

/**
 * Determine whether AMP dev mode is enabled.
 *
 * When enabled, the `<html>` element will get the data-ampdevmode attribute and the plugin will add the same attribute
 * to elements associated with the admin bar and other elements that are provided by the `amp_dev_mode_element_xpaths`
 * filter.
 *
 * @since 1.3
 *
 * @return bool Whether AMP dev mode is enabled.
 */
function amp_is_dev_mode() {

	/**
	 * Filters whether AMP mode is enabled.
	 *
	 * When enabled, the data-ampdevmode attribute will be added to the document element and it will allow the
	 * attributes to be added to the admin bar. It will also add the attribute to all elements which match the
	 * queries for the expressions returned by the 'amp_dev_mode_element_xpaths' filter.
	 *
	 * @since 1.3
	 * @param bool $is_dev_mode_enabled Whether AMP dev mode is enabled.
	 */
	return apply_filters(
		'amp_dev_mode_enabled',
		(
			// For the few sites that forcibly show the admin bar even when the user is logged out, only enable dev
			// mode if the user is actually logged in. This prevents the dev mode from being served to crawlers
			// when they index the AMP version. The theme support check disables dev mode in Reader mode.
			( is_admin_bar_showing() && is_user_logged_in() )
			||
			is_customize_preview()
		)
	);
}

/**
 * Get content sanitizers.
 *
 * @since 0.7
 * @since 1.1 Added AMP_Nav_Menu_Toggle_Sanitizer and AMP_Nav_Menu_Dropdown_Sanitizer.
 * @internal
 *
 * @param WP_Post $post Post that the content belongs to. Deprecated when theme supports AMP, as sanitizers apply
 *                      to non-post data (e.g. Text widget).
 * @return array Embed handlers.
 */
function amp_get_content_sanitizers( $post = null ) {
	$theme_support_args = AMP_Theme_Support::get_theme_support_args();

	if ( $post && ! amp_is_legacy() ) {
		_deprecated_argument(
			__FUNCTION__,
			'0.7',
			sprintf(
				/* translators: %s: $post */
				esc_html__( 'The %s argument is deprecated.', 'amp' ),
				'$post'
			)
		);
		$post = null;
	}

	$parsed_home_url = wp_parse_url( get_home_url() );
	$current_origin  = $parsed_home_url['scheme'] . '://' . $parsed_home_url['host'];
	if ( isset( $parsed_home_url['port'] ) ) {
		$current_origin .= ':' . $parsed_home_url['port'];
	}

	/**
	 * Filters whether AMP-to-AMP linking should be enabled.
	 *
	 * @since 1.4.0
	 * @param bool $amp_to_amp_linking_enabled Whether AMP-to-AMP linking should be enabled.
	 */
	$amp_to_amp_linking_enabled = (bool) apply_filters(
		'amp_to_amp_linking_enabled',
		AMP_Theme_Support::TRANSITIONAL_MODE_SLUG === AMP_Options_Manager::get_option( Option::THEME_SUPPORT )
	);

	$sanitizers = [
		'AMP_Embed_Sanitizer'             => [
			'amp_to_amp_linking_enabled' => $amp_to_amp_linking_enabled,
		],
		'AMP_Core_Theme_Sanitizer'        => [
			'template'       => get_template(),
			'stylesheet'     => get_stylesheet(),
			'theme_features' => [
				'force_svg_support' => [], // Always replace 'no-svg' class with 'svg' if it exists.
			],
		],
		'AMP_Srcset_Sanitizer'            => [],
		'AMP_Img_Sanitizer'               => [
			'align_wide_support' => current_theme_supports( 'align-wide' ),
		],
		'AMP_Form_Sanitizer'              => [],
		'AMP_Comments_Sanitizer'          => [
			'comments_live_list' => ! empty( $theme_support_args['comments_live_list'] ),
		],
		'AMP_Video_Sanitizer'             => [],
		'AMP_O2_Player_Sanitizer'         => [],
		'AMP_Audio_Sanitizer'             => [],
		'AMP_Playbuzz_Sanitizer'          => [],
		'AMP_Object_Sanitizer'            => [],
		'AMP_Iframe_Sanitizer'            => [
			'add_placeholder'    => true,
			'current_origin'     => $current_origin,
			'align_wide_support' => current_theme_supports( 'align-wide' ),
		],
		'AMP_Gallery_Block_Sanitizer'     => [ // Note: Gallery block sanitizer must come after image sanitizers since itś logic is using the already sanitized images.
			'carousel_required' => ! is_array( $theme_support_args ), // For back-compat.
		],
		'AMP_Block_Sanitizer'             => [], // Note: Block sanitizer must come after embed / media sanitizers since its logic is using the already sanitized content.
		'AMP_Script_Sanitizer'            => [],
		'AMP_Style_Sanitizer'             => [],
		'AMP_Meta_Sanitizer'              => [],
		'AMP_Layout_Sanitizer'            => [],
		'AMP_Accessibility_Sanitizer'     => [],
		'AMP_Tag_And_Attribute_Sanitizer' => [], // Note: This validating sanitizer must come at the end to clean up any remaining issues the other sanitizers didn't catch.
	];

	if ( ! empty( $theme_support_args['nav_menu_toggle'] ) ) {
		$sanitizers['AMP_Nav_Menu_Toggle_Sanitizer'] = $theme_support_args['nav_menu_toggle'];
	}

	if ( ! empty( $theme_support_args['nav_menu_dropdown'] ) ) {
		$sanitizers['AMP_Nav_Menu_Dropdown_Sanitizer'] = $theme_support_args['nav_menu_dropdown'];
	}

	if ( $amp_to_amp_linking_enabled && AMP_Theme_Support::STANDARD_MODE_SLUG !== AMP_Options_Manager::get_option( Option::THEME_SUPPORT ) ) {

		/**
		 * Filters the list of URLs which are excluded from being included in AMP-to-AMP linking.
		 *
		 * This only applies when the amp_to_amp_linking_enabled filter returns true,
		 * which it does by default in Transitional mode. This filter can be used to opt-in
		 * when in Reader mode. This does not apply in Standard mode.
		 * Only frontend URLs on the frontend need be excluded, as all other URLs are never made into AMP links.
		 *
		 * @since 1.5.0
		 *
		 * @param string[] $excluded_urls The URLs to exclude from having AMP-to-AMP links.
		 */
		$excluded_urls = apply_filters( 'amp_to_amp_excluded_urls', [] );

		$sanitizers['AMP_Link_Sanitizer'] = array_merge(
			[ 'paired' => ! amp_is_canonical() ],
			compact( 'excluded_urls' )
		);
	}

	/**
	 * Filters the content sanitizers.
	 *
	 * @since 0.2
	 * @since 0.7 Deprecated $post parameter. It will be null when `amp_is_canonical()`.
	 *
	 * @param array   $handlers Handlers.
	 * @param WP_Post $post     Post. Deprecated.
	 */
	$sanitizers = apply_filters( 'amp_content_sanitizers', $sanitizers, $post );

	if ( amp_is_dev_mode() ) {
		/**
		 * Filters the XPath queries for elements that should be enabled for dev mode.
		 *
		 * By supplying XPath queries to this filter, the data-ampdevmode attribute will automatically be added to the
		 * root HTML element as well as to any elements that match the expressions. The attribute is added to the
		 * elements prior to running any of the sanitizers.
		 *
		 * @since 1.3
		 * @param string[] $element_xpaths XPath element queries. Context is the root element.
		 */
		$dev_mode_xpaths = (array) apply_filters( 'amp_dev_mode_element_xpaths', [] );

		if ( is_admin_bar_showing() ) {
			$dev_mode_xpaths[] = '//*[ @id = "wpadminbar" ]';
			$dev_mode_xpaths[] = '//*[ @id = "wpadminbar" ]//*';
			$dev_mode_xpaths[] = '//style[ @id = "admin-bar-inline-css" ]';
		}

		if ( is_customize_preview() ) {
			// Scripts are always needed to inject changeset UUID.
			$dev_mode_xpaths[] = '//script[ @src ]';
			$dev_mode_xpaths[] = '//script[ not( @type ) or @type = "text/javascript" ]';

			// Style needed for Additional CSS to work as intended.
			$dev_mode_xpaths[] = '//style[ @id = "wp-custom-css" ]';

			// Styles needed for Colors customization.
			$dev_mode_xpaths[] = '//style[ @id = "custom-background-css" ]';
			$dev_mode_xpaths[] = '//style[ @id = "custom-theme-colors" ]';
		}

		$sanitizers = array_merge(
			[
				'AMP_Dev_Mode_Sanitizer' => [
					'element_xpaths' => $dev_mode_xpaths,
				],
			],
			$sanitizers
		);
	}

	/**
	 * Filters whether parsed CSS is allowed to be cached in transients.
	 *
	 * When this is filtered to be false, parsed CSS will not be stored in transients. This is important when there is
	 * highly-variable CSS content in order to prevent filling up the wp_options table with an endless number of entries.
	 *
	 * @since 1.5.0
	 * @param bool $transient_caching_allowed Transient caching allowed.
	 */
	$sanitizers['AMP_Style_Sanitizer']['allow_transient_caching'] = apply_filters( 'amp_parsed_css_transient_caching_allowed', true );

	// Force layout, style, meta, and validating sanitizers to be at the end.
	foreach ( [ 'AMP_Layout_Sanitizer', 'AMP_Style_Sanitizer', 'AMP_Meta_Sanitizer', 'AMP_Tag_And_Attribute_Sanitizer' ] as $class_name ) {
		if ( isset( $sanitizers[ $class_name ] ) ) {
			$sanitizer = $sanitizers[ $class_name ];
			unset( $sanitizers[ $class_name ] );
			$sanitizers[ $class_name ] = $sanitizer;
		}
	}

	return $sanitizers;
}

/**
 * Grabs featured image or the first attached image for the post.
 *
 * @since 0.7 This originally was located in the private method AMP_Post_Template::get_post_image_metadata().
 * @internal
 *
 * @param WP_Post|int $post Post or post ID.
 * @return array|false $post_image_meta Post image metadata, or false if not found.
 */
function amp_get_post_image_metadata( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}

	$post_image_meta = null;
	$post_image_id   = false;

	if ( has_post_thumbnail( $post->ID ) ) {
		$post_image_id = get_post_thumbnail_id( $post->ID );
	} elseif ( ( 'attachment' === $post->post_type ) && wp_attachment_is( 'image', $post ) ) {
		$post_image_id = $post->ID;
	} else {
		$attached_image_ids = get_posts(
			[
				'post_parent'      => $post->ID,
				'post_type'        => 'attachment',
				'post_mime_type'   => 'image',
				'posts_per_page'   => 1,
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'suppress_filters' => false,
			]
		);

		if ( ! empty( $attached_image_ids ) ) {
			$post_image_id = array_shift( $attached_image_ids );
		}
	}

	if ( ! $post_image_id ) {
		return false;
	}

	$post_image_src = wp_get_attachment_image_src( $post_image_id, 'full' );

	if ( is_array( $post_image_src ) ) {
		$post_image_meta = [
			'@type'  => 'ImageObject',
			'url'    => $post_image_src[0],
			'width'  => $post_image_src[1],
			'height' => $post_image_src[2],
		];
	}

	return $post_image_meta;
}

/**
 * Get the publisher logo.
 *
 * The following guidelines apply to logos used for general AMP pages.
 *
 * "The logo should be a rectangle, not a square. The logo should fit in a 60x600px rectangle.,
 * and either be exactly 60px high (preferred), or exactly 600px wide. For example, 450x45px
 * would not be acceptable, even though it fits in the 600x60px rectangle."
 *
 * @since 1.2.1
 * @link https://developers.google.com/search/docs/data-types/article#logo-guidelines
 * @internal
 *
 * @return string Publisher logo image URL. WordPress logo if no site icon or custom logo defined, and no logo provided via 'amp_site_icon_url' filter.
 */
function amp_get_publisher_logo() {
	$logo_image_url = null;

	/*
	 * This should be 60x600px rectangle. It *can* be larger than this, contrary to the current documentation.
	 * Only minimum size and ratio matters. So height should be at least 60px and width a minimum of 200px.
	 * An aspect ratio between 200/60 (10/3) and 600:60 (10/1) should be used. A square image still be used,
	 * but it is not preferred; a landscape logo should be provided if possible.
	 */
	$logo_width  = 600;
	$logo_height = 60;

	// Use the Custom Logo if set.
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	if ( has_custom_logo() && $custom_logo_id ) {
		$custom_logo_img = wp_get_attachment_image_src( $custom_logo_id, [ $logo_width, $logo_height ], false );
		if ( ! empty( $custom_logo_img[0] ) ) {
			$logo_image_url = $custom_logo_img[0];
		}
	}

	// Try Site Icon if a custom logo is not set.
	$site_icon_id = get_option( 'site_icon' );
	if ( empty( $logo_image_url ) && $site_icon_id ) {
		$site_icon_src = wp_get_attachment_image_src( $site_icon_id, [ $logo_width, $logo_height ], false );
		if ( ! empty( $site_icon_src ) ) {
			$logo_image_url = $site_icon_src[0];
		}
	}

	/**
	 * Filters the publisher logo URL in the schema.org data.
	 *
	 * Previously, this only filtered the Site Icon, as that was the only possible schema.org publisher logo.
	 * But the Custom Logo is now the preferred publisher logo, if it exists and its dimensions aren't too big.
	 *
	 * @since 0.3
	 *
	 * @param string $schema_img_url URL of the publisher logo, either the Custom Logo or the Site Icon.
	 */
	$logo_image_url = apply_filters( 'amp_site_icon_url', $logo_image_url );

	// Fallback to serving the WordPress logo.
	if ( empty( $logo_image_url ) ) {
		$logo_image_url = amp_get_asset_url( 'images/amp-page-fallback-wordpress-publisher-logo.png' );
	}

	return $logo_image_url;
}

/**
 * Get schema.org metadata for the current query.
 *
 * @since 0.7
 * @see AMP_Post_Template::build_post_data() Where the logic in this function originally existed.
 * @internal
 *
 * @return array $metadata All schema.org metadata for the post.
 */
function amp_get_schemaorg_metadata() {
	$metadata = [
		'@context'  => 'http://schema.org',
		'publisher' => [
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		],
	];

	$publisher_logo = amp_get_publisher_logo();
	if ( $publisher_logo ) {
		$metadata['publisher']['logo'] = [
			'@type' => 'ImageObject',
			'url'   => $publisher_logo,
		];
	}

	$queried_object = get_queried_object();
	if ( $queried_object instanceof WP_Post ) {
		if ( version_compare( strtok( get_bloginfo( 'version' ), '-' ), '5.3', '>=' ) ) {
			$date_published = mysql2date( 'c', $queried_object->post_date, false );
			$date_modified  = mysql2date( 'c', $queried_object->post_modified, false );
		} else {
			$date_published = mysql2date( 'c', $queried_object->post_date_gmt, false );
			$date_modified  = mysql2date( 'c', $queried_object->post_modified_gmt, false );
		}

		$metadata = array_merge(
			$metadata,
			[
				'@type'            => is_page() ? 'WebPage' : 'BlogPosting',
				'mainEntityOfPage' => get_permalink(),
				'headline'         => get_the_title(),
				'datePublished'    => $date_published,
				'dateModified'     => $date_modified,
			]
		);

		$post_author = get_userdata( $queried_object->post_author );
		if ( $post_author ) {
			$metadata['author'] = [
				'@type' => 'Person',
				'name'  => html_entity_decode( $post_author->display_name, ENT_QUOTES, get_bloginfo( 'charset' ) ),
			];
		}

		$image_metadata = amp_get_post_image_metadata( $queried_object );
		if ( $image_metadata ) {
			$metadata['image'] = $image_metadata['url'];
		}

		/**
		 * Filters Schema.org metadata for a post.
		 *
		 * The 'post_template' in the filter name here is due to this filter originally being introduced in `AMP_Post_Template`.
		 * In general the `amp_schemaorg_metadata` filter should be used instead.
		 *
		 * @since 0.3
		 *
		 * @param array   $metadata       Metadata.
		 * @param WP_Post $queried_object Post.
		 */
		$metadata = apply_filters( 'amp_post_template_metadata', $metadata, $queried_object );
	} elseif ( is_archive() ) {
		$metadata['@type'] = 'CollectionPage';
	}

	/**
	 * Filters Schema.org metadata for a query.
	 *
	 * Check the the main query for the context for which metadata should be added.
	 *
	 * @since 0.7
	 *
	 * @param array   $metadata Metadata.
	 */
	$metadata = apply_filters( 'amp_schemaorg_metadata', $metadata );

	return $metadata;
}

/**
 * Output schema.org metadata.
 *
 * @since 0.7
 * @since 1.1 we pass `JSON_UNESCAPED_UNICODE` to `wp_json_encode`.
 * @see https://github.com/ampproject/amp-wp/issues/1969
 * @internal
 */
function amp_print_schemaorg_metadata() {
	$metadata = amp_get_schemaorg_metadata();
	if ( empty( $metadata ) ) {
		return;
	}
	?>
	<script type="application/ld+json"><?php echo wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE ); ?></script>
	<?php
}

/**
 * Filters content and keeps only allowable HTML elements by amp-mustache.
 *
 * @see wp_kses()
 * @since 1.0
 * @internal
 *
 * @param string $markup Markup to sanitize.
 * @return string HTML markup with tags allowed by amp-mustache.
 */
function amp_wp_kses_mustache( $markup ) {
	$amp_mustache_allowed_html_tags = [ 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'mark', 'del', 'ins', 'sup', 'sub' ];
	return wp_kses( $markup, array_fill_keys( $amp_mustache_allowed_html_tags, [] ) );
}

/**
 * Add "View AMP" admin bar item for Transitional/Reader mode.
 *
 * Note that when theme support is present (in Native/Transitional modes), the admin bar item will be further amended by
 * the `AMP_Validation_Manager::add_admin_bar_menu_items()` method.
 *
 * @see \AMP_Validation_Manager::add_admin_bar_menu_items()
 * @internal
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar.
 */
function amp_add_admin_bar_view_link( $wp_admin_bar ) {
	if ( is_admin() || amp_is_canonical() || ! amp_is_available() ) {
		return;
	}

	$is_amp_request = amp_is_request();

	$current_url = remove_query_arg( array_merge( wp_removable_query_args(), [ QueryVar::NOAMP ] ), amp_get_current_url() );
	if ( $is_amp_request ) {
		$amp_url     = $current_url;
		$non_amp_url = amp_remove_paired_endpoint( $current_url );
	} else {
		$amp_url     = amp_add_paired_endpoint( $current_url );
		$non_amp_url = $current_url;
	}

	$icon = $is_amp_request ? Icon::logo() : Icon::link();
	$attr = [
		'id'    => 'amp-admin-bar-item-status-icon',
		'class' => 'ab-icon',
	];

	$non_amp_view_title = __( 'View non-AMP version', 'amp' );
	$amp_view_title     = __( 'View AMP version', 'amp' );

	$wp_admin_bar->add_node(
		[
			'id'    => 'amp',
			'title' => $icon->to_html( $attr ) . ' ' . esc_html__( 'AMP', 'amp' ),
			'href'  => esc_url( $is_amp_request ? $non_amp_url : $amp_url ),
			'meta'  => [
				'title' => esc_attr( $is_amp_request ? $non_amp_view_title : $amp_view_title ),
			],
		]
	);

	$wp_admin_bar->add_node(
		[
			'parent' => 'amp',
			'id'     => 'amp-view',
			'title'  => esc_html( $is_amp_request ? $non_amp_view_title : $amp_view_title ),
			'href'   => esc_url( $is_amp_request ? $non_amp_url : $amp_url ),
		]
	);

	// Make sure the Customizer opens with AMP enabled.
	$customize_node = $wp_admin_bar->get_node( 'customize' );
	if ( $customize_node && $is_amp_request && AMP_Theme_Support::READER_MODE_SLUG === AMP_Options_Manager::get_option( Option::THEME_SUPPORT ) ) {
		$args = get_object_vars( $customize_node );
		if ( amp_is_legacy() ) {
			$args['href'] = add_query_arg( 'autofocus[panel]', AMP_Template_Customizer::PANEL_ID, $args['href'] );
		} else {
			$args['href'] = add_query_arg( amp_get_slug(), '1', $args['href'] );
		}
		$wp_admin_bar->add_node( $args );
	}
}

/**
 * Generate hash for inline amp-script.
 *
 * The sha384 hash used by amp-script is represented not as hexadecimal but as base64url, which is defined in RFC 4648
 * under section 5, "Base 64 Encoding with URL and Filename Safe Alphabet". It is sometimes referred to as "web safe".
 *
 * @since 1.4.0
 * @link https://amp.dev/documentation/components/amp-script/#security-features
 * @link https://github.com/ampproject/amphtml/blob/e8707858895c2af25903af25d396e144e64690ba/extensions/amp-script/0.1/amp-script.js#L401-L425
 * @link https://github.com/ampproject/amphtml/blob/27b46b9c8c0fb3711a00376668d808f413d798ed/src/service/crypto-impl.js#L67-L124
 * @link https://github.com/ampproject/amphtml/blob/c4a663d0ba13d0488c6fe73c55dc8c971ac6ec0d/src/utils/base64.js#L52-L61
 * @link https://tools.ietf.org/html/rfc4648#section-5
 *
 * @param string $script Script.
 * @return string|null Script hash or null if the sha384 algorithm is not supported.
 */
function amp_generate_script_hash( $script ) {
	$sha384 = hash( 'sha384', $script, true );
	if ( false === $sha384 ) {
		return null;
	}
	$hash = str_replace(
		[ '+', '/', '=' ],
		[ '-', '_', '.' ],
		base64_encode( $sha384 ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	);
	return 'sha384-' . $hash;
}

/**
 * Turn a given URL into a paired AMP URL.
 *
 * @since 2.1
 *
 * @param string $url URL.
 * @return string AMP URL.
 */
function amp_add_paired_endpoint( $url ) {
	try {
		return Services::get( 'paired_routing' )->add_endpoint( $url );
	} catch ( InvalidService $e ) {
		if ( ! amp_is_enabled() ) {
			$reason = __( 'Function called while AMP is disabled via `amp_is_enabled` filter.', 'amp' );
		} else {
			$reason = __( 'Function cannot be called before services are registered.', 'amp' );
		}
		_doing_it_wrong( __FUNCTION__, esc_html( $reason ) . ' ' . esc_html( $e->getMessage() ), '2.1.1' );
		return $url;
	}
}

/**
 * Determine a given URL is for a paired AMP request.
 *
 * @since 2.1
 *
 * @param string $url URL to examine. If empty, will use the current URL.
 * @return bool True if the AMP query parameter is set with the required value, false if not.
 */
function amp_has_paired_endpoint( $url = '' ) {
	try {
		return Services::get( 'paired_routing' )->has_endpoint( $url );
	} catch ( InvalidService $e ) {
		if ( ! amp_is_enabled() ) {
			$reason = __( 'Function called while AMP is disabled via `amp_is_enabled` filter.', 'amp' );
		} else {
			$reason = __( 'Function cannot be called before services are registered.', 'amp' );
		}
		_doing_it_wrong( __FUNCTION__, esc_html( $reason ) . ' ' . esc_html( $e->getMessage() ), '2.1.1' );
		return false;
	}
}

/**
 * Remove the paired AMP endpoint from a given URL.
 *
 * @since 2.1
 *
 * @param string $url URL.
 * @return string URL with AMP stripped.
 */
function amp_remove_paired_endpoint( $url ) {
	try {
		return Services::get( 'paired_routing' )->remove_endpoint( $url );
	} catch ( InvalidService $e ) {
		if ( ! amp_is_enabled() ) {
			$reason = __( 'Function called while AMP is disabled via `amp_is_enabled` filter.', 'amp' );
		} else {
			$reason = __( 'Function cannot be called before services are registered.', 'amp' );
		}
		_doing_it_wrong( __FUNCTION__, esc_html( $reason ) . ' ' . esc_html( $e->getMessage() ), '2.1.1' );
		return $url;
	}
}

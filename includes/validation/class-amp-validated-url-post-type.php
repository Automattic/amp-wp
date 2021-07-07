<?php
/**
 * Class AMP_Validated_URL_Post_Type
 *
 * @package AMP
 */

use AmpProject\AmpWP\Admin\OptionsMenu;
use AmpProject\AmpWP\Admin\ValidationCounts;
use AmpProject\AmpWP\Icon;
use AmpProject\AmpWP\Option;
use AmpProject\AmpWP\QueryVar;
use AmpProject\AmpWP\Services;

/**
 * Class AMP_Validated_URL_Post_Type
 *
 * @since 1.0
 * @internal
 */
class AMP_Validated_URL_Post_Type {

	/**
	 * The slug of the post type to store URLs that have AMP errors.
	 *
	 * @var string
	 */
	const POST_TYPE_SLUG = 'amp_validated_url';

	/**
	 * Postmeta key for storing stylesheet data.
	 *
	 * @var string
	 */
	const STYLESHEETS_POST_META_KEY = '_amp_stylesheets';

	/**
	 * Postmeta key for storing queried object data.
	 *
	 * @var string
	 */
	const QUERIED_OBJECT_POST_META_KEY = '_amp_queried_object';

	/**
	 * Postmeta key for storing validated environment data.
	 *
	 * @var string
	 */
	const VALIDATED_ENVIRONMENT_POST_META_KEY = '_amp_validated_environment';

	/**
	 * Postmeta key for storing PHP fatal error data.
	 *
	 * @var string
	 */
	const PHP_FATAL_ERROR_POST_META_KEY = '_amp_php_fatal_error';

	/**
	 * The action to recheck URLs for AMP validity.
	 *
	 * @var string
	 */
	const VALIDATE_ACTION = 'amp_validate';

	/**
	 * The action to bulk recheck URLs for AMP validity.
	 *
	 * @var string
	 */
	const BULK_VALIDATE_ACTION = 'amp_bulk_validate';

	/**
	 * Action to update the status of AMP validation errors.
	 *
	 * @var string
	 */
	const UPDATE_POST_TERM_STATUS_ACTION = 'amp_update_validation_error_status';

	/**
	 * The query arg for whether there are remaining errors after rechecking URLs.
	 *
	 * @var string
	 */
	const REMAINING_ERRORS = 'amp_remaining_errors';

	/**
	 * The handle for the post edit screen script.
	 *
	 * @var string
	 */
	const EDIT_POST_SCRIPT_HANDLE = 'amp-validated-url-post-edit-screen';

	/**
	 * The handle for the AMP validated URL page script.
	 *
	 * @var string
	 */
	const AMP_VALIDATED_URL_PAGE_SCRIPT_HANDLE = 'amp-validated-url-page';

	/**
	 * HTML ID for the app root element.
	 *
	 * @var string
	 */
	const AMP_VALIDATED_URL_PAGE_APP_ROOT_ID = 'amp-validated-url-root';

	/**
	 * The query arg for the number of URLs tested.
	 *
	 * @var string
	 */
	const URLS_TESTED = 'amp_urls_tested';

	/**
	 * The nonce action for rechecking a URL.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'amp_recheck_';

	/**
	 * The name of the side meta box on the CPT post.php page.
	 *
	 * @var string
	 */
	const STATUS_META_BOX = 'amp_validation_status';

	/**
	 * The transient key to use for caching the number of URLs with new validation errors.
	 *
	 * @var string
	 */
	const NEW_VALIDATION_ERROR_URLS_COUNT_TRANSIENT = 'amp_new_validation_error_urls_count';

	/**
	 * The name of the input that captures the current state of validation errors.
	 *
	 * @var string
	 */
	const VALIDATION_ERRORS_INPUT_KEY = 'validation_errors';

	/**
	 * The total number of errors associated with a URL, regardless of the maximum that can display.
	 *
	 * @var int
	 */
	public static $total_errors_for_url;

	/**
	 * Registers the post type to store URLs with validation errors.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'amp_plugin_update', [ __CLASS__, 'handle_plugin_update' ] );

		$dev_tools_user_access = Services::get( 'dev_tools.user_access' );

		// Show in the admin menu if dev tools are enabled for the user or if the user is on any dev tools screen.
		$show_in_menu = (
			$dev_tools_user_access->is_user_enabled()
			||
			( isset( $_GET['post_type'] ) && self::POST_TYPE_SLUG === $_GET['post_type'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			||
			( isset( $_GET['post'], $_GET['action'] ) && 'edit' === $_GET['action'] && self::POST_TYPE_SLUG === get_post_type( (int) $_GET['post'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			||
			( isset( $_GET['taxonomy'] ) && AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG === $_GET['taxonomy'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		if ( $show_in_menu && current_user_can( 'manage_options' ) ) {
			$show_in_menu = AMP_Options_Manager::OPTION_NAME;
		}

		register_post_type(
			self::POST_TYPE_SLUG,
			[
				'labels'       => [
					'all_items'          => __( 'All Validated URLs', 'amp' ),
					'name'               => _x( 'AMP Validated URLs', 'post type general name', 'amp' ),
					'menu_name'          => __( 'Validated URLs', 'amp' ),
					'singular_name'      => __( 'Validated URL', 'amp' ),
					'not_found'          => __( 'No validated URLs found', 'amp' ),
					'not_found_in_trash' => __( 'No forgotten validated URLs', 'amp' ),
					'search_items'       => __( 'Search validated URLs', 'amp' ),
					'edit_item'          => '', // Overwritten in JS, so this prevents the page header from appearing and changing.
				],
				'supports'     => false,
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => $show_in_menu,
				'map_meta_cap' => false,
				'capabilities' => array_merge(
					array_fill_keys(
						[
							'edit_post',
							'read_post',
							'delete_post',
							'edit_posts',
							'edit_others_posts',
							'delete_posts',
							'publish_posts',
							'read_private_posts',
						],
						AMP_Validation_Manager::VALIDATE_CAPABILITY
					),
					[
						// Hide the add new post link, as new posts are created programmatically.
						'create_posts' => 'do_not_allow',
					]
				),
				// @todo Show in rest.
			]
		);

		if ( $show_in_menu ) {
			add_action( 'admin_menu', [ __CLASS__, 'update_validated_url_menu_item' ] );
		}

		// Rename the top-level menu from "Validated URLs" to "AMP DevTools" when the user does not have access to the AMP settings screen.
		if ( $show_in_menu && ! current_user_can( 'manage_options' ) ) {
			add_action(
				'admin_menu',
				static function () {
					global $menu;
					foreach ( $menu as &$menu_item ) {
						if ( 'edit.php?post_type=' . self::POST_TYPE_SLUG === $menu_item[2] ) {
							$menu_item[0] = esc_html__( 'AMP DevTools', 'amp' );
							$menu_item[6] = OptionsMenu::ICON_BASE64_SVG;
							break;
						}
					}
				}
			);
		}

		// Ensure cached count of URLs with new validation errors is flushed whenever a URL is updated, trashed, or deleted.
		$handle_delete = static function ( $post_id ) {
			if ( static::POST_TYPE_SLUG === get_post_type( $post_id ) ) {
				delete_transient( static::NEW_VALIDATION_ERROR_URLS_COUNT_TRANSIENT );
				delete_transient( AMP_Validation_Error_Taxonomy::TRANSIENT_KEY_ERROR_INDEX_COUNTS );
			}
		};
		add_action( 'save_post_' . self::POST_TYPE_SLUG, $handle_delete );
		add_action( 'trash_post', $handle_delete );
		add_action( 'delete_post', $handle_delete );

		if ( is_admin() ) {
			self::add_admin_hooks();
		}
	}

	/**
	 * Handle update to plugin.
	 *
	 * @param string $old_version Old version.
	 */
	public static function handle_plugin_update( $old_version ) {

		// Update the old post type slug from amp_invalid_url to amp_validated_url.
		if ( '1.0-' === substr( $old_version, 0, 4 ) || version_compare( $old_version, '1.0', '<' ) ) {
			global $wpdb;
			$post_ids = get_posts(
				[
					'post_type'      => 'amp_invalid_url',
					'fields'         => 'ids',
					'posts_per_page' => -1,
				]
			);
			foreach ( $post_ids as $post_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->update(
					$wpdb->posts,
					[ 'post_type' => self::POST_TYPE_SLUG ],
					[ 'ID' => $post_id ]
				);
				clean_post_cache( $post_id );
			}
		}
	}

	/**
	 * Add admin hooks.
	 */
	public static function add_admin_hooks() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_post_list_screen_scripts' ] );

		// Edit post screen hooks.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_edit_post_screen_scripts' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ], PHP_INT_MAX );
		add_action( 'edit_form_after_title', [ __CLASS__, 'render_single_url_list_table' ] );
		add_filter( 'edit_' . AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG . '_per_page', [ __CLASS__, 'get_terms_per_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'add_taxonomy' ] );
		add_action( 'edit_form_top', [ __CLASS__, 'print_url_as_title' ] );

		// Post list screen hooks.
		add_filter(
			'view_mode_post_types',
			static function( $post_types ) {
				return array_diff( $post_types, [ AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ] );
			}
		);
		add_action(
			'load-edit.php',
			static function() {
				if ( 'edit-' . AMP_Validated_URL_Post_Type::POST_TYPE_SLUG !== get_current_screen()->id ) {
					return;
				}
				add_action(
					'admin_head-edit.php',
					static function() {
						global $mode;
						$mode = 'list'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					}
				);
			}
		);
		add_action( 'admin_notices', [ __CLASS__, 'render_link_to_error_index_screen' ] );
		add_filter( 'the_title', [ __CLASS__, 'filter_the_title_in_post_list_table' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'render_post_filters' ], 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE_SLUG . '_posts_columns', [ __CLASS__, 'add_post_columns' ] );
		add_filter( 'manage_' . self::POST_TYPE_SLUG . '_columns', [ __CLASS__, 'add_single_post_columns' ] );
		add_action( 'manage_posts_custom_column', [ __CLASS__, 'output_custom_column' ], 10, 2 );
		add_filter( 'bulk_actions-edit-' . self::POST_TYPE_SLUG, [ __CLASS__, 'filter_bulk_actions' ], 10, 2 );
		add_filter( 'bulk_actions-' . self::POST_TYPE_SLUG, '__return_false' );
		add_filter( 'handle_bulk_actions-edit-' . self::POST_TYPE_SLUG, [ __CLASS__, 'handle_bulk_action' ], 10, 3 );
		add_action( 'admin_notices', [ __CLASS__, 'print_admin_notice' ] );
		add_action( 'admin_action_' . self::VALIDATE_ACTION, [ __CLASS__, 'handle_validate_request' ] );
		add_action( 'post_action_' . self::UPDATE_POST_TERM_STATUS_ACTION, [ __CLASS__, 'handle_validation_error_status_update' ] );
		add_filter( 'post_row_actions', [ __CLASS__, 'filter_post_row_actions' ], PHP_INT_MAX, 2 );
		add_filter( sprintf( 'views_edit-%s', self::POST_TYPE_SLUG ), [ __CLASS__, 'filter_table_views' ] );
		add_filter( 'bulk_post_updated_messages', [ __CLASS__, 'filter_bulk_post_updated_messages' ], 10, 2 );
		add_filter( 'admin_title', [ __CLASS__, 'filter_admin_title' ] );

		// Hide irrelevant "published" label in the AMP Validated URLs post list.
		add_filter(
			'post_date_column_status',
			static function ( $status, $post ) {
				if ( AMP_Validated_URL_Post_Type::POST_TYPE_SLUG === get_post_type( $post ) ) {
					$status = '';
				}

				return $status;
			},
			10,
			2
		);

		// Prevent query vars from persisting after redirect.
		add_filter(
			'removable_query_args',
			static function ( $query_vars ) {
				$query_vars[] = 'amp_actioned';
				$query_vars[] = 'amp_taxonomy_terms_updated';
				$query_vars[] = AMP_Validated_URL_Post_Type::REMAINING_ERRORS;
				$query_vars[] = 'amp_urls_tested';
				$query_vars[] = 'amp_validate_error';

				return $query_vars;
			}
		);
	}

	/**
	 * Enqueue style.
	 */
	public static function enqueue_post_list_screen_scripts() {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen ) {
			return;
		}

		// Enqueue this on both the 'AMP Validated URLs' page and the single URL page.
		if ( 'edit-' . self::POST_TYPE_SLUG === $screen->id || self::POST_TYPE_SLUG === $screen->id ) {
			wp_enqueue_style(
				'amp-admin-tables',
				amp_get_asset_url( 'css/admin-tables.css' ),
				[ 'amp-icons' ],
				AMP__VERSION
			);

			wp_styles()->add_data( 'amp-admin-tables', 'rtl', 'replace' );
		}

		if ( 'edit-' . self::POST_TYPE_SLUG !== $screen->id ) {
			return;
		}

		wp_register_style(
			'amp-validation-tooltips',
			amp_get_asset_url( 'css/amp-validation-tooltips.css' ),
			[ 'wp-pointer' ],
			AMP__VERSION
		);

		wp_styles()->add_data( 'amp-validation-tooltips', 'rtl', 'replace' );

		$asset_file   = AMP__DIR__ . '/assets/js/amp-validation-tooltips.asset.php';
		$asset        = require $asset_file;
		$dependencies = $asset['dependencies'];
		$version      = $asset['version'];

		wp_register_script(
			'amp-validation-tooltips',
			amp_get_asset_url( 'js/amp-validation-tooltips.js' ),
			$dependencies,
			$version,
			true
		);

		wp_enqueue_style(
			'amp-validation-error-taxonomy',
			amp_get_asset_url( 'css/amp-validation-error-taxonomy.css' ),
			[ 'common', 'amp-validation-tooltips', 'amp-icons' ],
			AMP__VERSION
		);

		wp_styles()->add_data( 'amp-validation-error-taxonomy', 'rtl', 'replace' );

		wp_enqueue_script(
			'amp-validation-detail-toggle',
			amp_get_asset_url( 'js/amp-validation-detail-toggle.js' ),
			[ 'wp-dom-ready', 'wp-i18n', 'amp-validation-tooltips' ],
			AMP__VERSION,
			true
		);
	}

	/**
	 * On the 'AMP Validated URLs' screen, renders a link to the 'Error Index' page.
	 *
	 * @see AMP_Validation_Error_Taxonomy::render_link_to_invalid_urls_screen()
	 */
	public static function render_link_to_error_index_screen() {
		if ( ! ( get_current_screen() && 'edit' === get_current_screen()->base && self::POST_TYPE_SLUG === get_current_screen()->post_type ) ) {
			return;
		}

		$taxonomy_object = get_taxonomy( AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG );
		if ( ! current_user_can( $taxonomy_object->cap->manage_terms ) ) {
			return;
		}

		$id = 'link-errors-index';

		printf(
			'<a href="%s" hidden class="page-title-action" id="%s" style="margin-left: 1rem;">%s</a>',
			esc_url( get_admin_url( null, 'edit-tags.php?taxonomy=' . AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG . '&post_type=' . self::POST_TYPE_SLUG ) ),
			esc_attr( $id ),
			esc_html__( 'View Error Index', 'amp' )
		);

		?>
		<script>
			jQuery( function( $ ) {
				// Move the link to after the heading, as it also looks like there's no action for this.
				$( <?php echo wp_json_encode( '#' . $id ); ?> ).removeAttr( 'hidden' ).insertAfter( $( '.wp-heading-inline' ) );
			} );
		</script>
		<?php
	}

	/**
	 * Update the "Validated URLs" menu item label and append a count of how many validation error posts there are
	 * next to it.
	 *
	 * @global array $submenu
	 */
	public static function update_validated_url_menu_item() {
		global $submenu;

		$post_type_menu_slug = 'edit.php?post_type=' . self::POST_TYPE_SLUG;
		$parent_menu_slug    = current_user_can( 'manage_options' ) ? AMP_Options_Manager::OPTION_NAME : $post_type_menu_slug;

		if ( ! isset( $submenu[ $parent_menu_slug ] ) ) {
			return;
		}

		foreach ( $submenu[ $parent_menu_slug ] as &$submenu_item ) {
			if ( $post_type_menu_slug === $submenu_item[2] ) {
				// Use the `menu_name` label as the submenu label instead of the `all_items` label.
				$menu_name_label = get_post_type_object( self::POST_TYPE_SLUG )->labels->menu_name;
				$submenu_item[0] = $menu_name_label;

				if ( ValidationCounts::is_needed() ) {
					// Append markup to display a loading spinner while the unreviewed count is being fetched.
					$submenu_item[0] .= ' <span id="new-validation-url-count" class="awaiting-mod"><span class="amp-count-loading"></span></span>';
				}

				break;
			}
		}
	}

	/**
	 * Get the count of URLs that have new validation errors.
	 *
	 * @since 1.3
	 *
	 * @return int Count of new validation error URLs.
	 */
	public static function get_validation_error_urls_count() {
		$count = get_transient( static::NEW_VALIDATION_ERROR_URLS_COUNT_TRANSIENT );
		if ( false !== $count ) {
			// Handle case where integer stored in transient gets returned as string when persistent object cache is not
			// used. This is due to wp_options.option_value being a string.
			return (int) $count;
		}

		// Make sure filter is added in REST API context which is otherwise only added via AMP_Validation_Error_Taxonomy::add_admin_hooks().
		$callback   = [ AMP_Validation_Error_Taxonomy::class, 'filter_posts_where_for_validation_error_status' ];
		$priority   = 10;
		$has_filter = has_filter( 'posts_where', $callback );
		if ( false === $has_filter ) {
			add_filter( 'posts_where', $callback, $priority, 2 );
		}

		$query = new WP_Query(
			[
				'post_type'              => self::POST_TYPE_SLUG,
				AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_STATUS_QUERY_VAR => [
					AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
					AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
				],
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		// Remove filter if we added it in this method.
		if ( false === $has_filter ) {
			remove_filter( 'posts_where', $callback, $priority );
		}

		$count = $query->found_posts;

		set_transient( static::NEW_VALIDATION_ERROR_URLS_COUNT_TRANSIENT, $count, DAY_IN_SECONDS );

		return $count;
	}

	/**
	 * Gets validation errors for a given validated URL post.
	 *
	 * @param string|int|WP_Post $url Either the URL string or a post (ID or WP_Post) of amp_validated_url type.
	 * @param array              $args {
	 *     Args.
	 *
	 *     @type bool $ignore_accepted Exclude validation errors that are accepted (invalid markup removed). Default false.
	 * }
	 * @return array List of errors, with keys for term, data, status, and (sanitization) forced.
	 */
	public static function get_invalid_url_validation_errors( $url, $args = [] ) {
		$args = array_merge(
			[
				'ignore_accepted' => false,
			],
			$args
		);

		// Look up post by URL or ensure the amp_validated_url object.
		if ( is_string( $url ) ) {
			$post = self::get_invalid_url_post( $url );
		} else {
			$post = get_post( $url );
		}
		if ( ! $post || self::POST_TYPE_SLUG !== $post->post_type ) {
			return [];
		}

		// Skip when parse error.
		$stored_validation_errors = json_decode( $post->post_content, true );
		if ( ! is_array( $stored_validation_errors ) ) {
			return [];
		}

		$errors = [];
		foreach ( $stored_validation_errors as $stored_validation_error ) {
			if ( ! isset( $stored_validation_error['term_slug'] ) ) {
				continue;
			}

			$term = AMP_Validation_Error_Taxonomy::get_term( $stored_validation_error['term_slug'] );
			if ( ! $term ) {
				continue;
			}

			$sanitization = AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $stored_validation_error['data'] );
			if ( $args['ignore_accepted'] && ( AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS === $sanitization['status'] || AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS === $sanitization['status'] ) ) {
				continue;
			}

			$errors[] = array_merge(
				[
					'term' => $term,
					'data' => $stored_validation_error['data'],
				],
				$sanitization
			);
		}
		return $errors;
	}

	/**
	 * Display summary of the validation error counts for a given post.
	 *
	 * @param int|WP_Post $post Post of amp_validated_url type.
	 */
	public static function display_invalid_url_validation_error_counts_summary( $post ) {
		$validation_errors = self::get_invalid_url_validation_errors( $post );
		$counts            = self::count_invalid_url_validation_errors( $validation_errors );

		$removed_count = ( $counts['new_accepted'] + $counts['ack_accepted'] );
		$kept_count    = ( $counts['new_rejected'] + $counts['ack_rejected'] );

		$result = [];

		if ( $kept_count ) {
			$title = '';
			if ( $counts['new_rejected'] > 0 && $counts['ack_rejected'] > 0 ) {
				$title = sprintf(
					/* translators: %s is the count of new validation errors */
					_n(
						'%s validation error with kept markup is new',
						'%s validation errors with kept markup are new',
						$counts['new_rejected'],
						'amp'
					),
					$counts['new_rejected']
				);
			}
			$result[] = sprintf(
				'<span class="status-text %s" title="%s">%s %s: %s</span>',
				esc_attr( $counts['new_rejected'] > 0 ? 'has-new' : '' ),
				esc_attr( $title ),
				Icon::invalid()->to_html(),
				esc_html__( 'Invalid markup kept', 'amp' ),
				number_format_i18n( $kept_count )
			);
		}
		if ( $removed_count ) {
			$title = '';
			if ( $counts['new_accepted'] > 0 && $counts['ack_accepted'] > 0 ) {
				$title = sprintf(
					/* translators: %s is the count of new validation errors */
					_n(
						'%s validation error with removed markup is new',
						'%s validation errors with removed markup are new',
						$counts['new_rejected'],
						'amp'
					),
					$counts['new_accepted']
				);
			}
			$icon     = ( $counts['new_accepted'] + $counts['new_rejected'] ) > 0 ? Icon::removed() : Icon::valid();
			$result[] = sprintf(
				'<span class="status-text %s" title="%s">%s %s: %s</span>',
				esc_attr( $counts['new_accepted'] > 0 ? 'has-new' : '' ),
				esc_attr( $title ),
				$icon->to_html(),
				esc_html__( 'Invalid markup removed', 'amp' ),
				number_format_i18n( $removed_count )
			);
		}
		if ( 0 === $removed_count && 0 === $kept_count ) {
			$result[] = sprintf(
				'<span class="status-text">%s %s</span>',
				Icon::valid()->to_html(),
				esc_html__( 'All markup valid', 'amp' )
			);
		}

		echo implode( '', $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		printf( '<input class="amp-validation-error-new" type="hidden" value="%d">', (int) ( $counts['new_accepted'] + $counts['new_rejected'] > 0 ) );
	}

	/**
	 * Gets the existing custom post that stores errors for the $url, if it exists.
	 *
	 * @param string $url     The (in)valid URL.
	 * @param array  $options {
	 *     Options.
	 *
	 *     @type bool $normalize       Whether to normalize the URL.
	 *     @type bool $include_trashed Include trashed.
	 * }
	 * @return WP_Post|null The post of the existing custom post, or null.
	 */
	public static function get_invalid_url_post( $url, $options = [] ) {
		$default = [
			'normalize'       => true,
			'include_trashed' => false,
		];
		$options = wp_parse_args( $options, $default );

		if ( $options['normalize'] ) {
			$url = self::normalize_url_for_storage( $url );
		}
		$slug = md5( $url );

		$post = get_page_by_path( $slug, OBJECT, self::POST_TYPE_SLUG );
		if ( $post ) {
			return $post;
		}

		if ( $options['include_trashed'] ) {
			$post = get_page_by_path( $slug . '__trashed', OBJECT, self::POST_TYPE_SLUG );
			if ( $post ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Get the URL from a given amp_validated_url post.
	 *
	 * The URL will be returned with the amp query var added to it if the site is not canonical. The post_title
	 * is always stored using the canonical AMP-less URL.
	 *
	 * @param int|WP_Post $post Post.
	 * @return string|null The URL stored for the post or null if post does not exist or it is not the right type.
	 */
	public static function get_url_from_post( $post ) {
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE_SLUG !== $post->post_type ) {
			return null;
		}
		$url = $post->post_title;

		// Add AMP query var if in transitional mode.
		if ( ! amp_is_canonical() ) {
			$url = amp_add_paired_endpoint( $url );
		}

		// Set URL scheme based on whether HTTPS is current.
		$url = set_url_scheme( $url, ( 'http' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ) ? 'http' : 'https' );

		return $url;
	}

	/**
	 * Get the markup status preview URL.
	 *
	 * Adds a _wpnonce query param for the markup status preview action.
	 *
	 * @since 1.5.0
	 *
	 * @param string $url Frontend URL to preview markup status changes.
	 * @return string Preview URL.
	 */
	protected static function get_markup_status_preview_url( $url ) {
		return add_query_arg(
			'_wpnonce',
			wp_create_nonce( AMP_Validation_Manager::MARKUP_STATUS_PREVIEW_ACTION ),
			$url
		);
	}

	/**
	 * Normalize a URL for storage.
	 *
	 * The AMP query param is removed to facilitate switching between standard and transitional.
	 * The URL scheme is also normalized to HTTPS to help with transition from HTTP to HTTPS.
	 *
	 * @param string $url URL.
	 * @return string Normalized URL.
	 */
	protected static function normalize_url_for_storage( $url ) {
		// Only ever store the canonical version.
		if ( ! amp_is_canonical() ) {
			$url = amp_remove_paired_endpoint( $url );
		}

		// Remove fragment identifier in the rare case it could be provided. It is irrelevant for validation.
		$url = strtok( $url, '#' );

		// Query args to be removed from validated URLs.
		$removable_query_vars = array_merge(
			wp_removable_query_args(),
			[ 'preview_id', 'preview_nonce', 'preview', QueryVar::NOAMP ]
		);

		// Normalize query args, removing all that are not recognized or which are removable.
		$url_parts = explode( '?', $url, 2 );
		if ( 2 === count( $url_parts ) ) {
			$args = wp_parse_args( $url_parts[1] );
			foreach ( $removable_query_vars as $removable_query_arg ) {
				unset( $args[ $removable_query_arg ] );
			}
			$url = $url_parts[0];
			if ( ! empty( $args ) ) {
				$url = $url_parts[0] . '?' . build_query( $args );
			}
		}

		// Normalize the scheme as HTTPS.
		$url = set_url_scheme( $url, 'https' );

		return $url;
	}

	/**
	 * Stores the validation errors.
	 *
	 * If there are no validation errors provided, then any existing amp_validated_url post is deleted.
	 *
	 * @param array  $validation_errors Validation errors.
	 * @param string $url               URL on which the validation errors occurred. Will be normalized to non-AMP version.
	 * @param array  $args {
	 *     Args.
	 *
	 *     @type int|WP_Post $invalid_url_post Post to update. Optional. If empty, then post is looked up by URL.
	 *     @type array       $queried_object   Queried object, including keys for type and id. May be empty.
	 *     @type array       $stylesheets      Stylesheet data. May be empty.
	 *     @type array       $php_fatal_error  PHP Fatal Error. May be empty.
	 * }
	 * @return int|WP_Error $post_id The post ID of the custom post type used, or WP_Error on failure.
	 * @global WP $wp
	 */
	public static function store_validation_errors( $validation_errors, $url, $args = [] ) {
		$url  = self::normalize_url_for_storage( $url );
		$slug = md5( $url );
		$post = null;
		if ( ! empty( $args['invalid_url_post'] ) ) {
			$post = get_post( $args['invalid_url_post'] );
		}
		if ( ! $post ) {
			$post = self::get_invalid_url_post(
				$url,
				[
					'include_trashed' => true,
					'normalize'       => false, // Since already normalized.
				]
			);
		}

		/*
		 * The details for individual validation errors is stored in the amp_validation_error taxonomy terms.
		 * The post content just contains the slugs for these terms and the sources for the given instance of
		 * the validation error.
		 */
		$stored_validation_errors = [];

		// Prevent Kses from corrupting JSON in description.
		$pre_term_description_filters = [
			'wp_filter_kses'       => has_filter( 'pre_term_description', 'wp_filter_kses' ),
			'wp_targeted_link_rel' => has_filter( 'pre_term_description', 'wp_targeted_link_rel' ),
		];
		foreach ( $pre_term_description_filters as $callback => $priority ) {
			if ( false !== $priority ) {
				remove_filter( 'pre_term_description', $callback, $priority );
			}
		}

		$terms = [];
		foreach ( $validation_errors as $data ) {
			$term_data = AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term( $data );
			$term_slug = $term_data['slug'];

			if ( ! isset( $terms[ $term_slug ] ) ) {
				// Not using WP_Term_Query since more likely individual terms are cached and wp_insert_term() will itself look at this cache anyway.
				$term = AMP_Validation_Error_Taxonomy::get_term( $term_slug );
				if ( ! ( $term instanceof WP_Term ) ) {
					/*
					 * The default term_group is 0 so that is AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS.
					 * If sanitization auto-acceptance is enabled, then the term_group will be updated below.
					 */
					$r = wp_insert_term( $term_slug, AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG, wp_slash( $term_data ) );
					if ( is_wp_error( $r ) ) {
						continue;
					}
					$term_id = $r['term_id'];
					update_term_meta( $term_id, 'created_date_gmt', current_time( 'mysql', true ) );

					/*
					 * When sanitization is forced by filter, make sure the term is created with the filtered status.
					 * For some reason, the wp_insert_term() function doesn't work with the term_group being passed in.
					 */
					$sanitization = AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $data );
					if ( 'with_filter' === $sanitization['forced'] ) {
						$term_data['term_group'] = $sanitization['status'];
						wp_update_term(
							$term_id,
							AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
							[
								'term_group' => $sanitization['status'],
							]
						);
					} elseif ( AMP_Validation_Manager::is_sanitization_auto_accepted( $data ) ) {
						$term_data['term_group'] = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS;
						wp_update_term(
							$term_id,
							AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG,
							[
								'term_group' => $term_data['term_group'],
							]
						);
					}

					$term = get_term( $term_id );
				}
				$terms[ $term_slug ] = $term;
			}

			$stored_validation_errors[] = compact( 'term_slug', 'data' );
		}

		// Finish preventing Kses from corrupting JSON in description.
		foreach ( $pre_term_description_filters as $callback => $priority ) {
			if ( false !== $priority ) {
				add_filter( 'pre_term_description', $callback, $priority );
			}
		}

		$post_content = wp_json_encode( $stored_validation_errors );
		$placeholder  = 'amp_validated_url_content_placeholder' . wp_rand();

		// Guard against Kses from corrupting content by adding post_content after content_save_pre filter applies.
		$insert_post_content = static function( $post_data ) use ( $placeholder, $post_content ) {
			$should_supply_post_content = (
				isset( $post_data['post_content'], $post_data['post_type'] )
				&&
				$placeholder === $post_data['post_content']
				&&
				AMP_Validated_URL_Post_Type::POST_TYPE_SLUG === $post_data['post_type']
			);
			if ( $should_supply_post_content ) {
				$post_data['post_content'] = wp_slash( $post_content );
			}
			return $post_data;
		};
		add_filter( 'wp_insert_post_data', $insert_post_content );

		// Create a new invalid AMP URL post, or update the existing one.
		$r = wp_insert_post(
			wp_slash(
				[
					'ID'           => $post ? $post->ID : null,
					'post_type'    => self::POST_TYPE_SLUG,
					'post_title'   => $url,
					'post_name'    => $slug,
					'post_content' => $placeholder, // Content is provided via wp_insert_post_data filter above to guard against Kses-corruption.
					'post_status'  => 'publish',
				]
			),
			true
		);
		remove_filter( 'wp_insert_post_data', $insert_post_content );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$post_id = $r;
		wp_set_object_terms( $post_id, wp_list_pluck( $terms, 'term_id' ), AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG );

		update_post_meta( $post_id, self::VALIDATED_ENVIRONMENT_POST_META_KEY, self::get_validated_environment() );
		if ( isset( $args['queried_object'] ) ) {
			update_post_meta( $post_id, self::QUERIED_OBJECT_POST_META_KEY, $args['queried_object'] );
		}
		if ( isset( $args['stylesheets'] ) ) {
			// Note that json_encode() is being used here because wp_slash() will coerce scalar values to strings.
			update_post_meta( $post_id, self::STYLESHEETS_POST_META_KEY, wp_slash( wp_json_encode( $args['stylesheets'] ) ) );
		}
		if ( isset( $args['php_fatal_error'] ) ) {
			if ( empty( $args['php_fatal_error'] ) ) {
				delete_post_meta( $post_id, self::PHP_FATAL_ERROR_POST_META_KEY );
			} else {
				// Note that json_encode() is being used here because wp_slash() will coerce scalar values to strings.
				update_post_meta( $post_id, self::PHP_FATAL_ERROR_POST_META_KEY, wp_slash( wp_json_encode( $args['php_fatal_error'] ) ) );
			}
		}

		delete_transient( static::NEW_VALIDATION_ERROR_URLS_COUNT_TRANSIENT );

		return $post_id;
	}

	/**
	 * Delete batch of stylesheets postmeta.
	 *
	 * Given that parsed CSS can be quite large (250KB+) and is not de-duplicated across each validated URL, it is important
	 * to not store the stylesheet data indefinitely in order to not excessively bloat the database. The reality is that
	 * keeping around the parsed stylesheet data is of little value given that it will quickly go stale as themes and
	 * plugins are updated.
	 *
	 * @since 2.0
	 *
	 * @param int          $count  Count of batch size to delete.
	 * @param string|array $before Date before which to find amp_validated_url posts to delete.
	 *                             Accepts strtotime()-compatible string, or array of 'year', 'month', 'day' values.
	 * @return int Count of postmeta that were deleted.
	 */
	public static function delete_stylesheets_postmeta_batch( $count, $before ) {
		$deleted = 0;
		$query   = new WP_Query(
			[
				'post_type'      => self::POST_TYPE_SLUG,
				'meta_key'       => self::STYLESHEETS_POST_META_KEY,
				'date_query'     => [
					[
						'before' => $before,
					],
				],
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'posts_per_page' => $count,
			]
		);
		foreach ( $query->get_posts() as $post_id ) {
			if ( delete_post_meta( $post_id, self::STYLESHEETS_POST_META_KEY ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	/**
	 * Get recent validation errors by source.
	 *
	 * @since 2.0
	 * @todo This can be stored in object cache, invalidated whenever a validated URL post is inserted/updated/deleted.
	 *
	 * @param int $count Maximum count of validated URLs to gather validation errors from.
	 * @return array Multidimensional array where root keys are source types, sub-keys are source names, and leaf arrays are the validation error terms, data, and the post IDs the error occurs on.
	 */
	public static function get_recent_validation_errors_by_source( $count = 100 ) {
		$posts = get_posts(
			[
				'post_type'      => self::POST_TYPE_SLUG,
				'posts_per_page' => $count,
				'orderby'        => 'date',
				'order'          => 'desc',
			]
		);

		$errors_by_source = [];

		foreach ( $posts as $post ) {
			// Skip validated URLs which are stale since results will be misleading.
			if ( self::get_post_staleness( $post ) ) {
				continue;
			}

			$validation_errors = self::get_invalid_url_validation_errors( $post );
			if ( empty( $validation_errors ) ) {
				continue;
			}

			foreach ( $validation_errors as $validation_error ) {
				if ( empty( $validation_error['data']['sources'] ) || ! $validation_error['term'] instanceof WP_Term ) {
					continue;
				}
				foreach ( $validation_error['data']['sources'] as $source ) {
					if ( ! isset( $source['type'], $source['name'] ) ) {
						continue;
					}
					$data = json_decode( $validation_error['term']->description, true );
					if ( ! is_array( $data ) ) {
						continue;
					}

					if ( ! isset( $errors_by_source[ $source['type'] ][ $source['name'] ][ $validation_error['term']->slug ] ) ) {
						$errors_by_source[ $source['type'] ][ $source['name'] ][ $validation_error['term']->slug ] = [
							'term'     => $validation_error['term'],
							'data'     => $data,
							'post_ids' => [],
						];
					}
					$errors_by_source[ $source['type'] ][ $source['name'] ][ $validation_error['term']->slug ]['post_ids'][] = $post->ID;
				}
			}
		}

		return $errors_by_source;
	}

	/**
	 * Get the environment properties which will likely effect whether validation results are stale.
	 *
	 * @return array Environment.
	 */
	public static function get_validated_environment() {
		$plugin_registry = Services::get( 'plugin_registry' );

		$theme     = [];
		$theme_obj = wp_get_theme();
		if ( ! $theme_obj->errors() ) {
			$theme[ $theme_obj->get_stylesheet() ] = $theme_obj->get( 'Version' );

			$parent_theme_obj = $theme_obj->parent();
			if ( $parent_theme_obj ) {
				$theme[ $parent_theme_obj->get_stylesheet() ] = $parent_theme_obj->get( 'Version' );
			}
		}

		return [
			'theme'   => $theme,
			'plugins' => wp_list_pluck( $plugin_registry->get_plugins( true, false ), 'Version' ), // @todo What about multiple plugins being in the same directory?
			'options' => [
				Option::THEME_SUPPORT => AMP_Options_Manager::get_option( Option::THEME_SUPPORT ),
			],
		];
	}

	/**
	 * Get the differences between the current themes, plugins, and relevant options when amp_validated_url post was last updated and now.
	 *
	 * @param int|WP_Post $post Post of amp_validated_url type.
	 * @return array {
	 *     Staleness of the validation results. An empty array if the results are fresh.
	 *
	 *     @type string $theme   The theme that was active but is no longer. Absent if theme is the same.
	 *     @type array  $plugins Plugins that used to be active but are no longer, or which are active now but weren't. Also includes plugins that have version updates. Absent if the plugins were the same.
	 *     @type array  $options Options that used to be set. Absent if the options were the same.
	 * }
	 */
	public static function get_post_staleness( $post ) {
		$post = get_post( $post );
		if ( empty( $post ) || self::POST_TYPE_SLUG !== $post->post_type ) {
			return [];
		}

		$old_validated_environment = get_post_meta( $post->ID, self::VALIDATED_ENVIRONMENT_POST_META_KEY, true );
		$new_validated_environment = self::get_validated_environment();

		$staleness = [];

		// Theme difference.
		if ( isset( $old_validated_environment['theme'] ) && $new_validated_environment['theme'] !== $old_validated_environment['theme'] ) {
			if ( is_string( $old_validated_environment['theme'] ) ) {
				$old_validated_environment['theme'] = [
					$old_validated_environment['theme'] => null,
				];
			}

			$new_active_theme = array_diff_assoc( $new_validated_environment['theme'], $old_validated_environment['theme'] );
			if ( ! empty( $new_active_theme ) ) {
				$staleness['theme']['new'] = array_keys( $new_active_theme );
			}
			$old_active_theme = array_diff_assoc( $old_validated_environment['theme'], $new_validated_environment['theme'] );
			if ( ! empty( $old_active_theme ) ) {
				$staleness['theme']['old'] = array_keys( $old_active_theme );
			}
		}

		// Plugin difference.
		if ( isset( $old_validated_environment['plugins'] ) ) {
			$new_active_plugins = array_diff_assoc( $new_validated_environment['plugins'], $old_validated_environment['plugins'] );
			if ( ! empty( $new_active_plugins ) ) {
				$staleness['plugins']['new'] = array_keys( $new_active_plugins );
			}
			$old_active_plugins = array_diff_assoc( $old_validated_environment['plugins'], $new_validated_environment['plugins'] );
			if ( ! empty( $old_active_plugins ) ) {
				$staleness['plugins']['old'] = array_keys( $old_active_plugins );
			}
		}

		// Options difference.
		if ( isset( $old_validated_environment['options'] ) ) {
			$old_options = $old_validated_environment['options'];
		} else {
			$old_options = [];
		}
		$option_differences = array_diff_assoc( $old_options, $new_validated_environment['options'] );
		if ( ! empty( $option_differences ) ) {
			$staleness['options'] = $option_differences;
		}

		return $staleness;
	}

	/**
	 * Adds post columns to the UI for the validation errors.
	 *
	 * @param array $columns The post columns.
	 * @return array $columns The new post columns.
	 */
	public static function add_post_columns( $columns ) {
		$columns = array_merge(
			$columns,
			[
				AMP_Validation_Error_Taxonomy::ERROR_STATUS => sprintf(
					'%s<span class="dashicons dashicons-editor-help tooltip-button" tabindex="0"></span><div class="tooltip" hidden data-content="%s"></div>',
					esc_html__( 'Markup Status', 'amp' ),
					esc_attr(
						sprintf(
							'<h3>%s</h3><p>%s</p>',
							__( 'Markup Status', 'amp' ),
							__( 'When invalid markup is removed it will not block a URL from being served as AMP; the validation error will be sanitized, where the offending markup is stripped from the response to ensure AMP validity. If invalid AMP markup is kept, then URLs is occurs on will not be served as AMP pages.', 'amp' )
						)
					)
				),
				AMP_Validation_Error_Taxonomy::FOUND_ELEMENTS_AND_ATTRIBUTES => esc_html__( 'Invalid Markup', 'amp' ),
				AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT => esc_html__( 'Sources', 'amp' ),
				'css_usage' => esc_html__( 'CSS Usage', 'amp' ),
			]
		);

		if ( isset( $columns['title'] ) ) {
			$columns['title'] = esc_html__( 'URL', 'amp' );
		}

		// Move date to end.
		if ( isset( $columns['date'] ) ) {
			unset( $columns['date'] );
			$columns['date'] = esc_html__( 'Last Checked', 'amp' );
		}

		if ( ! empty( $_GET[ AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $columns['error_status'], $columns[ AMP_Validation_Error_Taxonomy::REMOVED_ELEMENTS ], $columns[ AMP_Validation_Error_Taxonomy::REMOVED_ATTRIBUTES ] );
			$columns[ AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT ] = esc_html__( 'Sources', 'amp' );
			$columns['date']  = esc_html__( 'Last Checked', 'amp' );
			$columns['title'] = esc_html__( 'URL', 'amp' );
		}

		return $columns;
	}

	/**
	 * Adds post columns to the /wp-admin/post.php page for amp_validated_url.
	 *
	 * @return array The filtered post columns.
	 */
	public static function add_single_post_columns() {
		return [
			'cb'                          => '<input type="checkbox" />',
			'error_code'                  => __( 'Error', 'amp' ),
			'error_type'                  => __( 'Type', 'amp' ),
			'details'                     => sprintf(
				'%s<span class="dashicons dashicons-editor-help tooltip-button" tabindex="0"></span><div class="tooltip" hidden data-content="%s"></div>',
				esc_html__( 'Context', 'amp' ),
				esc_attr(
					sprintf(
						'<h3>%s</h3><p>%s</p>',
						esc_html__( 'Context', 'amp' ),
						esc_html__( 'The parent element of where the error occurred.', 'amp' )
					)
				)
			),
			'sources_with_invalid_output' => __( 'Sources', 'amp' ),
			'status'                      => sprintf(
				'%s<span class="dashicons dashicons-editor-help tooltip-button" tabindex="0"></span><div class="tooltip" hidden data-content="%s"></div>',
				esc_html__( 'Markup Status', 'amp' ),
				esc_attr(
					sprintf(
						'<h3>%s</h3><p>%s</p>',
						esc_html__( 'Markup Status', 'amp' ),
						esc_html__( 'When invalid markup is removed it will not block a URL from being served as AMP; the validation error will be sanitized, where the offending markup is stripped from the response to ensure AMP validity. If invalid AMP markup is kept, then URLs is occurs on will not be served as AMP pages.', 'amp' )
					)
				)
			),
			'reviewed'                    => sprintf(
				'%s<span class="dashicons dashicons-editor-help tooltip-button" tabindex="0"></span><div class="tooltip" hidden data-content="%s"></div>',
				esc_html__( 'Reviewed', 'amp' ),
				esc_attr(
					sprintf(
						'<h3>%s</h3><p>%s</p>',
						esc_html__( 'Reviewed', 'amp' ),
						esc_html__( 'Confirm that the action being taken on the invalid markup (causing a validation error) has been seen and approved.', 'amp' )
					)
				)
			),
		];
	}

	/**
	 * Outputs custom columns in the /wp-admin UI for the AMP validation errors.
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $post_id     The ID of the post for the column.
	 * @return void
	 */
	public static function output_custom_column( $column_name, $post_id ) {
		$post = get_post( $post_id );
		if ( self::POST_TYPE_SLUG !== $post->post_type ) {
			return;
		}

		$validation_errors = self::get_invalid_url_validation_errors( $post_id );
		$error_summary     = AMP_Validation_Error_Taxonomy::summarize_validation_errors( wp_list_pluck( $validation_errors, 'data' ) );

		switch ( $column_name ) {
			case 'error_status':
				$staleness = self::get_post_staleness( $post_id );
				if ( ! empty( $staleness ) ) {
					echo '<p><strong><em>' . esc_html__( 'Stale results', 'amp' ) . '</em></strong></p>';
				}
				self::display_invalid_url_validation_error_counts_summary( $post_id );
				break;
			case AMP_Validation_Error_Taxonomy::FOUND_ELEMENTS_AND_ATTRIBUTES:
				$items = [];
				if ( ! empty( $error_summary[ AMP_Validation_Error_Taxonomy::REMOVED_ELEMENTS ] ) ) {
					foreach ( $error_summary[ AMP_Validation_Error_Taxonomy::REMOVED_ELEMENTS ] as $name => $count ) {
						if ( 1 === (int) $count ) {
							$items[] = sprintf( '<code>&lt;%s&gt;</code>', esc_html( $name ) );
						} else {
							$items[] = sprintf( '<code>&lt;%s&gt;</code> (%d)', esc_html( $name ), $count );
						}
					}
				}
				if ( ! empty( $error_summary[ AMP_Validation_Error_Taxonomy::REMOVED_ATTRIBUTES ] ) ) {
					foreach ( $error_summary[ AMP_Validation_Error_Taxonomy::REMOVED_ATTRIBUTES ] as $name => $count ) {
						if ( 1 === (int) $count ) {
							$items[] = sprintf( '<code>%s</code>', esc_html( $name ) );
						} else {
							$items[] = sprintf( '<code>%s</code> (%d)', esc_html( $name ), $count );
						}
					}
				}
				if ( ! empty( $error_summary['removed_pis'] ) ) {
					foreach ( $error_summary['removed_pis'] as $name => $count ) {
						if ( 1 === (int) $count ) {
							$items[] = sprintf( '<code>&lt;?%s&hellip;?&gt;</code>', esc_html( $name ) );
						} else {
							$items[] = sprintf( '<code>&lt;?%s&hellip;?&gt;</code> (%d)', esc_html( $name ), $count );
						}
					}
				}
				if ( ! empty( $items ) ) {
					$imploded_items = implode( ',</div><div>', $items );
					echo sprintf( '<div>%s</div>', $imploded_items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					esc_html_e( '--', 'amp' );
				}
				break;
			case 'css_usage':
				$style_custom_cdata_spec = null;
				foreach ( AMP_Allowed_Tags_Generated::get_allowed_tag( 'style' ) as $spec_rule ) {
					if ( isset( $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) && AMP_Style_Sanitizer::STYLE_AMP_CUSTOM_SPEC_NAME === $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) {
						$style_custom_cdata_spec = $spec_rule[ AMP_Rule_Spec::CDATA ];
					}
				}
				$stylesheets = json_decode( get_post_meta( $post->ID, self::STYLESHEETS_POST_META_KEY, true ), true );
				if ( ! is_array( $stylesheets ) || ! $style_custom_cdata_spec ) {
					echo esc_html( _x( 'n/a', 'CSS usage not available', 'amp' ) );
				} else {
					$total_size = 0;
					foreach ( $stylesheets as $stylesheet ) {
						if ( ! isset( $stylesheet['group'] ) || 'amp-custom' !== $stylesheet['group'] || ! empty( $stylesheet['duplicate'] ) ) {
							continue;
						}
						$total_size += $stylesheet['final_size'];
					}

					$css_usage_percentage = ( $total_size / $style_custom_cdata_spec['max_bytes'] ) * 100;
					if ( $css_usage_percentage > 100 ) {
						$color = '#a00'; // Red.
					} elseif ( $css_usage_percentage >= AMP_Style_Sanitizer::CSS_BUDGET_WARNING_PERCENTAGE ) {
						$color = '#d98500'; // Orange.
					} else {
						$color = '#006505'; // Green.
					}
					printf(
						'<span style="color:%s">%s%%</span>',
						esc_attr( $color ),
						esc_html( number_format_i18n( ceil( $css_usage_percentage ) ) )
					);
				}
				break;
			case AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT:
				if ( 0 === count( array_filter( $error_summary ) ) || empty( $error_summary[ AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT ] ) ) {
					esc_html_e( '--', 'amp' );
				} else {
					self::render_sources_column( $error_summary[ AMP_Validation_Error_Taxonomy::SOURCES_INVALID_OUTPUT ], $post_id );
				}
				break;
		}
	}

	/**
	 * Renders the sources column on the the single error URL page and the 'AMP Validated URLs' page.
	 *
	 * @param array $sources The summary of errors.
	 * @param int   $post_id The ID of the amp_validated_url post.
	 */
	public static function render_sources_column( $sources, $post_id ) {
		$active_theme          = null;
		$validated_environment = get_post_meta( $post_id, self::VALIDATED_ENVIRONMENT_POST_META_KEY, true );
		if ( isset( $validated_environment['theme'] ) ) {
			$active_theme = $validated_environment['theme'];
		}

		$output          = [];
		$plugin_registry = Services::get( 'plugin_registry' );
		foreach ( wp_array_slice_assoc( $sources, [ 'plugin', 'mu-plugin' ] ) as $type => $slugs ) {
			$plugin_names = [];
			$plugin_slugs = array_unique( $slugs );
			foreach ( $plugin_slugs as $plugin_slug ) {
				if ( 'mu-plugin' === $type ) {
					$plugin_names[] = $plugin_slug;
				} else {
					// Skip including Gutenberg in the summary if there is another plugin, since Gutenberg is like core.
					if ( 'gutenberg' === $plugin_slug && count( $slugs ) > 1 ) {
						continue;
					}

					$plugin_name = $plugin_slug;
					$plugin      = $plugin_registry->get_plugin_from_slug( $plugin_slug );
					if ( $plugin ) {
						$plugin_name = $plugin['data']['Name'];
					}
					$plugin_names[] = $plugin_name;
				}
			}
			$count = count( $plugin_names );
			if ( 1 === $count ) {
				$output[] = sprintf( '<strong class="source"><span class="dashicons dashicons-admin-plugins"></span>%s</strong>', esc_html( $plugin_names[0] ) );
			} else {
				$output[] = '<details class="source">';
				$output[] = sprintf(
					'<summary class="details-attributes__summary"><strong class="source"><span class="dashicons dashicons-admin-plugins"></span>%s (%d)</strong></summary>',
					'mu-plugin' === $type ? esc_html__( 'Must-Use Plugins', 'amp' ) : esc_html__( 'Plugins', 'amp' ),
					$count
				);
				$output[] = '<div>';
				$output[] = implode( '<br/>', array_unique( $plugin_names ) );
				$output[] = '</div>';
				$output[] = '</details>';
			}
		}
		if ( isset( $sources['theme'] ) && empty( $sources['embed'] ) ) {
			foreach ( array_unique( $sources['theme'] ) as $theme_slug ) {
				$theme_obj = wp_get_theme( $theme_slug );
				if ( ! $theme_obj->errors() ) {
					$theme_name = $theme_obj->get( 'Name' );
				} else {
					$theme_name = $theme_slug;
				}
				$output[] = sprintf( '<strong class="source"><span class="dashicons dashicons-admin-appearance"></span>%s</strong>', esc_html( $theme_name ) );
			}
		}
		if ( isset( $sources['core'] ) ) {
			$core_sources = array_unique( $sources['core'] );
			$count        = count( $core_sources );
			if ( 1 === $count ) {
				$output[] = sprintf( '<strong class="source"><span class="dashicons dashicons-wordpress-alt"></span>%s</strong>', esc_html( $core_sources[0] ) );
			} else {
				$output[] = '<details class="source">';
				$output[] = sprintf( '<summary class="details-attributes__summary"><strong class="source"><span class="dashicons dashicons-wordpress-alt"></span>%s (%d)</strong></summary>', esc_html__( 'Other', 'amp' ), $count );
				$output[] = '<div>';
				$output[] = implode( '<br/>', array_unique( $sources['core'] ) );
				$output[] = '</div>';
				$output[] = '</details>';
			}
		}

		if ( empty( $output ) && ! empty( $sources['embed'] ) ) {
			$output[] = sprintf( '<strong class="source"><span class="dashicons dashicons-wordpress-alt"></span>%s</strong>', esc_html__( 'Embed', 'amp' ) );
		}

		if ( empty( $output ) && ! empty( $sources['blocks'] ) ) {
			foreach ( array_unique( $sources['blocks'] ) as $block ) {
				$block_title = AMP_Validation_Error_Taxonomy::get_block_title( $block );

				if ( $block_title ) {
					$output[] = sprintf(
						'<strong class="source"><span class="dashicons dashicons-edit"></span>%s</strong>',
						esc_html( $block_title )
					);
				} else {
					$output[] = sprintf(
						'<strong class="source"><span class="dashicons dashicons-edit"></span><code>%s</code></strong>',
						esc_html( $block )
					);
				}
			}
		}

		if ( empty( $output ) && ! empty( $sources['hook'] ) ) {
			switch ( $sources['hook'] ) {
				case 'the_content':
					$dashicon    = 'edit';
					$source_name = __( 'Content', 'amp' );
					break;
				case 'the_excerpt':
					$dashicon    = 'edit';
					$source_name = __( 'Excerpt', 'amp' );
					break;
				default:
					$dashicon    = 'wordpress-alt';
					$source_name = sprintf(
						/* translators: %s is the hook name */
						__( 'Hook: %s', 'amp' ),
						$sources['hook']
					);
			}
			$output[] = sprintf( '<strong class="source"><span class="dashicons dashicons-%s"></span>%s</strong>', esc_attr( $dashicon ), esc_html( $source_name ) );
		}

		if ( empty( $sources ) && $active_theme ) {
			$theme_obj = wp_get_theme( $active_theme );
			if ( ! $theme_obj->errors() ) {
				$theme_name = $theme_obj->get( 'Name' );
			} else {
				$theme_name = $active_theme;
			}
			$output[] = '<div class="source">';
			$output[] = '<span class="dashicons dashicons-admin-appearance"></span>';
			/* translators: %s is the guessed theme as the source for the error */
			$output[] = esc_html( sprintf( __( '%s (?)', 'amp' ), $theme_name ) );
			$output[] = '</div>';
		}

		echo implode( '', $output ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Adds a 'Recheck' bulk action to the edit.php page and modifies the 'Move to Trash' text.
	 *
	 * Ensure only delete action is present, not trash.
	 *
	 * @param array $actions The bulk actions in the edit.php page.
	 * @return array $actions The filtered bulk actions.
	 */
	public static function filter_bulk_actions( $actions ) {
		$has_delete = ( isset( $actions['trash'] ) || isset( $actions['delete'] ) );
		unset( $actions['trash'], $actions['delete'] );
		if ( $has_delete ) {
			$actions['delete'] = esc_html__( 'Forget', 'amp' );
		}

		unset( $actions['edit'] );
		$actions[ self::BULK_VALIDATE_ACTION ] = esc_html__( 'Recheck', 'amp' );
		return $actions;
	}

	/**
	 * Handles the 'Recheck' bulk action on the edit.php page.
	 *
	 * @param string $redirect The URL of the redirect.
	 * @param string $action   The action.
	 * @param array  $items    The items on which to take the action.
	 * @return string $redirect The filtered URL of the redirect.
	 */
	public static function handle_bulk_action( $redirect, $action, $items ) {
		if ( self::BULK_VALIDATE_ACTION !== $action ) {
			return $redirect;
		}
		$remaining_invalid_urls = [];

		$errors = [];

		foreach ( $items as $item ) {
			$post = get_post( $item );
			if ( empty( $post ) || ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$url = self::get_url_from_post( $post );
			if ( empty( $url ) ) {
				continue;
			}

			$validity = AMP_Validation_Manager::validate_url_and_store( $url );
			if ( is_wp_error( $validity ) ) {
				$errors[] = AMP_Validation_Manager::get_validate_url_error_message( $validity->get_error_code(), $validity->get_error_message() );
				continue;
			}

			$validation_errors      = wp_list_pluck( $validity['results'], 'error' );
			$unaccepted_error_count = count(
				array_filter(
					$validation_errors,
					static function( $error ) {
						return ! AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error );
					}
				)
			);
			if ( $unaccepted_error_count > 0 ) {
				$remaining_invalid_urls[] = $validity['url'];
			}
		}

		// Get the URLs that still have errors after rechecking.
		$args = [
			self::URLS_TESTED => count( $items ),
		];
		if ( ! empty( $errors ) ) {
			$args['amp_validate_error'] = AMP_Validation_Manager::serialize_validation_error_messages( $errors );
		} else {
			$args[ self::REMAINING_ERRORS ] = count( $remaining_invalid_urls );
		}

		$redirect = remove_query_arg( wp_removable_query_args(), $redirect );
		return add_query_arg( rawurlencode_deep( $args ), $redirect );
	}

	/**
	 * Outputs an admin notice after rechecking URL(s) on the custom post page.
	 *
	 * @return void
	 */
	public static function print_admin_notice() {
		if ( ! get_current_screen() || self::POST_TYPE_SLUG !== get_current_screen()->post_type ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( isset( $_GET['amp_validate_error'] ) && is_string( $_GET['amp_validate_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The input var is validated by the unserialize_validation_error_messages method.
			$errors = AMP_Validation_Manager::unserialize_validation_error_messages( wp_unslash( $_GET['amp_validate_error'] ) );
			if ( $errors ) {
				foreach ( array_unique( $errors ) as $error_message ) {
					printf(
						'<div class="notice is-dismissible error"><p>%s</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">%s</span></button></div>',
						wp_kses(
							$error_message,
							[
								'a'    => array_fill_keys( [ 'href', 'target' ], true ),
								'code' => [],
							]
						),
						esc_html__( 'Dismiss this notice.', 'amp' )
					);
				}
			}
		}

		if ( isset( $_GET[ self::REMAINING_ERRORS ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count_urls_tested = isset( $_GET[ self::URLS_TESTED ] ) ? (int) $_GET[ self::URLS_TESTED ] : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$errors_remain     = ! empty( $_GET[ self::REMAINING_ERRORS ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $errors_remain ) {
				$message = _n(
					'The rechecked URL still has remaining invalid markup kept.',
					'The rechecked URLs still have remaining invalid markup kept.',
					$count_urls_tested,
					'amp'
				);
				$class   = 'notice-warning';
			} else {
				$message = _n(
					'The rechecked URL is free of non-removed invalid markup.',
					'The rechecked URLs are free of non-removed invalid markup.',
					$count_urls_tested,
					'amp'
				);
				$class   = 'updated';
			}

			printf(
				'<div class="notice is-dismissible %s"><p>%s</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">%s</span></button></div>',
				esc_attr( $class ),
				esc_html( $message ),
				esc_html__( 'Dismiss this notice.', 'amp' )
			);
		}

		if ( isset( $_GET['amp_taxonomy_terms_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = (int) $_GET['amp_taxonomy_terms_updated']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$class = 'updated';
			printf(
				'<div class="notice is-dismissible %s"><p>%s</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">%s</span></button></div>',
				esc_attr( $class ),
				esc_html(
					sprintf(
						/* translators: %s is count of validation errors updated */
						_n(
							'Updated %s validation error.',
							'Updated %s validation errors.',
							$count,
							'amp'
						),
						number_format_i18n( $count )
					)
				),
				esc_html__( 'Dismiss this notice.', 'amp' )
			);
		}

		// Add notice for PHP fatal error during validation request.
		$post = get_post();
		if ( $post instanceof WP_Post && 'post' === get_current_screen()->base && self::POST_TYPE_SLUG === get_current_screen()->post_type ) {
			self::render_php_fatal_error_admin_notice( $post );
		}

		/**
		 * Adds notices to the single error page.
		 * 1. If the error does not occur in any validated URL: Notice with button to delete the error.
		 * 2. Notice with detailed error information in an expanding box.
		 */
		if ( ! empty( $_GET[ AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ] ) && isset( $_GET['post_type'] ) && self::POST_TYPE_SLUG === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error_id = sanitize_key( wp_unslash( $_GET[ AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$error = AMP_Validation_Error_Taxonomy::get_term( $error_id );
			if ( ! $error ) {
				return;
			}

			// @todo Update this to use the method which will be developed in PR #1429 AMP_Validation_Error_Taxonomy::get_term_error() .
			$validation_error = json_decode( $error->description, true );
			if ( ! is_array( $validation_error ) ) {
				$validation_error = [];
			}
			$sanitization = AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $validation_error );
			$error_title  = AMP_Validation_Error_Taxonomy::get_error_title_from_code( $validation_error );

			if ( 0 === $error->count ) {
				echo '<div class="notice accept-reject-error"><p>';

				esc_html_e( 'There are no validated URLs with this validation error. Would you like to delete it?', 'amp' );

				$delete_url = wp_nonce_url(
					add_query_arg(
						[
							'action'  => 'delete',
							'term_id' => $error->term_id,
						]
					),
					'delete'
				);

				printf(
					' <a class="button button-small button-primary reject" href="%s">%s</a> ',
					esc_url( $delete_url ),
					esc_html__( 'Delete', 'amp' )
				);

				echo '</p></div>';
			}

			$status_text   = AMP_Validation_Error_Taxonomy::get_status_text_with_icon( $sanitization, true );
			$status_detail = sprintf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'Status', 'amp' ), wp_kses_post( $status_text ) );

			$error_details = AMP_Validation_Error_Taxonomy::render_single_url_error_details( $validation_error, $error, false, false );
			$error_details = str_replace( '<dl class="detailed">', '<dl class="detailed">' . $status_detail, $error_details );

			$class = 'notice error-details';
			if ( ! ( $sanitization['term_status'] & AMP_Validation_Error_Taxonomy::ACKNOWLEDGED_VALIDATION_ERROR_BIT_MASK ) ) {
				$class .= ' unreviewed';
			}

			?>
			<div class="<?php echo esc_attr( $class ); ?>">
				<ul>
					<?php echo $error_details; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</ul>
			</div>
			<?php

			$heading = wp_kses_post( $error_title );
			?>
			<script type="text/javascript">
				jQuery( function( $ ) {
					$( 'h1.wp-heading-inline' ).html( <?php echo wp_json_encode( $heading ); ?> );
				});
			</script>
			<?php
		}
	}

	/**
	 * Render PHP fatal error admin notice.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function render_php_fatal_error_admin_notice( WP_Post $post ) {
		$error = get_post_meta( $post->ID, self::PHP_FATAL_ERROR_POST_META_KEY, true );
		if ( empty( $error ) ) {
			return;
		}
		$error_data = json_decode( $error, true );
		if ( ! is_array( $error_data ) || ! isset( $error_data['message'], $error_data['file'], $error_data['line'] ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><?php echo AMP_Validation_Manager::get_validate_url_error_message( 'fatal_error_during_validation' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) : ?>
				<blockquote>
					<pre><?php echo esc_html( $error_data['message'] ); ?></pre>
				</blockquote>
				<p>
					<?php esc_html_e( 'Location:', 'amp' ); ?>
					<code><?php echo esc_html( sprintf( '%s:%d', $error_data['file'], $error_data['line'] ) ); ?></code>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handles clicking 'recheck' on the inline post actions and in the admin bar on the frontend.
	 *
	 * @throws Exception But it is caught. This is here for a PHPCS bug.
	 */
	public static function handle_validate_request() {
		check_admin_referer( self::NONCE_ACTION );
		if ( ! AMP_Validation_Manager::has_cap() ) {
			wp_die( esc_html__( 'You do not have permissions to validate an AMP URL. Did you get logged out?', 'amp' ) );
		}

		$post = null;
		$url  = null;
		$args = [];

		try {
			if ( isset( $_GET['post'] ) ) {
				$post = (int) $_GET['post'];
				if ( $post <= 0 ) {
					throw new Exception( 'unknown_post' );
				}
				$post = get_post( $post );
				if ( ! $post || self::POST_TYPE_SLUG !== $post->post_type ) {
					throw new Exception( 'invalid_post' );
				}
				if ( ! current_user_can( 'edit_post', $post->ID ) ) {
					throw new Exception( 'unauthorized' );
				}
				$url = self::get_url_from_post( $post );
			} elseif ( isset( $_GET['url'] ) ) {
				$url = wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['url'] ) ), null );
				if ( ! $url ) {
					throw new Exception( 'illegal_url' );
				}
				// Don't let non-admins create new amp_validated_url posts.
				if ( ! AMP_Validation_Manager::has_cap() ) {
					throw new Exception( 'unauthorized' );
				}
			}

			if ( ! $url ) {
				throw new Exception( 'missing_url' );
			}

			$validity = AMP_Validation_Manager::validate_url_and_store( $url );
			if ( is_wp_error( $validity ) ) {
				throw new Exception( AMP_Validation_Manager::get_validate_url_error_message( $validity->get_error_code(), $validity->get_error_message() ) );
			}

			$errors   = wp_list_pluck( $validity['results'], 'error' );
			$redirect = get_edit_post_link( $validity['post_id'], 'raw' );

			$error_count = count(
				array_filter(
					$errors,
					static function ( $error ) {
						return ! AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error );
					}
				)
			);

			$args[ self::URLS_TESTED ]      = '1';
			$args[ self::REMAINING_ERRORS ] = $error_count;
		} catch ( Exception $e ) {
			$args['amp_validate_error'] = AMP_Validation_Manager::serialize_validation_error_messages(
				[ $e->getMessage() ]
			);
			$args[ self::URLS_TESTED ]  = '0';

			if ( $post && self::POST_TYPE_SLUG === $post->post_type ) {
				$redirect = get_edit_post_link( $post->ID, 'raw' );
			} else {
				$redirect = admin_url(
					add_query_arg(
						[ 'post_type' => self::POST_TYPE_SLUG ],
						'edit.php'
					)
				);
			}
		}

		wp_safe_redirect( add_query_arg( rawurlencode_deep( $args ), $redirect ) );
		exit();
	}

	/**
	 * Re-check validated URL post for whether it has blocking validation errors.
	 *
	 * @param int|WP_Post $post Post.
	 * @return array|WP_Error List of blocking validation results, or a WP_Error in the case of failure.
	 */
	public static function recheck_post( $post ) {
		if ( ! $post ) {
			return new WP_Error( 'missing_post' );
		}
		$post = get_post( $post );
		if ( ! $post ) {
			return new WP_Error( 'missing_post' );
		}
		$url = self::get_url_from_post( $post );
		if ( ! $url ) {
			return new WP_Error( 'missing_url' );
		}

		$validity = AMP_Validation_Manager::validate_url_and_store( $url, $post );
		if ( is_wp_error( $validity ) ) {
			return $validity;
		}

		$validation_errors  = wp_list_pluck( $validity['results'], 'error' );
		$validation_results = [];
		foreach ( $validation_errors  as $error ) {
			$sanitized = AMP_Validation_Error_Taxonomy::is_validation_error_sanitized( $error ); // @todo Consider re-using $validity['results'][x]['sanitized'], unless auto-sanitize is causing problem.

			$validation_results[] = compact( 'error', 'sanitized' );
		}
		return $validation_results;
	}

	/**
	 * Handle validation error status update.
	 *
	 * @see AMP_Validation_Error_Taxonomy::handle_validation_error_update()
	 * @todo This is duplicated with logic in AMP_Validation_Error_Taxonomy. All of the term updating needs to be refactored to make use of the REST API.
	 */
	public static function handle_validation_error_status_update() {
		check_admin_referer( self::UPDATE_POST_TERM_STATUS_ACTION, self::UPDATE_POST_TERM_STATUS_ACTION . '_nonce' );

		if ( empty( $_POST[ self::VALIDATION_ERRORS_INPUT_KEY ] ) || ! is_array( $_POST[ self::VALIDATION_ERRORS_INPUT_KEY ] ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post || self::POST_TYPE_SLUG !== $post->post_type ) {
			return;
		}

		if ( ! AMP_Validation_Manager::has_cap() || ! current_user_can( 'edit_post', $post->ID ) ) {
			wp_die( esc_html__( 'You do not have permissions to validate an AMP URL. Did you get logged out?', 'amp' ) );
		}

		$updated_count = 0;

		$has_pre_term_description_filter = has_filter( 'pre_term_description', 'wp_filter_kses' );
		if ( false !== $has_pre_term_description_filter ) {
			remove_filter( 'pre_term_description', 'wp_filter_kses', $has_pre_term_description_filter );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization performed in loop.
		foreach ( $_POST[ self::VALIDATION_ERRORS_INPUT_KEY ] as $term_slug => $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}

			$status = isset( $data[ AMP_Validation_Manager::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR ] )
				? $data[ AMP_Validation_Manager::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR ]
				: null;

			$status_acknowledged = isset( $data[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACKNOWLEDGE_ACTION ] );

			if ( ! is_numeric( $status ) ) {
				continue;
			}
			$term_slug = sanitize_key( $term_slug );
			$term      = AMP_Validation_Error_Taxonomy::get_term( $term_slug );
			if ( ! $term ) {
				continue;
			}
			$term_group = AMP_Validation_Error_Taxonomy::sanitize_term_status( $status );
			if ( null === $term_group ) {
				continue;
			}

			$term_group = $status_acknowledged
				? $term_group | AMP_Validation_Error_Taxonomy::ACKNOWLEDGED_VALIDATION_ERROR_BIT_MASK
				: $term_group & ~ AMP_Validation_Error_Taxonomy::ACKNOWLEDGED_VALIDATION_ERROR_BIT_MASK;

			if ( $term_group !== $term->term_group ) {
				$updated_count++;
				wp_update_term( $term->term_id, AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG, compact( 'term_group' ) );
			}
		}

		if ( false !== $has_pre_term_description_filter ) {
			add_filter( 'pre_term_description', 'wp_filter_kses', $has_pre_term_description_filter );
		}

		$args = [
			'amp_taxonomy_terms_updated' => $updated_count,
		];

		// Re-check the post after the validation status change.
		if ( $updated_count > 0 ) {
			$validation_results = self::recheck_post( $post->ID );
			if ( ! is_wp_error( $validation_results ) ) {
				$args[ self::REMAINING_ERRORS ] = count(
					array_filter(
						$validation_results,
						static function( $result ) {
							return ! $result['sanitized'];
						}
					)
				);
			}

			delete_transient( static::NEW_VALIDATION_ERROR_URLS_COUNT_TRANSIENT );
		}

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = get_edit_post_link( $post->ID, 'raw' );
		}

		$redirect = remove_query_arg( wp_removable_query_args(), $redirect );
		wp_safe_redirect( add_query_arg( $args, $redirect ) );
		exit();
	}

	/**
	 * Enqueue scripts for the edit post screen.
	 */
	public static function enqueue_edit_post_screen_scripts() {
		$current_screen = get_current_screen();
		if ( 'post' !== $current_screen->base || self::POST_TYPE_SLUG !== $current_screen->post_type ) {
			return;
		}

		// Eliminate autosave since it is only relevant for the content editor.
		wp_dequeue_script( 'autosave' );

		$asset_file   = AMP__DIR__ . '/assets/js/' . self::EDIT_POST_SCRIPT_HANDLE . '.asset.php';
		$asset        = require $asset_file;
		$dependencies = $asset['dependencies'];
		$version      = $asset['version'];

		wp_enqueue_script(
			self::EDIT_POST_SCRIPT_HANDLE,
			amp_get_asset_url( 'js/' . self::EDIT_POST_SCRIPT_HANDLE . '.js' ),
			$dependencies,
			$version,
			true
		);

		// React-based validated URL page component.
		$asset_file   = AMP__DIR__ . '/assets/js/' . self::AMP_VALIDATED_URL_PAGE_SCRIPT_HANDLE . '.asset.php';
		$asset        = require $asset_file;
		$dependencies = $asset['dependencies'];
		$version      = $asset['version'];

		wp_enqueue_script(
			self::AMP_VALIDATED_URL_PAGE_SCRIPT_HANDLE,
			amp_get_asset_url( 'js/' . self::AMP_VALIDATED_URL_PAGE_SCRIPT_HANDLE . '.js' ),
			$dependencies,
			$version,
			true
		);

		$post                    = get_post();
		$validated_url_page_data = [
			'APP_ROOT_ID'              => self::AMP_VALIDATED_URL_PAGE_APP_ROOT_ID,
			'POST_ID'                  => $post->ID,
			'VALIDATED_URLS_REST_PATH' => '/amp/v1/validated-urls',
		];

		wp_add_inline_script(
			self::AMP_VALIDATED_URL_PAGE_SCRIPT_HANDLE,
			sprintf(
				'var ampSettings = %s;',
				wp_json_encode( $validated_url_page_data )
			),
			'before'
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( self::EDIT_POST_SCRIPT_HANDLE, 'amp' );
		} elseif ( function_exists( 'wp_get_jed_locale_data' ) || function_exists( 'gutenberg_get_jed_locale_data' ) ) {
			$locale_data  = function_exists( 'wp_get_jed_locale_data' ) ? wp_get_jed_locale_data( 'amp' ) : gutenberg_get_jed_locale_data( 'amp' );
			$translations = wp_json_encode( $locale_data );

			wp_add_inline_script(
				self::EDIT_POST_SCRIPT_HANDLE,
				'wp.i18n.setLocaleData( ' . $translations . ', "amp" );',
				'after'
			);
		}
	}

	/**
	 * Adds the meta boxes to the CPT post.php page.
	 *
	 * @global array $wp_meta_boxes
	 * @return void
	 */
	public static function add_meta_boxes() {
		global $wp_meta_boxes;
		$stylesheets_metabox_id = 'amp_stylesheets';

		remove_meta_box( 'submitdiv', self::POST_TYPE_SLUG, 'side' );
		remove_meta_box( 'slugdiv', self::POST_TYPE_SLUG, 'normal' );
		add_meta_box(
			self::STATUS_META_BOX,
			__( 'Status', 'amp' ),
			[ __CLASS__, 'print_status_meta_box' ],
			self::POST_TYPE_SLUG,
			'side',
			'default',
			[ '__back_compat_meta_box' => true ]
		);
		add_meta_box(
			$stylesheets_metabox_id,
			__( 'Stylesheets', 'amp' ),
			[ __CLASS__, 'print_stylesheets_meta_box' ],
			self::POST_TYPE_SLUG,
			'normal',
			'default',
			[ '__back_compat_meta_box' => true ]
		);

		// Ensure only the expected metaboxes are shown on this screen.
		// Note the O(n^3) complexity here is not a concern because each nested array has only a few items.
		$allowed_metaboxes = [ 'slugdiv', 'submitdiv', self::STATUS_META_BOX, $stylesheets_metabox_id ];
		foreach ( $wp_meta_boxes[ self::POST_TYPE_SLUG ] as $context => $metabox_contexts ) {
			foreach ( $metabox_contexts as $metabox_priorities ) {
				foreach ( array_keys( $metabox_priorities ) as $id ) {
					if ( ! in_array( $id, $allowed_metaboxes, true ) ) {
						remove_meta_box( $id, self::POST_TYPE_SLUG, $context );
					}
				}
			}
		}
	}

	/**
	 * Outputs the markup of the side meta box in the CPT post.php page.
	 *
	 * This is partially copied from meta-boxes.php.
	 * Adds 'Published on,' and links to move to trash and recheck.
	 *
	 * @param WP_Post $post The post for which to output the box.
	 * @return void
	 */
	public static function print_status_meta_box( $post ) {
		?>
		<style>
			#amp_validation_status .inside {
				margin: 0;
				padding: 0;
			}
			#re-check-action {
				float: left;
			}
		</style>
		<div id="submitpost" class="submitbox">
			<?php wp_nonce_field( self::UPDATE_POST_TERM_STATUS_ACTION, self::UPDATE_POST_TERM_STATUS_ACTION . '_nonce', false ); ?>
			<div id="minor-publishing">
				<div class="curtime misc-pub-section">
					<span id="timestamp">
					<?php
					printf(
						/* translators: %s: The date this was published */
						wp_kses_post( __( 'Last checked: <b>%s</b>', 'amp' ) ),
						/* translators: Meta box date format */
						esc_html( date_i18n( __( 'M j, Y @ H:i', 'amp' ), strtotime( $post->post_date ) ) )
					);
					?>
					</span>
				</div>
				<div id="minor-publishing-actions">
					<div id="re-check-action">
						<a class="button button-secondary" href="<?php echo esc_url( self::get_recheck_url( $post ) ); ?>">
							<?php esc_html_e( 'Recheck', 'amp' ); ?>
						</a>
					</div>
					<div id="preview-action">
						<button type="button" name="action" class="preview button" id="preview_validation_errors"><?php esc_html_e( 'Preview Changes', 'amp' ); ?></button>
					</div>
					<div class="clear"></div>
				</div>
				<div id="misc-publishing-actions">

					<div class="misc-pub-section">
						<?php
						$staleness = self::get_post_staleness( $post );
						if ( ! empty( $staleness ) ) {
							echo '<div class="notice notice-info notice-alt inline"><p>';
							echo '<b>';
							esc_html_e( 'Stale results', 'amp' );
							echo '</b>';
							echo '<br>';
							if ( ! empty( $staleness['theme'] ) && ! empty( $staleness['plugins'] ) ) {
								esc_html_e( 'The theme and plugins have changed since these results were obtained.', 'amp' );
								echo ' ';
							} elseif ( ! empty( $staleness['theme'] ) ) {
								esc_html_e( 'The theme has changed since these results were obtained.', 'amp' );
								echo ' ';
							} elseif ( ! empty( $staleness['plugins'] ) ) {
								esc_html_e( 'Plugins have been updated since these results were obtained.', 'amp' );
								echo ' ';
							}
							esc_html_e( 'Please recheck.', 'amp' );
							echo '</p></div>';
						}
						?>

						<?php
						$counts = self::count_invalid_url_validation_errors( self::get_invalid_url_validation_errors( $post ) );

						if ( 0 < ( $counts['new_rejected'] + $counts['new_accepted'] ) ) {
							?>
							<strong id="amp-invalid-markup" class="status-text">
								<span class="amp-icon amp-invalid"></span>
								<?php esc_html_e( 'Invalid markup not reviewed', 'amp' ); ?>
							</strong>
							<?php
							esc_html_e(
								'For each instance of invalid markup, determine whether it can be safely removed or it needs to be kept. You can change the Markup Status (Removed or Kept) and click the Preview Changes button to see its impact on the page. Once the proper action has been taken (Remove or Keep), you can toggle the checkbox the instance as Reviewed.',
								'amp'
							);
							echo '<br><br>';
						}
						?>

						<?php
						$is_amp_enabled = self::is_amp_enabled_on_post( $post );
						$class          = $is_amp_enabled ? 'amp-enabled' : 'amp-disabled';
						?>
						<strong id="amp-enabled-icon" class="status-text <?php echo esc_attr( $class ); ?>">
							<?php
							if ( $is_amp_enabled ) {
								esc_html_e( 'AMP enabled', 'amp' );
							} else {
								esc_html_e( 'AMP disabled', 'amp' );
							}
							?>
						</strong>
						<?php if ( $is_amp_enabled && count( array_filter( $counts ) ) > 0 ) : ?>
							<?php esc_html_e( 'AMP is enabled because no invalid markup is being kept.', 'amp' ); ?>
						<?php elseif ( ! $is_amp_enabled ) : ?>
							<?php esc_html_e( 'AMP is disabled because some invalid markup has been kept. To enable AMP to be served, either mark the invalid markup as &#8220;removed&#8221; or provide an AMP-compatible fix that eliminates the invalid markup from being generated in the first place.', 'amp' ); ?>
						<?php endif; ?>
					</div>

					<div class="misc-pub-section">
						<?php
						$actions = [];

						ob_start();
						$view_label     = __( 'View URL', 'amp' );
						$queried_object = get_post_meta( $post->ID, self::QUERIED_OBJECT_POST_META_KEY, true );
						if ( isset( $queried_object['id'], $queried_object['type'] ) ) {
							if ( 'post' === $queried_object['type'] && get_post( $queried_object['id'] ) && post_type_exists( get_post( $queried_object['id'] )->post_type ) ) {
								$post_type_object = get_post_type_object( get_post( $queried_object['id'] )->post_type );
								edit_post_link( $post_type_object->labels->edit_item, '', '', $queried_object['id'] );
								$view_label = $post_type_object->labels->view_item;
							} elseif ( 'term' === $queried_object['type'] && get_term( $queried_object['id'] ) && taxonomy_exists( get_term( $queried_object['id'] )->taxonomy ) ) {
								$taxonomy_object = get_taxonomy( get_term( $queried_object['id'] )->taxonomy );
								edit_term_link( $taxonomy_object->labels->edit_item, '', '', get_term( $queried_object['id'] ) );
								$view_label = $taxonomy_object->labels->view_item;
							} elseif ( 'user' === $queried_object['type'] ) {
								$link = get_edit_user_link( $queried_object['id'] );
								if ( $link ) {
									printf( '<a href="%s">%s</a>', esc_url( $link ), esc_html__( 'Edit User', 'amp' ) );
								}
								$view_label = __( 'View User', 'amp' );
							}
						}
						$actions['edit'] = ob_get_clean();
						$actions['view'] = sprintf( '<a href="%s">%s</a>', esc_url( self::get_url_from_post( $post ) ), esc_html( $view_label ) );

						/**
						 * Filters the array of action links shown in the status metabox.
						 *
						 * @since 2.1
						 * @internal
						 *
						 * @param string[] $actions Action links.
						 */
						$actions = apply_filters( 'amp_validated_url_status_actions', $actions, $post );

						echo wp_kses(
							implode( ' | ', array_filter( $actions ) ),
							[
								'a' => array_fill_keys( [ 'href' ], true ),
							]
						);
						?>
					</div>
				</div>
			</div>
			<div id="major-publishing-actions">
				<div id="delete-action">
					<a class="submitdelete deletion" href="<?php echo esc_url( get_delete_post_link( $post->ID, '', true ) ); ?>">
						<?php esc_html_e( 'Forget', 'amp' ); ?>
					</a>
				</div>
				<div id="publishing-action">
					<button type="submit" name="action" class="button button-primary" value="<?php echo esc_attr( self::UPDATE_POST_TERM_STATUS_ACTION ); ?>"><?php esc_html_e( 'Update', 'amp' ); ?></button>
				</div>
				<div class="clear"></div>
			</div>
		</div><!-- /submitpost -->

		<script>
		jQuery( function( $ ) {
			var validateUrl, postId;
			validateUrl = <?php echo wp_json_encode( self::get_markup_status_preview_url( self::get_url_from_post( $post ) ) ); ?>;
			postId = <?php echo wp_json_encode( $post->ID ); ?>;
			$( '#preview_validation_errors' ).on( 'click', function() {
				var params = {}, validatePreviewUrl = validateUrl;
				params.preview = '1';
				$( '.amp-validation-error-status' ).each( function() {
					if ( this.value && ! this.options[ this.selectedIndex ].defaultSelected ) {
						params[ this.name ] = this.value;
					}
				} );
				validatePreviewUrl += '&' + $.param( params );
				validatePreviewUrl += '#development=1';
				window.open( validatePreviewUrl, 'amp-validation-error-term-status-preview-' + String( postId ) );
			} );
		} );
		</script>
		<?php
	}

	/**
	 * Renders stylesheet info for the validated URL.
	 *
	 * @param WP_Post $post The post for the meta box.
	 * @return void
	 */
	public static function print_stylesheets_meta_box( $post ) {
		?>
			<div id="amp-validated-url-root"></div>
		<?php
		$stylesheets = get_post_meta( $post->ID, self::STYLESHEETS_POST_META_KEY, true );
		if ( empty( $stylesheets ) ) {
			printf(
				'<p><em>%s</em></p>',
				wp_kses_post(
					sprintf(
						/* translators: placeholder is URL to recheck the post */
						__( 'Stylesheet information for this URL is no longer available. Such data is automatically deleted after a week to reduce database storage. It is of little value to store long-term given that it becomes stale as themes and plugins are updated. To obtain the latest stylesheet information, <a href="%s">recheck this URL</a>.', 'amp' ),
						esc_url( self::get_recheck_url( $post ) . '#amp_stylesheets' )
					)
				)
			);
			return;
		}
		$stylesheets = json_decode( $stylesheets, true );
		if ( ! is_array( $stylesheets ) ) {
			printf(
				'<p><em>%s</em></p>',
				esc_html__( 'Unable to retrieve data for stylesheets.', 'amp' )
			);
			return;
		}

		$style_custom_cdata_spec = null;
		foreach ( AMP_Allowed_Tags_Generated::get_allowed_tag( 'style' ) as $spec_rule ) {
			if ( isset( $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) && AMP_Style_Sanitizer::STYLE_AMP_CUSTOM_SPEC_NAME === $spec_rule[ AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) {
				$style_custom_cdata_spec = $spec_rule[ AMP_Rule_Spec::CDATA ];
			}
		}

		$included_final_size    = 0;
		$included_original_size = 0;
		$excluded_final_size    = 0;
		$excluded_original_size = 0;
		$excluded_stylesheets   = 0;
		$max_final_size         = 0;

		$included_status  = 1;
		$excessive_status = 2;
		$excluded_status  = 3;

		// Determine which stylesheets are included based on their priorities.
		$pending_stylesheet_indices = array_keys( $stylesheets );
		usort(
			$pending_stylesheet_indices,
			static function ( $a, $b ) use ( $stylesheets ) {
				return $stylesheets[ $a ]['priority'] - $stylesheets[ $b ]['priority'];
			}
		);
		foreach ( $pending_stylesheet_indices as $i ) {
			// @todo Add information about amp-keyframes as well.
			if ( ! isset( $stylesheets[ $i ]['group'] ) || 'amp-custom' !== $stylesheets[ $i ]['group'] || ! empty( $stylesheets[ $i ]['duplicate'] ) ) {
				continue;
			}
			$max_final_size = max( $max_final_size, $stylesheets[ $i ]['final_size'] );
			if ( $stylesheets[ $i ]['included'] ) {
				$included_final_size    += $stylesheets[ $i ]['final_size'];
				$included_original_size += $stylesheets[ $i ]['original_size'];

				if ( $included_final_size >= $style_custom_cdata_spec['max_bytes'] ) {
					$stylesheets[ $i ]['status'] = $excessive_status;
				} else {
					$stylesheets[ $i ]['status'] = $included_status;
				}
			} else {
				$excluded_final_size    += $stylesheets[ $i ]['final_size'];
				$excluded_original_size += $stylesheets[ $i ]['original_size'];
				$excluded_stylesheets++;
				$stylesheets[ $i ]['status'] = $excluded_status;
			}
		}

		?>

		<?php if ( ! AMP_Style_Sanitizer::has_required_php_css_parser() ) : ?>
			<div class="notice notice-alt notice-warning inline">
				<p>
					<?php esc_html_e( 'AMP CSS processing is limited because a conflicting version of PHP-CSS-Parser has been loaded by another plugin or theme. Tree shaking is not available.', 'amp' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<table class="amp-stylesheet-summary">
			<tr>
				<th>
					<?php esc_html_e( 'Total CSS size prior to minification:', 'amp' ); ?>
				</th>
				<td>
					<?php echo esc_html( number_format_i18n( $included_original_size + $excluded_original_size ) ); ?>
					<abbr title="<?php esc_attr_e( 'bytes', 'amp' ); ?>"><?php echo esc_html_x( 'B', 'abbreviation for bytes', 'amp' ); ?></abbr>
				</td>
			</tr>
			<tr>
				<th>
					<?php esc_html_e( 'Total CSS size after minification:', 'amp' ); ?>
				</th>
				<td>
					<?php echo esc_html( number_format_i18n( $included_final_size + $excluded_final_size ) ); ?>
					<abbr title="<?php esc_attr_e( 'bytes', 'amp' ); ?>"><?php echo esc_html_x( 'B', 'abbreviation for bytes', 'amp' ); ?></abbr>
				</td>
			</tr>
			<tr>
				<th>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s is max kilobytes */
							__( 'Percentage of used CSS budget (%sKB):', 'amp' ),
							number_format_i18n( $style_custom_cdata_spec['max_bytes'] / 1000 )
						)
					);
					?>
				</th>
				<td>
					<?php
					$percentage_budget_used = ( ( $included_final_size + $excluded_final_size ) / $style_custom_cdata_spec['max_bytes'] ) * 100;

					printf( '%.1f%% ', $percentage_budget_used ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					if ( $percentage_budget_used > 100 ) {
						$icon = Icon::invalid();
					} elseif ( $percentage_budget_used >= AMP_Style_Sanitizer::CSS_BUDGET_WARNING_PERCENTAGE ) {
						$icon = Icon::warning();
					} else {
						$icon = Icon::valid();
					}

					echo $icon->to_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</td>
			</tr>
			<tr>
				<th>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s is the number of stylesheets excluded */
							_n( 'Excluded minified CSS (%s stylesheet):', 'Excluded minified CSS size (%s stylesheets):', $excluded_stylesheets, 'amp' ),
							number_format_i18n( $excluded_stylesheets )
						)
					);
					?>
				</th>
				<td>
					<?php echo esc_html( number_format_i18n( $excluded_final_size ) ); ?>
					<abbr title="<?php esc_attr_e( 'bytes', 'amp' ); ?>"><?php echo esc_html_x( 'B', 'abbreviation for bytes', 'amp' ); ?></abbr>
				</td>
			</tr>
		</table>

		<?php if ( $percentage_budget_used > 100 ) : ?>
			<div class="notice notice-alt notice-error inline">
				<p>
					<?php if ( 0 === $excluded_stylesheets ) : ?>
						<?php esc_html_e( 'You have exceeded the CSS budget. The page will not be served as a valid AMP page.', 'amp' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'You have exceeded the CSS budget. Stylesheets deemed of lesser priority have been excluded from the page.', 'amp' ); ?>
					<?php endif; ?>
					<?php esc_html_e( 'Please review the flagged stylesheets below and determine if the current theme or a particular plugin is including excessive CSS.', 'amp' ); ?>
				</p>
			</div>
		<?php elseif ( $percentage_budget_used >= AMP_Style_Sanitizer::CSS_BUDGET_WARNING_PERCENTAGE ) : ?>
			<div class="notice notice-alt notice-warning inline">
				<p>
					<?php esc_html_e( 'You are nearing the limit of the CSS budget. Once this limit is reached, stylesheets deemed of lesser priority will be excluded from the page. Please review the stylesheets below and determine if the current theme or a particular plugin is including excessive CSS.', 'amp' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<table class="amp-stylesheet-list wp-list-table widefat fixed striped">
			<thead>
			<tr>
				<th class="column-stylesheet_expand"></th>
				<th class="column-stylesheet_order"><?php esc_html_e( 'Order', 'amp' ); ?></th>
				<th class="column-original_size">
					<?php esc_html_e( 'Original', 'amp' ); ?>
					(<abbr title="<?php esc_attr_e( 'bytes', 'amp' ); ?>"><?php echo esc_html_x( 'B', 'abbreviation for bytes', 'amp' ); ?></abbr>)
				</th>
				<th class="column-minified"><?php esc_html_e( 'Minified', 'amp' ); ?></th>
				<th class="column-final_size">
					<?php esc_html_e( 'Final', 'amp' ); ?>
					(<abbr title="<?php esc_attr_e( 'bytes', 'amp' ); ?>"><?php echo esc_html_x( 'B', 'abbreviation for bytes', 'amp' ); ?></abbr>)
				</th>
				<th class="column-percentage"><?php esc_html_e( 'Percent', 'amp' ); ?></th>
				<th class="column-priority"><?php esc_html_e( 'Priority', 'amp' ); ?></th>
				<th class="column-stylesheet_included"><?php esc_html_e( 'Included', 'amp' ); ?></th>
				<th class="column-markup"><?php esc_html_e( 'Markup', 'amp' ); ?></th>
				<th class="column-sources_with_invalid_output"><?php esc_html_e( 'Sources', 'amp' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php $row = 1; ?>
			<?php foreach ( $stylesheets as $stylesheet ) : ?>
				<?php
				// @todo Add information about amp-keyframes as well.
				if ( ! isset( $stylesheet['group'] ) || 'amp-custom' !== $stylesheet['group'] || ! empty( $stylesheet['duplicate'] ) ) {
					continue;
				}

				$origin_html = '<' . $stylesheet['element']['name'];
				if ( ! empty( $stylesheet['element']['attributes'] ) ) {
					$attributes = $stylesheet['element']['attributes'];
					if ( ! empty( $attributes['class'] ) ) {
						$attributes['class'] = trim( preg_replace( '/(^|\s)amp-wp-\w+(\s|$)/', ' ', $attributes['class'] ) );
						if ( empty( $attributes['class'] ) ) {
							unset( $attributes['class'] );
						}
					}
					if ( isset( $attributes[ AMP_Style_Sanitizer::ORIGINAL_STYLE_ATTRIBUTE_NAME ] ) ) {
						$attributes['style'] = $attributes[ AMP_Style_Sanitizer::ORIGINAL_STYLE_ATTRIBUTE_NAME ];
						unset( $attributes[ AMP_Style_Sanitizer::ORIGINAL_STYLE_ATTRIBUTE_NAME ] );
					}
					if ( ! empty( $attributes ) ) {
						foreach ( $attributes as $name => $value ) {
							if ( '' === $value ) {
								$origin_html .= ' ' . sprintf( '%s', esc_html( $name ) );
							} else {
								$origin_html .= ' ' . sprintf( '%s="%s"', esc_html( $name ), esc_attr( $value ) );
							}
						}
					}
				}
				$origin_html .= '>';

				if ( 0 === $stylesheet['original_size'] ) {
					$ratio = 1;
				} else {
					$ratio = $stylesheet['final_size'] / $stylesheet['original_size'];
				}
				?>
				<tr class="<?php echo esc_attr( sprintf( 'stylesheet level-0 %s', 0 === $row % 2 ? 'even' : 'odd' ) ); ?>">
					<td class="column-stylesheet_expand">
						<button class="toggle-stylesheet-details" type="button">
							<span class="screen-reader-text"><?php esc_html_e( 'Expand/collapse', 'amp' ); ?></span>
						</button>
					</td>
					<td class="column-stylesheet_order">
						<?php echo (int) $row; ?>
					</td>
					<td class="column-original_size">
						<?php
						echo esc_html( number_format_i18n( $stylesheet['original_size'] ) );
						?>
					</td>
					<td class="column-minified">
						<?php
						if ( $ratio <= 1 ) {
							echo esc_html( sprintf( '-%.1f%%', ( 1.0 - $ratio ) * 100 ) );
						} else {
							echo esc_html( sprintf( '+%.1f%%', -1 * ( 1.0 - $ratio ) * 100 ) );
						}
						?>
					</td>
					<td class="column-final_size">
						<?php
						echo esc_html( number_format_i18n( $stylesheet['final_size'] ) );
						?>
					</td>
					<td class="column-percentage">
						<?php
						$percentage = $stylesheet['final_size'] / ( $included_final_size + $excluded_final_size );
						?>
						<meter value="<?php echo esc_attr( $stylesheet['final_size'] ); ?>" min="0" max="<?php echo esc_attr( $included_final_size + $excluded_final_size ); ?>" title="<?php esc_attr_e( 'Stylesheet bytes of total CSS added to page', 'amp' ); ?>">
							<?php echo esc_html( round( ( $percentage ) * 100 ) ) . '%'; ?>
						</meter>
					</td>
					<td class="column-priority">
						<?php echo esc_html( $stylesheet['priority'] ); ?>
					</td>
					<td class="column-stylesheet_included">
						<?php
						switch ( $stylesheet['status'] ) {
							case $included_status:
								printf( '<span title="%s" class="amp-icon amp-valid"></span>', esc_attr__( 'Stylesheet included', 'amp' ) );
								break;
							case $excessive_status:
								printf( '<span title="%s" class="amp-icon amp-warning"></span>', esc_attr__( 'Stylesheet overruns CSS budget yet it is still included on page', 'amp' ) );
								break;
							case $excluded_status:
								printf( '<span title="%s" class="amp-icon amp-invalid"></span>', esc_attr__( 'Stylesheet excluded due to exceeding CSS budget', 'amp' ) );
								break;
						}
						?>
					</td>
					<td class="column-markup">
						<?php
						$origin_abbr_text = '?';
						if ( 'link_element' === $stylesheet['origin'] ) {
							$origin_abbr_text = '<link&nbsp;&hellip;>'; // @todo Consider adding the basename of the CSS file.
						} elseif ( 'style_element' === $stylesheet['origin'] ) {
							$origin_abbr_text = '<style>';
						} elseif ( 'style_attribute' === $stylesheet['origin'] ) {
							$origin_abbr_text = 'style="&hellip;"';
						}
						$needs_abbr = $origin_abbr_text !== $origin_html;
						if ( $needs_abbr ) {
							printf( '<abbr title="%s">', esc_attr( $origin_html ) );
						}
						printf( '<code>%s</code>', esc_html( $origin_abbr_text ) );
						if ( $needs_abbr ) {
							echo '</abbr>';
						}
						echo '</code>';
						?>
					</td>
					<td class="column-sources_with_invalid_output">
						<?php
						if ( empty( $stylesheet['sources'] ) ) {
							esc_html_e( '--', 'amp' );
						} else {
							self::render_sources_column( AMP_Validation_Error_Taxonomy::summarize_sources( $stylesheet['sources'] ), $post->ID );
						}
						?>
					</td>
				</tr>
				<tr class="<?php echo esc_attr( sprintf( 'stylesheet-details level-0 %s', 0 === $row % 2 ? 'even' : 'odd' ) ); ?>">
					<td colspan="10">
						<dl class="detailed">
							<dt><?php esc_html_e( 'Original Markup', 'amp' ); ?></dt>
							<dd><code class="stylesheet-origin-markup"><?php echo esc_html( $origin_html ); ?></code></dd>

							<?php if ( isset( $stylesheet['element']['attributes']['href'] ) ) : ?>
								<dt><?php esc_html_e( 'Stylesheet URL', 'amp' ); ?></dt>
								<dd>
									<?php
									printf(
										'<a href="%s" target="_blank">%s</a>',
										esc_url( $stylesheet['element']['attributes']['href'] ),
										esc_html( $stylesheet['element']['attributes']['href'] )
									);
									?>
								</dd>
							<?php endif; ?>

							<dt><?php esc_html_e( 'Sources', 'amp' ); ?></dt>
							<dd>
								<?php AMP_Validation_Error_Taxonomy::render_sources( $stylesheet['sources'] ); ?>
							</dd>

							<dt><?php esc_html_e( 'CSS Code', 'amp' ); ?></dt>
							<dd>
								<?php
								ob_start();
								echo '<code class="shaken-stylesheet">';
								$open_parens = 0;
								$ins_count   = 0;
								$del_count   = 0;
								foreach ( $stylesheet['shaken_tokens'] as $shaken_token ) {
									if ( $shaken_token[0] ) {
										$ins_count++;
									} else {
										$del_count++;
									}

									if ( is_array( $shaken_token[1] ) ) {
										echo '<span class="declaration-block">';
										$selector_count = count( $shaken_token[1] );
										foreach ( array_keys( $shaken_token[1] ) as $i => $selector ) {
											$included = $shaken_token[1][ $selector ];
											echo $included ? '<ins class="selector">' : '<del class="selector">';
											echo str_repeat( "\t", $open_parens ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

											$selector_html = preg_replace(
												'/(:root|html)(:not\(#_\))+/',
												sprintf(
													'<abbr title="%s">$0</abbr>',
													esc_attr(
														'style_attribute' === $stylesheet['origin']
															?
															__( 'Selector generated to increase specificity so the cascade is preserved for properties moved from style attribute to CSS rules in style[amp-custom].', 'amp' )
															:
															__( 'Selector generated to increase specificity for important properties so that the CSS cascade is preserved. AMP does not allow important properties.', 'amp' )
													)
												),
												esc_html( $selector )
											);
											if ( 'style_attribute' === $stylesheet['origin'] ) {
												$selector_html = preg_replace(
													'/\.amp-wp-\w+/',
													sprintf(
														'<abbr title="%s">$0</abbr>',
														esc_attr__( 'Class name generated during extraction of inline style to style[amp-custom].', 'amp' )
													),
													$selector_html
												);
											}

											echo $selector_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

											if ( $i + 1 < $selector_count ) {
												echo ',';
											}
											echo $included ? '</ins>' : '</del>';
										}

										echo $shaken_token[0] ? '<ins>' : '<del>';
										echo str_repeat( "\t", $open_parens + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										echo '{ ' . esc_html( implode( '; ', $shaken_token[2] ) ) . '; }';
										echo $shaken_token[0] ? '</ins>' : '</del>';

										echo '</span>';
									} elseif ( is_string( $shaken_token[1] ) ) {
										echo $shaken_token[0] ? '<ins class="">' : '<del class="">';

										$parent_count_diff = substr_count( $shaken_token[1], '{' ) - substr_count( $shaken_token[1], '}' );
										if ( $parent_count_diff >= 0 ) {
											echo str_repeat( "\t", $open_parens ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										} else {
											echo str_repeat( "\t", $open_parens + $parent_count_diff ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										}
										$open_parens += $parent_count_diff;

										echo esc_html( $shaken_token[1] );

										echo $shaken_token[0] ? '</ins>' : '</del>';
									}
								}
								echo '</code>';
								$html = trim( ob_get_clean() );

								if ( 0 === $ins_count && 0 === $del_count ) {
									printf(
										'<p><em>%s</em></p>',
										esc_html__( 'The stylesheet was empty after minification (removal of comments and whitespace).', 'amp' )
									);
								} elseif ( 0 === $ins_count ) {
									printf(
										'<p><em>%s</em></p>',
										esc_html__( 'All of the stylesheet was removed during tree-shaking.', 'amp' )
									);
								}

								if ( 0 !== $ins_count || 0 !== $del_count ) {
									if ( $del_count > 0 ) {
										printf(
											'<p><label><input type="checkbox" class="show-removed-styles"> %s</label></p>',
											esc_html__( 'Show styles removed during tree-shaking', 'amp' )
										);
									}
									echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								?>
							</dd>
						</dl>
					</td>
				</tr>
				<?php $row++; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the single URL list table.
	 *
	 * Mainly copied from edit-tags.php.
	 * This is output on the post.php page for amp_validated_url,
	 * where the editor normally would be.
	 * But it's really more similar to /wp-admin/edit-tags.php than a post.php page,
	 * as this outputs a WP_Terms_List_Table of amp_validation_error terms.
	 *
	 * @todo: complete this, as it may need to use more logic from edit-tags.php.
	 * @param WP_Post $post The post for the meta box.
	 * @return void
	 */
	public static function render_single_url_list_table( $post ) {
		if ( self::POST_TYPE_SLUG !== $post->post_type ) {
			return;
		}

		$taxonomy        = AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG;
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object ) {
			wp_die( esc_html__( 'Invalid taxonomy.', 'amp' ) );
		}

		/**
		 * Set the order of the terms in the order of occurrence.
		 *
		 * Note that this function will call \AMP_Validation_Error_Taxonomy::get_term() repeatedly, and the
		 * object cache will be pre-populated with terms due to the term query in the term list table.
		 *
		 * @return WP_Term[]
		 */
		$override_terms_in_occurrence_order = static function() use ( $post ) {
			return wp_list_pluck( AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( $post ), 'term' );
		};

		add_filter( 'get_terms', $override_terms_in_occurrence_order );

		$wp_list_table = _get_list_table( 'WP_Terms_List_Table' );
		get_current_screen()->set_screen_reader_content(
			[
				'heading_pagination' => $taxonomy_object->labels->items_list_navigation,
				'heading_list'       => $taxonomy_object->labels->items_list,
			]
		);

		$wp_list_table->prepare_items();
		$wp_list_table->views();

		// The inline script depends on data from the list table.
		self::$total_errors_for_url = $wp_list_table->get_pagination_arg( 'total_items' );

		?>
		<form class="search-form wp-clearfix" method="get">
			<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $taxonomy ); ?>" />
			<input type="hidden" name="post_type" value="<?php echo esc_attr( $post->post_type ); ?>" />
			<?php $wp_list_table->search_box( esc_html__( 'Search Errors', 'amp' ), 'invalid-url-search' ); ?>
		</form>

		<div id="remove-keep-buttons" class="hidden">
			<button type="button" class="button action remove"><?php esc_html_e( 'Remove', 'amp' ); ?></button>
			<button type="button" class="button action keep"><?php esc_html_e( 'Keep', 'amp' ); ?></button>
			<button type="button" class="button action reviewed-toggle reviewed"><?php esc_html_e( 'Mark reviewed', 'amp' ); ?></button>
			<button type="button" class="button action reviewed-toggle unreviewed"><?php esc_html_e( 'Mark unreviewed', 'amp' ); ?></button>
			<button type="button" class="button action copy-all"><?php esc_html_e( 'Copy to clipboard', 'amp' ); ?></button>
			<div id="vertical-divider"></div>
		</div>
		<div id="url-post-filter" class="alignleft actions">
			<?php AMP_Validation_Error_Taxonomy::render_error_type_filter(); ?>
		</div>
		<?php
		AMP_Validation_Error_Taxonomy::reset_validation_error_row_index();
		$wp_list_table->display();
		?>

		<?php
		remove_filter( 'get_terms', $override_terms_in_occurrence_order );
	}

	/**
	 * Gets the number of amp_validation_error terms that should appear on the single amp_validated_url /wp-admin/post.php page.
	 *
	 * @param int $terms_per_page The number of terms on a page.
	 * @return int The number of terms on the page.
	 */
	public static function get_terms_per_page( $terms_per_page ) {
		global $pagenow;
		if ( 'post.php' === $pagenow ) {
			return PHP_INT_MAX;
		}
		return $terms_per_page;
	}

	/**
	 * Adds the taxonomy to the $_REQUEST, so that it is available in WP_Screen and WP_Terms_List_Table.
	 *
	 * It would be ideal to do this in render_single_url_list_table(),
	 * but set_current_screen() looks to run before that, and that needs access to the 'taxonomy'.
	 */
	public static function add_taxonomy() {
		global $pagenow;

		if ( 'post.php' !== $pagenow || ! isset( $_REQUEST['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$post_id = (int) $_REQUEST['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $post_id ) && self::POST_TYPE_SLUG === get_post_type( $post_id ) ) {
			$_REQUEST['taxonomy'] = AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG;
		}
	}

	/**
	 * Show URL at the top of the edit form in place of the title (since title support is not present).
	 *
	 * @param WP_Post $post Post.
	 */
	public static function print_url_as_title( $post ) {
		if ( self::POST_TYPE_SLUG !== $post->post_type ) {
			return;
		}

		$url = self::get_url_from_post( $post );
		if ( ! $url ) {
			return;
		}

		// @todo For URLs without a queried object, this should eventually be augmented to indicate the query type (e.g. Homepage, Search Results, Date Archive, etc)
		$entity_title = self::get_validated_url_title();
		?>
		<?php if ( $entity_title ) : ?>
			<h2><em><?php echo esc_html( $entity_title ); ?></em></h2>
		<?php endif; ?>
		<h2 class="amp-validated-url">
			<a href="<?php echo esc_url( $url ); ?>">
				<span class="dashicons dashicons-admin-links"></span>
				<?php echo esc_html( $url ); ?>
			</a>
		</h2>
		<?php
	}

	/**
	 * Strip host name from AMP validated URL being printed.
	 *
	 * @param string $title Title.
	 * @param int    $id Post ID.
	 *
	 * @return string Title.
	 */
	public static function filter_the_title_in_post_list_table( $title, $id = null ) {
		if ( function_exists( 'get_current_screen' ) && get_current_screen() && get_current_screen()->base === 'edit' && get_current_screen()->post_type === self::POST_TYPE_SLUG && self::POST_TYPE_SLUG === get_post_type( $id ) ) {
			$title = preg_replace( '#^(\w+:)?//[^/]+#', '', $title );
		}
		return $title;
	}

	/**
	 * Renders the filters on the validated URL post type edit.php page.
	 *
	 * @param string $post_type The slug of the post type.
	 * @param string $which     The location for the markup, either 'top' or 'bottom'.
	 */
	public static function render_post_filters( $post_type, $which ) {
		if ( self::POST_TYPE_SLUG === $post_type && 'top' === $which ) {
			AMP_Validation_Error_Taxonomy::render_error_status_filter();
			AMP_Validation_Error_Taxonomy::render_error_type_filter();
		}
	}

	/**
	 * Gets the URL to recheck the post for AMP validity.
	 *
	 * Appends a query var to $redirect_url.
	 * On clicking the link, it checks if errors still exist for $post.
	 *
	 * @param  string|WP_Post $url_or_post   The post storing the validation error or the URL to check.
	 * @return string The URL to recheck the post.
	 */
	public static function get_recheck_url( $url_or_post ) {
		$args = [
			'action' => self::VALIDATE_ACTION,
		];
		if ( is_string( $url_or_post ) ) {
			$args['url'] = $url_or_post;
		} elseif ( $url_or_post instanceof WP_Post && self::POST_TYPE_SLUG === $url_or_post->post_type ) {
			$args['post'] = $url_or_post->ID;
		}

		return wp_nonce_url(
			add_query_arg( rawurlencode_deep( $args ), admin_url() ),
			self::NONCE_ACTION
		);
	}

	/**
	 * Filters the document title on the single URL page at /wp-admin/post.php.
	 *
	 * @global string $title
	 *
	 * @param string $admin_title Document title.
	 * @return string Filtered document title.
	 */
	public static function filter_admin_title( $admin_title ) {
		global $title;
		if ( self::is_validated_url_admin_screen() ) {

			// This is not ideal to set this in a filter, but it's the only apparent way to set the variable for admin-header.php.
			$title = __( 'AMP Validated URL', 'amp' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			/* translators: Admin screen title. %s: Admin screen name */
			$admin_title = sprintf( __( '%s &#8212; WordPress', 'default' ), $title );
		}
		return $admin_title;
	}

	/**
	 * Determines whether the current screen is for a validated URL.
	 *
	 * @return bool Is screen.
	 */
	private static function is_validated_url_admin_screen() {
		global $pagenow;
		return ! (
			'post.php' !== $pagenow
			||
			! isset( $_GET['post'], $_GET['action'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			||
			self::POST_TYPE_SLUG !== get_post_type( $_GET['post'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);
	}

	/**
	 * Gets the title for validated URL, corresponding with the title for the queried object.
	 *
	 * @param int|WP_Post $post Post for the validated URL.
	 * @return string|null Title, or null if none is available.
	 */
	public static function get_validated_url_title( $post = null ) {
		$name = null;
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}

		// Mainly uses the same conditionals as print_status_meta_box().
		$queried_object = get_post_meta( $post->ID, self::QUERIED_OBJECT_POST_META_KEY, true );
		if ( isset( $queried_object['type'], $queried_object['id'] ) ) {
			$name = null;
			if ( 'post' === $queried_object['type'] && get_post( $queried_object['id'] ) ) {
				$name = html_entity_decode( get_the_title( $queried_object['id'] ), ENT_QUOTES );
			} elseif ( 'term' === $queried_object['type'] && get_term( $queried_object['id'] ) ) {
				$name = get_term( $queried_object['id'] )->name;
			} elseif ( 'user' === $queried_object['type'] && get_user_by( 'ID', $queried_object['id'] ) ) {
				$name = get_user_by( 'ID', $queried_object['id'] )->display_name;
			}
		}

		return $name;
	}

	/**
	 * Filters post row actions.
	 *
	 * Manages links for details, recheck, view, forget, and forget permanently.
	 *
	 * @param array   $actions Row action links.
	 * @param WP_Post $post Current WP post.
	 *
	 * @return array Filtered action links.
	 */
	public static function filter_post_row_actions( $actions, $post ) {
		if ( ! is_object( $post ) || self::POST_TYPE_SLUG !== $post->post_type ) {
			return $actions;
		}

		// Inline edits are not relevant.
		unset( $actions['inline hide-if-no-js'] );

		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_edit_post_link( $post ) ),
				esc_html__( 'Details', 'amp' )
			);
		}

		if ( 'trash' !== $post->post_status && current_user_can( 'edit_post', $post->ID ) ) {
			$url = self::get_url_from_post( $post );
			if ( $url ) {
				$actions['view'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $url ),
					esc_html__( 'View', 'amp' )
				);
			}

			$actions[ self::VALIDATE_ACTION ] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::get_recheck_url( $post ) ),
				esc_html__( 'Recheck', 'amp' )
			);
			if ( self::get_post_staleness( $post ) ) {
				$actions[ self::VALIDATE_ACTION ] = sprintf( '<em>%s</em>', $actions[ self::VALIDATE_ACTION ] );
			}
		}

		// Replace 'Trash' with 'Forget' (which permanently deletes).
		$has_delete = ( isset( $actions['trash'] ) || isset( $actions['delete'] ) );
		unset( $actions['trash'], $actions['delete'] );
		if ( $has_delete ) {
			$actions['delete'] = sprintf(
				'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
				get_delete_post_link( $post->ID, '', true ),
				/* translators: %s: post title */
				esc_attr( sprintf( __( 'Forget &#8220;%s&#8221;', 'amp' ), self::get_url_from_post( $post ) ) ),
				esc_html__( 'Forget', 'amp' )
			);
		}

		$actions = wp_array_slice_assoc(
			$actions,
			[ 'edit', self::VALIDATE_ACTION, 'view', 'delete' ]
		);

		return $actions;
	}

	/**
	 * Filters table views for the post type.
	 *
	 * @param array $views Array of table view links keyed by status slug.
	 * @return array Filtered views.
	 */
	public static function filter_table_views( $views ) {
		// Replace 'Trash' text with 'Forgotten'.
		if ( isset( $views['trash'] ) ) {
			$status = get_post_status_object( 'trash' );

			$views['trash'] = str_replace( $status->label, esc_html__( 'Forgotten', 'amp' ), $views['trash'] );
		}

		return $views;
	}

	/**
	 * Filters messages displayed after bulk updates.
	 *
	 * Note that trashing is replaced with deletion whenever possible, so the trashed and untrashed messages will not be used in practice.
	 *
	 * @param array $messages    Bulk message text.
	 * @param array $bulk_counts Post numbers for the current message.
	 * @return array Filtered messages.
	 */
	public static function filter_bulk_post_updated_messages( $messages, $bulk_counts ) {
		if ( get_current_screen()->id === sprintf( 'edit-%s', self::POST_TYPE_SLUG ) ) {
			$messages['post'] = array_merge(
				$messages['post'],
				[
					/* translators: %s is the number of posts forgotten */
					'deleted'   => _n(
						'%s validated URL forgotten.',
						'%s validated URLs forgotten.',
						$bulk_counts['deleted'],
						'amp'
					),
					/* translators: %s is the number of posts forgotten */
					'trashed'   => _n(
						'%s validated URL forgotten.',
						'%s validated URLs forgotten.',
						$bulk_counts['trashed'],
						'amp'
					),
					/* translators: %s is the number of posts restored from trash. */
					'untrashed' => _n(
						'%s validated URL unforgotten.',
						'%s validated URLs unforgotten.',
						$bulk_counts['untrashed'],
						'amp'
					),
				]
			);
		}

		return $messages;
	}

	/**
	 * Is AMP Enabled on Post
	 *
	 * @param WP_Post $post Post object to check.
	 *
	 * @return bool|void
	 */
	public static function is_amp_enabled_on_post( $post ) {
		if ( empty( $post ) ) {
			return;
		}

		$validation_errors = self::get_invalid_url_validation_errors( $post );
		$counts            = self::count_invalid_url_validation_errors( $validation_errors );
		return 0 === ( $counts['new_rejected'] + $counts['ack_rejected'] );
	}

	/**
	 * Count URL Validation Errors
	 *
	 * @param array $validation_errors Validation errors.
	 *
	 * @return int[]
	 */
	protected static function count_invalid_url_validation_errors( $validation_errors ) {
		$counts = array_fill_keys(
			[ 'new_accepted', 'ack_accepted', 'new_rejected', 'ack_rejected' ],
			0
		);
		foreach ( $validation_errors as $error ) {
			switch ( $error['term']->term_group ) {
				case AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS:
					$counts['new_rejected']++;
					break;
				case AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS:
					$counts['new_accepted']++;
					break;
				case AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS:
					$counts['ack_accepted']++;
					break;
				case AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS:
					$counts['ack_rejected']++;
					break;
			}
		}
		return $counts;
	}
}

<?php
/**
 * Class AMP_Prepare_Data
 *
 * @package AMP
 * @since 2.1
 */

use AmpProject\AmpWP\QueryVar;

/**
 * Class AMP_Prepare_Data
 *
 * @since 2.1
 * @internal
 */
class AMP_Prepare_Data {

	/**
	 * Args for AMP send data.
	 *
	 * @since 2.1
	 *
	 * @var array
	 */
	public $args = [];

	/**
	 * List of URL to send data.
	 *
	 * @since 2.1
	 *
	 * @var string[]
	 */
	public $urls = [];

	/**
	 * Constructor method.
	 *
	 * @since 2.1
	 *
	 * @param array $args Arguments for AMP Send data.
	 */
	public function __construct( $args = [] ) {

		$this->args = ( ! empty( $args ) && is_array( $args ) ) ? $args : [];

		$this->parse_args();
	}

	/**
	 * To parse args for AMP data that will send.
	 *
	 * @since 2.1
	 *
	 * @return void
	 */
	public function parse_args() {

		if ( ! empty( $this->args['urls'] ) && is_array( $this->args['urls'] ) ) {
			$this->urls = array_merge( $this->urls, $this->args['urls'] );
		}

		if ( ! empty( $this->args['term_ids'] ) && is_array( $this->args['term_ids'] ) ) {
			$this->args['term_ids'] = array_map( 'intval', $this->args['term_ids'] );
			$this->args['term_ids'] = array_filter( $this->args['term_ids'] );

			foreach ( $this->args['term_ids'] as $term_id ) {
				$url = get_term_link( $term_id );

				if ( ! empty( $url ) && ! is_wp_error( $url ) ) {
					$this->urls[] = $url;
				}
			}
		}

		if ( ! empty( $this->args['post_ids'] ) && is_array( $this->args['post_ids'] ) ) {
			$this->args['post_ids'] = array_map( 'intval', $this->args['post_ids'] );
			$this->args['post_ids'] = array_filter( $this->args['post_ids'] );

			foreach ( $this->args['post_ids'] as $post_id ) {

				$url = get_permalink( $post_id );

				if ( ! empty( $url ) && ! is_wp_error( $url ) ) {
					$this->urls[] = $url;
				}
			}
		}

		$this->urls = array_map( __CLASS__ . '::normalize_url_for_storage', $this->urls );
		$this->urls = array_values( array_unique( $this->urls ) );

	}

	/**
	 * Normalize a URL for storage.
	 *
	 * The AMP query param is removed to facilitate switching between standard and transitional.
	 * The URL scheme is also normalized to HTTPS to help with transition from HTTP to HTTPS.
	 *
	 * @since 2.1
	 *
	 * @reference AMP_Validated_URL_Post_Type::normalize_url_for_storage
	 *
	 * @param string $url URL.
	 *
	 * @return string Normalized URL.
	 */
	public static function normalize_url_for_storage( $url ) {

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
	 * To get amp data to send it to compatibility server.
	 *
	 * @since 2.1
	 *
	 * @return array
	 */
	public function get_data() {

		$amp_urls = $this->get_amp_urls();

		$request_data = [
			'site_url'      => static::get_home_url(),
			'site_info'     => $this->get_site_info(),
			'plugins'       => $this->get_plugin_info(),
			'themes'        => $this->get_theme_info(),
			'errors'        => array_values( $amp_urls['errors'] ),
			'error_sources' => array_values( $amp_urls['error_sources'] ),
			'urls'          => array_values( $amp_urls['urls'] ),
			'error_log'     => $this->get_error_log(),
		];

		return $request_data;
	}

	/**
	 * To get site info.
	 *
	 * @since 2.1
	 *
	 * @return array Site information.
	 */
	protected function get_site_info() {

		$wp_type = 'single';

		if ( is_multisite() ) {
			$wp_type = ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) ? 'subdomain' : 'subdir';
		}

		$active_theme = wp_get_theme();
		$active_theme = static::normalize_theme_info( $active_theme );

		$amp_settings = \AMP_Options_Manager::get_options();
		$amp_settings = ( ! empty( $amp_settings ) && is_array( $amp_settings ) ) ? $amp_settings : [];

		$loopback_status = '';

		if ( class_exists( 'Health_Check_Loopback' ) ) {
			$loopback_status = \Health_Check_Loopback::can_perform_loopback();
			$loopback_status = ( ! empty( $loopback_status->status ) ) ? $loopback_status->status : '';
		}

		$site_info = [
			'site_url'                     => static::get_home_url(),
			'site_title'                   => get_bloginfo( 'site_title' ),
			'php_version'                  => phpversion(),
			'mysql_version'                => '',
			'wp_version'                   => get_bloginfo( 'version' ),
			'wp_language'                  => get_bloginfo( 'language' ),
			'wp_https_status'              => is_ssl() ? true : false,
			'wp_multisite'                 => $wp_type,
			'wp_active_theme'              => $active_theme,
			'object_cache_status'          => wp_using_ext_object_cache(),
			'libxml_version'               => ( defined( 'LIBXML_VERSION' ) ) ? LIBXML_VERSION : '',
			'is_defined_curl_multi'        => ( function_exists( 'curl_multi_init' ) ),
			'stylesheet_transient_caching' => '',
			'loopback_requests'            => $loopback_status,
			'amp_mode'                     => ( ! empty( $amp_settings['theme_support'] ) ) ? $amp_settings['theme_support'] : '',
			'amp_version'                  => ( ! empty( $amp_settings['version'] ) ) ? $amp_settings['version'] : '',
			'amp_plugin_configured'        => ( ! empty( $amp_settings['plugin_configured'] ) ) ? true : false,
			'amp_all_templates_supported'  => ( ! empty( $amp_settings['all_templates_supported'] ) ) ? true : false,
			'amp_supported_post_types'     => ( ! empty( $amp_settings['supported_post_types'] ) && is_array( $amp_settings['supported_post_types'] ) ) ? $amp_settings['supported_post_types'] : [],
			'amp_supported_templates'      => ( ! empty( $amp_settings['supported_templates'] ) && is_array( $amp_settings['supported_templates'] ) ) ? $amp_settings['supported_templates'] : [],
			'amp_mobile_redirect'          => ( ! empty( $amp_settings['mobile_redirect'] ) ) ? true : false,
			'amp_reader_theme'             => ( ! empty( $amp_settings['reader_theme'] ) ) ? $amp_settings['reader_theme'] : '',
		];

		return $site_info;
	}

	/**
	 * To get list of active plugin's information.
	 *
	 * @since 2.1
	 *
	 * @return array List of plugin detail.
	 */
	protected function get_plugin_info() {

		$active_plugins = get_option( 'active_plugins' );

		if ( is_multisite() ) {
			$network_wide_activate_plugins = get_site_option( 'active_sitewide_plugins' );
			$active_plugins                = array_merge( $active_plugins, $network_wide_activate_plugins );
		}

		$active_plugins = array_values( array_unique( $active_plugins ) );
		$plugin_info    = array_map( __CLASS__ . '::normalize_plugin_info', $active_plugins );
		$plugin_info    = array_filter( $plugin_info );

		return $plugin_info;
	}

	/**
	 * To get active theme info.
	 *
	 * @since 2.1
	 *
	 * @return array List of theme information.
	 */
	protected function get_theme_info() {

		$themes   = [ wp_get_theme() ];
		$response = array_map( __CLASS__ . '::normalize_theme_info', $themes );
		$response = array_filter( $response );

		return $response;
	}

	/**
	 * To get error log.
	 *
	 * @since 2.1
	 *
	 * @return array Error log contents and log_errors ini setting.
	 */
	protected function get_error_log() {

		$file        = file( ini_get( 'error_log' ) );
		$max_lines   = max( 0, count( $file ) - 200 );
		$file_length = count( $file );
		$contents    = [];

		for ( $i = $max_lines; $i < $file_length; $i ++ ) {
			if ( ! empty( $file[ $i ] ) ) {
				$contents[] = sanitize_text_field( $file[ $i ] );
			}
		}

		return [
			'log_errors' => ini_get( 'log_errors' ),
			'contents'   => implode( "\n", $contents ),
		];
	}

	/**
	 * To get plugin information by plugin file.
	 *
	 * @since 2.1
	 *
	 * @param string $plugin_file Plugin file.
	 *
	 * @return array Plugin detail.
	 */
	protected static function normalize_plugin_info( $plugin_file ) {

		$absolute_plugin_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_file;
		$plugin_data          = get_plugin_data( $absolute_plugin_file );

		$slug = explode( '/', $plugin_file );
		$slug = $slug[0];

		$amp_options        = get_option( 'amp-options' );
		$suppressed_plugins = ( ! empty( $amp_options['suppressed_plugins'] ) && is_array( $amp_options['suppressed_plugins'] ) ) ? $amp_options['suppressed_plugins'] : [];

		$suppressed_plugin_list = array_keys( $suppressed_plugins );

		if ( empty( $plugin_data['Name'] ) ) {
			return [];
		}

		return [
			'name'              => $plugin_data['Name'],
			'slug'              => $slug,
			'plugin_url'        => $plugin_data['PluginURI'],
			'version'           => $plugin_data['Version'],
			'author'            => $plugin_data['AuthorName'],
			'author_url'        => $plugin_data['AuthorURI'],
			'requires_wp'       => $plugin_data['RequiresWP'],
			'requires_php'      => $plugin_data['RequiresPHP'],
			'is_active'         => is_plugin_active( $plugin_file ),
			'is_network_active' => is_plugin_active_for_network( $plugin_file ),
			'is_suppressed'     => in_array( $slug, $suppressed_plugin_list, true ) ? $suppressed_plugins[ $slug ]['last_version'] : '',
		];

	}

	/**
	 * To normalize theme information.
	 *
	 * @since 2.1
	 *
	 * @param \WP_Theme $theme_object Theme object.
	 *
	 * @return array Normalize theme information.
	 */
	protected static function normalize_theme_info( $theme_object ) {

		if ( empty( $theme_object ) || ! is_a( $theme_object, 'WP_Theme' ) ) {
			return [];
		}

		$active_theme      = wp_get_theme();
		$active_theme_slug = '';
		$parent_theme      = '';

		if ( ! empty( $active_theme ) && is_a( $active_theme, 'WP_Theme' ) ) {
			$active_theme_slug = $active_theme->get_stylesheet();
		}

		if ( ! empty( $theme_object->parent() ) && ! is_a( $theme_object->parent(), 'WP_Theme' ) ) {
			$parent_theme = $theme_object->parent()->get_stylesheet();
		}

		$tags = $theme_object->get( 'Tags' );
		$tags = ( ! empty( $tags ) && is_array( $tags ) ) ? $tags : [];

		$theme_data = [
			'name'         => $theme_object->get( 'Name' ),
			'slug'         => $theme_object->get_stylesheet(),
			'version'      => $theme_object->get( 'Version' ),
			'status'       => $theme_object->get( 'Status' ),
			'tags'         => $tags,
			'text_domain'  => $theme_object->get( 'TextDomain' ),
			'requires_wp'  => $theme_object->get( 'RequiresWP' ),
			'requires_php' => $theme_object->get( 'RequiresPHP' ),
			'theme_url'    => $theme_object->get( 'ThemeURI' ),
			'author'       => $theme_object->get( 'Author' ),
			'author_url'   => $theme_object->get( 'AuthorURI' ),
			'is_active'    => ( $theme_object->get_stylesheet() === $active_theme_slug ),
			'parent_theme' => $parent_theme,
		];

		return $theme_data;
	}

	/**
	 * To get list of AMP errors.
	 *
	 * @since 2.1
	 *
	 * @return array List of errors.
	 */
	protected static function get_errors() {

		$error_data      = [];
		$amp_error_terms = get_terms(
			[
				'taxonomy'        => 'amp_validation_error',
				'hide_empty'      => true,
				'suppress_filter' => true,
			]
		);

		if ( empty( $amp_error_terms ) || ! is_array( $amp_error_terms ) || is_wp_error( $amp_error_terms ) ) {
			return [];
		}

		$amp_error_terms = array_values( $amp_error_terms );

		foreach ( $amp_error_terms as $index => $error_term ) {

			if ( empty( $error_term ) || ! is_a( $error_term, 'WP_Term' ) ) {
				continue;
			}

			// Remove site specific detail like site home_url() from error detail.
			$description = strtolower( trim( $error_term->description ) );
			$description = static::remove_domain( $description );

			// Convert that into array.
			$error_detail = json_decode( $description, true );

			$error_detail['text'] = ( ! empty( $error_detail['text'] ) ) ? trim( $error_detail['text'] ) : '';

			ksort( $error_detail );

			/**
			 * Generate new slug after removing site specific data.
			 */
			$error_detail['error_slug'] = static::generate_hash( $error_detail );

			/**
			 * Keep the slug as key to quickly get error detail.
			 */
			$error_data[ $error_term->slug ] = $error_detail;
		}

		return $error_data;
	}

	/**
	 * Normalises the error data.
	 *
	 * @since 2.1
	 *
	 * @param array $error_data Error data array.
	 *
	 * @return array|mixed|null
	 */
	protected static function normalize_error( $error_data ) {

		if ( empty( $error_data ) || ! is_array( $error_data ) ) {
			return [];
		}

		unset( $error_data['sources'] );

		$error_data['text'] = ( ! empty( $error_data['text'] ) ) ? trim( $error_data['text'] ) : '';

		$error_data = wp_json_encode( $error_data );
		$error_data = static::remove_domain( $error_data );
		$error_data = json_decode( $error_data, true );

		ksort( $error_data );

		/**
		 * Generate new slug after removing site specific data.
		 */
		$error_data['error_slug'] = static::generate_hash( $error_data );

		return $error_data;
	}

	/**
	 * To normalize the error source data.
	 *
	 * @since 2.1
	 *
	 * @param array $source Error source detail.
	 *
	 * @return array Normalized error source data.
	 */
	protected static function normalize_error_source( $source ) {

		if ( empty( $source ) || ! is_array( $source ) ) {
			return [];
		}

		static $plugin_versions = [];
		static $theme_versions  = [];

		/**
		 * All plugin info
		 */
		if ( empty( $plugin_versions ) || ! is_array( $plugin_versions ) ) {

			$plugin_list = get_plugins();
			$plugin_list = array_keys( $plugin_list );
			$plugin_list = array_values( array_unique( $plugin_list ) );
			$plugin_list = array_map( __CLASS__ . '::normalize_plugin_info', $plugin_list );

			foreach ( $plugin_list as $plugin ) {
				$plugin_versions[ $plugin['slug'] ] = $plugin['version'];
			}
		}

		/**
		 * All theme info.
		 */
		if ( empty( $theme_versions ) || ! is_array( $theme_versions ) ) {

			$theme_list = wp_get_themes();

			foreach ( $theme_list as $theme ) {
				if ( ! empty( $theme ) && is_a( $theme, 'WP_Theme' ) ) {
					$theme_versions[ $theme->get_stylesheet() ] = $theme->get( 'Version' );
				}
			}
		}

		/**
		 * Normalize error source.
		 */

		$allowed_types  = [ 'plugin', 'theme' ];
		$source['type'] = ( ! empty( $source['type'] ) ) ? strtolower( trim( $source['type'] ) ) : '';

		/**
		 * Do not include wp-core sources.
		 */
		if ( empty( $source['type'] ) || ! in_array( $source['type'], $allowed_types, true ) ) {
			return [];
		}

		if ( 'plugin' === $source['type'] ) {
			$source['version'] = $plugin_versions[ $source['name'] ];
		} elseif ( 'theme' === $source['type'] ) {
			$source['version'] = $theme_versions[ $source['name'] ];
		}

		if ( ! empty( $source['text'] ) ) {
			$source['text'] = trim( $source['text'] );
			$source['text'] = static::remove_domain( $source['text'] );
		}

		// Generate error source slug.
		$error_source_slug = self::generate_hash( $source );

		// Update source information. Add error_slug and source_slug.
		$source['error_source_slug'] = $error_source_slug;

		ksort( $source );

		return $source;
	}

	/**
	 * To get amp validated URLs.
	 *
	 * @since 2.1
	 *
	 * @return array List amp validated URLs.
	 */
	protected function get_amp_urls() {

		global $wpdb;

		$query = "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE `post_type`='amp_validated_url'";

		if ( ! empty( $this->urls ) && is_array( $this->urls ) ) {
			$placeholder = implode( ', ', array_fill( 0, count( $this->urls ), '%s' ) );
			$query      .= ' AND post_title IN ( ' . $placeholder . ' ) ';
			$query_data  = $this->urls;

		} else {

			$query     .= ' LIMIT %d, %d';
			$query_data = [ 0, 100 ];

		}

		// This query needs to be uncached and it is prepared, yet there's false positive in PHPCS because of using variable instead of string in prepare.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$amp_error_posts  = $wpdb->get_results( $wpdb->prepare( $query, $query_data ) );
		$amp_invalid_urls = [];

		/**
		 * Error Information
		 */
		$error_list = [];

		/**
		 * Error Source information.
		 */
		$error_source_list = [];

		/**
		 * Post loop.
		 */
		foreach ( $amp_error_posts as $amp_error_post ) {

			if ( empty( $amp_error_post ) ) {
				continue;
			}

			// Empty array for post staleness means post is NOT stale.
			if ( ! empty( \AMP_Validated_URL_Post_Type::get_post_staleness( $amp_error_post->ID ) ) ) {
				continue;
			}

			$post_errors_raw = json_decode( $amp_error_post->post_content, true );
			$post_errors     = [];

			if ( empty( $post_errors_raw ) ) {
				continue;
			}

			/**
			 * Error loop.
			 */
			foreach ( $post_errors_raw as $post_error ) {

				$error_data    = ( ! empty( $post_error['data'] ) && is_array( $post_error['data'] ) ) ? $post_error['data'] : [];
				$error_sources = ( ! empty( $error_data['sources'] ) && is_array( $error_data['sources'] ) ) ? $error_data['sources'] : [];

				if ( empty( $error_data ) || empty( $error_sources ) ) {
					continue;
				}

				unset( $error_data['sources'] );
				$error_data = static::normalize_error( $error_data );

				/**
				 * Store error data in all error list.
				 */
				if ( ! empty( $error_data ) && is_array( $error_data ) ) {
					$error_list[ $error_data['error_slug'] ] = $error_data;
				}

				/**
				 * Source loop.
				 */
				foreach ( $error_sources as $index => $source ) {
					$source['error_slug']    = $error_data['error_slug'];
					$error_sources[ $index ] = static::normalize_error_source( $source );

					/**
					 * Store error source in all error_source list.
					 */
					if ( ! empty( $error_sources[ $index ] ) && is_array( $error_sources[ $index ] ) ) {
						$error_source_list[ $error_sources[ $index ]['error_source_slug'] ] = $error_sources[ $index ];
					}
				}

				$error_sources      = array_filter( $error_sources );
				$error_source_slugs = wp_list_pluck( $error_sources, 'error_source_slug' );
				$error_source_slugs = array_values( array_unique( $error_source_slugs ) );

				if ( ! empty( $error_source_slugs ) && is_array( $error_source_slugs ) ) {
					$post_errors[] = [
						'error_slug' => $error_data['error_slug'],
						'sources'    => $error_source_slugs,
					];
				}
			}

			// Object information.
			$amp_queried_object = get_post_meta( $amp_error_post->ID, '_amp_queried_object', true );
			$object_type        = ( ! empty( $amp_queried_object['type'] ) ) ? $amp_queried_object['type'] : '';
			$object_subtype     = '';

			if ( empty( $object_type ) ) {

				if ( false !== strpos( $amp_error_post->post_title, '?s=' ) ) {
					$object_type = 'search';
				}
			}

			switch ( $object_type ) {
				case 'post':
					$object_subtype = get_post( $amp_queried_object['id'] )->post_type;
					break;
				case 'term':
					$object_subtype = get_term( $amp_queried_object['id'] )->taxonomy;
					break;
				case 'user':
					break;
			}

			// Stylesheet info.
			$stylesheet_info = static::get_stylesheet_info( $amp_error_post->ID );

			$amp_invalid_urls[] = [
				'url'                   => $amp_error_post->post_title,
				'object_type'           => $object_type,
				'object_subtype'        => $object_subtype,
				'css_size_before'       => ( ! empty( $stylesheet_info['css_size_before'] ) ) ? $stylesheet_info['css_size_before'] : '',
				'css_size_after'        => ( ! empty( $stylesheet_info['css_size_after'] ) ) ? $stylesheet_info['css_size_after'] : '',
				'css_size_excluded'     => ( ! empty( $stylesheet_info['css_size_excluded'] ) ) ? $stylesheet_info['css_size_excluded'] : '',
				'css_budget_percentage' => ( ! empty( $stylesheet_info['css_budget_percentage'] ) ) ? $stylesheet_info['css_budget_percentage'] : '',
				'errors'                => $post_errors,
			];
		}

		return [
			'errors'        => $error_list,
			'error_sources' => $error_source_list,
			'urls'          => $amp_invalid_urls,
		];

	}

	/**
	 * Get style sheet info of the post.
	 *
	 * @since 2.1
	 *
	 * Reference: AMP_Validated_URL_Post_Type::print_stylesheets_meta_box()
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array AMP stylesheet used info.
	 */
	protected static function get_stylesheet_info( $post_id ) {

		$stylesheets = get_post_meta( $post_id, \AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY, true );

		if ( empty( $stylesheets ) ) {
			return [];
		}

		$stylesheets             = json_decode( $stylesheets, true );
		$style_custom_cdata_spec = null;

		foreach ( \AMP_Allowed_Tags_Generated::get_allowed_tag( 'style' ) as $spec_rule ) {
			if ( isset( $spec_rule[ \AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) && \AMP_Style_Sanitizer::STYLE_AMP_CUSTOM_SPEC_NAME === $spec_rule[ \AMP_Rule_Spec::TAG_SPEC ]['spec_name'] ) {
				$style_custom_cdata_spec = $spec_rule[ \AMP_Rule_Spec::CDATA ];
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
				$excluded_stylesheets ++;
				$stylesheets[ $i ]['status'] = $excluded_status;
			}
		}

		$percentage_budget_used = ( ( $included_final_size + $excluded_final_size ) / $style_custom_cdata_spec['max_bytes'] ) * 100;
		$response               = [
			'css_size_before'       => intval( $included_original_size + $excluded_original_size ),
			'css_size_after'        => intval( $included_final_size + $excluded_final_size ),
			'css_size_excluded'     => intval( $excluded_stylesheets ),
			'css_budget_percentage' => round( $percentage_budget_used, 1 ),
		];

		return $response;

	}

	/**
	 * To get home url of the site.
	 * Note: It will give home url without protocol.
	 *
	 * @since 2.1
	 *
	 * @return string Home URL.
	 */
	public static function get_home_url() {

		$home_url = home_url();
		$home_url = strtolower( trim( $home_url ) );

		$http_protocol = wp_parse_url( $home_url, PHP_URL_SCHEME );

		$home_url = str_replace( "$http_protocol://", '', $home_url );
		$home_url = untrailingslashit( $home_url );

		return $home_url;
	}

	/**
	 * To remove home url from the content.
	 *
	 * @since 2.1
	 *
	 * @param string $content Content from home_url need to remove.
	 *
	 * @return string Content after removing home_url.
	 */
	protected static function remove_domain( $content ) {

		if ( empty( $content ) ) {
			return '';
		}

		$home_url = static::get_home_url();
		$home_url = str_replace( [ '.', '/' ], [ '\.', '\\\\{1,5}\/' ], $home_url );

		/**
		 * Reference: https://regex101.com/r/c25pNF/1
		 */
		$regex = "/http[s]?:\\\\{0,5}\/\\\\{0,5}\/$home_url/mU";

		$content = preg_replace( $regex, '', $content );

		return $content;
	}

	/**
	 * To generate hash of object.
	 *
	 * @since 2.1
	 *
	 * @param string|array|object $object Object for that hash need to generate.
	 *
	 * @return string Hash value of provided object.
	 */
	protected static function generate_hash( $object ) {

		if ( empty( $object ) ) {
			return '';
		}

		if ( is_object( $object ) ) {
			$object = (array) $object;
		}

		if ( is_array( $object ) ) {
			ksort( $object );
			$object = wp_json_encode( $object );
		}

		$object = trim( $object );
		$hash   = hash( 'sha256', $object );

		return $hash;
	}
}

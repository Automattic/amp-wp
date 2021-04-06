<?php
use AmpProject\AmpWP\QueryVar;
use AmpProject\AmpWP\Admin\GoogleFonts;

define( 'AMP_SEND_DATA_SERVER_ENDPOINT', 'https://rich-torus-221321.ue.r.appspot.com' );

/**
 * Admin page styles.
 */
add_action(
	'admin_enqueue_scripts',
	function( $hook ) {
		if ( 'amp_page_amp-support' !== $hook ) {
			return;
		}
		// Google Fonts & AMP Settings
		$f = new GoogleFonts();
		wp_enqueue_style(
			'amp-settings',
			amp_get_asset_url( 'css/amp-settings.css' ),
			[
				$f->get_handle(),
				'wp-components',
			],
			AMP__VERSION
		);

		// CodeMirror
		// $cm_settings['codeEditor'] = wp_enqueue_code_editor( array('type' => 'application/json') );
		// wp_localize_script( 'jquery', 'cm_settings', $cm_settings );
		// wp_enqueue_script( 'wp-theme-plugin-editor' );
		// wp_enqueue_style( 'wp-codemirror' );
	}
);

/**
 * Admin page template.
 */
add_action(
	'admin_menu',
	function() {

		add_submenu_page(
			AMP_Options_Manager::OPTION_NAME,
			esc_html__( 'Support', 'amp' ),
			esc_html__( 'Support', 'amp' ),
			'manage_options',
			'amp-support',
			function() {
				$post_id = filter_input( INPUT_GET, 'post_id', FILTER_SANITIZE_NUMBER_INT );
				$data    = AMP_Prepare_Data::get_data( $post_id );

				?>
				<style>
					.amp li {
						list-style-type: disc;
						margin-left: 20px;
					}
					.amp a.is-primary {
						margin-top:0 !important;
					}
					.amp .settings-welcome h3 {
						margin-top: 2rem !important;
					}
					.amp .settings-welcome detail p {
						margin-top: 1rem !important;
					}
					.amp-drawer__panel-body-inner {
						padding-left: 2rem;
					}
					#code {
						width: 95%;
						height: 50vh;
						font-family: monospace;
					}
				</style>
				<div class="amp">

				<h2><?php echo esc_html__( 'AMP Support', 'amp' ); ?></h2>

				<div class="settings-welcome">
					<div class="selectable selectable--left">
						<div class="settings-welcome__illustration">
							<svg width="62" height="51" viewBox="0 0 62 51" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#welcome-svg-clip)"><path d="M19.0226 3.89844H39.5226C45.0226 3.89844 49.4226 8.29844 49.4226 13.7984V34.2984C49.4226 39.7984 45.0226 44.1984 39.5226 44.1984H19.0226C13.5226 44.1984 9.12256 39.7984 9.12256 34.2984V13.7984C9.12256 8.29844 13.5226 3.89844 19.0226 3.89844Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M17.8227 11.1992C18.7227 11.1992 19.4227 11.8992 19.4227 12.7992V35.6992C19.4227 36.5992 18.7227 37.2992 17.8227 37.2992C16.9227 37.2992 16.2227 36.5992 16.2227 35.6992V12.6992C16.2227 11.7992 16.9227 11.1992 17.8227 11.1992Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M17.8228 21.9C19.5901 21.9 21.0228 20.4673 21.0228 18.7C21.0228 16.9327 19.5901 15.5 17.8228 15.5C16.0555 15.5 14.6228 16.9327 14.6228 18.7C14.6228 20.4673 16.0555 21.9 17.8228 21.9Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M29.3227 37.0977C28.4227 37.0977 27.7227 36.3977 27.7227 35.4977V12.6977C27.7227 11.7977 28.4227 11.0977 29.3227 11.0977C30.2227 11.0977 30.9227 11.7977 30.9227 12.6977V35.5977C30.9227 36.3977 30.2227 37.0977 29.3227 37.0977Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M40.9225 37.0977C40.0225 37.0977 39.3225 36.3977 39.3225 35.4977V12.6977C39.3225 11.7977 40.0225 11.0977 40.9225 11.0977C41.8225 11.0977 42.5225 11.7977 42.5225 12.6977V35.5977C42.5225 36.3977 41.8225 37.0977 40.9225 37.0977Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M40.9227 24.0992C42.69 24.0992 44.1227 22.6665 44.1227 20.8992C44.1227 19.1319 42.69 17.6992 40.9227 17.6992C39.1553 17.6992 37.7227 19.1319 37.7227 20.8992C37.7227 22.6665 39.1553 24.0992 40.9227 24.0992Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M29.2227 30.9977C30.99 30.9977 32.4227 29.565 32.4227 27.7977C32.4227 26.0303 30.99 24.5977 29.2227 24.5977C27.4554 24.5977 26.0227 26.0303 26.0227 27.7977C26.0227 29.565 27.4554 30.9977 29.2227 30.9977Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M47.3225 5.19784C47.9225 3.69784 49.9225 0.797843 53.4225 1.49784" stroke="#2459E7" stroke-width="2" stroke-linecap="round"></path><path d="M50.5227 7.19675C51.7227 6.69675 54.5227 6.29675 56.2227 9.09675" stroke="#2459E7" stroke-width="2" stroke-linecap="round"></path><path d="M12.4225 44.7969C11.9225 45.7969 10.9225 48.1969 11.1225 49.3969" stroke="#2459E7" stroke-width="2" stroke-linecap="round"></path><path d="M8.92266 43.6992C8.42266 44.0992 7.52266 44.6992 6.72266 45.1992" stroke="#2459E7" stroke-width="2" stroke-linecap="round"></path><path d="M7.42261 39.8984C5.92261 40.4984 2.82261 41.5984 1.92261 41.7984" stroke="#2459E7" stroke-width="2" stroke-linecap="round"></path><path d="M3.92251 48.8992C4.80617 48.8992 5.52251 48.1829 5.52251 47.2992C5.52251 46.4156 4.80617 45.6992 3.92251 45.6992C3.03885 45.6992 2.32251 46.4156 2.32251 47.2992C2.32251 48.1829 3.03885 48.8992 3.92251 48.8992Z" fill="#2459E7"></path><path d="M60.1227 12.7C61.0064 12.7 61.7227 11.9837 61.7227 11.1C61.7227 10.2163 61.0064 9.5 60.1227 9.5C59.2391 9.5 58.5227 10.2163 58.5227 11.1C58.5227 11.9837 59.2391 12.7 60.1227 12.7Z" fill="#2459E7"></path></g><defs><clipPath id="welcome-svg-clip"><rect width="60.8" height="50" fill="white" transform="translate(0.922607 0.398438)"></rect></clipPath></defs></svg>
						</div>
						<div>
							<h2>
							<?php if ( ! empty( $post_id ) ) : ?>
								<?php echo esc_html__( 'Send diagnostic data for ', 'amp' ); echo esc_url( get_the_title( $post_id ) ); ?>
							<?php else : ?>
								<?php echo sprintf( 
									esc_html__( 'Send diagnostic data for %s', 'amp' ),
									' &nbsp;<a href="edit.php?post_type=amp_validated_url">' . count( $data['urls'] ) . ' ' . _n( count( $data['urls'] ), 'validated URL:', 'validated URLs:', 'amp' ) . '</a>'
								); ?>
							<?php endif; ?>
								<svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg"><mask id="check-circle-mask" mask-type="alpha" maskUnits="userSpaceOnUse" x="2" y="2" width="21" height="21"><path fill-rule="evenodd" clip-rule="evenodd" d="M12.7537 2.60938C7.23366 2.60938 2.75366 7.08938 2.75366 12.6094C2.75366 18.1294 7.23366 22.6094 12.7537 22.6094C18.2737 22.6094 22.7537 18.1294 22.7537 12.6094C22.7537 7.08938 18.2737 2.60938 12.7537 2.60938ZM12.7537 20.6094C8.34366 20.6094 4.75366 17.0194 4.75366 12.6094C4.75366 8.19938 8.34366 4.60938 12.7537 4.60938C17.1637 4.60938 20.7537 8.19938 20.7537 12.6094C20.7537 17.0194 17.1637 20.6094 12.7537 20.6094ZM10.7537 14.7794L17.3437 8.18937L18.7537 9.60938L10.7537 17.6094L6.75366 13.6094L8.16366 12.1994L10.7537 14.7794Z" fill="white"></path></mask><g mask="url(#check-circle-mask)"><rect x="0.753662" y="0.609375" width="24" height="24" fill="#2459E7"></rect></g></svg>
							</h2>

							<?php if ( ! empty( $data['urls'] ) && empty( $post_id ) ) : ?>
							<ul>
								<?php foreach( $data['urls'] as $url ) : ?>
									<li><?php echo esc_html( $url['url'] ); ?></li>
								<?php endforeach; ?>
							</ul>
							<?php else: ?>
							<ul>
								<li><a href="<?php echo esc_url( remove_query_arg( 'post_id') ); ?>">
									<?php esc_html_e( 'Switch to all verified URLs.', 'amp' ); ?>
								</a></li>
							</ul>
							<?php endif; ?>

							<a href="#" class="components-button is-primary"><?php echo esc_html__( 'Send Diagnostics', 'amp' ); ?></a>

							<p id="status"></p>
							
							<detail>
								<p>
									<?php
										esc_html_e( 'Clicking this button will return a unique ID suitable for sharing in a support forum for further guidance and information.', 'amp' );
									?>
								</p>
								<ul>
									<li><a href="https://wordpress.org/support/plugin/amp/" target="_blank"><?php esc_html_e( 'WordPress.org support forum', 'amp' ); ?></a></li>
									<li><a href="https://github.com/ampproject/amp-wp/issues" target="_blank"><?php esc_html_e( 'GitHub issues', 'amp' ); ?></a></li>
								</ul>
							</detail>

						</div>
					</div>
				</div>

				<div class="amp-drawer amp-drawer--handle-type-full-width amp-drawer--opened selectable selectable--left">
					<div class="components-panel__body amp-drawer__panel-body is-opened">
						<h2 class="components-panel__body-title">
							<button type="button" aria-expanded="true" class="components-button components-panel__body-toggle">
								<span aria-hidden="true">
								<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="components-panel__arrow" role="img" aria-hidden="true" focusable="false"><path d="M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z"></path></svg>
								</span>
								<div class="amp-drawer__heading"><h3>Details</h3></div>
							</button>
						</h2>
					<div class="amp-drawer__panel-body-inner"><div>
						<summary>
							<h4><?php echo esc_html__( 'The following data will be sent:', 'amp' ); ?></h4>

							<ul id="data">

								<?php if ( isset( $data['site_info'] ) ) : ?>
								<li>
									<a href="site-health.php"><?php esc_html_e( 'Site Health info', 'amp' ); ?></a>
								</li>
								<?php endif; ?>

								<?php if ( is_array( $data['plugins'] ) ) : ?>
								<li>
									<a href="plugins.php"><?php esc_html_e( 'List of', 'amp' ); ?> <?php echo count( $data['plugins'] ); ?> <?php echo esc_html( _n( 'active plugin', 'active plugins', count( $data['plugins'] ), 'amp' ) ); ?></a>
								</li>
								<?php endif; ?>

								<?php if ( is_array( $data['themes'] ) ) : ?>
								<li>
									<a href="themes.php"><?php esc_html_e( 'Active theme info', 'amp' ); ?></a>
								</li>
								<?php endif; ?>

								<?php if ( is_array( $data['errors'] ) ) : ?>
								<li>
									<a href="edit.php?post_type=amp_validated_url"><?php echo count( $data['errors'] ); ?> <?php echo esc_html( _n( 'error', 'errors', count( $data['plugins'] ), 'amp' ) ); ?></a>
								</li>
								<?php endif; ?>

								<?php if ( is_array( $data['urls'] ) ) : ?>
								<li>
									<a href="edit.php?post_type=amp_validated_url"><?php echo count( $data['urls'] ); ?> <?php echo esc_html( _n( 'validated URL', 'validated URLs', count( $data['urls'] ), 'amp' ) ); ?></a>
								</li>
								<?php endif; ?>
							</ul>
						</summary>

						<textarea id="code"><?php
							echo esc_textarea( wp_json_encode( $data, JSON_PRETTY_PRINT ) ); ?>
						</textarea>

					</div></div>
				</div>

				<script>
					jQuery( document ).ready( function( $ ){
						$( 'a.is-primary' ).click(function(){
							$.ajax({
								url: 'admin-ajax.php',
								data: {
									'action': 'amp-diagnostic',
									'post_id': '<?php echo $post_id ?>',
									'_ajax_nonce': '<?php echo wp_create_nonce( 'amp-diagnostic' ) ?>',
								},
								dataType: 'json',
								type: 'GET',
								beforeSend: function(){
									$('#status').html(
										'<?php echo esc_html__( 'Sending...', 'amp' ); ?>'
									);
								},
								success: function( d ) {
									console.log( d );
									if ( 'ok' === d.status ) {
										$('#status').text(
											'<?php echo esc_html__( 'Diagnostics sent.', 'amp' ); ?>'
										);
									}
									if ( 'fail' === d.status ) {
										$('#status').text(
											'<?php echo esc_html__( 'Sending failed. Please try again.', 'amp' ); ?>'
										);
									}
								}
							} );
							return false;
						} );

						$('.amp-drawer__panel-body-inner').hide();
						$('.components-panel__body-toggle').click( function(){
							$('.amp-drawer__panel-body-inner').slideToggle();
							if ( $('.amp-drawer__panel-body').hasClass( 'is-opened' ) ) {
								$('.amp-drawer__panel-body')
									.toggleClass( 'is-opened' )
									.find('svg').css( 'transform', 'rotate(180deg)' );
							}else {
								$('.amp-drawer__panel-body')
									.toggleClass( 'is-opened' )
									.find('svg').css( 'transform', 'rotate(0deg)' );;

							}
						});

						// wp.codeEditor.initialize( $('#code') , cm_settings );
					} );
				</script>
				<?php
			}
		);
	}
);

/**
 * Add Diagnostic link to Admin Bar.
 */
add_action(
	'admin_bar_menu',
	function( $wp_admin_bar ) {
		if ( ! is_object( $wp_admin_bar->get_nodes()['amp'] ) ) {
			return;
		}

		// Get the AMP Validated URL post ID
		$current_url    = remove_query_arg(
			array_merge( wp_removable_query_args(), [ QueryVar::NOAMP ] ),
			amp_get_current_url()
		);
		$post = AMP_Validated_URL_Post_Type::get_invalid_url_post( $current_url );

		$wp_admin_bar->add_node(
			array(
				'parent' => 'amp',
				'title'  => __( 'Support', 'amp' ),
				'id'     => 'amp-diagnostic',
				'href'   => esc_url(
					add_query_arg(
						array(
							'page'    => 'amp-support',
							'post_id' => $post->ID,
						),
						admin_url( 'admin.php' )
					)
				),
			)
		);
	},
	102
);

/**
 * Add diagnostic link to meta box.
 */
add_filter(
	'amp_validated_url_status_actions',
	function( $actions, $post ) {
		$actions['send-diagnostic'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				add_query_arg(
					array(
						'page'    => 'amp-support',
						'post_id' => $post->ID,
					),
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'Get Support', 'amp' )
		);

		return $actions;
	},
	10,
	2
);

/**
 * Add diagnostic link to Post row actions.
 */
add_filter(
	'post_row_actions',
	function( $actions, $post ) {
		if ( ! is_object( $post ) || AMP_Validated_URL_Post_Type::POST_TYPE_SLUG !== $post->post_type ) {
			return $actions;
		}

		$actions['send-diagnostic'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				add_query_arg(
					array(
						'page'    => 'amp-support',
						'post_id' => $post->ID,
					),
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'Get Support', 'amp' )
		);

		return $actions;
	},
	PHP_INT_MAX - 1,
	2
);

add_filter(
	'plugin_row_meta',
	function( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( 'amp/amp.php' === $plugin_file ) {
			$plugin_meta[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'page'    => 'amp-support',
							'post_id' => $post->ID,
						),
						admin_url( 'admin.php' )
					)
				),
				esc_html__( 'Support', 'amp' )
			);
		}
		return $plugin_meta;
	},
	10,
	4
);

/**
 * AJAX responder.
 */
add_action(
	'wp_ajax_amp-diagnostic',
	function() {

		if (
			! current_user_can( 'manage_options' )
			|| ! check_ajax_referer( 'amp-diagnostic' )
		) {
			exit;
		}

		$args       = array();
		$assoc_args = array();

		// $is_print     = filter_var( get_flag_value( $assoc_args, 'print', false ), FILTER_SANITIZE_STRING );
		// $is_synthetic = filter_var( get_flag_value( $assoc_args, 'is-synthetic', false ), FILTER_SANITIZE_STRING );
		// $endpoint     = filter_var( get_flag_value( $assoc_args, 'endpoint', AMP_SEND_DATA_SERVER_ENDPOINT ), FILTER_SANITIZE_STRING );
		$endpoint = untrailingslashit( AMP_SEND_DATA_SERVER_ENDPOINT );
		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		$data = AMP_Prepare_Data::get_data( $post_id );
		$data = wp_parse_args(
			$data,
			array(
				'site_url'                   => array(),
				'site_info'                  => array(),
				'plugins'                    => array(),
				'themes'                     => array(),
				'errors'                     => array(),
				'error_sources'              => array(),
				'amp_validated_environments' => array(),
				'urls'                       => array(),
			)
		);

		/**
		 * Modify data for synthetic sites.
		 */
		if ( $is_synthetic ) {
			$data['site_info']['is_synthetic_data'] = true;
		}

		/**
		 * Print or send AMP data.
		 */
		if ( $is_print ) {

			// Print the data.
			$print = strtolower( trim( $is_print ) );
			if ( 'json' === $print ) {
				print_r( wp_json_encode( $data ) . PHP_EOL );
			} elseif ( 'json-pretty' === $print ) {
				print_r( wp_json_encode( $data, JSON_PRETTY_PRINT ) . PHP_EOL );
			} else {
				print_r( $data );
			}
		} else {

			// Send data to server.
			$response = wp_remote_post(
				sprintf( '%s/api/v1/amp-wp/', $endpoint ),
				[
					'method'   => 'POST',
					'timeout'  => 1000,
					'body'     => $data,
					'compress' => true,
				]
			);

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				// WP_CLI::warning( "Something went wrong: $error_message" );
				echo wp_json_encode(
					array(
						'status' => esc_html( "Something went wrong: $error_message" ),
					)
				);
				exit;
			} else {

				$body = json_decode( wp_remote_retrieve_body( $response ) );
			}

		}

		/**
		 * Prepare summary of data.
		 */
		$url_error_relationship = [];

		foreach ( $data['urls'] as $url ) {
			foreach ( $url['errors'] as $error ) {
				foreach ( $error['sources'] as $source ) {
					$url_error_relationship[] = $url['url'] . '-' . $error['error_slug'] . '-' . $source;
				}
			}
		}

		$plugin_count = count( $data['plugins'] );

		if ( $is_synthetic ) {
			$plugin_count_text = ( $plugin_count - 5 ) . " - Excluding common plugins of synthetic sites. ( $plugin_count - 5 )";
		} else {
			$plugin_count_text = $plugin_count;
		}

		$summary = [
			'Site URL'               => AMP_Prepare_Data::get_home_url(),
			'Plugin count'           => $plugin_count_text,
			'Themes'                 => count( $data['themes'] ),
			'Errors'                 => count( array_values( $data['errors'] ) ),
			'Error Sources'          => count( array_values( $data['error_sources'] ) ),
			'Validated URL'          => count( array_values( $data['urls'] ) ),
			'URL Error Relationship' => count( array_values( $url_error_relationship ) ),
		];

		if ( $is_synthetic ) {
			$summary['Synthetic Data'] = 'Yes';
		}

		$body->summary = $summary;

		echo wp_json_encode( $body );

		exit;
	}
);

/**
 * Class AMP_Prepare_Data
 */
class AMP_Prepare_Data {

	/**
	 * To get amp data to send it to compatibility server.
	 *
	 * @return array
	 */
	public static function get_data( $post_id = 0 ) {

		// amp_send_data_check_amp_activate();

		$amp_urls = static::get_amp_urls( $post_id );

		$request_data = [
			'site_url'                   => static::get_home_url(),
			'site_info'                  => static::get_site_info(),
			'plugins'                    => static::get_plugin_info(),
			'themes'                     => static::get_theme_info(),
			'errors'                     => array_values( static::get_errors() ),
			'error_sources'              => array_values( $amp_urls['error_sources'] ),
			'amp_validated_environments' => array_values( $amp_urls['amp_validated_environments'] ),
			'urls'                       => array_values( $amp_urls['urls'] ),
		];

		return $request_data;
	}

	/**
	 * To get site info.
	 *
	 * @return array Site information.
	 */
	protected static function get_site_info() {

		$wp_type = 'single';

		if ( is_multisite() ) {
			$wp_type = ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) ? 'subdomain' : 'subdir';
		}

		global $_wp_using_ext_object_cache;

		$active_theme = wp_get_theme();
		$active_theme = static::normalize_theme_info( $active_theme );

		$amp_settings = AMP_Options_Manager::get_options();
		$amp_settings = ( ! empty( $amp_settings ) && is_array( $amp_settings ) ) ? $amp_settings : [];

		$loopback_status = '';

		if ( class_exists( 'Health_Check_Loopback' ) ) {
			$loopback_status = Health_Check_Loopback::can_perform_loopback();
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
			'object_cache_status'          => ( ! empty( $_wp_using_ext_object_cache ) ) ? true : false,
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
	 * @return array List of plugin detail.
	 */
	protected static function get_plugin_info() {

		$active_plugins = get_option( 'active_plugins' );

		if ( is_multisite() ) {
			$network_wide_activate_plugins = get_site_option( 'active_sitewide_plugins' );
			$active_plugins                = array_merge( $active_plugins, $network_wide_activate_plugins );
		}

		$active_plugins = array_values( array_unique( $active_plugins ) );
		$plugin_info    = array_map( 'AMP_Prepare_Data::normalize_plugin_info', $active_plugins );

		return $plugin_info;
	}

	/**
	 * To get plugin information by plugin file.
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
	 * To get active theme info.
	 *
	 * @return array List of theme information.
	 */
	protected static function get_theme_info() {

		$themes   = [ wp_get_theme() ];
		$response = [];

		foreach ( $themes as $theme ) {
			$response[] = static::normalize_theme_info( $theme );
		}

		return $response;
	}

	/**
	 * To normalize theme information.
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

		/**
		 * Remove duplicate data.
		 */
		$error_data = array_map( 'unserialize', array_unique( array_map( 'serialize', $error_data ) ) );

		return $error_data;
	}

	/**
	 * To get amp validated URLs.
	 *
	 * @return array List amp validated URLs.
	 */
	protected static function get_amp_urls( $post_id = 0 ) {

		global $wpdb;

		$scannable_url_provider = new \AmpProject\AmpWP\Validation\ScannableURLProvider(
			new \AmpProject\AmpWP\Validation\URLScanningContext(
				100,
				[],
				false
			)
		);

		$urls = wp_list_pluck( $scannable_url_provider->get_urls(), 'url' );

		$query = "SELECT ID, post_title, post_content FROM $wpdb->posts WHERE post_type='amp_validated_url'";

		if ( ! empty( $post_id ) ) {
			$query .= ' AND ID = "' . $post_id . '"';
		}else {
			$query .= " AND post_title IN( '" . implode( '\',\'', $urls ) . "' )";
		}

		$amp_error_posts = $wpdb->get_results( $query );

		// To Store all error_sources data.
		$all_sources = [];

		// To store all environment data.
		$all_amp_validated_environments = [];

		// To store all AMP validated URls
		$amp_invalid_urls = [];

		$error_data      = static::get_errors();
		$plugin_info     = static::get_plugin_info();
		$theme_info      = static::get_theme_info();
		$plugin_versions = [];
		$theme_versions  = [];

		foreach ( $plugin_info as $item ) {
			$plugin_versions[ $item['slug'] ] = $item['version'];
		}

		foreach ( $theme_info as $item ) {
			$theme_versions[ $item['slug'] ] = $item['version'];
		}

		/**
		 * Process each post.
		 *
		 * Post ==> Errors => sources
		 */
		foreach ( $amp_error_posts as $amp_error_post ) {

			if ( empty( $amp_error_post ) ) {
				continue;
			}

			$staleness = AMP_Validated_URL_Post_Type::get_post_staleness( $amp_error_post->ID );

			// Empty array for post staleness means post is NOT stale.
			if ( ! empty( $staleness ) ) {
				continue;
			}

			$post_errors_raw = json_decode( $amp_error_post->post_content, true );
			$post_errors     = [];

			if ( empty( $post_errors_raw ) ) {
				continue;
			}

			/**
			 * Process individual error in each post
			 */
			foreach ( $post_errors_raw as $error ) { // Errors of each posts.

				$error_slug = $error_data[ $error['term_slug'] ]['error_slug'];

				$sources            = ( ! empty( $error['data']['sources'] ) ) ? $error['data']['sources'] : [];
				$post_error_sources = [];

				/**
				 * Process each error_source of errors
				 */
				foreach ( $sources as $index => $source ) { // Source of each errors of the post

					$allowed_types  = [ 'plugin', 'theme' ];
					$source['type'] = ( ! empty( $source['type'] ) ) ? strtolower( trim( $source['type'] ) ) : '';

					/**
					 * Do not include wp-core sources.
					 */
					if ( empty( $source['type'] ) || ! in_array( $source['type'], $allowed_types, true ) ) {
						continue;
					}

					if ( 'plugin' === $source['type'] ) {
						$sources[ $index ]['version'] = $plugin_versions[ $source['name'] ];
					} elseif ( 'theme' === $source['type'] ) {
						$sources[ $index ]['version'] = $theme_versions[ $source['name'] ];
					}

					if ( ! empty( $sources[ $index ]['text'] ) ) {
						$sources[ $index ]['text'] = trim( $sources[ $index ]['text'] );
						$sources[ $index ]['text'] = static::remove_domain( $sources[ $index ]['text'] );
					}

					// Generate error source slug.
					$error_source_slug = self::generate_hash( $sources[ $index ] );

					// Update source information. Add error_slug and source_slug.
					$sources[ $index ]['error_source_slug'] = $error_source_slug;
					$sources[ $index ]['error_slug']        = $error_slug;

					ksort( $sources[ $index ] );

					// Store error source slug in current post list.
					$post_error_sources[] = $error_source_slug;

					// Store error source detail in all source list.
					$all_sources[ $error_source_slug ] = $sources[ $index ];

				} // Process on individual source complete.

				$post_errors[] = [
					'error_slug' => $error_slug,
					'sources'    => array_values( $post_error_sources ),
				];
			} // Process on each post is completed.

			// AMP Validated environment.
			$amp_validated_environment          = get_post_meta( $amp_error_post->ID, '_amp_validated_environment', true );
			$amp_validated_environment_slug     = static::generate_hash( $amp_validated_environment );
			$amp_validated_environment['_slug'] = $amp_validated_environment_slug;

			// Store in all amp validation environments.
			$all_amp_validated_environments[ $amp_validated_environment_slug ] = $amp_validated_environment;

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
			'error_sources'              => $all_sources,
			'amp_validated_environments' => $all_amp_validated_environments,
			'urls'                       => $amp_invalid_urls,
		];
	}

	/**
	 * Get style sheet info of the post.
	 *
	 * Reference: AMP_Validated_URL_Post_Type::print_stylesheets_meta_box()
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array AMP stylesheet used info.
	 */
	protected static function get_stylesheet_info( $post_id ) {

		$stylesheets = get_post_meta( $post_id, AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY, true );

		if ( empty( $stylesheets ) ) {
			return [];
		}

		$stylesheets             = json_decode( $stylesheets, true );
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
			// @todo Add information about amp-key frames as well.
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

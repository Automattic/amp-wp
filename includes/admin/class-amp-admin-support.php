<?php
/**
 * Class AMP_Admin_Support
 *
 * @package AMP
 * @since 2.1
 */

use AmpProject\AmpWP\Admin\GoogleFonts;
use AmpProject\AmpWP\QueryVar;

/**
 * Class managing support pages in wp-admin dashboard.
 *
 * @since 2.1
 * @internal
 */
class AMP_Admin_Support {
	/**
	 * Handle for CSS file.
	 *
	 * @since 2.1
	 *
	 * @var string
	 */
	const ASSET_HANDLE = 'amp-settings';

	/**
	 * Endpoint to send diagnostic data.
	 *
	 * @since 2.1
	 *
	 * @var string
	 */
	const AMP_SEND_DATA_SERVER_ENDPOINT = 'https://insights.amp-wp.org';

	/**
	 * Registers functionality through WordPress hooks.
	 *
	 * @since 2.1
	 */
	public function init() {
		if (
			! current_user_can( 'manage_options' )
			&& ! defined( 'TESTS_PLUGIN_DIR' ) // @see tests/php/bootstrap.php
		) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 20 );
		/**
		 * AJAX responder.
		 */
		add_action( 'wp_ajax_amp_diagnostic', [ $this, 'wp_ajax_amp_diagnostic' ] );

		/**
		 * Add Diagnostic link to Admin Bar.
		 */
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 102 );

		/**
		 * Add diagnostic link to meta box.
		 */
		add_filter( 'amp_validated_url_status_actions', [ $this, 'amp_validated_url_status_actions' ], 10, 2 );

		/**
		 * Add diagnostic link to Post row actions.
		 */
		add_filter( 'post_row_actions', [ $this, 'post_row_actions' ], PHP_INT_MAX - 1, 2 );

		/**
		 * Plugin row Support link.
		 */
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
	}

	/**
	 * Sends diagnostic data to AMP support server.
	 *
	 * Prints the UUID returned from the server, if data is sent successfully, else, prints the error message.
	 *
	 * @since 2.1
	 *
	 * @return void|array
	 */
	public function wp_ajax_amp_diagnostic() {

		if (
			(
				! current_user_can( 'manage_options' )
				|| ! check_ajax_referer( 'amp-diagnostic' )
			)
			&& ! defined( 'TESTS_PLUGIN_DIR' ) // @see tests/php/bootstrap.php
		) {
			exit;
		}

		$endpoint     = untrailingslashit( self::AMP_SEND_DATA_SERVER_ENDPOINT );
		$is_synthetic = false;
		// Post ID: amp_validated_url ID or 0 for all.
		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		$args = [
			'urls'     => [],
			'post_ids' => ( ! empty( $post_id ) ) ? [ $post_id ] : [],
			'term_ids' => [],
		];

		if ( empty( $post_id ) ) {
			$scannable_url_provider = new \AmpProject\AmpWP\Validation\ScannableURLProvider(
				new \AmpProject\AmpWP\Validation\URLScanningContext(
					100,  // limit per type.
					[],   // include conditionals.
					false // include_unsupported.
				)
			);

			$urls = wp_list_pluck( $scannable_url_provider->get_urls(), 'url' );
			$args['urls'] = $urls;
		}

		$amp_data_object = new AMP_Prepare_Data( $args );
		$data            = $amp_data_object->get_data();

		$data = wp_parse_args(
			$data,
			[
				'site_url'      => [],
				'site_info'     => [],
				'plugins'       => [],
				'themes'        => [],
				'errors'        => [],
				'error_sources' => [],
				'urls'          => [],
			]
		);

		/**
		 * Modify data for synthetic sites.
		 */
		if ( $is_synthetic ) {
			$data['site_info']['is_synthetic_data'] = true;
		}

		/**
		 * @see tests/php/bootstrap.php
		 */
		if ( defined( 'TESTS_PLUGIN_DIR' ) ) {
			return [
				'endpoint' => sprintf( '%s/api/v1/amp-wp/', $endpoint ),
				'data'     => $data,
			];
		}

		// Send data to server.
		$response = wp_remote_post(
			sprintf( '%s/api/v1/amp-wp/', $endpoint ),
			[
				// We need long timeout here, in case the data being sent is large or the network connection is slow.
				'timeout'  => 3000, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
				'body'     => $data,
				'compress' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			echo wp_json_encode(
				[
					'status' => esc_html( "Something went wrong: $error_message" ),
				]
			);
			exit;
		} else {

			$body = json_decode( wp_remote_retrieve_body( $response ) );
		}

		if ( null === $body ) {
			echo wp_json_encode(
				[
					'status' => esc_html( 'Something went wrong: ' . wp_remote_retrieve_body( $response ) ),
				]
			);
			exit;
		}

		echo wp_json_encode( $body );

		exit;
	}

	/**
	 * Enqueue assets for admin page.
	 *
	 * @since 2.1
	 *
	 * @param string $hook The current admin page.
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'amp_page_amp-support' !== $hook ) {
			return;
		}
		// Google Fonts & AMP Settings.
		$f = new GoogleFonts();
		wp_enqueue_style(
			self::ASSET_HANDLE,
			amp_get_asset_url( 'css/amp-settings.css' ),
			[
				$f->get_handle(),
				'wp-components',
			],
			AMP__VERSION
		);
	}

	/**
	 * Adds support page to AMP's submenu.
	 *
	 * @since 2.1
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_submenu_page(
			AMP_Options_Manager::OPTION_NAME,
			esc_html__( 'Support', 'amp' ),
			esc_html__( 'Support', 'amp' ),
			'manage_options',
			'amp-support',
			[ $this, 'support_page' ]
		);
	}

	/**
	 * Renders support page.
	 *
	 * @since 2.1
	 *
	 * @return void
	 */
	public function support_page() {
		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		if ( empty( $post_id ) ) {
			$scannable_url_provider = new \AmpProject\AmpWP\Validation\ScannableURLProvider(
				new \AmpProject\AmpWP\Validation\URLScanningContext(
					100,  // limit per type.
					[],   // include conditionals.
					false // include_unsupported.
				)
			);

			$urls = wp_list_pluck( $scannable_url_provider->get_urls(), 'url' );
			$args = [ 'urls' => $urls ];
		} else {
			$args = [ 'post_ids' => $post_id ];
		}

		$amp_data_object = new AMP_Prepare_Data( $args );
		$data            = $amp_data_object->get_data();

		?>
		<style>
			.amp li {
				list-style-type: disc;
				margin-left: 20px;
			}
			.amp h2 a {
				display:inline-block;
				width: auto;
				white-space: nowrap;
			}
			.amp a.is-primary {
				margin-top:0 !important;
			}
			.amp .settings-welcome h3 {
				margin-top: 2rem !important;
			}
			.amp .settings-welcome .selectable {
				align-items: start;
			}
			.amp .settings-welcome .detail p {
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

			/* copy link */
			#status a {
				margin-left: 1rem;
			}
			.disabled {
				background-color: #ccc !important;
			}
		</style>
		<div class="amp">

		<h2><?php esc_html_e( 'AMP Support', 'amp' ); ?></h2>

		<div class="settings-welcome">
			<div class="selectable selectable--left">
				<div class="settings-welcome__illustration">
					<svg width="62" height="51" viewBox="0 0 62 51" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#welcome-svg-clip)"><path d="M19.0226 3.89844H39.5226C45.0226 3.89844 49.4226 8.29844 49.4226 13.7984V34.2984C49.4226 39.7984 45.0226 44.1984 39.5226 44.1984H19.0226C13.5226 44.1984 9.12256 39.7984 9.12256 34.2984V13.7984C9.12256 8.29844 13.5226 3.89844 19.0226 3.89844Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M17.8227 11.1992C18.7227 11.1992 19.4227 11.8992 19.4227 12.7992V35.6992C19.4227 36.5992 18.7227 37.2992 17.8227 37.2992C16.9227 37.2992 16.2227 36.5992 16.2227 35.6992V12.6992C16.2227 11.7992 16.9227 11.1992 17.8227 11.1992Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M17.8228 21.9C19.5901 21.9 21.0228 20.4673 21.0228 18.7C21.0228 16.9327 19.5901 15.5 17.8228 15.5C16.0555 15.5 14.6228 16.9327 14.6228 18.7C14.6228 20.4673 16.0555 21.9 17.8228 21.9Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M29.3227 37.0977C28.4227 37.0977 27.7227 36.3977 27.7227 35.4977V12.6977C27.7227 11.7977 28.4227 11.0977 29.3227 11.0977C30.2227 11.0977 30.9227 11.7977 30.9227 12.6977V35.5977C30.9227 36.3977 30.2227 37.0977 29.3227 37.0977Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M40.9225 37.0977C40.0225 37.0977 39.3225 36.3977 39.3225 35.4977V12.6977C39.3225 11.7977 40.0225 11.0977 40.9225 11.0977C41.8225 11.0977 42.5225 11.7977 42.5225 12.6977V35.5977C42.5225 36.3977 41.8225 37.0977 40.9225 37.0977Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M40.9227 24.0992C42.69 24.0992 44.1227 22.6665 44.1227 20.8992C44.1227 19.1319 42.69 17.6992 40.9227 17.6992C39.1553 17.6992 37.7227 19.1319 37.7227 20.8992C37.7227 22.6665 39.1553 24.0992 40.9227 24.0992Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M29.2227 30.9977C30.99 30.9977 32.4227 29.565 32.4227 27.7977C32.4227 26.0303 30.99 24.5977 29.2227 24.5977C27.4554 24.5977 26.0227 26.0303 26.0227 27.7977C26.0227 29.565 27.4554 30.9977 29.2227 30.9977Z" fill="white" stroke="#2459E7" stroke-width="2"></path><path d="M47.3225 5.19784C47.9225 3.69784 49.9225 0.797843 53.4225 1.49784" stroke="#2459E7" stroke-width="2" stroke-linecap="round"></path><path d="M50.5227 7.19675C51.7227 6.69675 54.5227 6.29675 56.2227 9.09675" stroke="#2459E7" stroke-width="2" stroke-linecap="round"></path><path d="M12.4225 44.7969C11.9225 45.7969 10.9225 48.1969 11.1225 49.3969" stroke="#2459E7" stroke-width="2" stroke-linecap="round"></path><path d="M8.92266 43.6992C8.42266 44.0992 7.52266 44.6992 6.72266 45.1992" stroke="#2459E7" stroke-width="2" stroke-linecap="round"></path><path d="M7.42261 39.8984C5.92261 40.4984 2.82261 41.5984 1.92261 41.7984" stroke="#2459E7" stroke-width="2" stroke-linecap="round"></path><path d="M3.92251 48.8992C4.80617 48.8992 5.52251 48.1829 5.52251 47.2992C5.52251 46.4156 4.80617 45.6992 3.92251 45.6992C3.03885 45.6992 2.32251 46.4156 2.32251 47.2992C2.32251 48.1829 3.03885 48.8992 3.92251 48.8992Z" fill="#2459E7"></path><path d="M60.1227 12.7C61.0064 12.7 61.7227 11.9837 61.7227 11.1C61.7227 10.2163 61.0064 9.5 60.1227 9.5C59.2391 9.5 58.5227 10.2163 58.5227 11.1C58.5227 11.9837 59.2391 12.7 60.1227 12.7Z" fill="#2459E7"></path></g><defs><clipPath id="welcome-svg-clip"><rect width="60.8" height="50" fill="white" transform="translate(0.922607 0.398438)"></rect></clipPath></defs></svg>
				</div>
				<div>
					<h2>
						<?php if ( ! empty( $post_id ) ) : ?>
							<?php
							esc_html_e( 'Send diagnostic data for ', 'amp' );
							echo esc_url( get_the_title( $post_id ) );
							?>
						<?php else : ?>
							<?php
							echo sprintf(
							// translators: %s contains singular or plural "Validated URL(s)".
								esc_html__( 'Send diagnostic data for %s', 'amp' ),
								' &nbsp;<a href="edit.php?post_type=amp_validated_url">' . count( $data['urls'] ) . ' ' . esc_html( _n( 'validated URL:', 'validated URLs:', count( $data['urls'] ), 'amp' ) ) . '</a>'
							);
							?>
						<?php endif; ?>
						<svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg"><mask id="check-circle-mask" mask-type="alpha" maskUnits="userSpaceOnUse" x="2" y="2" width="21" height="21"><path fill-rule="evenodd" clip-rule="evenodd" d="M12.7537 2.60938C7.23366 2.60938 2.75366 7.08938 2.75366 12.6094C2.75366 18.1294 7.23366 22.6094 12.7537 22.6094C18.2737 22.6094 22.7537 18.1294 22.7537 12.6094C22.7537 7.08938 18.2737 2.60938 12.7537 2.60938ZM12.7537 20.6094C8.34366 20.6094 4.75366 17.0194 4.75366 12.6094C4.75366 8.19938 8.34366 4.60938 12.7537 4.60938C17.1637 4.60938 20.7537 8.19938 20.7537 12.6094C20.7537 17.0194 17.1637 20.6094 12.7537 20.6094ZM10.7537 14.7794L17.3437 8.18937L18.7537 9.60938L10.7537 17.6094L6.75366 13.6094L8.16366 12.1994L10.7537 14.7794Z" fill="white"></path></mask><g mask="url(#check-circle-mask)"><rect x="0.753662" y="0.609375" width="24" height="24" fill="#2459E7"></rect></g></svg>
					</h2>

					<?php if ( ! empty( $data['urls'] ) && empty( $post_id ) ) : ?>
						<ul>
							<?php foreach ( $data['urls'] as $url ) : ?>
								<li><a href="<?php echo esc_url( $url['url'] ); ?>"><?php echo esc_html( $url['url'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php
					if ( 0 !== count( $data['urls'] ) && ! empty( $post_id ) ) :
						?>
						<ul>
							<li><a href="<?php echo esc_url( remove_query_arg( 'post_id' ) ); ?>">
									<?php esc_html_e( 'Switch to all validated URLs.', 'amp' ); ?>
								</a></li>
						</ul>
					<?php else : ?>
						<ul>
							<li>
								<?php esc_html_e( 'Site Health and error info will be sent.', 'amp' ); ?>
							</li>
						</ul>
					<?php endif; ?>

					<p>
						<a href="#" class="components-button is-primary"><?php esc_html_e( 'Send Diagnostics', 'amp' ); ?></a>
					</p>
					<p id="status"></p>

					<detail>
						<p>
							<?php
							esc_html_e( 'Clicking this button will return a unique ID suitable for sharing in a support forum for further guidance and information. Once the UUID appears, copy it and share in a new support forum post:', 'amp' );
							?>
						</p>
						<ul>
							<li><a href="https://wordpress.org/support/plugin/amp/" target="_blank"><?php esc_html_e( 'WordPress.org support forum', 'amp' ); ?></a></li>
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

							<?php if ( ! empty( $data['error_log']['contents'] ) ) : ?>
								<li>
									<?php esc_html_e( 'Last 200 lines of PHP error log', 'amp' ); ?>
								</li>
							<?php endif; ?>
						</ul>
					</summary>

					<textarea id="code">
						<?php
						echo esc_textarea( wp_json_encode( $data, JSON_PRETTY_PRINT ) );
						?>
						</textarea>

				</div></div>
		</div>

		<script>
			jQuery( document ).ready( function( $ ){

				$( 'a.is-primary' ).click(function(){
					$.ajax({
						url: 'admin-ajax.php',
						data: {
							'action': 'amp_diagnostic',
							'post_id': '<?php echo (int) $post_id; ?>',
							'_ajax_nonce': '<?php echo esc_js( wp_create_nonce( 'amp-diagnostic' ) ); ?>',
						},
						dataType: 'json',
						type: 'GET',
						beforeSend: function(){
							if ( ! $('a.is-primary').hasClass( 'disabled' ) ) {
								$('#status').html(
									'<?php echo esc_html__( 'Sending...', 'amp' ); ?>'
								);
								$('a.is-primary').addClass( 'disabled' );
							}
						},
						success: function( d ) {
							if ( typeof d.data === 'object' ) {
								$('#status').html(
									'<?php echo esc_html__( 'Diagnostics sent. ', 'amp' ); ?><br/>' + '<?php echo esc_html__( 'Unique ID: ', 'amp' ); ?>' + '<strong>' + d.data.uuid + '</strong>'
								);
								$('a.is-primary').removeClass('disabled');

								// Copy link
								var $a = $('<a href="#"><?php esc_html_e( 'Copy', 'amp' ); ?></a>')
									.data( 'uuid', d.data.uuid )
									.click(function(){
										navigator.clipboard.writeText( $(this).data('uuid') )
										return false;
									});
								$('#status').append( $a );

							} else {
								$('#status').text(
									'<?php echo esc_html__( 'Sending failed. Please try again.', 'amp' ); ?>'
								);
								$('a.is-primary').removeClass('disabled');
							}
						},
						error: function( d ) {
							$('#status').text(
								'<?php echo esc_html__( 'Sending failed. Please try again.', 'amp' ); ?>'
							);
							$('a.is-primary').removeClass('disabled');
						}
					} );
					return false;
				} );

				$('.amp-drawer__panel-body-inner').hide();
				$('.amp-drawer__panel-body').find('svg').css( 'transform', 'rotate(180deg)' );
				$('.components-panel__body-toggle').click( function(){
					$('.amp-drawer__panel-body-inner').slideToggle();
					if ( $('.amp-drawer__panel-body').hasClass( 'is-opened' ) ) {
						$('.amp-drawer__panel-body')
							.toggleClass( 'is-opened' )
							.find('svg').css( 'transform', 'rotate(0deg)' );
					}else {
						$('.amp-drawer__panel-body')
							.toggleClass( 'is-opened' )
							.find('svg').css( 'transform', 'rotate(180deg)' );

					}
				});

			} );
		</script>
		<?php
	}

	/**
	 * Add Diagnostic link to Admin Bar.
	 *
	 * @since 2.1
	 *
	 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar object.
	 *
	 * @return void
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		if ( ! array_key_exists( 'amp', $wp_admin_bar->get_nodes() ) ) {
			return;
		}

		// Get the AMP Validated URL post ID.
		$current_url = remove_query_arg(
			array_merge( wp_removable_query_args(), [ QueryVar::NOAMP ] ),
			amp_get_current_url()
		);

		$post = AMP_Validated_URL_Post_Type::get_invalid_url_post( $current_url );

		$wp_admin_bar->add_node(
			[
				'parent' => 'amp',
				'title'  => __( 'Support', 'amp' ),
				'id'     => 'amp-diagnostic',
				'href'   => esc_url(
					add_query_arg(
						[
							'page'    => 'amp-support',
							'post_id' => ! empty( $post ) ? $post->ID : 0,
						],
						admin_url( 'admin.php' )
					)
				),
			]
		);
	}

	/**
	 * Add diagnostic link to meta box.
	 *
	 * @since 2.1
	 *
	 * @param string[] $actions Array of actions.
	 * @param WP_Post  $post Referenced WP_Post object.
	 */
	public function amp_validated_url_status_actions( $actions, $post ) {
		$actions['send-diagnostic'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				add_query_arg(
					[
						'page'    => 'amp-support',
						'post_id' => $post->ID,
					],
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'Get Support', 'amp' )
		);

		return $actions;
	}

	/**
	 * Add diagnostic link to Post row actions.
	 *
	 * @since 2.1
	 *
	 * @param string[] $actions Array of actions.
	 * @param WP_Post  $post Referenced WP_Post object.
	 */
	public function post_row_actions( $actions, $post ) {
		if ( ! is_object( $post ) || AMP_Validated_URL_Post_Type::POST_TYPE_SLUG !== $post->post_type ) {
			return $actions;
		}

		$actions['send-diagnostic'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				add_query_arg(
					[
						'page'    => 'amp-support',
						'post_id' => $post->ID,
					],
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'Get Support', 'amp' )
		);

		return $actions;
	}

	/**
	 * Plugin row Support link.
	 *
	 * @since 2.1
	 *
	 * @param string[] $plugin_meta An array of the plugin's metadata, including the version, author, author URI, and plugin URI.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 *
	 * @return string[] Filtered array of plugin's metadata.
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		global $post;
		if ( 'amp/amp.php' === $plugin_file || 'amp-wp/amp.php' === $plugin_file ) {
			$plugin_meta[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						[
							'page'    => 'amp-support',
							'post_id' => 0,
						],
						admin_url( 'admin.php' )
					)
				),
				esc_html__( 'Contact support', 'amp' )
			);
		}
		return $plugin_meta;
	}

	/**
	 * Sends data to our endpoint where we queue it for further analysis.
	 *
	 * @since 2.1
	 *
	 * @param string[] $args       Not Used.
	 * @param string[] $assoc_args Associative array of arguments passed to the CLI command.
	 *
	 * @throws \Exception When the AMP plugin is not active.
	 *
	 * @return void
	 */
	public function amp_send_diagnostic( $args = [], $assoc_args = [] ) {

		$is_print     = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'print', false ), FILTER_SANITIZE_STRING );
		$is_synthetic = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'is-synthetic', false ), FILTER_SANITIZE_STRING );
		$endpoint     = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'endpoint', self::AMP_SEND_DATA_SERVER_ENDPOINT ), FILTER_SANITIZE_STRING );
		$endpoint     = untrailingslashit( $endpoint );

		$urls     = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'urls', false ), FILTER_SANITIZE_STRING );
		$post_ids = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'post_ids', false ), FILTER_SANITIZE_STRING );
		$term_ids = filter_var( \WP_CLI\Utils\get_flag_value( $assoc_args, 'term_ids', false ), FILTER_SANITIZE_STRING );

		$args = [
			'urls'     => ( ! empty( $urls ) ) ? explode( ',', $urls ) : [],
			'post_ids' => ( ! empty( $post_ids ) ) ? explode( ',', $post_ids ) : [],
			'term_ids' => ( ! empty( $term_ids ) ) ? explode( ',', $term_ids ) : [],
		];

		$amp_data_object = new AMP_Prepare_Data( $args );
		$data            = $amp_data_object->get_data();

		$data = wp_parse_args(
			$data,
			[
				'site_url'      => [],
				'site_info'     => [],
				'plugins'       => [],
				'themes'        => [],
				'errors'        => [],
				'error_sources' => [],
				'urls'          => [],
			]
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
				echo wp_json_encode( $data ) . PHP_EOL;
			} elseif ( 'json-pretty' === $print ) {
				echo wp_json_encode( $data, JSON_PRETTY_PRINT ) . PHP_EOL;
			}
		} else {

			// Send data to server.

			$response = wp_remote_post(
				sprintf( '%s/api/v1/amp-wp/', $endpoint ),
				[
					// We need long timeout here, in case the data being sent is large or the network connection is slow.
					'timeout'  => 3000, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'body'     => $data,
					'compress' => true,
				]
			);

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				\WP_CLI::warning( "Something went wrong: $error_message" );
			} else {
				$body = wp_remote_retrieve_body( $response );
				\WP_CLI::success( $body );
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

		\WP_CLI::log( sprintf( PHP_EOL . "%'=100s", '' ) );
		\WP_CLI::log( 'Summary of AMP data' );
		\WP_CLI::log( sprintf( "%'=100s", '' ) );
		foreach ( $summary as $key => $value ) {
			\WP_CLI::log( sprintf( '%-25s : %s', $key, $value ) );
		}
		\WP_CLI::log( sprintf( "%'=100s" . PHP_EOL, '' ) );
	}
}

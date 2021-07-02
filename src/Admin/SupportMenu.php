<?php
/**
 * Class SupportMenu
 *
 * @package Ampproject\Ampwp
 */

namespace AmpProject\AmpWP\Admin;

use AmpProject\AmpWP\Infrastructure\Conditional;
use AmpProject\AmpWP\Infrastructure\Registerable;
use AmpProject\AmpWP\Infrastructure\Service;
use AmpProject\AmpWP\Services;

/**
 * SupportMenu class.
 *
 * @internal
 */
class SupportMenu implements Conditional, Service, Registerable {

	/**
	 * Handle for JS file.
	 *
	 * @var string
	 */
	const ASSET_HANDLE = 'amp-support';

	/**
	 * AJAX action name to use.
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'amp_send_support_request';

	/**
	 * The parent menu slug.
	 *
	 * @var string
	 */
	private $parent_menu_slug;

	/**
	 * GoogleFonts instance.
	 *
	 * @var GoogleFonts
	 */
	private $google_fonts;

	/**
	 * Class constructor.
	 *
	 * @param OptionsMenu $options_menu An instance of the class handling the parent menu.
	 * @param GoogleFonts $google_fonts An instance of the GoogleFonts service.
	 */
	public function __construct( OptionsMenu $options_menu, GoogleFonts $google_fonts ) {

		$this->parent_menu_slug = $options_menu->get_menu_slug();

		$this->google_fonts = $google_fonts;
	}

	/**
	 * Check whether the conditional object is currently needed.
	 *
	 * @return bool Whether the conditional object is needed.
	 */
	public static function is_needed() {

		if ( ! is_admin() ) {
			return false;
		}

		/**
		 * Filter whether to enable the AMP settings.
		 *
		 * @param bool $enable Whether to enable the AMP settings. Default true.
		 *
		 * @since 0.5
		 */
		return (bool) apply_filters( 'amp_support_menu_is_enabled', true );
	}

	/**
	 * Adds hooks.
	 */
	public function register() {

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_menu', [ $this, 'add_menu_items' ], 9 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_callback' ] );

	}

	/**
	 * Returns the slug for the support page.
	 *
	 * @return string
	 */
	public function get_menu_slug() {

		return 'amp-support';
	}

	/**
	 * Provides the support screen handle.
	 *
	 * @return string
	 */
	public function screen_handle() {

		return sprintf( 'amp_page_%s', $this->get_menu_slug() );
	}

	/**
	 * Add menu.
	 */
	public function add_menu_items() {

		require_once ABSPATH . '/wp-admin/includes/plugin.php';

		add_submenu_page(
			$this->parent_menu_slug,
			esc_html__( 'Support', 'amp' ),
			esc_html__( 'Support', 'amp' ),
			'manage_options',
			$this->get_menu_slug(),
			[ $this, 'render_screen' ],
			10
		);

	}

	/**
	 * Enqueues settings page assets.
	 *
	 * @param string $hook_suffix The current admin page.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {

		if ( $this->screen_handle() !== $hook_suffix ) {
			return;
		}

		/** This action is documented in includes/class-amp-theme-support.php */
		do_action( 'amp_register_polyfills' );

		$asset_file   = AMP__DIR__ . '/assets/js/' . self::ASSET_HANDLE . '.asset.php';
		$asset        = require $asset_file;
		$dependencies = $asset['dependencies'];
		$version      = $asset['version'];

		wp_enqueue_script(
			self::ASSET_HANDLE,
			amp_get_asset_url( 'js/' . self::ASSET_HANDLE . '.js' ),
			$dependencies,
			$version,
			true
		);

		wp_enqueue_style(
			self::ASSET_HANDLE,
			amp_get_asset_url( 'css/amp-support.css' ),
			[
				$this->google_fonts->get_handle(),
				'wp-components',
			],
			AMP__VERSION
		);

		$args    = [];
		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		if ( ! empty( $post_id ) && 0 < intval( $post_id ) ) {
			$args = [
				'amp_validated_post_ids' => [
					$post_id,
				],
			];
		}

		$support_service = Services::get( 'support' );
		$data            = $support_service->get_data( $args );

		wp_localize_script(
			self::ASSET_HANDLE,
			'ampSupportData',
			[
				'action' => self::AJAX_ACTION,
				'nonce'  => wp_create_nonce( self::AJAX_ACTION ),
				'args'   => $args,
				'data'   => $data,
			]
		);
	}

	/**
	 * Ajax callback.
	 *
	 * @return void
	 */
	public function ajax_callback() {

		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.', 401 );

			return;
		}

		$request_args = filter_input( INPUT_POST, 'args', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
		$request_args = ( ! empty( $request_args ) ) ? $request_args : [];

		$support_response = Services::get( 'support' )->send_data( $request_args );

		if ( ! empty( $support_response ) && is_wp_error( $support_response ) ) {
			wp_send_json_error( $support_response->get_error_message(), 500 );

			return;
		}

		if ( 'ok' === $support_response['status'] && ! empty( $support_response['data']['uuid'] ) ) {
			wp_send_json_success( $support_response['data'] );

			return;
		}

		wp_send_json_error( 'Fail to send data.', 500 );

	}

	/**
	 * Display Settings.
	 *
	 * @return void
	 */
	public function render_screen() {

		?>
		<div id="amp-support" class="wrap">
			<div class="amp amp-support">
				<div id="amp-support-root"></div>
			</div>
		</div>
		<?php
	}
}

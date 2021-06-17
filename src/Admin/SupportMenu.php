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

/**
 * SupportMenu class.
 *
 * @internal
 */
class SupportMenu implements Conditional, Service, Registerable {

	/**
	 * Handle for JS file.
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	const ASSET_HANDLE = 'amp-settings';

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

		return sprintf( 'toplevel_page_%s', $this->get_menu_slug() );
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
			amp_get_asset_url( 'css/amp-settings.css' ),
			[
				$this->google_fonts->get_handle(),
				'wp-components',
			],
			AMP__VERSION
		);

	}

	/**
	 * Display Settings.
	 */
	public function render_screen() {

		?>
		<div class="wrap">
			<form id="amp-settings" action="options.php" method="post">
				<?php settings_fields( $this->get_menu_slug() ); ?>
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<?php settings_errors(); ?>

				<div class="amp amp-support">
					<div id="amp-support-root"></div>
				</div>
			</form>
		</div>
		<?php
	}
}

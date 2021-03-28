<?php
/**
 * Plugin Name: AMP
 * Description: An easier path to great Page Experience for everyone. Powered by AMP.
 * Plugin URI: https://amp-wp.org
 * Author: AMP Project Contributors
 * Author URI: https://github.com/ampproject/amp-wp/graphs/contributors
 * Version: 2.2.0-alpha
 * License: GPLv2 or later
 * Requires at least: 4.9
 * Requires PHP: 5.6
 *
 * @package AMP
 */

define( 'AMP__FILE__', __FILE__ );
define( 'AMP__DIR__', dirname( __FILE__ ) );
define( 'AMP__VERSION', '2.2.0-alpha' );

/**
 * Errors encountered while loading the plugin.
 *
 * This has to be a global for the sake of PHP 5.2.
 *
 * @var WP_Error $_amp_load_errors
 */
global $_amp_load_errors;

$_amp_load_errors = new WP_Error();

if ( version_compare( phpversion(), '5.6', '<' ) ) {
	$_amp_load_errors->add(
		'insufficient_php_version',
		sprintf(
			/* translators: %s: required PHP version */
			__( 'The AMP plugin requires PHP %s. Please contact your host to update your PHP version.', 'amp' ),
			'5.6+'
		)
	);
}

// See composer.json for this list.
$_amp_required_extensions = array(
	// Required by FasterImage.
	'curl'   => array(
		'functions' => array(
			'curl_close',
			'curl_errno',
			'curl_error',
			'curl_exec',
			'curl_getinfo',
			'curl_init',
			'curl_setopt',
		),
	),
	'dom'    => array(
		'classes' => array(
			'DOMAttr',
			'DOMComment',
			'DOMDocument',
			'DOMElement',
			'DOMNode',
			'DOMNodeList',
			'DOMXPath',
		),
	),
	'filter' => array(
		'functions' => array(
			'filter_var',
		),
	),
	// Required by PHP-CSS-Parser.
	'iconv'  => array(
		'functions' => array( 'iconv' ),
	),
	'libxml' => array(
		'functions' => array( 'libxml_use_internal_errors' ),
	),
	'spl'    => array(
		'functions' => array( 'spl_autoload_register' ),
	),
);
$_amp_missing_extensions = array();
$_amp_missing_classes    = array();
$_amp_missing_functions  = array();
foreach ( $_amp_required_extensions as $_amp_required_extension => $_amp_required_constructs ) {
	if ( ! extension_loaded( $_amp_required_extension ) ) {
		$_amp_missing_extensions[] = "<code>$_amp_required_extension</code>";
	} else {
		foreach ( $_amp_required_constructs as $_amp_construct_type => $_amp_constructs ) {
			switch ( $_amp_construct_type ) {
				case 'functions':
					foreach ( $_amp_constructs as $_amp_construct ) {
						if ( ! function_exists( $_amp_construct ) ) {
							$_amp_missing_functions[] = "<code>$_amp_construct</code>";
						}
					}
					break;
				case 'classes':
					foreach ( $_amp_constructs as $_amp_construct ) {
						if ( ! class_exists( $_amp_construct ) ) {
							$_amp_missing_classes[] = "<code>$_amp_construct</code>";
						}
					}
					break;
			}
		}
		unset( $_amp_construct_type, $_amp_constructs );
	}
}
if ( count( $_amp_missing_extensions ) > 0 ) {
	$_amp_load_errors->add(
		'missing_extension',
		sprintf(
			/* translators: %s is list of missing extensions */
			_n(
				'The following PHP extension is missing: %s. Please contact your host to finish installation.',
				'The following PHP extensions are missing: %s. Please contact your host to finish installation.',
				count( $_amp_missing_extensions ),
				'amp'
			),
			implode( ', ', $_amp_missing_extensions )
		)
	);
}
if ( count( $_amp_missing_classes ) > 0 ) {
	$_amp_load_errors->add(
		'missing_class',
		sprintf(
			/* translators: %s is list of missing extensions */
			_n(
				'The following PHP class is missing: %s. Please contact your host to finish installation.',
				'The following PHP classes are missing: %s. Please contact your host to finish installation.',
				count( $_amp_missing_classes ),
				'amp'
			),
			implode( ', ', $_amp_missing_classes )
		)
	);
}
if ( count( $_amp_missing_functions ) > 0 ) {
	$_amp_load_errors->add(
		'missing_class',
		sprintf(
			/* translators: %s is list of missing extensions */
			_n(
				'The following PHP function is missing: %s. Please contact your host to finish installation.',
				'The following PHP functions are missing: %s. Please contact your host to finish installation.',
				count( $_amp_missing_functions ),
				'amp'
			),
			implode( ', ', $_amp_missing_functions )
		)
	);
}

unset( $_amp_required_extensions, $_amp_missing_extensions, $_amp_required_constructs, $_amp_missing_classes, $_amp_missing_functions, $_amp_required_extension, $_amp_construct_type, $_amp_construct, $_amp_constructs );

// DEV_CODE. This block of code is removed during the build process.
if ( ! file_exists( AMP__DIR__ . '/vendor/autoload.php' ) || ! file_exists( AMP__DIR__ . '/vendor/sabberworm/php-css-parser' ) || ! file_exists( AMP__DIR__ . '/assets/js/amp-block-editor.js' ) ) {
	$_amp_load_errors->add(
		'build_required',
		sprintf(
			/* translators: %s: composer install && npm install && npm run build:prod */
			__( 'You appear to be running the AMP plugin from source. Please do %s to finish installation.', 'amp' ), // phpcs:ignore WordPress.Security.EscapeOutput
			'<code>composer install &amp;&amp; npm install &amp;&amp; npm run build:prod</code>'
		)
	);
}

/**
 * Displays an admin notice about why the plugin is unable to load.
 *
 * @since 1.1.2
 * @internal
 * @global WP_Error $_amp_load_errors
 */
function _amp_show_load_errors_admin_notice() {
	global $_amp_load_errors;
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'AMP plugin unable to initialize.', 'amp' ); ?></strong>
			<ul>
			<?php foreach ( array_keys( $_amp_load_errors->errors ) as $error_code ) : ?>
				<?php foreach ( $_amp_load_errors->get_error_messages( $error_code ) as $message ) : ?>
					<li>
						<?php echo wp_kses_post( $message ); ?>
					</li>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</ul>
		</p>
	</div>
	<?php
}

// Abort if dependencies are not satisfied.
if ( ! empty( $_amp_load_errors->errors ) ) {
	add_action( 'admin_notices', '_amp_show_load_errors_admin_notice' );

	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || 'true' === getenv( 'CI' ) || 'cli' === PHP_SAPI ) {
		$messages = array( __( 'AMP plugin unable to initialize.', 'amp' ) );
		foreach ( array_keys( $_amp_load_errors->errors ) as $error_code ) {
			$messages = array_merge( $messages, $_amp_load_errors->get_error_messages( $error_code ) );
		}
		$message = implode( "\n * ", $messages );
		$message = str_replace( array( '<code>', '</code>' ), '`', $message );
		$message = html_entity_decode( $message, ENT_QUOTES, 'UTF-8' );

		if ( ! class_exists( 'WP_CLI' ) ) {
			echo "$message\n"; // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

			exit( 1 );
		}

		WP_CLI::warning( $message );
	}

	return;
}

/**
 * Print admin notice if plugin installed with incorrect slug (which impacts WordPress's auto-update system).
 *
 * @since 1.0
 * @internal
 */
function _amp_incorrect_plugin_slug_admin_notice() {
	$actual_slug = basename( AMP__DIR__ );
	?>
	<div class="notice notice-warning">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %1$s is the current directory name, and %2$s is the required directory name */
					__( 'You appear to have installed the AMP plugin incorrectly. It is currently installed in the <code>%1$s</code> directory, but it needs to be placed in a directory named <code>%2$s</code>. Please rename the directory. This is important for WordPress plugin auto-updates.', 'amp' ),
					$actual_slug,
					'amp'
				)
			);
			?>
		</p>
	</div>
	<?php
}

if ( 'amp' !== basename( AMP__DIR__ ) ) {
	add_action( 'admin_notices', '_amp_incorrect_plugin_slug_admin_notice' );
}

require_once AMP__DIR__ . '/vendor/autoload.php';
require_once AMP__DIR__ . '/includes/admin/class-amp-prepare-data.php';

register_activation_hook( __FILE__, 'amp_activate' );

register_deactivation_hook( __FILE__, 'amp_deactivate' );

add_action( 'plugins_loaded', 'amp_bootstrap_plugin', defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~PHP_INT_MAX ); // phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound

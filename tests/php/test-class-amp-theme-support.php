<?php
/**
 * Tests for Theme Support.
 *
 * @package AMP
 * @since 0.7
 */

use org\bovigo\vfs;
use Amp\AmpWP\Tests\PrivateAccess;

/**
 * Tests for Theme Support.
 *
 * @covers AMP_Theme_Support
 */
class Test_AMP_Theme_Support extends WP_UnitTestCase {

	use PrivateAccess;

	/**
	 * The name of the tested class.
	 *
	 * @var string
	 */
	const TESTED_CLASS = 'AMP_Theme_Support';

	/**
	 * Set up before class.
	 */
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		AMP_HTTP::$server_timing = true;
	}

	/**
	 * Set up.
	 */
	public function setUp() {
		parent::setUp();
		AMP_Validation_Manager::reset_validation_results();
		unset( $GLOBALS['current_screen'] );
		delete_option( AMP_Options_Manager::OPTION_NAME ); // Make sure default reader mode option does not override theme support being added.
		remove_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::read_theme_support();
	}

	/**
	 * After a test method runs, reset any state in WordPress the test method might have changed.
	 *
	 * @global WP_Scripts $wp_scripts
	 */
	public function tearDown() {
		global $wp_scripts, $wp_styles, $wp_admin_bar;
		$wp_scripts   = null;
		$wp_styles    = null;
		$wp_admin_bar = null;

		parent::tearDown();
		unset( $GLOBALS['show_admin_bar'] );
		AMP_Validation_Manager::reset_validation_results();
		remove_theme_support( AMP_Theme_Support::SLUG );
		remove_theme_support( 'custom-header' );
		$_REQUEST                = [];
		$_GET                    = [];
		$_SERVER['QUERY_STRING'] = '';
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_SERVER['REQUEST_METHOD'] );
		unset( $GLOBALS['content_width'] );
		if ( isset( $GLOBALS['wp_customize'] ) ) {
			$GLOBALS['wp_customize']->stop_previewing_theme();
		}
		AMP_HTTP::$headers_sent = [];
		remove_all_filters( 'theme_root' );
		remove_all_filters( 'template' );
		remove_all_filters( 'show_admin_bar' );
	}

	/**
	 * Test init.
	 *
	 * @covers AMP_Theme_Support::init()
	 */
	public function test_init() {
		$_REQUEST['__amp_source_origin'] = 'foo';
		$_GET['__amp_source_origin']     = 'foo';

		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		$this->assertEquals( 10, has_action( 'widgets_init', [ self::TESTED_CLASS, 'register_widgets' ] ) );
		$this->assertEquals( PHP_INT_MAX, has_action( 'wp', [ self::TESTED_CLASS, 'finish_init' ] ) );
	}

	/**
	 * Test add_theme_support(amp) with invalid args.
	 *
	 * @expectedIncorrectUsage add_theme_support
	 * @covers \AMP_Theme_Support::read_theme_support()
	 * @covers \AMP_Theme_Support::get_theme_support_args()
	 */
	public function test_read_theme_support_bad_args_array() {
		$args = [
			AMP_Theme_Support::PAIRED_FLAG => false,
			'invalid_param_key'            => [],
		];
		add_theme_support( AMP_Theme_Support::SLUG, $args );
		AMP_Theme_Support::read_theme_support();
		$this->assertTrue( current_theme_supports( AMP_Theme_Support::SLUG ) );
		$this->assertEquals( $args, AMP_Theme_Support::get_theme_support_args() );
	}

	/**
	 * Test add_theme_support(amp) with invalid args.
	 *
	 * @expectedIncorrectUsage add_theme_support
	 * @covers \AMP_Theme_Support::read_theme_support()
	 */
	public function test_read_theme_support_bad_available_callback() {
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'available_callback' => static function() {
					return (bool) wp_rand( 0, 1 );
				},
			]
		);
		AMP_Theme_Support::read_theme_support();
		$this->assertTrue( current_theme_supports( AMP_Theme_Support::SLUG ) );
	}

	/**
	 * Test read_theme_support, get_theme_support_args, and is_support_added_via_option.
	 *
	 * @covers \AMP_Theme_Support::read_theme_support()
	 * @covers \AMP_Theme_Support::get_support_mode_added_via_option()
	 * @covers \AMP_Theme_Support::get_support_mode_added_via_theme()
	 * @covers \AMP_Theme_Support::get_support_mode()
	 * @covers \AMP_Theme_Support::get_theme_support_args()
	 */
	public function test_read_theme_support_and_support_args() {

		// Test default is reader mode.
		delete_option( AMP_Options_Manager::OPTION_NAME );
		remove_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::read_theme_support();
		$this->assertEquals( AMP_Theme_Support::READER_MODE_SLUG, AMP_Theme_Support::get_support_mode() );
		$this->assertFalse( current_theme_supports( AMP_Theme_Support::SLUG ) );

		// Test that when default options are used, the theme support flag is the default.
		delete_option( AMP_Options_Manager::OPTION_NAME );
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[ AMP_Theme_Support::PAIRED_FLAG => false ]
		);
		$this->assertEquals( AMP_Theme_Support::STANDARD_MODE_SLUG, AMP_Options_Manager::get_option( 'theme_support' ) );
		AMP_Theme_Support::read_theme_support();
		$this->assertEquals( AMP_Theme_Support::STANDARD_MODE_SLUG, AMP_Theme_Support::get_support_mode() );
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[ AMP_Theme_Support::PAIRED_FLAG => true ]
		);
		AMP_Theme_Support::read_theme_support();
		$this->assertEquals( AMP_Theme_Support::TRANSITIONAL_MODE_SLUG, AMP_Theme_Support::get_support_mode() );

		// Test that reader mode being set via option overrides theme support flag.
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Options_Manager::update_option( 'theme_support', AMP_Theme_Support::READER_MODE_SLUG ); // Will override the theme support flag.
		AMP_Theme_Support::read_theme_support();
		$this->assertSame( AMP_Theme_Support::READER_MODE_SLUG, AMP_Theme_Support::get_support_mode() );

		// Test that Standard mode in theme does not override Transitional mode set via option (user option prevails).
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Options_Manager::update_option( 'theme_support', AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		AMP_Theme_Support::read_theme_support();
		$this->assertSame( AMP_Theme_Support::TRANSITIONAL_MODE_SLUG, AMP_Theme_Support::get_support_mode() );

		// Test that standard via option trumps transitional in theme.
		$args = [
			'templates_supported'          => 'all',
			AMP_Theme_Support::PAIRED_FLAG => true,
			'comments_live_list'           => true,
		];
		add_theme_support( AMP_Theme_Support::SLUG, $args );
		AMP_Options_Manager::update_option( 'theme_support', AMP_Theme_Support::STANDARD_MODE_SLUG ); // Will override the theme support flag.
		AMP_Theme_Support::read_theme_support();
		$this->assertEquals(
			array_merge(
				$args,
				[
					AMP_Theme_Support::PAIRED_FLAG => false, // The Standard user option overrides the theme flag.
				]
			),
			AMP_Theme_Support::get_theme_support_args()
		);
		$this->assertSame( AMP_Theme_Support::STANDARD_MODE_SLUG, AMP_Theme_Support::get_support_mode_added_via_option() );
		$this->assertSame( AMP_Theme_Support::TRANSITIONAL_MODE_SLUG, AMP_Theme_Support::get_support_mode_added_via_theme() );
		$this->assertSame( AMP_Theme_Support::STANDARD_MODE_SLUG, AMP_Theme_Support::get_support_mode() );
		$this->assertTrue( current_theme_supports( AMP_Theme_Support::SLUG ) );

		// Test that standard via theme does not trump transitional option.
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Options_Manager::update_option( 'theme_support', AMP_Theme_Support::TRANSITIONAL_MODE_SLUG ); // Will be ignored since standard in theme overrides transitional option.
		AMP_Theme_Support::read_theme_support();
		$this->assertTrue( current_theme_supports( AMP_Theme_Support::SLUG ) );
		$this->assertSame( AMP_Theme_Support::TRANSITIONAL_MODE_SLUG, AMP_Theme_Support::get_support_mode_added_via_option() );
		$this->assertSame( AMP_Theme_Support::STANDARD_MODE_SLUG, AMP_Theme_Support::get_support_mode_added_via_theme() );
		$this->assertSame( AMP_Theme_Support::TRANSITIONAL_MODE_SLUG, AMP_Theme_Support::get_support_mode() );
		$this->assertEquals( [ AMP_Theme_Support::PAIRED_FLAG => true ], AMP_Theme_Support::get_theme_support_args() );

		// Test that no support via theme can be overridden with option.
		remove_theme_support( AMP_Theme_Support::SLUG );
		AMP_Options_Manager::update_option( 'theme_support', AMP_Theme_Support::STANDARD_MODE_SLUG );
		AMP_Theme_Support::read_theme_support();
		$this->assertNull( AMP_Theme_Support::get_support_mode_added_via_theme() );
		$this->assertSame( AMP_Theme_Support::STANDARD_MODE_SLUG, AMP_Theme_Support::get_support_mode_added_via_option() );
		$this->assertTrue( current_theme_supports( AMP_Theme_Support::SLUG ) );

		// Test that no support via theme can be overridden with option.
		remove_theme_support( AMP_Theme_Support::SLUG );
		AMP_Options_Manager::update_option( 'theme_support', AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		AMP_Theme_Support::read_theme_support();
		$this->assertNull( AMP_Theme_Support::get_support_mode_added_via_theme() );
		$this->assertSame( AMP_Theme_Support::TRANSITIONAL_MODE_SLUG, AMP_Theme_Support::get_support_mode_added_via_option() );
		$this->assertTrue( current_theme_supports( AMP_Theme_Support::SLUG ) );

		// Test that forced validation works.
		remove_theme_support( AMP_Theme_Support::SLUG );
		delete_option( AMP_Options_Manager::OPTION_NAME );
		$_GET[ AMP_Validation_Manager::VALIDATE_QUERY_VAR ] = AMP_Validation_Manager::get_amp_validate_nonce();
		AMP_Theme_Support::read_theme_support();
		$this->assertSame( AMP_Theme_Support::STANDARD_MODE_SLUG, AMP_Theme_Support::get_support_mode_added_via_option() );
		$this->assertNull( AMP_Theme_Support::get_support_mode_added_via_theme() );
		$this->assertTrue( get_theme_support( AMP_Theme_Support::SLUG ) );
	}

	/**
	 * Test supports_reader_mode.
	 *
	 * @covers \AMP_Theme_Support::supports_reader_mode()
	 */
	public function test_supports_reader_mode() {
		$themes_directory = 'themes';
		$mock_theme       = 'example-theme';
		$mock_directory   = vfs\vfsStream::setup( $themes_directory, null, [ $mock_theme => [] ] );

		// Add filters so that get_template_directory() the theme in the mock filesystem.
		add_filter(
			'theme_root',
			function() use ( $mock_directory ) {
				return $mock_directory->url();
			}
		);

		add_filter(
			'template',
			function() use ( $mock_theme ) {
				return $mock_theme;
			}
		);

		add_theme_support( 'amp' );
		AMP_Theme_Support::read_theme_support();

		// The mode is Standard, and there is no /amp directory, so this should be false.
		$this->assertFalse( AMP_Theme_Support::supports_reader_mode() );

		remove_theme_support( 'amp' );
		AMP_Theme_Support::read_theme_support();

		// The mode is Reader, but there is no /amp directory in the theme.
		$this->assertFalse( AMP_Theme_Support::supports_reader_mode() );

		// Add an /amp directory to the theme.
		$mock_directory->getChild( $mock_theme )->addChild( vfs\vfsStream::newDirectory( 'amp' ) );

		// This should be true, as there is now an /amp directory in the theme.
		$this->assertTrue( AMP_Theme_Support::supports_reader_mode() );
	}

	/**
	 * Test that init finalization, including amphtml link is added at the right time.
	 *
	 * @covers AMP_Theme_Support::finish_init()
	 */
	public function test_finish_init() {
		$post_id = self::factory()->post->create( [ 'post_title' => 'Test' ] );
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				AMP_Theme_Support::PAIRED_FLAG => true,
				'template_dir'                 => 'amp',
			]
		);

		// Test transitional mode singular, where not on endpoint that it causes amphtml link to be added.
		remove_action( 'wp_head', 'amp_add_amphtml_link' );
		$this->go_to( get_permalink( $post_id ) );
		$this->assertFalse( is_amp_endpoint() );
		AMP_Theme_Support::finish_init();
		$this->assertEquals( 10, has_action( 'wp_head', 'amp_add_amphtml_link' ) );

		// Test transitional mode homepage, where still not on endpoint that it causes amphtml link to be added.
		remove_action( 'wp_head', 'amp_add_amphtml_link' );
		$this->go_to( home_url() );
		$this->assertFalse( is_amp_endpoint() );
		AMP_Theme_Support::finish_init();
		$this->assertEquals( 10, has_action( 'wp_head', 'amp_add_amphtml_link' ) );

		// Test canonical, so amphtml link is not added and init finalizes.
		remove_action( 'wp_head', 'amp_add_amphtml_link' );
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				AMP_Theme_Support::PAIRED_FLAG => false,
				'template_dir'                 => 'amp',
			]
		);
		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( is_amp_endpoint() );
		AMP_Theme_Support::finish_init();
		$this->assertFalse( has_action( 'wp_head', 'amp_add_amphtml_link' ) );
		$this->assertEquals( 10, has_filter( 'index_template_hierarchy', [ 'AMP_Theme_Support', 'filter_amp_template_hierarchy' ] ), 'Expected add_amp_template_filters to have been called since template_dir is not empty' );
		$this->assertEquals( 20, has_action( 'wp_head', 'amp_add_generator_metadata' ), 'Expected add_hooks to have been called' );
	}

	/**
	 * Test ensure_proper_amp_location for canonical.
	 *
	 * @covers AMP_Theme_Support::ensure_proper_amp_location()
	 */
	public function test_ensure_proper_amp_location_canonical() {
		add_theme_support( AMP_Theme_Support::SLUG );
		$e = null;

		// Already canonical.
		$_SERVER['REQUEST_URI'] = '/foo/bar/';
		$this->assertFalse( AMP_Theme_Support::ensure_proper_amp_location( false ) );

		// URL query param.
		$_GET[ amp_get_slug() ] = '';
		$_SERVER['REQUEST_URI'] = add_query_arg( amp_get_slug(), '', '/foo/bar' );
		try {
			$this->assertTrue( AMP_Theme_Support::ensure_proper_amp_location( false ) );
		} catch ( Exception $exception ) {
			$e = $exception;
		}
		$this->assertTrue( isset( $e ) ); // wp_safe_redirect() modifies the headers, and causes an error.
		$this->assertContains( 'headers already sent', $e->getMessage() );
		$e = null;

		// Endpoint.
		unset( $_GET[ amp_get_slug() ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		set_query_var( amp_get_slug(), '' );
		$_SERVER['REQUEST_URI'] = '/2016/01/24/foo/amp/';
		try {
			$this->assertTrue( AMP_Theme_Support::ensure_proper_amp_location( false ) );
		} catch ( Exception $exception ) {
			$e = $exception;
		}
		$this->assertContains( 'headers already sent', $e->getMessage() );
		$e = null;
	}

	/**
	 * Test ensure_proper_amp_location for transitional.
	 *
	 * @covers AMP_Theme_Support::ensure_proper_amp_location()
	 */
	public function test_ensure_proper_amp_location_transitional() {
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'template_dir' => './',
			]
		);
		$e = null;

		// URL query param, no redirection.
		$_GET[ amp_get_slug() ] = '';
		$_SERVER['REQUEST_URI'] = add_query_arg( amp_get_slug(), '', '/foo/bar' );
		$this->assertFalse( AMP_Theme_Support::ensure_proper_amp_location( false ) );

		// Endpoint, redirect.
		unset( $_GET[ amp_get_slug() ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		set_query_var( amp_get_slug(), '' );
		$_SERVER['REQUEST_URI'] = '/2016/01/24/foo/amp/';
		try {
			$this->assertTrue( AMP_Theme_Support::ensure_proper_amp_location( false ) );
		} catch ( Exception $exception ) {
			$e = $exception;
		}
		$this->assertContains( 'headers already sent', $e->getMessage() );
	}

	/**
	 * Test redirect_non_amp_url.
	 *
	 * @covers AMP_Theme_Support::redirect_non_amp_url()
	 */
	public function test_redirect_non_amp_url() {
		$e = null;

		$redirect_status_code = null;
		add_filter(
			'wp_redirect_status',
			static function( $code ) use ( &$redirect_status_code ) {
				$redirect_status_code = $code;
				return $code;
			},
			PHP_INT_MAX
		);

		// Try AMP URL param.
		$_SERVER['REQUEST_URI'] = add_query_arg( amp_get_slug(), '', '/foo/bar' );
		try {
			$redirect_status_code = null;
			$this->assertTrue( AMP_Theme_Support::redirect_non_amp_url( 302 ) );
		} catch ( Exception $exception ) {
			$e = $exception;
		}
		$this->assertTrue( isset( $e ) );
		$this->assertContains( 'headers already sent', $e->getMessage() );
		$this->assertSame( 302, $redirect_status_code );
		$e = null;

		// Try AMP URL endpoint.
		$_SERVER['REQUEST_URI'] = '/2016/01/24/foo/amp/';
		try {
			$redirect_status_code = null;
			$this->assertTrue( AMP_Theme_Support::redirect_non_amp_url( 301 ) );
		} catch ( Exception $exception ) {
			$e = $exception;
		}
		$this->assertTrue( isset( $e ) ); // wp_safe_redirect() modifies the headers, and causes an error.
		$this->assertContains( 'headers already sent', $e->getMessage() );
		$this->assertSame( 301, $redirect_status_code );
		$e = null;

		// Make sure that if the URL doesn't have AMP that there should be no redirect.
		$_SERVER['REQUEST_URI'] = '/foo/bar';
		$this->assertFalse( AMP_Theme_Support::redirect_non_amp_url() );
	}

	/**
	 * Test is_paired_available.
	 *
	 * @covers AMP_Theme_Support::is_paired_available()
	 */
	public function test_is_paired_available() {

		// Establish initial state.
		$post_id = self::factory()->post->create( [ 'post_title' => 'Test' ] );
		remove_theme_support( AMP_Theme_Support::SLUG );
		query_posts( [ 'p' => $post_id ] ); // phpcs:ignore
		$this->assertTrue( is_singular() );

		// Transitional support is not available if theme support is not present or canonical.
		$this->assertFalse( AMP_Theme_Support::is_paired_available() );
		add_theme_support( AMP_Theme_Support::SLUG );
		$this->assertFalse( AMP_Theme_Support::is_paired_available() );

		// Transitional mode is available once template_dir is supplied.
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'template_dir' => 'amp-templates',
			]
		);
		$this->assertTrue( AMP_Theme_Support::is_paired_available() );

		// Transitional mode not available when post does not support AMP.
		add_filter( 'amp_skip_post', '__return_true' );
		$this->assertFalse( AMP_Theme_Support::is_paired_available() );
		$this->assertTrue( is_singular() );
		query_posts( [ 's' => 'test' ] ); // phpcs:ignore
		$this->assertTrue( is_search() );
		$this->assertTrue( AMP_Theme_Support::is_paired_available() );
		remove_filter( 'amp_skip_post', '__return_true' );

		// Check that mode=paired works.
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				AMP_Theme_Support::PAIRED_FLAG => true,
			]
		);
		add_filter(
			'amp_supportable_templates',
			static function( $supportable_templates ) {
				$supportable_templates['is_singular']['supported'] = true;
				$supportable_templates['is_search']['supported']   = false;
				return $supportable_templates;
			}
		);
		query_posts( [ 'p' => $post_id ] ); // phpcs:ignore
		$this->assertTrue( is_singular() );
		$this->assertTrue( AMP_Theme_Support::is_paired_available() );

		query_posts( [ 's' => $post_id ] ); // phpcs:ignore
		$this->assertTrue( is_search() );
		$this->assertFalse( AMP_Theme_Support::is_paired_available() );
	}

	/**
	 * Test is_customize_preview_iframe.
	 *
	 * @covers AMP_Theme_Support::is_customize_preview_iframe()
	 */
	public function test_is_customize_preview_iframe() {
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		$this->assertFalse( AMP_Theme_Support::is_customize_preview_iframe() );
		$GLOBALS['wp_customize'] = new WP_Customize_Manager(
			[
				'messenger_channel' => 'baz',
			]
		);
		$this->assertFalse( AMP_Theme_Support::is_customize_preview_iframe() );
		$GLOBALS['wp_customize']->start_previewing_theme();
		$this->assertTrue( AMP_Theme_Support::is_customize_preview_iframe() );
	}

	/**
	 * Test add_amp_template_filters.
	 *
	 * @covers AMP_Theme_Support::add_amp_template_filters()
	 */
	public function test_add_amp_template_filters() {
		$template_types = $this->get_private_property( 'AMP_Theme_Support', 'template_types' );

		AMP_Theme_Support::add_amp_template_filters();

		foreach ( $template_types as $template_type ) {
			$template_type = preg_replace( '|[^a-z0-9-]+|', '', $template_type );

			$this->assertEquals( 10, has_filter( "{$template_type}_template_hierarchy", [ self::TESTED_CLASS, 'filter_amp_template_hierarchy' ] ) );
		}
	}

	/**
	 * Test validate_non_amp_theme.
	 *
	 * @global WP_Widget_Factory $wp_widget_factory
	 * @global WP_Scripts $wp_scripts
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_validate_non_amp_theme() {
		wp_scripts();
		wp();
		add_filter( 'amp_validation_error_sanitized', '__return_true' );
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		ob_start();
		?>
		<!DOCTYPE html>
		<html lang="en-US" class="no-js">
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="maximum-scale=1.0">
				<?php wp_head(); ?>
			</head>
			<body>
				<amp-mathml layout="container" data-formula="\[x = {-b \pm \sqrt{b^2-4ac} \over 2a}.\]"></amp-mathml>
				<?php wp_print_scripts( 'amp-mathml' ); ?>
			</body>
		</html>
		<?php
		$original_html  = trim( ob_get_clean() );
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html );

		// Invalid viewport meta tag is not present.
		$this->assertNotContains( '<meta name="viewport" content="maximum-scale=1.0">', $sanitized_html );

		// Correct viewport meta tag was added.
		$this->assertContains( '<meta name="viewport" content="width=device-width">', $sanitized_html );

		// MathML script was added.
		$this->assertContains( '<script type="text/javascript" src="https://cdn.ampproject.org/v0/amp-mathml-0.1.js" async custom-element="amp-mathml"></script>', $sanitized_html ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
	}

	/**
	 * Test incorrect usage for get_template_availability.
	 *
	 * @expectedIncorrectUsage AMP_Theme_Support::get_template_availability
	 * @covers AMP_Theme_Support::get_template_availability()
	 */
	public function test_incorrect_usage_get_template_availability() {
		global $wp_query;
		remove_action( 'parse_query', 'wp_hide_admin_bar_offline' );

		// Test no query available.
		$wp_query     = null;
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertInternalType( 'array', $availability );
		$this->assertEquals( [ 'no_query_available' ], $availability['errors'] );
		$this->assertFalse( $availability['supported'] );
		$this->assertNull( $availability['immutable'] );
		$this->assertNull( $availability['template'] );

		// Test no theme support.
		remove_theme_support( AMP_Theme_Support::SLUG );
		$this->go_to( get_permalink( self::factory()->post->create() ) );
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertEquals( [ 'no_theme_support' ], $availability['errors'] );
		$this->assertFalse( $availability['supported'] );
		$this->assertNull( $availability['immutable'] );
		$this->assertNull( $availability['template'] );
	}

	/**
	 * Test get_template_availability with available_callback.
	 *
	 * @expectedIncorrectUsage add_theme_support
	 * @covers AMP_Theme_Support::get_template_availability()
	 */
	public function test_get_template_availability_with_available_callback() {
		remove_action( 'parse_query', 'wp_hide_admin_bar_offline' );
		$this->go_to( get_permalink( self::factory()->post->create() ) );
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'available_callback' => '__return_true',
			]
		);
		AMP_Theme_Support::init();
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertEquals(
			$availability,
			[
				'supported' => true,
				'immutable' => true,
				'template'  => null,
				'errors'    => [],
			]
		);

		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'available_callback' => '__return_false',
			]
		);
		AMP_Theme_Support::init();
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertEquals(
			$availability,
			[
				'supported' => false,
				'immutable' => true,
				'template'  => null,
				'errors'    => [ 'available_callback' ],
			]
		);
	}

	/**
	 * Test get_template_availability.
	 *
	 * @covers AMP_Theme_Support::get_template_availability()
	 */
	public function test_get_template_availability() {
		remove_action( 'parse_query', 'wp_hide_admin_bar_offline' );
		global $wp_query;
		$post_id = self::factory()->post->create();
		query_posts( [ 'p' => $post_id ] ); // phpcs:ignore

		// Test successful match of singular template.
		$this->assertTrue( is_singular() );
		AMP_Options_Manager::update_option( 'all_templates_supported', false );
		add_theme_support( AMP_Theme_Support::SLUG );
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertEmpty( $availability['errors'] );
		$this->assertTrue( $availability['supported'] );
		$this->assertFalse( $availability['immutable'] );
		$this->assertEquals( 'is_singular', $availability['template'] );

		// Test successful match when passing WP_Query and WP_Post into method.
		$query        = $wp_query;
		$wp_query     = null;
		$availability = AMP_Theme_Support::get_template_availability( $query );
		$this->assertTrue( $availability['supported'] );
		$this->assertEquals( 'is_singular', $availability['template'] );
		$availability = AMP_Theme_Support::get_template_availability( get_post( $post_id ) );
		$this->assertTrue( $availability['supported'] );
		$this->assertEquals( 'is_singular', $availability['template'] );
		$this->assertNull( $wp_query ); // Make sure it is reset.

		// Test nested hierarchy.
		AMP_Options_Manager::update_option( 'supported_templates', [ 'is_special', 'is_custom' ] );
		add_filter(
			'amp_supportable_templates',
			static function( $templates ) {
				$templates['is_single']        = [
					'label'     => 'Single post',
					'supported' => false,
					'parent'    => 'is_singular',
				];
				$templates['is_special']       = [
					'label'    => 'Special post',
					'parent'   => 'is_single',
					'callback' => static function( WP_Query $query ) {
						return $query->is_singular() && 'special' === get_post( $query->get_queried_object_id() )->post_name;
					},
				];
				$templates['is_page']          = [
					'label'     => 'Page',
					'supported' => true,
					'parent'    => 'is_singular',
				];
				$templates['is_custom']        = [
					'label'    => 'Custom',
					'callback' => static function( WP_Query $query ) {
						return false !== $query->get( 'custom', false );
					},
				];
				$templates['is_custom[thing]'] = [
					'label'    => 'Custom Thing',
					'parent'   => 'is_custom',
					'callback' => static function( WP_Query $query ) {
						return 'thing' === $query->get( 'custom', false );
					},
				];
				return $templates;
			}
		);
		add_filter(
			'query_vars',
			static function( $vars ) {
				$vars[] = 'custom';
				return $vars;
			}
		);

		$availability = AMP_Theme_Support::get_template_availability( get_post( $post_id ) );
		$this->assertFalse( $availability['supported'] );
		$this->assertTrue( $availability['immutable'] );
		$this->assertEquals( 'is_single', $availability['template'] );

		$special_id   = self::factory()->post->create(
			[
				'post_type' => 'post',
				'post_name' => 'special',
			]
		);
		$availability = AMP_Theme_Support::get_template_availability( get_post( $special_id ) );
		$this->assertTrue( $availability['supported'] );
		$this->assertEquals( 'is_special', $availability['template'] );
		$this->assertFalse( $availability['immutable'] );

		remove_post_type_support( 'page', AMP_Post_Type_Support::SLUG );
		$availability = AMP_Theme_Support::get_template_availability( self::factory()->post->create_and_get( [ 'post_type' => 'page' ] ) );
		$this->assertFalse( $availability['supported'] );
		$this->assertEquals( [ 'post-type-support' ], $availability['errors'] );
		$this->assertEquals( 'is_page', $availability['template'] );
		add_post_type_support( 'page', AMP_Post_Type_Support::SLUG );
		$availability = AMP_Theme_Support::get_template_availability( self::factory()->post->create_and_get( [ 'post_type' => 'page' ] ) );
		$this->assertTrue( $availability['supported'] );

		// Test is_custom.
		$this->go_to( '/?custom=1' );
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertTrue( $availability['supported'] );
		$this->assertEquals( 'is_custom', $availability['template'] );

		// Test is_custom[thing].
		$this->go_to( '/?custom=thing' );
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertFalse( $availability['supported'] );
		$this->assertEquals( 'is_custom[thing]', $availability['template'] );
	}

	/**
	 * Test get_template_availability with ambiguous matching templates.
	 *
	 * @covers AMP_Theme_Support::get_template_availability()
	 */
	public function test_get_template_availability_with_ambiguity() {
		AMP_Options_Manager::update_option( 'all_templates_supported', true );
		add_theme_support( AMP_Theme_Support::SLUG );
		$custom_post_type = 'book';
		register_post_type(
			$custom_post_type,
			[
				'has_archive' => true,
				'public'      => true,
			]
		);
		self::factory()->post->create(
			[
				'post_type'  => $custom_post_type,
				'post_title' => 'test',
			]
		);

		// Test that when doing a post_type archive, we get the post type archive as expected.
		$this->go_to( "/?post_type=$custom_post_type" );
		$this->assertTrue( is_post_type_archive( $custom_post_type ) );
		$this->assertFalse( is_search() );
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertTrue( $availability['supported'] );
		$this->assertEmpty( $availability['errors'] );
		$this->assertEquals( "is_post_type_archive[$custom_post_type]", $availability['template'] );

		// Test that when doing a search and a post_type archive, the search wins.
		$this->go_to( "/?s=test&post_type=$custom_post_type" );
		$this->assertTrue( is_post_type_archive( $custom_post_type ) );
		$this->assertTrue( is_search() );
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertTrue( $availability['supported'] );
		$this->assertEmpty( $availability['errors'] );
		$this->assertEquals( 'is_search', $availability['template'] );
	}

	/**
	 * Test get_template_availability with broken parent relationship.
	 *
	 * @covers AMP_Theme_Support::get_template_availability()
	 */
	public function test_get_template_availability_with_missing_parent() {
		AMP_Options_Manager::update_option( 'supported_templates', [ 'missing_parent' ] );
		add_theme_support( AMP_Theme_Support::SLUG );
		add_filter(
			'amp_supportable_templates',
			static function ( $templates ) {
				$templates['missing_parent'] = [
					'label'    => 'Missing parent',
					'parent'   => 'is_unknown',
					'callback' => static function( WP_Query $query ) {
						return false !== $query->get( 'missing_parent', false );
					},
				];
				return $templates;
			}
		);
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars[] = 'missing_parent';
				return $vars;
			}
		);

		// Test missing_parent.
		$this->go_to( '/?missing_parent=1' );
		$this->setExpectedIncorrectUsage( 'AMP_Theme_Support::get_template_availability' );
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertTrue( $availability['supported'] );
		$this->assertEquals( 'missing_parent', $availability['template'] );
	}

	/**
	 * Test get_supportable_templates.
	 *
	 * @covers AMP_Theme_Support::get_supportable_templates()
	 */
	public function test_get_supportable_templates() {

		register_taxonomy(
			'accolade',
			'post',
			[
				'public' => true,
			]
		);
		register_taxonomy(
			'complaint',
			'post',
			[
				'public' => false,
			]
		);
		register_post_type(
			'announcement',
			[
				'public'      => true,
				'has_archive' => true,
			]
		);

		// Test default case with non-static front page.
		update_option( 'show_on_front', 'posts' );
		AMP_Options_Manager::update_option( 'all_templates_supported', true );
		$supportable_templates = AMP_Theme_Support::get_supportable_templates();
		foreach ( $supportable_templates as $id => $supportable_template ) {
			$this->assertNotInternalType( 'numeric', $id );
			$this->assertArrayHasKey( 'label', $supportable_template, "$id has label" );
			$this->assertTrue( $supportable_template['supported'] );
			$this->assertFalse( $supportable_template['immutable'] );
		}
		$this->assertArrayHasKey( 'is_singular', $supportable_templates );
		$this->assertArrayNotHasKey( 'is_front_page', $supportable_templates );
		$this->assertArrayHasKey( 'is_home', $supportable_templates );
		$this->assertArrayNotHasKey( 'parent', $supportable_templates['is_home'] );

		// Test common templates.
		$this->assertArrayHasKey( 'is_singular', $supportable_templates );
		$this->assertArrayHasKey( 'is_archive', $supportable_templates );
		$this->assertArrayHasKey( 'is_author', $supportable_templates );
		$this->assertArrayHasKey( 'is_date', $supportable_templates );
		$this->assertArrayHasKey( 'is_search', $supportable_templates );
		$this->assertArrayHasKey( 'is_404', $supportable_templates );
		$this->assertArrayHasKey( 'is_category', $supportable_templates );
		$this->assertArrayHasKey( 'is_tag', $supportable_templates );
		$this->assertArrayHasKey( 'is_tax[accolade]', $supportable_templates );
		$this->assertEquals( 'is_archive', $supportable_templates['is_tax[accolade]']['parent'] );
		$this->assertArrayNotHasKey( 'is_tax[complaint]', $supportable_templates );
		$this->assertArrayHasKey( 'is_post_type_archive[announcement]', $supportable_templates );
		$this->assertEquals( 'is_archive', $supportable_templates['is_post_type_archive[announcement]']['parent'] );

		// Test static homepage and page for posts.
		$page_on_front  = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$page_for_posts = self::factory()->post->create( [ 'post_type' => 'page' ] );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $page_for_posts );
		update_option( 'page_on_front', $page_on_front );
		$supportable_templates = AMP_Theme_Support::get_supportable_templates();
		foreach ( $supportable_templates as $id => $supportable_template ) {
			$this->assertNotInternalType( 'numeric', $id );
			$this->assertArrayHasKey( 'label', $supportable_template, "$id has label" );
		}
		$this->assertArrayHasKey( 'is_front_page', $supportable_templates );
		$this->assertArrayHasKey( 'parent', $supportable_templates['is_front_page'] );
		$this->assertEquals( 'is_singular', $supportable_templates['is_front_page']['parent'] );

		// Test inclusion of custom template, forcing category to be not-supported, and singular to be supported.
		add_filter(
			'amp_supportable_templates',
			static function( $templates ) {
				$templates['is_category']['supported'] = false;
				$templates['is_singular']['supported'] = true;

				$templates['is_custom'] = [
					'label'    => 'Custom',
					'callback' => static function( WP_Query $query ) {
						return false !== $query->get( 'custom', false );
					},
				];
				return $templates;
			}
		);
		$supportable_templates = AMP_Theme_Support::get_supportable_templates();
		$this->assertTrue( $supportable_templates['is_category']['immutable'] );
		$this->assertFalse( $supportable_templates['is_category']['supported'] );
		$this->assertFalse( $supportable_templates['is_category']['user_supported'] );
		$this->assertTrue( $supportable_templates['is_singular']['immutable'] );
		$this->assertTrue( $supportable_templates['is_singular']['supported'] );
		$this->assertTrue( $supportable_templates['is_singular']['user_supported'] );
		$this->assertArrayHasKey( 'is_custom', $supportable_templates );
		remove_all_filters( 'amp_supportable_templates' );

		// Test supporting templates by theme support args: all.
		AMP_Options_Manager::update_option( 'all_templates_supported', false );
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'templates_supported' => 'all',
			]
		);
		AMP_Options_Manager::update_option( 'theme_support', AMP_Theme_Support::STANDARD_MODE_SLUG );
		AMP_Theme_Support::read_theme_support();

		AMP_Theme_Support::init();
		$supportable_templates = AMP_Theme_Support::get_supportable_templates();
		foreach ( $supportable_templates as $key => $supportable_template ) {
			$this->assertTrue( $supportable_template['supported'], "Expected $key to be supported" );
			$this->assertTrue( $supportable_template['immutable'], "Expected $key to be immutable" );
		}

		// Test supporting templates by theme support args: selective templates.
		AMP_Options_Manager::update_option( 'all_templates_supported', false );
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'templates_supported' => [
					'is_date'   => true,
					'is_author' => false,
				],
			]
		);
		AMP_Theme_Support::init();
		$supportable_templates = AMP_Theme_Support::get_supportable_templates();
		$this->assertTrue( $supportable_templates['is_date']['supported'] );
		$this->assertTrue( $supportable_templates['is_date']['immutable'] );
		$this->assertFalse( $supportable_templates['is_author']['supported'] );
		$this->assertTrue( $supportable_templates['is_author']['immutable'] );
		$this->assertTrue( $supportable_templates['is_singular']['supported'] );
		$this->assertFalse( $supportable_templates['is_singular']['immutable'] );
		$this->assertFalse( $supportable_templates['is_category']['supported'] );
		$this->assertFalse( $supportable_templates['is_category']['immutable'] );
	}

	/**
	 * Test add_hooks.
	 *
	 * @covers AMP_Theme_Support::add_hooks()
	 */
	public function test_add_hooks() {
		AMP_Theme_Support::add_hooks();
		$this->assertFalse( has_action( 'wp_head', 'wp_post_preview_js' ) );
		$this->assertFalse( has_action( 'wp_head', 'wp_oembed_add_host_js' ) );

		$this->assertFalse( has_action( 'wp_head', 'print_emoji_detection_script' ) );
		$this->assertFalse( has_action( 'wp_print_styles', 'print_emoji_styles' ) );
		$this->assertEquals( 10, has_action( 'wp_print_styles', [ 'AMP_Theme_Support', 'print_emoji_styles' ] ) );
		$this->assertEquals( 10, has_filter( 'the_title', 'wp_staticize_emoji' ) );
		$this->assertEquals( 10, has_filter( 'the_excerpt', 'wp_staticize_emoji' ) );
		$this->assertEquals( 10, has_filter( 'the_content', 'wp_staticize_emoji' ) );
		$this->assertEquals( 10, has_filter( 'comment_text', 'wp_staticize_emoji' ) );
		$this->assertEquals( 10, has_filter( 'widget_text', 'wp_staticize_emoji' ) );

		$this->assertEquals( 20, has_action( 'wp_head', 'amp_add_generator_metadata' ) );
		$this->assertEquals( 0, has_action( 'wp_enqueue_scripts', [ self::TESTED_CLASS, 'enqueue_assets' ] ) );

		$this->assertEquals( 1000, has_action( 'wp_enqueue_scripts', [ self::TESTED_CLASS, 'dequeue_customize_preview_scripts' ] ) );
		$this->assertEquals( 10, has_filter( 'customize_partial_render', [ self::TESTED_CLASS, 'filter_customize_partial_render' ] ) );
		$this->assertEquals( 10, has_action( 'wp_footer', 'amp_print_analytics' ) );
		$this->assertEquals( 10, has_action( 'admin_bar_init', [ self::TESTED_CLASS, 'init_admin_bar' ] ) );
		$priority = defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~PHP_INT_MAX; // phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
		$this->assertEquals( $priority, has_action( 'template_redirect', [ self::TESTED_CLASS, 'start_output_buffering' ] ) );

		$this->assertEquals( 10, has_filter( 'comment_form_defaults', [ self::TESTED_CLASS, 'filter_comment_form_defaults' ] ) );
		$this->assertEquals( 10, has_filter( 'comment_reply_link', [ self::TESTED_CLASS, 'filter_comment_reply_link' ] ) );
		$this->assertEquals( 10, has_filter( 'cancel_comment_reply_link', [ self::TESTED_CLASS, 'filter_cancel_comment_reply_link' ] ) );
		$this->assertEquals( 100, has_action( 'comment_form', [ self::TESTED_CLASS, 'amend_comment_form' ] ) );
		$this->assertFalse( has_action( 'comment_form', 'wp_comment_form_unfiltered_html_nonce' ) );
		$this->assertEquals( PHP_INT_MAX, has_filter( 'get_header_image_tag', [ self::TESTED_CLASS, 'amend_header_image_with_video_header' ] ) );
	}

	/**
	 * Test register_widgets().
	 *
	 * @covers AMP_Theme_Support::register_widgets()
	 * @global WP_Widget_Factory $wp_widget_factory
	 */
	public function test_register_widgets() {
		global $wp_widget_factory;
		remove_all_actions( 'widgets_init' );
		$wp_widget_factory->widgets = [];
		wp_widgets_init();
		AMP_Theme_Support::register_widgets();

		$this->assertArrayNotHasKey( 'WP_Widget_Categories', $wp_widget_factory->widgets );
		$this->assertArrayHasKey( 'AMP_Widget_Categories', $wp_widget_factory->widgets );
	}

	/**
	 * Test register_content_embed_handlers.
	 *
	 * @covers AMP_Theme_Support::register_content_embed_handlers()
	 * @global int $content_width
	 */
	public function test_register_content_embed_handlers() {
		global $content_width;
		$content_width  = 1234;
		$embed_handlers = AMP_Theme_Support::register_content_embed_handlers();
		foreach ( $embed_handlers as $embed_handler ) {
			$this->assertTrue( is_subclass_of( $embed_handler, 'AMP_Base_Embed_Handler' ) );
			$property = $this->get_private_property( $embed_handler, 'args' );
			$this->assertEquals( $content_width, $property['content_max_width'] );
		}
	}

	/**
	 * Test amend_comment_form().
	 *
	 * @covers AMP_Theme_Support::amend_comment_form()
	 */
	public function test_amend_comment_form() {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( is_singular() );

		// Test AMP-first.
		add_theme_support( AMP_Theme_Support::SLUG );
		$this->assertTrue( amp_is_canonical() );
		$output = get_echo( [ 'AMP_Theme_Support', 'amend_comment_form' ] );
		$this->assertNotContains( '<input type="hidden" name="redirect_to"', $output );

		// Test transitional AMP.
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'template_dir' => 'amp-templates',
			]
		);
		$this->assertFalse( amp_is_canonical() );
		$output = get_echo( [ 'AMP_Theme_Support', 'amend_comment_form' ] );
		$this->assertContains( '<input type="hidden" name="redirect_to"', $output );
	}

	/**
	 * Test filter_amp_template_hierarchy.
	 *
	 * @covers AMP_Theme_Support::filter_amp_template_hierarchy()
	 */
	public function test_filter_amp_template_hierarchy() {
		$template_dir = 'amp-templates';
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'template_dir' => $template_dir,
			]
		);
		$templates          = [
			'single-post-example.php',
			'single-post.php',
			'single.php',
		];
		$filtered_templates = AMP_Theme_Support::filter_amp_template_hierarchy( $templates );

		$expected_templates = [];
		foreach ( $templates as $template ) {
			$expected_templates[] = $template_dir . '/' . $template;
			$expected_templates[] = $template;
		}

		$this->assertEquals( $expected_templates, $filtered_templates );
	}

	/**
	 * Test get_current_canonical_url.
	 *
	 * @covers AMP_Theme_Support::get_current_canonical_url()
	 */
	public function test_get_current_canonical_url() {
		global $post, $wp;
		$home_url = home_url( '/' );
		$this->assertEquals( $home_url, AMP_Theme_Support::get_current_canonical_url() );

		$added_query_vars = [
			'foo' => 'bar',
		];
		$wp->query_vars   = $added_query_vars;
		$this->assertEquals( add_query_arg( $added_query_vars, $home_url ), AMP_Theme_Support::get_current_canonical_url() );

		$post = self::factory()->post->create_and_get();
		$this->go_to( get_permalink( $post ) );
		$this->assertEquals( wp_get_canonical_url(), AMP_Theme_Support::get_current_canonical_url() );

	}

	/**
	 * Test get_comment_form_state_id.
	 *
	 * @covers AMP_Theme_Support::get_comment_form_state_id()
	 */
	public function test_get_comment_form_state_id() {
		$post_id = 54;
		$this->assertEquals( 'commentform_post_' . $post_id, AMP_Theme_Support::get_comment_form_state_id( $post_id ) );
		$post_id = 91542;
		$this->assertEquals( 'commentform_post_' . $post_id, AMP_Theme_Support::get_comment_form_state_id( $post_id ) );
	}

	/**
	 * Test filter_comment_form_defaults.
	 *
	 * @covers AMP_Theme_Support::filter_comment_form_defaults()
	 */
	public function test_filter_comment_form_defaults() {
		global $post;
		$post     = self::factory()->post->create_and_get();
		$defaults = AMP_Theme_Support::filter_comment_form_defaults(
			[
				'title_reply_to'      => 'Reply To',
				'title_reply'         => 'Reply',
				'cancel_reply_before' => '',
				'title_reply_before'  => '',
			]
		);
		$this->assertContains( AMP_Theme_Support::get_comment_form_state_id( get_the_ID() ), $defaults['title_reply_before'] );
		$this->assertContains( 'replyToName ?', $defaults['title_reply_before'] );
		$this->assertContains( '</span>', $defaults['cancel_reply_before'] );
	}

	/**
	 * Test filter_comment_reply_link.
	 *
	 * @covers AMP_Theme_Support::filter_comment_reply_link()
	 */
	public function test_filter_comment_reply_link() {
		global $post;
		$post          = self::factory()->post->create_and_get();
		$comment       = self::factory()->comment->create_and_get();
		$link          = sprintf( '<a href="%s">', get_comment_link( $comment ) );
		$respond_id    = '5234';
		$reply_text    = 'Reply';
		$reply_to_text = 'Reply to';
		$before        = '<div class="reply">';
		$after         = '</div>';
		$args          = compact( 'respond_id', 'reply_text', 'reply_to_text', 'before', 'after' );
		$comment       = self::factory()->comment->create_and_get();

		update_option( 'comment_registration', true );
		$filtered_link = AMP_Theme_Support::filter_comment_reply_link( $link, $args, $comment );
		$this->assertEquals( $before . $link . $after, $filtered_link );
		update_option( 'comment_registration', false );

		$filtered_link = AMP_Theme_Support::filter_comment_reply_link( $link, $args, $comment );
		$this->assertStringStartsWith( $before, $filtered_link );
		$this->assertStringEndsWith( $after, $filtered_link );
		$this->assertContains( AMP_Theme_Support::get_comment_form_state_id( get_the_ID() ), $filtered_link );
		$this->assertContains( $comment->comment_author, $filtered_link );
		$this->assertContains( $comment->comment_ID, $filtered_link );
		$this->assertContains( 'tap:AMP.setState', $filtered_link );
		$this->assertContains( $reply_text, $filtered_link );
		$this->assertContains( $reply_to_text, $filtered_link );
		$this->assertContains( $respond_id, $filtered_link );
	}

	/**
	 * Test filter_cancel_comment_reply_link.
	 *
	 * @covers AMP_Theme_Support::filter_cancel_comment_reply_link()
	 */
	public function test_filter_cancel_comment_reply_link() {
		global $post;
		$post                   = self::factory()->post->create_and_get();
		$url                    = get_permalink( $post );
		$_SERVER['REQUEST_URI'] = $url;
		self::factory()->comment->create_and_get();
		$formatted_link = get_cancel_comment_reply_link();
		$link           = remove_query_arg( 'replytocom' );
		$text           = 'Cancel your reply';
		$filtered_link  = AMP_Theme_Support::filter_cancel_comment_reply_link( $formatted_link, $link, $text );
		$this->assertContains( $url, $filtered_link );
		$this->assertContains( $text, $filtered_link );
		$this->assertContains( '<a id="cancel-comment-reply-link"', $filtered_link );
		$this->assertContains( '.values.comment_parent ==', $filtered_link );
		$this->assertContains( 'tap:AMP.setState(', $filtered_link );

		$filtered_link_no_text_passed = AMP_Theme_Support::filter_cancel_comment_reply_link( $formatted_link, $link, '' );
		$this->assertContains( 'Click here to cancel reply.', $filtered_link_no_text_passed );
	}

	/**
	 * Test init_admin_bar.
	 *
	 * @covers \AMP_Theme_Support::init_admin_bar()
	 * @covers \AMP_Theme_Support::filter_admin_bar_style_loader_tag()
	 * @covers \AMP_Theme_Support::filter_admin_bar_script_loader_tag()
	 */
	public function test_init_admin_bar() {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		add_action(
			'admin_bar_init',
			function() {
				wp_enqueue_style( 'example-admin-bar', 'https://example.com/example-admin-bar.css', [ 'admin-bar' ], '0.1' );
				wp_enqueue_script( 'example-admin-bar', 'https://example.com/example-admin-bar.js', [ 'admin-bar' ], '0.1', false );
			}
		);

		$callback = function() {
			?>
			<style type="text/css" media="screen">
				html { margin-top: 64px !important; }
				@media screen and ( max-width: 782px ) {
					html { margin-top: 92px !important; }
				}
			</style>
			<?php
		};
		add_theme_support( 'admin-bar', compact( 'callback' ) );

		global $wp_admin_bar;
		$wp_admin_bar = new WP_Admin_Bar();
		$wp_admin_bar->initialize();
		$this->assertEquals( 10, has_action( 'wp_head', $callback ) );

		AMP_Theme_Support::init_admin_bar();
		$this->assertEquals( 10, has_filter( 'style_loader_tag', [ 'AMP_Theme_Support', 'filter_admin_bar_style_loader_tag' ] ) );
		$this->assertEquals( 10, has_filter( 'script_loader_tag', [ 'AMP_Theme_Support', 'filter_admin_bar_script_loader_tag' ] ) );
		$this->assertFalse( has_action( 'wp_head', $callback ) );
		ob_start();
		wp_print_styles();
		wp_print_scripts();
		$output = ob_get_clean();
		$this->assertContains( '<style id=\'admin-bar-inline-css\' type=\'text/css\'>', $output ); // Note: data-ampdevmode attribute will be added by AMP_Dev_Mode_Sanitizer.
		$this->assertNotContains( '<link rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'admin-bar-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'example-admin-bar-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertContains( 'html { margin-top: 64px !important; }', $output );
		$this->assertRegExp( '/' . implode( '', [ '<script ', 'data-ampdevmode [^>]+admin-bar\.js' ] ) . '/', $output );
		$this->assertRegExp( '/' . implode( '', [ '<script ', 'data-ampdevmode [^>]+example-admin-bar\.js' ] ) . '/', $output );

		$body_classes = get_body_class();
		$this->assertContains( 'customize-support', $body_classes );
		$this->assertNotContains( 'no-customize-support', $body_classes );
	}

	/**
	 * Test init_admin_bar to ensure dashicons are not added to dev mode when directly enqueued.
	 *
	 * @covers \AMP_Theme_Support::init_admin_bar()
	 * @covers \AMP_Theme_Support::filter_admin_bar_style_loader_tag()
	 */
	public function test_init_admin_bar_for_directly_enqueued_dashicons() {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		global $wp_admin_bar;
		$wp_admin_bar = new WP_Admin_Bar();
		$wp_admin_bar->initialize();
		AMP_Theme_Support::init_admin_bar();

		// Enqueued directly.
		wp_enqueue_style( 'dashicons' );

		ob_start();
		wp_print_styles();
		$output = ob_get_clean();

		$this->assertContains( '<link rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertNotContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'admin-bar-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	}

	/**
	 * Test init_admin_bar to ensure dashicons are not added to dev mode when indirectly enqueued.
	 *
	 * @covers \AMP_Theme_Support::init_admin_bar()
	 * @covers \AMP_Theme_Support::filter_admin_bar_style_loader_tag()
	 */
	public function test_init_admin_bar_for_indirectly_enqueued_dashicons() {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		global $wp_admin_bar;
		$wp_admin_bar = new WP_Admin_Bar();
		$wp_admin_bar->initialize();
		AMP_Theme_Support::init_admin_bar();

		// Enqueued indirectly.
		wp_enqueue_style( 'my-font-pack', 'https://example.com/fonts', [ 'dashicons' ], '0.1' );

		ob_start();
		wp_print_styles();
		$output = ob_get_clean();

		$this->assertContains( '<link rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertNotContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'admin-bar-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	}

	/**
	 * Data provider for test_ensure_required_markup.
	 *
	 * @return array
	 */
	public function get_schema_script_data() {
		return [
			'schema_org_not_present'        => [
				'',
				1,
			],
			'schema_org_present'            => [
				wp_json_encode( [ '@context' => 'http://schema.org' ] ),
				1,
			],
			'schema_org_output_not_escaped' => [
				'{"@context":"http://schema.org"',
				1,
			],
			'schema_org_another_key'        => [
				wp_json_encode( [ '@anothercontext' => 'https://schema.org' ] ),
				1,
			],
		];
	}

	/**
	 * Test ensure_required_markup().
	 *
	 * @dataProvider get_schema_script_data
	 * @covers AMP_Theme_Support::ensure_required_markup()
	 * @param string  $script The value of the script.
	 * @param boolean $expected The expected result.
	 */
	public function test_ensure_required_markup_schemaorg( $script, $expected ) {
		$page = '<html><head><script type="application/ld+json">%s</script></head><body>Test</body></html>';
		$dom  = new DOMDocument();
		$dom->loadHTML( sprintf( $page, $script ) );
		AMP_Theme_Support::ensure_required_markup( $dom );
		$this->assertEquals( $expected, substr_count( $dom->saveHTML(), 'schema.org' ) );
	}

	/**
	 * Test moving AMP scripts from body to head.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 * @covers AMP_Theme_Support::ensure_required_markup()
	 */
	public function test_scripts_get_moved_to_head() {
		ob_start();
		?>
		<html>
			<head></head>
			<body>
				<amp-list width="auto" height="100" layout="fixed-height" src="/static/inline-examples/data/amp-list-urls.json">
					<template type="amp-mustache">
						<div class="url-entry">
							<a href="{{url}}">{{title}}</a>
						</div>
					</template>
				</amp-list>
				<?php wp_print_scripts( [ 'amp-runtime', 'amp-mustache', 'amp-list' ] ); ?>
			</body>
		</html>
		<?php
		$html = ob_get_clean();
		$html = AMP_Theme_Support::prepare_response( $html );

		$dom   = AMP_DOM_Utils::get_dom( $html );
		$xpath = new DOMXPath( $dom );

		$scripts = $xpath->query( '//script[ not( @type ) or @type = "text/javascript" ]' );
		$this->assertSame( 3, $scripts->length );
		foreach ( $scripts as $script ) {
			$this->assertSame( 'head', $script->parentNode->nodeName );
		}
	}

	/**
	 * Test removing AMP scripts that are not needed.
	 *
	 * @covers AMP_Theme_Support::ensure_required_markup()
	 */
	public function test_unneeded_scripts_get_removed() {
		wp();
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		// These should all get removed, unless used.
		$required_usage_grandfathered = [
			'amp-anim',
			'amp-ad',
			'amp-mustache',
			'amp-list',
			'amp-youtube',
			'amp-form',
			'amp-live-list',
		];

		// These also should get removed, unless used.
		$required_usage_error = [
			'amp-facebook-like',
			'amp-date-picker',
			'amp-call-tracking',
		];

		// These should not get removed, ever.
		$required_usage_none = [
			'amp-bind', // And yet, see https://github.com/ampproject/amphtml/blob/eb05855/extensions/amp-bind/validator-amp-bind.protoascii#L25-L28
			'amp-dynamic-css-classes',
			'amp-subscriptions',
			'amp-lightbox-gallery',
			'amp-video',
		];

		ob_start();
		?>
		<html>
			<head></head>
			<body>
				<?php wp_print_scripts( array_merge( $required_usage_grandfathered, $required_usage_error, $required_usage_none ) ); ?>
			</body>
		</html>
		<?php
		$html = ob_get_clean();
		$html = AMP_Theme_Support::prepare_response( $html );

		$dom   = AMP_DOM_Utils::get_dom( $html );
		$xpath = new DOMXPath( $dom );

		/** @var DOMElement $script Script. */
		$actual_script_srcs = [];
		foreach ( $xpath->query( '//script[ not( @type ) or @type = "text/javascript" ]' ) as $script ) {
			$actual_script_srcs[] = $script->getAttribute( 'src' );
		}

		$expected_script_srcs = [
			wp_scripts()->registered['amp-runtime']->src,
		];
		foreach ( $required_usage_none as $handle ) {
			$expected_script_srcs[] = wp_scripts()->registered[ $handle ]->src;
		}

		$this->assertEqualSets(
			$expected_script_srcs,
			$actual_script_srcs
		);
	}

	/**
	 * Test removing duplicate scripts.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 * @covers AMP_Theme_Support::ensure_required_markup()
	 */
	public function test_duplicate_scripts_are_removed() {
		wp();
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		ob_start();
		?>
		<html>
			<head>
				<script async src="https://cdn.ampproject.org/v0.js"></script>
				<script async custom-element="amp-video" src="https://cdn.ampproject.org/v0/amp-video-0.1.js"></script>
				<script async custom-element="amp-video" src="https://cdn.ampproject.org/v0/amp-video-0.1.js"></script>
				<script async custom-element="amp-video" src="https://cdn.ampproject.org/v0/amp-video-0.1.js"></script>
			</head>
			<body>
				<?php wp_print_scripts( [ 'amp-video', 'amp-runtime' ] ); ?>
			</body>
		</html>
		<?php
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$html = ob_get_clean();
		$html = AMP_Theme_Support::prepare_response( $html );

		$dom   = AMP_DOM_Utils::get_dom( $html );
		$xpath = new DOMXPath( $dom );

		$script_srcs = [];
		/**
		 * Script.
		 *
		 * @var DOMElement $script
		 */
		$scripts = $xpath->query( '//script[ @src ]' );
		foreach ( $scripts as $script ) {
			$script_srcs[] = $script->getAttribute( 'src' );
		}

		$this->assertCount( 2, $script_srcs );
		$this->assertEquals(
			$script_srcs,
			[
				'https://cdn.ampproject.org/v0.js',
				'https://cdn.ampproject.org/v0/amp-video-0.1.js',
			]
		);
	}

	/**
	 * Test dequeue_customize_preview_scripts.
	 *
	 * @covers AMP_Theme_Support::dequeue_customize_preview_scripts()
	 */
	public function test_dequeue_customize_preview_scripts() {
		// Ensure AMP_Theme_Support::is_customize_preview_iframe() is true.
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		$GLOBALS['wp_customize'] = new WP_Customize_Manager(
			[
				'messenger_channel' => 'baz',
			]
		);
		$GLOBALS['wp_customize']->start_previewing_theme();
		$customize_preview = 'customize-preview';
		$preview_style     = 'example-preview-style';
		wp_enqueue_style( $preview_style, home_url( '/' ), [ $customize_preview ] ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		AMP_Theme_Support::dequeue_customize_preview_scripts();
		$this->assertTrue( wp_style_is( $preview_style ) );
		$this->assertTrue( wp_style_is( $customize_preview ) );

		wp_enqueue_style( $preview_style, home_url( '/' ), [ $customize_preview ] ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( $customize_preview );
		// Ensure AMP_Theme_Support::is_customize_preview_iframe() is false.
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		AMP_Theme_Support::dequeue_customize_preview_scripts();
		$this->assertFalse( wp_style_is( $preview_style ) );
		$this->assertFalse( wp_style_is( $customize_preview ) );
	}

	/**
	 * Test start_output_buffering.
	 *
	 * @covers AMP_Theme_Support::start_output_buffering()
	 * @covers AMP_Theme_Support::is_output_buffering()
	 * @covers AMP_Theme_Support::finish_output_buffering()
	 */
	public function test_start_output_buffering() {
		wp();
		if ( ! function_exists( 'newrelic_disable_autorum ' ) ) {
			/**
			 * Define newrelic_disable_autorum to allow passing line.
			 */
			function newrelic_disable_autorum() {
				return true;
			}
		}

		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		$initial_ob_level = ob_get_level();
		AMP_Theme_Support::start_output_buffering();
		$this->assertEquals( $initial_ob_level + 1, ob_get_level() );
		ob_end_flush();
		$this->assertEquals( $initial_ob_level, ob_get_level() );

		// When this query var is present, this method should exit early, and shouldn't buffer the output.
		$_GET[ AMP_Debug::AMP_FLAGS_QUERY_VAR ][ AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR ] = '1';
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$initial_ob_level = ob_get_level();
		AMP_Theme_Support::start_output_buffering();
		$this->assertEquals( $initial_ob_level, ob_get_level() );
	}

	/**
	 * Test finish_output_buffering.
	 *
	 * @covers AMP_Theme_Support::finish_output_buffering()
	 * @covers AMP_Theme_Support::is_output_buffering()
	 */
	public function test_finish_output_buffering() {
		wp();
		add_filter( 'amp_validation_error_sanitized', '__return_true' );
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		$status = ob_get_status();
		$this->assertSame( 1, ob_get_level() );
		$this->assertEquals( 'default output handler', $status['name'] );
		$this->assertFalse( AMP_Theme_Support::is_output_buffering() );

		// start first layer buffer.
		ob_start();
		AMP_Theme_Support::start_output_buffering();
		$this->assertTrue( AMP_Theme_Support::is_output_buffering() );
		$this->assertSame( 3, ob_get_level() );

		echo '<html><head></head><body><img src="test.png"><script data-test>document.write(\'Illegal\');</script>';

		// Additional nested output bufferings which aren't getting closed.
		ob_start();
		echo 'foo';
		ob_start(
			static function( $response ) {
				return strtoupper( $response );
			}
		);
		echo 'bar';

		$this->assertTrue( AMP_Theme_Support::is_output_buffering() );
		while ( ob_get_level() > 2 ) {
			ob_end_flush();
		}
		$this->assertFalse( AMP_Theme_Support::is_output_buffering() );
		$output = ob_get_clean();
		$this->assertEquals( 1, ob_get_level() );

		$this->assertContains( '<html amp', $output );
		$this->assertContains( 'foo', $output );
		$this->assertContains( 'BAR', $output );
		$this->assertContains( '<amp-img src="test.png"', $output );
		$this->assertNotContains( '<script data-test', $output );

	}

	/**
	 * Test filter_customize_partial_render.
	 *
	 * @covers AMP_Theme_Support::filter_customize_partial_render()
	 */
	public function test_filter_customize_partial_render() {
		wp();
		add_filter( 'amp_validation_error_sanitized', '__return_true' );
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		$partial = '<img src="test.png"><script data-head>document.write(\'Illegal\');</script>';
		$output  = AMP_Theme_Support::filter_customize_partial_render( $partial );
		$this->assertContains( '<amp-img src="test.png"', $output );
		$this->assertNotContains( '<script', $output );
		$this->assertNotContains( '<html', $output );
	}

	/**
	 * Test exceeded_cache_miss_threshold
	 *
	 * @covers AMP_Theme_Support::exceeded_cache_miss_threshold()
	 */
	public function test_exceeded_cache_miss_threshold() {
		$this->assertFalse( AMP_Theme_Support::exceeded_cache_miss_threshold() );
		add_option( AMP_Theme_Support::CACHE_MISS_URL_OPTION, site_url() );
		$this->assertTrue( AMP_Theme_Support::exceeded_cache_miss_threshold() );
	}

	/**
	 * Get the ETag header value from the list of sent headers.
	 *
	 * @param array $headers Headers sent.
	 * @return string|null The ETag if found.
	 */
	protected function get_etag_header_value( $headers ) {
		foreach ( $headers as $header_sent ) {
			if ( 'ETag' === $header_sent['name'] ) {
				return $header_sent['value'];
			}
		}
		return null;
	}

	/**
	 * Test prepare_response.
	 *
	 * @global WP_Widget_Factory $wp_widget_factory
	 * @global WP_Scripts $wp_scripts
	 * @covers AMP_Theme_Support::prepare_response()
	 * @covers AMP_Theme_Support::ensure_required_markup()
	 * @covers ::amp_render_scripts()
	 */
	public function test_prepare_response() {
		add_theme_support( 'amp' );

		add_filter(
			'home_url',
			static function ( $url ) {
				return set_url_scheme( $url, 'https' );
			}
		);

		wp();
		$prepare_response_args = [
			'enable_response_caching' => false,
		];

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$original_html = $this->get_original_html();

		$call_prepare_response = static function() use ( $original_html, &$prepare_response_args ) {
			AMP_HTTP::$headers_sent                     = [];
			AMP_Validation_Manager::$validation_results = [];
			return AMP_Theme_Support::prepare_response( $original_html, $prepare_response_args );
		};

		$sanitized_html = $call_prepare_response();

		$etag = $this->get_etag_header_value( AMP_HTTP::$headers_sent );
		$this->assertNotEmpty( $etag );

		$this->assertNotContains( 'handle=', $sanitized_html );
		$this->assertEquals( 2, substr_count( $sanitized_html, '<!-- wp_print_scripts -->' ) );

		$ordered_contains = [
			'<html amp="">',
			'<meta charset="' . get_bloginfo( 'charset' ) . '">',
			'<meta name="viewport" content="width=device-width">',
			'<meta name="generator" content="AMP Plugin',
			'<title>',
			'<link rel="preconnect" href="https://cdn.ampproject.org">',
			'<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">',
			'<link rel="dns-prefetch" href="//cdn.ampproject.org">',
			'<link rel="preload" as="script" href="https://cdn.ampproject.org/v0.js">',
			'<link rel="preload" as="script" href="https://cdn.ampproject.org/v0/amp-experiment-1.0.js">',
			'<link rel="preload" as="script" href="https://cdn.ampproject.org/v0/amp-dynamic-css-classes-0.1.js">',
			'<script type="text/javascript" src="https://cdn.ampproject.org/v0.js" async></script>',
			'<script src="https://cdn.ampproject.org/v0/amp-experiment-1.0.js" async="" custom-element="amp-experiment"></script>',
			'<script async custom-element="amp-dynamic-css-classes" src="https://cdn.ampproject.org/v0/amp-dynamic-css-classes-0.1.js"></script>',
			'<script type="text/javascript" src="https://cdn.ampproject.org/v0/amp-list-0.1.js" async custom-element="amp-list"></script>',
			'<script type="text/javascript" src="https://cdn.ampproject.org/v0/amp-mathml-0.1.js" async custom-element="amp-mathml"></script>',

			// Note these are single-quoted because they are injected after the DOM has been re-serialized, so the type and src attributes come from WP_Scripts::do_item().
			'<script src="https://cdn.ampproject.org/v0/amp-audio-0.1.js" async="" custom-element="amp-audio"></script>',
			'<script src="https://cdn.ampproject.org/v0/amp-ad-0.1.js" async="" custom-element="amp-ad"></script>',

			'#<style amp-custom(="")?>.*?body\s*{\s*background:\s*black;?\s*}.*?</style>#s',

			'<link crossorigin="anonymous" rel="stylesheet" id="my-font-css" href="https://fonts.googleapis.com/css?family=Tangerine" type="text/css" media="all">',
			'<link rel="icon" href="https://example.org/favicon.png" sizes="32x32">',
			'<link rel="icon" href="https://example.org/favicon.png" sizes="192x192">',
			'<script type="application/ld+json">{"@context"',
			'<link rel="canonical" href="',

			'#<style amp-boilerplate(="")?>#',
			'#<noscript><style amp-boilerplate(="")?>#',
			'</head>',
		];

		$last_position        = -1;
		$prev_ordered_contain = '';
		foreach ( $ordered_contains as $ordered_contain ) {
			if ( '#' === substr( $ordered_contain, 0, 1 ) ) {
				$this->assertEquals( 1, preg_match( $ordered_contain, $sanitized_html, $matches, PREG_OFFSET_CAPTURE ), "Failed to find: $ordered_contain" );
				$this->assertGreaterThan( $last_position, $matches[0][1], "'$ordered_contain' is not after '$prev_ordered_contain'" );
				$last_position = $matches[0][1];
			} else {
				$this_position = strpos( $sanitized_html, $ordered_contain );
				$this->assertNotFalse( $this_position, "Failed to find: $ordered_contain" );
				$this->assertGreaterThan( $last_position, (int) $this_position, "'$ordered_contain' is not after '$prev_ordered_contain'" );
				$last_position = $this_position;
			}
			$prev_ordered_contain = $ordered_contain;
		}

		$this->assertContains( '<noscript><img', $sanitized_html );
		$this->assertContains( '<amp-img', $sanitized_html );

		$this->assertContains( '<noscript><audio', $sanitized_html );
		$this->assertContains( '<amp-audio', $sanitized_html );

		$removed_nodes = [];
		foreach ( AMP_Validation_Manager::$validation_results as $result ) {
			if ( $result['sanitized'] && isset( $result['error']['node_name'] ) ) {
				$node_name = $result['error']['node_name'];
				if ( ! isset( $removed_nodes[ $node_name ] ) ) {
					$removed_nodes[ $node_name ] = 0;
				}
				$removed_nodes[ $node_name ]++;
			}
		}

		$this->assertContains( '<button>no-onclick</button>', $sanitized_html );
		$this->assertCount( 5, AMP_Validation_Manager::$validation_results );
		$this->assertEquals(
			[
				'onclick' => 1,
				'handle'  => 3,
				'script'  => 1,
			],
			$removed_nodes
		);

		// Make sure trailing content after </html> gets moved.
		$this->assertRegExp( '#<!--comment-after-html-->\s*<div id="after-html"></div>\s*<!--comment-end-html-->\s*</body>\s*</html>\s*$#s', $sanitized_html );

		// Test that ETag allows response to short-circuit via If-None-Match request header.
		$_SERVER['HTTP_IF_NONE_MATCH'] = $etag;
		$this->assertEmpty( '', $call_prepare_response() );

		$_SERVER['HTTP_IF_NONE_MATCH'] = sprintf( '"%s"', $etag );
		$this->assertEmpty( '', $call_prepare_response() );

		$_SERVER['HTTP_IF_NONE_MATCH'] = sprintf( 'W/"%s"', $etag );
		$this->assertEmpty( '', $call_prepare_response() );

		$_SERVER['HTTP_IF_NONE_MATCH'] = sprintf( '"%s", W/"%s"', md5( 'foo' ), $etag );
		$this->assertEmpty( '', $call_prepare_response() );

		$_SERVER['HTTP_IF_NONE_MATCH'] = sprintf( '"%s", "%s"', $etag, md5( 'bar' ) );
		$this->assertEmpty( '', $call_prepare_response() );

		// Test not match.
		$_SERVER['HTTP_IF_NONE_MATCH'] = strrev( $etag );
		$this->assertNotEmpty( $call_prepare_response() );
		$this->assertNotEmpty( $this->get_etag_header_value( AMP_HTTP::$headers_sent ) );
		unset( $_SERVER['HTTP_IF_NONE_MATCH'] );

		$prepare_response_args['enable_response_caching'] = true;

		// Test that first response isn't cached.
		$first_response                = $call_prepare_response();
		$initial_server_timing_headers = $this->get_server_timing_headers();
		$this->assertGreaterThan( 0, count( $initial_server_timing_headers ) );
		$this->assertContains( '<html amp="">', $first_response ); // Note: AMP because sanitized validation errors.
		$this->reset_post_processor_cache_effectiveness();

		// Test that response cache is return upon second call.
		$this->assertEquals( $first_response, $call_prepare_response() );
		$server_timing_headers = $this->get_server_timing_headers();
		$this->assertCount( count( $server_timing_headers ), $this->get_server_timing_headers() );
		$this->reset_post_processor_cache_effectiveness();

		// Test new cache upon argument change.
		$prepare_response_args['test_reset_by_arg'] = true;
		$call_prepare_response();
		$server_timing_headers = $this->get_server_timing_headers();
		$this->assertCount( count( $server_timing_headers ), $this->get_server_timing_headers() );
		$this->reset_post_processor_cache_effectiveness();

		// Test response is cached.
		$call_prepare_response();
		$server_timing_headers = $this->get_server_timing_headers();
		$this->assertCount( count( $server_timing_headers ), $this->get_server_timing_headers() );
		$this->reset_post_processor_cache_effectiveness();

		// Test that response is no longer cached due to a change whether validation errors are sanitized.
		remove_filter( 'amp_validation_error_sanitized', '__return_true' );
		add_filter( 'amp_validation_error_sanitized', '__return_false' );
		$prepared_html         = $call_prepare_response();
		$server_timing_headers = $this->get_server_timing_headers();
		$this->assertCount( count( $server_timing_headers ), $this->get_server_timing_headers() );
		$this->assertContains( '<html>', $prepared_html ); // Note: no AMP because unsanitized validation error.
		$this->reset_post_processor_cache_effectiveness();

		// And test response is cached.
		$call_prepare_response();
		$server_timing_headers     = $this->get_server_timing_headers();
		$last_server_timing_header = array_pop( $server_timing_headers );
		$this->assertStringStartsWith( 'amp_processor_cache_hit;', $last_server_timing_header['value'] );
		$this->assertCount( count( $server_timing_headers ), $initial_server_timing_headers );
		$this->reset_post_processor_cache_effectiveness();

		// Test that the response is not cached if a certain query var is present.
		$_GET[ AMP_Debug::AMP_FLAGS_QUERY_VAR ][ AMP_Debug::DISABLE_RESPONSE_CACHE_QUERY_VAR ] = '1';
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$call_prepare_response();
		$server_timing_headers = $this->get_server_timing_headers();
		$this->assertCount( count( $server_timing_headers ), $this->get_server_timing_headers() );
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	}

	/**
	 * Test prepare_response for standard mode when some validation errors aren't auto-sanitized.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_prepare_response_standard_mode_non_amp() {
		wp();
		$original_html = $this->get_original_html();
		add_filter( 'amp_validation_error_sanitized', '__return_false' ); // For testing purpose only. This should not normally be done.

		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html );

		$this->assertContains( '<html>', $sanitized_html, 'The AMP attribute is removed from the HTML element' );
		$this->assertContains( '<button onclick="alert', $sanitized_html, 'Invalid AMP is present in the response.' );
		$this->assertContains( 'document.write = function', $sanitized_html, 'Override of document.write() is present.' );
	}

	/**
	 * Test prepare_response when submitting form.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_prepare_response_for_submitted_form() {
		AMP_HTTP::$purged_amp_query_vars[ AMP_HTTP::ACTION_XHR_CONVERTED_QUERY_VAR ] = true;
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$response = AMP_Theme_Support::prepare_response( '<p>¡Tienes éxito!</p>' );
		$this->assertEquals( '{"status_code":200,"status_text":"OK"}', $response );

		unset( AMP_HTTP::$purged_amp_query_vars[ AMP_HTTP::ACTION_XHR_CONVERTED_QUERY_VAR ] );
		unset( $_SERVER['REQUEST_METHOD'] );
	}

	/**
	 * Test combining amp-script-src meta tags.
	 *
	 * @covers \amp_generate_script_hash()
	 */
	public function test_prepare_response_for_amp_script() {
		$script1 = 'document.body.textContent += "First!";';
		$script2 = 'document.body.textContent += "Second!";';
		$script3 = 'document.body.textContent += "Third!";';
		$script4 = 'document.body.textContent += "Fourth! (And forbidden because no amp-script-src meta in head.)";';

		$script1_hash = amp_generate_script_hash( $script1 );
		$script2_hash = amp_generate_script_hash( $script2 );
		$script3_hash = amp_generate_script_hash( $script3 );
		$script4_hash = amp_generate_script_hash( $script4 );

		ob_start();
		?>
		<html>
			<head>
				<meta name="amp-script-src" content="<?php echo esc_attr( $script1_hash ); ?>">
				<meta name="amp-script-src" content="<?php echo esc_attr( $script2_hash ); ?>">
				<style>
					body { background: black; color:white }
				</style>
				<meta name="amp-script-src" content="<?php echo esc_attr( $script3_hash ); ?>">
			</head>
			<body>
				<meta name="amp-script-src" content="<?php echo esc_attr( $script4_hash ); ?>">

				<amp-script script="s1" layout="fixed-height" height="30"></amp-script><script type="text/plain" target="amp-script" id="s1"><?php echo $script1; // phpcs:ignore ?></script>
				<amp-script script="s2" layout="fixed-height" height="30"></amp-script><script type="text/plain" target="amp-script" id="s2"><?php echo $script2; // phpcs:ignore ?></script>
				<amp-script script="s3" layout="fixed-height" height="30"></amp-script><script type="text/plain" target="amp-script" id="s3"><?php echo $script3; // phpcs:ignore ?></script>
				<amp-script script="s4" layout="fixed-height" height="30"></amp-script><script type="text/plain" target="amp-script" id="s4"><?php echo $script4; // phpcs:ignore ?></script>
			</body>
		</html>
		<?php
		$output = ob_get_clean();

		$processed = AMP_Theme_Support::prepare_response( $output );
		$dom       = AMP_DOM_Utils::get_dom( $processed );
		$xpath     = new DOMXPath( $dom );

		$meta_elements = $xpath->query( '/html/head/meta[ @name = "amp-script-src" ]' );
		$this->assertSame( 1, $meta_elements->length );

		$meta = $meta_elements->item( 0 );
		$this->assertSame( "$script1_hash $script2_hash $script3_hash", $meta->getAttribute( 'content' ) );
	}

	/**
	 * Test post-processor cache effectiveness in AMP_Theme_Support::prepare_response().
	 */
	public function test_post_processor_cache_effectiveness() {
		wp();
		$original_html = $this->get_original_html();
		$args          = [ 'enable_response_caching' => true ];
		wp_using_ext_object_cache( true ); // turn on external object cache flag.
		$this->reset_post_processor_cache_effectiveness();
		AMP_Options_Manager::update_option( 'enable_response_caching', true );

		// Test the response is not cached after exceeding the cache miss threshold.
		for ( $num_calls = 1, $max = AMP_Theme_Support::CACHE_MISS_THRESHOLD + 2; $num_calls <= $max; $num_calls++ ) {
			// Simulate dynamic changes in the content.
			$original_html = str_replace( 'dynamic-id-', "dynamic-id-{$num_calls}-", $original_html );

			AMP_HTTP::$headers_sent                     = [];
			AMP_Validation_Manager::$validation_results = [];
			AMP_Theme_Support::prepare_response( $original_html, $args );

			$caches_for_url = wp_cache_get( AMP_Theme_Support::POST_PROCESSOR_CACHE_EFFECTIVENESS_KEY, AMP_Theme_Support::POST_PROCESSOR_CACHE_EFFECTIVENESS_GROUP );
			$cache_miss_url = get_option( AMP_Theme_Support::CACHE_MISS_URL_OPTION, false );

			// When we've met the threshold, check that caching did not happen.
			if ( $num_calls > AMP_Theme_Support::CACHE_MISS_THRESHOLD ) {
				$this->assertCount( AMP_Theme_Support::CACHE_MISS_THRESHOLD, $caches_for_url );
				$this->assertEquals( amp_get_current_url(), $cache_miss_url );

				// Check that response caching was automatically disabled.
				$this->assertFalse( AMP_Options_Manager::get_option( 'enable_response_caching' ) );
			} else {
				$this->assertCount( $num_calls, $caches_for_url );
				$this->assertFalse( $cache_miss_url );
				$this->assertTrue( AMP_Options_Manager::get_option( 'enable_response_caching' ) );
			}

			$this->assertGreaterThan( 0, count( $this->get_server_timing_headers() ) );
		}

		// Reset.
		wp_using_ext_object_cache( false );
	}

	/**
	 * Initializes and returns the original HTML.
	 */
	private function get_original_html() {
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		global $wp_widget_factory, $wp_scripts, $wp_styles;
		$wp_scripts = null;
		$wp_styles  = null;
		wp_scripts();
		wp_styles();

		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();
		$wp_widget_factory = new WP_Widget_Factory();
		wp_widgets_init();

		$this->assertTrue( is_amp_endpoint() );

		add_action(
			'wp_enqueue_scripts',
			static function() {
				wp_enqueue_script( 'amp-list' );
				wp_enqueue_style( 'my-font', 'https://fonts.googleapis.com/css?family=Tangerine', [], null ); // phpcs:ignore
			}
		);
		add_action(
			'wp_print_scripts',
			static function() {
				echo '<!-- wp_print_scripts -->';
			}
		);

		add_filter(
			'script_loader_tag',
			static function( $tag, $handle ) {
				if ( ! wp_scripts()->get_data( $handle, 'conditional' ) ) {
					$tag = preg_replace( '/(?<=<script)/', " handle='$handle' ", $tag );
				}
				return $tag;
			},
			10,
			2
		);

		add_action(
			'wp_footer',
			static function() {
				wp_print_scripts( 'amp-mathml' );
				?>
				<amp-mathml layout="container" data-formula="\[x = {-b \pm \sqrt{b^2-4ac} \over 2a}.\]"></amp-mathml>
				<?php
			},
			1
		);

		add_filter(
			'get_site_icon_url',
			static function() {
				return home_url( '/favicon.png' );
			}
		);

		// Specify file paths for stylesheets not available in src.
		foreach ( [ 'wp-block-library', 'wp-block-library-theme' ] as $src_style_handle ) {
			if ( wp_style_is( $src_style_handle, 'registered' ) ) {
				wp_styles()->registered[ $src_style_handle ]->src = amp_get_asset_url( 'css/amp-default.css' ); // A dummy path.
			}
		}

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<?php wp_head(); ?>
			<script data-head>document.write('Illegal');</script>
			<script async custom-element="amp-dynamic-css-classes" src="https://cdn.ampproject.org/v0/amp-dynamic-css-classes-0.1.js"></script>
		</head>
		<body><!-- </body></html> -->
		<div id="dynamic-id-0"></div>
		<img width="100" height="100" src="https://example.com/test.png">
		<audio src="https://example.com/audios/myaudio.mp3"></audio>
		<amp-ad type="a9"
				width="300"
				height="250"
				data-aax_size="300x250"
				data-aax_pubname="test123"
				data-aax_src="302"></amp-ad>

		<?php wp_footer(); ?>

		<button onclick="alert('Illegal');">no-onclick</button>

		<style>body { background: black; }</style>

		<amp-experiment>
			<script type="application/json">
				{ "aExperiment": {} }
			</script>
		</amp-experiment>
		<amp-list src="https://example.com/list.json?RANDOM"></amp-list>
		</body>
		</html>
		<!--comment-after-html-->
		<div id="after-html"></div>
		<!--comment-end-html-->
		<?php
		return trim( ob_get_clean() );
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	}

	/**
	 * Returns the "Server-Timing" headers.
	 *
	 * @return array Server-Timing headers.
	 */
	private function get_server_timing_headers() {
		return array_filter(
			AMP_HTTP::$headers_sent,
			static function( $header ) {
				return 'Server-Timing' === $header['name'];
			}
		);
	}

	/**
	 * Reset cached URLs in post-processor cache effectiveness.
	 */
	private function reset_post_processor_cache_effectiveness() {
		wp_cache_delete( AMP_Theme_Support::POST_PROCESSOR_CACHE_EFFECTIVENESS_KEY, AMP_Theme_Support::POST_PROCESSOR_CACHE_EFFECTIVENESS_GROUP );
		delete_option( AMP_Theme_Support::CACHE_MISS_URL_OPTION );
	}

	/**
	 * Test prepare_response for responses that may or may not be valid HTML.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_prepare_response_varying_html() {
		wp();
		add_filter( 'amp_validation_error_sanitized', '__return_true' );
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		// JSON.
		$input = '{"success":true}';
		$this->assertEquals( $input, AMP_Theme_Support::prepare_response( $input ) );

		// Nothing, for redirect.
		$input = '';
		$this->assertEquals( $input, AMP_Theme_Support::prepare_response( $input ) );

		// HTML, but a fragment.
		$input = '<ul><li>one</li><li>two</li><li>three</li></ul>';
		$this->assertEquals( $input, AMP_Theme_Support::prepare_response( $input ) );

		// HTML, but still a fragment.
		$input = '<html><header><h1>HellO!</h1></header></html>';
		$this->assertEquals( $input, AMP_Theme_Support::prepare_response( $input ) );

		// HTML, but very stripped down.
		$input  = '<html><head></head>Hello</html>';
		$output = AMP_Theme_Support::prepare_response( $input );
		$this->assertContains( '<html amp', $output );
		$this->assertContains( '<meta charset="UTF-8">', $output );

		// HTML with doctype, comments, and whitespace before head.
		$input  = "   <!--\nHello world!\n-->\n\n<!DOCTYPE html>  <html\n\n>\n<head profile='http://www.acme.com/profiles/core'></head><body>Hello</body></html>";
		$output = AMP_Theme_Support::prepare_response( $input );
		$this->assertContains( '<html amp', $output );
		$this->assertContains( '<meta charset="UTF-8">', $output );
	}

	/**
	 * Test prepare_response to inject html[amp] attribute and ensure HTML5 doctype.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_prepare_response_to_add_html5_doctype_and_amp_attribute() {
		wp_scripts();
		wp();
		add_filter( 'amp_validation_error_sanitized', '__return_true' );
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();
		ob_start();
		?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
		<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><?php wp_head(); ?></head><body><?php wp_footer(); ?></body></html>
		<?php
		$original_html  = trim( ob_get_clean() );
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html );

		$this->assertStringStartsWith( '<!DOCTYPE html>', $sanitized_html );
		$this->assertContains( '<html amp', $sanitized_html );
		$this->assertContains( '<meta charset="utf-8">', $sanitized_html );
	}

	/**
	 * Test prepare_response will cache redirects when validation errors happen.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_prepare_response_redirect() {
		add_filter( 'amp_validation_error_sanitized', '__return_false', 100 );

		$this->go_to( home_url( '/?amp' ) );
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				AMP_Theme_Support::PAIRED_FLAG => true,
			]
		);
		add_filter(
			'amp_content_sanitizers',
			static function( $sanitizers ) {
				$sanitizers['AMP_Theme_Support_Sanitizer_Counter'] = [];
				return $sanitizers;
			}
		);
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();
		$this->assertTrue( is_amp_endpoint() );

		ob_start();
		?>
		<html>
			<head>
			</head>
			<body>
				<script>bad</script>
			</body>
		</html>
		<?php
		$original_html = trim( ob_get_clean() );

		$redirects = [];
		add_filter(
			'wp_redirect',
			static function( $url ) use ( &$redirects ) {
				array_unshift( $redirects, $url );
				return '';
			}
		);

		AMP_Theme_Support_Sanitizer_Counter::$count = 0;
		AMP_Validation_Manager::reset_validation_results();
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html, [ 'enable_response_caching' => true ] );
		$this->assertStringStartsWith( 'Redirecting to non-AMP version', $sanitized_html );
		$this->assertCount( 1, $redirects );
		$this->assertEquals( home_url( '/' ), $redirects[0] );
		$this->assertEquals( 1, AMP_Theme_Support_Sanitizer_Counter::$count );

		AMP_Validation_Manager::reset_validation_results();
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html, [ 'enable_response_caching' => true ] );
		$this->assertStringStartsWith( 'Redirecting to non-AMP version', $sanitized_html );
		$this->assertCount( 2, $redirects );
		$this->assertEquals( home_url( '/' ), $redirects[0] );
		$this->assertEquals( 1, AMP_Theme_Support_Sanitizer_Counter::$count, 'Expected sanitizer to not be invoked.' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Validation_Manager::add_validation_error_sourcing();

		AMP_Validation_Manager::reset_validation_results();
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html, [ 'enable_response_caching' => true ] );
		$this->assertStringStartsWith( 'Redirecting to non-AMP version', $sanitized_html );
		$this->assertCount( 3, $redirects );
		$this->assertEquals( home_url( '/?amp_validation_errors=1' ), $redirects[0] );
		$this->assertEquals( 2, AMP_Theme_Support_Sanitizer_Counter::$count, 'Expected sanitizer be invoked after validation changed.' );

		AMP_Validation_Manager::reset_validation_results();
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html, [ 'enable_response_caching' => true ] );
		$this->assertStringStartsWith( 'Redirecting to non-AMP version', $sanitized_html );
		$this->assertCount( 4, $redirects );
		$this->assertEquals( home_url( '/?amp_validation_errors=1' ), $redirects[0] );
		$this->assertEquals( 2, AMP_Theme_Support_Sanitizer_Counter::$count, 'Expected sanitizer to not now be invoked since previous validation results now cached.' );

	}

	/**
	 * Test enqueue_assets().
	 *
	 * @covers AMP_Theme_Support::enqueue_assets()
	 */
	public function test_enqueue_assets() {
		$style_slug = 'amp-default';
		wp_dequeue_style( $style_slug );
		AMP_Theme_Support::enqueue_assets();
		$this->assertContains( $style_slug, wp_styles()->queue );
	}

	/**
	 * Test AMP_Theme_Support::whitelist_layout_in_wp_kses_allowed_html().
	 *
	 * @see AMP_Theme_Support::whitelist_layout_in_wp_kses_allowed_html()
	 */
	public function test_whitelist_layout_in_wp_kses_allowed_html() {
		$attribute             = 'data-amp-layout';
		$image_no_dimensions   = [
			'img' => [
				$attribute => true,
			],
		];
		$image_with_dimensions = array_merge(
			$image_no_dimensions,
			[
				'height' => '100',
				'width'  => '100',
			]
		);

		$this->assertEquals( [], AMP_Theme_Support::whitelist_layout_in_wp_kses_allowed_html( [] ) );
		$this->assertEquals( $image_no_dimensions, AMP_Theme_Support::whitelist_layout_in_wp_kses_allowed_html( $image_no_dimensions ) );

		$context = AMP_Theme_Support::whitelist_layout_in_wp_kses_allowed_html( $image_with_dimensions );
		$this->assertTrue( $context['img'][ $attribute ] );

		$context = AMP_Theme_Support::whitelist_layout_in_wp_kses_allowed_html( $image_with_dimensions );
		$this->assertTrue( $context['img'][ $attribute ] );

		add_filter( 'wp_kses_allowed_html', 'AMP_Theme_Support::whitelist_layout_in_wp_kses_allowed_html', 10, 2 );
		$image = '<img data-amp-layout="fill">';
		$this->assertEquals( $image, wp_kses_post( $image ) );
	}

	/**
	 * Test AMP_Theme_Support::amend_header_image_with_video_header().
	 *
	 * @see AMP_Theme_Support::amend_header_image_with_video_header()
	 */
	public function test_amend_header_image_with_video_header() {
		$mock_image = '<img src="https://example.com/flower.jpeg">';

		// If there's no theme support for 'custom-header', the callback should simply return the image.
		$this->assertEquals(
			$mock_image,
			AMP_Theme_Support::amend_header_image_with_video_header( $mock_image )
		);

		// If theme support is present, but there isn't a header video selected, the callback should again return the image.
		add_theme_support(
			'custom-header',
			[
				'video' => true,
			]
		);

		// There's a YouTube URL as the header video.
		set_theme_mod( 'external_header_video', 'https://www.youtube.com/watch?v=a8NScvBhVnc' );
		$this->assertEquals(
			$mock_image . '<amp-youtube media="(min-width: 900px)" width="0" height="0" layout="responsive" autoplay loop id="wp-custom-header-video" data-videoid="a8NScvBhVnc" data-param-rel="0" data-param-showinfo="0" data-param-controls="0" data-param-iv_load_policy="3" data-param-modestbranding="1" data-param-playsinline="1" data-param-disablekb="1" data-param-fs="0"></amp-youtube><style>#wp-custom-header-video .amp-video-eq { display:none; }</style>',
			AMP_Theme_Support::amend_header_image_with_video_header( $mock_image )
		);
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Class AMP_Theme_Support_Sanitizer_Counter
 */
class AMP_Theme_Support_Sanitizer_Counter extends AMP_Base_Sanitizer {

	/**
	 * Count.
	 *
	 * @var int
	 */
	public static $count = 0;

	/**
	 * "Sanitize".
	 */
	public function sanitize() {
		self::$count++;
	}
}

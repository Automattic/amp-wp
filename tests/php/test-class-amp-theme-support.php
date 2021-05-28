<?php
/**
 * Tests for Theme Support.
 *
 * @package AMP
 * @since 0.7
 */

use AmpProject\AmpWP\Admin\ReaderThemes;
use AmpProject\AmpWP\ConfigurationArgument;
use AmpProject\AmpWP\Dom\Options;
use AmpProject\AmpWP\Option;
use AmpProject\AmpWP\QueryVar;
use AmpProject\AmpWP\Tests\Helpers\AssertContainsCompatibility;
use AmpProject\AmpWP\Tests\Helpers\PrivateAccess;
use AmpProject\AmpWP\Tests\Helpers\LoadsCoreThemes;
use AmpProject\Dom\Document;
use org\bovigo\vfs;

/**
 * Tests for Theme Support.
 *
 * @covers AMP_Theme_Support
 */
class Test_AMP_Theme_Support extends WP_UnitTestCase {

	use AssertContainsCompatibility;
	use PrivateAccess;
	use LoadsCoreThemes;

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
		remove_theme_support( 'amp' );
		$this->register_core_themes();
	}

	/**
	 * Set template mode.
	 *
	 * @param string $mode Mode/
	 */
	private function set_template_mode( $mode ) {
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, $mode );
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

		$this->set_private_property( AMP_Theme_Support::class, 'metadata', null );

		parent::tearDown();
		unset( $GLOBALS['show_admin_bar'] );
		AMP_Validation_Manager::$is_validate_request = false;
		AMP_Validation_Manager::reset_validation_results();
		$this->set_template_mode( AMP_Theme_Support::READER_MODE_SLUG );
		remove_theme_support( 'custom-header' );
		$_REQUEST                = []; // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
		$_SERVER['QUERY_STRING'] = '';
		$_SERVER['REQUEST_URI']  = '';
		unset( $_SERVER['REQUEST_METHOD'] );
		unset( $GLOBALS['content_width'] );
		if ( isset( $GLOBALS['wp_customize'] ) ) {
			$GLOBALS['wp_customize']->stop_previewing_theme();
		}
		AMP_HTTP::$headers_sent = [];
		remove_all_filters( 'theme_root' );
		remove_all_filters( 'template' );
		unregister_post_type( 'book' );
		unregister_post_type( 'announcement' );
		$this->restore_theme_directories();
	}

	/**
	 * Test init.
	 *
	 * @covers AMP_Theme_Support::init()
	 */
	public function test_init() {
		$_REQUEST['__amp_source_origin'] = 'foo';
		$_GET['__amp_source_origin']     = 'foo';

		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
		AMP_Theme_Support::init();
		$this->assertEquals( false, has_action( 'widgets_init', [ self::TESTED_CLASS, 'register_widgets' ] ) );
		$this->assertEquals( PHP_INT_MAX, has_action( 'wp', [ self::TESTED_CLASS, 'finish_init' ] ) );
	}

	/** @covers AMP_Theme_Support::get_theme_support_args() */
	public function test_get_theme_support_args() {
		remove_theme_support( 'amp' );
		$this->assertFalse( AMP_Theme_Support::get_theme_support_args() );

		// When paired not supplied, default to paired.
		add_theme_support( 'amp' );
		$args = AMP_Theme_Support::get_theme_support_args();
		$this->assertEquals(
			[ 'paired' => true ],
			$args
		);

		// Handle explicit requirement for Standard mode.
		add_theme_support( 'amp', [ 'paired' => false ] );
		$args = AMP_Theme_Support::get_theme_support_args();
		$this->assertEquals(
			[
				'paired' => false,
			],
			$args
		);

		// Redundant paired.
		add_theme_support(
			'amp',
			[
				'paired' => true,
				'foo'    => 'bar',
			]
		);
		$args = AMP_Theme_Support::get_theme_support_args();
		$this->assertEquals(
			[
				'paired' => true,
				'foo'    => 'bar',
			],
			$args
		);

		// Merged paired.
		add_theme_support( 'amp', [ 'foo' => 'bar' ] );
		$args = AMP_Theme_Support::get_theme_support_args();
		$this->assertEquals(
			[
				'paired' => true,
				'foo'    => 'bar',
			],
			$args
		);
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

		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );

		// The mode is Standard, and there is no /amp directory, so this should be false.
		$this->assertFalse( AMP_Theme_Support::supports_reader_mode() );

		$this->set_template_mode( AMP_Theme_Support::READER_MODE_SLUG );

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
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );

		// Test transitional mode singular.
		$this->go_to( get_permalink( $post_id ) );
		$this->assertFalse( amp_is_request() );
		AMP_Theme_Support::finish_init();

		// Test transitional mode homepage.
		$this->go_to( home_url() );
		$this->assertFalse( amp_is_request() );
		AMP_Theme_Support::finish_init();

		// Test canonical, so amphtml link is not added and init finalizes.
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'template_dir' => 'my-amp',
			]
		);
		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( amp_is_request() );
		AMP_Theme_Support::finish_init();
		$this->assertEquals( 10, has_filter( 'index_template_hierarchy', [ 'AMP_Theme_Support', 'filter_amp_template_hierarchy' ] ), 'Expected add_amp_template_filters to have been called since template_dir is not empty' );
		$this->assertEquals( 20, has_action( 'wp_head', 'amp_add_generator_metadata' ), 'Expected add_hooks to have been called' );
		$this->assertTrue( current_theme_supports( 'amp' ) );

		// Test Transitional without existing theme support args.
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		remove_theme_support( 'amp' );
		$this->go_to( amp_get_permalink( $post_id ) );
		$this->assertTrue( amp_is_request() );
		AMP_Theme_Support::finish_init();
		$this->assertTrue( current_theme_supports( 'amp' ) );

		// Test Transitional with existing theme support args.
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		add_theme_support( 'amp', [ 'foo' => 'bar' ] );
		$this->go_to( amp_get_permalink( $post_id ) );
		$this->assertTrue( amp_is_request() );
		AMP_Theme_Support::finish_init();
		$this->assertTrue( current_theme_supports( 'amp' ) );
		$this->assertEquals( [ [ 'foo' => 'bar' ] ], get_theme_support( 'amp' ) );

		// Test legacy Reader mode.
		add_theme_support( 'amp' );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::READER_MODE_SLUG );
		AMP_Options_Manager::update_option( Option::READER_THEME, ReaderThemes::DEFAULT_READER_THEME );
		$this->assertTrue( amp_is_legacy() );
		$this->go_to( amp_get_permalink( $post_id ) );
		AMP_Theme_Support::finish_init();
		$this->assertFalse( current_theme_supports( 'amp' ) );

		// Test Reader mode with Reader theme.
		remove_theme_support( 'amp' );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::READER_MODE_SLUG );
		AMP_Options_Manager::update_option( Option::READER_THEME, 'twentyseventeen' );
		$this->assertFalse( amp_is_legacy() );
		$this->go_to( amp_get_permalink( $post_id ) );
		AMP_Theme_Support::finish_init();
		$this->assertTrue( current_theme_supports( 'amp' ) );
	}

	/**
	 * Test is_paired_available.
	 *
	 * @covers AMP_Theme_Support::is_paired_available()
	 */
	public function test_is_paired_available() {

		// Establish initial state.
		$post_id = self::factory()->post->create( [ 'post_title' => 'Test' ] );
		$this->set_template_mode( AMP_Theme_Support::READER_MODE_SLUG );
		query_posts( [ 'p' => $post_id ] ); // phpcs:ignore
		$this->assertTrue( is_singular() );

		// Transitional support is not available if theme support is not present or canonical.
		$this->assertFalse( AMP_Theme_Support::is_paired_available() );
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
		$this->assertFalse( AMP_Theme_Support::is_paired_available() );

		// Transitional mode is available once template_dir is supplied.
		delete_option( AMP_Options_Manager::OPTION_NAME );
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

		AMP_Options_Manager::update_option( Option::ALL_TEMPLATES_SUPPORTED, false );
		AMP_Options_Manager::update_option( Option::SUPPORTED_TEMPLATES, [ 'is_singular' ] );

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
		// Remove ID attributes which were added in WP 5.5.
		add_filter(
			'script_loader_tag',
			static function ( $script ) {
				return preg_replace( "/ id='amp-[^']+?'/", '', $script );
			}
		);

		wp_scripts();
		wp();
		add_filter( 'amp_validation_error_sanitized', '__return_true' );
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		ob_start();
		?>
		<!DOCTYPE html>
		<html lang="en-US" class="no-js">
			<head>
				<meta charset="utf-8">
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

		// Insufficient viewport tag was not left in.
		$this->assertStringNotContains( '<meta name="viewport" content="maximum-scale=1.0">', $sanitized_html );

		// Viewport tag was modified to include all requirements.
		$this->assertStringContains( '<meta name="viewport" content="maximum-scale=1.0,width=device-width">', $sanitized_html );

		// MathML script was added.
		$this->assertStringContains( 'https://cdn.ampproject.org/v0/amp-mathml-0.1.js', $sanitized_html ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
	}

	/**
	 * Test incorrect usage for get_template_availability.
	 *
	 * @expectedIncorrectUsage AMP_Theme_Support::get_template_availability
	 * @covers AMP_Theme_Support::get_template_availability()
	 */
	public function test_incorrect_usage_get_template_availability() {
		global $wp_query;
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
		$this->assertFalse( amp_is_legacy() );

		// Suppress PWA doing-it-wrong messages from PWA plugin.
		remove_action( 'parse_query', 'wp_hide_admin_bar_offline' ); // PWA<0.5
		remove_action( 'parse_query', 'wp_unauthenticate_error_template_requests' ); // PWA>=0.5

		// Test no query available.
		$wp_query     = null;
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertInternalType( 'array', $availability );
		$this->assertEquals( [ 'no_query_available' ], $availability['errors'] );
		$this->assertFalse( $availability['supported'] );
		$this->assertNull( $availability['template'] );

		// Test no theme support.
		$this->set_template_mode( AMP_Theme_Support::READER_MODE_SLUG );
		$this->assertTrue( amp_is_legacy() );
		$this->go_to( get_permalink( self::factory()->post->create() ) );
		$availability = AMP_Theme_Support::get_template_availability();
		$this->assertEquals( [ 'legacy_reader_mode' ], $availability['errors'] );
		$this->assertFalse( $availability['supported'] );
		$this->assertNull( $availability['template'] );
	}

	/**
	 * Test get_template_availability.
	 *
	 * @covers AMP_Theme_Support::get_template_availability()
	 */
	public function test_get_template_availability() {
		// Suppress PWA doing-it-wrong messages from PWA plugin.
		remove_action( 'parse_query', 'wp_hide_admin_bar_offline' ); // PWA<0.5
		remove_action( 'parse_query', 'wp_unauthenticate_error_template_requests' ); // PWA>=0.5

		global $wp_query;
		$post_id = self::factory()->post->create();
		query_posts( [ 'p' => $post_id ] ); // phpcs:ignore

		// Test successful match of singular template.
		$this->assertTrue( is_singular() );
		AMP_Options_Manager::update_option( Option::ALL_TEMPLATES_SUPPORTED, false );
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
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
		AMP_Options_Manager::update_option( Option::SUPPORTED_TEMPLATES, [ 'is_special', 'is_custom', 'is_page' ] );
		add_filter(
			'amp_supportable_templates',
			static function( $templates ) {
				$templates['is_single']        = [
					'label'  => 'Single post',
					'parent' => 'is_singular',
				];
				$templates['is_special']       = [
					'label'    => 'Special post',
					'parent'   => 'is_single',
					'callback' => static function( WP_Query $query ) {
						return $query->is_singular() && 'special' === get_post( $query->get_queried_object_id() )->post_name;
					},
				];
				$templates['is_page']          = [
					'label'  => 'Page',
					'parent' => 'is_singular',
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

		$supported_post_types = array_diff(
			AMP_Options_Manager::get_option( Option::SUPPORTED_POST_TYPES ),
			[ 'page' ]
		);
		AMP_Options_Manager::update_option( Option::SUPPORTED_POST_TYPES, $supported_post_types );
		$availability = AMP_Theme_Support::get_template_availability( self::factory()->post->create_and_get( [ 'post_type' => 'page' ] ) );
		$this->assertFalse( $availability['supported'] );
		$this->assertEquals( [ 'post-type-support' ], $availability['errors'] );
		$this->assertEquals( 'is_page', $availability['template'] );
		AMP_Options_Manager::update_option(
			Option::SUPPORTED_POST_TYPES,
			array_merge( $supported_post_types, [ 'page' ] )
		);
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
		AMP_Options_Manager::update_option( Option::ALL_TEMPLATES_SUPPORTED, true );
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
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
		AMP_Options_Manager::update_option( Option::SUPPORTED_TEMPLATES, [ 'missing_parent' ] );
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
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
		AMP_Options_Manager::update_option( Option::ALL_TEMPLATES_SUPPORTED, true );
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

		// Test inclusion of custom template.
		add_filter(
			'amp_supportable_templates',
			static function( $templates ) {
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
		$this->assertArrayHasKey( 'is_custom', $supportable_templates );
		remove_all_filters( 'amp_supportable_templates' );
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

		$this->assertEquals( PHP_INT_MAX, has_filter( 'comment_form_defaults', [ self::TESTED_CLASS, 'filter_comment_form_defaults' ] ) );
		$this->assertEquals( 10, has_filter( 'comment_reply_link', [ self::TESTED_CLASS, 'filter_comment_reply_link' ] ) );
		$this->assertEquals( 10, has_filter( 'cancel_comment_reply_link', [ self::TESTED_CLASS, 'filter_cancel_comment_reply_link' ] ) );
		$this->assertFalse( has_action( 'comment_form', 'wp_comment_form_unfiltered_html_nonce' ) );
		$this->assertEquals( PHP_INT_MAX, has_filter( 'get_header_image_tag', [ self::TESTED_CLASS, 'amend_header_image_with_video_header' ] ) );
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
	 * Test get_current_canonical_url when using Standard mode.
	 *
	 * @covers AMP_Theme_Support::get_current_canonical_url()
	 */
	public function test_get_current_canonical_url_in_standard_mode() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );

		$post = self::factory()->post->create_and_get(
			[
				'post_type'   => 'post',
				'post_name'   => amp_get_slug(),
				'post_status' => 'publish',
			]
		);

		$current_url = home_url( get_permalink( $post ) );
		$this->go_to( $current_url );
		$this->assertEquals( $current_url, AMP_Theme_Support::get_current_canonical_url() );
	}

	/**
	 * Test get_current_canonical_url when using Transitional mode (a Paired AMP mode).
	 *
	 * @covers AMP_Theme_Support::get_current_canonical_url()
	 */
	public function test_get_current_canonical_url_in_paired_amp() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );

		$post = self::factory()->post->create_and_get(
			[
				'post_type'   => 'post',
				'post_name'   => amp_get_slug(),
				'post_status' => 'publish',
			]
		);

		$canonical_url = get_permalink( $post );
		$amphtml_url   = amp_add_paired_endpoint( $canonical_url );
		$this->go_to( $amphtml_url );
		$this->assertEquals( $canonical_url, AMP_Theme_Support::get_current_canonical_url() );
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
		$this->assertStringContains( AMP_Theme_Support::get_comment_form_state_id( get_the_ID() ), $defaults['title_reply_before'] );
		$this->assertStringContains( 'replyToName ?', $defaults['title_reply_before'] );
		$this->assertStringContains( '</span>', $defaults['cancel_reply_before'] );
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
		$this->assertStringContains( AMP_Theme_Support::get_comment_form_state_id( get_the_ID() ), $filtered_link );
		$this->assertStringContains( $comment->comment_author, $filtered_link );
		$this->assertStringContains( $comment->comment_ID, $filtered_link );
		$this->assertStringContains( 'tap:AMP.setState', $filtered_link );
		$this->assertStringContains( $reply_text, $filtered_link );
		$this->assertStringContains( $reply_to_text, $filtered_link );
		$this->assertStringContains( $respond_id, $filtered_link );
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
		$this->assertStringContains( $url, $filtered_link );
		$this->assertStringContains( $text, $filtered_link );
		$this->assertStringContains( '<a id="cancel-comment-reply-link"', $filtered_link );
		$this->assertStringContains( '.values.comment_parent ==', $filtered_link );
		$this->assertStringContains( 'tap:AMP.setState(', $filtered_link );

		$filtered_link_no_text_passed = AMP_Theme_Support::filter_cancel_comment_reply_link( $formatted_link, $link, '' );
		$this->assertStringContains( 'Click here to cancel reply.', $filtered_link_no_text_passed );
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
		$this->assertStringContains( '<style id=\'admin-bar-inline-css\' type=\'text/css\'>', $output ); // Note: data-ampdevmode attribute will be added by AMP_Dev_Mode_Sanitizer.
		$this->assertStringNotContains( '<link rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertStringContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertStringContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'admin-bar-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertStringContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'example-admin-bar-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertStringContains( 'html { margin-top: 64px !important; }', $output );
		$this->assertRegExp( '/' . implode( '', [ '<script ', 'data-ampdevmode [^>]+admin-bar\.js' ] ) . '/', $output );
		$this->assertRegExp( '/' . implode( '', [ '<script ', 'data-ampdevmode [^>]+example-admin-bar\.js' ] ) . '/', $output );

		$body_classes = get_body_class();
		$this->assertContains( 'customize-support', $body_classes );
		$this->assertNotContains( 'no-customize-support', $body_classes );
	}

	/**
	 * Assert that the queried element exists.
	 *
	 * @param DOMXPath $xpath XPath.
	 * @param string   $query Query.
	 */
	public function assert_queried_element_exists( DOMXPath $xpath, $query ) {
		$element = $xpath->query( $query )->item( 0 );
		$this->assertInstanceOf( 'DOMElement', $element, 'Expected element for query: ' . $query );
	}

	/**
	 * Assert that dev mode attribute *is* on the queried element.
	 *
	 * @param DOMXPath $xpath XPath.
	 * @param string   $query Query.
	 */
	public function assert_dev_mode_is_on_queried_element( DOMXPath $xpath, $query ) {
		$element = $xpath->query( $query )->item( 0 );
		$this->assertInstanceOf( 'DOMElement', $element, 'Expected element for query: ' . $query );
		$this->assertTrue( $element->hasAttribute( AMP_Rule_Spec::DEV_MODE_ATTRIBUTE ), 'Expected dev mode to be enabled on element for query: ' . $query );
	}

	/**
	 * Assert that dev mode attribute is *not* on the queried element.
	 *
	 * @param DOMXPath $xpath XPath.
	 * @param string   $query Query.
	 */
	public function assert_dev_mode_is_not_on_queried_element( DOMXPath $xpath, $query ) {
		$element = $xpath->query( $query )->item( 0 );
		$this->assertInstanceOf( 'DOMElement', $element, 'Expected element for query: ' . $query );
		$this->assertFalse( $element->hasAttribute( AMP_Rule_Spec::DEV_MODE_ATTRIBUTE ), 'Expected dev mode to not be enabled on element for query: ' . $query );
	}

	/**
	 * Get data to test AMP_Theme_Support::filter_admin_bar_style_loader_tag().
	 *
	 * @return array
	 */
	public function get_data_to_test_filtering_admin_bar_style_loader_tag_data() {
		return [
			'admin_bar_exclusively_dependent'             => [
				static function () {
					wp_enqueue_style( 'example-admin-bar', 'https://example.com/example-admin-bar.css', [ 'admin-bar' ], '0.1' );
				},
				function ( DOMXPath $xpath ) {
					$this->assert_dev_mode_is_on_queried_element( $xpath, '//link[ @id = "example-admin-bar-css" ]' );
					$this->assert_dev_mode_is_on_queried_element( $xpath, '//link[ @id = "dashicons-css" ]' );
				},
			],

			'dashicons_enqueued_independent_of_admin_bar' => [
				static function () {
					wp_enqueue_style( 'dashicons' );
				},
				function ( DOMXPath $xpath ) {
					$this->assert_dev_mode_is_not_on_queried_element( $xpath, '//link[ @id = "dashicons-css" ]' );
				},
			],

			'dashicons_enqueued_independent_of_also_enqueued_admin_bar' => [
				static function () {
					wp_enqueue_style( 'admin-bar' );
					wp_enqueue_style( 'dashicons' );
				},
				function ( DOMXPath $xpath ) {
					$this->assert_dev_mode_is_not_on_queried_element( $xpath, '//link[ @id = "dashicons-css" ]' );
				},
			],

			'dashicons_not_dev_mode_because_non_admin_bar_dependency' => [
				static function () {
					wp_enqueue_style( 'admin-bar' );
					wp_enqueue_style( 'special-icons', 'https://example.com/special-icons.css', [ 'dashicons' ], '0.1' );
				},
				function ( DOMXPath $xpath ) {
					$this->assert_dev_mode_is_not_on_queried_element( $xpath, '//link[ @id = "dashicons-css" ]' );
				},
			],

			'dashicons_in_dev_mode_because_all_dependents_depend_on_admin_bar' => [
				static function () {
					wp_enqueue_style( 'admin-bar' );
					wp_enqueue_style( 'special-icons', 'https://example.com/special-icons.css', [ 'dashicons', 'admin-bar' ], '0.1' );
				},
				function ( DOMXPath $xpath ) {
					$this->assert_dev_mode_is_on_queried_element( $xpath, '//link[ @id = "dashicons-css" ]' );
				},
			],

			'dashicons_in_dev_mode_because_all_recursive_dependents_depend_on_admin_bar' => [
				static function () {
					wp_enqueue_style( 'admin-bar' );
					wp_register_style( 'special-dashicons', 'https://example.com/special-dashicons.css', [ 'dashicons' ], '0.1' );
					wp_enqueue_style( 'colorized-admin-bar', 'https://example.com/special-icons.css', [ 'admin-bar' ], '0.1' );
					wp_enqueue_style( 'special-icons', 'https://example.com/special-icons.css', [ 'special-dashicons', 'colorized-admin-bar' ], '0.1' );
				},
				function ( DOMXPath $xpath ) {
					$this->assert_dev_mode_is_on_queried_element( $xpath, '//link[ @id = "dashicons-css" ]' );
				},
			],
		];
	}

	/**
	 * Test filter_admin_bar_style_loader_tag.
	 *
	 * @dataProvider get_data_to_test_filtering_admin_bar_style_loader_tag_data
	 * @covers \AMP_Theme_Support::filter_admin_bar_style_loader_tag()
	 * @covers \AMP_Theme_Support::is_exclusively_dependent()
	 *
	 * @param callable $setup_callback  Setup callback.
	 * @param callable $assert_callback Assert callback.
	 */
	public function test_filter_admin_bar_style_loader_tag( $setup_callback, $assert_callback ) {
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
		$this->go_to( '/' );
		add_filter( 'amp_dev_mode_enabled', '__return_true' );
		add_filter( 'style_loader_tag', [ 'AMP_Theme_Support', 'filter_admin_bar_style_loader_tag' ], 10, 2 );
		$setup_callback();
		ob_start();
		echo '<html><head>';
		wp_print_styles();
		echo '</head><body></body></html>';
		$output = ob_get_clean();

		$dom = new Document();
		$dom->loadHTML( $output );

		$assert_callback( new DOMXPath( $dom ) );
	}

	/**
	 * Test filter_admin_bar_style_loader_tag when ->deps is not an array.
	 *
	 * @covers \AMP_Theme_Support::filter_admin_bar_style_loader_tag()
	 */
	public function test_filter_admin_bar_style_loader_tag_non_array() {
		wp_enqueue_style( 'admin-bar' );
		$GLOBALS['wp_styles']->registered['admin-bar']->deps = null;
		$tag = '<link rel="stylesheet" id="dashicons-css" href="https://example.com/wp-includes/css/dashicons.css?ver=5.3.2" media="all" />'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertEquals( $tag, AMP_Theme_Support::filter_admin_bar_style_loader_tag( $tag, 'baz' ) );
	}

	/**
	 * Get data to test AMP_Theme_Support::filter_admin_bar_script_loader_tag().
	 *
	 * @return array
	 */
	public function get_data_to_test_filtering_admin_bar_script_loader_tag_data() {
		return [
			'admin_bar_scripts_have_dev_mode'        => [
				static function () {
					wp_enqueue_script( 'admin-bar' );
					wp_enqueue_script( 'example-admin-bar', 'https://example.com/example-admin-bar.js', [ 'admin-bar' ], '0.1', false );
				},
				function ( DOMXPath $xpath ) {
					$this->assert_dev_mode_is_on_queried_element( $xpath, '//script[ contains( @src, "/example-admin-bar" ) ]' );
					$this->assert_dev_mode_is_on_queried_element( $xpath, '//script[ contains( @src, "/admin-bar" ) ]' );
					if ( wp_script_is( 'hoverintent-js', 'registered' ) ) {
						$this->assert_dev_mode_is_on_queried_element( $xpath, '//script[ contains( @src, "/hoverintent-js" ) ]' );
					}
				},
			],

			'hoverintent_enqueued_prevents_dev_mode' => [
				function () {
					if ( ! wp_script_is( 'hoverintent-js', 'registered' ) ) {
						$this->markTestSkipped( 'The hoverintent-js script is not registered.' );
					}
					wp_enqueue_script( 'admin-bar' );
					wp_enqueue_script( 'theme-hover', 'https://example.com/theme-hover.js', [ 'hoverintent-js' ], '0.1', false );
				},
				function ( DOMXPath $xpath ) {
					$this->assert_dev_mode_is_on_queried_element( $xpath, '//script[ contains( @src, "/admin-bar" ) ]' );
					$this->assert_dev_mode_is_not_on_queried_element( $xpath, '//script[ contains( @src, "/theme-hover" ) ]' );
					$this->assert_dev_mode_is_not_on_queried_element( $xpath, '//script[ contains( @src, "/hoverintent-js" ) ]' );
				},
			],
		];
	}

	/**
	 * Test filter_admin_bar_script_loader_tag.
	 *
	 * @dataProvider get_data_to_test_filtering_admin_bar_script_loader_tag_data
	 * @covers \AMP_Theme_Support::filter_admin_bar_script_loader_tag()
	 * @covers \AMP_Theme_Support::is_exclusively_dependent()
	 *
	 * @param callable $setup_callback  Setup callback.
	 * @param callable $assert_callback Assert callback.
	 */
	public function test_filter_admin_bar_script_loader_tag( $setup_callback, $assert_callback ) {
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
		$this->go_to( '/' );
		add_filter( 'amp_dev_mode_enabled', '__return_true' );
		add_filter( 'script_loader_tag', [ 'AMP_Theme_Support', 'filter_admin_bar_script_loader_tag' ], 10, 2 );
		$setup_callback();
		ob_start();
		echo '<html><head>';
		wp_print_scripts();
		echo '</head><body>';
		wp_print_footer_scripts();
		echo '</body></html>';
		$output = ob_get_clean();

		$dom = new Document();
		$dom->loadHTML( $output ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$assert_callback( new DOMXPath( $dom ) );
	}

	/**
	 * Test filter_admin_bar_script_loader_tag when ->deps is not an array.
	 *
	 * @covers \AMP_Theme_Support::filter_admin_bar_script_loader_tag()
	 */
	public function test_filter_admin_bar_script_loader_tag_non_array() {
		wp_enqueue_script( 'admin-bar' );
		$GLOBALS['wp_scripts']->registered['admin-bar']->deps = null;
		$tag = '<script src="https://example.com/wp-includes/js/admin-bar.js?ver=5.3.2"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$this->assertEquals( $tag, AMP_Theme_Support::filter_admin_bar_script_loader_tag( $tag, 'example' ) );
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

		$this->assertStringContains( '<link rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertStringNotContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertStringContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'admin-bar-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
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

		$this->assertStringContains( '<link rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertStringNotContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'dashicons-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$this->assertStringContains( '<link data-ampdevmode rel=\'stylesheet\' id=\'admin-bar-css\'', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	}

	/**
	 * Test moving AMP scripts from body to head.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 * @covers AMP_Theme_Support::ensure_required_markup()
	 */
	public function test_scripts_get_moved_to_head() {
		ob_start();
		remove_all_actions( 'wp_head' );
		?>
		<html>
			<head>
				<?php wp_head(); ?>
			</head>
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
		$original_html = ob_get_clean();
		$html          = AMP_Theme_Support::prepare_response( $original_html );

		$dom = Document::fromHtml( $html, Options::DEFAULTS );

		$scripts = $dom->xpath->query( '//script[ not( @type ) or @type = "text/javascript" ]' );
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
		wp_styles(); // Needed after <https://core.trac.wordpress.org/changeset/50836>.

		wp();
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
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
		];

		ob_start();
		?>
		<html>
			<head></head>
			<body>
				<?php wp_print_scripts( array_merge( $required_usage_grandfathered, $required_usage_error, $required_usage_none ) ); ?>
				<?php wp_footer(); ?>
			</body>
		</html>
		<?php
		$html = ob_get_clean();
		$html = AMP_Theme_Support::prepare_response( $html );

		$dom = Document::fromHtml( $html, Options::DEFAULTS );

		/** @var DOMElement $script Script. */
		$actual_script_srcs = [];
		foreach ( $dom->xpath->query( '//script[ not( @amp-onerror ) and ( not( @type ) or @type = "text/javascript" ) ]' ) as $script ) {
			$actual_script_srcs[] = $script->getAttribute( 'src' );
		}

		$expected_script_srcs = [
			wp_scripts()->registered['amp-runtime']->src,
		];
		foreach ( $required_usage_none as $handle ) {
			$expected_script_srcs[] = wp_scripts()->registered[ $handle ]->src;
		}

		$this->assertEqualSets(
			array_map( 'basename', $expected_script_srcs ),
			array_map( 'basename', $actual_script_srcs )
		);
	}

	/**
	 * Test removing duplicate scripts.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 * @covers AMP_Theme_Support::ensure_required_markup()
	 */
	public function test_duplicate_scripts_are_removed() {
		wp_styles(); // Needed after <https://core.trac.wordpress.org/changeset/50836>.

		wp();
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
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
				<amp-video controls
					width="640"
					height="360"
					layout="responsive"
					poster="https://amp.dev/static/inline-examples/images/kitten-playing.png">
					<source src="https://amp.dev/static/inline-examples/videos/kitten-playing.webm"
							type="video/webm" />
					<source src="https://amp.dev/static/inline-examples/videos/kitten-playing.mp4"
							type="video/mp4" />
					<div fallback>
						<p>This browser does not support the video element.</p>
					</div>
				</amp-video>
				<?php wp_print_scripts( [ 'amp-video', 'amp-runtime' ] ); ?>
				<?php wp_footer(); ?>
			</body>
		</html>
		<?php
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$html = ob_get_clean();
		$html = AMP_Theme_Support::prepare_response( $html, [ ConfigurationArgument::ENABLE_OPTIMIZER => false ] );

		$dom = Document::fromHtml( $html, Options::DEFAULTS );

		$script_srcs = [];
		/**
		 * Script.
		 *
		 * @var DOMElement $script
		 */
		$scripts = $dom->xpath->query( '//script[ @src ]' );
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
		if ( ! function_exists( 'newrelic_disable_autorum' ) ) {

			/**
			 * Define newrelic_disable_autorum to allow passing line.
			 */
			function newrelic_disable_autorum() {
				return true;
			}
		}

		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		$initial_ob_level = ob_get_level();
		AMP_Theme_Support::start_output_buffering();
		$this->assertEquals( $initial_ob_level + 1, ob_get_level() );
		ob_end_flush();
		$this->assertEquals( $initial_ob_level, ob_get_level() );
	}

	/**
	 * Test finish_output_buffering.
	 *
	 * @covers AMP_Theme_Support::finish_output_buffering()
	 * @covers AMP_Theme_Support::is_output_buffering()
	 */
	public function test_finish_output_buffering() {
		wp_styles(); // Needed after <https://core.trac.wordpress.org/changeset/50836>.

		wp();
		add_filter( 'amp_validation_error_sanitized', '__return_true' );
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
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
		wp_footer();

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

		$this->assertStringContains( '<html amp', $output );
		$this->assertStringContains( 'foo', $output );
		$this->assertStringContains( 'BAR', $output );
		$this->assertStringContains( '<amp-img src="test.png"', $output );
		$this->assertStringNotContains( '<script data-test', $output );

	}

	/**
	 * Test filter_customize_partial_render.
	 *
	 * @covers AMP_Theme_Support::filter_customize_partial_render()
	 */
	public function test_filter_customize_partial_render() {
		wp();
		add_filter( 'amp_validation_error_sanitized', '__return_true' );
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();

		$partial = '<img src="test.png" style="border:solid 1px red;"><script data-head>document.write(\'Illegal\');</script><style>img { background:blue }</style>';
		$output  = AMP_Theme_Support::filter_customize_partial_render( $partial );
		$this->assertStringContains( '<amp-img src="test.png"', $output );
		$this->assertStringContains( '<style amp-custom-partial="">', $output );
		$this->assertStringContains( 'amp-img{background:blue}', $output );
		$this->assertStringContains( ':root:not(#_):not(#_):not(#_):not(#_):not(#_) .amp-wp-b123f72{border:solid 1px red}', $output );
		$this->assertStringEndsWith( '/*# sourceURL=amp-custom-partial.css */</style>', $output );
		$this->assertStringNotContains( '<script', $output );
		$this->assertStringNotContains( '<html', $output );
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
	public function test_prepare_response_standard() {
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		add_filter(
			'home_url',
			static function ( $url ) {
				return set_url_scheme( $url, 'https' );
			}
		);

		wp();

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$original_html = $this->get_original_html();

		$this->set_private_property( AMP_Theme_Support::class, 'metadata', amp_get_schemaorg_metadata() );

		$call_prepare_response = static function() use ( $original_html ) {
			AMP_HTTP::$headers_sent                     = [];
			AMP_Validation_Manager::$validation_results = [];
			return AMP_Theme_Support::prepare_response( $original_html );
		};

		$sanitized_html = $call_prepare_response();

		$this->assertStringNotContains( 'handle=', $sanitized_html );
		$this->assertEquals( 2, substr_count( $sanitized_html, '<!-- wp_print_scripts -->' ) );

		$ordered_contains = [
			'<html amp=""',
			'<meta charset="' . Document\Encoding::AMP . '">',
			'<meta name="viewport" content="width=device-width">',
			'<link as="script" crossorigin="anonymous" href="https://cdn.ampproject.org/v0.mjs" rel="modulepreload">',
			'<link rel="preconnect" href="https://cdn.ampproject.org">',
			'<link rel="dns-prefetch" href="//cdn.ampproject.org">',
			'<link rel="preload" as="script" href="https://cdn.ampproject.org/v0/amp-dynamic-css-classes-0.1.js">',
			'<link rel="preload" as="script" href="https://cdn.ampproject.org/v0/amp-experiment-0.1.js">',
			'<meta name="generator" content="AMP Plugin',
			'<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">',

			'<script type="module" src="https://cdn.ampproject.org/v0.mjs" async crossorigin="anonymous"></script>',
			'<script async custom-element="amp-dynamic-css-classes" src="https://cdn.ampproject.org/v0/amp-dynamic-css-classes-0.1.mjs" type="module" crossorigin="anonymous"></script>',
			'<script src="https://cdn.ampproject.org/v0/amp-experiment-0.1.mjs" async="" custom-element="amp-experiment" type="module" crossorigin="anonymous"></script>',

			'<script src="https://cdn.ampproject.org/v0/amp-ad-0.1.mjs" async="" custom-element="amp-ad" type="module" crossorigin="anonymous"></script>',
			'<script src="https://cdn.ampproject.org/v0/amp-audio-0.1.mjs" async="" custom-element="amp-audio" type="module" crossorigin="anonymous"></script>',
			'<script type="module" src="https://cdn.ampproject.org/v0/amp-list-0.1.mjs" async custom-element="amp-list" crossorigin="anonymous"></script>',
			'<script type="module" src="https://cdn.ampproject.org/v0/amp-mathml-0.1.mjs" async custom-element="amp-mathml" crossorigin="anonymous"></script>',

			'<link rel="icon" href="https://example.org/favicon.png" sizes="32x32">',
			'<link rel="icon" href="https://example.org/favicon.png" sizes="192x192">',
			'<link crossorigin="anonymous" rel="stylesheet" id="my-font-css" href="https://fonts.googleapis.com/css?family=Tangerine" type="text/css" media="all">',

			'#<style amp-custom(="")?>.*?body\s*{\s*background:\s*black;?\s*}.*?</style>#s',

			'<title>',
			'<link rel="canonical" href="',
			'<script type="application/ld+json">{"@context"',

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

		$this->assertStringContains( '<noscript><img', $sanitized_html );
		$this->assertStringContains( '<amp-img', $sanitized_html );

		$this->assertStringContains( '<noscript><audio', $sanitized_html );
		$this->assertStringContains( '<amp-audio', $sanitized_html );

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

		$this->assertStringContains( '<button>no-onclick</button>', $sanitized_html );
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

		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	}

	/**
	 * Test prepare_response for standard mode when some validation errors aren't auto-sanitized.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_prepare_response_standard_mode_non_amp() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		add_filter( 'amp_dev_mode_enabled', '__return_false' );
		wp();
		$original_html = $this->get_original_html();
		add_filter( 'amp_validation_error_sanitized', '__return_false' ); // For testing purpose only. This should not normally be done.

		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html, [ ConfigurationArgument::ENABLE_OPTIMIZER => false ] );

		$this->assertStringContains( '<html>', $sanitized_html, 'The AMP attribute is removed from the HTML element' );
		$this->assertStringContains( '<button onclick="alert', $sanitized_html, 'Invalid AMP is present in the response.' );
		$this->assertStringContains( 'document.write = function', $sanitized_html, 'Override of document.write() is present.' );
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
	 * Test prepare_response when validating an invalid AMP page.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_prepare_response_for_validating_invalid_amp_page() {
		AMP_Validation_Manager::$is_validate_request = true;

		$response = AMP_Theme_Support::prepare_response( '' );
		$this->assertJson( $response );
		$this->assertStringContains( 'RENDERED_PAGE_NOT_AMP', $response );
	}

	/**
	 * Initializes and returns the original HTML.
	 */
	private function get_original_html() {
		// Remove ID attributes which were added in WP 5.5.
		add_filter(
			'script_loader_tag',
			static function ( $script ) {
				return preg_replace( "/ id='amp-[^']+?'/", '', $script );
			}
		);

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		global $wp_widget_factory, $wp_scripts, $wp_styles;
		$wp_scripts = null;
		$wp_styles  = null;
		wp_scripts();
		wp_styles();

		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();
		$wp_widget_factory->widgets = [];
		wp_widgets_init();

		$this->assertTrue( amp_is_request() );

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
		<!-- 2nd image is needed for testing <noscript> as first is SSR'ed -->
		<img width="100" height="100" src="https://example.com/hero.png">
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
		<amp-list src="https://example.com/list.json?RANDOM" width="100" height="100"></amp-list>
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
	 * Test prepare_response for responses that do not trigger standard template actions.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_prepare_response_doing_template_actions() {
		global $wp_actions;
		wp();
		$this->set_template_mode( AMP_Theme_Support::STANDARD_MODE_SLUG );
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

		// HTML but no template actions triggered.
		$input = '<html><head></head></html>';
		$this->assertEquals( $input, AMP_Theme_Support::prepare_response( $input ) );

		// HTML with AMP attribute.
		$input  = '<html amp><head></head>Hello</html>';
		$output = AMP_Theme_Support::prepare_response( $input );
		$this->assertStringContains( '<html amp', $output );
		$this->assertStringContains( '<meta charset="utf-8">', $output );

		// HTML with AMP emoji attribute.
		$input  = '<html ⚡><head></head>Hello</html>';
		$output = AMP_Theme_Support::prepare_response( $input );
		$this->assertStringContains( '<html amp', $output );
		$this->assertStringContains( '<meta charset="utf-8">', $output );

		// HTML with alternative AMP emoji attribute.
		$input  = '<html lang="en-US" ⚡️ foo="" bar="baz"><head></head>Hello</html>';
		$output = AMP_Theme_Support::prepare_response( $input );
		$this->assertStringContains( '<html lang="en-US" amp', $output );
		$this->assertStringContains( '<meta charset="utf-8">', $output );

		// HTML with doctype, comments, and whitespace before head.
		$input  = "   <!--\nHello world!\n-->\n\n<!DOCTYPE html>  <html\n\namp>\n<head profile='http://www.acme.com/profiles/core'></head><body>Hello</body></html>";
		$output = AMP_Theme_Support::prepare_response( $input );
		$this->assertStringContains( '<html amp', $output );
		$this->assertStringContains( '<meta charset="utf-8">', $output );

		$get_do_action = static function ( $action ) {
			return get_echo( 'do_action', [ $action ] );
		};
		foreach ( [ 'wp_head', 'wp_footer', 'amp_post_template_head', 'amp_post_template_footer' ] as $action ) {
			$wp_actions = [];
			wp_enqueue_scripts();
			$input  = '<html><head></head>' . $get_do_action( $action ) . '</html>';
			$output = AMP_Theme_Support::prepare_response( $input );
			$this->assertStringContains( '<html amp', $output );
			$this->assertStringContains( '<meta charset="utf-8">', $output );
		}
	}

	/**
	 * Test prepare_response for responses that throw an exception.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_prepare_response_throwing_exception() {
		// Set up temporary capture of error log to test error log output.
		$capture = tmpfile();
		$backup  = ini_set( // phpcs:ignore WordPress.PHP.IniSet.Risky
			'error_log',
			stream_get_meta_data( $capture )['uri']
		);

		add_filter(
			'amp_enable_optimizer',
			static function () {
				throw new RuntimeException( 'FAILURE', 42 );
			}
		);

		if ( ! function_exists( 'newrelic_disable_autorum' ) ) {

			/**
			 * Define newrelic_disable_autorum to allow passing line.
			 */
			function newrelic_disable_autorum() {
				return true;
			}
		}

		wp();
		$output = AMP_Theme_Support::finish_output_buffering( $this->get_original_html() );

		// Verify that error log was properly populated.
		$this->assertRegExp(
			'/^\[[^\]]*\] A PHP error occurred while trying to prepare the AMP response\..*- FAILURE \(42\) \[RuntimeException\].*/',
			stream_get_contents( $capture )
		);

		// Reset error log back to initial settings.
		ini_set( 'error_log', $backup ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		$this->assertStringContains( 'Failed to prepare AMP page', $output );
	}

	/**
	 * Test prepare_response will cache redirects when validation errors happen.
	 *
	 * @covers AMP_Theme_Support::prepare_response()
	 */
	public function test_prepare_response_redirect() {
		add_filter( 'amp_validation_error_sanitized', '__return_false', 100 );

		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				AMP_Theme_Support::PAIRED_FLAG => true,
			]
		);
		$this->go_to( home_url( '/?amp=1' ) );
		add_filter(
			'amp_content_sanitizers',
			static function( $sanitizers ) {
				$sanitizers['AMP_Theme_Support_Sanitizer_Counter'] = [];
				return $sanitizers;
			}
		);
		AMP_Theme_Support::init();
		AMP_Theme_Support::finish_init();
		$this->assertTrue( amp_is_request() );

		ob_start();
		?>
		<html>
			<head>
				<?php wp_head(); ?>
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
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html );
		$this->assertStringStartsWith( 'Redirecting', $sanitized_html );
		$this->assertCount( 1, $redirects );
		$this->assertEquals( add_query_arg( QueryVar::NOAMP, QueryVar::NOAMP_AVAILABLE, home_url( '/' ) ), $redirects[0] );
		$this->assertEquals( 1, AMP_Theme_Support_Sanitizer_Counter::$count );

		AMP_Validation_Manager::reset_validation_results();
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html );
		$this->assertStringStartsWith( 'Redirecting', $sanitized_html );
		$this->assertCount( 2, $redirects );
		$this->assertEquals( add_query_arg( QueryVar::NOAMP, QueryVar::NOAMP_AVAILABLE, home_url( '/' ) ), $redirects[0] );
		$this->assertEquals( 2, AMP_Theme_Support_Sanitizer_Counter::$count, 'Expected sanitizer to be invoked again.' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		AMP_Validation_Manager::add_validation_error_sourcing();

		AMP_Validation_Manager::reset_validation_results();
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html );
		$this->assertStringStartsWith( '<!DOCTYPE html>', $sanitized_html, 'Expected page instead of redirect since admin logged-in.' );
		$this->assertCount( 2, $redirects );
		$this->assertEquals( 3, AMP_Theme_Support_Sanitizer_Counter::$count, 'Expected sanitizer be invoked after validation changed.' );

		AMP_Validation_Manager::reset_validation_results();
		$sanitized_html = AMP_Theme_Support::prepare_response( $original_html );
		$this->assertStringStartsWith( '<!DOCTYPE html>', $sanitized_html, 'Expected page instead of redirect since admin logged-in.' );
		$this->assertCount( 2, $redirects );
		$this->assertEquals( 4, AMP_Theme_Support_Sanitizer_Counter::$count, 'Expected sanitizer to be invoked again although validation results are cached.' );
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
	 * Test AMP_Theme_Support::include_layout_in_wp_kses_allowed_html().
	 *
	 * @see AMP_Theme_Support::include_layout_in_wp_kses_allowed_html()
	 */
	public function test_include_layout_in_wp_kses_allowed_html() {
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

		$this->assertEquals( [], AMP_Theme_Support::include_layout_in_wp_kses_allowed_html( [] ) );
		$this->assertEquals( $image_no_dimensions, AMP_Theme_Support::include_layout_in_wp_kses_allowed_html( $image_no_dimensions ) );

		$context = AMP_Theme_Support::include_layout_in_wp_kses_allowed_html( $image_with_dimensions );
		$this->assertTrue( $context['img'][ $attribute ] );

		$context = AMP_Theme_Support::include_layout_in_wp_kses_allowed_html( $image_with_dimensions );
		$this->assertTrue( $context['img'][ $attribute ] );

		add_filter( 'wp_kses_allowed_html', 'AMP_Theme_Support::include_layout_in_wp_kses_allowed_html', 10, 2 );
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

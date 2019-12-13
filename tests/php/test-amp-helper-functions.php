<?php
/**
 * Test AMP helper functions.
 *
 * @package AMP
 */

/**
 * Class Test_AMP_Helper_Functions
 */
class Test_AMP_Helper_Functions extends WP_UnitTestCase {

	use AMP_Test_HandleValidation;

	/**
	 * The mock Site Icon value to use in a filter.
	 *
	 * @var string
	 */
	const MOCK_SITE_ICON = 'https://example.com/new-site-icon.jpg';

	/**
	 * After a test method runs, reset any state in WordPress the test method might have changed.
	 */
	public function tearDown() {
		remove_theme_support( AMP_Theme_Support::SLUG );
		global $wp_scripts, $pagenow, $show_admin_bar;
		$wp_scripts     = null;
		$show_admin_bar = null;
		$pagenow        = 'index.php'; // Since clean_up_global_scope() doesn't.
		$_GET           = [];

		if ( class_exists( 'WP_Block_Type_Registry' ) ) {
			foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $block ) {
				if ( 'amp/' === substr( $block->name, 0, 4 ) ) {
					WP_Block_Type_Registry::get_instance()->unregister( $block->name );
				}
			}
		}

		parent::tearDown();
	}

	/**
	 * Filter for amp_pre_get_permalink and amp_get_permalink.
	 *
	 * @param string $url     URL.
	 * @param int    $post_id Post ID.
	 * @return string URL.
	 */
	public function return_example_url( $url, $post_id ) {
		$current_filter = current_filter();
		return 'http://overridden.example.com/?' . build_query( compact( 'url', 'post_id', 'current_filter' ) );
	}

	/**
	 * Test amp_get_slug().
	 *
	 * @covers ::amp_get_slug()
	 */
	public function test_amp_get_slug() {
		$this->assertSame( 'amp', amp_get_slug() );
	}

	/**
	 * Test amp_get_current_url().
	 *
	 * @covers ::amp_get_current_url()
	 */
	public function test_amp_get_current_url() {
		$request_uris = [
			'/foo',
			'/bar?baz',
			null,
		];

		foreach ( $request_uris as $request_uri ) {
			if ( $request_uri ) {
				$_SERVER['REQUEST_URI'] = wp_slash( $request_uri );
			} else {
				unset( $_SERVER['REQUEST_URI'] );
			}
			$this->assertEquals(
				home_url( $request_uri ?: '/' ),
				amp_get_current_url(),
				sprintf( 'Unexpected for URI: %s', wp_json_encode( $request_uri, 64 /* JSON_UNESCAPED_SLASHES */ ) )
			);
		}
	}

	/**
	 * Test amp_get_permalink() without pretty permalinks.
	 *
	 * @covers ::amp_get_permalink()
	 */
	public function test_amp_get_permalink_without_pretty_permalinks() {
		remove_theme_support( AMP_Theme_Support::SLUG );
		delete_option( 'permalink_structure' );
		flush_rewrite_rules();

		$drafted_post   = self::factory()->post->create(
			[
				'post_name'   => 'draft',
				'post_status' => 'draft',
				'post_type'   => 'post',
			]
		);
		$published_post = self::factory()->post->create(
			[
				'post_name'   => 'publish',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);
		$published_page = self::factory()->post->create(
			[
				'post_name'   => 'publish',
				'post_status' => 'publish',
				'post_type'   => 'page',
			]
		);

		$this->assertStringEndsWith( '&amp', amp_get_permalink( $published_post ) );
		$this->assertStringEndsWith( '&amp', amp_get_permalink( $drafted_post ) );
		$this->assertStringEndsWith( '&amp', amp_get_permalink( $published_page ) );

		add_filter( 'amp_pre_get_permalink', [ $this, 'return_example_url' ], 10, 2 );
		add_filter( 'amp_get_permalink', [ $this, 'return_example_url' ], 10, 2 );
		$url = amp_get_permalink( $published_post );
		$this->assertContains( 'current_filter=amp_pre_get_permalink', $url );
		$this->assertContains( 'url=0', $url );

		remove_filter( 'amp_pre_get_permalink', [ $this, 'return_example_url' ], 10 );
		$url = amp_get_permalink( $published_post );
		$this->assertContains( 'current_filter=amp_get_permalink', $url );
		remove_filter( 'amp_pre_get_permalink', [ $this, 'return_example_url' ] );

		// Now check with theme support added (in transitional mode).
		add_theme_support( AMP_Theme_Support::SLUG, [ 'template_dir' => './' ] );
		$this->assertStringEndsWith( '&amp', amp_get_permalink( $published_post ) );
		$this->assertStringEndsWith( '&amp', amp_get_permalink( $drafted_post ) );
		$this->assertStringEndsWith( '&amp', amp_get_permalink( $published_page ) );
		add_filter( 'amp_get_permalink', [ $this, 'return_example_url' ], 10, 2 );
		$this->assertNotContains( 'current_filter=amp_get_permalink', amp_get_permalink( $published_post ) ); // Filter does not apply.
		add_filter( 'amp_pre_get_permalink', [ $this, 'return_example_url' ], 10, 2 );
		$this->assertNotContains( 'current_filter=amp_pre_get_permalink', amp_get_permalink( $published_post ) ); // Filter does not apply.
	}

	/**
	 * Test amp_get_permalink() with pretty permalinks.
	 *
	 * @covers ::amp_get_permalink()
	 */
	public function test_amp_get_permalink_with_pretty_permalinks() {
		global $wp_rewrite;
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
		$wp_rewrite->use_trailing_slashes = true;
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();

		$add_anchor_fragment = static function( $url ) {
			return $url . '#anchor';
		};

		$drafted_post   = self::factory()->post->create(
			[
				'post_name'   => 'draft',
				'post_status' => 'draft',
			]
		);
		$published_post = self::factory()->post->create(
			[
				'post_name'   => 'publish',
				'post_status' => 'publish',
			]
		);
		$published_page = self::factory()->post->create(
			[
				'post_name'   => 'publish',
				'post_status' => 'publish',
				'post_type'   => 'page',
			]
		);
		$this->assertStringEndsWith( '&amp', amp_get_permalink( $drafted_post ) );
		$this->assertStringEndsWith( '/amp/', amp_get_permalink( $published_post ) );
		$this->assertStringEndsWith( '?amp', amp_get_permalink( $published_page ) );

		add_filter( 'post_link', $add_anchor_fragment );
		$this->assertStringEndsWith( '/amp/#anchor', amp_get_permalink( $published_post ) );
		remove_filter( 'post_link', $add_anchor_fragment );

		add_filter( 'amp_pre_get_permalink', [ $this, 'return_example_url' ], 10, 2 );
		add_filter( 'amp_get_permalink', [ $this, 'return_example_url' ], 10, 2 );
		$url = amp_get_permalink( $published_post );
		$this->assertContains( 'current_filter=amp_pre_get_permalink', $url );
		$this->assertContains( 'url=0', $url );

		remove_filter( 'amp_pre_get_permalink', [ $this, 'return_example_url' ], 10 );
		$url = amp_get_permalink( $published_post );
		$this->assertContains( 'current_filter=amp_get_permalink', $url );
		remove_filter( 'amp_get_permalink', [ $this, 'return_example_url' ], 10 );

		// Now check with theme support added (in transitional mode).
		add_theme_support( AMP_Theme_Support::SLUG, [ 'template_dir' => './' ] );
		$this->assertStringEndsWith( '&amp', amp_get_permalink( $drafted_post ) );
		$this->assertStringEndsWith( '?amp', amp_get_permalink( $published_post ) );
		$this->assertStringEndsWith( '?amp', amp_get_permalink( $published_page ) );
		add_filter( 'amp_get_permalink', [ $this, 'return_example_url' ], 10, 2 );
		$this->assertNotContains( 'current_filter=amp_get_permalink', amp_get_permalink( $published_post ) ); // Filter does not apply.
		add_filter( 'amp_pre_get_permalink', [ $this, 'return_example_url' ], 10, 2 );
		$this->assertNotContains( 'current_filter=amp_pre_get_permalink', amp_get_permalink( $published_post ) ); // Filter does not apply.

		// Make sure that if permalink has anchor that it is persists.
		add_filter( 'post_link', $add_anchor_fragment );
		$this->assertStringEndsWith( '/?amp#anchor', amp_get_permalink( $published_post ) );
	}

	/**
	 * Test amp_get_permalink() with theme support transitional mode.
	 *
	 * @covers ::amp_get_permalink()
	 */
	public function test_amp_get_permalink_with_theme_support() {
		global $wp_rewrite;
		add_theme_support( AMP_Theme_Support::SLUG );

		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
		$wp_rewrite->use_trailing_slashes = true;
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();

		$post_id = self::factory()->post->create();
		$this->assertEquals( get_permalink( $post_id ), amp_get_permalink( $post_id ) );

		add_theme_support(
			AMP_Theme_Support::SLUG,
			[
				'template_dir' => 'amp',
			]
		);
	}

	/**
	 * Test amp_remove_endpoint.
	 *
	 * @covers ::amp_remove_endpoint()
	 */
	public function test_amp_remove_endpoint() {
		$this->assertEquals( 'https://example.com/foo/', amp_remove_endpoint( 'https://example.com/foo/?amp' ) );
		$this->assertEquals( 'https://example.com/foo/?#bar', amp_remove_endpoint( 'https://example.com/foo/?amp#bar' ) );
		$this->assertEquals( 'https://example.com/foo/', amp_remove_endpoint( 'https://example.com/foo/amp/' ) );
		$this->assertEquals( 'https://example.com/foo/?blaz', amp_remove_endpoint( 'https://example.com/foo/amp/?blaz' ) );
	}


	/**
	 * Test that hook is added.
	 *
	 * @covers ::amp_add_frontend_actions()
	 */
	public function test_amp_add_frontend_actions() {
		$this->assertFalse( has_action( 'wp_head', 'amp_add_amphtml_link' ) );
		amp_add_frontend_actions();
		$this->assertEquals( 10, has_action( 'wp_head', 'amp_add_amphtml_link' ) );
	}

	/**
	 * URLs to test amphtml link.
	 *
	 * @return array
	 */
	public function get_amphtml_urls() {
		$post_id = self::factory()->post->create();
		return [
			'is_home' => [
				home_url( '/' ),
				add_query_arg( amp_get_slug(), '', home_url( '/' ) ),
			],
			'is_404'  => [
				home_url( '/no-existe/' ),
				add_query_arg( amp_get_slug(), '', home_url( '/no-existe/' ) ),
			],
			'is_post' => [
				get_permalink( $post_id ),
				amp_get_permalink( $post_id ),
			],
		];
	}

	/**
	 * Adding link when theme support is not present.
	 *
	 * @dataProvider get_amphtml_urls
	 * @covers ::amp_add_amphtml_link()
	 * @param string $canonical_url Canonical URL.
	 * @param string $amphtml_url   The amphtml URL.
	 */
	public function test_amp_add_amphtml_link_reader_mode( $canonical_url, $amphtml_url ) {
		$this->assertFalse( current_theme_supports( AMP_Theme_Support::SLUG ) );
		$this->assertFalse( amp_is_canonical() );
		$get_amp_html_link = static function() {
			return get_echo( 'amp_add_amphtml_link' );
		};

		$assert_amphtml_link_present = function() use ( $amphtml_url, $get_amp_html_link ) {
			$this->assertEquals(
				sprintf( '<link rel="amphtml" href="%s">', esc_url( $amphtml_url ) ),
				$get_amp_html_link()
			);
		};

		$this->go_to( $canonical_url );
		$assert_amphtml_link_present();

		// Make sure adding the filter hides the amphtml link.
		add_filter( 'amp_frontend_show_canonical', '__return_false' );
		$this->assertEmpty( $get_amp_html_link() );
		remove_filter( 'amp_frontend_show_canonical', '__return_false' );
		$assert_amphtml_link_present();
	}

	/**
	 * Adding link when theme support in transitional mode.
	 *
	 * @dataProvider get_amphtml_urls
	 * @covers ::amp_add_amphtml_link()
	 * @param string $canonical_url Canonical URL.
	 * @param string $amphtml_url   The amphtml URL.
	 */
	public function test_amp_add_amphtml_link_transitional_mode( $canonical_url, $amphtml_url ) {
		AMP_Options_Manager::update_option( 'theme_support', AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		$this->accept_sanitization_by_default( false );
		AMP_Theme_Support::read_theme_support();
		AMP_Theme_Support::init();
		$this->assertTrue( current_theme_supports( AMP_Theme_Support::SLUG ) );
		$this->assertFalse( amp_is_canonical() );

		$get_amp_html_link = static function() {
			return get_echo( 'amp_add_amphtml_link' );
		};

		$assert_amphtml_link_present = function() use ( $amphtml_url, $get_amp_html_link ) {
			$this->assertEquals(
				sprintf( '<link rel="amphtml" href="%s">', esc_url( $amphtml_url ) ),
				$get_amp_html_link()
			);
		};

		$this->go_to( $canonical_url );
		$assert_amphtml_link_present();

		// Make sure adding the filter hides the amphtml link.
		add_filter( 'amp_frontend_show_canonical', '__return_false' );
		$this->assertEmpty( $get_amp_html_link() );
		remove_filter( 'amp_frontend_show_canonical', '__return_false' );
		$assert_amphtml_link_present();

		// Make sure that the link is not provided when there are validation errors associated with the URL.
		$invalid_url_post_id = AMP_Validated_URL_Post_Type::store_validation_errors(
			[
				[ 'code' => 'foo' ],
			],
			$canonical_url
		);
		$this->assertNotInstanceOf( 'WP_Error', $invalid_url_post_id );
		$this->assertContains( '<!--', $get_amp_html_link() );
		$this->assertTrue( AMP_Theme_Support::is_paired_available() );

		// Allow the URL when the errors are forcibly sanitized.
		add_filter( 'amp_validation_error_sanitized', '__return_true' );
		$this->assertTrue( AMP_Theme_Support::is_paired_available() );
		$assert_amphtml_link_present();
	}

	/**
	 * Test is_amp_endpoint() function.
	 *
	 * @covers ::is_amp_endpoint()
	 */
	public function test_is_amp_endpoint() {
		$this->go_to( get_permalink( self::factory()->post->create() ) );
		$this->assertFalse( is_amp_endpoint() );

		// Legacy query var.
		set_query_var( amp_get_slug(), '' );
		$this->assertTrue( is_amp_endpoint() );
		unset( $GLOBALS['wp_query']->query_vars[ amp_get_slug() ] );
		$this->assertFalse( is_amp_endpoint() );

		// Transitional theme support.
		add_theme_support( AMP_Theme_Support::SLUG, [ 'template_dir' => './' ] );
		$_GET['amp'] = '';
		$this->assertTrue( is_amp_endpoint() );
		unset( $_GET['amp'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->assertFalse( is_amp_endpoint() );
		remove_theme_support( AMP_Theme_Support::SLUG );

		// Standard theme support.
		add_theme_support( AMP_Theme_Support::SLUG );
		$this->assertTrue( is_amp_endpoint() );

		// Special core pages.
		$pages = [ 'wp-login.php', 'wp-signup.php', 'wp-activate.php' ];
		foreach ( $pages as $page ) {
			$GLOBALS['pagenow'] = $page;
			$this->assertFalse( is_amp_endpoint() );
		}
		unset( $GLOBALS['pagenow'] );

		/**
		 * Simulate a user unchecking almost all of the boxes in 'AMP Settings' > 'Supported Templates'.
		 * The user has chosen not to show them as AMP, so most URLs should not be AMP endpoints.
		 */
		AMP_Options_Manager::update_option( 'all_templates_supported', false );
		AMP_Options_Manager::update_option( 'supported_templates', [ 'is_author' ] );

		// A post shouldn't be an AMP endpoint, as it was unchecked in the UI via the options above.
		$this->go_to( self::factory()->post->create() );
		$this->assertFalse( is_amp_endpoint() );

		// The homepage shouldn't be an AMP endpoint, as it was also unchecked in the UI.
		$this->go_to( home_url( '/' ) );
		$this->assertFalse( is_amp_endpoint() );

		// When the user passes a flag to the WP-CLI command, it forces AMP validation no matter whether the user disabled AMP on any template.
		$_GET[ AMP_Validation_Manager::VALIDATE_QUERY_VAR ] = AMP_Validation_Manager::get_amp_validate_nonce();
		$this->assertTrue( is_amp_endpoint() );
	}

	/**
	 * Test is_amp_endpoint() function for post embeds and feeds.
	 *
	 * @covers ::is_amp_endpoint()
	 * global WP_Query $wp_the_query
	 */
	public function test_is_amp_endpoint_for_post_embeds_and_feeds() {
		add_theme_support( AMP_Theme_Support::SLUG );
		$post_id = self::factory()->post->create_and_get()->ID;

		$this->go_to( home_url( "?p=$post_id" ) );
		$this->assertTrue( is_amp_endpoint() );

		$this->go_to( home_url( "?p=$post_id&embed=1" ) );
		$this->assertFalse( is_amp_endpoint() );

		$this->go_to( home_url( '?feed=rss' ) );
		$this->assertFalse( is_amp_endpoint() );

		if ( class_exists( 'WP_Service_Workers' ) && defined( 'WP_Service_Workers::QUERY_VAR' ) && function_exists( 'pwa_add_error_template_query_var' ) ) {
			$this->go_to( home_url( "?p=$post_id" ) );
			global $wp_query;
			$wp_query->set( WP_Service_Workers::QUERY_VAR, WP_Service_Workers::SCOPE_FRONT );
			$this->assertFalse( is_amp_endpoint() );
		}
	}

	/**
	 * Test is_amp_endpoint() function before the parse_query action happens.
	 *
	 * @covers ::is_amp_endpoint()
	 * @expectedIncorrectUsage is_amp_endpoint
	 */
	public function test_is_amp_endpoint_before_parse_query_action() {
		global $wp_actions;
		unset( $wp_actions['parse_query'] );
		$this->assertFalse( is_amp_endpoint() );
	}

	/**
	 * Test is_amp_endpoint() function when there is no WP_Query.
	 *
	 * @covers ::is_amp_endpoint()
	 * @expectedIncorrectUsage is_feed
	 * @expectedIncorrectUsage is_embed
	 * @expectedIncorrectUsage is_amp_endpoint
	 */
	public function test_is_amp_endpoint_when_no_wp_query() {
		global $wp_query;
		$wp_query = null;
		$this->assertFalse( is_amp_endpoint() );
	}

	/**
	 * Test is_amp_endpoint() function before the wp action happens.
	 *
	 * @covers ::is_amp_endpoint()
	 * @expectedIncorrectUsage is_amp_endpoint
	 */
	public function test_is_amp_endpoint_before_wp_action() {
		add_theme_support( 'amp' );
		global $wp_actions;
		unset( $wp_actions['wp'] );
		$this->assertTrue( is_amp_endpoint() );
	}

	/**
	 * Test is_amp_endpoint() with a debugging query var to disable AMP.
	 *
	 * @covers ::is_amp_endpoint()
	 * @expectedIncorrectUsage is_amp_endpoint
	 */
	public function test_is_amp_endpoint_with_debug_query_var() {
		add_theme_support( AMP_Theme_Support::SLUG );

		// The query var is present, but the user doesn't have the right permission, so this should return true.
		$_GET[ AMP_Debug::AMP_FLAGS_QUERY_VAR ][ AMP_Debug::DISABLE_AMP_QUERY_VAR ] = '1';
		$this->assertTrue( is_amp_endpoint() );

		// Now that the user has the right permission, this should return false, meaning the query var forced AMP to be disabled.
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertFalse( is_amp_endpoint() );
	}

	/**
	 * Filter calls.
	 *
	 * @var array
	 */
	protected $last_filter_call;

	/**
	 * Capture filter call.
	 *
	 * @param mixed $value Value.
	 * @return mixed Value.
	 */
	public function capture_filter_call( $value ) {
		$this->last_filter_call = [
			'current_filter' => current_filter(),
			'args'           => func_get_args(),
		];
		return $value;
	}

	/**
	 * Test amp_get_asset_url.
	 *
	 * @covers ::amp_get_asset_url()
	 */
	public function test_amp_get_asset_url() {
		$this->assertStringEndsWith( '/assets/foo.jpg', amp_get_asset_url( 'foo.jpg' ) );
	}

	/**
	 * Test amp_get_boilerplate_code.
	 *
	 * @covers ::amp_get_boilerplate_code()
	 */
	public function test_amp_get_boilerplate_code() {
		$boilerplate_code = amp_get_boilerplate_code();
		$this->assertStringStartsWith( '<style amp-boilerplate>', $boilerplate_code );
		$this->assertContains( '<noscript><style amp-boilerplate>', $boilerplate_code );
	}

	/**
	 * Test amp_get_boilerplate_stylesheets.
	 *
	 * @covers ::amp_get_boilerplate_stylesheets()
	 */
	public function test_amp_get_boilerplate_stylesheets() {
		$stylesheets = amp_get_boilerplate_stylesheets();
		$this->assertInternalType( 'array', $stylesheets );
		$this->assertCount( 2, $stylesheets );
		$this->assertContains( 'body{-webkit-animation:-amp-start', $stylesheets[0] );
		$this->assertContains( 'body{-webkit-animation:none', $stylesheets[1] );
	}

	/**
	 * Test amp_add_generator_metadata.
	 *
	 * @covers ::amp_add_generator_metadata()
	 */
	public function test_amp_add_generator_metadata() {
		remove_theme_support( AMP_Theme_Support::SLUG );

		$get_generator_tag = static function() {
			return get_echo( 'amp_add_generator_metadata' );
		};

		$output = $get_generator_tag();
		$this->assertContains( 'mode=reader', $output );
		$this->assertContains( 'v' . AMP__VERSION, $output );
		$this->assertContains( 'experiences=website', $output );

		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		$output = $get_generator_tag();
		$this->assertContains( 'mode=transitional', $output );
		$this->assertContains( 'v' . AMP__VERSION, $output );

		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => false ] );
		$output = $get_generator_tag();
		$this->assertContains( 'mode=standard', $output );
		$this->assertContains( 'v' . AMP__VERSION, $output );

		AMP_Options_Manager::update_option( 'experiences', [ AMP_Options_Manager::STORIES_EXPERIENCE ] );
		$output = $get_generator_tag();
		$this->assertContains( 'mode=none', $output );
		$this->assertContains( 'experiences=stories', $output );

		AMP_Options_Manager::update_option( 'experiences', [ AMP_Options_Manager::WEBSITE_EXPERIENCE, AMP_Options_Manager::STORIES_EXPERIENCE ] );
		$output = $get_generator_tag();
		$this->assertContains( 'mode=standard', $output );
		$this->assertContains( 'experiences=website,stories', $output );
	}

	/**
	 * Test script registering.
	 *
	 * @covers ::amp_register_default_scripts()
	 * @covers ::amp_filter_script_loader_tag()
	 * @covers ::amp_render_scripts()
	 * @global WP_Scripts $wp_scripts
	 */
	public function test_script_registering() {
		global $wp_scripts;
		$wp_scripts = null;
		$this->assertEquals( 10, has_action( 'wp_default_scripts', 'amp_register_default_scripts' ) );
		$this->assertEquals( PHP_INT_MAX, has_action( 'script_loader_tag', 'amp_filter_script_loader_tag' ) );

		$this->assertTrue( wp_script_is( 'amp-runtime', 'registered' ) );
		$this->assertTrue( wp_script_is( 'amp-mustache', 'registered' ) );
		$this->assertTrue( wp_script_is( 'amp-list', 'registered' ) );
		$this->assertTrue( wp_script_is( 'amp-bind', 'registered' ) );

		wp_enqueue_script( 'amp-mathml' );
		wp_enqueue_script( 'amp-mustache' );
		$this->assertTrue( wp_script_is( 'amp-mathml', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'amp-mustache', 'enqueued' ) );

		// Try overriding URL.
		wp_scripts()->registered['amp-mustache']->src = 'https://cdn.ampproject.org/v0/amp-mustache-latest.js';

		$output = get_echo( 'wp_print_scripts' );

		$this->assertContains( '<script type=\'text/javascript\' src=\'https://cdn.ampproject.org/v0.js\' async></script>', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$this->assertContains( '<script type=\'text/javascript\' src=\'https://cdn.ampproject.org/v0/amp-mathml-0.1.js\' async custom-element="amp-mathml"></script>', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$this->assertContains( '<script type=\'text/javascript\' src=\'https://cdn.ampproject.org/v0/amp-mustache-latest.js\' async custom-template="amp-mustache"></script>', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

		// Try rendering via amp_render_scripts() instead of amp_render_scripts(), which is how component scripts get added normally.
		$output = amp_render_scripts(
			[
				'amp-mathml'    => true, // But already printed above.
				'amp-carousel'  => 'https://cdn.ampproject.org/v0/amp-mustache-2.0.js',
				'amp-accordion' => true,
			]
		);
		$this->assertNotContains( 'amp-mathml', $output, 'The amp-mathml component was already printed above.' );
		$this->assertContains( '<script type=\'text/javascript\' src=\'https://cdn.ampproject.org/v0/amp-mustache-2.0.js\' async custom-element="amp-carousel"></script>', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$this->assertContains( '<script type=\'text/javascript\' src=\'https://cdn.ampproject.org/v0/amp-accordion-0.1.js\' async custom-element="amp-accordion"></script>', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

		// Try some experimental component to ensure expected script attributes are added.
		wp_register_script( 'amp-foo', 'https://cdn.ampproject.org/v0/amp-foo-0.1.js', [ 'amp-runtime' ], null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter, WordPress.WP.EnqueuedResourceParameters.MissingVersion
		$output = get_echo( 'wp_print_scripts', [ 'amp-foo' ] );
		$this->assertContains( '<script type=\'text/javascript\' src=\'https://cdn.ampproject.org/v0/amp-foo-0.1.js\' async custom-element="amp-foo"></script>', $output ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
	}

	/**
	 * Test amp_get_content_embed_handlers().
	 *
	 * @covers ::amp_get_content_embed_handlers()
	 */
	public function test_amp_get_content_embed_handlers() {
		$post = self::factory()->post->create_and_get();
		add_filter( 'amp_content_embed_handlers', [ $this, 'capture_filter_call' ], 10, 2 );

		$this->last_filter_call = null;
		add_theme_support( AMP_Theme_Support::SLUG );
		$handlers = amp_get_content_embed_handlers();
		$this->assertArrayHasKey( 'AMP_SoundCloud_Embed_Handler', $handlers );
		$this->assertEquals( 'amp_content_embed_handlers', $this->last_filter_call['current_filter'] );
		$this->assertEquals( $handlers, $this->last_filter_call['args'][0] );
		$this->assertNull( $this->last_filter_call['args'][1] );

		$this->last_filter_call = null;
		remove_theme_support( AMP_Theme_Support::SLUG );
		$handlers = amp_get_content_embed_handlers( $post );
		$this->assertArrayHasKey( 'AMP_SoundCloud_Embed_Handler', $handlers );
		$this->assertEquals( 'amp_content_embed_handlers', $this->last_filter_call['current_filter'] );
		$this->assertEquals( $handlers, $this->last_filter_call['args'][0] );
		$this->assertEquals( $post, $this->last_filter_call['args'][1] );
	}

	/**
	 * Test amp_is_dev_mode().
	 *
	 * @covers ::amp_is_dev_mode()
	 */
	public function test_amp_is_dev_mode() {
		add_theme_support( 'amp' );

		$this->assertFalse( amp_is_dev_mode() );
		add_filter( 'amp_dev_mode_enabled', '__return_true' );
		$this->assertTrue( amp_is_dev_mode() );
		remove_filter( 'amp_dev_mode_enabled', '__return_true' );
		$this->assertFalse( amp_is_dev_mode() );

		// Test authenticated user with admin bar showing.
		add_filter( 'show_admin_bar', '__return_true' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertTrue( is_admin_bar_showing() );
		$this->assertTrue( is_user_logged_in() );
		$this->assertTrue( amp_is_dev_mode() );

		// Test unauthenticated user with admin bar forced.
		add_filter( 'show_admin_bar', '__return_true' );
		wp_set_current_user( 0 );
		$this->assertFalse( is_user_logged_in() );
		$this->assertTrue( is_admin_bar_showing() );
		$this->assertFalse( amp_is_dev_mode() );
	}

	/**
	 * Test deprecated $post param for amp_get_content_embed_handlers().
	 *
	 * @covers ::amp_get_content_embed_handlers()
	 */
	public function test_amp_get_content_embed_handlers_deprecated_param() {
		$post = self::factory()->post->create_and_get();
		$this->setExpectedDeprecated( 'amp_get_content_embed_handlers' );
		add_theme_support( AMP_Theme_Support::SLUG );
		amp_get_content_embed_handlers( $post );
	}

	/**
	 * Test amp_get_content_sanitizers().
	 *
	 * @covers ::amp_get_content_sanitizers()
	 */
	public function test_amp_get_content_sanitizers() {
		$post = self::factory()->post->create_and_get();
		add_filter( 'amp_content_sanitizers', [ $this, 'capture_filter_call' ], 10, 2 );

		$this->last_filter_call = null;
		add_theme_support( AMP_Theme_Support::SLUG );
		$handlers = amp_get_content_sanitizers();
		$this->assertArrayHasKey( 'AMP_Style_Sanitizer', $handlers );
		$this->assertEquals( 'amp_content_sanitizers', $this->last_filter_call['current_filter'] );
		$this->assertEquals( $handlers, $this->last_filter_call['args'][0] );
		$handler_classes = array_keys( $handlers );
		$this->assertNull( $this->last_filter_call['args'][1] );
		$this->assertEquals( 'AMP_Tag_And_Attribute_Sanitizer', end( $handler_classes ) );

		$this->last_filter_call = null;
		remove_theme_support( AMP_Theme_Support::SLUG );
		$handlers = amp_get_content_sanitizers( $post );
		$this->assertArrayHasKey( 'AMP_Style_Sanitizer', $handlers );
		$this->assertEquals( 'amp_content_sanitizers', $this->last_filter_call['current_filter'] );
		$this->assertEquals( $handlers, $this->last_filter_call['args'][0] );
		$this->assertEquals( $post, $this->last_filter_call['args'][1] );

		// Make sure the style and whitelist sanitizers are always at the end, even after filtering.
		add_filter(
			'amp_content_sanitizers',
			static function( $classes ) {
				$classes['Even_After_Whitelist_Sanitizer'] = [];
				return $classes;
			}
		);
		$ordered_sanitizers = array_keys( amp_get_content_sanitizers() );
		$this->assertEquals( 'Even_After_Whitelist_Sanitizer', $ordered_sanitizers[ count( $ordered_sanitizers ) - 3 ] );
		$this->assertEquals( 'AMP_Style_Sanitizer', $ordered_sanitizers[ count( $ordered_sanitizers ) - 2 ] );
		$this->assertEquals( 'AMP_Tag_And_Attribute_Sanitizer', $ordered_sanitizers[ count( $ordered_sanitizers ) - 1 ] );
	}

	/**
	 * Test amp_get_content_sanitizers().
	 *
	 * @covers ::amp_get_content_sanitizers()
	 */
	public function test_amp_get_content_sanitizers_with_dev_mode() {
		$element_xpaths = [ '//script[ @id = "hello-world" ]' ];
		add_filter(
			'amp_dev_mode_element_xpaths',
			function ( $xpaths ) use ( $element_xpaths ) {
				return array_merge( $xpaths, $element_xpaths );
			}
		);

		// Check that AMP_Dev_Mode_Sanitizer is not registered if not in dev mode.
		$sanitizers = amp_get_content_sanitizers();
		$this->assertFalse( amp_is_dev_mode() );
		$this->assertArrayNotHasKey( 'AMP_Dev_Mode_Sanitizer', $sanitizers );

		// Check that AMP_Dev_Mode_Sanitizer is registered once in dev mode, but not with admin bar showing yet.
		add_filter( 'amp_dev_mode_enabled', '__return_true' );
		$sanitizers = amp_get_content_sanitizers();
		$this->assertFalse( is_admin_bar_showing() );
		$this->assertTrue( amp_is_dev_mode() );
		$this->assertArrayHasKey( 'AMP_Dev_Mode_Sanitizer', $sanitizers );
		$this->assertEquals( 'AMP_Dev_Mode_Sanitizer', current( array_keys( $sanitizers ) ) );
		$this->assertEquals(
			compact( 'element_xpaths' ),
			$sanitizers['AMP_Dev_Mode_Sanitizer']
		);
		remove_filter( 'amp_dev_mode_enabled', '__return_true' );

		// Check that AMP_Dev_Mode_Sanitizer is registered once in dev mode, and now also with admin bar showing.
		add_filter( 'amp_dev_mode_enabled', '__return_true' );
		add_filter( 'show_admin_bar', '__return_true' );
		$sanitizers = amp_get_content_sanitizers();
		$this->assertTrue( is_admin_bar_showing() );
		$this->assertTrue( amp_is_dev_mode() );
		$this->assertArrayHasKey( 'AMP_Dev_Mode_Sanitizer', $sanitizers );
		$this->assertEqualSets(
			array_merge(
				$element_xpaths,
				[
					'//*[ @id = "wpadminbar" ]',
					'//*[ @id = "wpadminbar" ]//*',
					'//style[ @id = "admin-bar-inline-css" ]',
				]
			),
			$sanitizers['AMP_Dev_Mode_Sanitizer']['element_xpaths']
		);
	}

	/**
	 * Test deprecated $post param for amp_get_content_sanitizers().
	 *
	 * @covers ::amp_get_content_sanitizers()
	 */
	public function test_amp_get_content_sanitizers_deprecated_param() {
		$post = self::factory()->post->create_and_get();
		$this->setExpectedDeprecated( 'amp_get_content_sanitizers' );
		add_theme_support( AMP_Theme_Support::SLUG );
		amp_get_content_sanitizers( $post );
	}

	/**
	 * Test post_supports_amp().
	 *
	 * @covers ::post_supports_amp()
	 */
	public function test_post_supports_amp() {
		add_post_type_support( 'page', AMP_Post_Type_Support::SLUG );

		// Test disabled by default for page for posts and show on front.
		update_option( 'show_on_front', 'page' );
		$post = self::factory()->post->create_and_get( [ 'post_type' => 'page' ] );
		$this->assertTrue( post_supports_amp( $post ) );
		update_option( 'show_on_front', 'page' );
		$this->assertTrue( post_supports_amp( $post ) );
		update_option( 'page_for_posts', $post->ID );
		$this->assertFalse( post_supports_amp( $post ) );
		update_option( 'page_for_posts', '' );
		update_option( 'page_on_front', $post->ID );
		$this->assertFalse( post_supports_amp( $post ) );
		update_option( 'show_on_front', 'posts' );
		$this->assertTrue( post_supports_amp( $post ) );

		// Test disabled by default for page templates.
		update_post_meta( $post->ID, '_wp_page_template', 'foo.php' );
		$this->assertFalse( post_supports_amp( $post ) );

		// Reset.
		remove_post_type_support( 'page', AMP_Post_Type_Support::SLUG );
	}

	/**
	 * Test amp_get_post_image_metadata()
	 *
	 * @covers ::amp_get_post_image_metadata()
	 */
	public function test_amp_get_post_image_metadata() {
		$post_id = self::factory()->post->create();
		$this->assertFalse( amp_get_post_image_metadata( $post_id ) );

		$first_test_image = '/tmp/test-image.jpg';
		copy( DIR_TESTDATA . '/images/test-image.jpg', $first_test_image );
		$attachment_id = self::factory()->attachment->create_object(
			[
				'file'           => $first_test_image,
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $first_test_image ) );

		set_post_thumbnail( $post_id, $attachment_id );
		$metadata = amp_get_post_image_metadata( $post_id );
		$this->assertEquals( 'ImageObject', $metadata['@type'] );
		$this->assertEquals( 50, $metadata['width'] );
		$this->assertEquals( 50, $metadata['height'] );
		$this->assertStringEndsWith( 'test-image.jpg', $metadata['url'] );

		delete_post_thumbnail( $post_id );
		$this->assertFalse( amp_get_post_image_metadata( $post_id ) );
		wp_update_post(
			[
				'ID'          => $attachment_id,
				'post_parent' => $post_id,
			]
		);
		$metadata = amp_get_post_image_metadata( $post_id );
		$this->assertStringEndsWith( 'test-image.jpg', $metadata['url'] );

		// Test an 'attachment' post type.
		$attachment_src          = 'example/attachment.jpeg';
		$attachment_height       = 45;
		$attachment_width        = 600;
		$attachment_id           = self::factory()->attachment->create_object(
			[
				'file'           => $attachment_src,
				'post_mime_type' => 'image/jpeg',
			]
		);
		$expected_attachment_img = wp_get_attachment_image_src( $attachment_id, 'full', false );

		update_post_meta(
			$attachment_id,
			'_wp_attachment_metadata',
			[
				'height' => $attachment_height,
				'width'  => $attachment_width,
			]
		);
		$this->go_to( get_permalink( $attachment_id ) );

		$this->assertEquals(
			[
				'@type'  => 'ImageObject',
				'height' => $attachment_height,
				'url'    => $expected_attachment_img[0],
				'width'  => $attachment_width,
			],
			amp_get_post_image_metadata( $attachment_id )
		);

		// Test a video as an 'attachment' post type, which shouldn't have a schema.org image.
		$attachment_src = 'example/test-video.mpeg';
		$attachment_id  = self::factory()->attachment->create_object(
			[
				'file'           => $attachment_src,
				'post_mime_type' => 'video/mpeg',
			]
		);
		$this->go_to( get_permalink( $attachment_id ) );
		$this->assertFalse( amp_get_post_image_metadata( $attachment_id ) );
	}

	/**
	 * Insert site icon attachment.
	 *
	 * @param string $file Image file path.
	 * @return int|WP_Error Attachment ID or error.
	 */
	public function insert_site_icon_attachment( $file ) {
		$attachment_id = self::factory()->attachment->create_upload_object( $file, null );
		$cropped       = wp_crop_image( $attachment_id, 0, 0, 512, 512, 512, 512 );

		require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';
		$wp_site_icon = new WP_Site_Icon();

		/** This filter is documented in wp-admin/includes/class-custom-image-header.php */
		$cropped = apply_filters( 'wp_create_file_in_uploads', $cropped, $attachment_id ); // For replication.
		$object  = $wp_site_icon->create_attachment_object( $cropped, $attachment_id );
		unset( $object['ID'] );

		// Update the attachment.
		add_filter( 'intermediate_image_sizes_advanced', [ $wp_site_icon, 'additional_sizes' ] );
		$attachment_id = $wp_site_icon->insert_attachment( $object, $cropped );
		remove_filter( 'intermediate_image_sizes_advanced', [ $wp_site_icon, 'additional_sizes' ] );

		return $attachment_id;
	}

	/**
	 * Test amp_get_schemaorg_metadata() for non-story.
	 *
	 * @covers ::amp_get_schemaorg_metadata()
	 * @covers ::amp_get_publisher_logo()
	 */
	public function test_amp_get_schemaorg_metadata_non_story() {
		update_option( 'blogname', 'Foo' );
		$publisher_type     = 'Organization';
		$expected_publisher = [
			'@type' => $publisher_type,
			'name'  => 'Foo',
			'logo'  => amp_get_asset_url( 'images/amp-page-fallback-wordpress-publisher-logo.png' ),
		];

		$user_id = self::factory()->user->create(
			[
				'first_name' => 'John',
				'last_name'  => 'Smith',
			]
		);
		$page_id = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_title'  => 'Example Page',
				'post_author' => $user_id,
			]
		);
		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_title'  => 'Example Post',
				'post_author' => $user_id,
			]
		);

		$site_icon_attachment_id   = $this->insert_site_icon_attachment( DIR_TESTDATA . '/images/33772.jpg' );
		$custom_logo_attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', null );

		// Test non-singular, with no publisher logo.
		$this->go_to( home_url() );
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals( 'http://schema.org', $metadata['@context'] );
		$this->assertArrayNotHasKey( '@type', $metadata );
		$this->assertArrayHasKey( 'publisher', $metadata );
		$this->assertEquals( $expected_publisher, $metadata['publisher'] );
		$this->assertEquals( $metadata['publisher']['logo'], amp_get_publisher_logo() );

		// Set site icon which now should get used instead of default for publisher logo.
		update_option( 'site_icon', $site_icon_attachment_id );
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals(
			wp_get_attachment_image_url( $site_icon_attachment_id, 'full', false ),
			$metadata['publisher']['logo']
		);
		$this->assertEquals( wp_get_attachment_image_url( $site_icon_attachment_id, 'full', false ), amp_get_publisher_logo() );

		// Set custom logo which now should get used instead of default for publisher logo.
		set_theme_mod( 'custom_logo', $custom_logo_attachment_id );
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals(
			wp_get_attachment_image_url( $custom_logo_attachment_id, 'full', false ),
			$metadata['publisher']['logo']
		);
		$this->assertEquals( wp_get_attachment_image_url( $custom_logo_attachment_id, 'full', false ), amp_get_publisher_logo() );

		// Test amp_site_icon_url filter overrides previous.
		add_filter( 'amp_site_icon_url', [ __CLASS__, 'mock_site_icon' ] );
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals( self::MOCK_SITE_ICON, $metadata['publisher']['logo'] );
		$this->assertEquals( $metadata['publisher']['logo'], amp_get_publisher_logo() );

		// Clear out all customized icons.
		remove_filter( 'amp_site_icon_url', [ __CLASS__, 'mock_site_icon' ] );
		delete_option( 'site_icon' );
		remove_theme_mod( 'custom_logo' );

		// Test page.
		$this->go_to( get_permalink( $page_id ) );
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals( 'http://schema.org', $metadata['@context'] );
		$this->assertEquals( $expected_publisher, $metadata['publisher'] );
		$this->assertEquals( 'WebPage', $metadata['@type'] );
		$this->assertArrayHasKey( 'author', $metadata );
		$this->assertEquals( get_permalink( $page_id ), $metadata['mainEntityOfPage'] );
		$this->assertEquals( get_the_title( $page_id ), $metadata['headline'] );
		$this->assertArrayHasKey( 'datePublished', $metadata );
		$this->assertArrayHasKey( 'dateModified', $metadata );
		$this->assertEquals( $metadata['publisher']['logo'], amp_get_publisher_logo() );

		// Test post.
		$this->go_to( get_permalink( $post_id ) );
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals( 'http://schema.org', $metadata['@context'] );
		$this->assertEquals( $expected_publisher, $metadata['publisher'] );
		$this->assertEquals( 'BlogPosting', $metadata['@type'] );
		$this->assertEquals( get_permalink( $post_id ), $metadata['mainEntityOfPage'] );
		$this->assertEquals( get_the_title( $post_id ), $metadata['headline'] );
		$this->assertArrayHasKey( 'datePublished', $metadata );
		$this->assertArrayHasKey( 'dateModified', $metadata );
		$this->assertEquals(
			[
				'@type' => 'Person',
				'name'  => 'John Smith',
			],
			$metadata['author']
		);
		$this->assertEquals( $metadata['publisher']['logo'], amp_get_publisher_logo() );

		// Test override.
		$this->go_to( get_permalink( $post_id ) );
		$self = $this;
		add_filter(
			'amp_post_template_metadata',
			static function( $meta, $post ) use ( $self, $post_id ) {
				$self->assertEquals( $post_id, $post->ID );
				$meta['did_amp_post_template_metadata'] = true;
				$self->assertArrayNotHasKey( 'amp_schemaorg_metadata', $meta );
				return $meta;
			},
			10,
			2
		);
		add_filter(
			'amp_schemaorg_metadata',
			static function( $meta ) use ( $self ) {
				$meta['did_amp_schemaorg_metadata'] = true;
				$self->assertArrayHasKey( 'did_amp_post_template_metadata', $meta );
				$meta['author']['name'] = 'George';
				return $meta;
			}
		);

		$metadata = amp_get_schemaorg_metadata();
		$this->assertArrayHasKey( 'did_amp_post_template_metadata', $metadata );
		$this->assertArrayHasKey( 'did_amp_schemaorg_metadata', $metadata );
		$this->assertEquals( 'George', $metadata['author']['name'] );
		$this->assertEquals( $metadata['publisher']['logo'], amp_get_publisher_logo() );
	}

	/**
	 * Test amp_get_schemaorg_metadata() for a story.
	 *
	 * @covers ::amp_get_schemaorg_metadata()
	 * @covers ::amp_get_publisher_logo()
	 */
	public function test_amp_get_schemaorg_metadata_story() {
		if ( ! AMP_Story_Post_Type::has_required_block_capabilities() ) {
			$this->markTestSkipped( 'Lacking required block capabilities.' );
		}
		AMP_Options_Manager::update_option( 'experiences', [ AMP_Options_Manager::WEBSITE_EXPERIENCE, AMP_Options_Manager::STORIES_EXPERIENCE ] );
		AMP_Story_Post_Type::register();

		$site_icon_attachment_id   = $this->insert_site_icon_attachment( DIR_TESTDATA . '/images/33772.jpg' );
		$custom_logo_attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', null );
		$fallback_publisher_logo   = amp_get_asset_url( 'images/stories-editor/amp-story-fallback-wordpress-publisher-logo.png' );

		$post_id = self::factory()->post->create(
			[
				'post_type'  => AMP_Story_Post_Type::POST_TYPE_SLUG,
				'post_title' => 'Example Story',
			]
		);
		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( is_singular( AMP_Story_Post_Type::POST_TYPE_SLUG ) );

		// Test default fallback icon.
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals( $fallback_publisher_logo, $metadata['publisher']['logo'] );
		$this->assertEquals( $fallback_publisher_logo, amp_get_publisher_logo() );

		// Set site icon which now should get used instead of default for publisher logo, since it is square.
		update_option( 'site_icon', $site_icon_attachment_id );
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals(
			wp_get_attachment_image_url( $site_icon_attachment_id, 'full', false ),
			$metadata['publisher']['logo']
		);
		$this->assertEquals( wp_get_attachment_image_url( $site_icon_attachment_id, 'full', false ), amp_get_publisher_logo() );

		// Set custom logo which still shouldn't affect the icon since it is not square.
		set_theme_mod( 'custom_logo', $custom_logo_attachment_id );
		$this->assertEquals( wp_get_attachment_image_url( $site_icon_attachment_id, 'full', false ), amp_get_publisher_logo() );
		delete_option( 'site_icon' );
		$this->assertEquals( $fallback_publisher_logo, amp_get_publisher_logo() );

		// Set the custom logo to be the site icon, which will allow it to be used since it is square.
		set_theme_mod( 'custom_logo', $site_icon_attachment_id );
		$this->assertEquals( wp_get_attachment_image_url( $site_icon_attachment_id, 'full', false ), amp_get_publisher_logo() );

		// Check other meta.
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals( 'http://schema.org', $metadata['@context'] );
		$this->assertEquals( get_option( 'blogname' ), $metadata['publisher']['name'] );
		$this->assertEquals( 'BlogPosting', $metadata['@type'] );
		$this->assertEquals( get_permalink( $post_id ), $metadata['mainEntityOfPage'] );
		$this->assertEquals( get_the_title( $post_id ), $metadata['headline'] );
		$this->assertArrayHasKey( 'datePublished', $metadata );
		$this->assertArrayHasKey( 'dateModified', $metadata );
	}

	/**
	 * Test amp_add_admin_bar_view_link()
	 *
	 * @covers ::amp_add_admin_bar_view_link()
	 * @global WP_Query $wp_query
	 */
	public function test_amp_add_admin_bar_item() {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		global $wp_query; // Must be here after the go_to() call.

		// Check that canonical adds no link.
		add_theme_support( 'amp' );
		$admin_bar = new WP_Admin_Bar();
		amp_add_admin_bar_view_link( $admin_bar );
		$this->assertNull( $admin_bar->get_node( 'amp' ) );

		// Check that paired mode does add link.
		add_theme_support( 'amp', [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		$admin_bar = new WP_Admin_Bar();
		amp_add_admin_bar_view_link( $admin_bar );
		$item = $admin_bar->get_node( 'amp' );
		$this->assertInternalType( 'object', $item );
		$this->assertEquals( esc_url( amp_get_permalink( $post_id ) ), $item->href );

		// Confirm that link is added to non-AMP version.
		set_query_var( amp_get_slug(), '' );
		$this->assertTrue( is_amp_endpoint() );
		$admin_bar = new WP_Admin_Bar();
		amp_add_admin_bar_view_link( $admin_bar );
		$item = $admin_bar->get_node( 'amp' );
		$this->assertInternalType( 'object', $item );
		$this->assertEquals( esc_url( get_permalink( $post_id ) ), $item->href );
		unset( $wp_query->query_vars[ amp_get_slug() ] );
		$this->assertFalse( is_amp_endpoint() );

		// Confirm post opt-out works.
		add_filter( 'amp_skip_post', '__return_true' );
		$admin_bar = new WP_Admin_Bar();
		amp_add_admin_bar_view_link( $admin_bar );
		$this->assertNull( $admin_bar->get_node( 'amp' ) );
		remove_filter( 'amp_skip_post', '__return_true' );

		// Confirm Reader mode works.
		remove_theme_support( 'amp' );
		$admin_bar = new WP_Admin_Bar();
		amp_add_admin_bar_view_link( $admin_bar );
		$item = $admin_bar->get_node( 'amp' );
		$this->assertInternalType( 'object', $item );
		$this->assertEquals( esc_url( amp_get_permalink( $post_id ) ), $item->href );
	}

	/**
	 * Test amp_generate_script_hash().
	 *
	 * @covers \amp_generate_script_hash()
	 */
	public function test_amp_generate_script_hash() {
		$this->assertSame( 'sha384-nYSGte6layPrGqn7c1Om8wNCgSq5PU-56H0R6j1kTb7R3aLbWeM3ra0YF5xKFuI0', amp_generate_script_hash( 'document.body.textContent += \'Hello world!\';' ) );
		$this->assertSame( 'sha384-Qdwpb9Wpgg4BE21ukx8rwjbJGEdW2xjanFfsRNtmYQH69a_QeI0it1V8N23ZdsRX', amp_generate_script_hash( 'document.body.textContent = \'¡Hola mundo!\';' ) );
		$this->assertSame( 'sha384-_MAJ0_NC2k8jrjehfi-5LdQasBICZXvp4gOwOx0D3mIStvDCGvZDzcTfXLgMrLL1', amp_generate_script_hash( 'document.body.textContent = \'<Hi! & 👋>\';' ) );
	}

	/**
	 * Get a mock publisher logo URL, to test that the filter works as expected.
	 *
	 * @param string $site_icon The publisher logo in the schema.org data.
	 * @return string $site_icon The filtered publisher logo in the schema.org data.
	 */
	public static function mock_site_icon( $site_icon ) {
		unset( $site_icon );
		return self::MOCK_SITE_ICON;
	}
}

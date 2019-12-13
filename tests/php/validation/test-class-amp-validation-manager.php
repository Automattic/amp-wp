<?php
/**
 * Tests for AMP_Validation_Manager class.
 *
 * @package AMP
 */

// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

/**
 * Tests for AMP_Validation_Manager class.
 *
 * @covers AMP_Validation_Manager
 * @since 0.7
 */
class Test_AMP_Validation_Manager extends WP_UnitTestCase {

	use AMP_Test_HandleValidation;

	/**
	 * The name of the tested class.
	 *
	 * @var string
	 */
	const TESTED_CLASS = 'AMP_Validation_Manager';

	/**
	 * An instance of DOMElement to test.
	 *
	 * @var DOMElement
	 */
	public $node;

	/**
	 * A tag that the sanitizer should strip.
	 *
	 * @var string
	 */
	public $disallowed_tag = '<script async src="https://example.com/script"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

	/**
	 * The name of a tag that the sanitizer should strip.
	 *
	 * @var string
	 */
	public $disallowed_tag_name = 'script';

	/**
	 * The name of an attribute that the sanitizer should strip.
	 *
	 * @var string
	 */
	public $disallowed_attribute_name = 'onload';

	/**
	 * A mock plugin name that outputs invalid markup.
	 *
	 * @var string
	 */
	public $plugin_name = 'foo-bar';

	/**
	 * A valid image that sanitizers should not alter.
	 *
	 * @var string
	 */
	public $valid_amp_img = '<amp-img id="img-123" media="(min-width: 600x)" src="/assets/example.jpg" width="200" height="500" layout="responsive"></amp-img>';

	/**
	 * The name of the tag to test.
	 *
	 * @var string
	 */
	const TAG_NAME = 'img';

	/**
	 * Backed up $wp_registered_widgets.
	 *
	 * @var array
	 */
	protected $original_wp_registered_widgets;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 * @global $wp_registered_widgets
	 */
	public function setUp() {
		unset( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] );
		parent::setUp();
		$dom_document = new DOMDocument( '1.0', 'utf-8' );
		$this->node   = $dom_document->createElement( self::TAG_NAME );
		AMP_Validation_Manager::reset_validation_results();
		$this->original_wp_registered_widgets = $GLOBALS['wp_registered_widgets'];

		if ( class_exists( 'WP_Block_Type_Registry' ) ) {
			foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $block ) {
				if ( 'amp/' === substr( $block->name, 0, 4 ) ) {
					WP_Block_Type_Registry::get_instance()->unregister( $block->name );
				}
			}
		}
		delete_option( AMP_Options_Manager::OPTION_NAME ); // Make sure default reader mode option does not override theme support being added.
	}

	/**
	 * After a test method runs, reset any state in WordPress the test method might have changed.
	 */
	public function tearDown() {
		$GLOBALS['wp_registered_widgets'] = $this->original_wp_registered_widgets;
		remove_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::read_theme_support();
		$_REQUEST = [];
		$_GET     = [];
		unset( $GLOBALS['current_screen'] );
		AMP_Validation_Manager::$should_locate_sources = false;
		AMP_Validation_Manager::$hook_source_stack     = [];
		AMP_Validation_Manager::$validation_results    = [];
		AMP_Validation_Manager::reset_validation_results();
		parent::tearDown();
	}

	/**
	 * Test init.
	 *
	 * @covers AMP_Validation_Manager::init()
	 */
	public function test_init() {
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Validation_Manager::init();

		$this->assertTrue( post_type_exists( AMP_Validated_URL_Post_Type::POST_TYPE_SLUG ) );
		$this->assertTrue( taxonomy_exists( AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ) );

		$this->assertEquals( 10, has_action( 'save_post', self::TESTED_CLASS . '::handle_save_post_prompting_validation' ) );
		$this->assertEquals( 10, has_action( 'enqueue_block_editor_assets', self::TESTED_CLASS . '::enqueue_block_validation' ) );

		$this->assertEquals( 10, has_action( 'edit_form_top', self::TESTED_CLASS . '::print_edit_form_validation_status' ) );
		$this->assertEquals( 10, has_action( 'all_admin_notices', self::TESTED_CLASS . '::print_plugin_notice' ) );

		$this->assertEquals( 10, has_action( 'rest_api_init', self::TESTED_CLASS . '::add_rest_api_fields' ) );

		$this->assertContains( AMP_Validation_Manager::VALIDATION_ERRORS_QUERY_VAR, wp_removable_query_args() );
		$this->assertEquals( 101, has_action( 'admin_bar_menu', [ self::TESTED_CLASS, 'add_admin_bar_menu_items' ] ) );

		$this->assertFalse( has_action( 'wp', [ self::TESTED_CLASS, 'wrap_widget_callbacks' ] ) );

		// Make sure should_locate_sources arg is recognized.
		remove_all_filters( 'amp_validation_error_sanitized' );
		$this->accept_sanitization_by_default( false );
		AMP_Validation_Manager::init(
			[
				'should_locate_sources' => true,
			]
		);
		$this->assertEquals( 10, has_action( 'wp', [ self::TESTED_CLASS, 'wrap_widget_callbacks' ] ) );
	}

	/**
	 * Test init when theme support and stories support are not present.
	 *
	 * @covers AMP_Validation_Manager::init()
	 */
	public function test_init_without_theme_or_stories_support() {
		AMP_Options_Manager::update_option( 'experiences', [ AMP_Options_Manager::WEBSITE_EXPERIENCE ] );
		remove_theme_support( AMP_Theme_Support::SLUG );
		AMP_Validation_Manager::init();

		$this->assertFalse( has_action( 'save_post', [ 'AMP_Validation_Manager', 'handle_save_post_prompting_validation' ] ) );
	}

	/**
	 * Test init when theme support is absent but stories support is.
	 *
	 * @covers AMP_Validation_Manager::init()
	 */
	public function test_init_with_stories_and_without_theme_support() {
		if ( ! AMP_Story_Post_Type::has_required_block_capabilities() ) {
			$this->markTestSkipped( 'Environment does not support Stories.' );
		}
		AMP_Options_Manager::update_option( 'experiences', [ AMP_Options_Manager::STORIES_EXPERIENCE ] );
		remove_theme_support( AMP_Theme_Support::SLUG );
		AMP_Validation_Manager::init();

		$this->assertSame( 10, has_action( 'save_post', [ 'AMP_Validation_Manager', 'handle_save_post_prompting_validation' ] ) );
		$this->assertFalse( has_action( 'admin_bar_menu', [ self::TESTED_CLASS, 'add_admin_bar_menu_items' ] ) );
	}

	/**
	 * Test \AMP_Validation_Manager::post_supports_validation.
	 *
	 * @covers \AMP_Validation_Manager::post_supports_validation()
	 */
	public function test_post_supports_validation() {

		// Ensure that story posts can be validated even when theme support is absent.
		remove_theme_support( AMP_Theme_Support::SLUG );
		AMP_Options_Manager::update_option( 'experiences', [ AMP_Options_Manager::WEBSITE_EXPERIENCE, AMP_Options_Manager::STORIES_EXPERIENCE ] );
		AMP_Story_Post_Type::register();
		if ( post_type_exists( AMP_Story_Post_Type::POST_TYPE_SLUG ) ) {
			$post = $this->factory()->post->create( [ 'post_type' => AMP_Story_Post_Type::POST_TYPE_SLUG ] );
			$this->assertTrue( AMP_Validation_Manager::post_supports_validation( $post ) );
		}

		// Support absent if theme support is absent for regular posts.
		remove_theme_support( AMP_Theme_Support::SLUG );
		$this->assertFalse( AMP_Validation_Manager::post_supports_validation( $this->factory()->post->create() ) );

		// Ensure normal case of validating published post when theme support present.
		add_theme_support( AMP_Theme_Support::SLUG );
		$this->assertTrue( AMP_Validation_Manager::post_supports_validation( $this->factory()->post->create() ) );

		// Trashed posts are not validatable.
		$this->assertFalse( AMP_Validation_Manager::post_supports_validation( $this->factory()->post->create( [ 'post_status' => 'trash' ] ) ) );

		// An invalid post is not previewable.
		$this->assertFalse( AMP_Validation_Manager::post_supports_validation( 0 ) );

		// Ensure non-viewable posts do not support validation.
		register_post_type( 'not_viewable', [ 'publicly_queryable' => false ] );
		$post = $this->factory()->post->create( [ 'post_type' => 'not_viewable' ] );
		$this->assertFalse( AMP_Validation_Manager::post_supports_validation( $post ) );
	}

	/**
	 * Test add_validation_hooks.
	 *
	 * Excessive CSS validation errors have special case handling.
	 * @see https://github.com/ampproject/amp-wp/issues/2326
	 *
	 * @covers AMP_Validation_Manager::is_sanitization_auto_accepted()
	 */
	public function test_is_sanitization_auto_accepted() {
		$some_error = [
			'node_name'   => 'href',
			'parent_name' => 'a',
			'type'        => 'html_attribute_error',
			'code'        => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR,
		];
		$excessive_css_error = [
			'node_name' => 'style',
			'type'      => 'css',
			'code'      => AMP_Style_Sanitizer::STYLESHEET_TOO_LONG,
		];

		remove_theme_support( AMP_Theme_Support::SLUG );
		$this->accept_sanitization_by_default( false );
		$this->assertFalse( AMP_Validation_Manager::is_sanitization_auto_accepted() );
		$this->assertFalse( AMP_Validation_Manager::is_sanitization_auto_accepted( $some_error ) );
		$this->assertFalse( AMP_Validation_Manager::is_sanitization_auto_accepted( $excessive_css_error ) );

		remove_theme_support( AMP_Theme_Support::SLUG );
		$this->accept_sanitization_by_default( true );
		$this->assertTrue( AMP_Validation_Manager::is_sanitization_auto_accepted() );
		$this->assertTrue( AMP_Validation_Manager::is_sanitization_auto_accepted( $some_error ) );
		$this->assertTrue( AMP_Validation_Manager::is_sanitization_auto_accepted( $excessive_css_error ) );

		add_theme_support( AMP_Theme_Support::SLUG );
		$this->accept_sanitization_by_default( false );
		$this->assertFalse( AMP_Validation_Manager::is_sanitization_auto_accepted() );
		$this->assertFalse( AMP_Validation_Manager::is_sanitization_auto_accepted( $some_error ) );
		$this->assertFalse( AMP_Validation_Manager::is_sanitization_auto_accepted( $excessive_css_error ) );

		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		$this->accept_sanitization_by_default( false );
		$this->assertFalse( AMP_Validation_Manager::is_sanitization_auto_accepted() );
		$this->assertFalse( AMP_Validation_Manager::is_sanitization_auto_accepted( $some_error ) );
		$this->assertFalse( AMP_Validation_Manager::is_sanitization_auto_accepted( $excessive_css_error ) );

		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		$this->accept_sanitization_by_default( true );
		$this->assertTrue( AMP_Validation_Manager::is_sanitization_auto_accepted() );
		$this->assertTrue( AMP_Validation_Manager::is_sanitization_auto_accepted( $some_error ) );
		$this->assertTrue( AMP_Validation_Manager::is_sanitization_auto_accepted( $excessive_css_error ) );
	}

	/**
	 * Test add_admin_bar_menu_items.
	 *
	 * @covers AMP_Validation_Manager::add_admin_bar_menu_items()
	 */
	public function test_add_admin_bar_menu_items() {
		$this->accept_sanitization_by_default( false );

		// No admin bar item when user lacks capability.
		$this->go_to( home_url( '/' ) );
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		$this->assertFalse( is_admin() );
		$this->assertFalse( AMP_Validation_Manager::has_cap() );
		$admin_bar = new WP_Admin_Bar();
		AMP_Validation_Manager::add_admin_bar_menu_items( $admin_bar );
		$this->assertNull( $admin_bar->get_node( 'amp' ) );

		// No admin bar item when no template available.
		$this->go_to( home_url() );
		add_theme_support( AMP_Theme_Support::SLUG );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertTrue( AMP_Validation_Manager::has_cap() );
		add_filter( 'amp_supportable_templates', '__return_empty_array' );
		AMP_Options_Manager::update_option( 'all_templates_supported', false );
		$admin_bar = new WP_Admin_Bar();
		AMP_Validation_Manager::add_admin_bar_menu_items( $admin_bar );
		$this->assertNull( $admin_bar->get_node( 'amp' ) );
		remove_filter( 'amp_supportable_templates', '__return_empty_array' );
		AMP_Options_Manager::update_option( 'all_templates_supported', true );

		// Admin bar item available in AMP-first mode.
		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => false ] );
		$admin_bar = new WP_Admin_Bar();
		AMP_Validation_Manager::add_admin_bar_menu_items( $admin_bar );
		$node = $admin_bar->get_node( 'amp' );
		$this->assertInternalType( 'object', $node );
		$this->assertContains( 'action=amp_validate', $node->href );
		$this->assertNull( $admin_bar->get_node( 'amp-view' ) );
		$this->assertInternalType( 'object', $admin_bar->get_node( 'amp-validity' ) );
		$this->assertInternalType( 'object', $admin_bar->get_node( 'amp-debug' ) );

		// Admin bar item available in paired mode.
		add_filter( 'amp_dev_mode_enabled', '__return_true' );
		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		$admin_bar = new WP_Admin_Bar();
		AMP_Validation_Manager::add_admin_bar_menu_items( $admin_bar );
		$node = $admin_bar->get_node( 'amp' );
		$this->assertInternalType( 'object', $node );
		$this->assertInternalType( 'object', $admin_bar->get_node( 'amp-paired-browsing' ) );

		/*
		 * Admin bar item available in transitional mode.
		 * Transitional mode is available once template_dir is supplied.
		 */
		add_theme_support( AMP_Theme_Support::SLUG, [ 'template_dir' => 'amp' ] );
		$admin_bar = new WP_Admin_Bar();
		AMP_Validation_Manager::add_admin_bar_menu_items( $admin_bar );
		$node = $admin_bar->get_node( 'amp' );
		$this->assertInternalType( 'object', $node );
		$this->assertInternalType( 'object', $admin_bar->get_node( 'amp-paired-browsing' ) );

		// Admin bar item available in paired mode.
		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		$admin_bar = new WP_Admin_Bar();
		AMP_Validation_Manager::add_admin_bar_menu_items( $admin_bar );
		$node = $admin_bar->get_node( 'amp' );
		$this->assertInternalType( 'object', $node );
		$this->assertStringEndsWith( '?amp', $node->href );
		$this->assertInternalType( 'object', $admin_bar->get_node( 'amp-view' ) );
		$this->assertInternalType( 'object', $admin_bar->get_node( 'amp-validity' ) );
		$this->assertInternalType( 'object', $admin_bar->get_node( 'amp-debug' ) );

		// Admin bar item available in paired mode with validation errors.
		$_GET[ AMP_Validation_Manager::VALIDATION_ERRORS_QUERY_VAR ] = 3;
		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		$admin_bar = new WP_Admin_Bar();
		AMP_Validation_Manager::add_admin_bar_menu_items( $admin_bar );
		$node = $admin_bar->get_node( 'amp' );
		$this->assertInternalType( 'object', $node );
		$this->assertContains( 'action=amp_validate', $node->href );
		$this->assertNull( $admin_bar->get_node( 'amp-view' ) );
		$this->assertInternalType( 'object', $admin_bar->get_node( 'amp-validity' ) );
		$this->assertInternalType( 'object', $admin_bar->get_node( 'amp-debug' ) );
	}

	/**
	 * Test add_debugging_option_nodes.
	 *
	 * @covers AMP_Validation_Manager::add_debugging_option_nodes()
	 */
	public function test_add_debugging_option_nodes() {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );
		$wp_admin_bar = new AMP_Test_WP_Admin_Bar();
		$amp_url      = add_query_arg( 'amp', '', 'https://example.com' );
		AMP_Validation_Manager::add_debugging_option_nodes( $wp_admin_bar, $amp_url );

		$parent_node_id = 'amp-debug';
		$this->assertTrue(
			in_array(
				[
					'parent' => 'amp',
					'id'     => $parent_node_id,
					'title'  => 'Debugging options',
				],
				$wp_admin_bar->nodes,
				true
			)
		);

		$query_var = AMP_Debug::DISABLE_AMP_QUERY_VAR;
		$this->assertTrue(
			in_array(
				[
					'parent' => $parent_node_id,
					'id'     => $query_var,
					'title'  => '<span class="amp-debug-option" style="font-family:Roboto,Oxygen-Sans,sans-serif;font-size:17px">☐</span> Disable AMP',
					'href'   => add_query_arg( AMP_Debug::AMP_FLAGS_QUERY_VAR, [ $query_var => 'true' ], $amp_url ),
				],
				$wp_admin_bar->nodes,
				true
			)
		);
	}

	/**
	 * Test get_debugging_option_title.
	 *
	 * @covers AMP_Validation_Manager::get_debugging_option_title()
	 */
	public function test_get_debugging_option_title() {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );
		$title     = 'Foo title';
		$query_var = AMP_Debug::PREVENT_REDIRECT_TO_NON_AMP_QUERY_VAR;

		// The query var is not present, so this should return the same title it's passed.
		$expected_title_unchecked = '<span class="amp-debug-option" style="font-family:Roboto,Oxygen-Sans,sans-serif;font-size:17px">☐</span> ' . $title;
		$this->assertEquals( $expected_title_unchecked, AMP_Validation_Manager::get_debugging_option_title( $title, $query_var ) );

		// The query var is present, but not under the top-level 'amp_flags' query var, so this should again return the same title it's passed.
		$_GET[ $query_var ] = '1';
		$this->assertEquals( $expected_title_unchecked, AMP_Validation_Manager::get_debugging_option_title( $title, $query_var ) );
		$_GET = [];

		// The query var is now present, so this should have the emoji in the returned title.
		$_GET[ AMP_Debug::AMP_FLAGS_QUERY_VAR ][ $query_var ] = '1';
		$this->assertEquals( '<span class="amp-debug-option" style="font-family:Roboto,Oxygen-Sans,sans-serif;font-size:17px">☑</span> ' . $title, AMP_Validation_Manager::get_debugging_option_title( $title, $query_var ) );
	}

	/**
	 * Test add_validation_error_sourcing.
	 *
	 * @covers AMP_Validation_Manager::add_validation_error_sourcing()
	 */
	public function test_add_validation_error_sourcing() {
		AMP_Validation_Manager::add_validation_error_sourcing();
		$this->assertEmpty( AMP_Validation_Manager::$validation_error_status_overrides );
		$this->assertEquals( PHP_INT_MAX, has_filter( 'the_content', [ self::TESTED_CLASS, 'decorate_filter_source' ] ) );
		$this->assertEquals( PHP_INT_MAX, has_filter( 'the_excerpt', [ self::TESTED_CLASS, 'decorate_filter_source' ] ) );
		$this->assertEquals( PHP_INT_MAX, has_action( 'do_shortcode_tag', [ self::TESTED_CLASS, 'decorate_shortcode_source' ] ) );

		// Test overrides.
		$validation_error_term_1 = AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term( [ 'test' => 1 ] );
		$validation_error_term_2 = AMP_Validation_Error_Taxonomy::prepare_validation_error_taxonomy_term( [ 'test' => 2 ] );
		$_REQUEST[ AMP_Validation_Manager::VALIDATE_QUERY_VAR ] = AMP_Validation_Manager::get_amp_validate_nonce();
		$_REQUEST[ AMP_Validation_Manager::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR ] = [
			$validation_error_term_1['slug'] => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
			$validation_error_term_2['slug'] => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_REJECTED_STATUS,
		];
		AMP_Validation_Manager::add_validation_error_sourcing();
		$this->assertCount( 2, AMP_Validation_Manager::$validation_error_status_overrides );
	}

	/**
	 * Test add_validation_error_sourcing with Gutenberg active.
	 *
	 * @covers AMP_Validation_Manager::add_validation_error_sourcing()
	 */
	public function test_add_validation_error_sourcing_gutenberg() {
		if ( ! function_exists( 'do_blocks' ) ) {
			$this->markTestSkipped( 'Gutenberg not active.' );
		}
		if ( version_compare( get_bloginfo( 'version' ), '4.9', '<' ) ) {
			$this->markTestSkipped( 'The WP version is less than 4.9, so Gutenberg did not init.' );
		}

		$priority = has_filter( 'the_content', 'do_blocks' );
		$this->assertNotFalse( $priority );
		AMP_Validation_Manager::add_validation_error_sourcing();
		$this->assertEquals( $priority - 1, has_filter( 'the_content', [ self::TESTED_CLASS, 'add_block_source_comments' ] ) );
	}

	/**
	 * Tests handle_save_post_prompting_validation.
	 *
	 * @covers AMP_Validation_Manager::handle_save_post_prompting_validation()
	 * @covers AMP_Validation_Manager::validate_queued_posts_on_frontend()
	 */
	public function test_handle_save_post_prompting_validation_and_validate_queued_posts_on_frontend() {
		add_theme_support( AMP_Theme_Support::SLUG );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$GLOBALS['pagenow']        = 'post.php';

		register_post_type( 'secret', [ 'public' => false ] );
		$secret           = self::factory()->post->create_and_get( [ 'post_type' => 'secret' ] );
		$_POST['post_ID'] = $secret->ID;
		AMP_Validation_Manager::handle_save_post_prompting_validation( $secret->ID );
		$this->assertFalse( has_action( 'shutdown', [ 'AMP_Validation_Manager', 'validate_queued_posts_on_frontend' ] ) );
		$this->assertEmpty( AMP_Validation_Manager::validate_queued_posts_on_frontend() );

		$auto_draft       = self::factory()->post->create_and_get( [ 'post_status' => 'auto-draft' ] );
		$_POST['post_ID'] = $auto_draft->ID;
		AMP_Validation_Manager::handle_save_post_prompting_validation( $auto_draft->ID );
		$this->assertFalse( has_action( 'shutdown', [ 'AMP_Validation_Manager', 'validate_queued_posts_on_frontend' ] ) );
		$this->assertEmpty( AMP_Validation_Manager::validate_queued_posts_on_frontend() );

		// Testing without $_POST context.
		$post = self::factory()->post->create_and_get( [ 'post_type' => 'post' ] );
		AMP_Validation_Manager::handle_save_post_prompting_validation( $post->ID );
		$this->assertFalse( has_action( 'shutdown', [ 'AMP_Validation_Manager', 'validate_queued_posts_on_frontend' ] ) );

		// Test success.
		$post = self::factory()->post->create_and_get( [ 'post_type' => 'post' ] );
		$_POST['post_ID'] = $post->ID;
		AMP_Validation_Manager::handle_save_post_prompting_validation( $post->ID );
		$this->assertEquals( 10, has_action( 'shutdown', [ 'AMP_Validation_Manager', 'validate_queued_posts_on_frontend' ] ) );

		add_filter(
			'pre_http_request',
			static function() {
				return new WP_Error( 'http_request_made' );
			}
		);
		$results = AMP_Validation_Manager::validate_queued_posts_on_frontend();
		$this->assertArrayHasKey( $post->ID, $results );
		$this->assertInstanceOf( 'WP_Error', $results[ $post->ID ] );

		unset( $GLOBALS['pagenow'] );
	}

	/**
	 * Test add_rest_api_fields.
	 *
	 * @covers AMP_Validation_Manager::add_rest_api_fields()
	 */
	public function test_add_rest_api_fields() {

		// Test in a transitional context.
		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		AMP_Theme_Support::read_theme_support();
		AMP_Validation_Manager::add_rest_api_fields();
		$post_types_non_canonical = array_intersect(
			get_post_types_by_support( 'amp' ),
			get_post_types(
				[
					'show_in_rest' => true,
				]
			)
		);
		$this->assert_rest_api_field_present( $post_types_non_canonical );

		// Test in a AMP-first (canonical) context.
		add_theme_support( AMP_Theme_Support::SLUG );
		AMP_Theme_Support::read_theme_support();
		AMP_Validation_Manager::add_rest_api_fields();
		$post_types_canonical = get_post_types_by_support( 'editor' );
		$this->assert_rest_api_field_present( $post_types_canonical );
	}

	/**
	 * Asserts that the post types have the additional REST field.
	 *
	 * @param array $post_types The post types that should have the REST field.
	 */
	protected function assert_rest_api_field_present( $post_types ) {
		foreach ( $post_types as $post_type ) {
			$field = $GLOBALS['wp_rest_additional_fields'][ $post_type ][ AMP_Validation_Manager::VALIDITY_REST_FIELD_NAME ];
			$this->assertEquals(
				$field['schema'],
				[
					'description' => 'AMP validity status',
					'type'        => 'object',
				]
			);
			$this->assertEquals( $field['get_callback'], [ self::TESTED_CLASS, 'get_amp_validity_rest_field' ] );
		}
	}

	/**
	 * Test get_amp_validity_rest_field.
	 *
	 * @covers AMP_Validation_Manager::get_amp_validity_rest_field()
	 * @covers AMP_Validation_Manager::validate_url()
	 */
	public function test_get_amp_validity_rest_field() {
		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		$this->accept_sanitization_by_default( false );
		AMP_Validated_URL_Post_Type::register();
		AMP_Validation_Error_Taxonomy::register();

		$id = self::factory()->post->create();
		$this->assertNull(
			AMP_Validation_Manager::get_amp_validity_rest_field(
				compact( 'id' ),
				'',
				new WP_REST_Request( 'GET' )
			)
		);

		// Create an error custom post for the ID, so this will return the errors in the field.
		$errors = [
			[
				'code' => 'test',
			],
		];
		$this->create_custom_post(
			$errors,
			amp_get_permalink( $id )
		);

		// Make sure capability check is honored.
		$this->assertNull(
			AMP_Validation_Manager::get_amp_validity_rest_field(
				compact( 'id' ),
				'',
				new WP_REST_Request( 'GET' )
			)
		);

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		// GET request.
		$field = AMP_Validation_Manager::get_amp_validity_rest_field(
			compact( 'id' ),
			'',
			new WP_REST_Request( 'GET' )
		);
		$this->assertArrayHasKey( 'results', $field );
		$this->assertArrayHasKey( 'review_link', $field );
		$this->assertEquals(
			$field['results'],
			array_map(
				static function ( $error ) {
					return [
						'sanitized'   => false,
						'error'       => $error,
						'status'      => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
						'term_status' => AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_REJECTED_STATUS,
						'forced'      => false,
					];
				},
				$errors
			)
		);

		// PUT request.
		add_filter(
			'pre_http_request',
			static function() {
				return [
					'body'     => '<html><body></body><!--AMP_VALIDATION:{"results":[]}--></html>',
					'response' => [
						'code'    => 200,
						'message' => 'ok',
					],
				];
			}
		);
		$field = AMP_Validation_Manager::get_amp_validity_rest_field(
			compact( 'id' ),
			'',
			new WP_REST_Request( 'PUT' )
		);
		$this->assertArrayHasKey( 'results', $field );
		$this->assertArrayHasKey( 'review_link', $field );
		$this->assertEmpty( $field['results'] );
	}

	/**
	 * Test has_cap.
	 *
	 * @covers AMP_Validation_Manager::has_cap()
	 */
	public function test_has_cap() {
		wp_set_current_user(
			self::factory()->user->create(
				[
					'role' => 'subscriber',
				]
			)
		);
		$this->assertFalse( AMP_Validation_Manager::has_cap() );

		$this->set_capability();
		$this->assertTrue( AMP_Validation_Manager::has_cap() );
	}

	/**
	 * Test add_validation_error.
	 *
	 * @covers AMP_Validation_Manager::add_validation_error()
	 */
	public function test_add_validation_error_track_removed() {
		AMP_Validation_Manager::$should_locate_sources = true;
		$this->assertEmpty( AMP_Validation_Manager::$validation_results );

		$that = $this;
		$node = $this->node;
		add_filter(
			'amp_validation_error',
			static function( $error, $context ) use ( $node, $that ) {
				$error['filtered'] = true;
				$that->assertEquals( AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG, $error['code'] );
				$that->assertSame( $node, $context['node'] );
				return $error;
			},
			10,
			2
		);

		AMP_Validation_Manager::add_validation_error(
			[
				'node_name'       => $this->node->nodeName,
				'code'            => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG,
				'node_attributes' => [],
			],
			[
				'node' => $this->node,
			]
		);

		$this->assertCount( 1, AMP_Validation_Manager::$validation_results );
		$this->assertEquals(
			[
				'node_name'       => 'img',
				'sources'         => [],
				'code'            => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG,
				'node_attributes' => [],
				'filtered'        => true,
			],
			AMP_Validation_Manager::$validation_results[0]['error']
		);
	}

	/**
	 * Test was_node_removed.
	 *
	 * @covers AMP_Validation_Manager::add_validation_error()
	 */
	public function test_add_validation_error_was_node_removed() {
		$this->assertEmpty( AMP_Validation_Manager::$validation_results );
		AMP_Validation_Manager::add_validation_error(
			[
				'node' => $this->node,
			]
		);
		$this->assertNotEmpty( AMP_Validation_Manager::$validation_results );
	}

	/**
	 * Test reset_validation_results.
	 *
	 * @covers AMP_Validation_Manager::reset_validation_results()
	 */
	public function test_reset_validation_results() {
		AMP_Validation_Manager::add_validation_error(
			[
				'code' => 'test',
			]
		);
		AMP_Validation_Manager::reset_validation_results();
		$this->assertEquals( [], AMP_Validation_Manager::$validation_results );
	}

	/**
	 * Test print_edit_form_validation_status
	 *
	 * @covers AMP_Validation_Manager::print_edit_form_validation_status()
	 */
	public function test_print_edit_form_validation_status() {
		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		$this->accept_sanitization_by_default( false );

		AMP_Validated_URL_Post_Type::register();
		AMP_Validation_Error_Taxonomy::register();
		$this->set_capability();
		$post   = self::factory()->post->create_and_get();
		$output = get_echo( [ 'AMP_Validation_Manager', 'print_edit_form_validation_status' ], [ $post ] );

		$this->assertNotContains( 'notice notice-warning', $output );

		$validation_errors = [
			[
				'code'            => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG,
				'node_name'       => $this->disallowed_tag_name,
				'parent_name'     => 'div',
				'node_attributes' => [],
				'sources'         => [
					[
						'type' => 'plugin',
						'name' => $this->plugin_name,
					],
				],
			],
		];

		AMP_Validated_URL_Post_Type::store_validation_errors( $validation_errors, get_permalink( $post->ID ) );
		$output = get_echo( [ 'AMP_Validation_Manager', 'print_edit_form_validation_status' ], [ $post ] );

		// When sanitization is accepted by default.
		$this->accept_sanitization_by_default( true );
		$expected_notice_non_accepted_errors = 'There is content which fails AMP validation. You will have to remove the invalid markup (or allow the plugin to remove it) to serve AMP.';
		$this->assertContains( 'notice notice-warning', $output );
		$this->assertContains( '<code>script</code>', $output );
		$this->assertContains( $expected_notice_non_accepted_errors, $output );

		// When auto-accepting validation errors, if there are unaccepted validation errors, there should be a notice because this will block serving an AMP document.
		add_theme_support( AMP_Theme_Support::SLUG );
		$output = get_echo( [ 'AMP_Validation_Manager', 'print_edit_form_validation_status' ], [ $post ] );
		$this->assertContains( 'There is content which fails AMP validation. You will have to remove the invalid markup (or allow the plugin to remove it) to serve AMP.', $output );

		/*
		 * When there are 'Rejected' or 'New Rejected' errors, there should be a message that explains that this will serve a non-AMP URL.
		 * This simulates sanitization being accepted by default, but it having been false when the validation errors were stored,
		 * as there are errors with 'New Rejected' status.
		 */
		$this->accept_sanitization_by_default( true );
		add_theme_support( AMP_Theme_Support::SLUG, [ AMP_Theme_Support::PAIRED_FLAG => true ] );
		AMP_Validated_URL_Post_Type::store_validation_errors( $validation_errors, get_permalink( $post->ID ) );
		$this->accept_sanitization_by_default( false );
		$output = get_echo( [ 'AMP_Validation_Manager', 'print_edit_form_validation_status' ], [ $post ] );
		$this->assertContains( $expected_notice_non_accepted_errors, $output );
	}

	/**
	 * Test source comments.
	 *
	 * @covers AMP_Validation_Manager::locate_sources()
	 * @covers AMP_Validation_Manager::parse_source_comment()
	 * @covers AMP_Validation_Manager::get_source_comment()
	 * @covers AMP_Validation_Manager::remove_source_comments()
	 */
	public function test_source_comments() {
		$source1 = [
			'type'      => 'plugin',
			'name'      => 'foo',
			'shortcode' => 'test',
			'function'  => __FUNCTION__,
		];
		$source2 = [
			'type'     => 'theme',
			'name'     => 'bar',
			'function' => __FUNCTION__,
			'hook'     => 'something',
		];

		$dom = AMP_DOM_Utils::get_dom_from_content(
			implode(
				'',
				[
					AMP_Validation_Manager::get_source_comment( $source1, true ),
					AMP_Validation_Manager::get_source_comment( $source2, true ),
					'<b id="test">Test</b>',
					AMP_Validation_Manager::get_source_comment( $source2, false ),
					AMP_Validation_Manager::get_source_comment( $source1, false ),
				]
			)
		);

		/**
		 * Comments.
		 *
		 * @var DOMComment[] $comments
		 */
		$comments = [];
		$xpath    = new DOMXPath( $dom );
		foreach ( $xpath->query( '//comment()' ) as $comment ) {
			$comments[] = $comment;
		}
		$this->assertCount( 4, $comments );

		$sources = AMP_Validation_Manager::locate_sources( $dom->getElementById( 'test' ) );
		$this->assertInternalType( 'array', $sources );
		$this->assertCount( 2, $sources );

		$this->assertEquals( $source1, $sources[0] );
		$parsed_comment = AMP_Validation_Manager::parse_source_comment( $comments[0] );
		$this->assertEquals( $source1, $parsed_comment['source'] );
		$this->assertFalse( $parsed_comment['closing'] );
		$parsed_comment = AMP_Validation_Manager::parse_source_comment( $comments[3] );
		$this->assertEquals( $source1, $parsed_comment['source'] );
		$this->assertTrue( $parsed_comment['closing'] );

		$this->assertEquals( $source2, $sources[1] );
		$parsed_comment = AMP_Validation_Manager::parse_source_comment( $comments[1] );
		$this->assertEquals( $source2, $parsed_comment['source'] );
		$this->assertFalse( $parsed_comment['closing'] );
		$parsed_comment = AMP_Validation_Manager::parse_source_comment( $comments[2] );
		$this->assertEquals( $source2, $parsed_comment['source'] );
		$this->assertTrue( $parsed_comment['closing'] );

		AMP_Validation_Manager::remove_source_comments( $dom );
		$this->assertEquals( 0, $xpath->query( '//comment()' )->length );
	}

	/**
	 * Get block data.
	 *
	 * @see Test_AMP_Validation_Utils::test_add_block_source_comments()
	 * @return array
	 */
	public function get_block_data() {
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			$this->markTestSkipped( 'Gutenberg not active.' );
		}
		$latest_posts_block = WP_Block_Type_Registry::get_instance()->get_registered( 'core/latest-posts' );

		$reflection_function = new ReflectionFunction( $latest_posts_block->render_callback );
		$is_gutenberg = false !== strpos( $reflection_function->getFileName(), 'gutenberg' );

		return [
			'paragraph'    => [
				"<!-- wp:paragraph -->\n<p>Latest posts:</p>\n<!-- /wp:paragraph -->",
				"<!--amp-source-stack {\"block_name\":\"core\/paragraph\",\"post_id\":{{post_id}},\"block_content_index\":0}-->\n<p>Latest posts:</p>\n<!--/amp-source-stack {\"block_name\":\"core\/paragraph\",\"post_id\":{{post_id}}}-->",
				[
					'element' => 'p',
					'blocks'  => [ 'core/paragraph' ],
				],
			],
			'latest_posts' => [
				'<!-- wp:latest-posts {"postsToShow":1,"categories":""} /-->',
				sprintf(
					'<!--amp-source-stack {"block_name":"core\/latest-posts","post_id":{{post_id}},"block_content_index":0,"block_attrs":{"postsToShow":1,"categories":""},"type":"%1$s","name":"%2$s","file":%4$s,"line":%5$s,"function":"%3$s"}--><ul class="wp-block-latest-posts wp-block-latest-posts__list"><li><a href="{{url}}">{{title}}</a></li></ul><!--/amp-source-stack {"block_name":"core\/latest-posts","post_id":{{post_id}},"block_attrs":{"postsToShow":1,"categories":""},"type":"%1$s","name":"%2$s","file":%4$s,"line":%5$s,"function":"%3$s"}-->',
					$is_gutenberg ? 'plugin' : 'core',
					$is_gutenberg ? 'gutenberg' : 'wp-includes',
					$latest_posts_block->render_callback,
					wp_json_encode(
						$is_gutenberg
						? preg_replace( ':.*gutenberg/:', '', $reflection_function->getFileName() )
						: preg_replace( ':.*wp-includes/:', '', $reflection_function->getFileName() )
					),
					$reflection_function->getStartLine()
				),
				[
					'element' => 'ul',
					'blocks'  => [ 'core/latest-posts' ],
				],
			],
			'columns'      => [
				"<!-- wp:columns -->\n<div class=\"wp-block-columns has-2-columns\">\n    <!-- wp:quote {\"layout\":\"column-1\",\"foo\":{\"bar\":1}} -->\n    <blockquote class=\"wp-block-quote layout-column-1\">\n        <p>A quotation!</p><cite>Famous</cite></blockquote>\n    <!-- /wp:quote -->\n\n    <!-- wp:html {\"layout\":\"column-2\"} -->\n    <div class=\"layout-column-2\">\n        <script>\n            document.write('Not allowed!');\n        </script>\n    </div>\n    <!-- /wp:html -->\n</div>\n<!-- /wp:columns -->",
				"<!--amp-source-stack {\"block_name\":\"core\/columns\",\"post_id\":{{post_id}},\"block_content_index\":0}-->\n<div class=\"wp-block-columns has-2-columns\">\n\n\n\n<!--amp-source-stack {\"block_name\":\"core\/quote\",\"post_id\":{{post_id}},\"block_content_index\":1,\"block_attrs\":{\"layout\":\"column-1\",\"foo\":{\"bar\":1}}}-->\n    <blockquote class=\"wp-block-quote layout-column-1\">\n        <p>A quotation!</p><cite>Famous</cite></blockquote>\n    <!--/amp-source-stack {\"block_name\":\"core\/quote\",\"post_id\":{{post_id}}}--><!--amp-source-stack {\"block_name\":\"core\/html\",\"post_id\":{{post_id}},\"block_content_index\":2,\"block_attrs\":{\"layout\":\"column-2\"}}-->\n    <div class=\"layout-column-2\">\n        <script>\n            document.write('Not allowed!');\n        </script>\n    </div>\n    <!--/amp-source-stack {\"block_name\":\"core\/html\",\"post_id\":{{post_id}}}--></div>\n<!--/amp-source-stack {\"block_name\":\"core\/columns\",\"post_id\":{{post_id}}}-->",
				[
					'element' => 'blockquote',
					'blocks'  => [
						'core/columns',
						'core/quote',
					],
				],
			],
		];
	}

	/**
	 * Test add_block_source_comments.
	 *
	 * @param string $content  Content.
	 * @param string $expected Expected content.
	 * @param array  $query    Query.
	 *
	 * @dataProvider get_block_data
	 * @covers       AMP_Validation_Manager::add_block_source_comments()
	 * @covers       AMP_Validation_Manager::handle_block_source_comment_replacement()
	 */
	public function test_add_block_source_comments( $content, $expected, $query ) {
		if ( ! function_exists( 'do_blocks' ) ) {
			$this->markTestSkipped( 'Gutenberg not active.' );
		}
		if ( version_compare( get_bloginfo( 'version' ), '4.9', '<' ) ) {
			$this->markTestSkipped( 'The WP version is less than 4.9, so Gutenberg did not init.' );
		}

		global $post;
		$post = self::factory()->post->create_and_get();
		$this->assertInstanceOf( 'WP_Post', get_post() );

		$rendered_block = do_blocks( AMP_Validation_Manager::add_block_source_comments( $content ) );

		$expected = str_replace(
			[
				'{{post_id}}',
				'{{title}}',
				'{{url}}',
			],
			[
				$post->ID,
				get_the_title( $post ),
				get_permalink( $post ),
			],
			$expected
		);

		// Temporary patch to support running unit tests in Gutenberg<5.7.0.
		$rendered_block = str_replace(
			'class="wp-block-latest-posts"',
			'class="wp-block-latest-posts wp-block-latest-posts__list"',
			$rendered_block
		);

		$this->assertEquals(
			preg_replace( '/(?<=>)\s+(?=<)/', '', str_replace( '%d', $post->ID, $expected ) ),
			preg_replace( '/(?<=>)\s+(?=<)/', '', $rendered_block )
		);

		$dom = AMP_DOM_Utils::get_dom_from_content( $rendered_block );
		$el  = $dom->getElementsByTagName( $query['element'] )->item( 0 );

		$this->assertEquals(
			$query['blocks'],
			wp_list_pluck( AMP_Validation_Manager::locate_sources( $el ), 'block_name' )
		);
	}

	/**
	 * Test wrap_widget_callbacks.
	 *
	 * @covers AMP_Validation_Manager::wrap_widget_callbacks()
	 */
	public function test_wrap_widget_callbacks() {
		global $wp_registered_widgets, $_wp_sidebars_widgets;

		$search_widget_id = 'search-2';
		$this->assertArrayHasKey( $search_widget_id, $wp_registered_widgets );
		$this->assertInternalType( 'array', $wp_registered_widgets[ $search_widget_id ]['callback'] );
		$this->assertInstanceOf( 'WP_Widget_Search', $wp_registered_widgets[ $search_widget_id ]['callback'][0] );
		$this->assertSame( 'display_callback', $wp_registered_widgets[ $search_widget_id ]['callback'][1] );
		$archives_widget_id = 'archives-2';
		$this->assertArrayHasKey( $archives_widget_id, $wp_registered_widgets );
		$this->assertInternalType( 'array', $wp_registered_widgets[ $archives_widget_id ]['callback'] );
		$wp_registered_widgets[ $archives_widget_id ]['callback'][0] = new AMP_Widget_Archives();

		AMP_Validation_Manager::wrap_widget_callbacks();
		$this->assertInstanceOf( 'AMP_Validation_Callback_Wrapper', $wp_registered_widgets[ $search_widget_id ]['callback'] );
		$this->assertInstanceOf( 'AMP_Validation_Callback_Wrapper', $wp_registered_widgets[ $archives_widget_id ]['callback'] );
		$this->assertInstanceOf( 'WP_Widget', $wp_registered_widgets[ $search_widget_id ]['callback'][0] );
		$this->assertInstanceOf( 'WP_Widget', $wp_registered_widgets[ $archives_widget_id ]['callback'][0] );
		$this->assertSame( 'display_callback', $wp_registered_widgets[ $search_widget_id ]['callback'][1] );
		$this->assertSame( 'display_callback', $wp_registered_widgets[ $archives_widget_id ]['callback'][1] );

		$sidebar_id = 'amp-sidebar';
		register_sidebar(
			[
				'id'           => $sidebar_id,
				'after_widget' => '</li>',
			]
		);

		// Test core search widget.
		$_wp_sidebars_widgets[ $sidebar_id ] = [ $search_widget_id ];
		AMP_Theme_Support::start_output_buffering();
		dynamic_sidebar( $sidebar_id );
		$output     = ob_get_clean();
		$reflection = new ReflectionMethod( 'WP_Widget_Search', 'widget' );
		$this->assertStringStartsWith(
			sprintf(
				'<!--amp-source-stack {"type":"core","name":"wp-includes","file":%1$s,"line":%2$d,"function":%3$s,"widget_id":%4$s}--><li id=%4$s',
				wp_json_encode( preg_replace( ':^.*wp-includes/:', '', $reflection->getFileName() ) ),
				$reflection->getStartLine(),
				wp_json_encode( $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName() ),
				wp_json_encode( $search_widget_id )
			),
			$output
		);
		$this->assertRegExp(
			'#</li><!--/amp-source-stack {.*$#s',
			$output
		);

		// Test plugin-extended archives widget.
		$_wp_sidebars_widgets[ $sidebar_id ] = [ $archives_widget_id ];
		AMP_Theme_Support::start_output_buffering();
		dynamic_sidebar( $sidebar_id );
		$output     = ob_get_clean();
		$reflection = new ReflectionMethod( 'AMP_Widget_Archives', 'widget' );
		$this->assertStringStartsWith(
			sprintf(
				'<!--amp-source-stack {"type":"plugin","name":"amp","file":%1$s,"line":%2$d,"function":%3$s,"widget_id":%4$s}--><li id=%4$s',
				wp_json_encode( preg_replace( ':^.*(?=includes/):', '', $reflection->getFileName() ) ),
				$reflection->getStartLine(),
				wp_json_encode( $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName() ),
				wp_json_encode( $archives_widget_id )
			),
			$output
		);
	}

	/**
	 * Test wrap_hook_callbacks.
	 *
	 * @covers AMP_Validation_Manager::wrap_hook_callbacks()
	 */
	public function test_callback_wrappers() {
		global $post;
		$that = $this;
		$post = self::factory()->post->create_and_get();
		$this->set_capability();
		$action_no_tag_output     = 'foo_action';
		$action_core_output       = 'core_action';
		$action_no_output         = 'bar_action_no_output';
		$action_function_callback = 'baz_action_function';
		$action_no_argument       = 'test_action_no_argument';
		$action_one_argument      = 'baz_action_one_argument';
		$action_two_arguments     = 'example_action_two_arguments';
		$notice                   = 'Example notice';

		AMP_Validation_Manager::add_validation_error_sourcing();

		add_action( $action_function_callback, '_amp_show_load_errors_admin_notice' );
		add_action( $action_no_argument, [ $this, 'output_div' ] );
		add_action( $action_one_argument, [ $this, 'output_notice' ] );
		add_action( $action_two_arguments, [ $this, 'output_message' ], 10, 2 );
		add_action( $action_no_output, [ $this, 'get_string' ], 10, 2 );
		add_action( $action_no_tag_output, 'the_ID' );
		add_action( $action_core_output, 'edit_post_link' );
		add_action( $action_no_output, '__return_false' );

		// All of the callback functions remain as-is. They will only change for a given hook at the 'all' action.
		$this->assertEquals( 10, has_action( $action_no_tag_output, 'the_ID' ) );
		$this->assertEquals( 10, has_action( $action_no_output, [ $this, 'get_string' ] ) );
		$this->assertEquals( 10, has_action( $action_no_argument, [ $this, 'output_div' ] ) );
		$this->assertEquals( 10, has_action( $action_one_argument, [ $this, 'output_notice' ] ) );
		$this->assertEquals( 10, has_action( $action_two_arguments, [ $this, 'output_message' ] ) );

		AMP_Theme_Support::start_output_buffering();
		do_action( $action_function_callback );
		$output = ob_get_clean();
		$this->assertContains( '<div class="notice notice-error">', $output );
		$this->assertContains( '<!--amp-source-stack {"type":"plugin","name":"amp"', $output );
		$this->assertContains( '<!--/amp-source-stack {"type":"plugin","name":"amp"', $output );

		AMP_Theme_Support::start_output_buffering();
		do_action( $action_no_argument );
		$output = ob_get_clean();
		$this->assertContains( '<div></div>', $output );
		$this->assertContains( '<!--amp-source-stack {"type":"plugin","name":"amp"', $output );
		$this->assertContains( '<!--/amp-source-stack {"type":"plugin","name":"amp"', $output );

		AMP_Theme_Support::start_output_buffering();
		do_action( $action_one_argument, $notice );
		$output = ob_get_clean();
		$this->assertContains( $notice, $output );
		$this->assertContains( sprintf( '<div class="notice notice-warning"><p>%s</p></div>', $notice ), $output );
		$this->assertContains( '<!--amp-source-stack {"type":"plugin","name":"amp"', $output );
		$this->assertContains( '<!--/amp-source-stack {"type":"plugin","name":"amp"', $output );

		AMP_Theme_Support::start_output_buffering();
		do_action( $action_two_arguments, $notice, get_the_ID() );
		$output = ob_get_clean();
		AMP_Theme_Support::start_output_buffering();
		self::output_message( $notice, get_the_ID() );
		$expected_output = ob_get_clean();
		$this->assertContains( $expected_output, $output );
		$this->assertContains( '<!--amp-source-stack {"type":"plugin","name":"amp"', $output );
		$this->assertContains( '<!--/amp-source-stack {"type":"plugin","name":"amp"', $output );

		// This action's callback doesn't output any HTML tags, so no HTML should be present.
		AMP_Theme_Support::start_output_buffering();
		do_action( $action_no_tag_output );
		$output = ob_get_clean();
		$this->assertNotContains( '<!--amp-source-stack ', $output );
		$this->assertNotContains( '<!--/amp-source-stack ', $output );

		// This action's callback comes from core.
		AMP_Theme_Support::start_output_buffering();
		do_action( $action_core_output );
		$output = ob_get_clean();
		$this->assertContains( '<!--amp-source-stack {"type":"core","name":"wp-includes"', $output );
		$this->assertContains( '<!--/amp-source-stack {"type":"core","name":"wp-includes"', $output );

		// This action's callback doesn't echo any markup, so it shouldn't be wrapped in comments.
		AMP_Theme_Support::start_output_buffering();
		do_action( $action_no_output );
		$output = ob_get_clean();
		$this->assertNotContains( '<!--amp-source-stack ', $output );
		$this->assertNotContains( '<!--/amp-source-stack ', $output );

		$handle_inner_action = null;
		$handle_outer_action = null;

		// Ensure that nested actions output the expected stack, and that has_action() works as expected in spite of the function wrapping.
		$handle_outer_action = static function() use ( $that, &$handle_outer_action, &$handle_inner_action ) {
			$that->assertEquals( 10, has_action( 'outer_action', $handle_outer_action ) );
			$that->assertEquals( 10, has_action( 'inner_action', $handle_inner_action ) );
			do_action( 'inner_action' );
			$that->assertEquals( 10, has_action( 'inner_action', $handle_inner_action ) );
		};
		$outer_reflection    = new ReflectionFunction( $handle_outer_action );
		$handle_inner_action = static function() use ( $that, &$handle_outer_action, &$handle_inner_action ) {
			$that->assertEquals( 10, has_action( 'outer_action', $handle_outer_action ) );
			$that->assertEquals( 10, has_action( 'inner_action', $handle_inner_action ) );
			echo '<b>Hello</b>';
		};
		$inner_reflection    = new ReflectionFunction( $handle_inner_action );
		add_action( 'outer_action', $handle_outer_action );
		add_action( 'inner_action', $handle_inner_action );
		AMP_Theme_Support::start_output_buffering();
		do_action( 'outer_action' );
		$output = ob_get_clean();
		$this->assertEquals(
			implode(
				'',
				[
					sprintf( '<!--amp-source-stack {"type":"plugin","name":"amp","file":"tests\/php\/validation\/test-class-amp-validation-manager.php","line":%d,"function":"{closure}","hook":"outer_action","priority":10}-->', $outer_reflection->getStartLine() ),
					sprintf( '<!--amp-source-stack {"type":"plugin","name":"amp","file":"tests\/php\/validation\/test-class-amp-validation-manager.php","line":%d,"function":"{closure}","hook":"inner_action","priority":10}-->', $inner_reflection->getStartLine() ),
					'<b>Hello</b>',
					sprintf( '<!--/amp-source-stack {"type":"plugin","name":"amp","file":"tests\/php\/validation\/test-class-amp-validation-manager.php","line":%d,"function":"{closure}","hook":"inner_action","priority":10}-->', $inner_reflection->getStartLine() ),
					sprintf( '<!--/amp-source-stack {"type":"plugin","name":"amp","file":"tests\/php\/validation\/test-class-amp-validation-manager.php","line":%d,"function":"{closure}","hook":"outer_action","priority":10}-->', $outer_reflection->getStartLine() ),
				]
			),
			$output
		);
	}

	/**
	 * Test has_parameters_passed_by_reference.
	 *
	 * @covers AMP_Validation_Manager::has_parameters_passed_by_reference()
	 */
	public function test_has_parameters_passed_by_reference() {
		$tested_method = new ReflectionMethod( 'AMP_Validation_Manager', 'has_parameters_passed_by_reference' );
		$tested_method->setAccessible( true );
		$reflection_by_reference = new ReflectionFunction( 'wp_default_styles' );
		$reflection_by_value     = new ReflectionFunction( 'get_bloginfo' );

		$this->assertTrue( $tested_method->invoke( null, $reflection_by_reference ) );
		$this->assertFalse( $tested_method->invoke( null, $reflection_by_value ) );
	}

	/**
	 * Test decorate_shortcode_source.
	 *
	 * @covers AMP_Validation_Manager::add_validation_error_sourcing()
	 * @covers AMP_Validation_Manager::decorate_shortcode_source()
	 * @covers AMP_Validation_Manager::decorate_filter_source()
	 * @throws Exception If assertion fails.
	 */
	public function test_decorate_shortcode_and_filter_source() {
		AMP_Validation_Manager::add_validation_error_sourcing();
		$shortcode_fallback = static function() {
			return '<b>test</b>';
		};
		add_shortcode(
			'test',
			$shortcode_fallback
		);

		$filtered_content = apply_filters( 'the_content', 'before[test]after' );

		if ( version_compare( get_bloginfo( 'version' ), '5.0', '>=' ) && has_filter( 'the_content', 'do_blocks' ) ) {
			$sources = [
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'WP_Embed::run_shortcode',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'WP_Embed::autoembed',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'do_blocks',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'wptexturize',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'wpautop',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'shortcode_unautop',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'prepend_attachment',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'wp_make_content_images_responsive',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'capital_P_dangit',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'do_shortcode',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'convert_smilies',
				],
			];
		} elseif ( has_filter( 'the_content', 'do_blocks' ) ) {
			$sources = [
				[
					'type'     => 'plugin',
					'name'     => 'gutenberg',
					'function' => 'do_blocks',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'WP_Embed::run_shortcode',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'WP_Embed::autoembed',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'wptexturize',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'wpautop',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'shortcode_unautop',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'prepend_attachment',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'wp_make_content_images_responsive',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'capital_P_dangit',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'do_shortcode',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'convert_smilies',
				],
			];
		} else {
			$sources = [
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'WP_Embed::run_shortcode',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'WP_Embed::autoembed',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'wptexturize',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'wpautop',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'shortcode_unautop',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'prepend_attachment',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'wp_make_content_images_responsive',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'capital_P_dangit',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'do_shortcode',
				],
				[
					'type'     => 'core',
					'name'     => 'wp-includes',
					'function' => 'convert_smilies',
				],
			];
		}

		foreach ( $sources as &$source ) {
			$function = $source['function'];
			unset( $source['function'] );
			if ( strpos( $function, '::' ) ) {
				$method     = explode( '::', $function, 2 );
				$reflection = new ReflectionMethod( $method[0], $method[1] );
			} else {
				$reflection = new ReflectionFunction( $function );
			}

			if ( 'core' === $source['type'] ) {
				$source['file'] = preg_replace( ':.*/' . preg_quote( $source['name'], ':' ) . '/:', '', $reflection->getFileName() );
			} elseif ( 'plugin' === $source['type'] ) {
				$source['file'] = preg_replace( ':.*/' . preg_quote( basename( WP_PLUGIN_DIR ), ':' ) . '/[^/]+?/:', '', $reflection->getFileName() );
			} else {
				throw new Exception( 'Unexpected type: ' . $source['type'] );
			}
			$source['line']     = $reflection->getStartLine();
			$source['function'] = $function;
		}

		$source_json = wp_json_encode(
			[
				'hook'    => 'the_content',
				'filter'  => true,
				'sources' => $sources,
			]
		);

		$shortcode_fallback_reflection = new ReflectionFunction( $shortcode_fallback );

		$expected_content = implode(
			'',
			[
				"<!--amp-source-stack $source_json-->",
				sprintf(
					'<p>before<!--amp-source-stack {"type":"plugin","name":"amp","file":%1$s,"line":%2$s,"function":"{closure}","shortcode":"test"}--><b>test</b><!--/amp-source-stack {"type":"plugin","name":"amp","file":%1$s,"line":%2$s,"function":"{closure}","shortcode":"test"}-->after</p>' . "\n",
					wp_json_encode( substr( $shortcode_fallback_reflection->getFileName(), strlen( AMP__DIR__ ) + 1 ) ),
					$shortcode_fallback_reflection->getStartLine()
				),
				"<!--/amp-source-stack $source_json-->",
			]
		);

		$this->assertEquals(
			preg_split( '/(?=<)/', $expected_content ),
			preg_split( '/(?=<)/', $filtered_content )
		);
	}

	/**
	 * Test get_source
	 *
	 * @covers AMP_Validation_Manager::get_source()
	 */
	public function test_get_source() {
		$source = AMP_Validation_Manager::get_source( 'amp_after_setup_theme' );
		$this->assertEquals( 'amp', $source['name'] );
		$this->assertEquals( 'plugin', $source['type'] );

		$source = AMP_Validation_Manager::get_source( 'the_content' );
		$this->assertEquals( 'wp-includes', $source['name'] );
		$this->assertEquals( 'core', $source['type'] );
	}

	/**
	 * Test can_output_buffer.
	 *
	 * Note that this method cannot currently be fully tested because
	 * it relies on `AMP_Theme_Support::start_output_buffering()` having
	 * been called, and this method starts an output buffer with a callback
	 *
	 * @covers AMP_Validation_Manager::can_output_buffer()
	 */
	public function test_can_output_buffer() {
		$this->assertFalse( AMP_Validation_Manager::can_output_buffer() );
	}

	/**
	 * Test wrapped_callback for filters.
	 *
	 * @covers AMP_Validation_Manager::wrapped_callback()
	 */
	public function test_filter_wrapped_callback() {
		$test_string     = 'Filter-amended Value';
		$filter_callback = [
			'function'      => static function ( $value ) use ( $test_string ) {
				return $value . $test_string;
			},
			'accepted_args' => 1,
			'source'        => [
				'type' => 'plugin',
				'name' => 'amp',
				'hook' => 'foo',
			],
		];

		$value = 'Some Value';
		apply_filters( 'foo', $value );
		$wrapped_callback = AMP_Validation_Manager::wrapped_callback( $filter_callback );
		$this->assertInstanceOf( '\\AMP_Validation_Callback_Wrapper', $wrapped_callback );
		AMP_Theme_Support::start_output_buffering();
		$filtered_value = $wrapped_callback( $value );
		$output = ob_get_clean();
		$this->assertEquals( $value . $test_string, $filtered_value );
		$this->assertEmpty( $output, 'Expected no output since no action triggered.' );
	}

	/**
	 * Test wrapped_callback for actions.
	 *
	 * @covers AMP_Validation_Manager::wrapped_callback()
	 */
	public function test_action_wrapped_callback() {
		$test_string     = "<b class='\nfoo\nbar\n'>Cool!</b>";
		$action_callback = [
			'function'      => static function() use ( $test_string ) {
				echo $test_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			},
			'accepted_args' => 1,
			'source'        => [
				'type' => 'plugin',
				'name' => 'amp',
				'hook' => 'bar',
			],
		];

		do_action( 'bar' ); // So that output buffering will be done.
		$wrapped_callback = AMP_Validation_Manager::wrapped_callback( $action_callback );
		$this->assertInstanceOf( '\\AMP_Validation_Callback_Wrapper', $wrapped_callback );
		AMP_Theme_Support::start_output_buffering();
		$wrapped_callback();
		$output = ob_get_clean();

		$this->assertInstanceOf( '\\AMP_Validation_Callback_Wrapper', $wrapped_callback );
		$this->assertContains( $test_string, $output );
		$this->assertContains( '<!--amp-source-stack {"type":"plugin","name":"amp","hook":"bar"}', $output );
		$this->assertContains( '<!--/amp-source-stack {"type":"plugin","name":"amp","hook":"bar"}', $output );

		$action_callback = [
			'function'      => [ $this, 'get_string' ],
			'accepted_args' => 0,
			'source'        => [
				'type' => 'plugin',
				'name' => 'amp',
				'hook' => 'bar',
			],
		];

		$wrapped_callback = AMP_Validation_Manager::wrapped_callback( $action_callback );
		$this->assertInstanceOf( '\\AMP_Validation_Callback_Wrapper', $wrapped_callback );
		AMP_Theme_Support::start_output_buffering();
		$result = $wrapped_callback();
		$output = ob_get_clean();
		$this->assertInstanceOf( '\\AMP_Validation_Callback_Wrapper', $wrapped_callback );
		$this->assertEquals( '', $output );
		$this->assertEquals( $this->get_string(), $result );
		unset( $GLOBALS['post'] );
	}

	/**
	 * Test wrap_buffer_with_source_comments.
	 *
	 * @covers \AMP_Validation_Manager::wrap_buffer_with_source_comments()
	 */
	public function test_wrap_buffer_with_source_comments() {
		$initial_content = '<html><body></body></html>';
		$this->assertEquals( $initial_content, AMP_Validation_Manager::wrap_buffer_with_source_comments( $initial_content ) );

		$earliest_source = [ 'plugin' => 'foo' ];
		$latest_source   = [ 'theme' => 'bar' ];

		// Doesn't use array_merge, as wrap_buffer_with_source_comments() accesses this array with indices like 1 or 2.
		AMP_Validation_Manager::$hook_source_stack[] = $earliest_source;
		AMP_Validation_Manager::$hook_source_stack[] = $latest_source;

		$wrapped_content = AMP_Validation_Manager::wrap_buffer_with_source_comments( $initial_content );
		$this->assertContains( $initial_content, $wrapped_content );
		$this->assertContains( '<!--amp-source-stack', $wrapped_content );
		$this->assertContains( '<!--/amp-source-stack', $wrapped_content );
		$this->assertContains( wp_json_encode( $latest_source ), $wrapped_content );
		$this->assertNotContains( wp_json_encode( $earliest_source ), $wrapped_content );
	}

	/**
	 * Test get_amp_validate_nonce.
	 *
	 * @covers \AMP_Validation_Manager::get_amp_validate_nonce()
	 */
	public function test_get_amp_validate_nonce() {
		$nonce = AMP_Validation_Manager::get_amp_validate_nonce();
		$this->assertInternalType( 'string', $nonce );
		$this->assertEquals( 10, strlen( $nonce ) );
	}

	/**
	 * Test should_validate_response.
	 *
	 * @covers AMP_Validation_Manager::should_validate_response()
	 */
	public function test_should_validate_response() {
		global $post;
		$post = self::factory()->post->create();
		$this->assertFalse( AMP_Validation_Manager::should_validate_response() );
		$_GET[ AMP_Validation_Manager::VALIDATE_QUERY_VAR ] = 1;
		$this->assertFalse( AMP_Validation_Manager::should_validate_response() );
		$this->set_capability();
		$this->assertTrue( AMP_Validation_Manager::should_validate_response() );
	}

	/**
	 * Test finalize_validation.
	 *
	 * @covers \AMP_Validation_Manager::finalize_validation()
	 */
	public function test_finalize_validation() {
		global $post, $show_admin_bar;

		$show_admin_bar = true;
		$dom            = new DOMDocument( '1.0' );
		$html           = '<html><body><div id="wp-admin-bar-amp-validity"><a href="#"></a></div><span id="amp-admin-bar-item-status-icon"></span><br></body></html>';
		$dom->loadHTML( $html );

		$validation_results                         = [
			[
				'error'       => [ 'code' => AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR ],
				'sanitized'   => false,
				'slug'        => '98765b4',
				'term_status' => 0,
			],
		];
		AMP_Validation_Manager::$validation_results = $validation_results;

		// should_validate_response() will be false, so finalize_validation() won't append the _RESULTS comment.
		AMP_Validation_Manager::finalize_validation( $dom );
		$this->assertNotContains( 'AMP_VALIDATION:{', $dom->documentElement->lastChild->nodeValue );

		// Ensure that should_validate_response() is true, so finalize_validation() will append the AMP_VALIDATION comment.
		$post = self::factory()->post->create();
		$_GET[ AMP_Validation_Manager::VALIDATE_QUERY_VAR ] = 1;
		$this->set_capability();
		AMP_Validation_Manager::finalize_validation( $dom );
		$this->assertTrue( (bool) preg_match( '#AMP_VALIDATION:({.+})#s', $dom->documentElement->lastChild->nodeValue, $matches ) );
		$returned_valudation = json_decode( $matches[1], true );
		$this->assertEquals( $validation_results, $returned_valudation['results'] );
	}

	/**
	 * Test filter_sanitizer_args
	 *
	 * @covers AMP_Validation_Manager::filter_sanitizer_args()
	 */
	public function test_filter_sanitizer_args() {
		global $post;
		$post       = self::factory()->post->create_and_get();
		$sanitizers = [
			'AMP_Img_Sanitizer'      => [],
			'AMP_Form_Sanitizer'     => [],
			'AMP_Comments_Sanitizer' => [],
		];

		$expected_callback   = self::TESTED_CLASS . '::add_validation_error';
		$filtered_sanitizers = AMP_Validation_Manager::filter_sanitizer_args( $sanitizers );
		foreach ( $filtered_sanitizers as $sanitizer => $args ) {
			$this->assertEquals( $expected_callback, $args['validation_error_callback'] );
		}
		remove_theme_support( AMP_Theme_Support::SLUG );
	}

	/**
	 * Test for validate_after_plugin_activation().
	 *
	 * @covers AMP_Validation_Manager::validate_after_plugin_activation()
	 */
	public function test_validate_after_plugin_activation() {
		add_filter( 'amp_pre_get_permalink', '__return_empty_string' );
		$r = AMP_Validation_Manager::validate_after_plugin_activation();
		$this->assertInstanceOf( 'WP_Error', $r );
		$this->assertEquals( 'no_published_post_url_available', $r->get_error_code() );
		remove_filter( 'amp_pre_get_permalink', '__return_empty_string' );

		$validation_error = [
			'code' => 'example',
		];

		$validation = [
			'results' => [
				[
					'error'     => $validation_error,
					'sanitized' => false,
				],
			],
		];

		self::factory()->post->create();
		$filter = static function() use ( $validation ) {
			return [
				'body' => sprintf(
					'<html amp><head></head><body></body><!--%s--></html>',
					'AMP_VALIDATION:' . wp_json_encode( $validation )
				),
			];
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );
		$r = AMP_Validation_Manager::validate_after_plugin_activation();
		remove_filter( 'pre_http_request', $filter );
		$this->assertEquals( [ $validation_error ], $r );
		$this->assertEquals( [ $validation_error ], get_transient( AMP_Validation_Manager::PLUGIN_ACTIVATION_VALIDATION_ERRORS_TRANSIENT_KEY ) );
	}

	/**
	 * Test for validate_url().
	 *
	 * @covers AMP_Validation_Manager::validate_url()
	 */
	public function test_validate_url() {
		add_theme_support( AMP_Theme_Support::SLUG );

		$validation_errors = [
			[
				'code' => 'example',
			],
		];

		// Test headers absent.
		self::factory()->post->create();
		$filter = static function() {
			return [
				'body'    => '',
				'headers' => [],
			];
		};
		add_filter( 'pre_http_request', $filter );
		$r = AMP_Validation_Manager::validate_url( home_url( '/' ) );
		$this->assertInstanceOf( 'WP_Error', $r );
		$this->assertEquals( 'white_screen_of_death', $r->get_error_code() );
		remove_filter( 'pre_http_request', $filter );

		// Test success.
		$that          = $this;
		$validated_url = home_url( '/foo/' );
		$filter        = static function( $pre, $r, $url ) use ( $validation_errors, $validated_url, $that ) {
			$that->assertStringStartsWith(
				add_query_arg(
					AMP_Validation_Manager::VALIDATE_QUERY_VAR,
					'',
					$validated_url
				),
				$url
			);
			$validation = [ 'results' => [] ];
			foreach ( $validation_errors as $error ) {
				$sanitized            = false;
				$validation['results'][] = compact( 'error', 'sanitized' );
			}

			return [
				'body' => sprintf(
					'<html amp><head></head><body></body><!--%s--></html>',
					'AMP_VALIDATION:' . wp_json_encode( $validation )
				),
			];
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );
		$r = AMP_Validation_Manager::validate_url( $validated_url );
		$this->assertInternalType( 'array', $r );
		$this->assertEquals( $validated_url, $r['url'] );
		$this->assertEquals( $validation_errors, wp_list_pluck( $r['results'], 'error' ) );
		remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test for print_plugin_notice()
	 *
	 * @covers AMP_Validation_Manager::print_plugin_notice()
	 */
	public function test_print_plugin_notice() {
		global $pagenow;
		$output = get_echo( [ 'AMP_Validation_Manager', 'print_plugin_notice' ] );
		$this->assertEmpty( $output );
		$pagenow          = 'plugins.php';
		$_GET['activate'] = 'true';

		$cache_plugins_backup = wp_cache_get( 'plugins', 'plugins' );

		$plugins = [
			'' => [
				$this->plugin_name => [
					'Name' => 'Foo Bar',
				],
			],
		];

		wp_cache_set( 'plugins', $plugins, 'plugins' );

		set_transient(
			AMP_Validation_Manager::PLUGIN_ACTIVATION_VALIDATION_ERRORS_TRANSIENT_KEY,
			[
				[
					'code'    => 'example',
					'sources' => [
						[
							'type' => 'plugin',
							'name' => 'foo-bar',
						],
					],
				],
			]
		);
		$output = get_echo( [ 'AMP_Validation_Manager', 'print_plugin_notice' ] );
		$this->assertContains( 'Warning: The following plugin may be incompatible with AMP', $output );
		$this->assertContains( 'Foo Bar', $output );
		$this->assertContains( 'More details', $output );
		$this->assertContains( admin_url( 'edit.php' ), $output );

		if ( $cache_plugins_backup ) {
			wp_cache_set( 'plugins', $cache_plugins_backup, 'plugins' );
		}
	}

	/**
	 * Test enqueue_block_validation.
	 *
	 * @covers AMP_Validation_Manager::enqueue_block_validation()
	 */
	public function test_enqueue_block_validation() {
		if ( ! function_exists( 'register_block_type' ) ) {
			$this->markTestSkipped( 'The block editor is not available.' );
		}

		add_theme_support( AMP_Theme_Support::SLUG );
		global $post;
		$post = self::factory()->post->create_and_get();
		$slug = 'amp-block-validation';
		$this->set_capability();
		AMP_Validation_Manager::enqueue_block_validation();

		$script                = wp_scripts()->registered[ $slug ];
		$expected_dependencies = [
			'lodash',
			'react',
			'wp-block-editor',
			'wp-components',
			'wp-compose',
			'wp-data',
			'wp-element',
			'wp-hooks',
			'wp-i18n',
			'wp-polyfill',
		];

		$this->assertContains( 'js/amp-block-validation.js', $script->src );
		$this->assertEqualSets( $expected_dependencies, $script->deps );
		$this->assertContains( $slug, wp_scripts()->queue );

		$style = wp_styles()->registered[ $slug ];
		$this->assertContains( 'css/amp-block-validation-compiled.css', $style->src );
		$this->assertEquals( AMP__VERSION, $style->ver );
		$this->assertContains( $slug, wp_styles()->queue );
	}

	/**
	 * Test enqueue_block_validation.
	 *
	 * @covers AMP_Validation_Manager::enqueue_block_validation()
	 */
	public function test_enqueue_block_validation_without_amp_support() {
		if ( ! function_exists( 'register_block_type' ) ) {
			$this->markTestSkipped( 'The block editor is not available.' );
		}

		$this->assertTrue( AMP_Options_Manager::is_website_experience_enabled() );
		$this->assertFalse( AMP_Options_Manager::is_stories_experience_enabled() );
		remove_theme_support( AMP_Theme_Support::SLUG );
		global $post;
		$post = $this->factory()->post->create_and_get();
		$slug = 'amp-block-validation';
		$this->set_capability();
		AMP_Validation_Manager::enqueue_block_validation();
		$this->assertNotContains( $slug, wp_scripts()->queue );

		AMP_Options_Manager::update_option( 'experiences', [ AMP_Options_Manager::WEBSITE_EXPERIENCE, AMP_Options_Manager::STORIES_EXPERIENCE ] );
		$this->assertTrue( AMP_Options_Manager::is_website_experience_enabled() );
		$this->assertTrue( AMP_Options_Manager::is_stories_experience_enabled() );
		AMP_Story_Post_Type::register();
		if ( post_type_exists( AMP_Story_Post_Type::POST_TYPE_SLUG ) ) {
			$post = $this->factory()->post->create_and_get( [ 'post_type' => AMP_Story_Post_Type::POST_TYPE_SLUG ] );
			AMP_Validation_Manager::enqueue_block_validation();
			$this->assertContains( $slug, wp_scripts()->queue );
		}
	}

	/**
	 * Processes markup, to determine AMP validity.
	 *
	 * Passes $markup through the AMP sanitizers.
	 * Also passes a 'validation_error_callback' to keep track of stripped attributes and nodes.
	 *
	 * @param string $markup The markup to process.
	 * @return string Sanitized markup.
	 */
	protected function process_markup( $markup ) {
		global $content_width;

		AMP_Theme_Support::register_content_embed_handlers();

		/** This filter is documented in wp-includes/post-template.php */
		$markup = apply_filters( 'the_content', $markup );
		$args   = [
			'content_max_width'         => ! empty( $content_width ) ? $content_width : AMP_Post_Template::CONTENT_MAX_WIDTH,
			'validation_error_callback' => 'AMP_Validation_Manager::add_validation_error',
		];

		$results = AMP_Content_Sanitizer::sanitize( $markup, amp_get_content_sanitizers(), $args );
		return $results[0];
	}

	/**
	 * Test process_markup.
	 */
	public function test_process_markup() {
		$this->set_capability();
		$this->process_markup( $this->valid_amp_img );
		$this->assertEquals( [], AMP_Validation_Manager::$validation_results );

		AMP_Validation_Manager::reset_validation_results();
		$video = '<video src="https://example.com/video">';
		$this->process_markup( $video );
		// This isn't valid AMP, but the sanitizer should convert it to an <amp-video>, without stripping anything.
		$this->assertEquals( [], AMP_Validation_Manager::$validation_results );

		AMP_Validation_Manager::reset_validation_results();

		$this->process_markup( $this->disallowed_tag );
		$this->assertCount( 1, AMP_Validation_Manager::$validation_results );
		$this->assertEquals( 'script', AMP_Validation_Manager::$validation_results[0]['error']['node_name'] );

		AMP_Validation_Manager::reset_validation_results();
		$disallowed_style = '<div style="display:none"></div>';
		$this->process_markup( $disallowed_style );
		$this->assertEquals( [], AMP_Validation_Manager::$validation_results );

		AMP_Validation_Manager::reset_validation_results();
		$invalid_video = '<video width="200" height="100"></video>';
		$this->process_markup( $invalid_video );
		$this->assertCount( 1, AMP_Validation_Manager::$validation_results );
		$this->assertEquals( 'video', AMP_Validation_Manager::$validation_results[0]['error']['node_name'] );
		AMP_Validation_Manager::reset_validation_results();

		$this->process_markup( '<button onclick="evil()">Do it</button>' );
		$this->assertCount( 1, AMP_Validation_Manager::$validation_results );
		$this->assertEquals( 'onclick', AMP_Validation_Manager::$validation_results[0]['error']['node_name'] );
		AMP_Validation_Manager::reset_validation_results();
	}


	/**
	 * Add a nonce to the $_REQUEST, so that is_authorized() returns true.
	 *
	 * @return void
	 */
	public function set_capability() {
		wp_set_current_user(
			self::factory()->user->create(
				[
					'role' => 'administrator',
				]
			)
		);
	}

	/**
	 * Outputs a div.
	 *
	 * @return void
	 */
	public function output_div() {
		echo '<div></div>';
	}

	/**
	 * Outputs a notice.
	 *
	 * @param string $message The message to output.
	 *
	 * @return void
	 */
	public function output_notice( $message ) {
		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_attr( $message ) );
	}

	/**
	 * Outputs a message with an excerpt.
	 *
	 * @param string $message The message to output.
	 * @param string $id The ID of the post.
	 *
	 * @return void
	 */
	public function output_message( $message, $id ) {
		printf( '<p>%s</p><p>%s</p>', esc_attr( $message ), esc_html( $id ) );
	}

	/**
	 * Gets a string.
	 *
	 * @return string
	 */
	public function get_string() {
		return 'Example string';
	}

	/**
	 * Creates and inserts a custom post.
	 *
	 * @param array  $errors Validation errors to populate.
	 * @param string $url    URL that the errors occur on. Defaults to the home page.
	 * @return int|WP_Error $error_post The ID of new custom post, or an error.
	 */
	public function create_custom_post( $errors = [], $url = null ) {
		if ( ! $url ) {
			$url = home_url( '/' );
		}

		return AMP_Validated_URL_Post_Type::store_validation_errors( $errors, $url );
	}
}

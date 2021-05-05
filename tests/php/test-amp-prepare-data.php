<?php
/**
 * Test AMP_Prepare_Data.
 *
 * @package AMP
 */

use AmpProject\AmpWP\QueryVar;

/**
 * Test AMP_Prepare_Data.
 *
 * @coversDefaultClass \AMP_Prepare_Data
 */
class AMP_Prepare_Data_Test extends WP_UnitTestCase {

	/**
	 * Set up. Populate an error.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->populate_validation_errors(
			home_url( '/' ),
			[ 'amp' ]
		);
	}

	/**
	 * Test __construct method.
	 *
	 * @covers ::__construct()
	 */
	public function test__construct() {
		$prepare_data = new \AMP_Prepare_Data( [] );
		$this->assertInstanceOf(
			'AMP_Prepare_Data',
			$prepare_data
		);
	}

	/**
	 * Test parse_args method.
	 *
	 * @covers ::parse_args()
	 */
	public function test_parse_args() {
		$args = [
			'urls'     => [ 'https://google.com/' ],
			'post_ids' => [ 123, 456 ],
			'term_ids' => [ 789 ],
		];

		$prepare_data = new \AMP_Prepare_Data( $args );

		$this->assertSame(
			$args['urls'],
			$prepare_data->urls
		);
		$this->assertSame(
			$args['term_ids'],
			$prepare_data->args['term_ids']
		);
		$this->assertSame(
			$args['post_ids'],
			$prepare_data->args['post_ids']
		);

		// If valid term IDs, permalinks should be added to URLs.
		$term = wp_insert_term( 'test', 'category' );

		$expected = [
			'urls'     => [
				\AMP_Prepare_Data::normalize_url_for_storage(
					get_term_link( $term['term_id'] )
				),
			],
			'term_ids' => [
				$term['term_id'],
			],
		];

		$prepare_data = new \AMP_Prepare_Data( $expected );

		$this->assertSame(
			$expected['urls'],
			$prepare_data->urls
		);

		// If valid post IDs, permalinks should be added to URLs.
		$post_id = wp_insert_post(
			[
				'post_title'  => 'test',
				'post_status' => 'publish',
			]
		);

		$expected = [
			'urls'     => [
				\AMP_Prepare_Data::normalize_url_for_storage(
					get_permalink( $post_id )
				),
			],
			'post_ids' => [
				$post_id,
			],
		];

		$prepare_data = new \AMP_Prepare_Data( $expected );

		$this->assertSame(
			$expected['urls'],
			$prepare_data->urls
		);
	}

	/**
	 * Test normalize_url_for_storage method.
	 *
	 * @covers ::normalize_url_for_storage()
	 */
	public function test_normalize_url_for_storage() {
		$url_not_normalized = add_query_arg(
			[
				QueryVar::NOAMP => '',
				'preview_id'    => 123,
			],
			'http://google.com/#anchor'
		);

		$this->assertSame(
			'https://google.com/',
			\AMP_Prepare_Data::normalize_url_for_storage(
				$url_not_normalized
			)
		);
	}

	/**
	 * Test get_data method.
	 *
	 * @covers ::get_data()
	 */
	public function test_get_data() {
		$pd = new \AMP_Prepare_Data();

		$data = $pd->get_data();

		$this->assertSame(
			\AMP_Prepare_Data::get_home_url(),
			$data['site_url']
		);
		$this->assertSame(
			$pd->get_site_info(),
			$data['site_info']
		);
		$this->assertSame(
			$pd->get_plugin_info(),
			$data['plugins']
		);
		$this->assertSame(
			$pd->get_theme_info(),
			$data['themes']
		);
		// @see setUp.
		$this->assertSame(
			'bad',
			$data['errors'][0]['code']
		);
		$this->assertTrue(
			! empty( $data['errors'][0]['error_slug'] )
		);
		$this->assertSame(
			$data['error_sources'][0]['error_slug'],
			$data['errors'][0]['error_slug']
		);
		$this->assertSame(
			'amp',
			$data['error_sources'][0]['name']
		);
		$this->assertSame(
			'plugin',
			$data['error_sources'][0]['type']
		);
		$this->assertSame(
			\AMP_Prepare_Data::normalize_url_for_storage(
				home_url( '/' )
			),
			$data['urls'][0]['url']
		);
		$this->assertSame(
			1,
			count( $data['urls'][0]['errors'] )
		);
		$this->assertTrue(
			is_array( $data['error_log'] )
		);
		$this->assertTrue(
			empty( $data['error_log']['contents'] )
		);
	}

	/**
	 * Test get_site_info method.
	 *
	 * @covers ::get_site_info()
	 */
	public function test_get_site_info() {
		global $wpdb;

		$pd        = new \AMP_Prepare_Data();
		$site_info = $pd->get_site_info();

		$wp_type = 'single';

		if ( is_multisite() ) {
			$wp_type = ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) ? 'subdomain' : 'subdir';
		}

		$loopback_status = '';

		if ( class_exists( 'Health_Check_Loopback' ) ) {
			$loopback_status = \Health_Check_Loopback::can_perform_loopback();
			$loopback_status = ( ! empty( $loopback_status->status ) ) ? $loopback_status->status : '';
		}

		$amp_settings = \AMP_Options_Manager::get_options();
		$amp_settings = ( ! empty( $amp_settings ) && is_array( $amp_settings ) ) ? $amp_settings : [];

		$this->assertSame(
			$site_info['site_url'],
			\AMP_Prepare_Data::get_home_url()
		);
		$this->assertSame(
			$site_info['site_title'],
			get_bloginfo( 'site_title' )
		);
		$this->assertSame(
			$site_info['php_version'],
			phpversion()
		);
		$this->assertSame(
			$site_info['mysql_version'],
			$wpdb->get_var( 'SELECT VERSION();' ) // phpcs:ignore
		);
		$this->assertSame(
			$site_info['wp_version'],
			get_bloginfo( 'version' )
		);
		$this->assertSame(
			$site_info['wp_language'],
			get_bloginfo( 'language' )
		);
		$this->assertSame(
			$site_info['wp_https_status'],
			is_ssl() ? true : false
		);
		$this->assertSame(
			$site_info['wp_multisite'],
			$wp_type
		);
		$this->assertSame(
			$site_info['wp_active_theme'],
			\AMP_Prepare_Data::normalize_theme_info( wp_get_theme() )
		);
		$this->assertSame(
			$site_info['object_cache_status'],
			wp_using_ext_object_cache()
		);
		$this->assertSame(
			$site_info['libxml_version'],
			( defined( 'LIBXML_VERSION' ) ) ? LIBXML_VERSION : ''
		);
		$this->assertSame(
			$site_info['is_defined_curl_multi'],
			function_exists( 'curl_multi_init' )
		);
		$this->assertSame(
			$site_info['loopback_requests'],
			$loopback_status
		);
		$this->assertSame(
			$site_info['amp_mode'],
			( ! empty( $amp_settings['theme_support'] ) ) ? $amp_settings['theme_support'] : ''
		);
		$this->assertSame(
			$site_info['amp_version'],
			( ! empty( $amp_settings['version'] ) ) ? $amp_settings['version'] : ''
		);
		$this->assertSame(
			$site_info['amp_plugin_configured'],
			( ! empty( $amp_settings['plugin_configured'] ) ) ? true : false
		);
		$this->assertSame(
			$site_info['amp_all_templates_supported'],
			( ! empty( $amp_settings['all_templates_supported'] ) ) ? true : false
		);
		$this->assertSame(
			$site_info['amp_supported_post_types'],
			( ! empty( $amp_settings['supported_post_types'] ) && is_array( $amp_settings['supported_post_types'] ) ) ? $amp_settings['supported_post_types'] : []
		);
		$this->assertSame(
			$site_info['amp_supported_templates'],
			( ! empty( $amp_settings['supported_templates'] ) && is_array( $amp_settings['supported_templates'] ) ) ? $amp_settings['supported_templates'] : []
		);
		$this->assertSame(
			$site_info['amp_mobile_redirect'],
			( ! empty( $amp_settings['mobile_redirect'] ) ) ? true : false
		);
		$this->assertSame(
			$site_info['amp_reader_theme'],
			( ! empty( $amp_settings['reader_theme'] ) ) ? $amp_settings['reader_theme'] : ''
		);
	}

	/**
	 * Test get_plugin_info method.
	 *
	 * @covers ::get_plugin_info()
	 */
	public function test_get_plugin_info() {
		$pd = new \AMP_Prepare_Data();

		$active_plugins = get_option( 'active_plugins' );

		if ( is_multisite() ) {
			$network_wide_activate_plugins = get_site_option( 'active_sitewide_plugins' );
			$active_plugins                = array_merge( $active_plugins, $network_wide_activate_plugins );
		}

		$active_plugins = array_values( array_unique( $active_plugins ) );
		$plugin_info    = array_map( '\AMP_Prepare_Data::normalize_plugin_info', $active_plugins );
		$plugin_info    = array_filter( $plugin_info );

		$this->assertSame(
			$plugin_info,
			$pd->get_plugin_info()
		);
	}

	/**
	 * Test get_theme_info method.
	 *
	 * @covers ::get_theme_info()
	 */
	public function test_get_theme_info() {
		$pd = new \AMP_Prepare_Data();

		$themes     = [ wp_get_theme() ];
		$theme_info = array_map( '\AMP_Prepare_Data::normalize_theme_info', $themes );
		$theme_info = array_filter( $theme_info );

		$this->assertSame(
			$theme_info,
			$pd->get_theme_info()
		);
	}

	/**
	 * Test get_error_log method.
	 *
	 * @covers ::get_error_log()
	 */
	public function test_get_error_log() {
		$pd             = new \AMP_Prepare_Data();
		$log            = $pd->get_error_log();
		$error_log_path = ini_get( 'error_log' );

		// Cannot test error_log() contents within phpunit, as error log is output to console.
		if ( empty( $error_log_path ) || ! file_exists( $error_log_path ) ) {
			$this->assertSame(
				$log,
				[
					'log_errors' => ini_get( 'log_errors' ),
					'contents'   => '',
				]
			);
		}
	}

	/**
	 * Test normalize_plugin_info method.
	 *
	 * @covers ::normalize_plugin_info()
	 */
	public function test_normalize_plugin_info() {
		$pd = new \AMP_Prepare_Data();

		$this->assertSame(
			[],
			$pd->normalize_plugin_info( 'not-a-plugin/plugin.php' )
		);

		$plugin_file          = 'amp/amp.php';
		$amp                  = $pd->normalize_plugin_info( $plugin_file );
		$absolute_plugin_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_file;
		$plugin_data          = get_plugin_data( $absolute_plugin_file );

		$this->assertNotEmpty( $amp['name'] );
		$this->assertNotEmpty( $amp['slug'] );
		$this->assertSame( 'amp', $amp['slug'] );
		$this->assertNotEmpty( $amp['plugin_url'] );
		$this->assertNotEmpty( $amp['version'] );
		$this->assertNotEmpty( $amp['author'] );
		$this->assertNotEmpty( $amp['author_url'] );
		if ( array_key_exists( 'RequiresWP', $plugin_data ) ) {
			$this->assertNotEmpty( $amp['requires_wp'] );
		} else {
			// WP ≤ 5.1.
			$this->assertEmpty( $amp['requires_wp'] );
		}
		if ( array_key_exists( 'RequiresPHP', $plugin_data ) ) {
			$this->assertNotEmpty( $amp['requires_php'] );
		} else {
			// WP ≤ 5.1.
			$this->assertEmpty( $amp['requires_php'] );
		}
		$this->assertSame(
			$amp['is_active'],
			is_plugin_active( $plugin_file )
		);
		$this->assertSame(
			$amp['is_network_active'],
			is_plugin_active_for_network( $plugin_file )
		);
		$this->assertEmpty( $amp['is_suppressed'] );

	}

	/**
	 * Test normalize_theme_info method.
	 *
	 * @covers ::normalize_theme_info()
	 */
	public function test_normalize_theme_info() {

		$this->assertSame(
			[],
			\AMP_Prepare_Data::normalize_theme_info( '' )
		);

		$t = wp_get_theme();
		$i = \AMP_Prepare_Data::normalize_theme_info( $t );

		$parent_theme = '';

		if ( ! empty( $t->parent() ) && ! is_a( $t->parent(), 'WP_Theme' ) ) {
			$parent_theme = $t->parent()->get_stylesheet();
		}

		$this->assertSame( $i['name'], $t->get( 'Name' ) );
		$this->assertSame( $i['slug'], $t->get_stylesheet() );
		$this->assertSame( $i['version'], $t->get( 'Version' ) );
		$this->assertSame( $i['status'], $t->get( 'Status' ) );
		$this->assertSame( $i['tags'], ( ! empty( $t->get( 'Tags' ) ) && is_array( $t->get( 'Tags' ) ) ) ? $t->get( 'Tags' ) : [] );
		$this->assertSame( $i['text_domain'], $t->get( 'TextDomain' ) );
		$this->assertSame( $i['requires_wp'], $t->get( 'RequiresWP' ) );
		$this->assertSame( $i['requires_php'], $t->get( 'RequiresPHP' ) );
		$this->assertSame( $i['theme_url'], $t->get( 'ThemeURI' ) );
		$this->assertSame( $i['author'], $t->get( 'Author' ) );
		$this->assertSame( $i['author_url'], $t->get( 'AuthorURI' ) );
		$this->assertTrue( $i['is_active'] ); // testing the current active theme.
		$this->assertSame( $i['parent_theme'], $parent_theme );

	}

	/**
	 * Test get_errors method.
	 *
	 * @covers ::get_errors()
	 */
	public function test_get_errors() {
		$error_data = \AMP_Prepare_Data::get_errors();

		// @see setUp.
		$this->assertSame(
			[ 'c8b31ce3370595c52a3528d1df9e25f8' ],
			array_keys( $error_data )
		);
		$this->assertSame(
			'bad',
			$error_data['c8b31ce3370595c52a3528d1df9e25f8']['code']
		);
		$this->assertSame(
			'30ce7183f572beb50ceab11285265c54a1dd03fb68b5203fa25dfe322ed332b5',
			$error_data['c8b31ce3370595c52a3528d1df9e25f8']['error_slug']
		);
		$this->assertEmpty(
			$error_data['c8b31ce3370595c52a3528d1df9e25f8']['text']
		);
	}

	/**
	 * Test normalize_error method.
	 *
	 * @covers ::normalize_error()
	 */
	public function test_normalize_error() {
		// @see setUp.
		$posts = get_posts(
			[
				'post_type'   => \AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
				'numberposts' => 1,
			]
		);
		$post  = $posts[0];

		$post_errors_raw = json_decode( $post->post_content, true );

		$error_data = $post_errors_raw[0]['data'];
		$error_data = \AMP_Prepare_Data::normalize_error( $error_data );

		$this->AssertEquals(
			'bad',
			$error_data['code']
		);
		$this->AssertEquals(
			'30ce7183f572beb50ceab11285265c54a1dd03fb68b5203fa25dfe322ed332b5',
			$error_data['error_slug']
		);
	}

	/**
	 * Test normalize_error_source method.
	 *
	 * @covers ::normalize_error_source()
	 */
	public function test_normalize_error_source() {
		// @see setUp.
		$posts = get_posts(
			[
				'post_type'   => \AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
				'numberposts' => 1,
			]
		);
		$post  = $posts[0];

		$post_errors_raw = json_decode( $post->post_content, true );

		$source = [];

		$this->assertEmpty( \AMP_Prepare_Data::normalize_error_source( $source ) );

		$source = $post_errors_raw[0]['data']['sources'][0];

		$source = \AMP_Prepare_Data::normalize_error_source( $source );

		$this->assertNotEmpty(
			$source['error_source_slug']
		);
	}

	/**
	 * Test get_amp_urls method.
	 *
	 * @covers ::get_amp_urls()
	 */
	public function test_get_amp_urls() {
		$pd = new \AMP_Prepare_Data();
		$data = $pd->get_amp_urls();

		$this->assertNotEmpty( $data['errors'] );
		$this->assertNotEmpty( $data['error_sources'] );
		$this->assertNotEmpty( $data['urls'] );
		$this->assertNotEmpty( $data['urls'][0]['errors'][0]['error_slug'] );
		$this->assertSame(
			$data['urls'][0]['errors'][0]['error_slug'],
			array_keys( $data['errors'] )[0]
		);
		$this->assertSame(
			$data['urls'][0]['errors'][0]['sources'][0],
			array_keys( $data['error_sources'] )[0]
		);
	}

	/**
	 * Test get_stylesheet_info method.
	 *
	 * @covers ::get_stylesheet_info()
	 */
	public function test_get_stylesheet_info() {
		$posts = get_posts(
			[
				'post_type'   => \AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
				'numberposts' => 1,
			]
		);
		$post  = $posts[0];

		$meta_value = <<<EOT
[
  {
    "group": "amp-custom",
    "original_size": 370,
    "final_size": 0,
    "element": {
      "name": "style",
      "attributes": {
        "type": "text/css"
      }
    },
    "origin": "style_element",
    "sources": [
      {
        "type": "core",
        "name": "wp-includes",
        "file": "functions.wp-styles.php",
        "line": 44,
        "function": "wp_print_styles",
        "hook": "wp_head",
        "priority": 8
      },
      {
        "type": "plugin",
        "name": "amp",
        "file": "includes/class-amp-theme-support.php",
        "line": 2130,
        "function": "AMP_Theme_Support::print_emoji_styles",
        "hook": "wp_print_styles",
        "priority": 10
      }
    ],
    "priority": 70,
    "hash": "bce4601f6f39498a77c695bd9125e0ee",
    "parse_time": 0.033727169036865234,
    "shake_time": 0.008584976196289062,
    "cached": false,
    "imported_font_urls": [],
    "shaken_tokens": [
      [
        false,
        {
          ":root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) amp-img.wp-smiley": false,
          ":root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) amp-anim.wp-smiley": false,
          ":root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) amp-img.emoji": false,
          ":root:not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_):not(#_) amp-anim.emoji": false
        },
        [
          "display:inline-block",
          "border:none",
          "box-shadow:none",
          "height:1em",
          "width:1em",
          "margin:0 .07em",
          "vertical-align:-.1em",
          "background:none",
          "padding:0"
        ]
      ]
    ],
    "included": true
  }
]
EOT;

		update_post_meta(
			$post->ID,
			\AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY,
			$meta_value
		);

		$response = \AMP_Prepare_Data::get_stylesheet_info( $post->ID );

		$this->assertEquals(
			370,
			$response['css_size_before']
		);
		$this->assertEquals(
			0,
			$response['css_size_after']
		);
		$this->assertEquals(
			0,
			$response['css_size_excluded']
		);
		$this->assertEquals(
			0,
			$response['css_budget_percentage']
		);
	}

	/**
	 * Test get_home_url method.
	 *
	 * @covers ::get_home_url()
	 */
	public function test_get_home_url() {
		$expected = strtolower(
			str_replace(
				[ 'https://', 'http://' ],
				[ '', '' ],
				untrailingslashit(
					home_url()
				)
			)
		);

		$this->assertEquals(
			$expected,
			\AMP_Prepare_Data::get_home_url()
		);
	}

	/**
	 * Test remove_domain method.
	 *
	 * @covers ::remove_domain()
	 */
	public function test_remove_domain() {
		$this->assertEmpty(
			\AMP_Prepare_Data::remove_domain(
				home_url()
			)
		);
	}

	/**
	 * Test generate_hash method.
	 *
	 * @covers ::generate_hash()
	 */
	public function test_generate_hash() {
		$expected = '209b5a7686f30ea1cd993b0573fc6ba6fba2c0936b2380763b7235c294056556';

		// Method should sort keys.
		$object = (object) [
			'xyz' => 456,
			'abc' => 123,
		];

		$this->assertSame(
			$expected,
			\AMP_Prepare_Data::generate_hash( $object )
		);

		// Sorted array should be same hash as unsorted array.
		$object = (object) [
			'abc' => 123,
			'xyz' => 456,
		];

		$this->assertSame(
			$expected,
			\AMP_Prepare_Data::generate_hash( $object )
		);
	}

	/**
	 * Populate sample validation errors.
	 *
	 * @param string   $url               URL to populate errors for. Defaults to the home URL.
	 * @param string[] $plugin_file_slugs Plugin file slugs.
	 * @return int ID for amp_validated_url post.
	 */
	private function populate_validation_errors( $url, $plugin_file_slugs ) {
		if ( ! $url ) {
			$url = home_url( '/' );
		}

		$errors = array_map(
			static function ( $plugin_file_slug ) {
				return [
					'code'    => 'bad',
					'sources' => [
						[
							'type' => 'plugin',
							'name' => $plugin_file_slug,
						],
					],
				];
			},
			$plugin_file_slugs
		);

		$r = AMP_Validated_URL_Post_Type::store_validation_errors( $errors, $url );
		if ( is_wp_error( $r ) ) {
			throw new Exception( $r->get_error_message() );
		}
		return $r;
	}
}

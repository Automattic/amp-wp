<?php
/**
 * Tests for SupportData.
 *
 * @package AmpProject\AmpWP\Support\Tests
 */

namespace AmpProject\AmpWP\Support\Tests;

use AmpProject\AmpWP\QueryVar;
use AmpProject\AmpWP\Support\SupportData;
use AmpProject\AmpWP\Support\SupportService;
use PhpParser\Node\Expr\Array_;
use WP_UnitTestCase;

/**
 * Tests for SupportData.
 *
 * @group support-admin
 * @coversDefaultClass \AmpProject\AmpWP\Support\SupportData
 */
class SupportDataTest extends WP_UnitTestCase {

	/**
	 * Instance of OptionsMenu
	 *
	 * @var SupportData
	 */
	public $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {

		parent::setUp();

		$this->instance = new SupportData( [] );
	}

	/**
	 * @covers ::__construct
	 * @covers ::parse_args
	 */
	public function test_parse_args() {

		$url_post_id        = $this->factory()->post->create( [] );
		$post_id            = $this->factory()->post->create( [] );
		$term_id            = $this->factory()->category->create( [] );
		$amp_validated_post = $this->factory()->post->create_and_get(
			[
				'post_title' => home_url( 'sample-page-for-amp-validation' ),
				'post_type'  => \AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
			]
		);

		$input = [
			'urls'                   => [ get_permalink( $url_post_id ) ],
			'post_ids'               => [ $post_id ],
			'term_ids'               => [ $term_id ],
			'amp_validated_post_ids' => [ $amp_validated_post->ID ],
		];

		$expected = array_map(
			SupportData::class . '::normalize_url_for_storage',
			[
				get_permalink( $url_post_id ),
				get_term_link( $term_id ),
				get_permalink( $post_id ),
				$amp_validated_post->post_title,
			]
		);

		$instance = new SupportData( $input );

		$this->assertEquals( $expected, $instance->urls );

	}

	/**
	 * @covers ::send_data
	 * @covers ::get_data
	 */
	public function test_send_data() {

		// Mock http request.
		$support_data = [];

		$callback_wp_remote = static function ( $preempt, $parsed_args ) use ( &$support_data ) {

			$support_data = $parsed_args['body'];

			return [
				'body' => wp_json_encode(
					[
						'status' => 'ok',
						'data'   => [
							'uuid' => 'ampwp-563e5de8-3129-55fb-af71-a6fbd9ef5026',
						],
					]
				),
			];
		};
		add_filter( 'pre_http_request', $callback_wp_remote, 10, 2 );

		$instance = new SupportData( [] );
		$instance->send_data();

		$support_data_keys = [
			'site_url',
			'site_info',
			'plugins',
			'themes',
			'errors',
			'error_sources',
			'urls',
			'error_log',
		];

		foreach ( $support_data_keys as $key ) {
			$this->assertArrayHasKey( $key, $support_data );
		}

		$this->assertEquals( SupportData::get_home_url(), $support_data['site_url'] );

		remove_filter( 'pre_http_request', $callback_wp_remote );
	}

	/**
	 * Test get_error_log method.
	 *
	 * @covers ::get_error_log
	 */
	public function test_get_error_log() {

		$instance = new SupportData( [] );
		$output   = $instance->get_error_log();

		$this->assertArrayHasKey( 'log_errors', $output );
		$this->assertArrayHasKey( 'contents', $output );

	}

	/**
	 * @covers ::normalize_url_for_storage
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
			SupportData::normalize_url_for_storage(
				$url_not_normalized
			)
		);
	}

	/**
	 * @covers ::get_site_info
	 */
	public function test_get_site_info() {

		$site_info = $this->instance->get_site_info();

		$site_info_keys = [
			'site_url',
			'site_title',
			'php_version',
			'mysql_version',
			'wp_version',
			'wp_language',
			'wp_https_status',
			'wp_multisite',
			'wp_active_theme',
			'object_cache_status',
			'libxml_version',
			'is_defined_curl_multi',
			'loopback_requests',
			'amp_mode',
			'amp_version',
			'amp_plugin_configured',
			'amp_all_templates_supported',
			'amp_supported_post_types',
			'amp_supported_templates',
			'amp_mobile_redirect',
			'amp_reader_theme',
		];

		foreach ( $site_info_keys as $key ) {
			$this->assertArrayHasKey( $key, $site_info );
		}
	}

	/**
	 * @covers ::get_plugin_info
	 * @covers ::normalize_plugin_info
	 */
	public function test_get_plugin_info() {

		$original_active_plugins = get_option( 'active_plugins' );
		$original_active_plugins = ( ! empty( $original_active_plugins ) && is_array( $original_active_plugins ) ) ? $original_active_plugins : [];

		// Mock the data
		update_option( 'active_plugins', [ 'hello.php' ] );

		$plugin_info          = $this->instance->get_plugin_info();
		$expected_plugin_info = SupportData::normalize_plugin_info( 'hello.php' );

		$this->assertEquals( 'Hello Dolly', $plugin_info[0]['name'] );
		$this->assertEquals( 'hello.php', $plugin_info[0]['slug'] );

		$plugin_keys = [
			'name',
			'slug',
			'plugin_url',
			'version',
			'author',
			'author_url',
			'requires_wp',
			'requires_php',
			'is_active',
			'is_network_active',
			'is_suppressed',
		];

		foreach ( $plugin_keys as $key ) {
			$this->assertArrayHasKey( $key, $plugin_info[0] );
			$this->assertEquals( $expected_plugin_info[ $key ], $plugin_info[0][ $key ] );
		}

		// Restore data.
		update_option( 'active_plugins', $original_active_plugins );
	}

	/**
	 * @covers ::get_theme_info
	 * @covers ::normalize_theme_info
	 */
	public function test_get_theme_info() {

		$theme_info = $this->instance->get_theme_info();

		$active_theme = SupportData::normalize_theme_info( wp_get_theme() );

		$theme_keys = [
			'name',
			'slug',
			'version',
			'status',
			'tags',
			'text_domain',
			'requires_wp',
			'requires_php',
			'theme_url',
			'author',
			'author_url',
			'is_active',
			'parent_theme',
		];

		foreach ( $theme_keys as $key ) {
			$this->assertArrayHasKey( $key, $theme_info[0] );
			$this->assertEquals( $active_theme[ $key ], $theme_info[0][ $key ] );
		}

	}

	/**
	 * Data provider for $this->test_normalize_error_data()
	 *
	 * @return array
	 */
	public function normalize_error_data_provider() {

		return [
			'empty'     => [
				'input'    => [],
				'expected' => [],
			],
			'normalize' => [
				'input'    => [
					'node_name'       => 'script',
					'parent_name'     => 'head',
					'code'            => 'DISALLOWED_TAG',
					'type'            => 'js_error',
					'node_attributes' => [
						'src' => home_url( '/wp-includes/js/jquery/jquery.js?ver=__normalized__' ),
						'id'  => 'jquery-core-js',
					],
					'node_type'       => 1,
					'sources'         => [ 'some data' ],
				],
				'expected' => [
					'node_name'       => 'script',
					'parent_name'     => 'head',
					'code'            => 'DISALLOWED_TAG',
					'node_attributes' => [
						'src' => '/wp-includes/js/jquery/jquery.js?ver=__normalized__',
						'id'  => 'jquery-core-js',
					],
					'node_type'       => 1,
					'text'            => '',
					'type'            => 'js_error',
					'error_slug'      => 'dc023279738b7ab0fd76fd6a6e004320039cba2f2eee04b30a5f3843262c2d0b',
				],
			],
		];
	}

	/**
	 * @dataProvider normalize_error_data_provider
	 * @covers ::normalize_error
	 * @covers ::remove_domain
	 * @covers ::generate_hash
	 */
	public function test_normalize_error( $input, $expected ) {

		$this->assertEquals( $expected, SupportData::normalize_error( $input ) );
	}

	/**
	 * Data provider for $this->test_normalize_error_source()
	 *
	 * @return array
	 */
	public function normalize_error_source_data_provider() {

		$plugin_info = SupportData::normalize_plugin_info( 'hello.php' );
		$theme_info  = SupportData::normalize_theme_info( wp_get_theme() );

		$data = [
			'empty'  => [
				'input'    => [],
				'expected' => [],
			],
			'core'   => [
				'input'    => [
					'type'              => 'core',
					'name'              => 'wp-includes',
					'file'              => 'script-loader.php',
					'line'              => 2021,
					'function'          => 'wp_enqueue_scripts',
					'hook'              => 'wp_head',
					'priority'          => 1,
					'dependency_type'   => 'script',
					'handle'            => 'jquery-blockui',
					'dependency_handle' => 'jquery-core',
				],
				'expected' => [],
			],
			'plugin' => [
				'input'    => [
					'type'              => 'plugin',
					'name'              => $plugin_info['slug'],
					'file'              => $plugin_info['slug'],
					'line'              => 350,
					'function'          => 'dummy_function',
					'hook'              => 'wp_enqueue_scripts',
					'priority'          => 10,
					'dependency_type'   => 'script',
					'handle'            => 'hello-script',
					'dependency_handle' => 'jquery-core',
					'text'              => 'Start of the content. ' . home_url( '/adiitional.css' ) . ' End of the content',
				],
				'expected' => [
					'dependency_handle' => 'jquery-core',
					'dependency_type'   => 'script',
					'file'              => $plugin_info['slug'],
					'function'          => 'dummy_function',
					'handle'            => 'hello-script',
					'hook'              => 'wp_enqueue_scripts',
					'line'              => 350,
					'name'              => $plugin_info['slug'],
					'priority'          => 10,
					'text'              => 'Start of the content. /adiitional.css End of the content',
					'type'              => 'plugin',
					'version'           => $plugin_info['version'],
				],
			],
			'theme'  => [
				'input'    => [
					'type'     => 'theme',
					'name'     => $theme_info['slug'],
					'file'     => 'inc/template-functions.php',
					'line'     => 403,
					'function' => 'theme_post_content',
					'hook'     => 'theme_loop_post',
					'priority' => 30,
				],
				'expected' => [
					'file'     => 'inc/template-functions.php',
					'function' => 'theme_post_content',
					'hook'     => 'theme_loop_post',
					'line'     => 403,
					'name'     => $theme_info['slug'],
					'priority' => 30,
					'type'     => 'theme',
					'version'  => $theme_info['version'],
				],
			],
		];

		foreach ( [ 'plugin', 'theme' ] as $key ) {
			$data[ $key ]['expected']['error_source_slug'] = SupportData::generate_hash( $data[ $key ]['expected'] );
			ksort( $data[ $key ]['expected'] );
		}

		return $data;
	}

	/**
	 * @dataProvider normalize_error_source_data_provider
	 * @covers ::normalize_error_source
	 * @covers ::generate_hash
	 */
	public function test_normalize_error_source( $input, $expected ) {

		$this->assertEquals( $expected, SupportData::normalize_error_source( $input ) );
	}

	/**
	 * Create validated URL.
	 *
	 * @return \WP_Post Validated URL post.
	 */
	public function create_validated_url() {

		$plugin_info = SupportData::normalize_plugin_info( 'hello.php' );

		$post = $this->factory()->post->create_and_get(
			[
				'post_content' => 'Some post content',
			]
		);

		return $this->factory()->post->create_and_get(
			[
				'post_title'   => get_permalink( $post ),
				'post_type'    => \AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
				'post_content' => wp_json_encode(
					[
						[
							'term_slug' => '1',
							'data'      => [
								'node_name'       => 'script',
								'parent_name'     => 'head',
								'code'            => 'DISALLOWED_TAG',
								'type'            => 'js_error',
								'node_attributes' => [
									'src' => home_url( '/wp-includes/js/jquery/jquery.js?ver=__normalized__' ),
									'id'  => 'jquery-core-js',
								],
								'node_type'       => 1,
								'sources'         => [
									[
										'type'            => 'plugin',
										'name'            => $plugin_info['slug'],
										'file'            => $plugin_info['slug'],
										'line'            => 350,
										'function'        => 'dummy_function',
										'hook'            => 'wp_enqueue_scripts',
										'priority'        => 10,
										'dependency_type' => 'script',
										'handle'          => 'hello-script',
										'dependency_handle' => 'jquery-core',
										'text'            => 'Start of the content. ' . home_url( '/adiitional.css' ) . ' End of the content',
									],
								],
							],
						],
					]
				),
				'meta_input'   => [
					'_amp_queried_object' => [
						'id'   => $post->ID,
						'type' => 'post',
					],
				],
			]
		);
	}

	/**
	 * Test get_amp_urls method.
	 *
	 * @covers ::get_amp_urls
	 * @covers ::get_stylesheet_info
	 */
	public function test_get_amp_urls() {

		$this->create_validated_url();
		$data = $this->instance->get_amp_urls();

		$this->assertCount( 1, $data['errors'] );
		$this->assertCount( 1, $data['error_sources'] );
		$this->assertCount( 1, $data['urls'] );

		$keys = [
			'url',
			'object_type',
			'object_subtype',
			'css_size_before',
			'css_size_after',
			'css_size_excluded',
			'css_budget_percentage',
			'errors',
		];

		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $data['urls'][0] );
		}
	}

	/**
	 * @covers ::get_home_url
	 */
	public function test_get_home_url() {

		$home_url      = home_url();
		$home_url      = strtolower( trim( $home_url ) );
		$http_protocol = wp_parse_url( $home_url, PHP_URL_SCHEME );
		$home_url      = str_replace( "$http_protocol://", '', $home_url );
		$home_url      = untrailingslashit( $home_url );

		$this->assertEquals( $home_url, SupportData::get_home_url() );

	}
}

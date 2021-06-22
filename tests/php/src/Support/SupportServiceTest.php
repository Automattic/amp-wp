<?php
/**
 * Tests for SupportServiceTest.
 *
 * @package AmpProject\AmpWP\Support\Tests
 */

namespace AmpProject\AmpWP\Support\Tests;

use AmpProject\AmpWP\Support\SupportData;
use AmpProject\AmpWP\Support\SupportService;
use WP_UnitTestCase;

/**
 * Tests for SupportServiceTest.
 *
 * @group support-admin
 * @coversDefaultClass \AmpProject\AmpWP\Support\SupportService
 */
class SupportServiceTest extends WP_UnitTestCase {

	/**
	 * Instance of OptionsMenu
	 *
	 * @var SupportService
	 */
	public $instance;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {

		parent::setUp();

		$this->instance = new SupportService();
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
	 * @covers ::get_command_name
	 */
	public function test_get_command_name() {

		$this->assertEquals( 'amp', SupportService::get_command_name() );
	}

	/**
	 * @covers ::send_data
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

		$this->instance->send_data( [] );

		$expected_data_keys = [
			'site_url',
			'site_info',
			'plugins',
			'themes',
			'errors',
			'error_sources',
			'urls',
			'error_log',
		];

		foreach ( $expected_data_keys as $key ) {
			$this->assertArrayHasKey( $key, $support_data );
		}

		remove_filter( 'pre_http_request', $callback_wp_remote );
	}

	/**
	 * @covers ::get_data
	 */
	public function test_get_data() {

		$output = $this->instance->get_data( [] );

		$expected_data_keys = [
			'site_url',
			'site_info',
			'plugins',
			'themes',
			'errors',
			'error_sources',
			'urls',
			'error_log',
		];

		foreach ( $expected_data_keys as $key ) {
			$this->assertArrayHasKey( $key, $output );
		}

	}

	/**
	 * @covers ::cli_command
	 */
	public function test_cli_command() {

		$amp_validated_url = $this->create_validated_url();

		ob_start();
		$this->instance->cli_command(
			[],
			[
				'print' => 'json-pretty',
			]
		);
		$output = ob_get_clean();

		$this->assertContains( wp_json_encode( $amp_validated_url->post_title ), $output );
		$this->assertContains( wp_json_encode( '/wp-includes/js/jquery/jquery.js?ver=__normalized__' ), $output );
		$this->assertContains( wp_json_encode( 'DISALLOWED_TAG' ), $output );
	}
}

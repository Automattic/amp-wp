<?php
/**
 * Tests for AMP_Debug class.
 *
 * @package AMP
 */

/**
 * Tests for AMP_Debug class.
 *
 * @covers AMP_Debug
 */
class Test_AMP_Debug extends WP_UnitTestCase {

	/**
	 * Reset the state after each test.
	 */
	public function tearDown() {
		$_GET = [];
		remove_all_filters( 'user_has_cap' );
	}

	/**
	 * Gets the testing data.
	 *
	 * @return array[] The arguments for the method, the expected return values from them, and the $_GET values.
	 */
	public function get_query() {
		return [
			'invalid_flag_as_argument'                     => [
				'non-existent-flag-name',
				false,
				[],
			],
			'valid_flag_but_without_top_level_query_var'   => [
				AMP_Debug::DISABLE_TREE_SHAKING_QUERY_VAR,
				false,
				[ AMP_Debug::DISABLE_TREE_SHAKING_QUERY_VAR => '' ],
			],
			'valid_flag_passed_but_not_in_get_suberglobal' => [
				AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR,
				false,
				[ AMP_Debug::AMP_FLAGS_QUERY_VAR => [] ],
			],
			'valid_flag_but_false_as_string'               => [
				AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR,
				false,
				[ AMP_Debug::AMP_FLAGS_QUERY_VAR => [ AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR => 'false' ] ],
			],
			'valid_flag_with_random_string'                => [
				AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR,
				false,
				[ AMP_Debug::AMP_FLAGS_QUERY_VAR => [ AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR => 'random-string' ] ],
			],
			'valid_flag_with_numeric_string'               => [
				AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR,
				false,
				[ AMP_Debug::AMP_FLAGS_QUERY_VAR => [ AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR => '5555' ] ],
			],
			'valid_flag_but_empty_string_value'            => [
				AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR,
				true,
				[ AMP_Debug::AMP_FLAGS_QUERY_VAR => [ AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR => '' ] ],
			],
			'valid_flag_with_1_as_string'                  => [
				AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR,
				true,
				[ AMP_Debug::AMP_FLAGS_QUERY_VAR => [ AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR => '1' ] ],
			],
			'valid_flag_with_true_as_string'               => [
				AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR,
				true,
				[ AMP_Debug::AMP_FLAGS_QUERY_VAR => [ AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR => 'true' ] ],
			],
		];
	}

	/**
	 * Test has_flag, when the user has the right capability.
	 *
	 * @dataProvider get_query
	 * @covers \AMP_Debug::has_flag()
	 *
	 * @param string  $argument The argument to pass to the tested method.
	 * @param boolean $expected The expected return value of the tested method.
	 * @param array   $get The query vars present in $_GET.
	 */
	public function test_has_flag_with_capability( $argument, $expected, $get ) {
		// Ensure the user has the right capability.
		add_filter(
			'user_has_cap',
			function( $all_caps ) {
				$all_caps['edit_posts'] = true;
				return $all_caps;
			}
		);

		$_GET = $get;
		$this->assertEquals( $expected, AMP_Debug::has_flag( $argument ) );
	}

	/**
	 * Test has_flag, when the user does not have the right capability.
	 *
	 * Though this uses a @dataProvider, the return should be false every time,
	 * as the user does not have the right capability.
	 *
	 * @dataProvider get_query
	 * @covers \AMP_Debug::has_flag()
	 *
	 * @param string  $argument The argument to pass to the tested method.
	 * @param boolean $expected The expected return value of the tested method.
	 * @param array   $get The query vars present in $_GET.
	 */
	public function test_has_flag_without_user_capability( $argument, $expected, $get ) {
		unset( $expected );

		// Ensure the user does not have the capability for the debugging query vars.
		add_filter(
			'user_has_cap',
			function( $all_caps ) {
				$all_caps['edit_posts'] = false;
				return $all_caps;
			}
		);

		$_GET = $get;
		$this->assertFalse( AMP_Debug::has_flag( $argument ) );
	}

	/**
	 * Test get_all_query_vars.
	 *
	 * @covers \AMP_Debug::get_all_query_vars()
	 */
	public function test_get_all_query_vars() {
		$all_query_vars = AMP_Debug::get_all_query_vars();
		$this->assertEquals( 'Disable post-processing', $all_query_vars[ AMP_Debug::DISABLE_POST_PROCESSING_QUERY_VAR ] );
	}
}

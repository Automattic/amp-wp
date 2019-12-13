<?php
/**
 * Class AMP_Debug
 *
 * @package AMP
 */

/**
 * Class AMP_Debug
 *
 * @since 1.4.1
 */
final class AMP_Debug {

	/**
	 * A top-level query var for AMP flags.
	 *
	 * Debugging query vars should follow this.
	 * For example, https://example.com/?amp&amp_flags[disable_post_processing]=1
	 *
	 * @var string
	 */
	const AMP_FLAGS_QUERY_VAR = 'amp_flags';

	/**
	 * A query var to disable post processing.
	 *
	 * @var string
	 */
	const DISABLE_POST_PROCESSING_QUERY_VAR = 'disable_post_processing';

	/**
	 * A query var to disable the response cache.
	 *
	 * @var string
	 */
	const DISABLE_RESPONSE_CACHE_QUERY_VAR = 'disable_response_cache';

	/**
	 * A query var to prevent a redirect to a non-AMP URL.
	 *
	 * @var string
	 */
	const PREVENT_REDIRECT_TO_NON_AMP_QUERY_VAR = 'prevent_redirect';

	/**
	 * A query var to disable tree shaking.
	 *
	 * @var string
	 */
	const DISABLE_TREE_SHAKING_QUERY_VAR = 'disable_tree_shaking';

	/**
	 * Query var to reject all validation errors.
	 *
	 * @var string
	 */
	const REJECT_ALL_VALIDATION_ERRORS_QUERY_VAR = 'reject_all_errors';

	/**
	 * Query var to accept 'excessive_css' validation errors.
	 *
	 * @var string
	 */
	const ACCEPT_EXCESSIVE_CSS_ERROR_QUERY_VAR = 'accept_excessive_css';

	/**
	 * Query var to disable AMP.
	 *
	 * @var string
	 */
	const DISABLE_AMP_QUERY_VAR = 'disable_amp';

	/**
	 * Gets whether the flag as a query var should be considered true.
	 *
	 * If the flag is present but '', this will be true, like &amp_flags[disable_amp].
	 * It will also be true if it is '1' or 'true', but false for most other values.
	 * The flag should be after the top-level flag of 'amp_flags'.
	 * For example, with the flag 'disable_amp':
	 * https://example.com/?amp&amp_flags[disable_amp]=1
	 *
	 * @param string $flag The name of the flag (query var).
	 * @return bool Whether the flag as a query var should be considered true.
	 */
	public static function has_flag( $flag ) {
		if ( ! isset( $_GET[ self::AMP_FLAGS_QUERY_VAR ][ $flag ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$flag_query_var = $_GET[ self::AMP_FLAGS_QUERY_VAR ][ $flag ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (
			array_key_exists( $flag, self::get_all_query_vars() )
			&&
			AMP_Validation_Manager::has_cap()
		) {
			return '' === $flag_query_var ? true : filter_var( $flag_query_var, FILTER_VALIDATE_BOOLEAN );
		}

		return false;
	}

	/**
	 * Gets all of the debugging query vars.
	 *
	 * @return array An associative array of query var name => title.
	 */
	public static function get_all_query_vars() {
		return [
			self::DISABLE_POST_PROCESSING_QUERY_VAR      => __( 'Disable post-processing', 'amp' ),
			self::DISABLE_RESPONSE_CACHE_QUERY_VAR       => __( 'Disable response cache', 'amp' ),
			self::PREVENT_REDIRECT_TO_NON_AMP_QUERY_VAR  => __( 'Prevent redirect', 'amp' ),
			self::REJECT_ALL_VALIDATION_ERRORS_QUERY_VAR => __( 'Reject all errors', 'amp' ),
			self::ACCEPT_EXCESSIVE_CSS_ERROR_QUERY_VAR   => __( 'Accept excessive CSS', 'amp' ),
			self::DISABLE_AMP_QUERY_VAR                  => __( 'Disable AMP', 'amp' ),
			self::DISABLE_TREE_SHAKING_QUERY_VAR         => __( 'Disable tree shaking', 'amp' ),
		];
	}
}

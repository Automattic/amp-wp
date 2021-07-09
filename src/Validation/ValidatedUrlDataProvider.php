<?php
/**
 * Provides validated URL data.
 *
 * @package AMP
 * @since 2.2
 */

namespace AmpProject\AmpWP\Validation;

use WP_Error;

/**
 * ValidatedUrlDataProvider class.
 *
 * @since 2.2
 * @internal
 */
final class ValidatedUrlDataProvider {

	/**
	 * Provide validated URL data for a given post ID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return ValidatedUrlData|WP_Error Validated URL data.
	 */
	public function for_id( $post_id ) {
		$post = get_post( $post_id );

		if ( empty( $post ) ) {
			return new WP_Error(
				'amp_validated_url_missing_id',
				__( 'Unable to retrieve validation data for this ID.', 'amp' ),
				[ 'status' => 404 ]
			);
		}

		return new ValidatedUrlData( $post );
	}
}

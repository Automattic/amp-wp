<?php
/**
 * Provides validated URL data.
 *
 * @package AMP
 * @since 2.2
 */

namespace AmpProject\AmpWP\Validation;

use AMP_Validated_URL_Post_Type;
use WP_Error;
use WP_Post;

/**
 * ValidatedUrlDataProvider class.
 *
 * @since 2.2
 * @internal
 */
final class ValidatedUrlDataProvider {

	/**
	 * Validated URL post.
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * Validated URL stylesheets data parsed from the JSON string in post meta.
	 *
	 * @var array|null
	 */
	private $stylesheets = null;

	/**
	 * ValidatedUrlDataProvider constructor.
	 *
	 * @param int $post_id Post ID.
	 */
	public function __construct( $post_id ) {
		$this->post = get_post( $post_id );
	}

	/**
	 * Check if the validated URL data instance is valid.
	 *
	 * @return bool|WP_Error
	 */
	public function is_valid() {
		if ( empty( $this->post ) ) {
			return new WP_Error(
				'amp_validated_url_missing_id',
				__( 'Unable to retrieve validation data for this ID.', 'amp' ),
				[ 'status' => 404 ]
			);
		}

		return false;
	}

	/**
	 * Get validated URL ID.
	 *
	 * @return int|null
	 */
	public function get_id() {
		if ( ! $this->post ) {
			return null;
		}

		return $this->post->ID;
	}

	/**
	 * Get the URL that was validated.
	 *
	 * @return string|null
	 */
	public function get_url() {
		if ( ! $this->post ) {
			return null;
		}

		return AMP_Validated_URL_Post_Type::get_url_from_post( $this->post );
	}

	/**
	 * Get the date that the URL was validated.
	 *
	 * @return string|null
	 */
	public function get_date() {
		if ( ! $this->post ) {
			return null;
		}

		return $this->post->post_date;
	}

	/**
	 * Get the user that last validated the URL.
	 *
	 * @return int|null
	 */
	public function get_author() {
		if ( ! $this->post ) {
			return null;
		}

		return (int) $this->post->post_author;
	}

	/**
	 * Get the validated URL stylesheets data.
	 *
	 * @return array|WP_Error
	 */
	public function get_stylesheets() {
		if ( null !== $this->stylesheets ) {
			return $this->stylesheets;
		}

		$stylesheets = get_post_meta( $this->get_id(), AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY, true );

		if ( empty( $stylesheets ) ) {
			return new WP_Error(
				'amp_validated_url_stylesheets_no_longer_available',
				__( 'Stylesheet information for this URL is no longer available. Such data is automatically deleted after a week to reduce database storage. It is of little value to store long-term given that it becomes stale as themes and plugins are updated. To obtain the latest stylesheet information, recheck this URL.', 'amp' ),
				[ 'status' => 404 ]
			);
		}

		$stylesheets = json_decode( $stylesheets, true );

		if ( ! is_array( $stylesheets ) ) {
			return new WP_Error(
				'amp_validated_url_stylesheets_missing',
				__( 'Unable to retrieve stylesheets data for this URL.', 'amp' ),
				[ 'status' => 404 ]
			);
		}

		$this->stylesheets = $stylesheets;

		return $this->stylesheets;
	}
}

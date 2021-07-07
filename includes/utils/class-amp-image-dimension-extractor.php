<?php
/**
 * Class AMP_Image_Dimension_Extractor
 *
 * @package AMP
 */

/**
 * Class with static methods to extract image dimensions.
 *
 * @internal
 */
class AMP_Image_Dimension_Extractor {

	const STATUS_FAILED_LAST_ATTEMPT     = 'failed';
	const STATUS_IMAGE_EXTRACTION_FAILED = 'failed';

	/**
	 * Internal flag whether callbacks have been registered.
	 *
	 * @var bool
	 */
	private static $callbacks_registered = false;

	/**
	 * Extracts dimensions from image URLs.
	 *
	 * @since 0.2
	 *
	 * @param array|string $urls Array of URLs to extract dimensions from, or a single URL string.
	 * @return array|string Extracted dimensions keyed by original URL, or else the single set of dimensions if one URL string is passed.
	 */
	public static function extract( $urls ) {
		if ( ! self::$callbacks_registered ) {
			self::register_callbacks();
		}

		$return_dimensions = [];

		// Back-compat for users calling this method directly.
		$is_single = is_string( $urls );
		if ( $is_single ) {
			$urls = [ $urls ];
		}

		// Normalize URLs and also track a map of normalized-to-original as we'll need it to reformat things when returning the data.
		$url_map         = [];
		$normalized_urls = [];
		foreach ( $urls as $original_url ) {
			$normalized_url = self::normalize_url( $original_url );
			if ( false !== $normalized_url ) {
				$url_map[ $original_url ] = $normalized_url;
				$normalized_urls[]        = $normalized_url;
			} else {
				// This is not a URL we can extract dimensions from, so default to false.
				$return_dimensions[ $original_url ] = false;
			}
		}

		$extracted_dimensions = array_fill_keys( $normalized_urls, false );

		/**
		 * Filters the dimensions extracted from image URLs.
		 *
		 * @since 0.5.1
		 *
		 * @param array $extracted_dimensions Extracted dimensions, initially mapping images URLs to false.
		 */
		$extracted_dimensions = apply_filters( 'amp_extract_image_dimensions_batch', $extracted_dimensions );

		// We need to return a map with the original (un-normalized URL) as we that to match nodes that need dimensions.
		foreach ( $url_map as $original_url => $normalized_url ) {
			$return_dimensions[ $original_url ] = $extracted_dimensions[ $normalized_url ];
		}

		// Back-compat: just return the dimensions, not the full mapped array.
		if ( $is_single ) {
			return current( $return_dimensions );
		}

		return $return_dimensions;
	}

	/**
	 * Normalizes the given URL.
	 *
	 * This method ensures the URL has a scheme and, if relative, is prepended the WordPress site URL.
	 *
	 * @param string $url URL to normalize.
	 * @return string|false Normalized URL, or false if normalization failed.
	 */
	public static function normalize_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		if ( 0 === strpos( $url, 'data:' ) ) {
			return false;
		}

		$normalized_url = $url;

		if ( 0 === strpos( $url, '//' ) ) {
			$normalized_url = set_url_scheme( $url, 'http' );
		} else {
			$parsed = wp_parse_url( $url );
			if ( ! isset( $parsed['host'] ) ) {
				$path = '';
				if ( isset( $parsed['path'] ) ) {
					$path .= $parsed['path'];
				}
				if ( isset( $parsed['query'] ) ) {
					$path .= '?' . $parsed['query'];
				}
				$home      = home_url();
				$home_path = wp_parse_url( $home, PHP_URL_PATH );
				if ( ! empty( $home_path ) ) {
					$home = substr( $home, 0, - strlen( $home_path ) );
				}
				$normalized_url = $home . $path;
			}
		}

		/**
		 * Apply filters on the normalized image URL for dimension extraction.
		 *
		 * @since 1.1
		 *
		 * @param string $normalized_url Normalized image URL.
		 * @param string $url            Original image URL.
		 */
		$normalized_url = apply_filters( 'amp_normalized_dimension_extractor_image_url', $normalized_url, $url );

		return $normalized_url;
	}

	/**
	 * Registers the necessary callbacks.
	 */
	private static function register_callbacks() {
		self::$callbacks_registered = true;

		add_filter( 'amp_extract_image_dimensions_batch', [ __CLASS__, 'extract_by_filename_or_filesystem' ] );
		add_filter( 'amp_extract_image_dimensions_batch', [ __CLASS__, 'extract_by_downloading_images' ], 999, 1 );

		/**
		 * Fires after the amp_extract_image_dimensions_batch filter has been added to extract by downloading images.
		 *
		 * @since 0.5.1
		 */
		do_action( 'amp_extract_image_dimensions_batch_callbacks_registered' );
	}

	/**
	 * To get attachment ID from attached path.
	 *
	 * @param string $path Attached path.
	 *
	 * @return int Positive number on success, Otherwise 0.
	 */
	private static function get_attachment_id_from_path( $path ) {

		if ( empty( $path ) ) {
			return 0;
		}

		$path = wp_parse_url( $path, PHP_URL_PATH );

		if ( empty( $path ) ) {
			return 0;
		}

		global $wpdb;

		$cache_key     = md5( $path );
		$cache_group   = 'amp_attachment_id_from_path';
		$attachment_id = wp_cache_get( $cache_key, $cache_group );

		if ( empty( $attachment_id ) ) {
			$attachment_id = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					"SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_wp_attached_file' AND meta_value=%s ORDER BY post_id ASC;",
					$path
				)
			);

			$attachment_id = ( ! empty( $attachment_id ) && is_array( $attachment_id ) ) ? array_pop( $attachment_id ) : 0;

			wp_cache_set( $cache_key, $attachment_id, $cache_group, 3 * HOUR_IN_SECONDS );
		}

		return (int) $attachment_id;
	}

	/**
	 * Extract dimensions from filename if dimension exists or from file system.
	 *
	 * @param array $dimensions Image urls mapped to dimensions.
	 *
	 * @return array Dimensions mapped to image urls, or false if they could not be retrieved
	 */
	public static function extract_by_filename_or_filesystem( $dimensions ) {

		if ( empty( $dimensions ) || ! is_array( $dimensions ) ) {
			return [];
		}

		$upload_dir      = wp_get_upload_dir();
		$base_upload_uri = strtolower( trim( $upload_dir['baseurl'] ) );

		foreach ( $dimensions as $url => $value ) {

			// Check whether some other callback attached to the filter already provided dimensions for this image.
			if ( ! empty( $value ) && is_array( $value ) ) {
				continue;
			}

			// Check if it's internal media or not. If it's not then bail out.
			if ( false === strpos( strtolower( trim( $url ) ), $base_upload_uri ) ) {
				continue;
			}

			// Get media path.
			$attached_path = ltrim( str_replace( $base_upload_uri, '', $url ), '/' );

			// Try to get attachment id from media path.
			$attachment_id      = static::get_attachment_id_from_path( $attached_path );
			$attachment_id      = ( ! empty( $attachment_id ) && 0 < (int) $attachment_id ) ? (int) $attachment_id : 0;
			$possible_dimension = [];

			// If attachment is exist fetch size from attachment metadata.
			if ( ! empty( $attachment_id ) ) {
				$possible_dimension = wp_get_attachment_metadata( $attachment_id );
			}

			// If attachment is exist and dimension not available in metadata then try to fetch from file system.
			if ( ! empty( $attachment_id ) && ( empty( $possible_dimension ) || ! is_array( $possible_dimension ) ) ) {
				$image_file = sprintf( '%s/%s', trim( $upload_dir['basedir'] ), $attached_path );

				if ( function_exists( 'wp_getimagesize' ) ) {
					$imagesize = wp_getimagesize( $image_file );
				} else {
					$imagesize = getimagesize( $image_file );
				}

				if ( ! empty( $imagesize ) && is_array( $imagesize ) ) {
					$possible_dimension = [
						'width'  => (int) $imagesize[0],
						'height' => (int) $imagesize[1],
					];
				}
			}

			// If not exists then whether file contain dimension or not.
			if ( empty( $attachment_id ) ) {
				$basename                   = basename( $attached_path );
				$filename_without_extension = explode( '.', $basename );
				$extension                  = array_pop( $filename_without_extension );
				$filename_without_extension = implode( '.', $filename_without_extension );

				$regex = '/-(?<width>\d+)x(?<height>\d+)(?:\.' . $extension . ')$/m';
				preg_match( $regex, $attached_path, $possible_dimension );
			}

			if ( ! empty( $possible_dimension['width'] ) && ! empty( $possible_dimension['height'] ) ) {
				$dimensions[ $url ] = [
					'width'  => (int) $possible_dimension['width'],
					'height' => (int) $possible_dimension['height'],
				];
			}
		}

		return $dimensions;
	}

	/**
	 * Extract dimensions from downloaded images (or transient/cached dimensions from downloaded images)
	 *
	 * @param array $dimensions Image urls mapped to dimensions.
	 * @param false $mode       Deprecated.
	 * @return array Dimensions mapped to image urls, or false if they could not be retrieved
	 */
	public static function extract_by_downloading_images( $dimensions, $mode = false ) {
		if ( $mode ) {
			_deprecated_argument( __METHOD__, 'AMP 1.1' );
		}

		$transient_expiration = 30 * DAY_IN_SECONDS;

		$urls_to_fetch = [];
		$images        = [];

		self::determine_which_images_to_fetch( $dimensions, $urls_to_fetch );
		try {
			self::fetch_images( $urls_to_fetch, $images );
			self::process_fetched_images( $urls_to_fetch, $images, $dimensions, $transient_expiration );
		} catch ( Exception $exception ) {
			trigger_error( esc_html( $exception->getMessage() ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		}

		return $dimensions;
	}

	/**
	 * Determine which images to fetch by checking for dimensions in transient/cache.
	 * Creates a short lived transient that acts as a semaphore so that another visitor
	 * doesn't trigger a remote fetch for the same image at the same time.
	 *
	 * @param array $dimensions Image urls mapped to dimensions.
	 * @param array $urls_to_fetch Urls of images to fetch because dimensions are not in transient/cache.
	 */
	private static function determine_which_images_to_fetch( &$dimensions, &$urls_to_fetch ) {
		foreach ( $dimensions as $url => $value ) {

			// Check whether some other callback attached to the filter already provided dimensions for this image.
			if ( is_array( $value ) ) {
				continue;
			}

			$url_hash          = md5( $url );
			$transient_name    = sprintf( 'amp_img_%s', $url_hash );
			$cached_dimensions = get_transient( $transient_name );

			// If we're able to retrieve the dimensions from a transient, set them and move on.
			if ( is_array( $cached_dimensions ) ) {
				$dimensions[ $url ] = [
					'width'  => $cached_dimensions[0],
					'height' => $cached_dimensions[1],
				];
				continue;
			}

			// If the value in the transient reflects we couldn't get dimensions for this image the last time we tried, move on.
			if ( self::STATUS_FAILED_LAST_ATTEMPT === $cached_dimensions ) {
				$dimensions[ $url ] = false;
				continue;
			}

			$transient_lock_name = sprintf( 'amp_lock_%s', $url_hash );

			// If somebody is already trying to extract dimensions for this transient right now, move on.
			if ( false !== get_transient( $transient_lock_name ) ) {
				$dimensions[ $url ] = false;
				continue;
			}

			// Include the image as a url to fetch.
			$urls_to_fetch[ $url ]                        = [];
			$urls_to_fetch[ $url ]['url']                 = $url;
			$urls_to_fetch[ $url ]['transient_name']      = $transient_name;
			$urls_to_fetch[ $url ]['transient_lock_name'] = $transient_lock_name;
			set_transient( $transient_lock_name, 1, MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Fetch dimensions of remote images
	 *
	 * @throws Exception When cURL handle cannot be added.
	 *
	 * @param array $urls_to_fetch Image src urls to fetch.
	 * @param array $images Array to populate with results of image/dimension inspection.
	 */
	private static function fetch_images( $urls_to_fetch, &$images ) {
		$urls   = array_keys( $urls_to_fetch );
		$client = new \FasterImage\FasterImage();

		/**
		 * Filters the user agent for onbtaining the image dimensions.
		 *
		 * @param string $user_agent User agent.
		 */
		$client->setUserAgent( apply_filters( 'amp_extract_image_dimensions_get_user_agent', self::get_default_user_agent() ) );
		$client->setBufferSize( 1024 );
		$client->setSslVerifyHost( true );
		$client->setSslVerifyPeer( true );

		$images = $client->batch( $urls );
	}

	/**
	 * Determine success or failure of remote fetch, integrate fetched dimensions into url to dimension mapping,
	 * cache fetched dimensions via transient and release/delete semaphore transient
	 *
	 * @param array $urls_to_fetch List of image urls that were fetched and transient names corresponding to each (for unlocking semaphore, setting "real" transient).
	 * @param array $images Results of remote fetch mapping fetched image url to dimensions.
	 * @param array $dimensions Map of image url to dimensions to be updated with results of remote fetch.
	 * @param int   $transient_expiration Duration image dimensions should exist in transient/cache.
	 */
	private static function process_fetched_images( $urls_to_fetch, $images, &$dimensions, $transient_expiration ) {
		foreach ( $urls_to_fetch as $url_data ) {
			$image_data = $images[ $url_data['url'] ];
			if ( self::STATUS_IMAGE_EXTRACTION_FAILED === $image_data['size'] ) {
				$dimensions[ $url_data['url'] ] = false;
				set_transient( $url_data['transient_name'], self::STATUS_FAILED_LAST_ATTEMPT, $transient_expiration );
			} else {
				$dimensions[ $url_data['url'] ] = [
					'width'  => $image_data['size'][0],
					'height' => $image_data['size'][1],
				];
				set_transient(
					$url_data['transient_name'],
					[
						$image_data['size'][0],
						$image_data['size'][1],
					],
					$transient_expiration
				);
			}
			delete_transient( $url_data['transient_lock_name'] );
		}
	}

	/**
	 * Get default user agent
	 *
	 * @return string
	 */
	public static function get_default_user_agent() {
		return 'amp-wp, v' . AMP__VERSION . ', ' . home_url();
	}
}

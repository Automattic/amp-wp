<?php
/**
 * Plugin uninstall file.
 *
 * @package AMP
 */

namespace AmpProject\AmpWP;

// if uninstall.php is not called by WordPress, Then die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

/**
 * Delete data from option table.
 *
 * @return void
 */
function delete_options() {

	delete_option( 'amp-options' );
}

/**
 * Delete AMP Validated URL posts.
 *
 * @return void
 */
function delete_posts() {

	global $wpdb;

	$current_page = 1;
	$per_page     = 1000;

	do {
		$offset = $per_page * ( $current_page - 1 );

		/**
		 * We don't need to cache result.
		 * Since we are going to delete those records.
		 */
		$result = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_type='amp_validated_url' LIMIT %d OFFSET %d;",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		if ( empty( $result ) || ! is_array( $result ) ) {
			break;
		}

		$post_ids = wp_list_pluck( $result, 'ID' );

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id );
		}

		$current_page++;
	} while ( ! empty( $result ) );
}

/**
 * Delete AMP validation error terms.
 *
 * @return void
 */
function delete_terms() {

	global $wpdb;

	$current_page = 1;
	$per_page     = 1000;

	$taxonomy = 'amp_validation_error';

	do {
		$offset = $per_page * ( $current_page - 1 );

		/**
		 * We don't need to cache result.
		 * Since we are going to delete those records.
		 */
		$result = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy=%s LIMIT %d OFFSET %d;",
				$taxonomy,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		if ( empty( $result ) || ! is_array( $result ) ) {
			break;
		}

		$term_ids = wp_list_pluck( $result, 'term_id' );

		foreach ( $term_ids as $term_id ) {
			wp_delete_term( $term_id, $taxonomy );
		}

		$current_page++;
	} while ( ! empty( $result ) );
}

$flag = get_option( 'amp-preserver-data', false );

if ( empty( $flag ) ) {
	delete_options();
	delete_posts();
	delete_terms();
}

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
	delete_option( 'amp_css_transient_monitor_time_series' );
	delete_option( 'amp_customize_setting_modified_timestamps' );
}

/**
 * Delete transient data from option table if object cache is not available.
 *
 * @return void
 */
function delete_transient() {

	if ( wp_using_ext_object_cache() ) {
		return;
	}

	global $wpdb;

	$transient_groups = [
		'amp-parsed-stylesheet-v',
		'amp_img_',
		'amp_new_validation_error_urls_count',
		'amp_error_index_counts',
		'amp_plugin_activation_validation_errors',
		'amp_themes_wporg',
		'amp_lock_',
	];

	$where_clause = [];

	foreach ( $transient_groups as $transient_group ) {
		$where_clause[] = $wpdb->prepare(
			' option_name LIKE %s OR option_name LIKE %s ',
			"_transient_$transient_group%",
			"_transient_timeout_$transient_group%"
		);
	}

	$where_clause = implode( ' OR ', $where_clause );

	$query = "DELETE FROM $wpdb->options WHERE $where_clause";

	/**
	 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
	 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
	 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
	 *
	 * We don't need to cache result.
	 * Since we are going to delete those records.
	 */
	$wpdb->query( $query );
	// phpcs:enable
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
		 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		 *
		 * We don't need to cache result.
		 * Since we are going to delete those records.
		 */
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_type='amp_validated_url' LIMIT %d OFFSET %d;",
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

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
		 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		 *
		 * We don't need to cache result.
		 * Since we are going to delete those records.
		 */
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy=%s LIMIT %d OFFSET %d;",
				$taxonomy,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

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

delete_options();
delete_transient();
delete_posts();
delete_terms();

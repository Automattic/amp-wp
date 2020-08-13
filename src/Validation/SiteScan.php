<?php
/**
 * Provides site scan results.
 *
 * @package AMP
 * @since 2.1
 */

namespace AmpProject\AmpWP\Validation;

use AMP_Theme_Support;
use AMP_Validation_Error_Taxonomy;
use AMP_Validation_Manager;
use WP_Query;

/**
 * SiteScan class.
 *
 * @since 2.1
 */
final class SiteScan {

	/**
	 * The total number of validation errors, regardless of whether they were accepted.
	 *
	 * @var int
	 */
	public $total_errors = 0;

	/**
	 * The total number of unaccepted validation errors.
	 *
	 * If an error has been accepted in the /wp-admin validation UI,
	 * it won't count toward this.
	 *
	 * @var int
	 */
	public $unaccepted_errors = 0;

	/**
	 * The number of URLs crawled, regardless of whether they have validation errors.
	 *
	 * @var int
	 */
	public $number_crawled = 0;

	/**
	 * Whether to force crawling of URLs.
	 *
	 * By default, this script only crawls URLs that support AMP,
	 * where the user has not opted-out of AMP for the URL.
	 * For example, by un-checking 'Posts' in 'AMP Settings' > 'Supported Templates'.
	 * Or un-checking 'Enable AMP' in the post's editor.
	 *
	 * @var bool
	 */
	public $force_crawl_urls;

	/**
	 * An allowlist of conditionals to use for validation.
	 *
	 * Usually, this script will validate all of the templates that don't have AMP disabled.
	 * But this allows validating based on only these conditionals.
	 * This is set if the WP-CLI command has an --include argument.
	 *
	 * @var array
	 */
	public $include_conditionals;

	/**
	 * The maximum number of URLs to validate for each type.
	 *
	 * Templates are each a separate type, like those for is_category() and is_tag().
	 * Also, each post type is a separate type.
	 * This value is overridden if the WP-CLI command has an --limit argument, like --limit=10.
	 *
	 * @var int
	 */
	public $limit_type_validate_count;

	/**
	 * The validation counts by type, like template or post type.
	 *
	 * @var array[] {
	 *     Validity by type.
	 *
	 *     @type array $type {
	 *         @type int $valid The number of valid URLs for this type.
	 *         @type int $total The total number of URLs for this type, valid or invalid.
	 *     }
	 * }
	 */
	public $validity_by_type = [];

	/**
	 * URLs to crawl.
	 *
	 * @var array
	 */
	private $urls;

	/**
	 * Class constructor.
	 *
	 * @param integer $limit_type_validate_count The maximum number of URLs to validate for each type.
	 * @param array   $include_conditionals An allowlist of conditionals to use for validation.
	 * @param boolean $force_crawl_urls Whether to force crawling of URLs.
	 */
	public function __construct(
		$limit_type_validate_count = 10,
		$include_conditionals = [],
		$force_crawl_urls = false
	) {
		$this->limit_type_validate_count = $limit_type_validate_count;
		$this->include_conditionals      = $include_conditionals;
		$this->force_crawl_urls          = $force_crawl_urls;
	}

	/**
	 * Provides the array of URLs to check. Each URL is an array with two elements, with the URL at index 0 and the type at index 1.
	 *
	 * @return array Array of URLs and types.
	 */
	public function get_urls() {
		if ( ! is_null( $this->urls ) ) {
			return $this->urls;
		}

		/*
		 * If 'Your homepage displays' is set to 'Your latest posts', validate the homepage.
		 * It will not be part of the page validation below.
		 */
		if ( 'posts' === get_option( 'show_on_front' ) && $this->is_template_supported( 'is_home' ) ) {
			$this->urls[] = [
				'url'  => home_url( '/' ),
				'type' => 'home',
			];
		}

		$amp_enabled_taxonomies = array_filter(
			get_taxonomies( [ 'public' => true ] ),
			[ $this, 'does_taxonomy_support_amp' ]
		);
		$public_post_types      = get_post_types( [ 'public' => true ], 'names' );

		// Validate one URL of each template/content type, then another URL of each type on the next iteration.
		for ( $i = 0; $i < $this->limit_type_validate_count; $i++ ) {
			// Validate all public, published posts.
			foreach ( $public_post_types as $post_type ) {
				$post_ids = $this->get_posts_that_support_amp( $this->get_posts_by_type( $post_type, $i, 1 ) );
				if ( ! empty( $post_ids[0] ) ) {
					$this->urls[] = [
						'url'  => get_permalink( $post_ids[0] ),
						'type' => $post_type,
					];
				}
			}

			foreach ( $amp_enabled_taxonomies as $taxonomy ) {
				$taxonomy_links = $this->get_taxonomy_links( $taxonomy, $i, 1 );
				$link           = reset( $taxonomy_links );
				if ( ! empty( $link ) ) {
					$this->urls[] = [
						'url'  => $link,
						'type' => $taxonomy,
					];
				}
			}

			$author_page_urls = $this->get_author_page_urls( $i, 1 );
			if ( ! empty( $author_page_urls[0] ) ) {
				$this->urls[] = [
					'url'  => $author_page_urls[0],
					'type' => 'author',
				];
			}
		}

		// Only validate 1 date and 1 search page.
		$url = $this->get_date_page();
		if ( $url ) {
			$this->urls[] = [
				'url'  => $url,
				'type' => 'date',
			];
		}
		$url = $this->get_search_page();
		if ( $url ) {
			$this->urls[] = [
				'url'  => $url,
				'type' => 'search',
			];
		}

		return $this->urls;
	}

	/**
	 * Gets the total number of URLs to validate.
	 *
	 * By default, this only counts AMP-enabled posts and terms.
	 * But if $force_crawl_urls is true, it counts all of them, regardless of their AMP status.
	 * It also uses $this->maximum_urls_to_validate_for_each_type,
	 * which can be overridden with a command line argument.
	 *
	 * @return int The number of URLs to validate.
	 */
	public function count_urls_to_validate() {
		return count( $this->get_urls() );
	}

	/**
	 * Gets whether the template is supported.
	 *
	 * If the user has passed an include argument to the WP-CLI command, use that to find if this template supports AMP.
	 * For example, wp amp validation run --include=is_tag,is_category
	 * would return true only if is_tag() or is_category().
	 * But passing the self::FLAG_NAME_FORCE_VALIDATION argument to the WP-CLI command overrides this.
	 *
	 * @param string $template The template to check.
	 * @return bool Whether the template is supported.
	 */
	public function is_template_supported( $template ) {
		// If the --include argument is present in the WP-CLI command, this template conditional must be present in it.
		if ( ! empty( $this->include_conditionals ) ) {
			return in_array( $template, $this->include_conditionals, true );
		}
		if ( $this->force_crawl_urls ) {
			return true;
		}

		$supportable_templates = AMP_Theme_Support::get_supportable_templates();

		// Check whether this taxonomy's template is supported, including in the 'AMP Settings' > 'Supported Templates' UI.
		return ! empty( $supportable_templates[ $template ]['supported'] );
	}

	/**
	 * Gets the posts IDs that support AMP.
	 *
	 * By default, this only gets the post IDs if they support AMP.
	 * This means that 'Posts' isn't deselected in 'AMP Settings' > 'Supported Templates'.
	 * And 'Enable AMP' isn't unchecked in the post's editor.
	 * But if $force_crawl_urls is true, this simply returns all of the IDs.
	 *
	 * @param array $ids THe post IDs to check for AMP support.
	 * @return array The post IDs that support AMP, or an empty array.
	 */
	private function get_posts_that_support_amp( $ids ) {
		if ( ! $this->is_template_supported( 'is_singular' ) ) {
			return [];
		}

		if ( $this->force_crawl_urls ) {
			return $ids;
		}

		return array_filter(
			$ids,
			'post_supports_amp'
		);
	}

	/**
	 * Gets the IDs of public, published posts.
	 *
	 * @param string   $post_type The post type.
	 * @param int|null $offset The offset of the query (optional).
	 * @param int|null $number The number of posts to query for (optional).
	 * @return int[]   $post_ids The post IDs in an array.
	 */
	private function get_posts_by_type( $post_type, $offset = null, $number = null ) {
		$args = [
			'post_type'      => $post_type,
			'posts_per_page' => is_int( $number ) ? $number : $this->limit_type_validate_count,
			'post_status'    => 'publish',
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'fields'         => 'ids',
		];
		if ( is_int( $offset ) ) {
			$args['offset'] = $offset;
		}

		// Attachment posts usually have the post_status of 'inherit,' so they can use the status of the post they're attached to.
		if ( 'attachment' === $post_type ) {
			$args['post_status'] = 'inherit';
		}
		$query = new WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Gets the author page URLs, like https://example.com/author/admin/.
	 *
	 * Accepts an $offset parameter, for the query of authors.
	 * 0 is the first author in the query, and 1 is the second.
	 *
	 * @param int|string $offset The offset for the URL to query for, should be an int if passing an argument.
	 * @param int|string $number The total number to query for, should be an int if passing an argument.
	 * @return array The author page URLs, or an empty array.
	 */
	private function get_author_page_urls( $offset = '', $number = '' ) {
		$author_page_urls = [];
		if ( ! $this->is_template_supported( 'is_author' ) ) {
			return $author_page_urls;
		}

		$number = ! empty( $number ) ? $number : $this->limit_type_validate_count;
		foreach ( get_users( compact( 'offset', 'number' ) ) as $author ) {
			$author_page_urls[] = get_author_posts_url( $author->ID, $author->user_nicename );
		}

		return $author_page_urls;
	}

	/**
	 * Gets a single search page URL, like https://example.com/?s=example.
	 *
	 * @return string|null An example search page, or null.
	 */
	private function get_search_page() {
		if ( ! $this->is_template_supported( 'is_search' ) ) {
			return null;
		}

		return add_query_arg( 's', 'example', home_url( '/' ) );
	}

	/**
	 * Gets a single date page URL, like https://example.com/?year=2018.
	 *
	 * @return string|null An example search page, or null.
	 */
	private function get_date_page() {
		if ( ! $this->is_template_supported( 'is_date' ) ) {
			return null;
		}

		return add_query_arg( 'year', gmdate( 'Y' ), home_url( '/' ) );
	}

	/**
	 * Gets whether the taxonomy supports AMP.
	 *
	 * This only gets the term IDs if they support AMP.
	 * If their taxonomy is unchecked in 'AMP Settings' > 'Supported Templates,' this does not return them.
	 * For example, if 'Categories' is unchecked.
	 * This can be overridden by passing the self::FLAG_NAME_FORCE_VALIDATION argument to the WP-CLI command.
	 *
	 * @param string $taxonomy The taxonomy.
	 * @return boolean Whether the taxonomy supports AMP.
	 */
	private function does_taxonomy_support_amp( $taxonomy ) {
		if ( 'post_tag' === $taxonomy ) {
			$taxonomy = 'tag';
		}
		$taxonomy_key        = 'is_' . $taxonomy;
		$custom_taxonomy_key = sprintf( 'is_tax[%s]', $taxonomy );
		return $this->is_template_supported( $taxonomy_key ) || $this->is_template_supported( $custom_taxonomy_key );
	}

	/**
	 * Gets the front-end links for taxonomy terms.
	 * For example, https://example.org/?cat=2
	 *
	 * @param string     $taxonomy The name of the taxonomy, like 'category' or 'post_tag'.
	 * @param int|string $offset The number at which to offset the query (optional).
	 * @param int        $number The maximum amount of links to get (optional).
	 * @return string[]  The term links, as an array of strings.
	 */
	private function get_taxonomy_links( $taxonomy, $offset = '', $number = 1 ) {
		return array_map(
			'get_term_link',
			get_terms(
				array_merge(
					compact( 'taxonomy', 'offset', 'number' ),
					[
						'orderby' => 'id',
					]
				)
			)
		);
	}

	/**
	 * Validates the URL, stores the results, and increments the counts.
	 *
	 * @param string $url  The URL to validate.
	 * @param string $type The type of template, post, or taxonomy.
	 */
	public function validate_and_store_url( $url, $type ) {
		$validity = AMP_Validation_Manager::validate_url_and_store( $url );

		/*
		 * If the request to validate this returns a WP_Error, return.
		 * One cause of an error is if the validation request results in a 404 response code.
		 */
		if ( is_wp_error( $validity ) ) {
			return $validity;
		}

		$validation_errors      = wp_list_pluck( $validity['results'], 'error' );
		$unaccepted_error_count = count(
			array_filter(
				$validation_errors,
				static function( $error ) {
					$validation_status = AMP_Validation_Error_Taxonomy::get_validation_error_sanitization( $error );
					return (
					AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACK_ACCEPTED_STATUS !== $validation_status['term_status']
					&&
					AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_NEW_ACCEPTED_STATUS !== $validation_status['term_status']
					);
				}
			)
		);

		if ( count( $validation_errors ) > 0 ) {
			$this->total_errors++;
		}
		if ( $unaccepted_error_count > 0 ) {
			$this->unaccepted_errors++;
		}

		$this->number_crawled++;

		if ( ! isset( $this->validity_by_type[ $type ] ) ) {
			$this->validity_by_type[ $type ] = [
				'valid' => 0,
				'total' => 0,
			];
		}
		$this->validity_by_type[ $type ]['total']++;
		if ( 0 === $unaccepted_error_count ) {
			$this->validity_by_type[ $type ]['valid']++;
		}

		return true;
	}
}

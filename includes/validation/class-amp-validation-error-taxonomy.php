<?php
/**
 * Class AMP_Validation_Error_Taxonomy
 *
 * @package AMP
 */

/**
 * Class AMP_Validation_Error_Taxonomy
 *
 * @since 1.0
 */
class AMP_Validation_Error_Taxonomy {

	/**
	 * The slug of the taxonomy to store AMP errors.
	 *
	 * @var string
	 */
	const TAXONOMY_SLUG = 'amp_validation_error';

	/**
	 * Acknowledged validation error bit mask.
	 *
	 * @var int
	 */
	const ACKNOWLEDGED_VALIDATION_ERROR_BIT_MASK = 2; // === 0b10.

	/**
	 * Accepted validation error bit mask.
	 *
	 * @var int
	 */
	const ACCEPTED_VALIDATION_ERROR_BIT_MASK = 1; // === 0b01.

	/**
	 * Term group for new validation_error terms which are rejected (not auto-accepted).
	 *
	 * @var int
	 */
	const VALIDATION_ERROR_NEW_REJECTED_STATUS = 0; // == 0b00 == ^ACKNOWLEDGED_VALIDATION_ERROR_BIT_MASK | ^ACCEPTED_VALIDATION_ERROR_BIT_MASK.

	/**
	 * Term group for new validation_error terms which are auto-accepted.
	 *
	 * @var int
	 */
	const VALIDATION_ERROR_NEW_ACCEPTED_STATUS = 1; // == 0b01 == ^ACKNOWLEDGED_VALIDATION_ERROR_BIT_MASK | ACCEPTED_VALIDATION_ERROR_BIT_MASK.

	/**
	 * Term group for validation_error terms that the accepts and thus can be sanitized and does not disable AMP.
	 *
	 * @var int
	 */
	const VALIDATION_ERROR_ACK_ACCEPTED_STATUS = 3; // == 0b11 == ACKNOWLEDGED_VALIDATION_ERROR_BIT_MASK | ACCEPTED_VALIDATION_ERROR_BIT_MASK.

	/**
	 * Term group for validation_error terms that the user flags as being blockers to enabling AMP.
	 *
	 * @var int
	 */
	const VALIDATION_ERROR_ACK_REJECTED_STATUS = 2; // == 0b10 == ACKNOWLEDGED_VALIDATION_ERROR_BIT_MASK | ^ACCEPTED_VALIDATION_ERROR_BIT_MASK.

	/**
	 * Action name for ignoring a validation error.
	 *
	 * @var string
	 */
	const VALIDATION_ERROR_ACCEPT_ACTION = 'amp_validation_error_accept';

	/**
	 * Action name for rejecting a validation error.
	 *
	 * @var string
	 */
	const VALIDATION_ERROR_REJECT_ACTION = 'amp_validation_error_reject';

	/**
	 * Action name for clearing empty validation error terms.
	 *
	 * @var string
	 */
	const VALIDATION_ERROR_CLEAR_EMPTY_ACTION = 'amp_validation_error_terms_clear_empty';

	/**
	 * Query var used when filtering by validation error status or passing updates.
	 *
	 * @var string
	 */
	const VALIDATION_ERROR_STATUS_QUERY_VAR = 'amp_validation_error_status';

	/**
	 * Query var used when filtering for the validation error type.
	 *
	 * @var string
	 */
	const VALIDATION_ERROR_TYPE_QUERY_VAR = 'amp_validation_error_type';

	/**
	 * Query var used for ordering list by error code.
	 *
	 * @var string
	 */
	const VALIDATION_DETAILS_ERROR_CODE_QUERY_VAR = 'amp_validation_code';

	/**
	 * Query var used to indicate how many terms were deleted.
	 *
	 * @var string
	 */
	const VALIDATION_ERRORS_CLEARED_QUERY_VAR = 'amp_validation_errors_cleared';

	/**
	 * The <option> value to not filter at all, like for 'All Statuses'.
	 *
	 * This is also used in WP_List_Table, like for the 'Bulk Actions' option.
	 * When this is present, this ensures that this isn't filtered.
	 *
	 * @var int
	 */
	const NO_FILTER_VALUE = '';

	/**
	 * The 'type' of error for invalid HTML elements, like <frame>.
	 *
	 * These usually have the 'code' of AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG.
	 * Except for AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG errors for a <script>, which have the JS_ERROR_TYPE.
	 * This allows filtering by type in the taxonomy page, like displaying only HTML element errors, or only CSS errors.
	 *
	 * @var string
	 */
	const HTML_ELEMENT_ERROR_TYPE = 'html_element_error';

	/**
	 * The 'type' of error for invalid HTML attributes.
	 *
	 * Banned attributes include i-amp-*.
	 * But on* attributes, like onclick, have the JS_ERROR_TYPE.
	 *
	 * @var string
	 */
	const HTML_ATTRIBUTE_ERROR_TYPE = 'html_attribute_error';

	/**
	 * The 'type' of error that applies to the error 'code' of AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG when the node is a <script>.
	 * This applies both when enqueuing a script, and when a <script> is echoed directly.
	 *
	 * @var string
	 */
	const JS_ERROR_TYPE = 'js_error';

	/**
	 * The 'type' of all CSS errors, no matter what the 'code'.
	 *
	 * @var string
	 */
	const CSS_ERROR_TYPE = 'css_error';

	/**
	 * The key for removed elements.
	 *
	 * @var string
	 */
	const REMOVED_ELEMENTS = 'removed_elements';

	/**
	 * The key for found elements and attributes.
	 *
	 * @var string
	 */
	const FOUND_ELEMENTS_AND_ATTRIBUTES = 'found_elements_and_attributes';

	/**
	 * The key for removed attributes.
	 *
	 * @var string
	 */
	const REMOVED_ATTRIBUTES = 'removed_attributes';

	/**
	 * The key in the response for the sources that have invalid output.
	 *
	 * @var string
	 */
	const SOURCES_INVALID_OUTPUT = 'sources_with_invalid_output';

	/**
	 * The key for removed sources.
	 *
	 * @var string
	 */
	const REMOVED_SOURCES = 'removed_sources';

	/**
	 * The key for the error status.
	 *
	 * @var string
	 */
	const ERROR_STATUS = 'error_status';

	/**
	 * Whether the terms_clauses filter should apply to a term query for validation errors to limit to a given status.
	 *
	 * This is set to false when calling wp_count_terms() for the admin menu and for the views.
	 *
	 * @see AMP_Validation_Manager::get_validation_error_count()
	 * @var bool
	 */
	protected static $should_filter_terms_clauses_for_error_validation_status;

	/**
	 * Registers the taxonomy to store the validation errors.
	 *
	 * @return void
	 */
	public static function register() {

		register_taxonomy(
			self::TAXONOMY_SLUG,
			AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
			[
				'labels'             => [
					'name'                  => _x( 'AMP Validation Error Index', 'taxonomy general name', 'amp' ),
					'singular_name'         => _x( 'AMP Validation Error', 'taxonomy singular name', 'amp' ),
					'search_items'          => __( 'Search AMP Validation Errors', 'amp' ),
					'all_items'             => __( 'All AMP Validation Errors', 'amp' ),
					'edit_item'             => __( 'Edit AMP Validation Error', 'amp' ),
					'update_item'           => __( 'Update AMP Validation Error', 'amp' ),
					'menu_name'             => __( 'Error Index', 'amp' ),
					'back_to_items'         => __( 'Back to AMP Validation Errors', 'amp' ),
					'popular_items'         => __( 'Frequent Validation Errors', 'amp' ),
					'view_item'             => __( 'View Validation Error', 'amp' ),
					'add_new_item'          => __( 'Add New Validation Error', 'amp' ), // Makes no sense.
					'new_item_name'         => __( 'New Validation Error Hash', 'amp' ), // Makes no sense.
					'not_found'             => __( 'No validation errors found.', 'amp' ),
					'no_terms'              => __( 'Validation Error', 'amp' ),
					'items_list_navigation' => __( 'Validation errors navigation', 'amp' ),
					'items_list'            => __( 'Validation errors list', 'amp' ),
					/* translators: Tab heading when selecting from the most used terms */
					'most_used'             => __( 'Most Used Validation Errors', 'amp' ),
				],
				'public'             => false,
				'show_ui'            => true, // @todo False because we need a custom UI.
				'show_tagcloud'      => false,
				'show_in_quick_edit' => false,
				'hierarchical'       => false, // Or true? Code could be the parent term?
				'show_in_menu'       => current_theme_supports( 'amp' ) && current_user_can( 'manage_options' ),
				'meta_box_cb'        => false,
				'capabilities'       => [
					// Note that delete_terms is needed so the checkbox (cb) table column will work.
					'assign_terms' => 'do_not_allow',
					'edit_terms'   => 'do_not_allow',
				],
			]
		);

		if ( is_admin() ) {
			self::add_admin_hooks();
		}

		self::accept_validation_errors( AMP_Core_Theme_Sanitizer::get_acceptable_errors( get_template() ) );
		add_filter( 'amp_validation_error_sanitized', [ __CLASS__, 'conditionally_change_sanitization' ], 10, 2 );
	}

	/**
	 * Get amp_validation_error taxonomy term by slug or error properties.
	 *
	 * @since 1.0
	 * @see get_term_by()
	 *
	 * @param string|array $error Slug for term or array of term data.
	 * @return WP_Term|false Queried term or false if no match.
	 */
	public static function get_term( $error ) {
		$slug = null;
		if ( is_string( $error ) ) {
			$slug = $error;
		} elseif ( is_array( $error ) ) {
			$term_data = self::prepare_validation_error_taxonomy_term( $error );
			$slug      = $term_data['slug'];
		}
		if ( ! $slug ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Method must be passed a term slug (string) or error attributes (array).', 'amp' ), '1.0' );
			return false;
		}

		return get_term_by( 'slug', $slug, self::TAXONOMY_SLUG );
	}

	/**
	 * Delete all amp_validation_error terms that have zero counts (no amp_validated_url posts associated with them).
	 *
	 * @since 1.0
	 *
	 * @return int Count of terms that were deleted.
	 */
	public static function delete_empty_terms() {
		global $wpdb;
		$empty_term_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy = %s AND count = 0", self::TAXONOMY_SLUG )
		);

		if ( empty( $empty_term_ids ) ) {
			return 0;
		}

		// Make sure the term counts are still accurate.
		wp_update_term_count_now( $empty_term_ids, self::TAXONOMY_SLUG );

		$deleted_count = 0;
		foreach ( $empty_term_ids as $term_id ) {
			$term = get_term( (int) $term_id, self::TAXONOMY_SLUG );

			// Skip if the term count was not actually 0.
			if ( ! $term || 0 !== $term->count ) {
				continue;
			}

			if ( true === wp_delete_term( $term->term_id, self::TAXONOMY_SLUG ) ) {
				$deleted_count++;
			}
		}
		return $deleted_count;
	}

	/**
	 * Sanitize term status(es).
	 *
	 * @param int|int[]|string $status One or more statuses (including comma-delimited string).
	 * @param array            $options {
	 *     Options.
	 *
	 *     @type bool $multiple Multiple, whether to extract more than one. Default false.
	 * }
	 * @return int|int[]|null Returns an integer unless the multiple option is passed. Null if invalid.
	 */
	public static function sanitize_term_status( $status, $options = [] ) {
		$multiple = ! empty( $options['multiple'] );

		// Catch case where an empty string is supplied. Prevent casting to 0.
		if ( ! is_numeric( $status ) && empty( $status ) ) {
			return $multiple ? [] : null;
		}

		if ( is_string( $status ) ) {
			$statuses = wp_parse_id_list( $status );
		} else {
			$statuses = array_map( 'absint', (array) $status );
		}

		$statuses = array_intersect(
			[
				self::VALIDATION_ERROR_NEW_REJECTED_STATUS,
				self::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
				self::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
				self::VALIDATION_ERROR_ACK_REJECTED_STATUS,
			],
			$statuses
		);
		$statuses = array_values( array_unique( $statuses ) );

		if ( ! $multiple ) {
			return array_shift( $statuses );
		}

		return $statuses;
	}

	/**
	 * Prepare term_group IN condition for SQL WHERE clause.
	 *
	 * @param int[] $groups Term groups.
	 * @return string SQL.
	 */
	public static function prepare_term_group_in_sql( $groups ) {
		global $wpdb;
		return $wpdb->prepare(
			'IN ( ' . implode( ', ', array_fill( 0, count( $groups ), '%d' ) ) . ' )',
			$groups
		);
	}

	/**
	 * Prepare a validation error for lookup or insertion as taxonomy term.
	 *
	 * @param array $error Validation error.
	 * @return array Term fields.
	 */
	public static function prepare_validation_error_taxonomy_term( $error ) {
		unset( $error['sources'] );
		ksort( $error );
		$description = wp_json_encode( $error );
		$term_slug   = md5( $description );
		return [
			'slug'        => $term_slug,
			'name'        => $term_slug,
			'description' => $description,
		];
	}

	/**
	 * Determine whether a validation error should be sanitized.
	 *
	 * @since 1.0
	 * @see AMP_Validation_Error_Taxonomy::get_validation_error_sanitization()
	 * @see AMP_Validation_Manager::is_sanitization_auto_accepted()
	 *
	 * @param array $error Validation error.
	 * @return bool Whether error should be sanitized.
	 */
	public static function is_validation_error_sanitized( $error ) {
		$sanitization = self::get_validation_error_sanitization( $error );
		return (
			self::VALIDATION_ERROR_ACK_ACCEPTED_STATUS === $sanitization['status']
			||
			self::VALIDATION_ERROR_NEW_ACCEPTED_STATUS === $sanitization['status']
		);
	}

	/**
	 * Get the validation error sanitization.
	 *
	 * @since 1.0
	 * @see AMP_Validation_Manager::is_sanitization_auto_accepted()
	 *
	 * @param array $error Validation error.
	 * @return array {
	 *     Validation error sanitization.
	 *
	 *     @type int          $status      Validation status.
	 *     @type int          $term_status The initial validation status prior to being overridden by previewing, option, or filter.
	 *     @type false|string $forced      If and how the status is overridden from its initial term status.
	 * }
	 */
	public static function get_validation_error_sanitization( $error ) {
		$term_data = self::prepare_validation_error_taxonomy_term( $error );
		$term      = self::get_term( $term_data['slug'] );
		$statuses  = [
			self::VALIDATION_ERROR_NEW_REJECTED_STATUS,
			self::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
			self::VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
			self::VALIDATION_ERROR_ACK_REJECTED_STATUS,
		];
		if ( ! empty( $term ) && in_array( $term->term_group, $statuses, true ) ) {
			$term_status = $term->term_group;
		} else {
			$term_status = AMP_Validation_Manager::is_sanitization_auto_accepted( $error ) ? self::VALIDATION_ERROR_NEW_ACCEPTED_STATUS : self::VALIDATION_ERROR_NEW_REJECTED_STATUS;
		}

		$forced = false;
		$status = $term_status;

		// See note in AMP_Validation_Manager::add_validation_error_sourcing() for why amp_validation_error_sanitized filter isn't used.
		if ( isset( AMP_Validation_Manager::$validation_error_status_overrides[ $term_data['slug'] ] ) ) {
			$status = AMP_Validation_Manager::$validation_error_status_overrides[ $term_data['slug'] ];
			$forced = 'with_preview';
		}

		/**
		 * Filters whether the validation error should be sanitized.
		 *
		 * Returning true this indicates that the validation error is acceptable
		 * and should not be considered a blocker to render AMP. Returning null
		 * means that the default status should be used.
		 *
		 * Note that the $node is not passed here to ensure that the filter can be
		 * applied on validation errors that have been stored. Likewise, the $sources
		 * are also omitted because these are only available during an explicit
		 * validation request and so they are not suitable for plugins to vary
		 * sanitization by.
		 *
		 * @since 1.0
		 * @see AMP_Validation_Manager::is_sanitization_auto_accepted() Which controls whether an error is initially accepted or rejected for sanitization.
		 *
		 * @param null|bool $sanitized Whether sanitized; this is initially null, and changing it to bool causes the validation error to be forced.
		 * @param array $error Validation error being sanitized.
		 */
		$sanitized = apply_filters( 'amp_validation_error_sanitized', null, $error );

		if ( null !== $sanitized ) {
			$forced = 'with_filter';
			$status = $sanitized ? self::VALIDATION_ERROR_ACK_ACCEPTED_STATUS : self::VALIDATION_ERROR_ACK_REJECTED_STATUS;
		}

		return compact( 'status', 'forced', 'term_status' );
	}

	/**
	 * Automatically (forcibly) accept validation errors that arise (that is, remove the invalid markup causing the validation errors).
	 *
	 * @since 1.0
	 * @see AMP_Core_Theme_Sanitizer::get_acceptable_errors()
	 *
	 * @param array|true $acceptable_errors Acceptable validation errors, where keys are codes and values are either `true` or sparse array to check as subset. If just true, then all validation errors are accepted.
	 */
	public static function accept_validation_errors( $acceptable_errors ) {
		if ( empty( $acceptable_errors ) ) {
			return;
		}
		add_filter(
			'amp_validation_error_sanitized',
			static function( $sanitized, $error ) use ( $acceptable_errors ) {
				if ( true === $acceptable_errors ) {
					return true;
				}

				if ( isset( $acceptable_errors[ $error['code'] ] ) ) {
					if ( true === $acceptable_errors[ $error['code'] ] ) {
						return true;
					}
					foreach ( $acceptable_errors[ $error['code'] ] as $acceptable_error_props ) {
						if ( AMP_Validation_Error_Taxonomy::is_array_subset( $error, $acceptable_error_props ) ) {
							return true;
						}
					}
				}
				return $sanitized;
			},
			10,
			2
		);
	}

	/**
	 * Check if one array is a sparse subset of another array.
	 *
	 * @param array $superset Superset array.
	 * @param array $subset   Subset array.
	 *
	 * @return bool Whether subset is contained in superset.
	 */
	public static function is_array_subset( $superset, $subset ) {
		foreach ( $subset as $key => $subset_value ) {
			if ( ! isset( $superset[ $key ] ) || gettype( $subset_value ) !== gettype( $superset[ $key ] ) ) {
				return false;
			}
			if ( is_array( $subset_value ) ) {
				if ( ! self::is_array_subset( $superset[ $key ], $subset_value ) ) {
					return false;
				}
			} elseif ( $superset[ $key ] !== $subset_value ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get the count of validation error terms, optionally restricted by term group (e.g. accepted or rejected).
	 *
	 * @param array $args  {
	 *    Args passed into wp_count_terms().
	 *
	 *     @type int|int[]|string $group        Term group(s), including comma-separated ID list.
	 * }
	 * @return int Term count.
	 */
	public static function get_validation_error_count( $args = [] ) {
		$args = array_merge(
			[
				'group' => null,
			],
			$args
		);

		$groups = null;
		if ( isset( $args['group'] ) ) {
			$groups = self::sanitize_term_status( $args['group'], [ 'multiple' => true ] );
		}

		$filter = static function( $clauses ) use ( $groups ) {
			$clauses['where'] .= ' AND t.term_group ' . AMP_Validation_Error_Taxonomy::prepare_term_group_in_sql( $groups );
			return $clauses;
		};
		if ( isset( $args['group'] ) ) {
			add_filter( 'terms_clauses', $filter );
		}
		self::$should_filter_terms_clauses_for_error_validation_status = false;
		$term_count = wp_count_terms( self::TAXONOMY_SLUG, $args );
		self::$should_filter_terms_clauses_for_error_validation_status = true;
		if ( isset( $args['group'] ) ) {
			remove_filter( 'terms_clauses', $filter );
		}
		return (int) $term_count;
	}

	/**
	 * Add support for querying posts by amp_validation_error_status and by error type.
	 *
	 * Add recognition of amp_validation_error_status query var for amp_validated_url post queries.
	 * Also, conditionally filter for error type, like js_error or css_error.
	 *
	 * @see WP_Tax_Query::get_sql_for_clause()
	 *
	 * @param string   $where SQL WHERE clause.
	 * @param WP_Query $query Query.
	 * @return string Modified WHERE clause.
	 */
	public static function filter_posts_where_for_validation_error_status( $where, WP_Query $query ) {
		global $wpdb;

		// If the post type is not correct, return the $where clause unchanged.
		if ( ! in_array( AMP_Validated_URL_Post_Type::POST_TYPE_SLUG, (array) $query->get( 'post_type' ), true ) ) {
			return $where;
		}

		$error_statuses = [];
		if ( false !== $query->get( self::VALIDATION_ERROR_STATUS_QUERY_VAR, false ) ) {
			$error_statuses = self::sanitize_term_status( $query->get( self::VALIDATION_ERROR_STATUS_QUERY_VAR ), [ 'multiple' => true ] );
		}
		$error_type = sanitize_key( $query->get( self::VALIDATION_ERROR_TYPE_QUERY_VAR ) );

		/*
		 * Selecting the 'All Statuses' <option> sends a value of '' to indicate that this should not filter.
		 */
		$is_error_status_present = ! empty( $error_statuses );
		$is_error_type_present   = in_array( $error_type, self::get_error_types(), true );

		// If neither the error status nor the type is present, there is no need to filter the $where clause.
		if ( ! $is_error_status_present && ! $is_error_type_present ) {
			return $where;
		}

		$sql_select = $wpdb->prepare(
			"
				SELECT 1
				FROM $wpdb->term_relationships
				INNER JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_taxonomy_id = $wpdb->term_relationships.term_taxonomy_id
				INNER JOIN $wpdb->terms ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id
				WHERE
					$wpdb->term_taxonomy.taxonomy = %s
					AND
					$wpdb->term_relationships.object_id = $wpdb->posts.ID
			",
			self::TAXONOMY_SLUG
		);

		if ( $is_error_status_present ) {
			$sql_select .= " AND $wpdb->terms.term_group " . self::prepare_term_group_in_sql( $error_statuses );
		}

		if ( $is_error_type_present ) {
			$sql_select .= $wpdb->prepare(
				" AND $wpdb->term_taxonomy.description LIKE %s ",
				'%"type":"' . $wpdb->esc_like( $error_type ) . '"%'
			);
		}

		$sql_select .= ' LIMIT 1 ';

		$where .= " AND ( $sql_select ) ";

		return $where;
	}

	/**
	 * Gets the AMP validation response.
	 *
	 * Returns the current validation errors the sanitizers found in rendering the page.
	 *
	 * @param array $validation_errors Validation errors.
	 * @return array The AMP validity of the markup.
	 */
	public static function summarize_validation_errors( $validation_errors ) {
		$results            = [];
		$removed_elements   = [];
		$removed_attributes = [];
		$removed_pis        = [];
		$invalid_sources    = [];
		foreach ( $validation_errors as $validation_error ) {
			$code = isset( $validation_error['code'] ) ? $validation_error['code'] : null;

			if ( AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG === $code ) {
				if ( ! isset( $removed_elements[ $validation_error['node_name'] ] ) ) {
					$removed_elements[ $validation_error['node_name'] ] = 0;
				}
				++$removed_elements[ $validation_error['node_name'] ];
			} elseif ( AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR === $code ) {
				if ( ! isset( $removed_attributes[ $validation_error['node_name'] ] ) ) {
					$removed_attributes[ $validation_error['node_name'] ] = 0;
				}
				++$removed_attributes[ $validation_error['node_name'] ];
			} elseif ( AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_PROCESSING_INSTRUCTION === $code ) {
				if ( ! isset( $removed_pis[ $validation_error['node_name'] ] ) ) {
					$removed_pis[ $validation_error['node_name'] ] = 0;
				}
				++$removed_pis[ $validation_error['node_name'] ];
			}

			if ( ! empty( $validation_error['sources'] ) ) {
				foreach ( $validation_error['sources'] as $source ) {
					if ( isset( $source['hook'] ) ) {
						$invalid_sources['hook'] = $source['hook'];
					}
					if ( isset( $source['type'], $source['name'] ) ) {
						$invalid_sources[ $source['type'] ][] = $source['name'];
					} elseif ( isset( $source['embed'] ) ) {
						$invalid_sources['embed'] = true;
					}
					if ( isset( $source['block_name'] ) ) {
						$invalid_sources['blocks'][] = $source['block_name'];
					}
				}

				// Remove core if there is a plugin or theme.
				if ( isset( $invalid_sources['core'] ) && ( isset( $invalid_sources['theme'] ) || isset( $invalid_sources['plugin'] ) ) ) {
					unset( $invalid_sources['core'] );
				}
			}
		}

		$results = array_merge(
			[
				self::SOURCES_INVALID_OUTPUT => $invalid_sources,
			],
			compact(
				'removed_elements',
				'removed_attributes',
				'removed_pis'
			),
			$results
		);

		return $results;
	}

	/**
	 * Add admin hooks.
	 */
	public static function add_admin_hooks() {
		add_filter( 'redirect_term_location', [ __CLASS__, 'add_term_filter_query_var' ], 10, 2 );
		add_action( 'load-edit-tags.php', [ __CLASS__, 'add_group_terms_clauses_filter' ] );
		add_action( 'load-edit-tags.php', [ __CLASS__, 'add_error_type_clauses_filter' ] );
		add_action( 'load-post.php', [ __CLASS__, 'add_error_type_clauses_filter' ] );
		add_action( 'load-edit-tags.php', [ __CLASS__, 'add_order_clauses_from_description_json' ] );
		add_action( 'load-post.php', [ __CLASS__, 'add_order_clauses_from_description_json' ] );
		add_action( sprintf( 'after-%s-table', self::TAXONOMY_SLUG ), [ __CLASS__, 'render_taxonomy_filters' ] );
		add_action( sprintf( 'after-%s-table', self::TAXONOMY_SLUG ), [ __CLASS__, 'render_link_to_invalid_urls_screen' ] );
		add_action(
			'load-edit-tags.php',
			static function() {
				add_filter( 'user_has_cap', [ __CLASS__, 'filter_user_has_cap_for_hiding_term_list_table_checkbox' ], 10, 3 );
			}
		);
		add_filter( 'terms_clauses', [ __CLASS__, 'filter_terms_clauses_for_description_search' ], 10, 3 );
		add_action( 'admin_notices', [ __CLASS__, 'add_admin_notices' ] );
		add_filter( self::TAXONOMY_SLUG . '_row_actions', [ __CLASS__, 'filter_tag_row_actions' ], 10, 2 );
		if ( get_taxonomy( self::TAXONOMY_SLUG )->show_in_menu ) {
			add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu_validation_error_item' ] );
		}
		add_action( 'parse_term_query', [ __CLASS__, 'parse_post_php_term_query' ] );
		add_filter( 'manage_' . self::TAXONOMY_SLUG . '_custom_column', [ __CLASS__, 'filter_manage_custom_columns' ], 10, 3 );
		add_filter( 'manage_' . AMP_Validated_URL_Post_Type::POST_TYPE_SLUG . '_sortable_columns', [ __CLASS__, 'add_single_post_sortable_columns' ] );
		add_filter( 'posts_where', [ __CLASS__, 'filter_posts_where_for_validation_error_status' ], 10, 2 );
		add_filter( 'post_action_' . self::VALIDATION_ERROR_REJECT_ACTION, [ __CLASS__, 'handle_single_url_page_bulk_and_inline_actions' ] );
		add_filter( 'post_action_' . self::VALIDATION_ERROR_ACCEPT_ACTION, [ __CLASS__, 'handle_single_url_page_bulk_and_inline_actions' ] );
		add_filter( 'handle_bulk_actions-edit-' . self::TAXONOMY_SLUG, [ __CLASS__, 'handle_validation_error_update' ], 10, 3 );
		add_action( 'load-edit-tags.php', [ __CLASS__, 'handle_inline_edit_request' ] );
		add_action( 'load-edit-tags.php', [ __CLASS__, 'handle_clear_empty_terms_request' ] );
		add_action( 'load-edit.php', [ __CLASS__, 'handle_inline_edit_request' ] );

		// Prevent query vars from persisting after redirect.
		add_filter(
			'removable_query_args',
			static function( $query_vars ) {
				$query_vars[] = 'amp_actioned';
				$query_vars[] = 'amp_actioned_count';
				$query_vars[] = 'amp_validation_errors_not_deleted';
				$query_vars[] = AMP_Validation_Error_Taxonomy::VALIDATION_ERRORS_CLEARED_QUERY_VAR;
				return $query_vars;
			}
		);

		// Add recognition of amp_validation_error_status and type query vars (which will only apply in admin since post type is not publicly_queryable).
		add_filter(
			'query_vars',
			static function( $query_vars ) {
				$query_vars[] = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_STATUS_QUERY_VAR;
				$query_vars[] = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_TYPE_QUERY_VAR;
				return $query_vars;
			}
		);

		// Default ordering terms by ID descending so that new terms appear at the top.
		add_filter(
			'get_terms_defaults',
			static function( $args, $taxonomies ) {
				if ( [ AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ] === $taxonomies ) {
					$args['orderby'] = 'term_id';
					$args['order']   = 'DESC';
				}
				return $args;
			},
			10,
			2
		);

		// Add bulk actions.
		add_filter(
			'bulk_actions-edit-' . self::TAXONOMY_SLUG,
			static function( $bulk_actions ) {
				unset( $bulk_actions['delete'] );
				$bulk_actions[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_ACCEPT_ACTION ] = __( 'Remove', 'amp' );
				$bulk_actions[ AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_REJECT_ACTION ] = __( 'Keep', 'amp' );
				return $bulk_actions;
			}
		);

		// Override the columns displayed for the validation error terms.
		add_filter(
			'manage_edit-' . self::TAXONOMY_SLUG . '_columns',
			static function( $old_columns ) {

				return [
					'cb'               => $old_columns['cb'],
					'error_code'       => esc_html__( 'Error', 'amp' ),
					'status'           => sprintf(
						'%s<span class="dashicons dashicons-editor-help tooltip-button" tabindex="0"></span><div class="tooltip" hidden data-content="%s"></div>',
						esc_html__( 'Markup Status', 'amp' ),
						esc_attr(
							sprintf(
								'<h3>%s</h3><p>%s</p>',
								esc_html__( 'Markup Status', 'amp' ),
								__( 'When invalid markup is removed it will not block a URL from being served as AMP; the validation error will be sanitized, where the offending markup is stripped from the response to ensure AMP validity. If invalid AMP markup is kept, then URLs is occurs on will not be served as AMP pages.', 'amp' )
							)
						)
					),
					'details'          => sprintf(
						'%s<span class="dashicons dashicons-editor-help tooltip-button" tabindex="0"></span><div class="tooltip" hidden data-content="%s"></div>',
						esc_html__( 'Context', 'amp' ),
						esc_attr(
							sprintf(
								'<h3>%s</h3><p>%s</p>',
								esc_html__( 'Context', 'amp' ),
								esc_html__( 'The parent element of where the error occurred.', 'amp' )
							)
						)
					),
					'error_type'       => esc_html__( 'Type', 'amp' ),
					'created_date_gmt' => esc_html__( 'Last Seen', 'amp' ),
					'posts'            => esc_html__( 'URLs', 'amp' ),
				];
			}
		);

		// Let the created date column sort by term ID.
		add_filter(
			'manage_edit-' . self::TAXONOMY_SLUG . '_sortable_columns',
			static function( $sortable_columns ) {
				$sortable_columns['created_date_gmt'] = 'term_id';
				$sortable_columns['error_type']       = AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_TYPE_QUERY_VAR;
				$sortable_columns['error_code']       = AMP_Validation_Error_Taxonomy::VALIDATION_DETAILS_ERROR_CODE_QUERY_VAR;
				return $sortable_columns;
			}
		);

		// Hide empty term addition form.
		add_action(
			'admin_enqueue_scripts',
			static function() {
				$current_screen = get_current_screen();
				if ( ! $current_screen ) {
					return;
				}

				if ( AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG === $current_screen->taxonomy ) {
					wp_add_inline_style(
						'common',
						'
					#col-left { display: none; }
					#col-right { float:none; width: auto; }

					/* Improve column widths */
					td.column-details pre, td.column-sources pre { overflow:auto; }
					th.column-created_date_gmt { width:15%; }
					th.column-status { width:15%; }
				'
					);

					wp_register_style(
						'amp-validation-tooltips',
						amp_get_asset_url( 'css/amp-validation-tooltips.css' ),
						[ 'wp-pointer' ],
						AMP__VERSION
					);

					wp_styles()->add_data( 'amp-validation-tooltips', 'rtl', 'replace' );

					$asset_file   = AMP__DIR__ . '/assets/js/amp-validation-tooltips.asset.php';
					$asset        = require $asset_file;
					$dependencies = $asset['dependencies'];
					$version      = $asset['version'];

					wp_register_script(
						'amp-validation-tooltips',
						amp_get_asset_url( 'js/amp-validation-tooltips.js' ),
						$dependencies,
						$version,
						true
					);

					wp_enqueue_style(
						'amp-validation-error-taxonomy',
						amp_get_asset_url( 'css/amp-validation-error-taxonomy.css' ),
						[ 'common', 'amp-validation-tooltips' ],
						AMP__VERSION
					);

					wp_styles()->add_data( 'amp-validation-error-taxonomy', 'rtl', 'replace' );

					wp_enqueue_script(
						'amp-validation-detail-toggle',
						amp_get_asset_url( 'js/amp-validation-detail-toggle.js' ),
						[ 'wp-dom-ready', 'wp-i18n', 'amp-validation-tooltips' ],
						AMP__VERSION,
						true
					);
				}

				if ( 'post' === $current_screen->base && AMP_Validated_URL_Post_Type::POST_TYPE_SLUG === $current_screen->post_type ) {
					wp_enqueue_style(
						'amp-validation-single-error-url',
						amp_get_asset_url( 'css/amp-validation-single-error-url.css' ),
						[ 'common' ],
						AMP__VERSION
					);

					wp_styles()->add_data( 'amp-validation-single-error-url', 'rtl', 'replace' );

					$asset_file   = AMP__DIR__ . '/assets/js/amp-validation-single-error-url-details.asset.php';
					$asset        = require $asset_file;
					$dependencies = $asset['dependencies'];
					$version      = $asset['version'];

					wp_enqueue_script(
						'amp-validation-single-error-url-details',
						amp_get_asset_url( 'js/amp-validation-single-error-url-details.js' ),
						$dependencies,
						$version,
						true
					);
				}
			}
		);

		// Make sure parent menu item is expanded when visiting the taxonomy term page.
		add_filter(
			'parent_file',
			static function( $parent_file ) {
				if ( get_current_screen()->taxonomy === AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG ) {
					$parent_file = AMP_Options_Manager::OPTION_NAME;
				}
				return $parent_file;
			},
			10,
			2
		);

		// Replace the primary column to be error instead of the removed name column..
		add_filter(
			'list_table_primary_column',
			static function( $primary_column ) {
				if ( get_current_screen() && AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG === get_current_screen()->taxonomy ) {
					$primary_column = 'error_code';
				}
				return $primary_column;
			}
		);

		// Jump to the requested line when opening the file editor.
		add_action(
			'admin_enqueue_scripts',
			function ( $hook_suffix ) {
				if ( ! isset( $_GET['line'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return;
				}
				$line = (int) $_GET['line']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				if ( 'plugin-editor.php' === $hook_suffix || 'theme-editor.php' === $hook_suffix ) {
					wp_add_inline_script(
						'wp-theme-plugin-editor',
						sprintf(
							'
								(
									function( originalInitCodeEditor ) {
										wp.themePluginEditor.initCodeEditor = function init() {
											originalInitCodeEditor.apply( this, arguments );
											this.instance.codemirror.doc.setCursor( %d - 1 );
										};
									}
								)( wp.themePluginEditor.initCodeEditor );
							',
							wp_json_encode( $line )
						)
					);
				}
			}
		);
	}

	/**
	 * Filter the term redirect URL, to possibly add query vars to filter by term status or type.
	 *
	 * On clicking the 'Filter' button on the 'AMP Validation Errors' taxonomy page,
	 * edit-tags.php processes the POST request that this submits.
	 * Then, it redirects to a URL to display the page again.
	 * This filter callback looks for a value for VALIDATION_ERROR_TYPE_QUERY_VAR in the $_POST request.
	 * That means that the user filtered by error type, like 'js_error'.
	 * It then passes that value to the redirect URL as a query var,
	 * So that the taxonomy page will be filtered for that error type.
	 *
	 * @see AMP_Validation_Error_Taxonomy::add_error_type_clauses_filter() for the filtering of the 'where' clause, based on the query vars.
	 * @param string      $url The $url to redirect to.
	 * @param WP_Taxonomy $tax The WP_Taxonomy object.
	 * @return string The filtered URL.
	 */
	public static function add_term_filter_query_var( $url, $tax ) {
		if (
			self::TAXONOMY_SLUG !== $tax->name
			||
			! isset( $_POST['post_type'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			||
			AMP_Validated_URL_Post_Type::POST_TYPE_SLUG !== $_POST['post_type'] // phpcs:ignore WordPress.Security.NonceVerification.Missing
		) {
			return $url;
		}

		// If the error type query var is valid, pass it along in the redirect $url.
		if (
			isset( $_POST[ self::VALIDATION_ERROR_TYPE_QUERY_VAR ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			&&
			in_array(
				$_POST[ self::VALIDATION_ERROR_TYPE_QUERY_VAR ], // phpcs:ignore WordPress.Security.NonceVerification.Missing
				array_merge( self::get_error_types(), [ (string) self::NO_FILTER_VALUE ] ),
				true
			)
		) {
			$url = add_query_arg(
				self::VALIDATION_ERROR_TYPE_QUERY_VAR,
				sanitize_key( wp_unslash( $_POST[ self::VALIDATION_ERROR_TYPE_QUERY_VAR ] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$url
			);
		}

		// If the error status query var is valid, pass it along in the redirect $url.
		$groups = [];
		if ( isset( $_POST[ self::VALIDATION_ERROR_STATUS_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$groups = self::sanitize_term_status( wp_unslash( $_POST[ self::VALIDATION_ERROR_STATUS_QUERY_VAR ] ), [ 'multiple' => true ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( ! empty( $groups ) ) {
			$url = add_query_arg(
				[ self::VALIDATION_ERROR_STATUS_QUERY_VAR => $groups ],
				$url
			);
		} else {
			$url = remove_query_arg( self::VALIDATION_ERROR_STATUS_QUERY_VAR, $url );
		}

		return $url;
	}

	/**
	 * Filter amp_validation_error term query by term group when requested.
	 */
	public static function add_group_terms_clauses_filter() {
		if ( self::TAXONOMY_SLUG !== get_current_screen()->taxonomy || ! isset( $_GET[ self::VALIDATION_ERROR_STATUS_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		self::$should_filter_terms_clauses_for_error_validation_status = true;
		$groups = self::sanitize_term_status( wp_unslash( $_GET[ self::VALIDATION_ERROR_STATUS_QUERY_VAR ] ), [ 'multiple' => true ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $groups ) ) {
			return;
		}
		add_filter(
			'terms_clauses',
			static function( $clauses, $taxonomies ) use ( $groups ) {
				if ( AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG === $taxonomies[0] && AMP_Validation_Error_Taxonomy::$should_filter_terms_clauses_for_error_validation_status ) {
					$clauses['where'] .= ' AND t.term_group ' . AMP_Validation_Error_Taxonomy::prepare_term_group_in_sql( $groups );
				}
				return $clauses;
			},
			10,
			2
		);
	}

	/**
	 * Adds filter for amp_validation_error term query by type, like in the 'AMP Validation Errors' taxonomy page.
	 *
	 * Filters 'load-edit-tags.php' and 'load-post.php',
	 * as the post.php page is like an edit-tags.php page,
	 * in that it has a WP_Terms_List_Table of validation error terms.
	 * Allows viewing only a certain type at a time, like only JS errors.
	 */
	public static function add_error_type_clauses_filter() {
		if ( self::TAXONOMY_SLUG !== get_current_screen()->taxonomy || ! isset( $_GET[ self::VALIDATION_ERROR_TYPE_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$type = sanitize_key( wp_unslash( $_GET[ self::VALIDATION_ERROR_TYPE_QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $type, self::get_error_types(), true ) ) {
			return;
		}

		add_filter(
			'terms_clauses',
			static function( $clauses, $taxonomies ) use ( $type ) {
				global $wpdb;
				if ( AMP_Validation_Error_Taxonomy::TAXONOMY_SLUG === $taxonomies[0] ) {
					$clauses['where'] .= $wpdb->prepare( ' AND tt.description LIKE %s', '%"type":"' . $wpdb->esc_like( $type ) . '"%' );
				}
				return $clauses;
			},
			10,
			2
		);
	}

	/**
	 * If ordering the list by a field in the description JSON, locate the best spot in the JSON string by which to sort alphabetically.
	 *
	 * This is used both on the taxonomy edit-tags.php page
	 * and the single URL post.php page, as that page also has a list table of terms.
	 */
	public static function add_order_clauses_from_description_json() {
		if ( self::TAXONOMY_SLUG !== get_current_screen()->taxonomy ) {
			return;
		}

		$sortable_column_vars = [
			self::VALIDATION_ERROR_TYPE_QUERY_VAR,
			self::VALIDATION_DETAILS_ERROR_CODE_QUERY_VAR,
		];

		if ( ! isset( $_GET['orderby'] ) || ! in_array( $_GET['orderby'], $sortable_column_vars, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		add_filter(
			'terms_clauses',
			static function( $clauses ) {
				global $wpdb;

				if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$clauses['order'] = 'DESC';
				} else {
					$clauses['order'] = 'ASC';
				}

				switch ( $_GET['orderby'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					case AMP_Validation_Error_Taxonomy::VALIDATION_ERROR_TYPE_QUERY_VAR:
						$clauses['orderby'] = $wpdb->prepare(
							'ORDER BY SUBSTR(tt.description, LOCATE(%s, tt.description, LOCATE(%s, tt.description)))',
							'"type":"',
							'}' // Start substr search after the first closing bracket to skip the "type" nested in the element_attributes object.
						);
						break;

					case AMP_Validation_Error_Taxonomy::VALIDATION_DETAILS_ERROR_CODE_QUERY_VAR:
						$clauses['orderby'] = $wpdb->prepare(
							'ORDER BY SUBSTR(tt.description, LOCATE(%s, tt.description))',
							'"code":"'
						);
						break;
				}

				return $clauses;
			},
			10,
			2
		);
	}

	/**
	 * Outputs the taxonomy filter UI for this taxonomy type.
	 *
	 * Similar to what appears on /wp-admin/edit.php for posts and pages,
	 * this outputs <select> elements to choose the error status and type,
	 * and a 'Filter' submit button that filters for them.
	 *
	 * @param string $taxonomy_name The name of the taxonomy.
	 */
	public static function render_taxonomy_filters( $taxonomy_name ) {
		if ( self::TAXONOMY_SLUG !== $taxonomy_name ) {
			return;
		}

		$div_id = 'amp-tax-filter';
		?>
		<div id="<?php echo esc_attr( $div_id ); ?>" class="alignleft actions">
			<?php
			self::render_error_status_filter();
			self::render_error_type_filter();
			submit_button( __( 'Apply Filter', 'amp' ), '', 'filter_action', false, [ 'id' => 'doaction' ] );
			self::render_clear_empty_button();
			?>
		</div>

		<script>
			( function ( $ ) {
				$( function() {
					// Move the filter UI after the 'Bulk Actions' <select>, as it looks like there's no way to do this with only an action.
					$( '#<?php echo $div_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>' ).insertAfter( $( '.tablenav.top .bulkactions' ) );
				} );
			} )( jQuery );
		</script>
		<?php
	}

	/**
	 * On the 'Error Index' screen, renders a link to the 'AMP Validated URLs' page.
	 *
	 * @see AMP_Validated_URL_Post_Type::render_link_to_error_index_screen()
	 *
	 * @param string $taxonomy_name The name of the taxonomy.
	 */
	public static function render_link_to_invalid_urls_screen( $taxonomy_name ) {
		if ( self::TAXONOMY_SLUG !== $taxonomy_name ) {
			return;
		}

		$post_type_object = get_post_type_object( AMP_Validated_URL_Post_Type::POST_TYPE_SLUG );
		if ( ! current_user_can( $post_type_object->cap->edit_posts ) ) {
			return;
		}

		$id = 'link-errors-url';

		printf(
			'<a href="%s" class="page-title-action" id="%s" hidden style="margin-left: 1rem;">%s</a>',
			esc_url(
				add_query_arg(
					'post_type',
					AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
					admin_url( 'edit.php' )
				)
			),
			esc_attr( $id ),
			esc_html__( 'View Validated URLs', 'amp' )
		);

		?>
		<script>
			jQuery( function( $ ) {
				// Move the link to after the heading, as it also looks like there's no action for this.
				$( <?php echo wp_json_encode( '#' . $id ); ?> ).removeAttr( 'hidden' ).insertAfter( $( '.wp-heading-inline' ) );
			} );
		</script>
		<?php
	}

	/**
	 * Renders the error status filter <select> element.
	 *
	 * There is a difference how the errors are counted, depending on which screen this is on.
	 * For example: Removed Markup (10).
	 * This status filter <select> element is rendered on the validation error post page (Errors by URL),
	 * and the validation error taxonomy page (Error Index).
	 * On the taxonomy page, this simply needs to count the number of terms with a given type.
	 * On the post page, this needs to count the number of posts that have at least one error of a given type.
	 */
	public static function render_error_status_filter() {
		global $wp_query;
		$screen_base = get_current_screen()->base;

		if ( 'edit-tags' === $screen_base ) {
			$total_term_count        = self::get_validation_error_count();
			$ack_rejected_term_count = self::get_validation_error_count( [ 'group' => [ self::VALIDATION_ERROR_ACK_REJECTED_STATUS ] ] );
			$ack_accepted_term_count = self::get_validation_error_count( [ 'group' => [ self::VALIDATION_ERROR_ACK_ACCEPTED_STATUS ] ] );
			$new_term_count          = $total_term_count - $ack_rejected_term_count - $ack_accepted_term_count;

		} elseif ( 'edit' === $screen_base ) {
			$args = [
				'post_type'              => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			];

			$error_type = sanitize_key( $wp_query->get( self::VALIDATION_ERROR_TYPE_QUERY_VAR ) );
			if ( $error_type && in_array( $error_type, self::get_error_types(), true ) ) {
				$args[ self::VALIDATION_ERROR_TYPE_QUERY_VAR ] = $error_type;
			}

			$with_new_query = new WP_Query(
				array_merge(
					$args,
					[
						self::VALIDATION_ERROR_STATUS_QUERY_VAR => [
							self::VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
							self::VALIDATION_ERROR_NEW_REJECTED_STATUS,
						],
					]
				)
			);
			$new_term_count = $with_new_query->found_posts;

			$with_rejected_query     = new WP_Query(
				array_merge(
					$args,
					[ self::VALIDATION_ERROR_STATUS_QUERY_VAR => self::VALIDATION_ERROR_ACK_REJECTED_STATUS ]
				)
			);
			$ack_rejected_term_count = $with_rejected_query->found_posts;

			$with_accepted_query     = new WP_Query(
				array_merge(
					$args,
					[ self::VALIDATION_ERROR_STATUS_QUERY_VAR => self::VALIDATION_ERROR_ACK_ACCEPTED_STATUS ]
				)
			);
			$ack_accepted_term_count = $with_accepted_query->found_posts;
		} else {
			return;
		}

		$selected_groups = [];
		if ( isset( $_GET[ self::VALIDATION_ERROR_STATUS_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$selected_groups = self::sanitize_term_status( $_GET[ self::VALIDATION_ERROR_STATUS_QUERY_VAR ], [ 'multiple' => true ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $selected_groups ) ) {
			sort( $selected_groups );
			$error_status_filter_value = implode( ',', $selected_groups );
		} else {
			$error_status_filter_value = self::NO_FILTER_VALUE;
		}

		?>
		<label for="<?php echo esc_attr( self::VALIDATION_ERROR_STATUS_QUERY_VAR ); ?>" class="screen-reader-text"><?php esc_html_e( 'Filter by markup status', 'amp' ); ?></label>
		<select name="<?php echo esc_attr( self::VALIDATION_ERROR_STATUS_QUERY_VAR ); ?>" id="<?php echo esc_attr( self::VALIDATION_ERROR_STATUS_QUERY_VAR ); ?>">
			<option value="<?php echo esc_attr( self::NO_FILTER_VALUE ); ?>"><?php esc_html_e( 'All statuses', 'amp' ); ?></option>
			<?php
			if ( 'edit' === $screen_base ) {
				$new_term_text = sprintf(
					/* translators: %s: the new term count. */
					_nx(
						'With new error <span class="count">(%s)</span>',
						'With new errors <span class="count">(%s)</span>', // @todo Should this really have variations for singular/plural? Should count be part of translated string?
						$new_term_count,
						'terms',
						'amp'
					),
					number_format_i18n( $new_term_count )
				);
			} else {
				$new_term_text = sprintf(
					/* translators: %s: the new term count. */
					_nx(
						'New error <span class="count">(%s)</span>',
						'New errors <span class="count">(%s)</span>', // @todo Should this really have variations for singular/plural? Should count be part of translated string?
						$new_term_count,
						'terms',
						'amp'
					),
					number_format_i18n( $new_term_count )
				);
			}
			$value = self::VALIDATION_ERROR_NEW_REJECTED_STATUS . ',' . self::VALIDATION_ERROR_NEW_ACCEPTED_STATUS;
			?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $error_status_filter_value, $value ); ?>><?php echo wp_kses_post( $new_term_text ); ?></option>
			<?php
			if ( 'edit' === $screen_base ) {
				$removed_term_text = sprintf(
					/* translators: %s: the accepted term count. */
					_nx(
						'With removed markup <span class="count">(%s)</span>',
						'With removed markup <span class="count">(%s)</span>', // @todo Should this really have variations for singular/plural? Should count be part of translated string?
						$ack_accepted_term_count,
						'terms',
						'amp'
					),
					number_format_i18n( $ack_accepted_term_count )
				);
			} else {
				$removed_term_text = sprintf(
					/* translators: %s: the accepted term count. */
					_nx(
						'Removed markup <span class="count">(%s)</span>',
						'Removed markup <span class="count">(%s)</span>', // @todo Should this really have variations for singular/plural? Should count be part of translated string?
						$ack_accepted_term_count,
						'terms',
						'amp'
					),
					number_format_i18n( $ack_accepted_term_count )
				);
			}
			$value = self::VALIDATION_ERROR_ACK_ACCEPTED_STATUS;
			?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $error_status_filter_value, $value ); ?>><?php echo wp_kses_post( $removed_term_text ); ?></option>
			<?php
			if ( 'edit' === $screen_base ) {
				$kept_term_text = sprintf(
					/* translators: %s: the rejected term count. */
					_nx(
						'With kept markup <span class="count">(%s)</span>',
						'With kept markup <span class="count">(%s)</span>', // @todo Should this really have variations for singular/plural? Should count be part of translated string?
						$ack_rejected_term_count,
						'terms',
						'amp'
					),
					number_format_i18n( $ack_rejected_term_count )
				);
			} else {
				$kept_term_text = sprintf(
					/* translators: %s: the rejected term count. */
					_nx(
						'Kept markup <span class="count">(%s)</span>',
						'Kept markup <span class="count">(%s)</span>', // @todo Should this really have variations for singular/plural? Should count be part of translated string?
						$ack_rejected_term_count,
						'terms',
						'amp'
					),
					number_format_i18n( $ack_rejected_term_count )
				);
			}
			$value = self::VALIDATION_ERROR_ACK_REJECTED_STATUS;
			?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $error_status_filter_value, $value ); ?>><?php echo wp_kses_post( $kept_term_text ); ?></option>
		</select>
		<?php
	}

	/**
	 * Gets all of the possible error types.
	 *
	 * @return array Error types.
	 */
	public static function get_error_types() {
		return [ self::HTML_ELEMENT_ERROR_TYPE, self::HTML_ATTRIBUTE_ERROR_TYPE, self::JS_ERROR_TYPE, self::CSS_ERROR_TYPE ];
	}

	/**
	 * Renders the filter for error type.
	 *
	 * This type filter <select> element is rendered on the validation error post page (Errors by URL),
	 * and the validation error taxonomy page (Error Index).
	 */
	public static function render_error_type_filter() {
		$error_type_filter_value = isset( $_GET[ self::VALIDATION_ERROR_TYPE_QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::VALIDATION_ERROR_TYPE_QUERY_VAR ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		/*
		 * On the 'Errors by URL' page, the <option> text should be different.
		 * For example, it should be 'With JS Errors' instead of 'JS Errors'.
		 */
		$screen_base = get_current_screen()->base;
		?>
		<label for="<?php echo esc_attr( self::VALIDATION_ERROR_TYPE_QUERY_VAR ); ?>" class="screen-reader-text"><?php esc_html_e( 'Filter by error type', 'amp' ); ?></label>
		<select name="<?php echo esc_attr( self::VALIDATION_ERROR_TYPE_QUERY_VAR ); ?>" id="<?php echo esc_attr( self::VALIDATION_ERROR_TYPE_QUERY_VAR ); ?>">
			<option value="<?php echo esc_attr( self::NO_FILTER_VALUE ); ?>">
				<?php esc_html_e( 'All types of invalid markup', 'amp' ); ?>
			</option>
			<option value="<?php echo esc_attr( self::HTML_ELEMENT_ERROR_TYPE ); ?>" <?php selected( $error_type_filter_value, self::HTML_ELEMENT_ERROR_TYPE ); ?>>
				<?php if ( 'edit' === $screen_base ) : ?>
					<?php esc_html_e( 'With invalid HTML elements', 'amp' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Invalid HTML elements', 'amp' ); ?>
				<?php endif; ?>
			</option>
			<option value="<?php echo esc_attr( self::HTML_ATTRIBUTE_ERROR_TYPE ); ?>" <?php selected( $error_type_filter_value, self::HTML_ATTRIBUTE_ERROR_TYPE ); ?>>
				<?php if ( 'edit' === $screen_base ) : ?>
					<?php esc_html_e( 'With invalid HTML attributes', 'amp' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Invalid HTML attributes', 'amp' ); ?>
				<?php endif; ?>
			</option>
			<option value="<?php echo esc_attr( self::JS_ERROR_TYPE ); ?>" <?php selected( $error_type_filter_value, self::JS_ERROR_TYPE ); ?>>
				<?php if ( 'edit' === $screen_base ) : ?>
					<?php esc_html_e( 'With invalid JS', 'amp' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Invalid JS', 'amp' ); ?>
				<?php endif; ?>
			</option>
			<option value="<?php echo esc_attr( self::CSS_ERROR_TYPE ); ?>" <?php selected( $error_type_filter_value, self::CSS_ERROR_TYPE ); ?>>
				<?php if ( 'edit' === $screen_base ) : ?>
					<?php esc_html_e( 'With invalid CSS', 'amp' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Invalid CSS', 'amp' ); ?>
				<?php endif; ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render the button for clearing empty taxonomy terms.
	 *
	 * If there are no terms with a 0 count then this outputs nothing.
	 */
	public static function render_clear_empty_button() {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_taxonomy WHERE taxonomy = %s AND count = 0", self::TAXONOMY_SLUG ) );
		if ( $count > 0 ) {
			wp_nonce_field( self::VALIDATION_ERROR_CLEAR_EMPTY_ACTION, self::VALIDATION_ERROR_CLEAR_EMPTY_ACTION . '_nonce', false );
			submit_button( __( 'Clear Empty', 'amp' ), '', self::VALIDATION_ERROR_CLEAR_EMPTY_ACTION, false );
		}
	}

	/**
	 * Prevent user from being able to delete validation errors in order to disable the checkbox on the post list table.
	 *
	 * Yes, this is not ideal.
	 *
	 * @param array $allcaps All caps.
	 * @param array $caps    Requested caps.
	 * @param array $args    Cap args.
	 * @return array All caps.
	 */
	public static function filter_user_has_cap_for_hiding_term_list_table_checkbox( $allcaps, $caps, $args ) {
		if ( isset( $args[0] ) && 'delete_term' === $args[0] ) {
			$term  = get_term( $args[2] );
			$error = json_decode( $term->description, true );
			if ( ! is_array( $error ) ) {
				return $allcaps;
			}
		}
		return $allcaps;
	}

	/**
	 * Include searching taxonomy term descriptions and sources term meta.
	 *
	 * @param array $clauses    Clauses.
	 * @param array $taxonomies Taxonomies.
	 * @param array $args       Args.
	 * @return array Clauses.
	 */
	public static function filter_terms_clauses_for_description_search( $clauses, $taxonomies, $args ) {
		global $wpdb;
		if ( ! empty( $args['search'] ) && in_array( self::TAXONOMY_SLUG, $taxonomies, true ) ) {
			$clauses['where'] = preg_replace(
				'#(?<=\()(?=\(t\.name LIKE \')#',
				$wpdb->prepare( '(tt.description LIKE %s) OR ', '%' . $wpdb->esc_like( $args['search'] ) . '%' ),
				$clauses['where']
			);
		}
		return $clauses;
	}

	/**
	 * Show notices for changes to amp_validation_error terms.
	 */
	public static function add_admin_notices() {
		if ( ! ( self::TAXONOMY_SLUG === get_current_screen()->taxonomy || AMP_Validated_URL_Post_Type::POST_TYPE_SLUG === get_current_screen()->post_type ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Show success messages for accepting/rejecting validation errors.
		if ( ! empty( $_GET['amp_actioned'] ) && ! empty( $_GET['amp_actioned_count'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$actioned = sanitize_key( $_GET['amp_actioned'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count    = (int) $_GET['amp_actioned_count']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message  = null;
			if ( self::VALIDATION_ERROR_ACCEPT_ACTION === $actioned ) {
				$message = sprintf(
					/* translators: %s is number of errors accepted */
					_n(
						'Removed %s instance of invalid markup. It will no longer block related URLs from being served as AMP.',
						'Removed %s instances of invalid markup. They will no longer block related URLs from being served as AMP.',
						number_format_i18n( $count ),
						'amp'
					),
					$count
				);
			} elseif ( self::VALIDATION_ERROR_REJECT_ACTION === $actioned ) {
				$message = sprintf(
					/* translators: %s is number of errors rejected */
					_n(
						'Kept %s instance of invalid markup. It will continue to block related URLs from being served as AMP.',
						'Kept %s instances of invalid markup. They will continue to block related URLs from being served as AMP.',
						number_format_i18n( $count ),
						'amp'
					),
					$count
				);
			}

			if ( $message ) {
				printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
			}
		}

		// Show success message for clearing empty terms.
		if ( isset( $_GET[ self::VALIDATION_ERRORS_CLEARED_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$cleared_count = (int) $_GET[ self::VALIDATION_ERRORS_CLEARED_QUERY_VAR ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s is the number of validation errors cleared */
						_n(
							'Cleared %s validation error for invalid markup that no longer occurs on the site.',
							'Cleared %s validation errors for invalid markup that no longer occur on the site.',
							$cleared_count,
							'amp'
						),
						number_format_i18n( $cleared_count )
					)
				)
			);
		}
	}

	/**
	 * Add row actions.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Term $tag     Tag.
	 * @return array Actions.
	 */
	public static function filter_tag_row_actions( $actions, WP_Term $tag ) {
		global $pagenow;

		$term_id = $tag->term_id;
		$term    = get_term( $tag->term_id ); // We don't want filter=display given by $tag.

		/*
		 * Hide deletion link since a validation error should only be removed once
		 * it no longer has an occurrence on the site. When a validated URL is re-checked
		 * and it no longer has this validation error, then the count will be decremented.
		 * When a validation error term no longer has a count, then it is hidden from the
		 * list table. A cron job could periodically delete terms that have no counts.
		 */
		unset( $actions['delete'] );

		if ( 'post.php' === $pagenow ) {
			$actions['details'] = sprintf(
				'<button type="button" aria-label="%s" class="single-url-detail-toggle button-link">%s</button>',
				esc_attr__( 'Toggle error details', 'amp' ),
				esc_html__( 'Details', 'amp' )
			);
		} else {
			$actions['details'] = sprintf(
				'<a href="%s">%s</a>',
				admin_url(
					add_query_arg(
						[
							self::TAXONOMY_SLUG => $term->name,
							'post_type'         => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
						],
						'edit.php'
					)
				),
				esc_html__( 'Details', 'amp' )
			);
		}

		// Only add the 'Remove' and 'Keep' links to the index page, not the individual URL page.
		if ( 'edit-tags.php' === $pagenow ) {
			$sanitization = self::get_validation_error_sanitization( json_decode( $term->description, true ) );

			if ( self::VALIDATION_ERROR_ACK_ACCEPTED_STATUS !== $sanitization['status'] ) {
				$actions[ self::VALIDATION_ERROR_ACCEPT_ACTION ] = sprintf(
					'<a href="%s">%s</a>',
					wp_nonce_url(
						add_query_arg( array_merge( [ 'action' => self::VALIDATION_ERROR_ACCEPT_ACTION ], compact( 'term_id' ) ) ),
						self::VALIDATION_ERROR_ACCEPT_ACTION
					),
					esc_html(
						self::VALIDATION_ERROR_NEW_ACCEPTED_STATUS === $sanitization['term_status'] ? __( 'Confirm removed', 'amp' ) : __( 'Remove', 'amp' )
					)
				);
			}
			if ( self::VALIDATION_ERROR_ACK_REJECTED_STATUS !== $sanitization['status'] ) {
				$actions[ self::VALIDATION_ERROR_REJECT_ACTION ] = sprintf(
					'<a href="%s">%s</a>',
					wp_nonce_url(
						add_query_arg( array_merge( [ 'action' => self::VALIDATION_ERROR_REJECT_ACTION ], compact( 'term_id' ) ) ),
						self::VALIDATION_ERROR_REJECT_ACTION
					),
					esc_html(
						self::VALIDATION_ERROR_NEW_REJECTED_STATUS === $sanitization['term_status'] ? __( 'Confirm kept', 'amp' ) : __( 'Keep', 'amp' )
					)
				);
			}
		}
		return $actions;
	}

	/**
	 * Show AMP validation errors under AMP admin menu.
	 */
	public static function add_admin_menu_validation_error_item() {
		$menu_item_label = esc_html__( 'Error Index', 'amp' );
		$new_error_count = self::get_validation_error_count(
			[
				'group' => [ self::VALIDATION_ERROR_NEW_REJECTED_STATUS, self::VALIDATION_ERROR_NEW_ACCEPTED_STATUS ],
			]
		);
		if ( $new_error_count ) {
			$menu_item_label .= ' <span class="awaiting-mod"><span class="pending-count">' . esc_html( number_format_i18n( $new_error_count ) ) . '</span></span>';
		}

		$taxonomy_caps = (object) get_taxonomy( self::TAXONOMY_SLUG )->cap; // Yes, cap is an object not an array.
		add_submenu_page(
			AMP_Options_Manager::OPTION_NAME,
			$menu_item_label,
			$menu_item_label,
			$taxonomy_caps->manage_terms,
			// The following esc_attr() is sadly needed due to <https://github.com/WordPress/wordpress-develop/blob/4.9.5/src/wp-admin/menu-header.php#L201>.
			esc_attr( 'edit-tags.php?taxonomy=' . self::TAXONOMY_SLUG . '&post_type=' . AMP_Validated_URL_Post_Type::POST_TYPE_SLUG )
		);
	}

	/**
	 * Parses the term query on post.php pages (single error URL).
	 *
	 * This post.php page for amp_validated_url is more like an edit-tags.php page,
	 * in that it has a WP_Terms_List_Table of terms (of type amp_validation_error).
	 * So this needs to only show the terms (errors) associated with this amp_validated_url post.
	 *
	 * @param WP_Term_Query $wp_term_query Instance of WP_Term_Query.
	 */
	public static function parse_post_php_term_query( $wp_term_query ) {
		global $pagenow;
		if ( ! is_admin() || 'post.php' !== $pagenow || ! isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Only set the query var if this is the validated URL post type.
		$post_id = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( AMP_Validated_URL_Post_Type::POST_TYPE_SLUG === get_post_type( $post_id ) ) {
			$wp_term_query->query_vars['object_ids'] = $post_id;
		}
	}

	/**
	 * Provides a reader-friendly string for a term's error type.
	 *
	 * @param string $error_type The error type from the term's validation error JSON.
	 * @return string Reader-friendly string.
	 */
	public static function get_reader_friendly_error_type_text( $error_type ) {
		switch ( $error_type ) {
			case 'js_error':
				return esc_html__( 'JS', 'amp' );

			case 'html_element_error':
				return esc_html__( 'HTML element', 'amp' );

			case 'html_attribute_error':
				return esc_html__( 'HTML attribute', 'amp' );

			case 'css_error':
				return esc_html__( 'CSS', 'amp' );

			default:
				return $error_type;
		}
	}

	/**
	 * Provides the label for the details summary element.
	 *
	 * @param array $validation_error Validation error data.
	 * @return string The label.
	 */
	public static function get_details_summary_label( $validation_error ) {
		if (
			AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR === $validation_error['code'] ||
			AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG === $validation_error['code'] ||
			AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_PROCESSING_INSTRUCTION === $validation_error['code'] ||
			'duplicate_element' === $validation_error['code']
		) {
			$summary_label = sprintf( '<%s>', $validation_error['parent_name'] );
		} elseif ( isset( $validation_error['node_name'] ) ) {
			$summary_label = sprintf( '<%s>', $validation_error['node_name'] );
		} else {
			$summary_label = '&hellip;';
		}

		return sprintf( '<code>%s</code>', esc_html( $summary_label ) );
	}

	/**
	 * Supply the content for the custom columns.
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 * @return string Content.
	 */
	public static function filter_manage_custom_columns( $content, $column_name, $term_id ) {
		global $pagenow;

		$term = get_term( $term_id );

		$validation_error = json_decode( $term->description, true );
		if ( ! isset( $validation_error['code'] ) ) {
			$validation_error['code'] = 'unknown';
		}

		switch ( $column_name ) {
			case 'error_code':
				if ( 'post.php' === $pagenow ) {
					$content .= sprintf(
						'<button type="button" aria-label="%s" class="single-url-detail-toggle">',
						esc_attr__( 'Toggle error details', 'amp' )
					);
					$content .= wp_kses_post( self::get_error_title_from_code( $validation_error ) );
				} else {
					$content .= '<p>';
					$content .= sprintf(
						'<a href="%s">%s',
						admin_url(
							add_query_arg(
								[
									self::TAXONOMY_SLUG => $term->name,
									'post_type'         => AMP_Validated_URL_Post_Type::POST_TYPE_SLUG,
								],
								'edit.php'
							)
						),
						wp_kses_post( self::get_error_title_from_code( $validation_error ) )
					);
				}

				if ( 'post.php' === $pagenow ) {
					$content .= '</button>';
				} else {
					$content .= '</a>';
					$content .= '</p>';
				}

				if ( isset( $validation_error['message'] ) ) {
					$content .= sprintf( '<p>%s</p>', esc_html( $validation_error['message'] ) );
				}

				break;
			case 'status':
				// Output whether the validation error has been seen via hidden field since we can't set the 'new' class on the <tr> directly.
				// This will get read via amp-validated-url-post-edit-screen.js.
				$is_new   = ! ( $term->term_group & self::ACKNOWLEDGED_VALIDATION_ERROR_BIT_MASK );
				$content .= sprintf( '<input class="amp-validation-error-new" type="hidden" value="%d">', (int) $is_new );

				$is_removed = (bool) ( $term->term_group & self::ACCEPTED_VALIDATION_ERROR_BIT_MASK );

				if ( 'post.php' === $pagenow ) {
					$status_select_name = sprintf( '%s[%s]', AMP_Validation_Manager::VALIDATION_ERROR_TERM_STATUS_QUERY_VAR, $term->slug );

					if ( $term->term_group & self::ACCEPTED_VALIDATION_ERROR_BIT_MASK ) {
						$img_src = 'amp-logo-green';
					} else {
						$img_src = 'amp-logo-red';
					}

					ob_start();
					?>
					<div class="amp-validation-error-status-dropdown">
						<img src="<?php echo esc_url( amp_get_asset_url( 'images/' . $img_src . '.svg' ) ); ?>">
						<label for="<?php echo esc_attr( $status_select_name ); ?>" class="screen-reader-text">
							<?php esc_html_e( 'Markup Status', 'amp' ); ?>
						</label>
						<select class="amp-validation-error-status" id="<?php echo esc_attr( $status_select_name ); ?>" name="<?php echo esc_attr( $status_select_name ); ?>">
							<option value="<?php echo esc_attr( self::VALIDATION_ERROR_ACK_ACCEPTED_STATUS ); ?>" <?php selected( $is_removed ); ?> data-status-icon="<?php echo esc_url( amp_get_asset_url( 'images/amp-logo-green.svg' ) ); ?>">
								<?php esc_html_e( 'Removed', 'amp' ); ?>
							</option>
							<option value="<?php echo esc_attr( self::VALIDATION_ERROR_ACK_REJECTED_STATUS ); ?>" <?php selected( ! $is_removed ); ?> data-status-icon="<?php echo esc_url( amp_get_asset_url( 'images/amp-logo-red.svg' ) ); ?>">
								<?php esc_html_e( 'Kept', 'amp' ); ?>
							</option>
						</select>
						</div>
					<?php
					$content .= ob_get_clean();
				} else {
					$sanitization = self::get_validation_error_sanitization( $validation_error );
					$content     .= self::get_status_text_with_icon( $sanitization );
					$content     .= sprintf( '<input class="amp-validation-error-status" type="hidden" value="%d">', (int) $is_removed );
				}
				break;
			case 'created_date_gmt':
				$created_datetime = null;
				$created_date_gmt = get_term_meta( $term_id, 'created_date_gmt', true );
				if ( $created_date_gmt ) {
					try {
						$created_datetime = new DateTime( $created_date_gmt, new DateTimeZone( 'UTC' ) );
						$timezone_string  = get_option( 'timezone_string' );
						if ( ! $timezone_string && get_option( 'gmt_offset' ) ) {
							$timezone_string = timezone_name_from_abbr( '', get_option( 'gmt_offset' ) * HOUR_IN_SECONDS, false );
						}
						if ( $timezone_string ) {
							$created_datetime->setTimezone( new DateTimeZone( get_option( 'timezone_string' ) ) );
						}
					} catch ( Exception $e ) {
						unset( $e );
					}
				}
				if ( ! $created_datetime ) {
					$time_ago = __( 'n/a', 'amp' );
				} else {
					$time_ago = sprintf(
						'<abbr title="%s">%s</abbr>',
						esc_attr(
							$created_datetime->format(
								/* translators: localized date and time format, see http://php.net/date */
								__( 'F j, Y g:i a', 'amp' )
							)
						),
						/* translators: %s: the human-readable time difference. */
						esc_html( sprintf( __( '%s ago', 'amp' ), human_time_diff( $created_datetime->getTimestamp() ) ) )
					);
				}

				if ( $created_datetime ) {
					$time_ago = sprintf(
						'<time datetime="%s">%s</time>',
						$created_datetime->format( 'c' ),
						$time_ago
					);
				}
				$content .= $time_ago;

				break;
			case 'details':
				if ( 'post.php' === $pagenow ) {
					return self::render_single_url_error_details( $validation_error, $term );
				}

				if ( isset( $validation_error['parent_name'] ) ) {
					$summary = self::get_details_summary_label( $validation_error );

					unset( $validation_error['error_type'], $validation_error['parent_name'] );

					$attributes         = [];
					$attributes_heading = '';
					if ( ! empty( $validation_error['node_attributes'] ) ) {
						$attributes_heading = sprintf( '<div class="details-attributes__title">%s:</div>', esc_html( self::get_source_key_label( 'node_attributes', $validation_error ) ) );
						$attributes         = $validation_error['node_attributes'];
					} elseif ( ! empty( $validation_error['element_attributes'] ) ) {
						$attributes         = $validation_error['element_attributes'];
						$attributes_heading = sprintf( '<div class="details-attributes__title">%s:</div>', esc_html( self::get_source_key_label( 'element_attributes', $validation_error ) ) );
					}

					if ( empty( $attributes ) ) {
						$content .= $summary;
					} else {
						$content  = '<details>';
						$content .= '<summary class="details-attributes__summary">';
						$content .= $summary;
						$content .= '</summary>';

						$content .= $attributes_heading;
						$content .= '<ul class="details-attributes__list">';

						foreach ( $attributes as $attr => $value ) {
							$content .= sprintf( '<li><span class="details-attributes__attr">%s</span>', esc_html( $attr ) );

							if ( ! empty( $value ) ) {
								$content .= sprintf( ': <span class="details-attributes__value">%s</span>', esc_html( $value ) );
							}

							$content .= '</li>';
						}

						$content .= '</ul>';
						$content .= '</details>';
					}
				}

				break;
			case 'sources_with_invalid_output':
				if ( ! isset( $_GET['post'], $_GET['action'] ) || 'edit' !== $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					break;
				}
				$url_post_id       = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$validation_errors = AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( $url_post_id );
				$validation_errors = array_filter(
					$validation_errors,
					static function( $error ) use ( $term ) {
						return $error['term']->term_id === $term->term_id;
					}
				);
				$error_summary     = self::summarize_validation_errors( wp_list_pluck( $validation_errors, 'data' ) );
				AMP_Validated_URL_Post_Type::render_sources_column( $error_summary, $url_post_id );

				break;
			case 'error_type':
				if ( isset( $validation_error['type'] ) ) {
					$text = self::get_reader_friendly_error_type_text( $validation_error['type'] );
					if ( 'post.php' === $pagenow ) {
						$content .= sprintf(
							'<p data-error-type="%s">%s</p>',
							isset( $validation_error['type'] ) ? $validation_error['type'] : '',
							esc_html( $text )
						);
					} else {
						$content .= $text;
					}
				} else {
					$content .= esc_html__( 'Misc', 'amp' );
				}
				break;
		}
		return $content;
	}

	/**
	 * Adds post columns to the /wp-admin/post.php page for amp_validated_url.
	 *
	 * @param array $sortable_columns The sortable columns.
	 * @return array $sortable_columns The filtered sortable columns.
	 */
	public static function add_single_post_sortable_columns( $sortable_columns ) {
		return array_merge(
			$sortable_columns,
			[
				'error_code' => self::VALIDATION_DETAILS_ERROR_CODE_QUERY_VAR,
				'error_type' => self::VALIDATION_ERROR_TYPE_QUERY_VAR,
			]
		);
	}

	/**
	 * Renders error details when viewing a single URL page.
	 *
	 * @param array   $validation_error Validation error data.
	 * @param WP_Term $term The validation error term.
	 * @return string HTML for the details section.
	 */
	public static function render_single_url_error_details( $validation_error, $term ) {
		// Get the sources, if they exist.
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$validation_errors = AMP_Validated_URL_Post_Type::get_invalid_url_validation_errors( (int) $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			foreach ( $validation_errors as $error ) {
				if ( isset( $error['data']['sources'], $error['term']->term_id ) && $error['term']->term_id === $term->term_id ) {
					$validation_error['sources'] = $error['data']['sources'];
					break;
				}
			}
		}

		ob_start();
		?>

		<dl class="detailed">
			<dt><?php esc_html_e( 'Information', 'amp' ); ?></dt>
			<dd class="detailed">
				<p>
					<?php if ( isset( $validation_error['type'] ) && self::JS_ERROR_TYPE === $validation_error['type'] ) : ?>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: 1: script,  2: Documentation URL, 3: Documentation URL, 4: Documentation URL, 5: onclick, 6: Documentation URL, 7: amp-bind, 8: Documentation URL, 9: amp-script */
								__( 'Arbitrary JavaScript is not allowed in AMP. You cannot use JS %1$s tags unless they are for loading <a href="%2$s">AMP components</a> (which the AMP plugin will add for you automatically). In order for a page to be served as AMP, the invalid JS code must be removed from the page. Learn more about <a href="%3$s">how AMP works</a>. As an alternative to using custom JS, please consider using a pre-built AMP functionality, including <a href="%4$s">actions and events</a> (as opposed to JS event handler attributes like %5$s) and the <a href="%6$s">%7$s</a> component; you may also add custom JS if encapsulated in the <a href="%8$s">%9$s</a>.', 'amp' ),
								'<code>&lt;script&gt;</code>',
								'https://amp.dev/documentation/components/',
								'https://amp.dev/about/how-amp-works/',
								'https://amp.dev/documentation/guides-and-tutorials/learn/amp-actions-and-events/',
								'<code>onclick</code>',
								'https://amp.dev/documentation/components/amp-bind/',
								'amp-bind',
								'https://amp.dev/documentation/components/amp-script/',
								'amp-script'
							)
						)
						?>
					<?php elseif ( isset( $validation_error['type'] ) && self::CSS_ERROR_TYPE === $validation_error['type'] ) : ?>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: 1: Documentation URL, 2: Documentation URL, 3: !important */
								__( 'AMP allows you to <a href="%1$s">style your pages using CSS</a> in much the same way as regular HTML pages, however there are some <a href="%2$s">restrictions</a>. Nevertheless, the AMP plugin automatically inlines external stylesheets, transforms %3$s qualifiers, and uses tree shaking to remove the majority of CSS rules that do not apply to the current page. Nevertheless, AMP does have a 50KB limit and tree shaking cannot always reduce the amount of CSS under this limit; when this happens an excessive CSS error will result.', 'amp' ),
								'https://amp.dev/documentation/guides-and-tutorials/develop/style_and_layout/',
								'https://amp.dev/documentation/guides-and-tutorials/develop/style_and_layout/style_pages/',
								'<code>!important</code>'
							)
						)
						?>
					<?php else : ?>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: 1: Documentation URL, 2: Documentation URL. */
								__( 'AMP has specific set of allowed elements and attributes that are allowed in valid AMP pages. Learn about the <a href="%1$s">AMP HTML specification</a>. If an element or attribute is not allowed in AMP, it must be removed for the page to <a href="%2$s">cached and be eligible for prerendering</a>.', 'amp' ),
								'https://amp.dev/documentation/guides-and-tutorials/learn/spec/amphtml/',
								'https://amp.dev/documentation/guides-and-tutorials/learn/amp-caches-and-cors/how_amp_pages_are_cached/'
							)
						)
						?>
					<?php endif; ?>
				</p>
				<p>
					<?php echo wp_kses_post( __( 'If you <strong>remove</strong> the invalid markup then it will not block this page from being served as AMP. Note that you need to check what impact the removal of the invalid markup has on the page to see if the result is acceptable. If you <strong>keep</strong> the invalid markup, then the page will not be served as AMP.', 'amp' ) ); ?>
				</p>
			</dd>

			<?php if ( AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG === $validation_error['code'] && isset( $validation_error['node_attributes'] ) ) : ?>
				<dt><?php esc_html_e( 'Invalid markup', 'amp' ); ?></dt>
				<dd class="detailed">
					<code>
						<mark>
						<?php
						echo '&lt;' . esc_html( $validation_error['node_name'] );
						if ( count( $validation_error['node_attributes'] ) > 0 ) {
							echo ' &hellip; ';
						}
						echo '&gt;';
						?>
						</mark>
					</code>
				</dd>
			<?php elseif ( AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR === $validation_error['code'] && isset( $validation_error['element_attributes'] ) ) : ?>
				<dt><?php esc_html_e( 'Invalid markup', 'amp' ); ?></dt>
				<dd class="detailed">
					<code>
						<?php
						echo '&lt;' . esc_html( $validation_error['parent_name'] );
						if ( count( $validation_error['element_attributes'] ) > 1 ) {
							echo ' &hellip;';
						}
						echo '<mark>';
						printf( ' %s="%s"', esc_html( $validation_error['node_name'] ), esc_html( $validation_error['element_attributes'][ $validation_error['node_name'] ] ) );
						echo '</mark>';
						if ( count( $validation_error['element_attributes'] ) > 1 ) {
							echo ' &hellip;';
						}
						echo '&gt;';
						?>
					</code>
				</dd>
			<?php endif; ?>

			<?php foreach ( $validation_error as $key => $value ) : ?>
				<?php
				$is_element_attributes = ( 'node_attributes' === $key || 'element_attributes' === $key );
				if ( $is_element_attributes && empty( $value ) ) {
					continue;
				}
				if ( in_array( $key, [ 'code', 'type', 'property_value' ], true ) ) {
					continue; // Handled above.
				}
				?>
				<dt><?php echo esc_html( self::get_source_key_label( $key, $validation_error ) ); ?></dt>
				<dd class="detailed">
					<?php if ( in_array( $key, [ 'node_name', 'parent_name' ], true ) ) : ?>
						<code><?php echo esc_html( $value ); ?></code>
					<?php elseif ( 'property_name' === $key ) : ?>
						<?php
						if ( isset( $validation_error['property_value'] ) && is_scalar( $validation_error['property_value'] ) ) {
							printf(
								'<code>%s: %s</code>',
								esc_html( $value ),
								esc_html( $validation_error['property_value'] )
							);
						} else {
							printf( '<code>%s</code>', esc_html( $value ) );
						}
						?>
					<?php elseif ( 'text' === $key ) : ?>
						<details>
							<summary>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s is the byte count */
										_n(
											'%s byte',
											'%s bytes',
											strlen( $value ),
											'amp'
										),
										number_format_i18n( strlen( $value ) )
									)
								);
								?>
							</summary>
							<pre><?php echo esc_html( $value ); ?></pre>
						</details>
					<?php elseif ( 'sources' === $key ) : ?>
						<?php self::render_sources( $value ); ?>
					<?php elseif ( $is_element_attributes ) : ?>
						<table class="element-attributes">
							<?php foreach ( $value as $attr_name => $attr_value ) : ?>
								<tr>
								<?php
								$attr_name_class = empty( $attr_value ) ? '' : 'has-attr-value';
								printf( '<th class="%1$s"><code>%2$s</code></th>', esc_attr( $attr_name_class ), esc_html( $attr_name ) );
								echo '<td>';
								if ( ! empty( $attr_value ) ) {
									echo '<code>';
									$is_url = in_array( $attr_name, [ 'href', 'src' ], true );
									if ( $is_url ) {
										// Remove non-helpful normalized version.
										$url_query = wp_parse_url( $attr_value, PHP_URL_QUERY );
										if ( $url_query && false !== strpos( 'ver=__normalized__', $url_query ) ) {
											$attr_value = remove_query_arg( 'ver', $attr_value );
										}

										printf( '<a href="%s" target="_blank">', esc_url( $attr_value ) );
									}
									echo esc_html( $attr_value );
									if ( $is_url ) {
										echo '</a>';
									}
									echo '</code>';
								}

								echo '</td>';
								?>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php elseif ( is_array( $value ) ) : ?>
						<?php foreach ( $value as $value_key => $attr ) : ?>
							<?php
							printf( '<strong>%s</strong>', esc_html( $value_key ) );
							if ( ! empty( $attr ) ) :
								printf( ': %s', esc_html( $attr ) );
							endif;
							?>
							<br />
						<?php endforeach; ?>
					<?php elseif ( is_string( $value ) ) : ?>
						<?php echo esc_html( $value ); ?>
					<?php endif; ?>
				</dd>
			<?php endforeach; ?>
		</dl>

		<?php

		return sprintf(
			'<details open class="details-attributes"><summary class="details-attributes__summary">%s</summary>%s</details>',
			self::get_details_summary_label( $validation_error ),
			ob_get_clean()
		);
	}

	/**
	 * Find a plugin from a slug.
	 *
	 * A slug is a plugin directory name like 'amp' or if the plugin is just a single file, then the PHP file in
	 * the plugins directory.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return array|null
	 */
	public static function get_plugin_from_slug( $plugin_slug ) {
		$plugins = get_plugins();
		if ( isset( $plugins[ $plugin_slug ] ) ) {
			return [
				'name' => $plugin_slug,
				'data' => $plugins[ $plugin_slug ],
			];
		}
		foreach ( $plugins as $plugin_file => $plugin_data ) {
			if ( strtok( $plugin_file, '/' ) === $plugin_slug ) {
				return [
					'name' => $plugin_file,
					'data' => $plugin_data,
				];
			}
		}
		return null;
	}

	/**
	 * Get the URL for opening the file for a AMP validation error in an external editor.
	 *
	 * @since 1.4
	 *
	 * @param array $source Source for AMP validation error.
	 * @return string|null File editor URL or null if not available.
	 */
	private static function get_file_editor_url( $source ) {
		if ( ! isset( $source['file'], $source['line'], $source['type'], $source['name'] ) ) {
			return null;
		}

		$edit_url = null;

		/**
		 * Filters the template for the URL for linking to an external editor to open a file for editing.
		 *
		 * Users of IDEs that support opening files in via web protocols can use this filter to override
		 * the edit link to result in their editor opening rather than the theme/plugin editor.
		 *
		 * The initial filtered value is null, requiring extension plugins to supply the URL template
		 * string themselves. If no template string is provided, links to the theme/plugin editors will
		 * be provided if available. For example, for an extension plugin to cause file edit links to
		 * open in PhpStorm, the following filter can be used:
		 *
		 *     add_filter( 'amp_validation_error_source_file_editor_url_template', function () {
		 *         return 'phpstorm://open?file={{file}}&line={{line}}';
		 *     } );
		 *
		 * For a template to be considered, the string '{{file}}' must be present in the filtered value.
		 *
		 * @since 1.4
		 *
		 * @param string|null $editor_url_template Editor URL template.
		 */
		$editor_url_template = apply_filters( 'amp_validation_error_source_file_editor_url_template', null );

		// Supply the file path to the editor template.
		if ( null !== $editor_url_template && false !== strpos( $editor_url_template, '{{file}}' ) ) {
			$file_path = null;
			if ( 'core' === $source['type'] ) {
				if ( 'wp-includes' === $source['name'] ) {
					$file_path = ABSPATH . WPINC . '/' . $source['file'];
				} elseif ( 'wp-admin' === $source['name'] ) {
					$file_path = ABSPATH . 'wp-admin/' . $source['file'];
				}
			} elseif ( 'plugin' === $source['type'] ) {
				$file_path = WP_PLUGIN_DIR . '/' . $source['name'];
				if ( $source['name'] !== $source['file'] ) {
					$file_path .= '/' . $source['file'];
				}
			} elseif ( 'mu-plugin' === $source['type'] ) {
				$file_path = WPMU_PLUGIN_DIR . '/' . $source['name'];
			} elseif ( 'theme' === $source['type'] ) {
				$theme = wp_get_theme( $source['name'] );
				if ( $theme instanceof WP_Theme && ! $theme->errors() ) {
					$file_path = $theme->get_stylesheet_directory() . '/' . $source['file'];
				}
			}

			if ( $file_path && file_exists( $file_path ) ) {
				/**
				 * Filters the file path to be opened in an external editor for a given AMP validation error source.
				 *
				 * This is useful to map the file path from inside of a Docker container or VM to the host machine.
				 *
				 * @since 1.4
				 *
				 * @param string|null $editor_url_template Editor URL template.
				 * @param array       $source              Source information.
				 */
				$file_path = apply_filters( 'amp_validation_error_source_file_path', $file_path, $source );
				if ( $file_path ) {
					$edit_url = str_replace(
						[
							'{{file}}',
							'{{line}}',
						],
						[
							rawurlencode( $file_path ),
							rawurlencode( $source['line'] ),
						],
						$editor_url_template
					);
				}
			}
		}

		// Fall back to using the theme/plugin editors if no external editor is offered.
		if ( ! $edit_url ) {
			if ( 'plugin' === $source['type'] && current_user_can( 'edit_plugins' ) ) {
				$plugin = self::get_plugin_from_slug( $source['name'] );
				if ( $plugin ) {
					$file = $source['file'];

					// Prepend the plugin directory name to the file name as the plugin editor requires.
					$i = strpos( $plugin['name'], '/' );
					if ( false !== $i ) {
						$file = substr( $plugin['name'], 0, $i ) . '/' . $file;
					}

					$edit_url = add_query_arg(
						[
							'plugin' => rawurlencode( $plugin['name'] ),
							'file'   => rawurlencode( $file ),
							'line'   => rawurlencode( $source['line'] ),
						],
						admin_url( 'plugin-editor.php' )
					);
				}
			} elseif ( 'theme' === $source['type'] && current_user_can( 'edit_themes' ) ) {
				$edit_url = add_query_arg(
					[
						'file'  => rawurlencode( $source['file'] ),
						'theme' => rawurlencode( $source['name'] ),
						'line'  => rawurlencode( $source['line'] ),
					],
					admin_url( 'theme-editor.php' )
				);
			}
		}

		return $edit_url;
	}

	/**
	 * Render source name.
	 *
	 * @since 1.4
	 *
	 * @param string $name Name.
	 * @param string $type Type.
	 */
	private static function render_source_name( $name, $type ) {
		$nicename = null;
		switch ( $type ) {
			case 'theme':
				$theme = wp_get_theme( $name );
				if ( ! $theme->errors() ) {
					$nicename = $theme->get( 'Name' );
				}
				break;
			case 'plugin':
				$plugin = self::get_plugin_from_slug( $name );
				if ( $plugin && ! empty( $plugin['data']['Name'] ) ) {
					$nicename = $plugin['data']['Name'];
				}
				break;
		}
		echo ' ';

		if ( $nicename ) {
			printf( '%s (<code>%s</code>)', esc_html( $nicename ), esc_html( $name ) );
		} else {
			echo '<code>' . esc_html( $name ) . '</code>';
		}
	}

	/**
	 * Render sources.
	 *
	 * @param array $sources Sources.
	 */
	private static function render_sources( $sources ) {
		?>
		<details>
			<summary>
				<?php
				$source_count = count( $sources );
				echo esc_html(
					sprintf(
						/* translators: %s: number of sources. */
						_n(
							'Source stack (%s)',
							'Source stack (%s)',
							$source_count,
							'amp'
						),
						number_format_i18n( $source_count )
					)
				);
				?>
			</summary>
			<table class="validation-error-sources">
				<?php foreach ( $sources as $i => $source ) : ?>
					<?php
					$source_table_rows = $source;

					if ( isset( $source['file'], $source['line'] ) ) {
						unset( $source_table_rows['file'], $source_table_rows['line'] );
						$source_table_rows['location'] = [
							'link_text' => $source['file'] . ':' . $source['line'],
							'link_url'  => self::get_file_editor_url( $source ),
						];
					}
					$is_filter = ! empty( $source['filter'] );
					unset( $source_table_rows['filter'] );

					$dependency_type = null;
					if ( isset( $source['dependency_type'] ) ) {
						$dependency_type = $source['dependency_type'];
						unset( $source_table_rows['dependency_type'] );
					}

					$priority = null;
					if ( isset( $source['priority'] ) ) {
						$priority = $source['priority'];
						unset( $source_table_rows['priority'] );
					}

					$row_span = count( $source_table_rows );
					?>
					<tbody>
						<?php foreach ( array_keys( $source_table_rows ) as $j => $key ) : ?>
							<?php
							$value = $source_table_rows[ $key ];
							?>
							<tr>
								<?php if ( 0 === $j ) : ?>
									<th rowspan="<?php echo esc_attr( $row_span ); ?>" scope="rowgroup">
										#<?php echo esc_html( $i + 1 ); ?>
									</th>
								<?php endif; ?>
								<th scope="row">
									<?php
									switch ( $key ) {
										case 'name':
											esc_html_e( 'Name', 'amp' );
											break;
										case 'post_id':
											esc_html_e( 'Post ID', 'amp' );
											break;
										case 'post_type':
											esc_html_e( 'Post Type', 'amp' );
											break;
										case 'handle':
											if ( 'script' === $dependency_type ) {
												esc_html_e( 'Script Handle', 'amp' );
											} elseif ( 'style' === $dependency_type ) {
												esc_html_e( 'Style Handle', 'amp' );
											} else {
												esc_html_e( 'Handle', 'amp' );
											}
											break;
										case 'block_content_index':
											esc_html_e( 'Block Index', 'amp' );
											break;
										case 'block_name':
											esc_html_e( 'Block Name', 'amp' );
											break;
										case 'shortcode':
											esc_html_e( 'Shortcode', 'amp' );
											break;
										case 'type':
											esc_html_e( 'Type', 'amp' );
											break;
										case 'function':
											esc_html_e( 'Function', 'amp' );
											break;
										case 'location':
											esc_html_e( 'Location', 'amp' );
											break;
										case 'sources':
											esc_html_e( 'Sources', 'amp' );
											break;
										case 'hook':
											if ( $is_filter ) {
												esc_html_e( 'Filter', 'amp' );
											} else {
												esc_html_e( 'Action', 'amp' );
											}
											break;
										default:
											echo esc_html( $key );
									}
									echo ':';
									?>
								</th>
								<td>
									<?php if ( 'sources' === $key && is_array( $value ) ) : ?>
										<?php self::render_sources( $value ); ?>
									<?php elseif ( 'type' === $key ) : ?>
										<?php
										switch ( $value ) {
											case 'theme':
												echo '<span class="dashicons dashicons-admin-appearance"></span> ';
												esc_html_e( 'Theme', 'amp' );
												break;
											case 'plugin':
												echo '<span class="dashicons dashicons-admin-plugins"></span> ';
												esc_html_e( 'Plugin', 'amp' );
												break;
											case 'mu-plugin':
												echo '<span class="dashicons dashicons-admin-plugins"></span> ';
												esc_html_e( 'Must-Use Plugin', 'amp' );
												break;
											case 'core':
												echo '<span class="dashicons dashicons-wordpress-alt"></span> ';
												esc_html_e( 'Core', 'amp' );
												break;
											default:
												echo esc_html( (string) $value );
										}
										?>
									<?php elseif ( 'name' === $key && isset( $source['type'] ) ) : ?>
										<?php self::render_source_name( $value, $source['type'] ); ?>
									<?php elseif ( 'hook' === $key ) : ?>
										<code><?php echo esc_html( (string) $value ); ?></code>
										<?php
										if ( null !== $priority ) {
											echo esc_html(
												sprintf(
													/* translators: %d is the hook priority */
													__( '(priority %d)', 'amp' ),
													$priority
												)
											);
										}
										?>
									<?php elseif ( 'location' === $key ) : ?>
										<?php
										if ( ! empty( $value['link_url'] ) ) {
											printf(
												'<a href="%s" %s>',
												// Note that esc_attr() used instead of esc_url() to allow IDE protocols.
												esc_attr( $value['link_url'] ),
												// Open link in new window unless the user has filtered the URL to open their system IDE.
												in_array( wp_parse_url( $value['link_url'], PHP_URL_SCHEME ), [ 'http', 'https' ], true ) ? 'target="_blank"' : ''
											);
										}
										?>
										<?php echo esc_html( $value['link_text'] ); ?>
										<?php if ( ! empty( $value['link_url'] ) ) : ?>
											</a>
										<?php endif; ?>
									<?php elseif ( 'function' === $key ) : ?>
										<code><?php echo esc_html( '{closure}' === $value ? $value : $value . '()' ); ?></code>
									<?php elseif ( 'shortcode' === $key || 'handle' === $key ) : ?>
										<code><?php echo esc_html( $value ); ?></code>
									<?php elseif ( 'block_name' === $key ) : ?>
										<?php
										$block_title = self::get_block_title( $value );
										if ( $block_title ) {
											printf( '%s (<code>%s</code>)', esc_html( $block_title ), esc_html( $value ) );
										} else {
											printf( '<code>%s</code>', esc_html( $value ) );
										}
										?>
									<?php elseif ( 'post_type' === $key ) : ?>
										<?php
										$post_type = get_post_type_object( $value );
										if ( $post_type && isset( $post_type->labels->singular_name ) ) {
											echo esc_html( $post_type->labels->singular_name );
											printf( ' (<code>%s</code>)', esc_html( $value ) );
										} else {
											printf( '<code>%s</code>', esc_html( $value ) );
										}
										?>
									<?php elseif ( is_scalar( $value ) ) : ?>
										<?php echo esc_html( (string) $value ); ?>
									<?php else : ?>
										<pre><?php echo esc_html( wp_json_encode( $source, 128 /* JSON_PRETTY_PRINT */ | 64 /* JSON_UNESCAPED_SLASHES */ ) ); ?></pre>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				<?php endforeach; ?>
				</tbody>
			</table>
		</details>
		<?php
	}

	/**
	 * Get block name for a given block slug.
	 *
	 * @since 1.4
	 *
	 * @todo Blocks should eventually be registered server-side with titles.
	 * @param string $block_name Block slug.
	 * @return string Block title.
	 */
	public static function get_block_title( $block_name ) {
		$block_title = null;
		if ( 'core/html' === $block_name ) {
			$block_title = __( 'Custom HTML', 'amp' );
		}
		return $block_title;
	}

	/**
	 * Gets the translated error type name from the given the validation error.
	 *
	 * @param array $validation_error The validation error data.
	 * @return string|null $slug The translated type of the error.
	 */
	public static function get_translated_type_name( $validation_error ) {
		if ( ! isset( $validation_error['type'] ) ) {
			return null;
		}

		$translated_names = [
			self::HTML_ELEMENT_ERROR_TYPE   => __( 'HTML Element', 'amp' ),
			self::HTML_ATTRIBUTE_ERROR_TYPE => __( 'HTML Attribute', 'amp' ),
			self::JS_ERROR_TYPE             => __( 'JavaScript', 'amp' ),
			self::CSS_ERROR_TYPE            => __( 'CSS', 'amp' ),
		];

		if ( isset( $translated_names[ $validation_error['type'] ] ) ) {
			return $translated_names[ $validation_error['type'] ];
		}

		return null;
	}

	/**
	 * Handle inline edit links.
	 */
	public static function handle_inline_edit_request() {
		// Check for necessary arguments.
		if ( ! isset( $_GET['action'], $_GET['_wpnonce'], $_GET['term_id'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Check if we are on either the taxonomy page or a single error page (which has the post_type argument).
		if ( self::TAXONOMY_SLUG !== get_current_screen()->taxonomy && ! isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// If we have a post_type check that it is the correct one.
		if ( isset( $_GET['post_type'] ) && AMP_Validated_URL_Post_Type::POST_TYPE_SLUG !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$action = sanitize_key( $_GET['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( $action );
		$taxonomy_caps = (object) get_taxonomy( self::TAXONOMY_SLUG )->cap; // Yes, cap is an object not an array.
		if ( ! current_user_can( $taxonomy_caps->manage_terms ) ) {
			return;
		}

		$referer  = wp_get_referer();
		$term_id  = (int) $_GET['term_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect = self::handle_validation_error_update( $referer, $action, [ $term_id ] );

		if ( $redirect !== $referer ) {
			wp_safe_redirect( $redirect );
			exit;
		}
	}

	/**
	 * On the single URL page, handles the bulk actions of 'Remove' (formerly 'Accept') and 'Keep' (formerly 'Reject').
	 *
	 * On /wp-admin/post.php, this handles these bulk actions.
	 * This page is more like an edit-tags.php page, in that it has a WP_Terms_List_Table of amp_validation_error terms.
	 * So this reuses handle_validation_error_update(), which the edit-tags.php page uses.
	 *
	 * @param int $post_id The ID of the post for which to apply the bulk action.
	 */
	public static function handle_single_url_page_bulk_and_inline_actions( $post_id ) {
		if ( ! isset( $_REQUEST['action'] ) || AMP_Validated_URL_Post_Type::POST_TYPE_SLUG !== get_post_type( $post_id ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action              = sanitize_key( $_REQUEST['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$term_ids            = isset( $_POST['delete_tags'] ) ? array_map( 'sanitize_key', $_POST['delete_tags'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$single_term_id      = isset( $_GET['term_id'] ) ? sanitize_key( $_GET['term_id'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect_query_args = [
			'action'       => 'edit',
			'amp_actioned' => $action,
		];

		if ( $term_ids ) {
			// If this is a bulk action.
			self::handle_validation_error_update( null, $action, $term_ids );
			$redirect_query_args['amp_actioned_count'] = count( $term_ids );
		} elseif ( $single_term_id ) {
			// If this is an inline action, like 'Keep' or 'Remove'.
			self::handle_validation_error_update( null, $action, [ $single_term_id ] );
			$redirect_query_args['amp_actioned_count'] = 1;
		}

		// Even if the user didn't select any errors to bulk edit, redirect back to the same page.
		wp_safe_redirect(
			add_query_arg(
				$redirect_query_args,
				get_edit_post_link( $post_id, 'raw' )
			)
		);
		exit();
	}

	/**
	 * Handle bulk and inline edits to amp_validation_error terms.
	 *
	 * @param string $redirect_to Redirect to.
	 * @param string $action      Action.
	 * @param int[]  $term_ids    Term IDs.
	 *
	 * @return string Redirect.
	 */
	public static function handle_validation_error_update( $redirect_to, $action, $term_ids ) {
		// @todo Add a new action for mark and unmark as new.
		if ( ! in_array( $action, [ self::VALIDATION_ERROR_ACCEPT_ACTION, self::VALIDATION_ERROR_REJECT_ACTION ], true ) ) {
			return $redirect_to;
		}

		$has_pre_term_description_filter = has_filter( 'pre_term_description', 'wp_filter_kses' );
		if ( false !== $has_pre_term_description_filter ) {
			remove_filter( 'pre_term_description', 'wp_filter_kses', $has_pre_term_description_filter );
		}

		$updated_count = 0;
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id );
			if ( ! $term ) {
				continue;
			}
			$term_group = $term->term_group;

			// The action of marking an error as removed/kept (aka accepted/rejected) also results in it being marked as not-new.
			if ( self::VALIDATION_ERROR_ACCEPT_ACTION === $action ) {
				$term_group = self::VALIDATION_ERROR_ACK_ACCEPTED_STATUS;
			} elseif ( self::VALIDATION_ERROR_REJECT_ACTION === $action ) {
				$term_group = self::VALIDATION_ERROR_ACK_REJECTED_STATUS;
			}

			if ( $term_group !== $term->term_group ) {
				wp_update_term( $term_id, self::TAXONOMY_SLUG, compact( 'term_group' ) );
				$updated_count++;
			}
		}
		if ( false !== $has_pre_term_description_filter ) {
			add_filter( 'pre_term_description', 'wp_filter_kses', $has_pre_term_description_filter );
		}

		$redirect_to = add_query_arg(
			[
				'amp_actioned'       => $action,
				'amp_actioned_count' => count( $term_ids ),
			],
			$redirect_to
		);

		if ( $updated_count ) {
			delete_transient( AMP_Validated_URL_Post_Type::NEW_VALIDATION_ERROR_URLS_COUNT_TRANSIENT );
		}

		return $redirect_to;
	}

	/**
	 * Handle request to delete empty terms.
	 */
	public static function handle_clear_empty_terms_request() {
		if ( ! isset( $_POST[ self::VALIDATION_ERROR_CLEAR_EMPTY_ACTION ], $_POST[ self::VALIDATION_ERROR_CLEAR_EMPTY_ACTION . '_nonce' ] ) ) {
			return;
		}
		if ( ! check_ajax_referer( self::VALIDATION_ERROR_CLEAR_EMPTY_ACTION, self::VALIDATION_ERROR_CLEAR_EMPTY_ACTION . '_nonce', false ) ) {
			wp_die( esc_html__( 'The link you followed has expired.', 'amp' ) );
		}

		$taxonomy_caps = (object) get_taxonomy( self::TAXONOMY_SLUG )->cap; // Yes, cap is an object not an array.
		if ( ! current_user_can( $taxonomy_caps->manage_terms ) ) {
			wp_die( esc_html__( 'You do not have authorization.', 'amp' ) );
		}

		$deleted_terms = self::delete_empty_terms();

		$referer = wp_validate_redirect( wp_get_raw_referer() );
		if ( ! $referer ) {
			return;
		}

		$redirect = add_query_arg( self::VALIDATION_ERRORS_CLEARED_QUERY_VAR, $deleted_terms, $referer );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Determine whether a validation error is for a JS script element.
	 *
	 * @param array $validation_error Validation error.
	 * @return bool Is for scrip JS element.
	 */
	private static function is_validation_error_for_js_script_element( $validation_error ) {
		return (
			isset( $validation_error['node_name'] )
			&&
			'script' === $validation_error['node_name']
			&&
			(
				isset( $validation_error['node_attributes']['src'] )
				||
				empty( $validation_error['node_attributes']['type'] )
				||
				false !== strpos( $validation_error['node_attributes']['type'], 'javascript' )
			)
		);
	}

	/**
	 * Get Error Title from Code
	 *
	 * @todo The message here should be constructed in the sanitizer that emitted the validation error in the first place.
	 *
	 * @param array $validation_error Validation error.
	 * @return string Title with some formatting markup.
	 */
	public static function get_error_title_from_code( $validation_error ) {
		switch ( $validation_error['code'] ) {
			case AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG:
				if ( self::is_validation_error_for_js_script_element( $validation_error ) ) {
					$title = esc_html__( 'Invalid script', 'amp' );

					if ( isset( $validation_error['node_attributes']['src'] ) ) {
						$title .= sprintf(
							': <code>%s</code>',
							esc_html( basename( wp_parse_url( $validation_error['node_attributes']['src'], PHP_URL_PATH ) ) )
						);
					}
				} else {
					$title  = esc_html__( 'Invalid element', 'amp' );
					$title .= sprintf( ': <code>&lt;%s&gt;</code>', esc_html( $validation_error['node_name'] ) );
				}
				return $title;
			case AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR:
				return sprintf(
					'%s: <code>%s</code>',
					esc_html__( 'Invalid attribute', 'amp' ),
					esc_html( $validation_error['node_name'] )
				);
			case AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_PROCESSING_INSTRUCTION:
				return sprintf(
					'%s: <code>&lt;%s%s&hellip;%s&gt;</code>',
					esc_html__( 'Invalid processing instruction', 'amp' ),
					'?',
					esc_html( $validation_error['node_name'] ),
					'?'
				);
			case AMP_Style_Sanitizer::STYLESHEET_TOO_LONG:
				return esc_html__( 'Excessive CSS', 'amp' );
			case AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_AT_RULE:
				return sprintf(
					'%s: <code>@%s</code>',
					esc_html__( 'Illegal CSS at-rule', 'amp' ),
					esc_html( $validation_error['at_rule'] )
				);
			case AMP_Tag_And_Attribute_Sanitizer::DUPLICATE_UNIQUE_TAG:
				return sprintf(
					'%s: <code>&lt;%s&gt;</code>',
					esc_html__( 'Duplicate element', 'amp' ),
					esc_html( $validation_error['node_name'] )
				);
			case AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_DECLARATION:
				return esc_html__( 'Unrecognized CSS', 'amp' );
			case AMP_Style_Sanitizer::CSS_SYNTAX_PARSE_ERROR:
				return esc_html__( 'CSS parse error', 'amp' );
			case AMP_Style_Sanitizer::STYLESHEET_INVALID_FILE_URL:
				return esc_html__( 'Missing stylesheet file', 'amp' );
			case AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_PROPERTY:
			case AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_PROPERTY_NOLIST:
				$title = esc_html__( 'Illegal CSS property', 'amp' );
				if ( isset( $validation_error['property_name'] ) ) {
					$title .= sprintf( ': <code>%s</code>', esc_html( $validation_error['property_name'] ) );
				}
				return $title;
			case AMP_Tag_And_Attribute_Sanitizer::CDATA_TOO_LONG:
			case AMP_Tag_And_Attribute_Sanitizer::MANDATORY_CDATA_MISSING_OR_INCORRECT:
			case AMP_Tag_And_Attribute_Sanitizer::INVALID_CDATA_HTML_COMMENTS:
			case AMP_Tag_And_Attribute_Sanitizer::INVALID_CDATA_CSS_IMPORTANT:
			case AMP_Tag_And_Attribute_Sanitizer::CDATA_VIOLATES_BLACKLIST:
				return esc_html__( 'Illegal text content', 'amp' );
			case AMP_Style_Sanitizer::CSS_SYNTAX_INVALID_IMPORTANT:
				$title = esc_html__( 'Illegal CSS !important property', 'amp' );
				if ( isset( $validation_error['property_name'] ) ) {
					$title .= sprintf( ': <code>%s</code>', esc_html( $validation_error['property_name'] ) );
				}
				return $title;
			default:
				/* translators: %s error code */
				return sprintf( __( 'Unknown error (%s)', 'amp' ), $validation_error['code'] );
		}
	}

	/**
	 * Get label for object key in validation error source.
	 *
	 * @param string $key              Key.
	 * @param array  $validation_error Validation error.
	 * @return string Label for key.
	 */
	public static function get_source_key_label( $key, $validation_error ) {
		switch ( $key ) {
			case 'code':
				return __( 'Code', 'amp' );
			case 'at_rule':
				return __( 'At-rule', 'amp' );
			case 'node_attributes':
			case 'element_attributes':
				return __( 'Element attributes', 'amp' );
			case 'node_name':
				if ( AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_ATTR === $validation_error['code'] ) {
					return __( 'Attribute name', 'amp' );
				} elseif ( AMP_Tag_And_Attribute_Sanitizer::DISALLOWED_TAG === $validation_error['code'] ) {
					return __( 'Element name', 'amp' );
				} else {
					return __( 'Node name', 'amp' );
				}
			case 'parent_name':
				return __( 'Parent element', 'amp' );
			case 'property_name':
				return __( 'CSS property', 'amp' );
			case 'text':
				return __( 'Text content', 'amp' );
			case 'type':
				return __( 'Type', 'amp' );
			case 'sources':
				return __( 'Sources', 'amp' );
			default:
				return $key;
		}
	}

	/**
	 * Get Status Text with Icon
	 *
	 * @see \AMP_Validation_Error_Taxonomy::get_validation_error_sanitization()
	 *
	 * @param array $sanitization Sanitization.
	 * @return string Status text.
	 */
	public static function get_status_text_with_icon( $sanitization ) {
		if ( $sanitization['term_status'] & self::ACCEPTED_VALIDATION_ERROR_BIT_MASK ) {
			$class = 'accepted';
			$text  = __( 'Removed', 'amp' );
		} else {
			$class = 'rejected';
			$text  = __( 'Kept', 'amp' );
		}
		return sprintf( '<span class="status-text %s">%s</span>', esc_attr( $class ), esc_html( $text ) );
	}

	/**
	 * Possibly changes the sanitization of an error, based on the presence of query vars and the proper permission.
	 *
	 * This enables rejecting all validation errors, given the proper permissions and a query var.
	 * Likewise, it enables accepting only 'excessive_css' errors.
	 *
	 * @param bool|null $sanitized A boolean to change the sanitization of the error, or null to not change it.
	 * @param array     $error The error to examine.
	 * @return bool|null The filtered sanitization, false meaning that it will be rejected, and null meaning to not change the sanitization.
	 */
	public static function conditionally_change_sanitization( $sanitized, $error ) {
		if ( AMP_Debug::has_flag( AMP_Debug::REJECT_ALL_VALIDATION_ERRORS_QUERY_VAR ) ) {
			return false;
		}

		if (
			AMP_Debug::has_flag( AMP_Debug::ACCEPT_EXCESSIVE_CSS_ERROR_QUERY_VAR )
			&&
			isset( $error['code'] )
			&&
			'excessive_css' === $error['code']
		) {
			return true;
		}

		return $sanitized;
	}
}

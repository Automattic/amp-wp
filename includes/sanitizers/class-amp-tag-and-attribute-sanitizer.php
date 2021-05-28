<?php
/**
 * Class AMP_Tag_And_Attribute_Sanitizer
 *
 * Also referred to the "Validating Sanitizer".
 *
 * @package AMP
 */

use AmpProject\Attribute;
use AmpProject\CssLength;
use AmpProject\Dom\Document;
use AmpProject\Layout;
use AmpProject\Extension;
use AmpProject\Tag;

/**
 * Strips the tags and attributes from the content that are not allowed by the AMP spec.
 *
 * Allowed tags array is generated from this protocol buffer:
 *
 *     https://github.com/ampproject/amphtml/blob/bd29b0eb1b28d900d4abed2c1883c6980f18db8e/validator/validator-main.protoascii
 *     by the python script in amp-wp/bin/amp_wp_build.py. See the comment at the top
 *     of that file for instructions to generate class-amp-allowed-tags-generated.php.
 *
 * @todo Need to check the following items that are not yet checked by this sanitizer:
 *
 *     - `also_requires_attr` - if one attribute is present, this requires another.
 *     - `ChildTagSpec`       - Places restrictions on the number and type of child tags.
 *     - `if_value_regex`     - if one attribute value matches, this places a restriction
 *                              on another attribute/value.
 *
 * @internal
 */
class AMP_Tag_And_Attribute_Sanitizer extends AMP_Base_Sanitizer {

	const ATTR_REQUIRED_BUT_MISSING            = 'ATTR_REQUIRED_BUT_MISSING';
	const CDATA_TOO_LONG                       = 'CDATA_TOO_LONG';
	const CDATA_VIOLATES_DENYLIST              = 'CDATA_VIOLATES_DENYLIST';
	const DISALLOWED_ATTR                      = 'DISALLOWED_ATTR';
	const DISALLOWED_CHILD_TAG                 = 'DISALLOWED_CHILD_TAG';
	const DISALLOWED_DESCENDANT_TAG            = 'DISALLOWED_DESCENDANT_TAG';
	const DISALLOWED_FIRST_CHILD_TAG           = 'DISALLOWED_FIRST_CHILD_TAG';
	const DISALLOWED_PROCESSING_INSTRUCTION    = 'DISALLOWED_PROCESSING_INSTRUCTION';
	const DISALLOWED_PROPERTY_IN_ATTR_VALUE    = 'DISALLOWED_PROPERTY_IN_ATTR_VALUE';
	const DISALLOWED_RELATIVE_URL              = 'DISALLOWED_RELATIVE_URL';
	const DISALLOWED_TAG                       = 'DISALLOWED_TAG';
	const DISALLOWED_TAG_ANCESTOR              = 'DISALLOWED_TAG_ANCESTOR';
	const DUPLICATE_DIMENSIONS                 = 'DUPLICATE_DIMENSIONS';
	const DUPLICATE_ONEOF_ATTRS                = 'DUPLICATE_ONEOF_ATTRS';
	const DUPLICATE_UNIQUE_TAG                 = 'DUPLICATE_UNIQUE_TAG';
	const IMPLIED_LAYOUT_INVALID               = 'IMPLIED_LAYOUT_INVALID';
	const INCORRECT_MIN_NUM_CHILD_TAGS         = 'INCORRECT_MIN_NUM_CHILD_TAGS';
	const INCORRECT_NUM_CHILD_TAGS             = 'INCORRECT_NUM_CHILD_TAGS';
	const INVALID_ATTR_VALUE                   = 'INVALID_ATTR_VALUE';
	const INVALID_ATTR_VALUE_CASEI             = 'INVALID_ATTR_VALUE_CASEI';
	const INVALID_ATTR_VALUE_REGEX             = 'INVALID_ATTR_VALUE_REGEX';
	const INVALID_ATTR_VALUE_REGEX_CASEI       = 'INVALID_ATTR_VALUE_REGEX_CASEI';
	const INVALID_DISALLOWED_VALUE_REGEX       = 'INVALID_DISALLOWED_VALUE_REGEX';
	const INVALID_CDATA_CONTENTS               = 'INVALID_CDATA_CONTENTS';
	const INVALID_CDATA_CSS_I_AMPHTML_NAME     = 'INVALID_CDATA_CSS_I_AMPHTML_NAME';
	const INVALID_CDATA_CSS_IMPORTANT          = 'INVALID_CDATA_CSS_IMPORTANT';
	const INVALID_CDATA_HTML_COMMENTS          = 'INVALID_CDATA_HTML_COMMENTS';
	const INVALID_LAYOUT_AUTO_HEIGHT           = 'INVALID_LAYOUT_AUTO_HEIGHT';
	const INVALID_LAYOUT_AUTO_WIDTH            = 'INVALID_LAYOUT_AUTO_WIDTH';
	const INVALID_LAYOUT_FIXED_HEIGHT          = 'INVALID_LAYOUT_FIXED_HEIGHT';
	const INVALID_LAYOUT_HEIGHT                = 'INVALID_LAYOUT_HEIGHT';
	const INVALID_LAYOUT_HEIGHTS               = 'INVALID_LAYOUT_HEIGHTS';
	const INVALID_LAYOUT_NO_HEIGHT             = 'INVALID_LAYOUT_NO_HEIGHT';
	const INVALID_LAYOUT_UNIT_DIMENSIONS       = 'INVALID_LAYOUT_UNIT_DIMENSIONS';
	const INVALID_LAYOUT_WIDTH                 = 'INVALID_LAYOUT_WIDTH';
	const INVALID_URL                          = 'INVALID_URL';
	const INVALID_URL_PROTOCOL                 = 'INVALID_URL_PROTOCOL';
	const JSON_ERROR_CTRL_CHAR                 = 'JSON_ERROR_CTRL_CHAR';
	const JSON_ERROR_DEPTH                     = 'JSON_ERROR_DEPTH';
	const JSON_ERROR_EMPTY                     = 'JSON_ERROR_EMPTY';
	const JSON_ERROR_STATE_MISMATCH            = 'JSON_ERROR_STATE_MISMATCH';
	const JSON_ERROR_SYNTAX                    = 'JSON_ERROR_SYNTAX';
	const JSON_ERROR_UTF8                      = 'JSON_ERROR_UTF8';
	const MANDATORY_ANYOF_ATTR_MISSING         = 'MANDATORY_ANYOF_ATTR_MISSING';
	const MANDATORY_CDATA_MISSING_OR_INCORRECT = 'MANDATORY_CDATA_MISSING_OR_INCORRECT';
	const MANDATORY_ONEOF_ATTR_MISSING         = 'MANDATORY_ONEOF_ATTR_MISSING';
	const MANDATORY_TAG_ANCESTOR               = 'MANDATORY_TAG_ANCESTOR';
	const MISSING_LAYOUT_ATTRIBUTES            = 'MISSING_LAYOUT_ATTRIBUTES';
	const MISSING_MANDATORY_PROPERTY           = 'MISSING_MANDATORY_PROPERTY';
	const MISSING_REQUIRED_PROPERTY_VALUE      = 'MISSING_REQUIRED_PROPERTY_VALUE';
	const MISSING_URL                          = 'MISSING_URL';
	const SPECIFIED_LAYOUT_INVALID             = 'SPECIFIED_LAYOUT_INVALID';
	const WRONG_PARENT_TAG                     = 'WRONG_PARENT_TAG';

	/**
	 * Allowed tags.
	 *
	 * @since 0.5
	 *
	 * @var array
	 */
	protected $allowed_tags;

	/**
	 * Globally-allowed attributes.
	 *
	 * @since 0.5
	 *
	 * @var array[][]
	 */
	protected $globally_allowed_attributes;

	/**
	 * Layout-allowed attributes.
	 *
	 * @since 0.5
	 *
	 * @var string[]
	 */
	protected $layout_allowed_attributes;

	/**
	 * Mapping of alternative names back to their primary names.
	 *
	 * @since 0.7
	 * @var array
	 */
	protected $rev_alternate_attr_name_lookup = [];

	/**
	 * Mapping of JSON-serialized tag spec to the number of instances encountered in the document.
	 *
	 * @var array
	 */
	protected $visited_unique_tag_specs = [];

	/**
	 * Default args.
	 *
	 * @since 0.5
	 *
	 * @var array
	 */
	protected $DEFAULT_ARGS = [];

	/**
	 * AMP script components that are discovered being required through sanitization.
	 *
	 * @var string[]
	 */
	protected $script_components = [];

	/**
	 * Keep track of nodes that should not be replaced to prevent duplicated validation errors since sanitization is rejected.
	 *
	 * @var array
	 */
	protected $should_not_replace_nodes = [];

	/**
	 * Keep track of the elements that are currently open.
	 *
	 * This is used to determine whether a node exists inside of a given element tree, speeding up has_ancestor checks
	 * as well as disabling attribute validation inside of templates.
	 *
	 * @see \AMP_Tag_And_Attribute_Sanitizer::has_ancestor()
	 * @since 1.3
	 * @var array
	 */
	protected $open_elements = [];

	/**
	 * AMP_Tag_And_Attribute_Sanitizer constructor.
	 *
	 * @since 0.5
	 *
	 * @param Document $dom  DOM.
	 * @param array    $args Args.
	 */
	public function __construct( $dom, $args = [] ) {
		// @todo It is pointless to have this DEFAULT_ARGS copying the array values. We should only get the data from AMP_Allowed_Tags_Generated.
		$this->DEFAULT_ARGS = [
			'amp_allowed_tags'                => AMP_Allowed_Tags_Generated::get_allowed_tags(),
			'amp_globally_allowed_attributes' => AMP_Allowed_Tags_Generated::get_allowed_attributes(),
			'amp_layout_allowed_attributes'   => AMP_Allowed_Tags_Generated::get_layout_attributes(),
		];

		parent::__construct( $dom, $args );

		// Prepare allowlists.
		$this->allowed_tags = $this->args['amp_allowed_tags'];
		foreach ( AMP_Rule_Spec::$additional_allowed_tags as $tag_name => $tag_rule_spec ) {
			$this->allowed_tags[ $tag_name ][] = $tag_rule_spec;
		}

		// @todo Do the same for body when !use_document_element?
		if ( ! empty( $this->args['use_document_element'] ) ) {
			foreach ( $this->allowed_tags['html'] as &$rule_spec ) {
				unset( $rule_spec[ AMP_Rule_Spec::TAG_SPEC ][ AMP_Rule_Spec::MANDATORY_PARENT ] );
			}

			unset( $rule_spec );
		}

		foreach ( $this->allowed_tags as &$tag_specs ) {
			foreach ( $tag_specs as &$tag_spec ) {
				if ( isset( $tag_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ] ) ) {
					$tag_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ] = $this->process_alternate_names( $tag_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ] );
				}
			}

			unset( $tag_spec );
		}

		unset( $tag_specs );

		$this->globally_allowed_attributes = $this->process_alternate_names( $this->args['amp_globally_allowed_attributes'] );
		$this->layout_allowed_attributes   = $this->process_alternate_names( $this->args['amp_layout_allowed_attributes'] );
	}

	/**
	 * Return array of values that would be valid as an HTML `script` element.
	 *
	 * Array keys are AMP element names and array values are their respective
	 * Javascript URLs from https://cdn.ampproject.org
	 *
	 * @since 0.7
	 * @see amp_register_default_scripts()
	 *
	 * @return array() Returns component name as array key and true as value (or JavaScript URL string),
	 *                  respectively. When true then the default component script URL will be used.
	 *                  Will return an empty array if sanitization has yet to be run
	 *                  or if it did not find any HTML elements to convert to AMP equivalents.
	 */
	public function get_scripts() {
		return array_fill_keys( array_unique( $this->script_components ), true );
	}

	/**
	 * Process alternative names in attribute spec list.
	 *
	 * @since 0.7
	 *
	 * @param array $attr_spec_list Attribute spec list.
	 * @return array Modified attribute spec list.
	 */
	private function process_alternate_names( $attr_spec_list ) {
		foreach ( $attr_spec_list as $attr_name => &$attr_spec ) {
			// Save all alternative names in lookup to improve performance.
			if ( isset( $attr_spec[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] ) ) {
				foreach ( $attr_spec[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] as $alternative_name ) {
					$this->rev_alternate_attr_name_lookup[ $alternative_name ] = $attr_name;
				}
			}
		}
		return $attr_spec_list;
	}

	/**
	 * Sanitize the elements from the HTML contained in this instance's Dom\Document.
	 *
	 * @since 0.5
	 */
	public function sanitize() {
		$result = $this->sanitize_element( $this->root_element );
		if ( is_array( $result ) ) {
			$this->script_components = $result;
		}
	}

	/**
	 * Sanitize element.
	 *
	 * Walk the DOM tree with depth first search (DFS) with post order traversal (LRN).
	 *
	 * @param DOMElement $element Element.
	 * @return string[]|null Required component scripts from sanitizing an element tree, or null if the element was removed.
	 */
	private function sanitize_element( DOMElement $element ) {
		if ( ! isset( $this->open_elements[ $element->nodeName ] ) ) {
			$this->open_elements[ $element->nodeName ] = 0;
		}
		$this->open_elements[ $element->nodeName ]++;

		$script_components = [];

		// First recurse into children to sanitize descendants.
		// The check for $element->parentNode at each iteration is to make sure an invalid child didn't bubble up removed
		// ancestor nodes in AMP_Tag_And_Attribute_Sanitizer::remove_node().
		$this_child = $element->firstChild;
		while ( $this_child && $element->parentNode ) {
			$prev_child = $this_child->previousSibling;
			$next_child = $this_child->nextSibling;
			if ( $this_child instanceof DOMElement ) {
				$result = $this->sanitize_element( $this_child );
				if ( is_array( $result ) ) {
					$script_components = array_merge(
						$script_components,
						$result
					);
				}
			} elseif ( $this_child instanceof DOMProcessingInstruction ) {
				$this->remove_invalid_child( $this_child, [ 'code' => self::DISALLOWED_PROCESSING_INSTRUCTION ] );
			}

			if ( ! $this_child->parentNode ) {
				// Handle case where this child is replaced with children.
				$this_child = $prev_child ? $prev_child->nextSibling : $element->firstChild;
			} else {
				$this_child = $next_child;
			}
		}

		// If the element is still in the tree, process it.
		// The element can currently be removed from the tree when processing children via AMP_Tag_And_Attribute_Sanitizer::remove_node().
		$was_removed = false;
		if ( $element->parentNode ) {
			$result = $this->process_node( $element );
			if ( is_array( $result ) ) {
				$script_components = array_merge( $script_components, $result );
			} else {
				$was_removed = true;
			}
		}

		$this->open_elements[ $element->nodeName ]--;

		if ( $was_removed ) {
			return null;
		}

		return $script_components;
	}

	/**
	 * Augment rule spec for validation.
	 *
	 * @since 1.0
	 *
	 * @param DOMElement $node      Node.
	 * @param array      $rule_spec Rule spec.
	 * @return array Augmented rule spec.
	 */
	private function get_rule_spec_list_to_validate( DOMElement $node, $rule_spec ) {

		// Expand extension_spec into a set of attr_spec_list.
		if ( isset( $rule_spec[ AMP_Rule_Spec::TAG_SPEC ]['extension_spec'] ) ) {
			$extension_spec = $rule_spec[ AMP_Rule_Spec::TAG_SPEC ]['extension_spec'];

			// This could also be derived from the extension_type in the extension_spec.
			$custom_attr = 'amp-mustache' === $extension_spec['name'] ? 'custom-template' : 'custom-element';

			$rule_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ][ $custom_attr ] = [
				AMP_Rule_Spec::VALUE     => $extension_spec['name'],
				AMP_Rule_Spec::MANDATORY => true,
			];

			$rule_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ]['src'] = [
				AMP_Rule_Spec::VALUE_REGEX => implode(
					'',
					[
						'^',
						preg_quote( 'https://cdn.ampproject.org/v0/' . $extension_spec['name'] . '-', '/' ),
						'(' . implode( '|', array_merge( $extension_spec['version'], [ 'latest' ] ) ) . ')',
						'\.js$',
					]
				),
			];
		}

		// Augment the attribute list according to the parent's reference points, if it has them.
		if ( ! empty( $node->parentNode ) && isset( $this->allowed_tags[ $node->parentNode->nodeName ] ) ) {
			foreach ( $this->allowed_tags[ $node->parentNode->nodeName ] as $parent_rule_spec ) {
				if ( empty( $parent_rule_spec[ AMP_Rule_Spec::TAG_SPEC ]['reference_points'] ) ) {
					continue;
				}
				foreach ( $parent_rule_spec[ AMP_Rule_Spec::TAG_SPEC ]['reference_points'] as $reference_point_spec_name => $reference_point_spec_instance_attrs ) {
					$reference_point = AMP_Allowed_Tags_Generated::get_reference_point_spec( $reference_point_spec_name );
					if ( empty( $reference_point[ AMP_Rule_Spec::ATTR_SPEC_LIST ] ) ) {
						/*
						 * See special case for amp-selector in AMP_Tag_And_Attribute_Sanitizer::is_amp_allowed_attribute()
						 * where its reference point applies to any descendant elements, not just direct children.
						 */
						continue;
					}
					foreach ( $reference_point[ AMP_Rule_Spec::ATTR_SPEC_LIST ] as $attr_name => $reference_point_spec_attr ) {
						$reference_point_spec_attr = array_merge(
							$reference_point_spec_attr,
							$reference_point_spec_instance_attrs
						);

						/*
						 * Ignore mandatory constraint for now since this would end up causing other sibling children
						 * getting removed due to missing a mandatory attribute. To sanitize this it would require
						 * higher-level processing to look at an element's surrounding context, similar to how the
						 * sanitizer does not yet handle the mandatory_oneof constraint.
						 */
						unset( $reference_point_spec_attr['mandatory'] );

						$rule_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ][ $attr_name ] = $reference_point_spec_attr;
					}
				}
			}
		}
		return $rule_spec;
	}

	/**
	 * Process a node by checking if an element and its attributes are valid, and removing them when invalid.
	 *
	 * Attributes which are not valid are removed. Elements which are not allowed are also removed,
	 * including elements which miss mandatory attributes.
	 *
	 * @param DOMElement $node Node.
	 * @return string[]|null Required scripts, or null if the element was removed.
	 */
	private function process_node( DOMElement $node ) {

		// Remove nodes with tags that have not been put in the allowlist.
		if ( ! $this->is_amp_allowed_tag( $node ) ) {

			// If it's not an allowed tag, replace the node with it's children.
			$this->replace_node_with_children( $node );

			// Return early since this node no longer exists.
			return null;
		}

		/*
		 * Compile a list of rule_specs to validate for this node
		 * based on tag name of the node.
		 */
		$rule_spec_list_to_validate = [];
		$validation_errors          = [];
		$rule_spec_list             = $this->allowed_tags[ $node->nodeName ];
		foreach ( $rule_spec_list as $id => $rule_spec ) {
			$validity = $this->validate_tag_spec_for_node( $node, $rule_spec[ AMP_Rule_Spec::TAG_SPEC ] );
			if ( true === $validity ) {
				$rule_spec_list_to_validate[ $id ] = $this->get_rule_spec_list_to_validate( $node, $rule_spec );
			} else {
				$validation_errors[] = array_merge(
					$validity,
					[ 'spec_name' => $this->get_spec_name( $node, $rule_spec[ AMP_Rule_Spec::TAG_SPEC ] ) ]
				);
			}
		}

		// If no valid rule_specs exist, then remove this node and return.
		if ( empty( $rule_spec_list_to_validate ) ) {
			if ( 1 === count( $validation_errors ) ) {
				// If there was only one tag spec candidate that failed, use its error code for removing the node,
				// since we know it is the specific reason for why the node had to be removed.
				// This is the normal case.
				$this->remove_invalid_child(
					$node,
					$validation_errors[0]
				);
			} else {
				$spec_names = wp_list_pluck( $validation_errors, 'spec_name' );

				$unique_validation_error_count = count(
					array_unique(
						array_map(
							static function ( $validation_error ) {
								unset(
									$validation_error['spec_name'],
									// Remove other keys that may make the error unique.
									$validation_error['required_parent_name'],
									$validation_error['required_ancestor_name'],
									$validation_error['required_child_count'],
									$validation_error['required_min_child_count'],
									$validation_error['required_attr_value']
								);
								return $validation_error;
							},
							$validation_errors
						),
						SORT_REGULAR
					)
				);

				if ( 1 === $unique_validation_error_count ) {
					// If all of the validation errors are the same except for the spec_name, use the common error code.
					$validation_error = $validation_errors[0];
					unset( $validation_error['spec_name'] );
					$this->remove_invalid_child(
						$node,
						array_merge(
							$validation_error,
							compact( 'spec_names' )
						)
					);
				} else {
					// Otherwise, we have a rare condition where multiple tag specs fail for different reasons.
					foreach ( $validation_errors as $validation_error ) {
						if ( true === $this->remove_invalid_child( $node, $validation_error ) ) {
							break; // Once removed, ignore remaining errors.
						}
					}
				}
			}
			return null;
		}

		// The remaining validations all have to do with attributes.
		$attr_spec_list = [];
		$tag_spec       = [];
		$cdata          = [];

		/*
		 * If we have exactly one rule_spec, use it's attr_spec_list
		 * to validate the node's attributes.
		 */
		if ( 1 === count( $rule_spec_list_to_validate ) ) {
			$rule_spec      = array_pop( $rule_spec_list_to_validate );
			$attr_spec_list = $rule_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ];
			$tag_spec       = $rule_spec[ AMP_Rule_Spec::TAG_SPEC ];
			if ( isset( $rule_spec[ AMP_Rule_Spec::CDATA ] ) ) {
				$cdata = $rule_spec[ AMP_Rule_Spec::CDATA ];
			}
		} else {
			/*
			 * If there is more than one valid rule_spec for this node,
			 * then try to deduce which one is intended by inspecting
			 * the node's attributes.
			 */

			/*
			 * Get a score from each attr_spec_list by seeing how many
			 * attributes and values match the node.
			 */
			$attr_spec_scores = [];
			foreach ( $rule_spec_list_to_validate as $spec_id => $rule_spec ) {
				$attr_spec_scores[ $spec_id ] = $this->validate_attr_spec_list_for_node( $node, $rule_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ] );
			}

			// Remove all spec lists that didn't match.
			$attr_spec_scores = array_filter( $attr_spec_scores );

			// If no attribute spec lists match, then the element must be removed.
			if ( empty( $attr_spec_scores ) ) {
				$this->remove_node( $node );
				return null;
			}

			// Get the key(s) to the highest score(s).
			$spec_ids_sorted = array_keys( $attr_spec_scores, max( $attr_spec_scores ), true );

			// If there is exactly one attr_spec with a max score, use that one.
			if ( 1 === count( $spec_ids_sorted ) ) {
				$attr_spec_list = $rule_spec_list_to_validate[ $spec_ids_sorted[0] ][ AMP_Rule_Spec::ATTR_SPEC_LIST ];
				$tag_spec       = $rule_spec_list_to_validate[ $spec_ids_sorted[0] ][ AMP_Rule_Spec::TAG_SPEC ];
				if ( isset( $rule_spec_list_to_validate[ $spec_ids_sorted[0] ][ AMP_Rule_Spec::CDATA ] ) ) {
					$cdata = $rule_spec_list_to_validate[ $spec_ids_sorted[0] ][ AMP_Rule_Spec::CDATA ];
				}
			} else {
				// This should not happen very often, but...
				// If we're here, then we're not sure which spec should
				// be used. Let's use the top scoring ones.
				foreach ( $spec_ids_sorted as $id ) {
					$attr_spec_list = array_merge(
						$attr_spec_list,
						$rule_spec_list_to_validate[ $id ][ AMP_Rule_Spec::ATTR_SPEC_LIST ]
					);
					$tag_spec       = array_merge(
						$tag_spec,
						$rule_spec_list_to_validate[ $id ][ AMP_Rule_Spec::TAG_SPEC ]
					);
					if ( isset( $rule_spec_list_to_validate[ $id ][ AMP_Rule_Spec::CDATA ] ) ) {
						$cdata = array_merge( $cdata, $rule_spec_list_to_validate[ $id ][ AMP_Rule_Spec::CDATA ] );
					}
				}
				$first_spec = reset( $rule_spec_list_to_validate );
				if ( empty( $attr_spec_list ) && isset( $first_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ] ) ) {
					$attr_spec_list = $first_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ];
				}
			}
		}

		$attr_spec_list = array_merge(
			$this->globally_allowed_attributes,
			$attr_spec_list
		);

		// Remove element if it has illegal CDATA.
		if ( ! empty( $cdata ) && $node instanceof DOMElement ) {
			$validity = $this->validate_cdata_for_node( $node, $cdata );
			if ( true !== $validity ) {
				$sanitized = $this->remove_invalid_child(
					$node,
					array_merge(
						$validity,
						[ 'spec_name' => $this->get_spec_name( $node, $tag_spec ) ]
					)
				);
				return $sanitized ? null : $this->get_required_script_components( $node, $tag_spec, $attr_spec_list );
			}
		}

		// Amend spec list with layout.
		if ( isset( $tag_spec['amp_layout'] ) ) {
			$attr_spec_list = array_merge( $attr_spec_list, $this->layout_allowed_attributes );

			if ( isset( $tag_spec['amp_layout']['supported_layouts'] ) ) {
				$layouts = wp_array_slice_assoc( Layout::FROM_SPEC, $tag_spec['amp_layout']['supported_layouts'] );

				$attr_spec_list['layout'][ AMP_Rule_Spec::VALUE_REGEX_CASEI ] = '(' . implode( '|', $layouts ) . ')';
			}
		}

		// Enforce unique constraint.
		if ( ! empty( $tag_spec['unique'] ) ) {
			$removed      = false;
			$tag_spec_key = wp_json_encode( $tag_spec );
			if ( ! empty( $this->visited_unique_tag_specs[ $node->nodeName ][ $tag_spec_key ] ) ) {
				$removed = $this->remove_invalid_child(
					$node,
					[
						'code'      => self::DUPLICATE_UNIQUE_TAG,
						'spec_name' => $this->get_spec_name( $node, $tag_spec ),
					]
				);
			}
			$this->visited_unique_tag_specs[ $node->nodeName ][ $tag_spec_key ] = true;
			if ( $removed ) {
				return null;
			}
		}

		// Remove the element if it is has an invalid layout.
		$layout_validity = $this->is_valid_layout( $tag_spec, $node );
		if ( true !== $layout_validity ) {
			$sanitized = $this->remove_invalid_child( $node, $layout_validity );
			return $sanitized ? null : $this->get_required_script_components( $node, $tag_spec, $attr_spec_list );
		}

		// Identify attribute values that don't conform to the attr_spec.
		$disallowed_attributes = $this->sanitize_disallowed_attribute_values_in_node( $node, $attr_spec_list );

		// Remove all invalid attributes.
		if ( ! empty( $disallowed_attributes ) ) {
			/*
			 * Capture all element attributes up front so that differing validation errors result when
			 * one invalid attribute is accepted but the others are still rejected.
			 */
			$element_attributes = [];
			foreach ( $node->attributes as $attribute ) {
				$element_attributes[ $attribute->nodeName ] = $attribute->nodeValue;
			}
			$removed_attributes = [];

			foreach ( $disallowed_attributes as $disallowed_attribute ) {
				/**
				 * Returned vars.
				 *
				 * @var DOMAttr $attr_node
				 * @var string  $error_code
				 * @var array   $error_data
				 */
				list( $attr_node, $error_code, $error_data ) = $disallowed_attribute;

				$validation_error = [
					'code'               => $error_code,
					'element_attributes' => $element_attributes,
				];

				if ( self::DISALLOWED_PROPERTY_IN_ATTR_VALUE === $error_code ) {
					$properties = $this->parse_properties_attribute( $attr_node->nodeValue );

					$validation_error['meta_property_name'] = $error_data['name'];
					if ( ! $this->is_empty_attribute_value( $properties[ $error_data['name'] ] ) ) {
						$validation_error['meta_property_value'] = $properties[ $error_data['name'] ];
					}

					if ( $this->should_sanitize_validation_error( $validation_error, [ 'node' => $attr_node ] ) ) {
						unset( $properties[ $error_data['name'] ] );
						$node->setAttribute( $attr_node->nodeName, $this->serialize_properties_attribute( $properties ) );
					}
				} elseif ( self::MISSING_REQUIRED_PROPERTY_VALUE === $error_code ) {
					$validation_error['meta_property_name']           = $error_data['name'];
					$validation_error['meta_property_value']          = $error_data['value'];
					$validation_error['meta_property_required_value'] = $error_data['required_value'];

					if ( $this->should_sanitize_validation_error( $validation_error, [ 'node' => $attr_node ] ) ) {
						$properties = $this->parse_properties_attribute( $attr_node->nodeValue );
						if ( ! empty( $attr_spec_list[ $attr_node->nodeName ]['value_properties'][ $error_data['name'] ]['mandatory'] ) ) {
							$properties[ $error_data['name'] ] = $error_data['required_value'];
						} else {
							unset( $properties[ $error_data['name'] ] );
						}
						$node->setAttribute( $attr_node->nodeName, $this->serialize_properties_attribute( $properties ) );
					}
				} elseif ( self::MISSING_MANDATORY_PROPERTY === $error_code ) {
					$validation_error['meta_property_name']           = $error_data['name'];
					$validation_error['meta_property_required_value'] = $error_data['required_value'];
					if ( $this->should_sanitize_validation_error( $validation_error, [ 'node' => $attr_node ] ) ) {
						$properties = array_merge(
							$this->parse_properties_attribute( $attr_node->nodeValue ),
							[ $error_data['name'] => $error_data['required_value'] ]
						);
						$node->setAttribute( $attr_node->nodeName, $this->serialize_properties_attribute( $properties ) );
					}
				} else {
					$attr_spec = isset( $attr_spec_list[ $attr_node->nodeName ] ) ? $attr_spec_list[ $attr_node->nodeName ] : [];
					if ( $this->remove_invalid_attribute( $node, $attr_node, $validation_error, $attr_spec ) ) {
						$removed_attributes[] = $attr_node;
					}
				}
			}

			/*
			 * Only run cleanup after the fact to prevent a scenario where invalid markup is kept and so the attribute
			 * is actually not removed. This prevents a "DOMException: Not Found Error" from happening when calling
			 * remove_invalid_attribute() since clean_up_after_attribute_removal() can end up removing invalid link
			 * attributes (like 'target') when there is an invalid 'href' attribute, but if the 'target' attribute is
			 * itself invalid, then if clean_up_after_attribute_removal() is called inside of remove_invalid_attribute()
			 * it can cause a subsequent invocation of remove_invalid_attribute() to try to remove an invalid
			 * attribute that has already been removed from the DOM.
			 */
			foreach ( $removed_attributes as $removed_attribute ) {
				$this->clean_up_after_attribute_removal( $node, $removed_attribute );
			}
		}

		if ( ! empty( $tag_spec[ AMP_Rule_Spec::DESCENDANT_TAG_LIST ] ) ) {
			$allowed_tags = AMP_Allowed_Tags_Generated::get_descendant_tag_list( $tag_spec[ AMP_Rule_Spec::DESCENDANT_TAG_LIST ] );
			if ( ! empty( $allowed_tags ) ) {
				$this->remove_disallowed_descendants( $node, $allowed_tags, $this->get_spec_name( $node, $tag_spec ) );
			}
		}

		// After attributes have been sanitized (and potentially removed), if mandatory attribute(s) are missing, remove the element.
		$missing_mandatory_attributes = $this->get_missing_mandatory_attributes( $attr_spec_list, $node );
		if ( ! empty( $missing_mandatory_attributes ) ) {
			$sanitized = $this->remove_invalid_child(
				$node,
				[
					'code'       => self::ATTR_REQUIRED_BUT_MISSING,
					'attributes' => $missing_mandatory_attributes,
					'spec_name'  => $this->get_spec_name( $node, $tag_spec ),
				]
			);
			return $sanitized ? null : $this->get_required_script_components( $node, $tag_spec, $attr_spec_list );
		}

		if ( ! empty( $tag_spec[ AMP_Rule_Spec::MANDATORY_ANYOF ] ) ) {
			$anyof_attributes = $this->get_element_attribute_intersection( $node, $tag_spec[ AMP_Rule_Spec::MANDATORY_ANYOF ] );
			if ( 0 === count( $anyof_attributes ) ) {
				$sanitized = $this->remove_invalid_child(
					$node,
					[
						'code'                  => self::MANDATORY_ANYOF_ATTR_MISSING,
						'mandatory_anyof_attrs' => $tag_spec[ AMP_Rule_Spec::MANDATORY_ANYOF ], // @todo Temporary as value can be looked up via spec name. See https://github.com/ampproject/amp-wp/pull/3817.
						'spec_name'             => $this->get_spec_name( $node, $tag_spec ),
					]
				);
				return $sanitized ? null : $this->get_required_script_components( $node, $tag_spec, $attr_spec_list );
			}
		}

		if ( ! empty( $tag_spec[ AMP_Rule_Spec::MANDATORY_ONEOF ] ) ) {
			$oneof_attributes = $this->get_element_attribute_intersection( $node, $tag_spec[ AMP_Rule_Spec::MANDATORY_ONEOF ] );
			if ( 0 === count( $oneof_attributes ) ) {
				$sanitized = $this->remove_invalid_child(
					$node,
					[
						'code'                  => self::MANDATORY_ONEOF_ATTR_MISSING,
						'mandatory_oneof_attrs' => $tag_spec[ AMP_Rule_Spec::MANDATORY_ONEOF ], // @todo Temporary as value can be looked up via spec name. See https://github.com/ampproject/amp-wp/pull/3817.
						'spec_name'             => $this->get_spec_name( $node, $tag_spec ),
					]
				);
				return $sanitized ? null : $this->get_required_script_components( $node, $tag_spec, $attr_spec_list );
			} elseif ( count( $oneof_attributes ) > 1 ) {
				$sanitized = $this->remove_invalid_child(
					$node,
					[
						'code'                  => self::DUPLICATE_ONEOF_ATTRS,
						'duplicate_oneof_attrs' => $oneof_attributes,
						'spec_name'             => $this->get_spec_name( $node, $tag_spec ),
					]
				);
				return $sanitized ? null : $this->get_required_script_components( $node, $tag_spec, $attr_spec_list );
			}
		}

		return $this->get_required_script_components( $node, $tag_spec, $attr_spec_list );
	}

	/**
	 * Get required AMP component scripts.
	 *
	 * @param DOMElement $node           Element.
	 * @param array      $tag_spec       Tag spec.
	 * @param array      $attr_spec_list Attribute spec list.
	 * @return string[] Script component handles.
	 */
	private function get_required_script_components( DOMElement $node, $tag_spec, $attr_spec_list ) {
		$script_components = [];
		if ( ! empty( $tag_spec['requires_extension'] ) ) {
			$script_components = array_merge( $script_components, $tag_spec['requires_extension'] );
		}

		// Add required AMP components for attributes.
		foreach ( $node->attributes as $attribute ) {
			if ( isset( $attr_spec_list[ $attribute->nodeName ]['requires_extension'] ) ) {
				$script_components = array_merge(
					$script_components,
					$attr_spec_list[ $attribute->nodeName ]['requires_extension']
				);
			}
		}

		// Manually add components for attributes; this is hard-coded because attributes do not have requires_extension like tags do. See <https://github.com/ampproject/amp-wp/issues/1808>.
		if ( $node->hasAttribute( 'lightbox' ) ) {
			$script_components[] = 'amp-lightbox-gallery';
		}

		// Check if element needs amp-bind component.
		if ( $node instanceof DOMElement && ! in_array( 'amp-bind', $this->script_components, true ) ) {
			foreach ( $node->attributes as $name => $value ) {
				if ( Document::AMP_BIND_DATA_ATTR_PREFIX === substr( $name, 0, 14 ) ) {
					$script_components[] = 'amp-bind';
					break;
				}
			}
		}
		return $script_components;
	}

	/**
	 * Whether a node is missing a mandatory attribute.
	 *
	 * @param array      $attr_spec The attribute specification.
	 * @param DOMElement $node      The DOMElement of the node to check.
	 * @return bool $is_missing boolean Whether a required attribute is missing.
	 */
	public function is_missing_mandatory_attribute( $attr_spec, DOMElement $node ) {
		return 0 !== count( $this->get_missing_mandatory_attributes( $attr_spec, $node ) );
	}

	/**
	 * Get list of mandatory missing mandatory attributes.
	 *
	 * @param array      $attr_spec The attribute specification.
	 * @param DOMElement $node      The DOMElement of the node to check.
	 * @return string[] Names of missing attributes.
	 */
	private function get_missing_mandatory_attributes( $attr_spec, DOMElement $node ) {
		$missing_attributes = [];

		foreach ( $attr_spec as $attr_name => $attr_spec_rule_value ) {
			if ( empty( $attr_spec_rule_value[ AMP_Rule_Spec::MANDATORY ] ) ) {
				continue;
			}

			if ( '\u' === substr( $attr_name, 0, 2 ) ) {
				$attr_name = html_entity_decode( '&#x' . substr( $attr_name, 2 ) . ';' ); // Probably ⚡.
			}

			if ( ! $node->hasAttribute( $attr_name ) && AMP_Rule_Spec::FAIL === $this->check_attr_spec_rule_mandatory( $node, $attr_name, $attr_spec_rule_value ) ) {
				$missing_attributes[] = $attr_name;
			}
		}

		return $missing_attributes;
	}

	/**
	 * Validate element for its CDATA.
	 *
	 * @since 0.7
	 *
	 * @param DOMElement $element    Element.
	 * @param array      $cdata_spec CDATA.
	 * @return true|array True when valid or error data when invalid.
	 */
	private function validate_cdata_for_node( DOMElement $element, $cdata_spec ) {
		if (
			isset( $cdata_spec['max_bytes'] ) && strlen( $element->textContent ) > $cdata_spec['max_bytes']
			&&
			// Skip the <style amp-custom> tag, as we want to display it even with an excessive size if it passed the style sanitizer.
			// This would mean that AMP was disabled to not break the styling.
			! ( 'style' === $element->nodeName && $element->hasAttribute( 'amp-custom' ) )
		) {
			return [
				'code' => self::CDATA_TOO_LONG,
			];
		}
		if ( isset( $cdata_spec['disallowed_cdata_regex'] ) ) {
			foreach ( $cdata_spec['disallowed_cdata_regex'] as $disallowed_cdata_regex ) {
				if ( preg_match( '@' . $disallowed_cdata_regex['regex'] . '@u', $element->textContent ) ) {
					if ( isset( $disallowed_cdata_regex['error_message'] ) ) {
						// There are only a few error messages, so map them to error codes.
						switch ( $disallowed_cdata_regex['error_message'] ) {
							case 'CSS i-amphtml- name prefix':
								// The prefix used in selectors is handled by style sanitizer.
								return [ 'code' => self::INVALID_CDATA_CSS_I_AMPHTML_NAME ];
							case 'contents':
								return [ 'code' => self::INVALID_CDATA_CONTENTS ];
							case 'html comments':
								return [ 'code' => self::INVALID_CDATA_HTML_COMMENTS ];
						}
					}

					// Note: This fallback case is not currently reachable because all error messages are accounted for in the switch statement.
					return [ 'code' => self::CDATA_VIOLATES_DENYLIST ];
				}
			}
		} elseif ( isset( $cdata_spec['cdata_regex'] ) ) {
			$delimiter = false === strpos( $cdata_spec['cdata_regex'], '@' ) ? '@' : '#';
			if ( ! preg_match( $delimiter . $cdata_spec['cdata_regex'] . $delimiter . 'u', $element->textContent ) ) {
				return [ 'code' => self::MANDATORY_CDATA_MISSING_OR_INCORRECT ];
			}
		} elseif ( isset( $cdata_spec['mandatory_cdata'] ) && $cdata_spec['mandatory_cdata'] !== $element->textContent ) {
			return [ 'code' => self::MANDATORY_CDATA_MISSING_OR_INCORRECT ];
		}

		// When the CDATA is expected to be JSON, ensure it's valid JSON.
		if ( 'script' === $element->nodeName && 'application/json' === $element->getAttribute( 'type' ) ) {
			if ( '' === trim( $element->textContent ) ) {
				return [ 'code' => self::JSON_ERROR_EMPTY ];
			}

			json_decode( $element->textContent );
			$json_last_error = json_last_error();

			if ( JSON_ERROR_NONE !== $json_last_error ) {
				return [ 'code' => $this->get_json_error_code( $json_last_error ) ];
			}
		}

		return true;
	}

	/**
	 * Gets the JSON error code for the last error.
	 *
	 * @link https://www.php.net/manual/en/function.json-last-error.php#refsect1-function.json-last-error-returnvalues
	 *
	 * @param int $json_last_error The last JSON error code.
	 * @return string The error code for the last JSON error.
	 */
	private function get_json_error_code( $json_last_error ) {
		static $possible_json_errors = [
			'JSON_ERROR_CTRL_CHAR',
			'JSON_ERROR_DEPTH',
			'JSON_ERROR_STATE_MISMATCH',
			'JSON_ERROR_SYNTAX',
			'JSON_ERROR_UTF8',
		];

		foreach ( $possible_json_errors as $possible_error ) {
			if ( constant( $possible_error ) === $json_last_error ) {
				return $possible_error;
			}
		}

		return 'JSON_ERROR_SYNTAX';
	}

	/**
	 * Determines is a node is currently valid per its tag specification.
	 *
	 * Checks to see if a node's placement with the DOM is be valid for the
	 * given tag_spec. If there are restrictions placed on the type of node
	 * that can be an immediate parent or an ancestor of this node, then make
	 * sure those restrictions are met.
	 *
	 * This method has no side effects. It should not sanitize the DOM. It is purely to see if the spec matches.
	 *
	 * @since 0.5
	 *
	 * @param DOMElement $node     The node to validate.
	 * @param array      $tag_spec The specification.
	 * @return true|array True if node is valid for spec, or error data array if otherwise.
	 */
	private function validate_tag_spec_for_node( DOMElement $node, $tag_spec ) {

		if ( ! empty( $tag_spec[ AMP_Rule_Spec::MANDATORY_PARENT ] ) && ! $this->has_parent( $node, $tag_spec[ AMP_Rule_Spec::MANDATORY_PARENT ] ) ) {
			return [
				'code'                 => self::WRONG_PARENT_TAG,
				'required_parent_name' => $tag_spec[ AMP_Rule_Spec::MANDATORY_PARENT ],
			];
		}

		// Extension scripts must be in the head. Note this currently never fails because all AMP scripts are moved to the head before sanitization.
		if ( isset( $tag_spec['extension_spec'] ) && ! $this->has_parent( $node, 'head' ) ) {
			return [
				'code'                 => self::WRONG_PARENT_TAG,
				'required_parent_name' => 'head',
			];
		}

		if ( ! empty( $tag_spec[ AMP_Rule_Spec::DISALLOWED_ANCESTOR ] ) ) {
			foreach ( $tag_spec[ AMP_Rule_Spec::DISALLOWED_ANCESTOR ] as $disallowed_ancestor_node_name ) {
				if ( $this->has_ancestor( $node, $disallowed_ancestor_node_name ) ) {
					return [
						'code'                => self::DISALLOWED_TAG_ANCESTOR,
						'disallowed_ancestor' => $disallowed_ancestor_node_name,
					];
				}
			}
		}

		if ( ! empty( $tag_spec[ AMP_Rule_Spec::MANDATORY_ANCESTOR ] ) && ! $this->has_ancestor( $node, $tag_spec[ AMP_Rule_Spec::MANDATORY_ANCESTOR ] ) ) {
			return [
				'code'                   => self::MANDATORY_TAG_ANCESTOR,
				'required_ancestor_name' => $tag_spec[ AMP_Rule_Spec::MANDATORY_ANCESTOR ],
			];
		}

		if ( empty( $tag_spec[ AMP_Rule_Spec::CHILD_TAGS ] ) ) {
			return true;
		}

		$validity = $this->check_valid_children( $node, $tag_spec[ AMP_Rule_Spec::CHILD_TAGS ] );
		if ( true !== $validity ) {
			$validity['tag_spec'] = $this->get_spec_name( $node, $tag_spec );
			return $validity;
		}
		return true;
	}

	/**
	 * Checks to see if a spec is potentially valid.
	 *
	 * Checks the given node based on the attributes present in the node. This does not check every possible constraint
	 * imposed by the validator spec. It only performs the checks that are used to narrow down which set of attribute
	 * specs is most aligned with the given node. As of AMPHTML v1910161528000, the frequency of attribute spec
	 * constraints looks as follows:
	 *
	 *  433: value
	 *  400: mandatory
	 *  222: value_casei
	 *  147: disallowed_value_regex
	 *  115: value_regex
	 *  101: value_url
	 *   77: dispatch_key
	 *   17: value_regex_casei
	 *   15: requires_extension
	 *   12: alternative_names
	 *    2: value_properties
	 *
	 * The constraints that should be the most likely to differentiate one tag spec from another are:
	 *
	 * - value
	 * - mandatory
	 * - value_casei
	 *
	 * For example, there are two <amp-carousel> tag specs, one that has a mandatory lightbox attribute and another that
	 * lacks the lightbox attribute altogether. If an <amp-carousel> has the lightbox attribute, then we can rule out
	 * the tag spec without the lightbox attribute via the mandatory constraint.
	 *
	 * Additionally, there are multiple <amp-date-picker> tag specs, each which vary by the value of the 'type' attribute.
	 * By validating the type 'value' and 'value_casei' constraints here, we can narrow down the tag specs that should
	 * then be used to later validate and sanitize the element (in the sanitize_disallowed_attribute_values_in_node method).
	 *
	 * @see AMP_Tag_And_Attribute_Sanitizer::sanitize_disallowed_attribute_values_in_node()
	 *
	 * @param DOMElement $node           Node.
	 * @param array[]    $attr_spec_list Attribute Spec list.
	 *
	 * @return int Score for how well the attribute spec list matched.
	 */
	private function validate_attr_spec_list_for_node( DOMElement $node, $attr_spec_list ) {
		/*
		 * If node has no attributes there is no point in continuing, but if none of attributes
		 * in the spec list are mandatory, then we give this a score.
		 */
		if ( ! $node->hasAttributes() ) {
			foreach ( $attr_spec_list as $attr_name => $attr_spec_rule ) {
				if ( isset( $attr_spec_rule[ AMP_Rule_Spec::MANDATORY ] ) ) {
					return 0;
				}
			}
			return 1;
		}

		foreach ( $node->attributes as $attr_name => $attr_node ) {
			if ( ! isset( $attr_spec_list[ $attr_name ][ AMP_Rule_Spec::ALTERNATIVE_NAMES ] ) ) {
				continue;
			}
			foreach ( $attr_spec_list[ $attr_name ][ AMP_Rule_Spec::ALTERNATIVE_NAMES ] as $attr_alt_name ) {
				$attr_spec_list[ $attr_alt_name ] = $attr_spec_list[ $attr_name ];
			}
		}

		$score = 0;

		/*
		 * Keep track of how many of the attribute spec rules are mandatory,
		 * because if none are mandatory, then we will let this rule have a
		 * score since all the invalid attributes can just be removed.
		 */
		$mandatory_count = 0;

		/*
		 * Iterate through each attribute rule in this attr spec list and run
		 * the series of tests. Each filter is given a `$node`, an `$attr_name`,
		 * and an `$attr_spec_rule`. If the `$attr_spec_rule` seems to be valid
		 * for the given node, then the filter should increment the score by one.
		 */
		foreach ( $attr_spec_list as $attr_name => $attr_spec_rule ) {

			// If attr spec rule is empty, then it allows anything.
			if ( empty( $attr_spec_rule ) && $node->hasAttribute( $attr_name ) ) {
				$score += 2;
				continue;
			}

			// Merely having the attribute counts for something, though it may get sanitized out later.
			if ( $node->hasAttribute( $attr_name ) ) {
				$score += 2;
			}

			// If a mandatory attribute is required, and attribute exists, pass.
			if ( isset( $attr_spec_rule[ AMP_Rule_Spec::MANDATORY ] ) ) {
				$mandatory_count++;

				$result = $this->check_attr_spec_rule_mandatory( $node, $attr_name, $attr_spec_rule );
				if ( AMP_Rule_Spec::PASS === $result ) {
					$score += 2;
				} elseif ( AMP_Rule_Spec::FAIL === $result ) {
					return 0;
				}
			}

			/*
			 * Check 'value' - case sensitive
			 * Given attribute's value must exactly equal value of the rule to pass.
			 */
			if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE ] ) ) {
				$result = $this->check_attr_spec_rule_value( $node, $attr_name, $attr_spec_rule );
				if ( AMP_Rule_Spec::PASS === $result ) {
					$score += 2;
				} elseif ( AMP_Rule_Spec::FAIL === $result ) {
					return 0;
				}
			}

			/*
			 * Check 'value_casei' - case insensitive
			 * Given attribute's value must be a case insensitive match to the value of
			 * the rule to pass.
			 */
			if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_CASEI ] ) ) {
				$result = $this->check_attr_spec_rule_value_casei( $node, $attr_name, $attr_spec_rule );
				if ( AMP_Rule_Spec::PASS === $result ) {
					$score += 2;
				} elseif ( AMP_Rule_Spec::FAIL === $result ) {
					return 0;
				}
			}
		}

		// Give the spec a score if it doesn't have any mandatory attributes, since they could all be removed during sanitization.
		if ( 0 === $mandatory_count && 0 === $score ) {
			$score = 1;
		}

		return $score;
	}

	/**
	 * Get spec name for a given tag spec.
	 *
	 * @since 1.5
	 *
	 * @param DOMElement $element  Element.
	 * @param array      $tag_spec Tag spec.
	 * @return string Spec name.
	 */
	private function get_spec_name( DOMElement $element, $tag_spec ) {
		if ( isset( $tag_spec['spec_name'] ) ) {
			return $tag_spec['spec_name'];
		} elseif ( isset( $tag_spec['extension_spec']['name'] ) ) {
			return sprintf(
				'script[%s=%s]',
				'amp-mustache' === $tag_spec['extension_spec']['name'] ? 'custom-template' : 'custom-element',
				strtolower( $tag_spec['extension_spec']['name'] )
			);
		} else {
			return $element->nodeName;
		}
	}

	/**
	 * Remove invalid AMP attributes values from $node that have been implicitly disallowed.
	 *
	 * Allowed values are found $this->globally_allowed_attributes and in parameter $attr_spec_list
	 *
	 * @see \AMP_Tag_And_Attribute_Sanitizer::validate_attr_spec_list_for_node()
	 * @see https://github.com/ampproject/amphtml/blob/b692bf32880910cd52273cb41935098b86fb6725/validator/engine/validator.js#L3210-L3289
	 *
	 * @param DOMElement $node           Node.
	 * @param array[]    $attr_spec_list Attribute spec list.
	 * @return array Tuples containing attribute to remove, the error code and the error data.
	 */
	private function sanitize_disallowed_attribute_values_in_node( DOMElement $node, $attr_spec_list ) {
		$attrs_to_remove = [];
		$error_data      = null;

		foreach ( $attr_spec_list as $attr_name => $attr_val ) {
			if ( isset( $attr_spec_list[ $attr_name ][ AMP_Rule_Spec::ALTERNATIVE_NAMES ] ) ) {
				foreach ( $attr_spec_list[ $attr_name ][ AMP_Rule_Spec::ALTERNATIVE_NAMES ] as $attr_alt_name ) {
					$attr_spec_list[ $attr_alt_name ] = $attr_spec_list[ $attr_name ];
				}
			}
		}

		foreach ( $node->attributes as $attr_name => $attr_node ) {
			/*
			 * We can't remove attributes inside the 'foreach' loop without
			 * breaking the iteration. So we keep track of the attributes to
			 * remove in the first loop, then remove them in the second loop.
			 */
			if ( ! $this->is_amp_allowed_attribute( $attr_node, $attr_spec_list ) ) {
				$attrs_to_remove[] = [ $attr_node, self::DISALLOWED_ATTR, $error_data ];
				continue;
			}

			// Skip unspecified attribute, likely being data-* attribute.
			if ( ! isset( $attr_spec_list[ $attr_name ] ) ) {
				continue;
			}

			// Check the context to see if we are currently within a template tag.
			// If this is the case and the attribute value contains a template placeholder, we skip sanitization.
			if ( $this->is_inside_mustache_template( $node ) && preg_match( '/{{[^}]+?}}/', $attr_node->nodeValue ) ) {
				continue;
			}

			$attr_spec_rule = $attr_spec_list[ $attr_name ];

			/*
			 * Note that the following checks may have been previously done in validate_attr_spec_list_for_node():
			 *
			 * - check_attr_spec_rule_mandatory
			 * - check_attr_spec_rule_value
			 * - check_attr_spec_rule_value_casei
			 *
			 * They have already been checked because the tag spec should only be considered a candidate for a given
			 * node if it passes those checks, that is, if the shape of the node matches the spec close enough.
			 *
			 * However, if there was only one spec for a given tag, then the validate_attr_spec_list_for_node() would
			 * not have been called, and thus these checks need to be performed here as well.
			 */
			if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE ] ) &&
				AMP_Rule_Spec::FAIL === $this->check_attr_spec_rule_value( $node, $attr_name, $attr_spec_rule ) ) {
				$attrs_to_remove[] = [ $attr_node, self::INVALID_ATTR_VALUE, null ];
			} elseif ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_CASEI ] ) &&
				AMP_Rule_Spec::FAIL === $this->check_attr_spec_rule_value_casei( $node, $attr_name, $attr_spec_rule ) ) {
				$attrs_to_remove[] = [ $attr_node, self::INVALID_ATTR_VALUE_CASEI, null ];
			} elseif ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_REGEX ] ) &&
				AMP_Rule_Spec::FAIL === $this->check_attr_spec_rule_value_regex( $node, $attr_name, $attr_spec_rule ) ) {
				$attrs_to_remove[] = [ $attr_node, self::INVALID_ATTR_VALUE_REGEX, null ];
			} elseif ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_REGEX_CASEI ] ) &&
				AMP_Rule_Spec::FAIL === $this->check_attr_spec_rule_value_regex_casei( $node, $attr_name, $attr_spec_rule ) ) {
				$attrs_to_remove[] = [ $attr_node, self::INVALID_ATTR_VALUE_REGEX_CASEI, null ];
			} elseif ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ][ AMP_Rule_Spec::ALLOWED_PROTOCOL ] ) &&
				AMP_Rule_Spec::FAIL === $this->check_attr_spec_rule_allowed_protocol( $node, $attr_name, $attr_spec_rule ) ) {
				$attrs_to_remove[] = [ $attr_node, self::INVALID_URL_PROTOCOL, null ]; // @todo A javascript: protocol could be treated differently. It should have a JS error type.
			} elseif ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ] ) &&
				AMP_Rule_Spec::FAIL === $this->check_attr_spec_rule_valid_url( $node, $attr_name, $attr_spec_rule ) ) {
				$attrs_to_remove[] = [ $attr_node, self::INVALID_URL, null ];
			} elseif ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ] ) &&
				AMP_Rule_Spec::FAIL === $this->check_attr_spec_rule_disallowed_empty( $node, $attr_name, $attr_spec_rule ) ) {
				$attrs_to_remove[] = [ $attr_node, self::MISSING_URL, null ];
			} elseif ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ][ AMP_Rule_Spec::ALLOW_RELATIVE ] ) &&
				AMP_Rule_Spec::FAIL === $this->check_attr_spec_rule_disallowed_relative( $node, $attr_name, $attr_spec_rule ) ) {
				$attrs_to_remove[] = [ $attr_node, self::DISALLOWED_RELATIVE_URL, null ];
			} elseif ( isset( $attr_spec_rule[ AMP_Rule_Spec::DISALLOWED_VALUE_REGEX ] ) &&
				AMP_Rule_Spec::FAIL === $this->check_attr_spec_rule_disallowed_value_regex( $node, $attr_name, $attr_spec_rule ) ) {
				$attrs_to_remove[] = [ $attr_node, self::INVALID_DISALLOWED_VALUE_REGEX, null ];
			} elseif ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_PROPERTIES ] ) ) {
				$result = $this->check_attr_spec_rule_value_properties( $node, $attr_name, $attr_spec_rule );
				if ( AMP_Rule_Spec::FAIL === $result[0] ) {
					foreach ( $result[1] as $property_error ) {
						$attrs_to_remove[] = [ $attr_node, $property_error[0], $property_error[1] ];
					}
				}
			}
		}

		return $attrs_to_remove;
	}

	/**
	 * Check the validity of the layout attributes for the given element.
	 *
	 * This involves checking the layout, width, height and sizes attributes with AMP specific logic.
	 *
	 * @version 1911070201440
	 * @link https://github.com/ampproject/amphtml/blob/1911070201440/validator/engine/validator.js#L3937
	 *
	 * Adapted from the `validateLayout` method found in `validator.js` from the `ampproject/amphtml`
	 * project on GitHub.
	 *
	 * @param array[][]  $tag_spec Tag spec list.
	 * @param DOMElement $node     Tag to validate.
	 * @return true|array True if layout is valid, or error data if validation fails.
	 */
	private function is_valid_layout( $tag_spec, $node ) {
		// No need to validate if there are no specifications for the layout.
		if ( ! isset( $tag_spec['amp_layout'] ) ) {
			return true;
		}

		// We disable validating layout for tags where one of the layout attributes contains mustache syntax.
		// See <https://github.com/ampproject/amphtml/blob/19f1b72/validator/engine/validator.js#L4301-L4311>.
		if ( $this->is_inside_mustache_template( $node ) && $this->has_layout_attribute_with_mustache_variable( $node ) ) {
			return true;
		}

		$layout_attr  = $node->getAttribute( Attribute::LAYOUT );
		$sizes_attr   = $node->getAttribute( Attribute::SIZES );
		$heights_attr = $node->getAttribute( Attribute::HEIGHTS );
		$allow_fluid  = Layout::FLUID === $layout_attr;
		$allow_auto   = true;

		$input_width = new CssLength( $node->hasAttribute( Attribute::WIDTH ) ? $node->getAttribute( Attribute::WIDTH ) : null );
		$input_width->validate( $allow_auto, $allow_fluid );
		if ( ! $input_width->isValid() ) {
			return [
				'code'      => self::INVALID_LAYOUT_WIDTH,
				'attribute' => Attribute::WIDTH,
			];
		}

		$input_height = new CssLength( $node->hasAttribute( Attribute::HEIGHT ) ? $node->getAttribute( Attribute::HEIGHT ) : null );
		$input_height->validate( $allow_auto, $allow_fluid );
		if ( ! $input_height->isValid() ) {
			return [
				'code'      => self::INVALID_LAYOUT_HEIGHT,
				'attribute' => Attribute::HEIGHT,
			];
		}

		// Now calculate the effective layout attributes.
		$width  = $this->calculate_width( $tag_spec['amp_layout'], $layout_attr, $input_width );
		$height = $this->calculate_height( $tag_spec['amp_layout'], $layout_attr, $input_height );
		$layout = $this->calculate_layout( $layout_attr, $width, $height, $sizes_attr, $heights_attr );

		// Does the tag support the computed layout?
		// See <https://github.com/ampproject/amphtml/blob/19f1b72d/validator/engine/validator.js#L4364-L4391>.
		if ( ! $this->supports_layout( $tag_spec, $layout ) ) {
			/*
			 * Special case. If no layout related attributes were provided, this implies
			 * the CONTAINER layout. However, telling the user that the implied layout
			 * is unsupported for this tag is confusing if all they need is to provide
			 * width and height in, for example, the common case of creating
			 * an AMP-IMG without specifying dimensions. In this case, we emit a
			 * less correct, but simpler error message that could be more useful to
			 * the average user.
			 *
			 * See https://github.com/ampproject/amp-wp/issues/4465
			 */
			if (
				! $layout_attr &&
				Layout::CONTAINER === $layout &&
				$this->supports_layout( $tag_spec, Layout::RESPONSIVE )
			) {
				return [
					'code'      => self::MISSING_LAYOUT_ATTRIBUTES,
					'node_name' => $node->tagName,
				];
			}
			return [
				'code'      => ! $layout_attr ? self::IMPLIED_LAYOUT_INVALID : self::SPECIFIED_LAYOUT_INVALID,
				'layout'    => $layout,
				'node_name' => $node->tagName,
			];
		}

		// No need to go further if there is no layout attribute.
		if ( ! $layout_attr ) {
			return true;
		}

		// Only FLEX_ITEM allows for height to be set to auto.
		if ( $height->isAuto() && Layout::FLEX_ITEM !== $layout ) {
			return [
				'code'      => self::INVALID_LAYOUT_AUTO_HEIGHT,
				'attribute' => Attribute::HEIGHT,
			];
		}

		// FIXED, FIXED_HEIGHT, INTRINSIC, RESPONSIVE must have height set.
		if ( in_array( $layout, [ Layout::FIXED, Layout::FIXED_HEIGHT, Layout::INTRINSIC, Layout::RESPONSIVE ], true )
			&& ! $height->isDefined()
		) {
			return [
				'code'      => self::INVALID_LAYOUT_NO_HEIGHT,
				'attribute' => Attribute::HEIGHT,
			];
		}

		// For FIXED_HEIGHT if width is set it must be auto.
		if ( Layout::FIXED_HEIGHT === $layout && $width->isDefined() && ! $width->isAuto() ) {
			return [
				'code'                => self::INVALID_LAYOUT_FIXED_HEIGHT,
				'attribute'           => Attribute::WIDTH,
				'required_attr_value' => CssLength::AUTO,
			];
		}

		// FIXED, INTRINSIC, RESPONSIVE must have width set and not be auto.
		if ( in_array( $layout, [ Layout::FIXED, Layout::INTRINSIC, Layout::RESPONSIVE ], true ) ) {
			if ( ! $width->isDefined() || $width->isAuto() ) {
				return [
					'code'      => self::INVALID_LAYOUT_AUTO_WIDTH,
					'attribute' => Attribute::WIDTH,
				];
			}
		}

		// INTRINSIC, RESPONSIVE must have same units for height and width.
		if ( in_array( $layout, [ Layout::INTRINSIC, Layout::RESPONSIVE ], true )
			&& $width->getUnit() !== $height->getUnit()
		) {
			return [ 'code' => self::INVALID_LAYOUT_UNIT_DIMENSIONS ];
		}

		// Heights attribute is only allowed for RESPONSIVE layout.
		if ( ! $this->is_empty_attribute_value( $heights_attr ) && Layout::RESPONSIVE !== $layout ) {
			return [
				'code'                => self::INVALID_LAYOUT_HEIGHTS,
				'attribute'           => Attribute::LAYOUT,
				'required_attr_value' => Layout::RESPONSIVE,
			];
		}

		return true;
	}

	/**
	 * Whether the node is inside a mustache template.
	 *
	 * @since 1.5.3
	 *
	 * @param DOMElement $node The node to examine.
	 * @return bool Whether the node is inside a valid mustache template.
	 */
	private function is_inside_mustache_template( DOMElement $node ) {
		if ( ! empty( $this->open_elements[ Tag::TEMPLATE ] ) ) {
			while ( $node->parentNode instanceof DOMElement ) {
				$node = $node->parentNode;
				if ( Tag::TEMPLATE === $node->nodeName && Extension::MUSTACHE === $node->getAttribute( Attribute::TYPE ) ) {
					return true;
				}
			}
		} elseif ( ! empty( $this->open_elements[ Tag::SCRIPT ] ) ) {
			while ( $node->parentNode instanceof DOMElement ) {
				$node = $node->parentNode;
				if ( Tag::SCRIPT === $node->nodeName && Extension::MUSTACHE === $node->getAttribute( Attribute::TEMPLATE ) && Attribute::TYPE_TEXT_PLAIN === $node->getAttribute( Attribute::TYPE ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether the node has a layout attribute with variable syntax, like {{foo}}.
	 *
	 * This is important for whether to validate the layout of the node.
	 * Similar to the validation logic in the AMP validator.
	 *
	 * @see https://github.com/ampproject/amphtml/blob/c083d2c6120a251dcc9b0beb33c0336c7d3ca5a8/validator/engine/validator.js#L4038-L4054
	 *
	 * @since 1.5.3
	 *
	 * @param DOMElement $node The node to examine.
	 * @return bool Whether the node has a layout attribute with variable syntax.
	 */
	private function has_layout_attribute_with_mustache_variable( DOMElement $node ) {
		foreach ( [ Attribute::LAYOUT, Attribute::WIDTH, Attribute::HEIGHT, Attribute::SIZES, Attribute::HEIGHTS ] as $attribute ) {
			if ( preg_match( '/{{[^}]+?}}/', $node->getAttribute( $attribute ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Calculate the effective width from the input layout and input width.
	 *
	 * This involves considering that some elements, such as amp-audio and
	 * amp-pixel, have natural dimensions (browser or implementation-specific
	 * defaults for width / height).
	 *
	 * Adapted from the `CalculateWidth` method found in `validator.js` from the `ampproject/amphtml`
	 * project on GitHub.
	 *
	 * @version 1911070201440
	 * @link https://github.com/ampproject/amphtml/blob/1911070201440/validator/engine/validator.js#L3451
	 *
	 * @param array     $amp_layout_spec AMP layout specifications for tag.
	 * @param string    $input_layout    Layout for tag.
	 * @param CssLength $input_width     Parsed width.
	 * @return CssLength
	 */
	private function calculate_width( $amp_layout_spec, $input_layout, CssLength $input_width ) {
		if (
			(
				! array_key_exists( $input_layout, Layout::TO_SPEC ) ||
				Layout::FIXED === $input_layout
			) &&
			! $input_width->isDefined() &&
			isset( $amp_layout_spec['defines_default_width'] )
		) {
			$css_length = new CssLength( '1px' );
			$css_length->validate( false, false );
			return $css_length;
		}

		return $input_width;
	}

	/**
	 * Calculate the effective height from input layout and input height.
	 *
	 * Adapted from the `CalculateHeight` method found in `validator.js` from the `ampproject/amphtml`
	 * project on GitHub.
	 *
	 * @version 1911070201440
	 * @link https://github.com/ampproject/amphtml/blob/1911070201440/validator/engine/validator.js#L3493
	 *
	 * @param array     $amp_layout_spec AMP layout specifications for tag.
	 * @param string    $input_layout    Layout for tag.
	 * @param CssLength $input_height    Parsed height.
	 * @return CssLength
	 */
	private function calculate_height( $amp_layout_spec, $input_layout, CssLength $input_height ) {
		if (
			(
				! array_key_exists( $input_layout, Layout::TO_SPEC ) ||
				in_array( $input_layout, [ Layout::FIXED, Layout::FIXED_HEIGHT ], true )
			) &&
			! $input_height->isDefined() &&
			isset( $amp_layout_spec['defines_default_height'] )
		) {
			$css_length = new CssLength( '1px' );
			$css_length->validate( false, false );
			return $css_length;
		}

		return $input_height;
	}

	/**
	 * Calculate the layout.
	 *
	 * This depends on the width / height calculation above.
	 * It happens last because web designers often make
	 * fixed-sized mocks first and then the layout determines how things
	 * will change for different viewports / devices / etc.
	 *
	 * Adapted from the `CalculateLayout` method found in `validator.js` from the `ampproject/amphtml`
	 * project on GitHub.
	 *
	 * @version 1911070201440
	 * @link https://github.com/ampproject/amphtml/blob/1911070201440/validator/engine/validator.js#L3516
	 *
	 * @param string    $layout_attr  Layout attribute.
	 * @param CssLength $width        Parsed width.
	 * @param CssLength $height       Parsed height.
	 * @param string    $sizes_attr   Sizes attribute.
	 * @param string    $heights_attr Heights attribute.
	 * @return string Layout type.
	 */
	private function calculate_layout( $layout_attr, CssLength $width, CssLength $height, $sizes_attr, $heights_attr ) {
		if ( ! $this->is_empty_attribute_value( $layout_attr ) ) {
			return $layout_attr;
		} elseif ( ! $width->isDefined() && ! $height->isDefined() ) {
			return Layout::CONTAINER;
		} elseif (
			( $height->isDefined() && $height->isFluid() ) ||
			( $width->isDefined() && $width->isFluid() )
		) {
			return Layout::FLUID;
		} elseif (
			$height->isDefined() &&
			( ! $width->isDefined() || $width->isAuto() )
		) {
			return Layout::FIXED_HEIGHT;
		} elseif (
			$height->isDefined() &&
			$width->isDefined() &&
			(
				! $this->is_empty_attribute_value( $sizes_attr ) ||
				! $this->is_empty_attribute_value( $heights_attr )
			)
		) {
			return Layout::RESPONSIVE;
		} else {
			return Layout::FIXED;
		}
	}

	/**
	 * Check if attribute is mandatory determine whether it exists in $node.
	 *
	 * When checking for the given attribute it also checks valid alternates.
	 *
	 * @param DOMElement $node           Node.
	 * @param string     $attr_name      Attribute name.
	 * @param array[]    $attr_spec_rule Attribute spec rule.
	 *
	 * @return int:
	 *      - AMP_Rule_Spec::PASS - $attr_name is mandatory and it exists
	 *      - AMP_Rule_Spec::FAIL - $attr_name is mandatory, but doesn't exist
	 *      - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name is not mandatory
	 */
	private function check_attr_spec_rule_mandatory( DOMElement $node, $attr_name, $attr_spec_rule ) {
		if ( isset( $attr_spec_rule[ AMP_Rule_Spec::MANDATORY ] ) && $attr_spec_rule[ AMP_Rule_Spec::MANDATORY ] ) {
			if ( $node->hasAttribute( $attr_name ) ) {
				return AMP_Rule_Spec::PASS;
			}

			// Check if an alternative name list is specified.
			if ( isset( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] ) ) {
				foreach ( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] as $alt_name ) {
					if ( $node->hasAttribute( $alt_name ) ) {
						return AMP_Rule_Spec::PASS;
					}
				}
			}
			return AMP_Rule_Spec::FAIL;
		}
		return AMP_Rule_Spec::NOT_APPLICABLE;
	}

	/**
	 * Get the intersection of the element attributes with the supplied attributes.
	 *
	 * @param DOMElement $element         The element.
	 * @param string[]   $attribute_names The attribute names.
	 * @return string[] The attributes that matched.
	 */
	private function get_element_attribute_intersection( DOMElement $element, $attribute_names ) {
		$attributes = [];
		foreach ( $attribute_names as $attribute_name ) {
			if ( $element->hasAttribute( $attribute_name ) ) {
				$attributes[] = $attribute_name;
			}
		}
		return $attributes;
	}

	/**
	 * Check if attribute has a value rule determine if its value is valid.
	 *
	 * Checks for value validity by matches against valid values.
	 *
	 * @param DOMElement $node           Node.
	 * @param string     $attr_name      Attribute name.
	 * @param array      $attr_spec_rule Attribute spec rule.
	 *
	 * @return int:
	 *      - AMP_Rule_Spec::PASS - $attr_name has a value that matches the rule.
	 *      - AMP_Rule_Spec::FAIL - $attr_name has a value that does *not* match rule.
	 *      - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name does not exist or there
	 *                                        is no rule for this attribute.
	 */
	private function check_attr_spec_rule_value( DOMElement $node, $attr_name, $attr_spec_rule ) {
		if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE ] ) ) {
			if ( $node->hasAttribute( $attr_name ) ) {
				if ( $this->check_matching_attribute_value( $attr_name, $node->getAttribute( $attr_name ), $attr_spec_rule[ AMP_Rule_Spec::VALUE ] ) ) {
					return AMP_Rule_Spec::PASS;
				}

				return AMP_Rule_Spec::FAIL;
			}

			if ( isset( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] ) ) {
				foreach ( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] as $alternative_name ) {
					if ( $node->hasAttribute( $alternative_name ) ) {
						if ( $this->check_matching_attribute_value( $attr_name, $node->getAttribute( $alternative_name ), $attr_spec_rule[ AMP_Rule_Spec::VALUE ] ) ) {
							return AMP_Rule_Spec::PASS;
						}

						return AMP_Rule_Spec::FAIL;
					}
				}
			}
		}
		return AMP_Rule_Spec::NOT_APPLICABLE;
	}

	/**
	 * Check that an attribute's value matches is given spec value.
	 *
	 * This takes into account boolean attributes where value can match name (e.g. selected="selected").
	 *
	 * @since 0.7.0
	 * @since 1.0.0 The spec value is now an array.
	 *
	 * @param string       $attr_name   Attribute name.
	 * @param string       $attr_value  Attribute value.
	 * @param string|array $spec_values Attribute spec value(s).
	 * @return bool Is value valid.
	 */
	private function check_matching_attribute_value( $attr_name, $attr_value, $spec_values ) {
		if ( is_string( $spec_values ) ) {
			$spec_values = (array) $spec_values;
		}
		foreach ( $spec_values as $spec_value ) {
			if ( $spec_value === $attr_value || ( '' === $spec_value && strtolower( $attr_value ) === $attr_name ) ) {
				return true;
			}

			// Check for boolean attribute.
			$is_bool_match = (
				'' === $spec_value
				&&
				in_array( $attr_name, AMP_Rule_Spec::$boolean_attributes, true )
				&&
				strtolower( $attr_value ) === strtolower( $attr_name )
			);
			if ( $is_bool_match ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if attribute has a value rule determine if its value matches ignoring case.
	 *
	 * @param DOMElement   $node           Node.
	 * @param string       $attr_name      Attribute name.
	 * @param array|string $attr_spec_rule Attribute spec rule.
	 *
	 * @return int:
	 *      - AMP_Rule_Spec::PASS - $attr_name has a value that matches the rule.
	 *      - AMP_Rule_Spec::FAIL - $attr_name has a value that does *not* match rule.
	 *      - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name does not exist or there
	 *                                        is no rule for this attribute.
	 */
	private function check_attr_spec_rule_value_casei( DOMElement $node, $attr_name, $attr_spec_rule ) {
		if ( ! isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_CASEI ] ) ) {
			return AMP_Rule_Spec::NOT_APPLICABLE;
		}

		$result      = AMP_Rule_Spec::NOT_APPLICABLE;
		$rule_values = (array) $attr_spec_rule[ AMP_Rule_Spec::VALUE_CASEI ];
		foreach ( $rule_values as $rule_value ) {
			$rule_value = strtolower( $rule_value );
			if ( $node->hasAttribute( $attr_name ) ) {
				$attr_value = strtolower( $node->getAttribute( $attr_name ) );
				if ( $attr_value === $rule_value ) {
					return AMP_Rule_Spec::PASS;
				}

				$result = AMP_Rule_Spec::FAIL;
			} elseif ( isset( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] ) ) {
				foreach ( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] as $alternative_name ) {
					if ( $node->hasAttribute( $alternative_name ) ) {
						$attr_value = strtolower( $node->getAttribute( $alternative_name ) );
						if ( $attr_value === $rule_value ) {
							return AMP_Rule_Spec::PASS;
						}

						$result = AMP_Rule_Spec::FAIL;
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Check if attribute has a regex value rule determine if it matches.
	 *
	 * @param DOMElement       $node           Node.
	 * @param string           $attr_name      Attribute name.
	 * @param array[]|string[] $attr_spec_rule Attribute spec rule.
	 *
	 * @return int:
	 *      - AMP_Rule_Spec::PASS - $attr_name has a value that matches the rule.
	 *      - AMP_Rule_Spec::FAIL - $attr_name has a value that does *not* match rule.
	 *      - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name does not exist or there
	 *                                        is no rule for this attribute.
	 */
	private function check_attr_spec_rule_value_regex( DOMElement $node, $attr_name, $attr_spec_rule ) {
		// Check 'value_regex' - case sensitive regex match.
		if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_REGEX ] ) && $node->hasAttribute( $attr_name ) ) {
			$rule_value = $attr_spec_rule[ AMP_Rule_Spec::VALUE_REGEX ];

			/*
			 * The regex pattern has '^' and '$' though they are not in the AMP spec.
			 * Leaving them out would allow both '_blank' and 'yyy_blankzzz' to be
			 * matched by a regex rule of '(_blank|_self|_top)'. As the AMP JS validator
			 * only accepts '_blank' we leave it this way for now.
			 */
			if ( preg_match( '/^(' . $rule_value . ')$/u', $node->getAttribute( $attr_name ) ) ) {
				return AMP_Rule_Spec::PASS;
			}

			return AMP_Rule_Spec::FAIL;
		}
		return AMP_Rule_Spec::NOT_APPLICABLE;
	}

	/**
	 * Check if attribute has a case-insensitive regex value rule determine if it matches.
	 *
	 * @param DOMElement       $node           Node.
	 * @param string           $attr_name      Attribute name.
	 * @param array[]|string[] $attr_spec_rule Attribute spec rule.
	 *
	 * @return int:
	 *      - AMP_Rule_Spec::PASS - $attr_name has a value that matches the rule.
	 *      - AMP_Rule_Spec::FAIL - $attr_name has a value that does *not* match rule.
	 *      - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name does not exist or there
	 *                                        is no rule for this attribute.
	 */
	private function check_attr_spec_rule_value_regex_casei( DOMElement $node, $attr_name, $attr_spec_rule ) {
		// Check 'value_regex_casei' - case insensitive regex match.
		if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_REGEX_CASEI ] ) && $node->hasAttribute( $attr_name ) ) {
			$rule_value = $attr_spec_rule[ AMP_Rule_Spec::VALUE_REGEX_CASEI ];

			// See note above regarding the '^' and '$' that are added here.
			if ( preg_match( '/^(' . $rule_value . ')$/ui', $node->getAttribute( $attr_name ) ) ) {
				return AMP_Rule_Spec::PASS;
			}

			return AMP_Rule_Spec::FAIL;
		}
		return AMP_Rule_Spec::NOT_APPLICABLE;
	}

	/**
	 * Check if attribute has a valid host value
	 *
	 * @since 0.7
	 *
	 * @param DOMElement       $node           Node.
	 * @param string           $attr_name      Attribute name.
	 * @param array[]|string[] $attr_spec_rule Attribute spec rule.
	 *
	 * @return int:
	 *      - AMP_Rule_Spec::PASS - $attr_name has a value that matches the rule.
	 *      - AMP_Rule_Spec::FAIL - $attr_name has a value that does *not* match rule.
	 *      - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name does not exist or there
	 *                                        is no rule for this attribute.
	 */
	private function check_attr_spec_rule_valid_url( DOMElement $node, $attr_name, $attr_spec_rule ) {
		if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ] ) && $node->hasAttribute( $attr_name ) ) {
			foreach ( $this->extract_attribute_urls( $node->getAttributeNode( $attr_name ) ) as $url ) {
				$url = $this->normalize_url_from_attribute_value( $url );

				// Check whether the URL is parsable.
				$parts = wp_parse_url( $url );
				if ( false === $parts ) {
					return AMP_Rule_Spec::FAIL;
				}

				// Check if the protocol contains invalid chars (protocolCharIsValid: https://github.com/ampproject/amphtml/blob/af1e3a550feeafd732226202b8d1f26dcefefa18/validator/engine/parse-url.js#L31-L39).
				$protocol = $this->parse_protocol( $url );
				if ( isset( $protocol ) ) {
					if ( ! preg_match( '/^[a-zA-Z0-9\+-]+/i', $protocol ) ) {
						return AMP_Rule_Spec::FAIL;
					}
					$url = substr( $url, strlen( $protocol ) + 1 );
				}

				// Check if the host contains invalid chars (hostCharIsValid: https://github.com/ampproject/amphtml/blob/af1e3a550feeafd732226202b8d1f26dcefefa18/validator/engine/parse-url.js#L62-L103).
				$host = wp_parse_url( urldecode( $url ), PHP_URL_HOST );
				if ( $host && preg_match( '/[!"#$%&\'()*+,\/:;<=>?@[\]^`{|}~\s]/', $host ) ) {
					return AMP_Rule_Spec::FAIL;
				}
			}

			return AMP_Rule_Spec::PASS;
		}

		return AMP_Rule_Spec::NOT_APPLICABLE;
	}

	/**
	 * Parse protocol from URL.
	 *
	 * This may not be a valid protocol (scheme), but it will be where the protocol should be in the URL.
	 *
	 * @link https://github.com/ampproject/amphtml/blob/af1e3a550feeafd732226202b8d1f26dcefefa18/validator/engine/parse-url.js#L235-L282
	 * @param string $url URL.
	 * @return string|null Protocol without colon if matched. Otherwise null.
	 */
	private function parse_protocol( $url ) {
		if ( preg_match( '#^[^/]+?(?=:)#', $url, $matches ) ) {
			return $matches[0];
		}
		return null;
	}

	/**
	 * Normalize a URL that appeared as a tag attribute.
	 *
	 * @param string $url The URL to normalize.
	 * @return string The normalized URL.
	 */
	private function normalize_url_from_attribute_value( $url ) {
		return preg_replace( '/[\t\r\n]/', '', trim( $url ) );
	}

	/**
	 * Check if attribute has a protocol value rule determine if it matches.
	 *
	 * @param DOMElement       $node           Node.
	 * @param string           $attr_name      Attribute name.
	 * @param array[]|string[] $attr_spec_rule Attribute spec rule.
	 *
	 * @return int:
	 *      - AMP_Rule_Spec::PASS - $attr_name has a value that matches the rule.
	 *      - AMP_Rule_Spec::FAIL - $attr_name has a value that does *not* match rule.
	 *      - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name does not exist or there
	 *                                        is no rule for this attribute.
	 */
	private function check_attr_spec_rule_allowed_protocol( DOMElement $node, $attr_name, $attr_spec_rule ) {
		if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ][ AMP_Rule_Spec::ALLOWED_PROTOCOL ] ) ) {
			if ( $node->hasAttribute( $attr_name ) ) {
				foreach ( $this->extract_attribute_urls( $node->getAttributeNode( $attr_name ) ) as $url ) {
					$url_scheme = $this->parse_protocol( $this->normalize_url_from_attribute_value( $url ) );
					if ( isset( $url_scheme ) && ! in_array( strtolower( $url_scheme ), $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ][ AMP_Rule_Spec::ALLOWED_PROTOCOL ], true ) ) {
						return AMP_Rule_Spec::FAIL;
					}
				}
				return AMP_Rule_Spec::PASS;
			}

			if ( isset( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] ) ) {
				foreach ( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] as $alternative_name ) {
					if ( $node->hasAttribute( $alternative_name ) ) {
						foreach ( $this->extract_attribute_urls( $node->getAttributeNode( $alternative_name ), $attr_name ) as $url ) {
							$url_scheme = $this->parse_protocol( $this->normalize_url_from_attribute_value( $url ) );
							if ( isset( $url_scheme ) && ! in_array( strtolower( $url_scheme ), $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ][ AMP_Rule_Spec::ALLOWED_PROTOCOL ], true ) ) {
								return AMP_Rule_Spec::FAIL;
							}
						}
						return AMP_Rule_Spec::PASS;
					}
				}
			}
		}
		return AMP_Rule_Spec::NOT_APPLICABLE;
	}

	/**
	 * Extract URLs from attribute.
	 *
	 * @param DOMAttr     $attribute_node Attribute node.
	 * @param string|null $spec_attr_name Non-alternative attribute name for the spec.
	 * @return string[] List of URLs.
	 */
	private function extract_attribute_urls( DOMAttr $attribute_node, $spec_attr_name = null ) {
		/*
		 * Handle the srcset special case where the attribute value can contain multiple parts, each in the format `URL [WIDTH OR PIXEL_DENSITY]`.
		 * So we split the srcset attribute value by commas and then return the first token of each item, omitting width or pixel density descriptor.
		 * This splitting cannot be done for other URLs because it a comma can appear in a URL itself generally, but the syntax can break in srcset,
		 * unless the commas are URL-encoded.
		 */
		if ( 'srcset' === $attribute_node->nodeName || 'srcset' === $spec_attr_name ) {
			return array_filter(
				array_map(
					static function ( $srcset_part ) {
						// Remove descriptors for width and pixel density.
						return preg_replace( '/\s.*$/', '', trim( $srcset_part ) );
					},
					preg_split( '/\s*,\s*/', $attribute_node->nodeValue )
				)
			);
		}

		return [ $attribute_node->nodeValue ];
	}

	/**
	 * Check if attribute has disallowed relative URL value according to rule spec.
	 *
	 * @param DOMElement       $node           Node.
	 * @param string           $attr_name      Attribute name.
	 * @param array[]|string[] $attr_spec_rule Attribute spec rule.
	 *
	 * @return int:
	 *      - AMP_Rule_Spec::PASS - $attr_name has a value that matches the rule.
	 *      - AMP_Rule_Spec::FAIL - $attr_name has a value that does *not* match rule.
	 *      - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name does not exist or there
	 *                                        is no rule for this attribute.
	 */
	private function check_attr_spec_rule_disallowed_relative( DOMElement $node, $attr_name, $attr_spec_rule ) {
		if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ][ AMP_Rule_Spec::ALLOW_RELATIVE ] ) && ! $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ][ AMP_Rule_Spec::ALLOW_RELATIVE ] ) {
			if ( $node->hasAttribute( $attr_name ) ) {
				foreach ( $this->extract_attribute_urls( $node->getAttributeNode( $attr_name ) ) as $url ) {
					if ( '__amp_source_origin' === $url ) {
						return AMP_Rule_Spec::PASS;
					}

					$parsed_url = wp_parse_url( $url );

					/*
					 *  The JS AMP validator seems to consider 'relative' to mean
					 *  *protocol* relative, not *host* relative for this rule. So,
					 *  a url with an empty 'scheme' is considered "relative" by AMP.
					 *  ie. '//domain.com/path' and '/path' should both be considered
					 *  relative for purposes of AMP validation.
					 */
					if ( empty( $parsed_url['scheme'] ) ) {
						return AMP_Rule_Spec::FAIL;
					}
				}
				return AMP_Rule_Spec::PASS;
			}

			if ( isset( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] ) ) {
				foreach ( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] as $alternative_name ) {
					if ( $node->hasAttribute( $alternative_name ) ) {
						foreach ( $this->extract_attribute_urls( $node->getAttributeNode( $alternative_name ), $attr_name ) as $url ) {
							if ( '__amp_source_origin' === $url ) {
								return AMP_Rule_Spec::PASS;
							}

							$parsed_url = wp_parse_url( $url );
							if ( empty( $parsed_url['scheme'] ) ) {
								return AMP_Rule_Spec::FAIL;
							}
						}
					}
				}
				return AMP_Rule_Spec::PASS;
			}
		}
		return AMP_Rule_Spec::NOT_APPLICABLE;
	}

	/**
	 * Check if attribute has disallowed empty value rule determine if value is empty.
	 *
	 * @param DOMElement       $node           Node.
	 * @param string           $attr_name      Attribute name.
	 * @param array[]|string[] $attr_spec_rule Attribute spec rule.
	 *
	 * @return int:
	 *      - AMP_Rule_Spec::PASS - $attr_name has a value that matches the rule.
	 *      - AMP_Rule_Spec::FAIL - $attr_name has a value that does *not* match rule.
	 *      - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name does not exist or there
	 *                                        is no rule for this attribute.
	 */
	private function check_attr_spec_rule_disallowed_empty( DOMElement $node, $attr_name, $attr_spec_rule ) {
		if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ][ AMP_Rule_Spec::ALLOW_EMPTY ] ) ) {
			$allow_empty = $attr_spec_rule[ AMP_Rule_Spec::VALUE_URL ][ AMP_Rule_Spec::ALLOW_EMPTY ];
		} else {
			$allow_empty = empty( $attr_spec_rule[ AMP_Rule_Spec::MANDATORY ] );
		}
		if ( ! $allow_empty && $node->hasAttribute( $attr_name ) ) {
			$attr_value = $node->getAttribute( $attr_name );
			if ( empty( $attr_value ) ) {
				return AMP_Rule_Spec::FAIL;
			}
			return AMP_Rule_Spec::PASS;
		}
		return AMP_Rule_Spec::NOT_APPLICABLE;
	}

	/**
	 * Check if attribute has disallowed value via regex match and determine if value matches.
	 *
	 * @since 0.5
	 *
	 * @param DOMElement       $node           Node.
	 * @param string           $attr_name      Attribute name.
	 * @param array[]|string[] $attr_spec_rule Attribute spec rule.
	 *
	 * @return int:
	 *      - AMP_Rule_Spec::PASS - $attr_name has a value that matches the rule.
	 *      - AMP_Rule_Spec::FAIL - $attr_name has a value that does *not* match rule.
	 *      - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name does not exist or there
	 *                                        is no rule for this attribute.
	 */
	private function check_attr_spec_rule_disallowed_value_regex( DOMElement $node, $attr_name, $attr_spec_rule ) {
		if ( isset( $attr_spec_rule[ AMP_Rule_Spec::DISALLOWED_VALUE_REGEX ] ) ) {
			$pattern = '/' . $attr_spec_rule[ AMP_Rule_Spec::DISALLOWED_VALUE_REGEX ] . '/u';
			if ( $node->hasAttribute( $attr_name ) ) {
				$attr_value = $node->getAttribute( $attr_name );
				if ( preg_match( $pattern, $attr_value ) ) {
					return AMP_Rule_Spec::FAIL;
				}

				return AMP_Rule_Spec::PASS;
			}
			if ( isset( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] ) ) {
				foreach ( $attr_spec_rule[ AMP_Rule_Spec::ALTERNATIVE_NAMES ] as $alternative_name ) {
					if ( $node->hasAttribute( $alternative_name ) ) {
						$attr_value = $node->getAttribute( $alternative_name );
						if ( preg_match( $pattern, $attr_value ) ) {
							return AMP_Rule_Spec::FAIL;
						}

						return AMP_Rule_Spec::PASS;
					}
				}
			}
		}
		return AMP_Rule_Spec::NOT_APPLICABLE;
	}

	/**
	 * Parse properties attribute (e.g. meta viewport).
	 *
	 * @param string $value Attribute value.
	 * @return array Properties.
	 */
	private function parse_properties_attribute( $value ) {
		$properties = [];
		foreach ( explode( ',', $value ) as $pair ) {
			$pair_parts = explode( '=', $pair, 2 );
			if ( 2 !== count( $pair_parts ) ) {
				// This would occur when there are trailing commas, for example.
				continue;
			}
			$properties[ strtolower( trim( $pair_parts[0] ) ) ] = $pair_parts[1];
		}
		return $properties;
	}

	/**
	 * Serialize properties attribute (e.g. meta viewport).
	 *
	 * @param array $properties Properties.
	 * @return string Serialized properties.
	 */
	private function serialize_properties_attribute( $properties ) {
		return implode(
			',',
			array_map(
				static function ( $property_name ) use ( $properties ) {
					return $property_name . '=' . $properties[ $property_name ];
				},
				array_keys( $properties )
			)
		);
	}

	/**
	 * Check if attribute has valid properties.
	 *
	 * @since 0.7
	 *
	 * @param DOMElement       $node           Node.
	 * @param string           $attr_name      Attribute name.
	 * @param array[]|string[] $attr_spec_rule Attribute spec rule.
	 *
	 * @return array {
	 *     Results.
	 *
	 *     @type int     $result_code The result code. It can either be.
	 *                                 - AMP_Rule_Spec::PASS           - $attr_name has a value that matches the rule.
	 *                                 - AMP_Rule_Spec::FAIL           - $attr_name has a value that does *not* match rule.
	 *                                 - AMP_Rule_Spec::NOT_APPLICABLE - $attr_name does not exist or there is no rule for this attribute.
	 *     @type array[] $errors      Property errors.
	 * }
	 */
	private function check_attr_spec_rule_value_properties( DOMElement $node, $attr_name, $attr_spec_rule ) {
		if ( isset( $attr_spec_rule[ AMP_Rule_Spec::VALUE_PROPERTIES ] ) && $node->hasAttribute( $attr_name ) ) {
			$property_errors    = [];
			$properties         = $this->parse_properties_attribute( $node->getAttribute( $attr_name ) );
			$invalid_properties = array_diff( array_keys( $properties ), array_keys( $attr_spec_rule[ AMP_Rule_Spec::VALUE_PROPERTIES ] ) );

			// Fail if there are unrecognized properties.
			foreach ( $invalid_properties as $invalid_property ) {
				$property_errors[] = [
					self::DISALLOWED_PROPERTY_IN_ATTR_VALUE,
					[
						'name'  => $invalid_property,
						'value' => $properties[ $invalid_property ],
					],
				];
			}

			foreach ( $attr_spec_rule[ AMP_Rule_Spec::VALUE_PROPERTIES ] as $prop_name => $property_spec ) {
				// Mandatory property is missing.
				if ( ! empty( $property_spec['mandatory'] ) && ! isset( $properties[ $prop_name ] ) ) {
					$required_value = null;
					if ( isset( $property_spec['value'] ) ) {
						$required_value = $property_spec['value'];
					} elseif ( isset( $property_spec['value_double'] ) ) {
						$required_value = $property_spec['value_double'];
					}
					$property_errors[] = [
						self::MISSING_MANDATORY_PROPERTY,
						[
							'name'           => $prop_name,
							'required_value' => $required_value,
						],
					];
					continue;
				}

				if ( ! isset( $properties[ $prop_name ] ) ) {
					continue;
				}

				$prop_value = $properties[ $prop_name ];

				// Required value is absent, so fail.
				$required_value = null;
				if ( isset( $property_spec['value'] ) ) {
					$required_value = $property_spec['value'];
				} elseif ( isset( $property_spec['value_double'] ) ) {
					$required_value = $property_spec['value_double'];
				}
				if ( isset( $required_value ) && $prop_value !== $required_value ) {
					$property_errors[] = [
						self::MISSING_REQUIRED_PROPERTY_VALUE,
						[
							'name'           => $prop_name,
							'value'          => $prop_value,
							'required_value' => $required_value,
						],
					];
				}
			}

			if ( empty( $property_errors ) ) {
				return [ AMP_Rule_Spec::PASS, [] ];
			} else {
				return [
					AMP_Rule_Spec::FAIL,
					$property_errors,
				];
			}
		}
		return [ AMP_Rule_Spec::NOT_APPLICABLE, [] ];
	}

	/**
	 * Determine if the supplied attribute name is allowed for AMP.
	 *
	 * @since 0.5
	 *
	 * @param DOMAttr          $attr_node      Attribute node.
	 * @param array[]|string[] $attr_spec_list Attribute spec list.
	 * @return bool Return true if attribute name is valid for this attr_spec_list, false otherwise.
	 */
	private function is_amp_allowed_attribute( DOMAttr $attr_node, $attr_spec_list ) {
		$attr_name = $attr_node->nodeName;
		if (
			isset( $attr_spec_list[ $attr_name ] )
			||
			( 'data-' === substr( $attr_name, 0, 5 ) && Document::AMP_BIND_DATA_ATTR_PREFIX !== substr( $attr_name, 0, 14 ) )
			||
			// Allow the 'amp' or '⚡' attribute in <html>, like <html ⚡>.
			( 'html' === $attr_node->parentNode->nodeName && in_array( $attr_node->nodeName, [ 'amp', '⚡' ], true ) )
		) {
			return true;
		}

		$is_allowed_alt_name_attr = isset( $this->rev_alternate_attr_name_lookup[ $attr_name ], $attr_spec_list[ $this->rev_alternate_attr_name_lookup[ $attr_name ] ] );
		if ( $is_allowed_alt_name_attr ) {
			return true;
		}

		/*
		 * Handle special case for reference points which do not have to be direct children.
		 * This is noted as a special case in the AMP validator spec for amp-selector, so that is why it is
		 * a special case here in this method. It is also implemented in this way for the sake of efficiency
		 * to prevent having to waste time in process_node() merging attribute lists. For more on amp-selector's
		 * unique reference point, see:
		 * https://github.com/ampproject/amphtml/blob/1526498116488/extensions/amp-selector/validator-amp-selector.protoascii#L81-L91
		 */
		$descendant_reference_points = [
			'amp-selector'         => 'AMP-SELECTOR option',
			'amp-story-grid-layer' => 'AMP-STORY-GRID-LAYER default', // @todo Consider the more restrictive 'AMP-STORY-GRID-LAYER animate-in'.
		];
		foreach ( $descendant_reference_points as $ancestor_name => $reference_point_spec_name ) {
			if ( empty( $this->open_elements[ $ancestor_name ] ) ) {
				continue;
			}
			$reference_point_spec = AMP_Allowed_Tags_Generated::get_reference_point_spec( $reference_point_spec_name );
			if ( isset( $reference_point_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ][ $attr_name ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the supplied $node's HTML tag is allowed for AMP.
	 *
	 * @since 0.5
	 *
	 * @param DOMElement $node Node.
	 * @return bool Return true if the specified node's name is an AMP allowed tag, false otherwise.
	 */
	private function is_amp_allowed_tag( DOMElement $node ) {
		return isset( $this->allowed_tags[ $node->nodeName ] );
	}

	/**
	 * Determine if the supplied $node has a parent with the specified spec name.
	 *
	 * @since 0.5
	 *
	 * @todo It would be more robust if the the actual tag spec were looked up (see https://github.com/ampproject/amp-wp/pull/3817) and then matched against the parent. This is needed to support the spec 'subscriptions script ciphertext'.
	 *
	 * @param DOMElement $node             Node.
	 * @param string     $parent_spec_name Parent spec name, for example 'body' or 'form [method=post]'.
	 * @return bool Return true if given node has direct parent with the given name, false otherwise.
	 */
	private function has_parent( DOMElement $node, $parent_spec_name ) {
		$parsed_spec_name = $this->parse_tag_and_attributes_from_spec_name( $parent_spec_name );

		if ( ! $node->parentNode || $node->parentNode->nodeName !== $parsed_spec_name['tag_name'] ) {
			return false;
		}

		// Ensure attributes match; if not move up to the next node.
		foreach ( $parsed_spec_name['attributes'] as $attr_name => $attr_value ) {
			if ( $node instanceof DOMElement && strtolower( $node->getAttribute( $attr_name ) ) !== $attr_value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine if the supplied $node has an ancestor with the specified tag name.
	 *
	 * @since 0.5
	 *
	 * @param DOMElement $node              Node.
	 * @param string     $ancestor_tag_spec_name Ancestor tag spec name. This looks somewhat like a CSS selector, e.g. 'form div [submitting][template]'.
	 * @return bool Return true if given node has any ancestor with the give name, false otherwise.
	 */
	private function has_ancestor( DOMElement $node, $ancestor_tag_spec_name ) {
		if ( $this->get_ancestor_with_matching_spec_name( $node, $ancestor_tag_spec_name ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Parse tag name and attributes from spec name.
	 *
	 * Given a spec name like 'form [method=post]', extract the tag name 'form' and the attributes.
	 *
	 * @todo This is admittedly rudimentary. It would be more robust to actually look up the tag spec by the name and obtain the required attributes from there, but this is not necessary yet.
	 *
	 * @param string $spec_name Spec name.
	 * @return array Tag name and attributes.
	 */
	private function parse_tag_and_attributes_from_spec_name( $spec_name ) {
		static $parsed_specs = [];
		if ( isset( $parsed_specs[ $spec_name ] ) ) {
			return $parsed_specs[ $spec_name ];
		}

		$attributes = [];

		/*
		 * This matches spec names like:
		 * - body
		 * - form [method=post]
		 * - form > div [submit-error][template]
		 */
		if ( preg_match( '/^(?P<ancestors>.+? )?(?P<tag_name>[a-z0-9_-]+?)( (?P<raw_attrs>\[.+?\]))?$/i', $spec_name, $matches ) ) {
			$tag_name = $matches['tag_name'];

			if ( isset( $matches['raw_attrs'] ) ) {
				$raw_attr_pairs = explode( '][', trim( $matches['raw_attrs'], '[]' ) );
				foreach ( $raw_attr_pairs as $raw_attr_pair ) {
					$raw_attr_pair = explode( '=', $raw_attr_pair );

					$attr_key = $raw_attr_pair[0];
					$attr_val = isset( $raw_attr_pair[1] ) ? $raw_attr_pair[1] : true;

					$attributes[ $attr_key ] = $attr_val;
				}
			}
		} else {
			$tag_name = strtok( $spec_name, ' ' ); // Fallback case.
		}

		$parsed_specs[ $spec_name ] = compact( 'tag_name', 'attributes' );
		return $parsed_specs[ $spec_name ];
	}

	/**
	 * Loop through node's descendants and remove the ones that are not in the allowlist.
	 *
	 * @param DOMElement $node                Node.
	 * @param string[]   $allowed_descendants List of allowed descendant tags.
	 * @param string     $spec_name           Spec name.
	 */
	private function remove_disallowed_descendants( DOMElement $node, $allowed_descendants, $spec_name ) {
		if ( ! $node->hasChildNodes() ) {
			return;
		}

		$child_elements = [];
		for ( $i = 0; $i < $node->childNodes->length; $i++ ) {
			$child = $node->childNodes->item( $i );
			if ( $child instanceof DOMElement ) {
				$child_elements[] = $child;
			}
		}

		foreach ( $child_elements as $child_element ) {
			if ( ! in_array( $child_element->nodeName, $allowed_descendants, true ) ) {
				$this->remove_invalid_child(
					$child_element,
					[
						'code'                => self::DISALLOWED_DESCENDANT_TAG,
						'allowed_descendants' => $allowed_descendants,
						'disallowed_ancestor' => $node->parentNode->nodeName,
						'spec_name'           => $spec_name,
					]
				);
			} else {
				$this->remove_disallowed_descendants( $child_element, $allowed_descendants, $spec_name );
			}
		}
	}

	/**
	 * Check whether the node validates the constraints for children.
	 *
	 * @param DOMElement $node Node.
	 * @param array      $child_tags {
	 *     List of allowed child tag.
	 *
	 *     @type array $first_child_tag_name_oneof   List of tag names that are allowed as the first element child.
	 *     @type array $child_tag_name_oneof         List of tag names that are allowed as children.
	 *     @type int   $mandatory_num_child_tags     Mandatory number of child tags.
	 *     @type int   $mandatory_min_num_child_tags Mandatory minimum number of child tags.
	 * }
	 * @return true|array True if the element satisfies the requirements, or error data array if it should be removed.
	 */
	private function check_valid_children( DOMElement $node, $child_tags ) {
		$child_elements = [];
		for ( $i = 0; $i < $node->childNodes->length; $i++ ) {
			$child = $node->childNodes->item( $i );
			if ( $child instanceof DOMElement ) {
				$child_elements[] = $child;
			}
		}

		// If the first element is not of the required type, invalidate the entire element.
		if ( isset( $child_tags['first_child_tag_name_oneof'] ) && ! empty( $child_elements[0] ) && ! in_array( $child_elements[0]->nodeName, $child_tags['first_child_tag_name_oneof'], true ) ) {
			return [
				'code'            => self::DISALLOWED_FIRST_CHILD_TAG,
				'first_child_tag' => $child_elements[0]->nodeName,
			];
		}

		// Verify that all of the child are among the set of allowed elements.
		if ( isset( $child_tags['child_tag_name_oneof'] ) ) {
			foreach ( $child_elements as $child_element ) {
				if ( ! in_array( $child_element->nodeName, $child_tags['child_tag_name_oneof'], true ) ) {
					return [
						'code'      => self::DISALLOWED_CHILD_TAG,
						'child_tag' => $child_element->nodeName,
					];
				}
			}
		}

		// If there aren't the exact number of elements, then mark this $node as being invalid.
		if ( isset( $child_tags['mandatory_num_child_tags'] ) ) {
			$child_element_count = count( $child_elements );
			if ( $child_element_count === $child_tags['mandatory_num_child_tags'] ) {
				return true;
			} else {
				return [
					'code'                 => self::INCORRECT_NUM_CHILD_TAGS,
					'children_count'       => $child_element_count,
					'required_child_count' => $child_tags['mandatory_num_child_tags'],
				];
			}
		}

		// If there aren't enough elements, then mark this $node as being invalid.
		if ( isset( $child_tags['mandatory_min_num_child_tags'] ) ) {
			$child_element_count = count( $child_elements );
			if ( $child_element_count >= $child_tags['mandatory_min_num_child_tags'] ) {
				return true;
			} else {
				return [
					'code'                     => self::INCORRECT_MIN_NUM_CHILD_TAGS,
					'children_count'           => $child_element_count,
					'required_min_child_count' => $child_tags['mandatory_min_num_child_tags'],
				];
			}
		}

		return true;
	}

	/**
	 * Get the first ancestor node matching the specified tag name for the supplied $node.
	 *
	 * @since 0.5
	 * @todo It would be more robust if the the actual tag spec were looked up and then matched for each ancestor, but this is currently overkill.
	 *
	 * @param DOMElement $node               Node.
	 * @param string     $ancestor_spec_name Ancestor spec name, e.g. 'body' or 'form [method=post]'.
	 * @return DOMNode|null Returns an ancestor node for the name specified, or null if not found.
	 */
	private function get_ancestor_with_matching_spec_name( DOMElement $node, $ancestor_spec_name ) {
		$parsed_spec_name = $this->parse_tag_and_attributes_from_spec_name( $ancestor_spec_name );

		// Do quick check to see if the the ancestor element is even open.
		// Note first isset check is for the sake of \AMP_Tag_And_Attribute_Sanitizer_Attr_Spec_Rules_Test::test_get_ancestor_with_matching_spec_name().
		if ( isset( $this->open_elements['html'] ) && empty( $this->open_elements[ $parsed_spec_name['tag_name'] ] ) ) {
			return null;
		}

		while ( $node->parentNode instanceof DOMElement ) {
			$node = $node->parentNode;
			if ( $node->nodeName === $parsed_spec_name['tag_name'] ) {

				// Ensure attributes match; if not move up to the next node.
				foreach ( $parsed_spec_name['attributes'] as $attr_name => $attr_value ) {
					$match = (
						true === $attr_value ? $node->hasAttribute( $attr_name ) : strtolower( $node->getAttribute( $attr_name ) ) === $attr_value
					);
					if ( ! $match ) {
						continue 2;
					}
				}

				return $node;
			}
		}
		return null;
	}

	/**
	 * Replaces the given node with it's child nodes, if any
	 *
	 * Also adds them to the stack for processing by the sanitize() function.
	 *
	 * @since 0.3.3
	 * @since 1.0 Fix silently removing unrecognized elements.
	 * @see https://github.com/ampproject/amp-wp/issues/1100
	 *
	 * @param DOMElement $node Node.
	 */
	private function replace_node_with_children( DOMElement $node ) {

		// If node has no children or no parent, just remove the node.
		if ( ! $node->hasChildNodes() || ! $node->parentNode ) {
			$this->remove_node( $node );

			return;
		}

		/*
		 * If node has children, replace it with them and push children onto stack
		 *
		 * Create a DOM fragment to hold the children
		 */
		$fragment = $this->dom->createDocumentFragment();

		// Add all children to fragment/stack.
		$child = $node->firstChild;
		while ( $child ) {
			$fragment->appendChild( $child );
			$child = $node->firstChild;
		}

		// Prevent double-reporting nodes that are rejected for sanitization.
		if ( isset( $this->should_not_replace_nodes[ $node->nodeName ] ) && in_array( $node, $this->should_not_replace_nodes[ $node->nodeName ], true ) ) {
			return;
		}

		// Replace node with fragment.
		$should_replace = $this->should_sanitize_validation_error( [], compact( 'node' ) );
		if ( $should_replace ) {
			$node->parentNode->replaceChild( $fragment, $node );
		} else {
			$this->should_not_replace_nodes[ $node->nodeName ][] = $node;
		}
	}

	/**
	 * Removes a node from its parent node.
	 *
	 * If removing the node makes the parent node empty, then it will remove the parent
	 * too. It will Continue until a non-empty parent or the 'body' element is reached.
	 *
	 * @since 0.5
	 *
	 * @param DOMElement $node Node.
	 */
	private function remove_node( DOMElement $node ) {
		/**
		 * Parent.
		 *
		 * @var DOMNode $parent
		 */
		$parent = $node->parentNode;
		if ( $node && $parent ) {
			$this->remove_invalid_child( $node );
		}

		// @todo Does this parent removal even make sense anymore? Perhaps limit to <p> only.
		while ( $parent && ! $parent->hasChildNodes() && ! $parent->hasAttributes() && $this->root_element !== $parent ) {
			$node   = $parent;
			$parent = $parent->parentNode;
			if ( $parent ) {
				$parent->removeChild( $node );
			}
		}
	}

	/**
	 * Check whether a given tag spec supports a layout.
	 *
	 * @param array  $tag_spec Tag spec to check.
	 * @param string $layout   Layout to check support for. Based on the constants in the Layout interface.
	 * @param bool   $fallback Value to use for fallback if no explicit support is defined.
	 * @return bool Whether the given tag spec supports the layout.
	 */
	private function supports_layout( $tag_spec, $layout, $fallback = false ) {
		if ( ! isset( $tag_spec['amp_layout']['supported_layouts'] ) ) {
			return $fallback;
		}

		if ( ! array_key_exists( $layout, LAYOUT::TO_SPEC ) ) {
			return false;
		}

		return in_array( Layout::TO_SPEC[ $layout ], $tag_spec['amp_layout']['supported_layouts'], true );
	}
}

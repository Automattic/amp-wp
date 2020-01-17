/**
 * WordPress dependencies
 */
import { useReducer, useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { exposedActions, internalActions } from './actions';
import reducer from './reducer';

const INITIAL_STATE = {
	pages: [],
	current: null,
	selection: [],
	story: {},
	capabilities: {},
};

/**
 * More description to follow - especially about return value.
 *
 * Invariants kept by the system:
 * - Pages is always an array.
 * - All pages have a elements array.
 * - If there's at least one page, current page points to a valid page.
 * - Selection is always a unique array (and never null, never has duplicates).
 * - Pages always have a backgroundElementId property.
 * - A page can only have non-duplicated element ids, however two different pages can have the same element id.
 * - If a page has non-empty background element, it will be the id of the first element in the elements array.
 * - If selection has multiple elements, it can never include the background element.
 *
 * Invariants *not* kept by the system:
 * - New pages and objects aren't checked for id's and id's aren't validated for type.
 * - Selection isn't guaranteed to refer to objects on the current page.
 * - New pages aren't validated for type of elements property when added.
 * - No validation of keys or values in the story object.
 *
 * @return {Object} An object with keys `state`, `internal` and `api`.
 */
function useStoryReducer() {
	const [ state, dispatch ] = useReducer( reducer, INITIAL_STATE );

	const {
		internal,
		api,
	} = useMemo( () => {
		const wrapWithDispatch = ( actions ) => Object.keys( actions )
			.reduce(
				( collection, action ) => ( { ...collection, [ action ]: actions[ action ]( dispatch ) } ),
				{},
			);

		return {
			internal: wrapWithDispatch( internalActions, dispatch ),
			api: wrapWithDispatch( exposedActions, dispatch ),
		};
	}, [ dispatch ] );

	return {
		state,
		internal,
		api,
	};
}

export default useStoryReducer;

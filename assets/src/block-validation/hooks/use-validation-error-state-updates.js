/**
 * External dependencies
 */
import { isEqual } from 'lodash';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { usePrevious } from '@wordpress/compose';
import { useEffect, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { getQueryArg, isURL } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { BLOCK_VALIDATION_STORE_KEY } from '../store';

/**
 * Attempts to associate a validation error with a block current in the editor.
 *
 * @param {Object} args
 * @param {Object} args.validationError Validation error object.
 * @param {Object} args.source A single validation error source.
 * @param {number} args.currentPostId The ID of the current post.
 * @param {string[]} args.blockOrder Block client IDs in order.
 * @param {Function} args.getBlock Store selector to get a block in the current editor by client ID.
 */
export function maybeAddClientIdToValidationError( { validationError, source, currentPostId, blockOrder, getBlock } ) {
	if ( ! source.block_name || undefined === source.block_content_index ) {
		return;
	}

	if ( currentPostId !== source.post_id ) {
		return;
	}

	// Look up the block ID by index, assuming the blocks of content in the editor are the same as blocks rendered on frontend.
	const clientId = blockOrder[ source.block_content_index ];
	if ( ! clientId ) {
		return;
	}

	// Sanity check that block exists for clientId.
	const block = getBlock( clientId );
	if ( ! block ) {
		return;
	}

	// Check the block type in case a block is dynamically added/removed via the_content filter to cause alignment error.
	if ( block.name !== source.block_name ) {
		return;
	}

	validationError.clientId = clientId;
}

/**
 * Custom hook managing state updates through effect hooks.
 *
 * Handling state through a context provider might be preferable in other
 * circumstances, but in this case using a store is necessary because React
 * context is not passed down over slotfills, and we need multiple components
 * within multiple slotfills to have access to the same state.
 */
export function useValidationErrorStateUpdates() {
	const [ blockOrderBeforeSave, setBlockOrderBeforeSave ] = useState( [] );
	const [ hasRequestedPreview, setHasRequestedPreview ] = useState( false );
	const [ previousValidationErrors, setPreviousValidationErrors ] = useState( [] );
	const [ shouldValidate, setShouldValidate ] = useState( false );

	const {
		setIsFetchingErrors,
		setFetchingErrorsRequestErrorMessage,
		setReviewLink,
		setSupportLink,
		setValidationErrors,
	} = useDispatch( BLOCK_VALIDATION_STORE_KEY );

	const {
		currentPostId,
		getBlock,
		getClientIdsWithDescendants,
		isAutosavingPost,
		isEditedPostNew,
		isPreviewingPost,
		isSavingPost,
		previewLink,
		validationErrors,
	} = useSelect( ( select ) => ( {
		currentPostId: select( 'core/editor' ).getCurrentPostId(),
		getBlock: select( 'core/block-editor' ).getBlock,
		getClientIdsWithDescendants: select( 'core/block-editor' ).getClientIdsWithDescendants,
		isAutosavingPost: select( 'core/editor' ).isAutosavingPost(),
		isEditedPostNew: select( 'core/editor' ).isEditedPostNew(),
		isPreviewingPost: select( 'core/editor' ).isPreviewingPost(),
		isSavingPost: select( 'core/editor' ).isSavingPost(),
		previewLink: select( 'core/editor' ).getEditedPostPreviewLink(),
		validationErrors: select( BLOCK_VALIDATION_STORE_KEY ).getValidationErrors(),
	} ), [] );

	const wasEditedPostNew = usePrevious( isEditedPostNew );

	/**
	 * Trigger validation whens editor loads only for existing posts.
	 */
	useEffect( () => {
		if ( ! isEditedPostNew && ! wasEditedPostNew ) {
			setShouldValidate( true );
		}
	}, [ isEditedPostNew, wasEditedPostNew ] );

	/**
	 * Set flags when a post is being saved.
	 *
	 * Validation should not be triggered on autosaves with an exception of an
	 * autosave initiated by a post preview request (note that "Re-validate now"
	 * button in the sidebar issues a post preview request).
	 */
	useEffect( () => {
		if ( ! isSavingPost ) {
			return;
		}

		if ( isPreviewingPost ) {
			setShouldValidate( true );
			setHasRequestedPreview( true );
			return;
		}

		if ( isAutosavingPost ) {
			return;
		}

		setShouldValidate( true );
	}, [ isAutosavingPost, isPreviewingPost, isSavingPost ] );

	/**
	 * Fetches validation errors for the current post's URL after the editor has
	 * loaded and following subsequent saves.
	 */
	useEffect( () => {
		if ( ! shouldValidate ) {
			return;
		}

		// Indicate loading state as soon as post is started to be saved.
		if ( isSavingPost ) {
			setIsFetchingErrors( true );
			return;
		}

		// A preview link may not be available right after saving a post.
		if ( hasRequestedPreview && ! isURL( previewLink ) ) {
			return;
		}

		const data = {
			id: currentPostId,
		};

		if ( hasRequestedPreview ) {
			data.preview_nonce = getQueryArg( previewLink, 'preview_nonce' );
		}

		// The initial render is not related to `isSavingPost` flag change.
		// Still, we're fetching the errors, so the `isFetchingErrors`
		// flag should be set.
		setIsFetchingErrors( true );
		setShouldValidate( false );
		setHasRequestedPreview( false );
		setFetchingErrorsRequestErrorMessage( '' );
		setBlockOrderBeforeSave( getClientIdsWithDescendants() );

		apiFetch( {
			path: '/amp/v1/validate-post-url/',
			method: 'POST',
			data,
		} )
			.then( ( newValidation ) => {
				setValidationErrors( newValidation.results );
				setReviewLink( newValidation.review_link );
				setSupportLink( newValidation.support_link );
			} )
			.catch( ( error ) => {
				setFetchingErrorsRequestErrorMessage( error?.message || __( 'Whoops! Something went wrong.', 'amp' ) );
			} )
			.finally( () => {
				setIsFetchingErrors( false );
			} );
	}, [ currentPostId, getClientIdsWithDescendants, hasRequestedPreview, isSavingPost, previewLink, setFetchingErrorsRequestErrorMessage, setIsFetchingErrors, setReviewLink, setSupportLink, setValidationErrors, shouldValidate ] );

	/**
	 * Runs an equality check when validation errors are received before running
	 * the heavier effect.
	 */
	useEffect( () => {
		if ( validationErrors && ! isEqual( previousValidationErrors, validationErrors ) ) {
			setPreviousValidationErrors( validationErrors );
		}
	}, [ previousValidationErrors, validationErrors ] );

	/**
	 * Adds clientIds to the validation errors that are associated with blocks.
	 */
	useEffect( () => {
		const newValidationErrors = previousValidationErrors.map( ( validationError ) => {
			if ( ! validationError.error.sources ) {
				return validationError;
			}

			for ( const source of validationError.error.sources ) {
				/**
				 * The loop can finish if the validation error (which is passed
				 * by reference below) has obtained a clientId.
				 */
				if ( 'clientId' in validationError ) {
					break;
				}

				maybeAddClientIdToValidationError( {
					validationError,
					source,
					getBlock,
					blockOrder: 0 < blockOrderBeforeSave.length ? blockOrderBeforeSave : getClientIdsWithDescendants(), // blockOrderBeforeSave may be empty on initial load.
					currentPostId,
				} );
			}

			return validationError;
		} );

		setValidationErrors( newValidationErrors );
	}, [ blockOrderBeforeSave, currentPostId, getBlock, getClientIdsWithDescendants, setValidationErrors, previousValidationErrors ] );
}

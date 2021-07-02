/**
 * WordPress dependencies
 */
import { registerStore } from '@wordpress/data';

/**
 * External dependencies
 */
import {
	VALIDATION_ERROR_ACK_ACCEPTED_STATUS,
	VALIDATION_ERROR_ACK_REJECTED_STATUS,
	VALIDATION_ERROR_NEW_ACCEPTED_STATUS,
	VALIDATION_ERROR_NEW_REJECTED_STATUS,
} from 'amp-block-validation';

export const BLOCK_VALIDATION_STORE_KEY = 'amp/block-validation';

const SET_FETCHING_ERRORS_REQUEST_ERROR_MESSAGE = 'SET_FETCHING_ERRORS_REQUEST_ERROR_MESSAGE';
const SET_IS_FETCHING_ERRORS = 'SET_IS_FETCHING_ERRORS';
const SET_IS_POST_DIRTY = 'SET_IS_POST_DIRTY';
const SET_IS_SHOWING_REVIEWED = 'SET_IS_SHOWING_REVIEWED';
const SET_MAYBE_IS_POST_DIRTY = 'SET_MAYBE_IS_POST_DIRTY';
const SET_REVIEW_LINK = 'SET_REVIEW_LINK';
const SET_SUPPORT_LINK = 'SET_SUPPORT_LINK';
const SET_VALIDATION_ERRORS = 'SET_VALIDATION_ERRORS';

export const INITIAL_STATE = {
	ampCompatibilityBroken: false,
	fetchingErrorsRequestErrorMessage: '',
	isPostDirty: false,
	isFetchingErrors: false,
	isShowingReviewed: false,
	keptMarkupValidationErrors: [],
	maybeIsPostDirty: false,
	rawValidationErrors: [],
	reviewLink: null,
	supportLink: null,
	reviewedValidationErrors: [],
	unreviewedValidationErrors: [],
	validationErrors: [],
};

export function getStore( initialState ) {
	return {
		reducer: ( state = initialState, action ) => {
			switch ( action.type ) {
				case SET_FETCHING_ERRORS_REQUEST_ERROR_MESSAGE:
					return { ...state, fetchingErrorsRequestErrorMessage: action.fetchingErrorsRequestErrorMessage };

				case SET_IS_FETCHING_ERRORS:
					return { ...state, isFetchingErrors: action.isFetchingErrors };

				case SET_IS_POST_DIRTY:
					return { ...state, isPostDirty: action.isPostDirty };

				case SET_IS_SHOWING_REVIEWED:
					return { ...state, isShowingReviewed: action.isShowingReviewed };

				case SET_MAYBE_IS_POST_DIRTY:
					return { ...state, maybeIsPostDirty: action.maybeIsPostDirty };

				case SET_REVIEW_LINK:
					return { ...state, reviewLink: action.reviewLink };

				case SET_SUPPORT_LINK:
					return { ...state, supportLink: action.supportLink };

				case SET_VALIDATION_ERRORS:
					return {
						...state,
						ampCompatibilityBroken: Boolean(
							action.validationErrors.filter( ( { status } ) =>
								status === VALIDATION_ERROR_NEW_REJECTED_STATUS || status === VALIDATION_ERROR_ACK_REJECTED_STATUS,
							)?.length,
						),

						reviewedValidationErrors: action.validationErrors
							.filter( ( { status } ) =>
								status === VALIDATION_ERROR_ACK_ACCEPTED_STATUS || status === VALIDATION_ERROR_ACK_REJECTED_STATUS,
							),

						unreviewedValidationErrors: action.validationErrors
							.filter( ( { status } ) =>
								status === VALIDATION_ERROR_NEW_ACCEPTED_STATUS || status === VALIDATION_ERROR_NEW_REJECTED_STATUS,
							),

						keptMarkupValidationErrors: action.validationErrors
							.filter( ( { status } ) =>
								status === VALIDATION_ERROR_NEW_REJECTED_STATUS || status === VALIDATION_ERROR_ACK_REJECTED_STATUS,
							),

						validationErrors: action.validationErrors,
					};

				default:
					return state;
			}
		},
		actions: {
			setFetchingErrorsRequestErrorMessage: ( fetchingErrorsRequestErrorMessage ) => ( {
				type: SET_FETCHING_ERRORS_REQUEST_ERROR_MESSAGE,
				fetchingErrorsRequestErrorMessage,
			} ),
			setIsFetchingErrors: ( isFetchingErrors ) => ( {
				type: SET_IS_FETCHING_ERRORS,
				isFetchingErrors,
			} ),
			setIsPostDirty: ( isPostDirty ) => ( {
				type: SET_IS_POST_DIRTY,
				isPostDirty,
			} ),
			setIsShowingReviewed: ( isShowingReviewed ) => ( {
				type: SET_IS_SHOWING_REVIEWED,
				isShowingReviewed,
			} ),
			setMaybeIsPostDirty: ( maybeIsPostDirty ) => ( {
				type: SET_MAYBE_IS_POST_DIRTY,
				maybeIsPostDirty,
			} ),
			setReviewLink: ( reviewLink ) => ( {
				type: SET_REVIEW_LINK,
				reviewLink,
			} ),
			setSupportLink: ( supportLink ) => ( {
				type: SET_SUPPORT_LINK,
				supportLink,
			} ),
			setValidationErrors: ( validationErrors ) => ( {
				type: SET_VALIDATION_ERRORS,
				validationErrors,
			} ),
		},
		selectors: {
			getAMPCompatibilityBroken: ( { ampCompatibilityBroken } ) => ampCompatibilityBroken,
			getFetchingErrorsRequestErrorMessage: ( { fetchingErrorsRequestErrorMessage } ) => fetchingErrorsRequestErrorMessage,
			getIsFetchingErrors: ( { isFetchingErrors } ) => isFetchingErrors,
			getIsPostDirty: ( { isPostDirty } ) => isPostDirty,
			getIsShowingReviewed: ( { isShowingReviewed } ) => isShowingReviewed,
			getMaybeIsPostDirty: ( { maybeIsPostDirty } ) => maybeIsPostDirty,
			getReviewLink: ( { reviewLink } ) => reviewLink,
			getSupportLink: ( { supportLink } ) => supportLink,
			getReviewedValidationErrors: ( { reviewedValidationErrors } ) => reviewedValidationErrors,
			getUnreviewedValidationErrors: ( { unreviewedValidationErrors } ) => unreviewedValidationErrors,
			getKeptMarkupValidationErrors: ( { keptMarkupValidationErrors } ) => keptMarkupValidationErrors,
			getValidationErrors: ( { validationErrors } ) => validationErrors,
		},
		initialState,
	};
}

/**
 * Register the store for block validation.
 *
 * @param {Object} initialState Initial store state.
 */
export function createStore( initialState ) {
	registerStore( BLOCK_VALIDATION_STORE_KEY, getStore( initialState ) );
}
